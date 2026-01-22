<?php

namespace App\Service;

use App\Entity\App\Company;
use App\Entity\App\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\Proxy;

/**
 * Servicio para limpiar y manejar problemas de proxy en entornos multi-tenant
 */
class EntityProxyCleanerService
{
    /**
     * Limpia y recarga una entidad User para evitar problemas de proxy
     */
    public function cleanAndReloadUser(User $user, EntityManagerInterface $em): User
    {
        $userId = $user->getId();

        // Limpiar el EntityManager para evitar problemas de proxy
        $em->clear();

        // Recargar el usuario con sus relaciones Company y Regions usando una consulta explícita
        $reloadedUser = $em->createQuery(
            'SELECT u, c, r FROM App\\Entity\\App\\User u
             LEFT JOIN u.company c
             LEFT JOIN u.regions r
             WHERE u.id = :userId'
        )
        ->setParameter('userId', $userId)
        ->getOneOrNullResult();

        if (!$reloadedUser) {
            throw new \RuntimeException('Usuario no encontrado después de la recarga');
        }

        return $reloadedUser;
    }

    /**
     * Obtiene el ID de la compañía de forma segura, manejando problemas de proxy
     */
    public function getCompanyIdSafely(User $user, EntityManagerInterface $em): ?int
    {
        try {
            $company = $user->getCompany();
            
            if (!$company) {
                return null;
            }
            
            // Verificar si es un proxy y si está inicializado
            if ($company instanceof Proxy && !$company->__isInitialized()) {
                try {
                    $company->__load();
                } catch (\Exception $e) {
                    // Si falla la inicialización, la entidad no existe
                    return null;
                }
            }
            
            // Verificar que la compañía realmente existe en la base de datos actual
            $companyId = $company->getId();
            $existingCompany = $em->getRepository(Company::class)->find($companyId);
            
            return $existingCompany ? $companyId : null;
            
        } catch (\Exception $e) {
            // En caso de cualquier error, devolver null
            return null;
        }
    }

    /**
     * Limpia referencias huérfanas de Company en un User
     */
    public function cleanOrphanedCompanyReference(User $user, EntityManagerInterface $em): User
    {
        if ($user->getCompany() === null) {
            return $user;
        }

        try {
            $company = $user->getCompany();
            
            // Si es un proxy, intentar cargarlo
            if ($company instanceof Proxy && !$company->__isInitialized()) {
                $company->__load();
            }
            
            $companyId = $company->getId();
            
            // Verificar que la compañía existe en la base de datos actual
            $existingCompany = $em->getRepository(Company::class)->find($companyId);
            if (!$existingCompany) {
                // La compañía no existe, limpiar la referencia
                $user->setCompany(null);
            }
            
        } catch (\Exception $e) {
            // Si hay cualquier error accediendo a la compañía, limpiar la referencia
            $user->setCompany(null);
        }
        
        return $user;
    }

    /**
     * Verifica si una entidad es un proxy no inicializado
     */
    public function isUninitializedProxy($entity): bool
    {
        return $entity instanceof Proxy && !$entity->__isInitialized();
    }

    /**
     * Intenta inicializar un proxy de forma segura
     */
    public function safeInitializeProxy($proxy): bool
    {
        if (!$proxy instanceof Proxy) {
            return true; // No es un proxy, ya está "inicializado"
        }
        
        if ($proxy->__isInitialized()) {
            return true; // Ya está inicializado
        }
        
        try {
            $proxy->__load();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Limpia el EntityManager y recarga una entidad por su ID
     */
    public function clearAndReload(EntityManagerInterface $em, string $entityClass, $id)
    {
        $em->clear();
        return $em->getRepository($entityClass)->find($id);
    }

    /**
     * Verifica si una entidad existe realmente en la base de datos actual
     */
    public function entityExistsInCurrentDatabase(EntityManagerInterface $em, string $entityClass, $id): bool
    {
        try {
            $entity = $em->getRepository($entityClass)->find($id);
            return $entity !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Limpia todas las referencias de proxy problemáticas en un User
     */
    public function cleanAllProxyReferences(User $user, EntityManagerInterface $em): User
    {
        // Limpiar referencia de Company
        $user = $this->cleanOrphanedCompanyReference($user, $em);
        
        // Aquí se pueden agregar más limpiezas para otras relaciones si es necesario
        // Por ejemplo: Role, Regions, etc.
        
        return $user;
    }

    /**
     * Recarga completamente un User con todas sus relaciones principales
     */
    public function reloadUserWithRelations(int $userId, EntityManagerInterface $em): ?User
    {
        return $em->createQuery(
            'SELECT u, c, ro, re FROM App\\Entity\\App\\User u
             LEFT JOIN u.company c
             LEFT JOIN u.role ro
             LEFT JOIN u.regions re
             WHERE u.id = :userId'
        )
        ->setParameter('userId', $userId)
        ->getOneOrNullResult();
    }
}
