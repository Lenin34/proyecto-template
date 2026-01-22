<?php

namespace App\Repository;

use App\Entity\App\Notification;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Find active notifications associated with the given regions
     *
     * @param array $regions Array of Region entities
     * @return Notification[] Returns an array of Notification objects
     */
    public function findActiveByRegions(array $regions): array
    {
        if (empty($regions)) {
            return [];
        }

        $regionIds = array_map(function($region) {
            return $region->getId();
        }, $regions);

        return $this->createQueryBuilder('n')
            ->andWhere('n.status = :status')
            ->setParameter('status', \App\Enum\Status::ACTIVE)
            ->leftJoin('n.regions', 'r')
            ->andWhere('r.id IN (:regionIds) OR r.id IS NULL')
            ->setParameter('regionIds', $regionIds)
            ->orderBy('n.created_at', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * @deprecated Use findActiveByRegions instead
     * Find active notifications associated with the given companies
     *
     * @param array $companies Array of Company entities
     * @return Notification[] Returns an array of Notification objects
     */
    public function findActiveByCompanies(array $companies): array
    {
        // Get regions from companies
        $regions = [];
        foreach ($companies as $company) {
            $region = $company->getRegion();
            if ($region && !in_array($region, $regions, true)) {
                $regions[] = $region;
            }
        }

        return $this->findActiveByRegions($regions);
    }

    //    /**
    //     * @return Notification[] Returns an array of Notification objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('n.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Notification
    //    {
    //        return $this->createQueryBuilder('n')
    //            ->andWhere('n.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
