<?php

namespace App\Repository;

use App\Entity\App\Benefit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Benefit>
 */
class BenefitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Benefit::class);
    }

    /**
     * Find active benefits associated with the given companies
     *
     * @param array $companies Array of Company entities
     * @return Benefit[] Returns an array of Benefit objects
     */
    public function findActiveByCompanies(array $companies): array
    {
        if (empty($companies)) {
            return [];
        }

        $companyIds = array_map(function($company) {
            return $company->getId();
        }, $companies);

        return $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->join('b.companies', 'c')
            ->andWhere('c.id IN (:companyIds)')
            ->setParameter('companyIds', $companyIds)
            ->orderBy('b.created_at', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * Find active benefits for a company, paginated (ManyToMany)
     *
     * @param Company $company
     * @param int $page
     * @param int|null $perPage
     * @return \Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function findActiveBenefitsByCompany(\App\Entity\App\Company $company, int $page = 1, ?int $perPage = null)
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->join('b.companies', 'c')
            ->andWhere('c = :company')
            ->setParameter('company', $company)
            ->orderBy('b.created_at', 'DESC');

        if ($perPage !== null) {
            $qb->setFirstResult(($page - 1) * $perPage)
               ->setMaxResults($perPage);
        }

        return new \Doctrine\ORM\Tools\Pagination\Paginator($qb);
    }

    //    /**
    //     * @return Benefit[] Returns an array of Benefit objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('b.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Benefit
    //    {
    //        return $this->createQueryBuilder('b')
    //            ->andWhere('b.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
