<?php

namespace App\Traits;

use App\Entity\App\Company;
use App\Entity\App\User;
use App\Service\EntityProxyCleanerService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\Proxy;

/**
 * Trait para manejar problemas de proxy en controladores
 */
trait ProxyHandlerTrait
{
    /**
     * Maneja de forma segura el acceso a una entidad User, limpiando problemas de proxy
     */
    protected function safeGetUser(User $user, EntityManagerInterface $em, ?EntityProxyCleanerService $proxyCleanerService = null): User
    {
        if ($proxyCleanerService) {
            return $proxyCleanerService->cleanAndReloadUser($user, $em);
        }

        // Fallback si no hay servicio disponible
        return $this->fallbackCleanUser($user, $em);
    }

    /**
     * Obtiene de forma segura el ID de una compañía, manejando problemas de proxy
     */
    protected function safeGetCompanyId(User $user, EntityManagerInterface $em, ?EntityProxyCleanerService $proxyCleanerService = null): ?int
    {
        if ($proxyCleanerService) {
            return $proxyCleanerService->getCompanyIdSafely($user, $em);
        }

        // Fallback si no hay servicio disponible
        return $this->fallbackGetCompanyId($user, $em);
    }

    /**
     * Verifica si una entidad es un proxy problemático
     */
    protected function isProblematicProxy($entity): bool
    {
        if (!$entity instanceof Proxy) {
            return false;
        }

        if (!$entity->__isInitialized()) {
            return true;
        }

        try {
            // Intentar acceder a una propiedad básica
            $entity->getId();
            return false;
        } catch (\Exception $e) {
            return true;
        }
    }

    /**
     * Limpia referencias de proxy problemáticas en un User (fallback)
     */
    private function fallbackCleanUser(User $user, EntityManagerInterface $em): User
    {
        try {
            // Limpiar referencia de Company si es problemática
            $company = $user->getCompany();
            if ($company && $this->isProblematicProxy($company)) {
                $user->setCompany(null);
            }

            return $user;
        } catch (\Exception $e) {
            // En caso de error, recargar el usuario desde la base de datos
            $userId = $user->getId();
            $em->clear();
            
            $reloadedUser = $em->createQuery(
                'SELECT u, c FROM App\\Entity\\App\\User u LEFT JOIN u.company c WHERE u.id = :userId'
            )
            ->setParameter('userId', $userId)
            ->getOneOrNullResult();

            return $reloadedUser ?: $user;
        }
    }

    /**
     * Obtiene el ID de la compañía de forma segura (fallback)
     */
    private function fallbackGetCompanyId(User $user, EntityManagerInterface $em): ?int
    {
        try {
            $company = $user->getCompany();
            
            if (!$company) {
                return null;
            }

            if ($this->isProblematicProxy($company)) {
                return null;
            }

            $companyId = $company->getId();
            
            // Verificar que la compañía existe en la base de datos
            $existingCompany = $em->getRepository(Company::class)->find($companyId);
            
            return $existingCompany ? $companyId : null;
            
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Ejecuta una operación de forma segura, manejando errores de proxy
     */
    protected function safeExecute(callable $operation, $fallbackValue = null)
    {
        try {
            return $operation();
        } catch (\Exception $e) {
            // Si el error está relacionado con proxies, devolver el valor de fallback
            if (str_contains($e->getMessage(), 'Proxies\\__CG__') ||
                str_contains($e->getMessage(), 'entity identifier associated with the UnitOfWork') ||
                str_contains($e->getMessage(), 'proxy')) {
                return $fallbackValue;
            }
            
            // Re-lanzar otros errores
            throw $e;
        }
    }

    /**
     * Limpia el EntityManager si hay problemas de proxy
     */
    protected function clearEntityManagerOnProxyError(\Exception $e, EntityManagerInterface $em): void
    {
        if (str_contains($e->getMessage(), 'Proxies\\__CG__') ||
            str_contains($e->getMessage(), 'entity identifier associated with the UnitOfWork') ||
            str_contains($e->getMessage(), 'proxy')) {
            
            try {
                if ($em->isOpen()) {
                    $em->clear();
                }
            } catch (\Exception $clearException) {
                // Ignorar errores al limpiar
            }
        }
    }
}
