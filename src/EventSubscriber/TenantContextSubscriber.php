<?php

namespace App\EventSubscriber;

use App\Service\TenantManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use App\Entity\Master\MasterUser;
use App\Entity\App\User;

/**
 * TenantContextSubscriber
 * 
 * Este subscriber es el ÚNICO responsable de establecer el contexto del tenant para cada request.
 * Actúa como un "Portero" que configura el entorno antes de que cualquier controlador se ejecute.
 * Cambios en producción
 */


class TenantContextSubscriber implements EventSubscriberInterface
{
    private TenantManager $tenantManager;
    private LoggerInterface $logger;
    private TokenStorageInterface $tokenStorage;

    public function __construct(
        TenantManager $tenantManager,
        LoggerInterface $logger,
        TokenStorageInterface $tokenStorage
    ) {
        $this->tenantManager = $tenantManager;
        $this->logger = $logger;
        $this->tokenStorage = $tokenStorage;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['onKernelRequest', 31], // Configuración temprana (Portero) - AFTER Router (32)
                ['validateUserTenantConsistency', 0], // Validación de seguridad (después del firewall)
                ['ensureTenantPersistence', -10] // Persistencia tardía (después del firewall)
            ],
            KernelEvents::RESPONSE => [['onKernelResponse', -1000]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Solo procesar la petición principal, no sub-peticiones
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        // Ignorar peticiones OPTIONS, rutas internas y favicon
        // Reforzamos la exclusión del profiler verificando también el nombre de la ruta
        $route = $request->attributes->get('_route');
        if ($request->isMethod('OPTIONS') || 
            str_starts_with($request->getPathInfo(), '/_') || 
            str_starts_with($request->getPathInfo(), '/bundles/') ||
            $request->getPathInfo() === '/favicon.ico' ||
            ($route && (strpos($route, '_wdt') !== false || strpos($route, '_profiler') !== false))) {
            return;
        }

        // 1. RESOLVER EL TENANT
        $tenant = $this->resolveTenant($request);

        if (!$tenant) {
            // Si no hay tenant, dejamos pasar (puede ser una ruta pública global o error 404 después)
            return;
        }

        // 2. VALIDAR EL TENANT
        if (!$this->tenantManager->isValidTenant($tenant)) {
            $this->logger->warning('Intento de acceso con tenant inválido', ['tenant' => $tenant]);
            // No lanzamos excepción aquí para permitir que rutas públicas sin tenant funcionen,
            // pero el TenantManager no se configurará.
            return;
        }

        // 3. CONFIGURAR EL ENTORNO (El trabajo del Portero)
        try {
            // Esto configura la conexión a la BD correcta
            $this->tenantManager->setCurrentTenant($tenant);
            
            // 4. PERSISTENCIA (Asegurar que se recuerde en la sesión)
            if ($request->hasSession()) {
                $session = $request->getSession();
                
                // DEBUG: Log session state BEFORE any changes
                $this->logger->info('[SESSION DEBUG] Before persistence', [
                    'tenant' => $tenant,
                    'session_started' => $session->isStarted(),
                    'session_id' => $session->isStarted() ? $session->getId() : 'not-started',
                    'session_tenant' => $session->isStarted() ? $session->get('_tenant') : 'not-started',
                    'session_name' => $session->getName(),
                    'has_cookies' => !empty($_COOKIE),
                    'cookie_names' => array_keys($_COOKIE ?? []),
                    'has_security_token' => $session->has('_security_main'),
                    'security_token_preview' => $session->has('_security_main') ? substr(serialize($session->get('_security_main')), 0, 100) : 'N/A'
                ]);
                
                // CRÍTICO: Forzar inicio de sesión si tenemos un tenant válido
                // Esto previene pérdida de sesión entre requests
                if (!$session->isStarted()) {
                    $session->start();
                    $this->logger->info('[SESSION DEBUG] Session started', [
                        'tenant' => $tenant,
                        'session_id' => $session->getId()
                    ]);
                }
                
                // Guardar tenant en sesión
                $currentSessionTenant = $session->get('_tenant');
                if ($currentSessionTenant !== $tenant) {
                    $this->logger->info('[SESSION DEBUG] Tenant changed in session', [
                        'old_tenant' => $currentSessionTenant,
                        'new_tenant' => $tenant
                    ]);
                    $session->set('_tenant', $tenant);
                }
                
                // DEBUG: Log session state AFTER changes
                $this->logger->info('[SESSION DEBUG] After persistence', [
                    'tenant' => $tenant,
                    'session_id' => $session->getId(),
                    'session_tenant' => $session->get('_tenant'),
                    'all_session_keys' => array_keys($session->all()),
                    'has_security_token' => $session->has('_security_main'),
                    'security_token_preview' => $session->has('_security_main') ? substr(serialize($session->get('_security_main')), 0, 100) : 'N/A'
                ]);
            }

            $this->logger->debug('Tenant configurado exitosamente', ['tenant' => $tenant]);

        } catch (\Exception $e) {
            // Log error but don't stop execution
            $this->logger->error('Error setting tenant context: ' . $e->getMessage());
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get('_route') && (strpos($request->attributes->get('_route'), '_wdt') !== false || strpos($request->attributes->get('_route'), '_profiler') !== false)) {
            return;
        }

        if ($request->hasSession()) {
            $session = $request->getSession();
            if ($session->isStarted()) {
                $this->logger->info('[SESSION DEBUG] END OF REQUEST', [
                    'route' => $request->attributes->get('_route'),
                    'session_id' => $session->getId(),
                    'has_security_token' => $session->has('_security_main'),
                    'security_token_preview' => $session->has('_security_main') ? substr(serialize($session->get('_security_main')), 0, 100) : 'N/A'
                ]);
            }
        }
    }

