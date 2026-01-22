<?php

namespace App\Service;

use AllowDynamicProperties;
use App\Entity\Master\Tenant;
use App\Enum\Status;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use App\Service\TenantLoggerService;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

#[AllowDynamicProperties]
class TenantManager
{

    private ?string $currentTenant = null;
    private ManagerRegistry $doctrine;
    private RequestStack $requestStack;
    private array $connections = [];
    private array $validatedTenants = [];
    private array $tenantCache = [];
    private ?EntityManagerInterface $cachedEntityManager = null;
    private ?string $cachedEntityManagerTenant = null;
    private bool $isCli = false;
    private ?string $cliTenant = null;

    // Mapeo rápido para evitar consultar Master en tenants conocidos
    private array $predefinedTenants = [
        'ts' => 'msc-app-ts',
        'rs' => 'msc-app-rs',
        'SNT' => 'msc-app-snt',
        'issemym' => 'msc-app-issemym',
        'Master' => 'Master',
        'app' => 'msc-app-main' // Añadido based on your logs
    ];

    // Mapeo de dominios externos a tenants internos
    // NOTA: Este mapeo solo se usa como FALLBACK cuando no hay tenant en la URL
    // La prioridad es: 1) Ruta (/rs/, /ts/), 2) JWT, 3) Sesión, 4) Dominio
    // Si accedes a sindicato.grupooptimo.mx/rs/login, el tenant será 'rs' (de la ruta)
    private array $domainToTenantMapping = [
        'sindicato.grupooptimo.mx' => 'rs', // Tenant por defecto para este dominio
        // 'localhost' => 'rs', // Comentado para evitar conflictos en desarrollo multi-tenant
        // '127.0.0.1' => 'rs', 
        // Agregar más dominios según sea necesario
    ];

    public function __construct(
        ManagerRegistry $doctrine,
        RequestStack $requestStack
    ) {
        $this->doctrine = $doctrine;
        $this->requestStack = $requestStack;
        $this->isCli = php_sapi_name() === 'cli';
    }

