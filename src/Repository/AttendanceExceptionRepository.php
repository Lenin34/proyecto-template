<?php

namespace App\Repository;

use App\Entity\App\AttendanceDailySummary;
use App\Entity\App\AttendanceException;
use App\Entity\App\Company;
use App\Entity\App\User;
use App\Enum\ExceptionType;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AttendanceException>
 */
class AttendanceExceptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AttendanceException::class);
    }

    /**
     * Encuentra excepciones pendientes de aprobación
     */
    public function findPendingApproval(?Company $company = null): array
    {
        $qb = $this->createQueryBuilder('ae')
            ->join('ae.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->addSelect('ar', 'u')
            ->where('ae.status = :status')
            ->setParameter('status', Status::INACTIVE)
            ->orderBy('ae.created_at', 'ASC');

        if ($company) {
            $qb->andWhere('ar.company = :company')
               ->setParameter('company', $company);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Encuentra excepciones por tipo
     */
    public function findByType(
        ExceptionType $type,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Company $company = null
    ): array {
        $qb = $this->createQueryBuilder('ae')
            ->join('ae.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->addSelect('ar', 'u')
            ->where('ae.exception_type = :type')
            ->andWhere('ar.attendance_date BETWEEN :startDate AND :endDate')
            ->setParameter('type', $type)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'));

        if ($company) {
            $qb->andWhere('ar.company = :company')
               ->setParameter('company', $company);
        }

        return $qb->orderBy('ar.attendance_date', 'DESC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Encuentra excepciones de un usuario
     */
    public function findByUser(
        User $user,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ae')
            ->join('ae.attendanceRecord', 'ar')
            ->addSelect('ar')
            ->where('ar.user = :user')
            ->andWhere('ar.attendance_date BETWEEN :startDate AND :endDate')
            ->setParameter('user', $user)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('ar.attendance_date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra excepciones aprobadas por un supervisor
     */
    public function findApprovedBy(User $approver): array
    {
        return $this->createQueryBuilder('ae')
            ->join('ae.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->addSelect('ar', 'u')
            ->where('ae.approved_by_user = :approver')
            ->andWhere('ae.status = :status')
            ->setParameter('approver', $approver)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ae.approval_date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta excepciones por estado
     */
    public function countByStatus(Status $status, ?Company $company = null): int
    {
        $qb = $this->createQueryBuilder('ae')
            ->select('COUNT(ae.id)')
            ->where('ae.status = :status')
            ->setParameter('status', $status);

        if ($company) {
            $qb->join('ae.attendanceRecord', 'ar')
               ->andWhere('ar.company = :company')
               ->setParameter('company', $company);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Encuentra excepciones por registro de asistencia
     */
    public function findByAttendanceRecord(AttendanceDailySummary $record): array
    {
        return $this->createQueryBuilder('ae')
            ->where('ae.attendanceRecord = :record')
            ->setParameter('record', $record)
            ->orderBy('ae.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra excepciones que requieren aprobación
     */
    public function findRequiringApproval(?Company $company = null): array
    {
        $qb = $this->createQueryBuilder('ae')
            ->join('ae.attendanceRecord', 'ar')
            ->join('ar.user', 'u')
            ->addSelect('ar', 'u')
            ->where('ae.status = :status')
            ->setParameter('status', Status::INACTIVE);

        if ($company) {
            $qb->andWhere('ar.company = :company')
               ->setParameter('company', $company);
        }

        // Filtrar solo los tipos que requieren aprobación
        $requireApprovalTypes = [];
        foreach (ExceptionType::cases() as $type) {
            if ($type->requiresApproval()) {
                $requireApprovalTypes[] = $type;
            }
        }

        if (!empty($requireApprovalTypes)) {
            $qb->andWhere('ae.exception_type IN (:types)')
               ->setParameter('types', $requireApprovalTypes);
        }

        return $qb->orderBy('ae.created_at', 'ASC')
                  ->getQuery()
                  ->getResult();
    }

    /**
     * Estadísticas de excepciones por tipo
     */
    public function getExceptionStats(
        Company $company,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $result = $this->createQueryBuilder('ae')
            ->select('
                ae.exception_type,
                ae.status,
                COUNT(ae.id) as count
            ')
            ->join('ae.attendanceRecord', 'ar')
            ->where('ar.company = :company')
            ->andWhere('ar.attendance_date BETWEEN :startDate AND :endDate')
            ->setParameter('company', $company)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->groupBy('ae.exception_type', 'ae.status')
            ->getQuery()
            ->getResult();

        // Formatear resultado
        $stats = [];
        foreach ($result as $row) {
            $type = $row['exception_type'];
            $status = $row['status'];
            
            if (!isset($stats[$type])) {
                $stats[$type] = [
                    'total' => 0,
                    'pending' => 0,
                    'approved' => 0,
                    'rejected' => 0,
                ];
            }
            
            $stats[$type]['total'] += (int) $row['count'];
            
            switch ($status) {
                case Status::INACTIVE:
                    $stats[$type]['pending'] += (int) $row['count'];
                    break;
                case Status::ACTIVE:
                    $stats[$type]['approved'] += (int) $row['count'];
                    break;
                case Status::DELETED:
                    $stats[$type]['rejected'] += (int) $row['count'];
                    break;
            }
        }

        return $stats;
    }

    public function save(AttendanceException $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AttendanceException $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
