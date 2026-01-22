<?php

namespace App\Repository;

use App\Entity\App\Company;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Company>
 */
class CompanyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Company::class);
    }

    /**
     * Find active companies associated with the given regions
     *
     * @param array $regionIds Array of region IDs
     * @return Company[] Returns an array of Company objects
     */
    public function findActiveByRegions(array $regionIds): array
    {
        if (empty($regionIds)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->andWhere('c.region IN (:regionIds)')
            ->setParameter('regionIds', $regionIds)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return Company[] Returns an array of Company objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Company
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