    /**
     * Lista de valores que NO deben ser tratados como tenants válidos
     * Esto previene que peticiones a assets estáticos corrompan la sesión
     */
    private const INVALID_TENANT_VALUES = [
        'favicon.ico',
        'robots.txt',
        'sitemap.xml',
        'apple-touch-icon.png',
        'apple-touch-icon-precomposed.png',
        'browserconfig.xml',
        'manifest.json',
        'sw.js',
        'service-worker.js',
    ];

    private function resolveTenant($request): ?string
    {
        // A. Prioridad 1: URL (ej: /ts/dashboard) - SIEMPRE TIENE PRIORIDAD ABSOLUTA
        $tenantFromRoute = $request->attributes->get('dominio');
        if ($tenantFromRoute) {
            // CRÍTICO: Verificar que el valor no sea un asset estático mal interpretado como tenant
            if (in_array($tenantFromRoute, self::INVALID_TENANT_VALUES, true)) {
                $this->logger->debug('Tenant ignorado - es un asset estático', ['value' => $tenantFromRoute]);
                return null;
            }
            
            // NUEVO: Si hay tenant en URL, verificar si difiere del de sesión y limpiarlo
            if ($request->hasSession()) {
                $session = $request->getSession();
                if ($session->isStarted()) {
                    $sessionTenant = $session->get('_tenant');
                    if ($sessionTenant && $sessionTenant !== $tenantFromRoute) {
                        $this->logger->warning('Tenant de URL difiere de sesión - limpiando sesión', [
                            'url_tenant' => $tenantFromRoute,
                            'session_tenant' => $sessionTenant
                        ]);
                        // Limpiar tenant de sesión para forzar actualización
                        $session->remove('_tenant');
                    }
                }
            }
            
            $this->logger->debug('Tenant resuelto por ruta (prioridad absoluta)', ['tenant' => $tenantFromRoute]);
            return $tenantFromRoute;
        }

        // B. Prioridad 2: JWT (Para API)
        $authHeader = $request->headers->get('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            $token = substr($authHeader, 7);
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                $payload = json_decode(base64_decode($parts[1]), true);
                if (isset($payload['tenant'])) {
                    $this->logger->debug('Tenant resuelto por JWT', ['tenant' => $payload['tenant']]);
                    return $payload['tenant'];
                }
            }
        }

