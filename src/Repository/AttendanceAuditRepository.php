<?php

namespace App\Repository;

use App\Entity\App\AttendanceAudit;
use App\Entity\App\AttendanceDailySummary;
use App\Entity\App\Company;
use App\Entity\App\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttendanceAudit>
 */
class AttendanceAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttendanceAudit::class);
    }

    /**
     * Encuentra auditorías de un registro de asistencia
     */
    public function findByAttendanceRecord(AttendanceDailySummary $record): array
    {
        return $this->createQueryBuilder('aa')
            ->join('aa.modified_by_user', 'u')
            ->addSelect('u')
            ->where('aa.attendanceRecord = :record')
            ->setParameter('record', $record)
            ->orderBy('aa.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra auditorías realizadas por un usuario
     */
    public function findByModifier(
        User $modifier,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('aa')
            ->join('aa.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->addSelect('ar', 'u')
            ->where('aa.modified_by_user = :modifier')
            ->andWhere('aa.created_at BETWEEN :startDate AND :endDate')
            ->setParameter('modifier', $modifier)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('aa.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra auditorías de una empresa en un período
     */
    public function findByCompanyAndDateRange(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('aa')
            ->join('aa.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->join('aa.modified_by_user', 'modifier')
            ->addSelect('ar', 'u', 'modifier')
            ->where('ar.company = :company')
            ->andWhere('aa.created_at BETWEEN :startDate AND :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('aa.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra auditorías por campo modificado
     */
    public function findByField(
        string $fieldName,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Company $company = null
    ): array {
        $qb = $this->createQueryBuilder('aa')
            ->join('aa.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->join('aa.modified_by_user', 'modifier')
            ->addSelect('ar', 'u', 'modifier')
            ->where('aa.field_changed = :fieldName')
            ->andWhere('aa.created_at BETWEEN :startDate AND :endDate')
            ->setParameter('fieldName', $fieldName)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'));

        if ($company) {
            $qb->andWhere('ar.company = :company')
               ->setParameter('company', $company);
        }

        return $qb->orderBy('aa.created_at', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Encuentra auditorías de un empleado específico
     */
    public function findByEmployee(
        User $employee,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('aa')
            ->join('aa.attendanceRecord', 'ar')
            ->join('aa.modified_by_user', 'modifier')
            ->addSelect('ar', 'modifier')
            ->where('ar.user = :employee')
            ->andWhere('aa.created_at BETWEEN :startDate AND :endDate')
            ->setParameter('employee', $employee)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->orderBy('aa.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Estadísticas de modificaciones por campo
     */
    public function getFieldModificationStats(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $result = $this->createQueryBuilder('aa')
            ->select('
                aa.field_changed,
                COUNT(aa.id) as modification_count,
                COUNT(DISTINCT aa.modified_by_user) as unique_modifiers,
                COUNT(DISTINCT ar.user) as affected_employees
            ')
            ->join('aa.attendanceRecord', 'ar')
            ->where('ar.company = :company')
            ->andWhere('aa.created_at BETWEEN :startDate AND :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->groupBy('aa.field_changed')
            ->orderBy('modification_count', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Encuentra los usuarios que más modificaciones han hecho
     */
    public function getTopModifiers(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('aa')
            ->select('
                u.id,
                u.name,
                u.last_name,
                u.employee_number,
                COUNT(aa.id) as modification_count
            ')
            ->join('aa.modified_by_user', 'u')
            ->join('aa.attendanceRecord', 'ar')
            ->where('ar.company = :company')
            ->andWhere('aa.created_at BETWEEN :startDate AND :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->groupBy('u.id')
            ->orderBy('modification_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra auditorías recientes
     */
    public function findRecent(?Company $company = null, int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('aa')
            ->join('aa.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->join('aa.modified_by_user', 'modifier')
            ->addSelect('ar', 'u', 'modifier');

        if ($company) {
            $qb->where('ar.company = :company')
               ->setParameter('company', $company);
        }

        return $qb->orderBy('aa.created_at', 'DESC')
                  ->setMaxResults($limit)
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Cuenta modificaciones por día
     */
    public function countModificationsByDay(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('aa')
            ->select('
                DATE(aa.created_at) as modification_date,
                COUNT(aa.id) as count
            ')
            ->join('aa.attendanceRecord', 'ar')
            ->where('ar.company = :company')
            ->andWhere('aa.created_at BETWEEN :startDate AND :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d 00:00:00'))
            ->setParameter('endDate', $endDate->format('Y-m-d 23:59:59'))
            ->groupBy('modification_date')
            ->orderBy('modification_date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function save(AttendanceAudit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AttendanceAudit $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
