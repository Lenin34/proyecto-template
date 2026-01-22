<?php

namespace App\Repository;

use App\Entity\App\Company;
use App\Entity\App\FormTemplate;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FormTemplate>
 *
 * @method FormTemplate|null find($id, $lockMode = null, $lockVersion = null)
 * @method FormTemplate|null findOneBy(array $criteria, array $orderBy = null)
 * @method FormTemplate[]    findAll()
 * @method FormTemplate[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FormTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FormTemplate::class);
    }

    public function save(FormTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FormTemplate $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Encuentra formularios activos
     */
    public function findActive(array $orderBy = ['created_at' => 'ASC']): array
    {
        $qb = $this->createQueryBuilder('ft')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE);

        foreach ($orderBy as $field => $direction) {
            $qb->addOrderBy('ft.' . $field, $direction);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Encuentra un formulario por ID (ya no necesita validación de tenant)
     */
    public function findByIdActive(int $id): ?FormTemplate
    {
        return $this->createQueryBuilder('ft')
            ->where('ft.id = :id')
            ->andWhere('ft.status != :deletedStatus')
            ->setParameter('id', $id)
            ->setParameter('deletedStatus', Status::DELETED)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra formularios con sus campos para optimizar consultas
     */
    public function findWithFields(): array
    {
        return $this->createQueryBuilder('ft')
            ->leftJoin('ft.formTemplateFields', 'ftf')
            ->addSelect('ftf')
            ->where('ft.status = :status')
            ->andWhere('ftf.status = :fieldStatus OR ftf.status IS NULL')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('fieldStatus', Status::ACTIVE)
            ->orderBy('ft.created_at', 'ASC')
            ->addOrderBy('ftf.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta formularios activos
     */
    public function countActive(): int
    {
        return $this->createQueryBuilder('ft')
            ->select('COUNT(ft.id)')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Busca formularios por nombre
     */
    public function searchByName(string $searchTerm): array
    {
        return $this->createQueryBuilder('ft')
            ->where('ft.name LIKE :searchTerm')
            ->andWhere('ft.status = :status')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ft.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtiene formularios para caché con datos mínimos
     */
    public function findForCache(): array
    {
        $results = $this->createQueryBuilder('ft')
            ->select('ft.id, ft.name, ft.description, ft.created_at')
            ->addSelect('COUNT(DISTINCT ftf.id) as fields_count')
            ->addSelect('COUNT(DISTINCT fe.id) as responses_count')
            ->leftJoin('ft.formTemplateFields', 'ftf', 'WITH', 'ftf.status = :fieldStatus')
            ->leftJoin('ft.formEntries', 'fe', 'WITH', 'fe.status = :entryStatus')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('fieldStatus', Status::ACTIVE)
            ->setParameter('entryStatus', Status::ACTIVE)
            ->groupBy('ft.id, ft.name, ft.description, ft.created_at')
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getArrayResult();

        // Formatear para caché
        return array_map(function($result) {
            return [
                'id' => $result['id'],
                'name' => $result['name'],
                'description' => $result['description'],
                'created_at' => $result['created_at']->format('Y-m-d H:i:s'),
                'fields_count' => (int) $result['fields_count']
            ];
        }, $results);
    }

    /**
     * Encuentra formularios disponibles para una empresa específica
     * Incluye formularios sin restricciones (para todas las empresas) y formularios específicos para la empresa
     */
    public function findAvailableForCompany(Company $company): array
    {
        return $this->createQueryBuilder('ft')
            ->leftJoin('ft.companies', 'c')
            ->where('ft.status = :status')
            ->andWhere('c.id = :companyId OR c.id IS NULL')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('companyId', $company->getId())
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra formularios asignados específicamente a una empresa
     */
    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('ft')
            ->join('ft.companies', 'c')
            ->where('ft.status = :status')
            ->andWhere('c.id = :companyId')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('companyId', $company->getId())
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra formularios con sus empresas cargadas para optimizar consultas
     */
    public function findWithCompanies(): array
    {
        return $this->createQueryBuilder('ft')
            ->leftJoin('ft.companies', 'c')
            ->addSelect('c')
            ->where('ft.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra formularios disponibles para múltiples empresas
     */
    public function findAvailableForCompanies(array $companies): array
    {
        if (empty($companies)) {
            return [];
        }

        $companyIds = array_map(fn($company) => $company->getId(), $companies);

        return $this->createQueryBuilder('ft')
            ->leftJoin('ft.companies', 'c')
            ->where('ft.status = :status')
            ->andWhere('c.id IN (:companyIds) OR c.id IS NULL')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('companyIds', $companyIds)
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra formularios que no tienen restricciones de empresa (disponibles para todas)
     */
    public function findAvailableForAllCompanies(): array
    {
        return $this->createQueryBuilder('ft')
            ->leftJoin('ft.companies', 'c')
            ->where('ft.status = :status')
            ->andWhere('c.id IS NULL')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ft.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Verifica si un formulario está disponible para una empresa específica
     */
    public function isAvailableForCompany(int $formTemplateId, Company $company): bool
    {
        $result = $this->createQueryBuilder('ft')
            ->select('COUNT(ft.id)')
            ->leftJoin('ft.companies', 'c')
            ->where('ft.id = :formTemplateId')
            ->andWhere('ft.status = :status')
            ->andWhere('c.id = :companyId OR c.id IS NULL')
            ->setParameter('formTemplateId', $formTemplateId)
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('companyId', $company->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return $result > 0;
    }
}