<?php

namespace App\Repository;

use App\Entity\App\SocialMedia;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SocialMedia>
 */
class SocialMediaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SocialMedia::class);
    }

    /**
     * Encuentra todos los posts de redes sociales activos ordenados por fecha de creación
     * @return SocialMedia[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('s.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra posts activos por empresa
     * @return SocialMedia[]
     */
    public function findActiveByCompany(int $companyId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.company = :companyId')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('companyId', $companyId)
            ->orderBy('s.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra posts activos en un rango de fechas
     * @return SocialMedia[]
     */
    public function findActiveInDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.status = :status')
            ->andWhere('s.start_date >= :startDate')
            ->andWhere('s.end_date <= :endDate')
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('s.start_date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
