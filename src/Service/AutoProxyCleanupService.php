<?php

namespace App\Service;

use App\Entity\App\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Servicio que se encarga de limpiar automáticamente los proxies problemáticos
 */
class AutoProxyCleanupService
{
    private TenantManager $tenantManager;
    private EntityProxyCleanerService $proxyCleanerService;
    private LoggerInterface $logger;

    public function __construct(
        TenantManager $tenantManager,
        EntityProxyCleanerService $proxyCleanerService,
        LoggerInterface $logger
    ) {
        $this->tenantManager = $tenantManager;
        $this->proxyCleanerService = $proxyCleanerService;
        $this->logger = $logger;
    }

    /**
     * Ejecuta una operación de forma segura, limpiando proxies si es necesario
     * Reducido a 1 reintento para evitar operaciones duplicadas como envío de SMS
     */
    public function safeExecute(callable $operation, int $maxRetries = 1)
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                return $operation();
            } catch (\Exception $e) {
                $attempt++;

                if ($this->isProxyError($e)) {
                    $this->logger->info('Proxy error detected, attempting cleanup', [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'max_retries' => $maxRetries
                    ]);

                    // Limpiar EntityManager
                    $this->cleanupEntityManager();

                    // Si no es el último intento, continuar
                    if ($attempt < $maxRetries) {
                        continue;
                    }
                }

                // Re-lanzar la excepción si no es un error de proxy o se agotaron los intentos
                throw $e;
            }
        }
    }

    /**
     * Limpia automáticamente un User y sus relaciones problemáticas
     */
    public function autoCleanUser(User $user, EntityManagerInterface $em): User
    {
        try {
            // Intentar limpiar referencias problemáticas
            $cleanedUser = $this->proxyCleanerService->cleanAllProxyReferences($user, $em);
            
            // Si el usuario sigue teniendo problemas, recargarlo completamente
            if ($this->hasProxyProblems($cleanedUser)) {
                $cleanedUser = $this->proxyCleanerService->cleanAndReloadUser($cleanedUser, $em);
            }
            
            return $cleanedUser;
            
        } catch (\Exception $e) {
            $this->logger->warning('Failed to auto-clean user', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            
            // Como último recurso, intentar recargar el usuario desde la base de datos
            try {
                return $this->proxyCleanerService->reloadUserWithRelations($user->getId(), $em);
            } catch (\Exception $reloadException) {
                // Si todo falla, devolver el usuario original
                return $user;
            }
        }
    }

    /**
     * Verifica si un User tiene problemas de proxy
     */
    public function hasProxyProblems(User $user): bool
    {
        try {
            // Intentar acceder a las relaciones principales
            $company = $user->getCompany();
            if ($company && $this->proxyCleanerService->isUninitializedProxy($company)) {
                return true;
            }
            
            $role = $user->getRole();
            if ($role && $this->proxyCleanerService->isUninitializedProxy($role)) {
                return true;
            }
            
            return false;
            
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Limpia el EntityManager actual
     */
    public function cleanupEntityManager(): void
    {
        try {
            $this->tenantManager->clearCurrentEntityManager();
        } catch (\Exception $e) {
            $this->logger->warning('Failed to cleanup EntityManager', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Verifica si una excepción es un error de proxy
     */
    private function isProxyError(\Exception $e): bool
    {
        $message = $e->getMessage();
        
        return str_contains($message, 'Proxies\\__CG__') ||
               str_contains($message, 'entity identifier associated with the UnitOfWork') ||
               str_contains($message, 'EntityManager is closed') ||
               (str_contains($message, 'proxy') && str_contains($message, 'Doctrine'));
    }

    /**
     * Ejecuta una operación con un User de forma segura
     */
    public function safeUserOperation(User $user, EntityManagerInterface $em, callable $operation)
    {
        return $this->safeExecute(function() use ($user, $em, $operation) {
            $cleanUser = $this->autoCleanUser($user, $em);
            return $operation($cleanUser);
        });
    }

    /**
     * Obtiene de forma segura el ID de la compañía de un usuario
     */
    public function safeGetUserCompanyId(User $user, EntityManagerInterface $em): ?int
    {
        return $this->safeExecute(function() use ($user, $em) {
            $cleanUser = $this->autoCleanUser($user, $em);
            return $this->proxyCleanerService->getCompanyIdSafely($cleanUser, $em);
        });
    }

    /**
     * Persiste y hace flush de forma segura
     */
    public function safePersistAndFlush(EntityManagerInterface $em, $entity): void
    {
        $this->safeExecute(function() use ($em, $entity) {
            if ($entity instanceof User) {
                $entity = $this->autoCleanUser($entity, $em);
            }
            
            $em->persist($entity);
            $em->flush();
        });
    }
}
