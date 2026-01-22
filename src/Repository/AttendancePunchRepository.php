<?php

namespace App\Repository;

use App\Entity\App\AttendancePunch;
use App\Entity\App\Company;
use App\Entity\App\User;
use App\Enum\PunchType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttendancePunch>
 */
class AttendancePunchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttendancePunch::class);
    }

    /**
     * Encuentra punches de un usuario en una fecha específica
     */
    public function findByUserAndDate(User $user, \DateTimeInterface $date): array
    {
        $startDate = $date->format('Y-m-d 00:00:00');
        $endDate = $date->format('Y-m-d 23:59:59');

        return $this->createQueryBuilder('ap')
            ->where('ap.user = :user')
            ->andWhere('ap.punch_datetime BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ap.punch_datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra punches de una empresa en un rango de fechas
     */
    public function findByCompanyAndDateRange(
        Company $company, 
        \DateTimeInterface $startDate, 
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ap')
            ->join('ap.user', 'u')
            ->addSelect('u')
            ->where('ap.company = :company')
            ->andWhere('ap.punch_datetime BETWEEN :startDate AND :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('ap.punch_datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra el último punch de un usuario
     */
    public function findLastPunchByUser(User $user): ?AttendancePunch
    {
        return $this->createQueryBuilder('ap')
            ->where('ap.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ap.punch_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra el último punch de un tipo específico para un usuario en una fecha
     */
    public function findLastPunchByUserAndTypeAndDate(
        User $user, 
        PunchType $punchType, 
        \DateTimeInterface $date
    ): ?AttendancePunch {
        $startDate = $date->format('Y-m-d 00:00:00');
        $endDate = $date->format('Y-m-d 23:59:59');

        return $this->createQueryBuilder('ap')
            ->where('ap.user = :user')
            ->andWhere('ap.punch_type = :punchType')
            ->andWhere('ap.punch_datetime BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('punchType', $punchType)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ap.punch_datetime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Verifica si un usuario ya tiene un punch de entrada en una fecha
     */
    public function hasCheckInForDate(User $user, \DateTimeInterface $date): bool
    {
        $punch = $this->findLastPunchByUserAndTypeAndDate($user, PunchType::CHECK_IN, $date);
        return $punch !== null;
    }

    /**
     * Verifica si un usuario ya tiene un punch de salida en una fecha
     */
    public function hasCheckOutForDate(User $user, \DateTimeInterface $date): bool
    {
        $punch = $this->findLastPunchByUserAndTypeAndDate($user, PunchType::CHECK_OUT, $date);
        return $punch !== null;
    }

    /**
     * Encuentra punches para cálculo de resumen diario
     */
    public function findForDailySummaryCalculation(User $user, \DateTimeInterface $date): array
    {
        $startDate = $date->format('Y-m-d 00:00:00');
        $endDate = $date->format('Y-m-d 23:59:59');

        return $this->createQueryBuilder('ap')
            ->where('ap.user = :user')
            ->andWhere('ap.punch_datetime BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ap.punch_datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra punches sin procesar para cálculo
     */
    public function findUnprocessedPunches(\DateTimeInterface $date): array
    {
        $startDate = $date->format('Y-m-d 00:00:00');
        $endDate = $date->format('Y-m-d 23:59:59');

        return $this->createQueryBuilder('ap')
            ->join('ap.user', 'u')
            ->addSelect('u')
            ->where('ap.punch_datetime BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ap.user', 'ASC')
            ->addOrderBy('ap.punch_datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta punches por tipo en un rango de fechas
     */
    public function countByTypeAndDateRange(
        PunchType $punchType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Company $company = null
    ): int {
        $qb = $this->createQueryBuilder('ap')
            ->select('COUNT(ap.id)')
            ->where('ap.punch_type = :punchType')
            ->andWhere('ap.punch_datetime BETWEEN :startDate AND :endDate')
            ->setParameter('punchType', $punchType)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'));

        if ($company) {
            $qb->andWhere('ap.company = :company')
               ->setParameter('company', $company);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Encuentra punches con datos de ubicación
     */
    public function findWithLocationData(User $user, \DateTimeInterface $date): array
    {
        $startDate = $date->format('Y-m-d 00:00:00');
        $endDate = $date->format('Y-m-d 23:59:59');

        return $this->createQueryBuilder('ap')
            ->where('ap.user = :user')
            ->andWhere('ap.punch_datetime BETWEEN :startDate AND :endDate')
            ->andWhere('ap.location_data IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ap.punch_datetime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(AttendancePunch $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AttendancePunch $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
