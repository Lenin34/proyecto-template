<?php

namespace App\Service;

use App\Entity\App\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Servicio centralizado para aplicar filtros de acceso basados en región/empresa
 *
 * Jerarquía de roles:
 * - ROLE_ADMIN: Acceso total a todas las regiones y empresas
 * - ROLE_LIDER: Acceso solo a sus regiones asignadas y empresas de esas regiones
 * - ROLE_USER: Sin acceso administrativo
 */
class RegionAccessService
{
    private Security $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    /**
     * Verifica si el usuario actual tiene rol de administrador
     */
    public function isAdmin(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return in_array('ROLE_ADMIN', $user->getRoles(), true);
    }

    /**
     * Verifica si el usuario actual tiene rol de líder
     */
    public function isLider(): bool
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return false;
        }
        return in_array('ROLE_LIDER', $user->getRoles(), true);
    }

    /**
     * Verifica si se debe aplicar filtro de región/empresa
     * Retorna false para ADMIN (ve todo), true para LIDER
     */
    public function shouldApplyFilter(): bool
    {
        // Admin ve todo, no necesita filtro
        if ($this->isAdmin()) {
            return false;
        }
        // Lider necesita filtro por sus regiones
        return $this->isLider();
    }

    /**
     * Obtiene los IDs de las regiones del usuario actual
     *
     * @return int[] Array de IDs de regiones
     */
    public function getUserRegionIds(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $regionIds = [];
        foreach ($user->getRegions() as $region) {
            $regionIds[] = $region->getId();
        }
        return $regionIds;
    }

    /**
     * Obtiene los IDs de las empresas de las regiones del usuario actual
     *
     * @return int[] Array de IDs de empresas
     */
    public function getUserCompanyIds(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return [];
        }

        $companyIds = [];
        foreach ($user->getRegions() as $region) {
            foreach ($region->getCompanies() as $company) {
                $companyIds[] = $company->getId();
            }
        }
        return array_unique($companyIds);
    }

    /**
     * Aplica filtro directo por región a una entidad que tiene relación directa con Region
     * Ejemplo: Company tiene un campo 'region' directo
     *
     * @param QueryBuilder $qb QueryBuilder a modificar
     * @param string $entityAlias Alias de la entidad principal (ej: 'e', 'c')
     * @param string $regionField Nombre del campo de región en la entidad (ej: 'region')
     * @return QueryBuilder QueryBuilder modificado
     */
    public function applyRegionFilterDirect(QueryBuilder $qb, string $entityAlias, string $regionField = 'region'): QueryBuilder
    {
        // Admin ve todo
        if ($this->isAdmin()) {
            return $qb;
        }

        // Solo aplicar filtro para LIDER
        if (!$this->isLider()) {
            return $qb;
        }

        $regionIds = $this->getUserRegionIds();

        if (empty($regionIds)) {
            // Sin regiones asignadas = sin acceso
            return $qb->andWhere('1 = 0');
        }

        return $qb->andWhere("{$entityAlias}.{$regionField} IN (:userRegionIds)")
                  ->setParameter('userRegionIds', $regionIds);
    }

    /**
     * Aplica filtro por empresas a entidades que tienen relación ManyToOne con Company
     * Ejemplo: SocialMedia tiene un campo 'company' directo
     *
     * @param QueryBuilder $qb QueryBuilder a modificar
     * @param string $companyAlias Alias del join de company (ej: 'c')
     * @param bool $allowGlobal Si true, también muestra registros sin empresa asignada (globales)
     * @return QueryBuilder QueryBuilder modificado
     */
    public function applyCompanyFilter(QueryBuilder $qb, string $companyAlias = 'c', bool $allowGlobal = false): QueryBuilder
    {
        // Admin ve todo
        if ($this->isAdmin()) {
            return $qb;
        }

        // Solo aplicar filtro para LIDER
        if (!$this->isLider()) {
            return $qb;
        }

        $companyIds = $this->getUserCompanyIds();

        if (empty($companyIds)) {
            if ($allowGlobal) {
                // Sin empresas pero puede ver globales
                return $qb->andWhere("{$companyAlias}.id IS NULL");
            }
            // Sin empresas asignadas = sin acceso
            return $qb->andWhere('1 = 0');
        }

        if ($allowGlobal) {
            // Mostrar registros de sus empresas O registros globales (sin empresa)
            return $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in("{$companyAlias}.id", ':userCompanyIds'),
                    $qb->expr()->isNull("{$companyAlias}.id")
                )
            )->setParameter('userCompanyIds', $companyIds);
        }

        // Solo registros de sus empresas
        return $qb->andWhere("{$companyAlias}.id IN (:userCompanyIds)")
                  ->setParameter('userCompanyIds', $companyIds);
    }

    /**
     * Aplica filtro por empresas a entidades que tienen relación ManyToMany con Company
     * Ejemplo: Event, Benefit, Notification tienen colección 'companies'
     *
     * @param QueryBuilder $qb QueryBuilder a modificar
     * @param string $companiesAlias Alias del join de companies (ej: 'c')
     * @param bool $allowGlobal Si true, también muestra registros sin empresas asignadas (globales)
     * @return QueryBuilder QueryBuilder modificado
     */
    public function applyCompaniesFilter(QueryBuilder $qb, string $companiesAlias = 'c', bool $allowGlobal = true): QueryBuilder
    {
        // Admin ve todo
        if ($this->isAdmin()) {
            return $qb;
        }

        // Solo aplicar filtro para LIDER
        if (!$this->isLider()) {
            return $qb;
        }

        $companyIds = $this->getUserCompanyIds();

        if (empty($companyIds)) {
            if ($allowGlobal) {
                // Sin empresas pero puede ver globales
                return $qb->andWhere("{$companiesAlias}.id IS NULL");
            }
            // Sin empresas asignadas = sin acceso
            return $qb->andWhere('1 = 0');
        }

        if ($allowGlobal) {
            // Mostrar registros de sus empresas O registros globales (sin empresa)
            return $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in("{$companiesAlias}.id", ':userCompanyIds'),
                    $qb->expr()->isNull("{$companiesAlias}.id")
                )
            )->setParameter('userCompanyIds', $companyIds);
        }

        // Solo registros de sus empresas
        return $qb->andWhere("{$companiesAlias}.id IN (:userCompanyIds)")
                  ->setParameter('userCompanyIds', $companyIds);
    }

    /**
     * Apply region-based filtering for entities with ManyToMany relationship with Region
     * Similar to applyCompaniesFilter but for regions
     *
     * @param QueryBuilder $qb QueryBuilder a modificar
     * @param string $regionsAlias Alias del join de regions (ej: 'r')
     * @param bool $allowGlobal Si true, también muestra registros sin regiones asignadas (globales)
     * @return QueryBuilder QueryBuilder modificado
     */
    public function applyRegionsFilter(QueryBuilder $qb, string $regionsAlias = 'r', bool $allowGlobal = true): QueryBuilder
    {
        // Admin ve todo
        if ($this->isAdmin()) {
            return $qb;
        }

        // Solo aplicar filtro para LIDER
        if (!$this->isLider()) {
            return $qb;
        }

        $regionIds = $this->getUserRegionIds();

        if (empty($regionIds)) {
            if ($allowGlobal) {
                // Sin regiones pero puede ver globales
                return $qb->andWhere("{$regionsAlias}.id IS NULL");
            }
            // Sin regiones asignadas = sin acceso
            return $qb->andWhere('1 = 0');
        }

        if ($allowGlobal) {
            // Mostrar registros de sus regiones O registros globales (sin región)
            return $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->in("{$regionsAlias}.id", ':userRegionIds'),
                    $qb->expr()->isNull("{$regionsAlias}.id")
                )
            )->setParameter('userRegionIds', $regionIds);
        }

        // Solo registros de sus regiones
        return $qb->andWhere("{$regionsAlias}.id IN (:userRegionIds)")
                  ->setParameter('userRegionIds', $regionIds);
    }

    /**
     * Apply region-based filtering to a query builder based on the current user's role
     * Para entidades con relación ManyToMany con Region (ej: User tiene 'regions')
     *
     * @param QueryBuilder $queryBuilder The query builder to modify
     * @param string $entityAlias The alias used for the main entity in the query
     * @param string $regionJoinPath The path to join to the regions table (e.g., 'e.regions')
     * @return QueryBuilder The modified query builder
     */
    public function applyRegionFilter(QueryBuilder $queryBuilder, string $entityAlias, string $regionJoinPath): QueryBuilder
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if (!$user) {
            return $queryBuilder;
        }

        $roles = $user->getRoles();

        // ROLE_ADMIN has access to all regions, no filtering needed
        if (in_array('ROLE_ADMIN', $roles)) {
            return $queryBuilder;
        }

        // ROLE_LIDER has access only to assigned regions
        if (in_array('ROLE_LIDER', $roles)) {
            $regions = $user->getRegions();

            if ($regions->isEmpty()) {
                // If no regions assigned, return no results
                return $queryBuilder->andWhere('1 = 0');
            }

            $regionIds = [];
            foreach ($regions as $region) {
                $regionIds[] = $region->getId();
            }

            // Join with regions if not already joined
            $joinParts = $queryBuilder->getDQLPart('join');
            $isJoined = false;

            if (!empty($joinParts)) {
                foreach ($joinParts as $joinPart) {
                    foreach ($joinPart as $join) {
                        if ($join->getJoin() === $regionJoinPath) {
                            $isJoined = true;
                            break 2;
                        }
                    }
                }
            }

            if (!$isJoined) {
                $queryBuilder->join($regionJoinPath, 'reg');
            }

            return $queryBuilder->andWhere('reg.id IN (:regionIds)')
                ->setParameter('regionIds', $regionIds);
        }

        return $queryBuilder;
    }
}
