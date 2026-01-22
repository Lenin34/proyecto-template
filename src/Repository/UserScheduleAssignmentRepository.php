<?php

namespace App\Repository;

use App\Entity\App\Company;
use App\Entity\App\User;
use App\Entity\App\UserScheduleAssignment;
use App\Entity\App\WorkSchedule;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserScheduleAssignment>
 */
class UserScheduleAssignmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserScheduleAssignment::class);
    }

    /**
     * Encuentra la asignación activa de un usuario para una fecha específica
     */
    public function findActiveAssignmentForUserAndDate(User $user, \DateTimeInterface $date): ?UserScheduleAssignment
    {
        $dateStr = $date->format('Y-m-d');
        
        return $this->createQueryBuilder('usa')
            ->join('usa.workSchedule', 'ws')
            ->where('usa.user = :user')
            ->andWhere('usa.status = :status')
            ->andWhere('usa.effective_from <= :date')
            ->andWhere('usa.effective_until IS NULL OR usa.effective_until >= :date')
            ->setParameter('user', $user)
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('date', $dateStr)
            ->orderBy('usa.effective_from', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Encuentra la asignación actual de un usuario
     */
    public function findCurrentAssignmentForUser(User $user): ?UserScheduleAssignment
    {
        return $this->findActiveAssignmentForUserAndDate($user, new \DateTimeImmutable());
    }

    /**
     * Encuentra todas las asignaciones activas de un usuario
     */
    public function findActiveAssignmentsByUser(User $user): array
    {
        return $this->createQueryBuilder('usa')
            ->join('usa.workSchedule', 'ws')
            ->addSelect('ws')
            ->where('usa.user = :user')
            ->andWhere('usa.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('usa.effective_from', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra usuarios asignados a un horario específico
     */
    public function findUsersBySchedule(WorkSchedule $schedule): array
    {
        return $this->createQueryBuilder('usa')
            ->join('usa.user', 'u')
            ->addSelect('u')
            ->where('usa.workSchedule = :schedule')
            ->andWhere('usa.status = :status')
            ->andWhere('usa.effective_until IS NULL OR usa.effective_until >= CURRENT_DATE()')
            ->setParameter('schedule', $schedule)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra asignaciones por empresa y fecha
     */
    public function findAssignmentsByCompanyAndDate(Company $company, \DateTimeInterface $date): array
    {
        $dateStr = $date->format('Y-m-d');
        
        return $this->createQueryBuilder('usa')
            ->join('usa.user', 'u')
            ->join('usa.workSchedule', 'ws')
            ->addSelect('u', 'ws')
            ->where('u.company = :company')
            ->andWhere('usa.status = :status')
            ->andWhere('usa.effective_from <= :date')
            ->andWhere('usa.effective_until IS NULL OR usa.effective_until >= :date')
            ->setParameter('company', $company)
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('date', $dateStr)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra asignaciones que se superponen para un usuario
     */
    public function findOverlappingAssignments(
        User $user,
        \DateTimeInterface $effectiveFrom,
        ?\DateTimeInterface $effectiveUntil = null,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('usa')
            ->where('usa.user = :user')
            ->andWhere('usa.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', Status::ACTIVE);

        // Lógica de superposición de fechas
        if ($effectiveUntil) {
            $qb->andWhere('(
                (usa.effective_from <= :from AND (usa.effective_until IS NULL OR usa.effective_until >= :from)) OR
                (usa.effective_from <= :until AND (usa.effective_until IS NULL OR usa.effective_until >= :until)) OR
                (usa.effective_from >= :from AND usa.effective_from <= :until)
            )')
            ->setParameter('from', $effectiveFrom->format('Y-m-d'))
            ->setParameter('until', $effectiveUntil->format('Y-m-d'));
        } else {
            $qb->andWhere('usa.effective_until IS NULL OR usa.effective_until >= :from')
               ->setParameter('from', $effectiveFrom->format('Y-m-d'));
        }

        if ($excludeId) {
            $qb->andWhere('usa.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Encuentra historial de asignaciones de un usuario
     */
    public function findUserAssignmentHistory(User $user): array
    {
        return $this->createQueryBuilder('usa')
            ->join('usa.workSchedule', 'ws')
            ->addSelect('ws')
            ->where('usa.user = :user')
            ->setParameter('user', $user)
            ->orderBy('usa.effective_from', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Termina asignaciones activas de un usuario en una fecha específica
     */
    public function endActiveAssignments(User $user, \DateTimeInterface $endDate): int
    {
        return $this->createQueryBuilder('usa')
            ->update()
            ->set('usa.effective_until', ':endDate')
            ->set('usa.updated_at', ':now')
            ->where('usa.user = :user')
            ->andWhere('usa.status = :status')
            ->andWhere('usa.effective_until IS NULL OR usa.effective_until > :endDate')
            ->setParameter('user', $user)
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    public function save(UserScheduleAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserScheduleAssignment $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