        // C. Prioridad 3: Sesión (Solo si NO hay tenant en URL)
        if ($request->hasSession()) {
            $session = $request->getSession();
            $sessionCookieName = $session->getName() ?: 'CTMSESSID';
            if ($session->isStarted() || $request->cookies->has($sessionCookieName)) {
                 $tenant = $session->get('_tenant');
                 if ($tenant) {
                     $this->logger->debug('Tenant resuelto por sesión (fallback)', ['tenant' => $tenant]);
                     return $tenant;
                 }
            }
        }

        // D. Prioridad 4: Dominio (ej: tenant1.miapp.com)
        $host = $request->getHost();
        $mappings = $this->tenantManager->getDomainMappings();
        
        if (isset($mappings[$host])) {
            $this->logger->debug('Tenant resuelto por dominio', ['tenant' => $mappings[$host], 'host' => $host]);
            return $mappings[$host];
        }

        $this->logger->debug('No se pudo resolver ningún tenant', ['path' => $request->getPathInfo()]);
        return null;
    }

    /**
     * Asegura que el tenant se guarde en la sesión después de que el firewall haya actuado.
     * Esto evita forzar el inicio de sesión prematuramente.
     */
    public function ensureTenantPersistence(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        
        // Ignorar profiler también aquí
        $route = $request->attributes->get('_route');
        if ($route && (strpos($route, '_wdt') !== false || strpos($route, '_profiler') !== false)) {
            return;
        }
        
        // Si no hay sesión configurada, no hacemos nada
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $tenant = $this->tenantManager->getCurrentTenant();

        // Solo guardamos si tenemos un tenant Y la sesión ya está iniciada (por el firewall)
        if ($tenant && $session->isStarted()) {
            $currentSessionTenant = $session->get('_tenant');
            if ($currentSessionTenant !== $tenant) {
                $session->set('_tenant', $tenant);
                $this->logger->debug('Tenant persistido en sesión (late binding)', [
                    'tenant' => $tenant,
                    'route' => $route
                ]);
            }
        }
    }

    /**
     * Valida que el usuario autenticado sea consistente con el tenant actual.
     * Previene que un MasterUser mantenga sesión en un tenant normal y viceversa.
     */
    public function validateUserTenantConsistency(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Si no hay token, no hay nada que validar
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        if (!$user) {
            return;
        }

        $tenant = $this->tenantManager->getCurrentTenant();
        if (!$tenant) {
            return;
        }

        // Caso 1: MasterUser en tenant no-Master
        if ($user instanceof MasterUser && $tenant !== 'Master') {
            $this->logger->warning('SECURITY: MasterUser detected in non-Master tenant. Invalidating session.', [
                'user' => $user->getEmail(),
                'tenant' => $tenant,
                'route' => $event->getRequest()->attributes->get('_route')
            ]);
            $this->invalidateSession($event->getRequest());
            return;
        }
        
        // Caso 2: Usuario normal en tenant Master
        if ($user instanceof User && $tenant === 'Master') {
             $this->logger->warning('SECURITY: Regular User detected in Master tenant. Invalidating session.', [
                'user' => $user->getEmail(),
                'tenant' => $tenant
            ]);
            $this->invalidateSession($event->getRequest());
            return;
        }
    }

    private function invalidateSession($request): void 
    {
        $this->tokenStorage->setToken(null);
        if ($request->hasSession()) {
            $session = $request->getSession();
            // Limpiar target paths de seguridad antes de invalidar
            $session->remove('_security.main.target_path');
            $session->invalidate();
        }
    }
}
