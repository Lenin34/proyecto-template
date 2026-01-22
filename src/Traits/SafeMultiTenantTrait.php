<?php

namespace App\Traits;

use App\Entity\App\Company;
use App\Entity\App\Role;
use App\Entity\App\User;
use App\Service\SafeEntityManagerService;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Trait para controladores que necesitan manejo seguro de multi-tenancy
 */
trait SafeMultiTenantTrait
{
    /**
     * Cambia de tenant de forma segura y retorna el EntityManager
     */
    protected function switchTenantSafely(string $tenant): EntityManagerInterface
    {
        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        $safeEntityManager->switchTenantSafely($tenant);
        
        /** @var TenantManager $tenantManager */
        $tenantManager = $this->container->get(TenantManager::class);
        
        return $tenantManager->getEntityManager();
    }

    /**
     * Obtiene un User de forma segura, recargándolo si es necesario
     */
    protected function getSafeUser(User $user): ?User
    {
        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        return $safeEntityManager->getSafeUser($user);
    }

    /**
     * Obtiene una Company de forma segura
     */
    protected function getSafeCompany(?Company $company): ?Company
    {
        if (!$company) {
            return null;
        }

        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        return $safeEntityManager->getSafeCompany($company);
    }

    /**
     * Obtiene un Role de forma segura
     */
    protected function getSafeRole(?Role $role): ?Role
    {
        if (!$role) {
            return null;
        }

        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        return $safeEntityManager->getSafeRole($role);
    }

    /**
     * Persiste una entidad de forma segura
     */
    protected function persistSafely(object $entity): bool
    {
        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        return $safeEntityManager->persistSafely($entity);
    }

    /**
     * Ejecuta una operación de forma segura con manejo de errores
     */
    protected function executeOperationSafely(callable $operation): mixed
    {
        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        return $safeEntityManager->executeOperation($operation);
    }

    /**
     * Recarga una entidad de forma segura
     */
    protected function reloadEntitySafely(object $entity, string $entityClass): ?object
    {
        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        return $safeEntityManager->reloadEntity($entity, $entityClass);
    }

    /**
     * Verifica si una entidad existe en el tenant actual
     */
    protected function entityExistsInCurrentTenant(object $entity, string $entityClass): bool
    {
        /** @var SafeEntityManagerService $safeEntityManager */
        $safeEntityManager = $this->container->get(SafeEntityManagerService::class);
        
        return $safeEntityManager->entityExists($entity, $entityClass);
    }

    /**
     * Maneja un User de forma segura en el contexto actual
     * Útil para formularios y operaciones CRUD
     */
    protected function handleUserSafely(User $user, string $tenant): ?User
    {
        // Cambiar al tenant correcto
        $this->switchTenantSafely($tenant);
        
        // Obtener el usuario de forma segura
        $safeUser = $this->getSafeUser($user);
        
        if (!$safeUser) {
            return null;
        }
        
        // Limpiar relaciones problemáticas
        if ($safeUser->getCompany()) {
            $safeCompany = $this->getSafeCompany($safeUser->getCompany());
            $safeUser->setCompany($safeCompany);
        }
        
        if ($safeUser->getRole()) {
            $safeRole = $this->getSafeRole($safeUser->getRole());
            $safeUser->setRole($safeRole);
        }
        
        return $safeUser;
    }

    /**
     * Prepara un User para persistencia segura
     */
    protected function prepareUserForPersistence(User $user): User
    {
        // Limpiar relación con Company
        if ($user->getCompany()) {
            $safeCompany = $this->getSafeCompany($user->getCompany());
            $user->setCompany($safeCompany);
        }
        
        // Limpiar relación con Role
        if ($user->getRole()) {
            $safeRole = $this->getSafeRole($user->getRole());
            $user->setRole($safeRole);
        }
        
        return $user;
    }

    /**
     * Obtiene el EntityManager actual de forma segura
     */
    protected function getSafeEntityManager(): EntityManagerInterface
    {
        /** @var TenantManager $tenantManager */
        $tenantManager = $this->container->get(TenantManager::class);
        
        return $tenantManager->getEntityManager();
    }

    /**
     * Maneja errores de proxy de forma estándar
     */
    protected function handleProxyError(\Exception $e, string $context = ''): void
    {
        if (str_contains($e->getMessage(), 'Proxies\\__CG__') || 
            str_contains($e->getMessage(), 'entity identifier associated with the UnitOfWork')) {
            
            // Limpiar el EntityManager
            /** @var TenantManager $tenantManager */
            $tenantManager = $this->container->get(TenantManager::class);
            $tenantManager->clearCurrentEntityManager();
            
            $this->addFlash('error', 'Error de contexto de datos. Por favor, intenta nuevamente.');
            
            throw new \RuntimeException(
                'Proxy error detected' . ($context ? " in $context" : '') . ': ' . $e->getMessage(),
                0,
                $e
            );
        }
        
        throw $e;
    }

    /**
     * Wrapper para operaciones que pueden tener problemas de proxy
     */
    protected function safeOperation(callable $operation, string $context = ''): mixed
    {
        try {
            return $this->executeOperationSafely($operation);
        } catch (\Exception $e) {
            $this->handleProxyError($e, $context);
        }
    }
}