    public function setCurrentTenant(string $tenant): void
    {
        if ($this->currentTenant === $tenant) {
            return;
        }

        if (!$this->isValidTenant($tenant)) {
            $allowedTenants = implode(', ', array_keys($this->predefinedTenants));
            throw new NotFoundHttpException(
                sprintf('Tenant "%s" no es válido. Los tenants permitidos son: %s', $tenant, $allowedTenants)
            );
        }

        // Para CLI, almacenar el tenant específico
        if ($this->isCli) {
            $this->cliTenant = $tenant;
            $this->currentTenant = $tenant;
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        $session = ($request && $request->hasSession()) ? $request->getSession() : null;

        // DEBUG: Log tenant change detection
        error_log(sprintf(
            '[TenantManager] setCurrentTenant called: current=%s, new=%s, session_started=%s',
            $this->currentTenant ?? 'null',
            $tenant,
            $session && $session->isStarted() ? 'yes' : 'no'
        ));

        // Cambio de tenant detectado - manejo más conservador
        if ($this->currentTenant !== null && $this->currentTenant !== $tenant && $session && $session->isStarted()) {
            error_log(sprintf(
                '[TenantManager] TENANT CHANGE DETECTED! Clearing session data. From %s to %s',
                $this->currentTenant,
                $tenant
            ));
            
            // Solo limpiar datos específicos de tenant en lugar de toda la sesión
            $this->clearTenantSpecificData($session);

            // Limpiar solo las conexiones relacionadas con el tenant anterior
            $this->clearEntityManager($this->currentTenant);
        }

        $this->currentTenant = $tenant;

        // Establecer el tenant en la sesión de forma segura
        if ($session) {
            // Si la sesión no está iniciada, NO la forzamos.
            // Dejamos que el firewall o la aplicación la inicien cuando sea necesario.
            if ($session->isStarted()) {
                $session->set('_tenant', $tenant);
            }
        }
    }

    public function getCurrentTenant(): ?string
    {
        // 1. Prioridad: CLI context
        if ($this->isCli && $this->cliTenant) {
            return $this->cliTenant;
        }

        // 2. Tenant establecido manualmente
        if ($this->currentTenant) {
            return $this->currentTenant;
        }

        $request = $this->requestStack->getCurrentRequest();

        // 3. Si no hay request (CLI o contexto sin request)
        if (!$request) {
            return $this->getDefaultTenant();
        }

        // 4. Tenant de la ruta
        $routeTenant = $request->attributes->get('dominio');
        if ($routeTenant && $this->isValidTenant($routeTenant)) {
            return $routeTenant;
        }

        // 5. Tenant en sesión (usando clave unificada _tenant)
        // La sesión tiene prioridad sobre el mapeo de dominio para mantener la persistencia
        $session = $request->hasSession() ? $request->getSession() : null;
        $tenantFromSession = $session ? $session->get('_tenant') : null;

        if ($tenantFromSession && $this->isValidTenant($tenantFromSession)) {
            return $tenantFromSession;
        }

        // 6. Resolver tenant basándose en el dominio de la request (como fallback de la sesión)
        $tenantFromDomain = $this->resolveTenantFromDomain($request);
        if ($tenantFromDomain && $this->isValidTenant($tenantFromDomain)) {
            return $tenantFromDomain;
        }

        // 7. Fallback al tenant por defecto
        return $this->getDefaultTenant();
    }

    public function getEntityManager(?string $tenant = null): EntityManagerInterface
    {
        $tenant = $tenant ?? $this->getCurrentTenant();

        if (!$tenant) {
            throw new NotFoundHttpException('No se ha establecido un tenant actual.');
        }

        // Debug logging
        error_log("[TenantManager] getEntityManager called with tenant: " . $tenant);
        error_log("[TenantManager] Current cached tenant: " . ($this->cachedEntityManagerTenant ?? 'null'));
        error_log("[TenantManager] Has cached EM: " . ($this->cachedEntityManager ? 'yes' : 'no'));

        // Verificar si hay un EntityManager cacheado y válido para el tenant correcto
        if ($this->cachedEntityManager !== null &&
            $this->cachedEntityManagerTenant === $tenant &&
            $this->cachedEntityManager->isOpen()) {
            $connection = $this->cachedEntityManager->getConnection();

            // Verificar conectividad básica (isOpen es ligero)
            // No ejecutar 'SELECT 1' agresivamente en cada llamada para evitar overhead y reset innecesario
            return $this->cachedEntityManager;
        }

        try {
            $em = $this->doctrine->getManager($tenant);

            if (!$em->isOpen()) {
                $this->doctrine->resetManager($tenant);
                $em = $this->doctrine->getManager($tenant);
            }

            // Obtener conexión y validar configuración
            $connection = $em->getConnection();

            // Validar que la conexión es correcta para el tenant
            $expectedDbName = $this->predefinedTenants[$tenant] ?? null;
            $actualDbName = $connection->getParams()['dbname'] ?? 'unknown';

            // Forzar conexión ejecutando una query simple para validar conectividad
            // Esto reemplaza el método deprecado connect()
            try {
                $connection->executeQuery('SELECT 1');
            } catch (\Exception $e) {
                throw new \RuntimeException(sprintf(
                    'No se pudo establecer conexión para tenant "%s": %s',
                    $tenant, $e->getMessage()
                ));
            }

            if ($expectedDbName && $actualDbName !== $expectedDbName) {
                throw new \RuntimeException(sprintf(
                    'EntityManager para tenant "%s" conectado a base incorrecta. Esperado: %s, Actual: %s',
                    $tenant, $expectedDbName, $actualDbName
                ));
            }

            $this->cachedEntityManager = $em;
            $this->cachedEntityManagerTenant = $tenant;

            error_log("[TenantManager] Created and cached new EM for tenant: " . $tenant . ", DB: " . $actualDbName);
            return $em;
        } catch (\Exception $e) {
            unset($this->connections[$tenant]);
            throw new NotFoundHttpException(sprintf(
                'No se pudo obtener el EntityManager para el tenant "%s". Error: %s',
                $tenant,
                $e->getMessage()
            ));
        }
    }

    private function getDefaultTenant(): string
    {
        // Usar 'ts' como fallback ya que es el default_entity_manager configurado
        return 'ts';
    }

    /**
     * Resuelve el tenant basándose en el dominio de la request
     */
    private function resolveTenantFromDomain(Request $request): ?string
    {
        $host = $request->getHost();

        // Verificar mapeo directo de dominio a tenant
        if (isset($this->domainToTenantMapping[$host])) {
            return $this->domainToTenantMapping[$host];
        }

        // Para desarrollo local con puertos, extraer solo el host
        if (str_contains($host, ':')) {
            $hostWithoutPort = explode(':', $host)[0];
            if (isset($this->domainToTenantMapping[$hostWithoutPort])) {
                return $this->domainToTenantMapping[$hostWithoutPort];
            }
        }

        return null;
    }

    /**
     * Agrega un mapeo de dominio a tenant
     */
    public function addDomainMapping(string $domain, string $tenant): void
    {
        $this->domainToTenantMapping[$domain] = $tenant;
    }

    /**
     * Obtiene todos los mapeos de dominio a tenant
     */
    public function getDomainMappings(): array
    {
        return $this->domainToTenantMapping;
    }

    private function clearTenantSpecificData(SessionInterface $session): void
    {
        // Limpiar solo datos específicos de tenant en lugar de toda la sesión
        $keysToRemove = [];
        foreach ($session->all() as $key => $value) {
            if (strpos($key, 'tenant_') === 0) {
                $keysToRemove[] = $key;
            }
        }

        foreach ($keysToRemove as $key) {
            $session->remove($key);
        }
    }

    private function clearEntityManager(string $tenant): void
    {
        if (isset($this->connections[$tenant])) {
            unset($this->connections[$tenant]);
        }

        if ($this->cachedEntityManager !== null && $this->cachedEntityManager->isOpen()) {
            $this->cachedEntityManager->close();
            $this->cachedEntityManager = null;
            $this->cachedEntityManagerTenant = null;
        }

        $this->doctrine->resetManager($tenant);
    }

    public function isValidTenant(string $tenant): bool
    {
        // Rechazar explícitamente archivos estáticos o favicon
        if ($tenant === 'favicon.ico' || str_ends_with($tenant, '.ico') || str_ends_with($tenant, '.css') || str_ends_with($tenant, '.js')) {
            return false;
        }

        if (isset($this->validatedTenants[$tenant])) {
            return $this->validatedTenants[$tenant];
        }

        // Validación rápida sin consultar Master
        if (isset($this->predefinedTenants[$tenant])) {
            $this->validatedTenants[$tenant] = true;
            return true;
        }

        $this->validatedTenants[$tenant] = array_key_exists($tenant, $this->getAllowedTenants());
        return $this->validatedTenants[$tenant];
    }

    public function getAllowedTenants(): array
    {
        // 1) Si ya hay caché, devolverla
        if (!empty($this->tenantCache)) {
            return $this->tenantCache;
        }

        // 2) Precargar mapeo conocido sin consultar Master
        if (!empty($this->predefinedTenants)) {
            $this->tenantCache = $this->predefinedTenants;
        }

        // 3) Intentar enriquecer desde Master, pero no fallar si no está disponible
        try {
            $em = $this->doctrine->getManager('Master');
            $tenants = $em->getRepository(Tenant::class)->findBy(['status' => Status::ACTIVE]);

            $fromDb = array_reduce($tenants, function ($carry, $tenant) {
                $carry[$tenant->getDominio()] = $tenant->getDatabaseName();
                return $carry;
            }, []);

            // Mezclar dejando que la BD sobrescriba si hay diferencias
            $this->tenantCache = array_merge($this->tenantCache, $fromDb);
        } catch (\Throwable $e) {
            // Silencioso: seguimos con los predefinidos
        }

        return $this->tenantCache;
    }

    public function getTenantDatabaseName(string $tenant): string
    {
        $databaseMapping = $this->getAllowedTenants();

        if (!isset($databaseMapping[$tenant])) {
            throw new NotFoundHttpException(sprintf('No hay base de datos configurada para el tenant "%s".', $tenant));
        }

        return $databaseMapping[$tenant];
    }
    public function forceResetTenant(string $tenant): void
    {
        try {
            $this->clearEntityManager($tenant);
        } catch (\Exception $e) {
            // Silenciar errores
        }
    }

    public function clearCurrentEntityManager(): void
    {
        if ($this->cachedEntityManager !== null) {
            if ($this->cachedEntityManager->isOpen()) {
                $this->cachedEntityManager->close();
            }
            $this->cachedEntityManager = null;
            $this->cachedEntityManagerTenant = null;
        }
    }
    public function clearAllEntityManagers(): void
    {
        try {
            $managerNames = array_keys($this->doctrine->getManagerNames());

            foreach ($managerNames as $emName) {
                try {
                    $em = $this->doctrine->getManager($emName);
                    if ($em->isOpen()) {
                        $em->clear();
                        $em->close();
                    }
                    // Resetear para forzar nueva conexión en el siguiente uso
                    $this->doctrine->resetManager($emName);
                } catch (\Throwable $e) {
                    // Ignorar errores de entity managers no disponibles
                    continue;
                }
            }

            // Limpiar EM cacheado y conexiones internas
            if ($this->cachedEntityManager !== null) {
                if ($this->cachedEntityManager->isOpen()) {
                    $this->cachedEntityManager->close();
                }
                $this->cachedEntityManager = null;
                $this->cachedEntityManagerTenant = null;
            }
            $this->connections = [];
        } catch (\Throwable $e) {
            // En caso de error, no interrumpir la aplicación
        }
    }


    public function setCliContext(bool $isCli, ?string $tenant = null): void
    {
        $this->isCli = $isCli;
        if ($tenant) {
            $this->cliTenant = $tenant;
        }
    }
}