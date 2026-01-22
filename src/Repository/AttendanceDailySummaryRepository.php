<?php

namespace App\Repository;

use App\Entity\App\AttendanceDailySummary;
use App\Entity\App\Company;
use App\Entity\App\User;
use App\Enum\AttendanceStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttendanceDailySummary>
 */
class AttendanceDailySummaryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttendanceDailySummary::class);
    }

    /**
     * Encuentra resumen de un usuario para una fecha específica
     */
    public function findByUserAndDate(User $user, \DateTimeInterface $date): ?AttendanceDailySummary
    {
        return $this->createQueryBuilder('ads')
            ->where('ads.user = :user')
            ->andWhere('ads.attendance_date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra resúmenes de una empresa para una fecha
     */
    public function findByCompanyAndDate(Company $company, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('ads')
            ->join('ads.user', 'u')
            ->addSelect('u')
            ->where('ads.company = :company')
            ->andWhere('ads.attendance_date = :date')
            ->setParameter('company', $company)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra resúmenes de un usuario en un rango de fechas
     */
    public function findByUserAndDateRange(
        User $user, 
        \DateTimeInterface $startDate, 
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ads')
            ->where('ads.user = :user')
            ->andWhere('ads.attendance_date BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('ads.attendance_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra resúmenes pendientes de cálculo
     */
    public function findPendingCalculation(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('ads')
            ->join('ads.user', 'u')
            ->addSelect('u')
            ->where('ads.attendance_date = :date')
            ->andWhere('ads.is_calculated = false')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra resúmenes por estado de asistencia
     */
    public function findByStatusAndDateRange(
        AttendanceStatus $status,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Company $company = null
    ): array {
        $qb = $this->createQueryBuilder('ads')
            ->join('ads.user', 'u')
            ->addSelect('u')
            ->where('ads.attendance_status = :status')
            ->andWhere('ads.attendance_date BETWEEN :startDate AND :endDate')
            ->setParameter('status', $status)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'));

        if ($company) {
            $qb->andWhere('ads.company = :company')
               ->setParameter('company', $company);
        }

        return $qb->orderBy('ads.attendance_date', 'DESC')
                  ->addOrderBy('u.name', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Calcula estadísticas de asistencia para una empresa en un período
     */
    public function getAttendanceStats(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $result = $this->createQueryBuilder('ads')
            ->select('
                ads.attendance_status,
                COUNT(ads.id) as count,
                AVG(ads.total_worked_minutes) as avg_worked_minutes,
                SUM(ads.late_minutes) as total_late_minutes,
                SUM(ads.overtime_minutes) as total_overtime_minutes
            ')
            ->where('ads.company = :company')
            ->andWhere('ads.attendance_date BETWEEN :startDate AND :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->groupBy('ads.attendance_status')
            ->getQuery()
            ->getResult();

        // Formatear resultado
        $stats = [];
        foreach ($result as $row) {
            $stats[$row['attendance_status']] = [
                'count' => (int) $row['count'],
                'avg_worked_hours' => $row['avg_worked_minutes'] ? round($row['avg_worked_minutes'] / 60, 2) : 0,
                'total_late_hours' => $row['total_late_minutes'] ? round($row['total_late_minutes'] / 60, 2) : 0,
                'total_overtime_hours' => $row['total_overtime_minutes'] ? round($row['total_overtime_minutes'] / 60, 2) : 0,
            ];
        }

        return $stats;
    }

    /**
     * Encuentra empleados con más retrasos en un período
     */
    public function findTopLateEmployees(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('ads')
            ->select('
                u.id,
                u.name,
                u.last_name,
                u.employee_number,
                COUNT(ads.id) as late_days,
                SUM(ads.late_minutes) as total_late_minutes
            ')
            ->join('ads.user', 'u')
            ->where('ads.company = :company')
            ->andWhere('ads.attendance_date BETWEEN :startDate AND :endDate')
            ->andWhere('ads.late_minutes > 0')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->groupBy('u.id')
            ->orderBy('total_late_minutes', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra resúmenes con tiempo extra
     */
    public function findWithOvertime(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ads')
            ->join('ads.user', 'u')
            ->addSelect('u')
            ->where('ads.company = :company')
            ->andWhere('ads.attendance_date BETWEEN :startDate AND :endDate')
            ->andWhere('ads.overtime_minutes > 0')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('ads.overtime_minutes', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcula total de horas trabajadas por usuario en un período
     */
    public function getTotalWorkedHoursByUser(
        User $user,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): float {
        $result = $this->createQueryBuilder('ads')
            ->select('SUM(ads.total_worked_minutes) as total_minutes')
            ->where('ads.user = :user')
            ->andWhere('ads.attendance_date BETWEEN :startDate AND :endDate')
            ->andWhere('ads.is_calculated = true')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round($result / 60, 2) : 0;
    }

    /**
     * Encuentra resúmenes que necesitan recálculo
     */
    public function findNeedingRecalculation(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('ads')
            ->join('ads.user', 'u')
            ->addSelect('u')
            ->where('ads.attendance_date = :date')
            ->andWhere('ads.attendance_status = :pendingStatus')
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('pendingStatus', AttendanceStatus::PENDING_CALCULATION)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(AttendanceDailySummary $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AttendanceDailySummary $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
