<?php

namespace App\Repository;

use App\Entity\App\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Event>
 */
class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    /**
     * Find active events associated with the given companies
     *
     * @param array $companies Array of Company entities
     * @return Event[] Returns an array of Event objects
     */
    public function findActiveByCompanies(array $companies): array
    {
        if (empty($companies)) {
            return [];
        }

        $companyIds = array_map(function($company) {
            return $company->getId();
        }, $companies);

        return $this->createQueryBuilder('e')
            ->andWhere('e.status = :status')
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->join('e.companies', 'c')
            ->andWhere('c.id IN (:companyIds)')
            ->setParameter('companyIds', $companyIds)
            ->orderBy('e.created_at', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    //    /**
    //     * @return Event[] Returns an array of Event objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Event
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
