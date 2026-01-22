<?php

namespace App\Repository;

use App\Entity\App\Company;
use App\Entity\App\WorkSchedule;
use App\Enum\Status;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkSchedule>
 */
class WorkScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkSchedule::class);
    }

    /**
     * Encuentra horarios activos por empresa
     */
    public function findActiveByCompany(Company $company): array
    {
        return $this->createQueryBuilder('ws')
            ->where('ws.company = :company')
            ->andWhere('ws.status = :status')
            ->setParameter('company', $company)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ws.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra horarios con sus días y descansos
     */
    public function findWithDaysAndBreaks(int $id): ?WorkSchedule
    {
        return $this->createQueryBuilder('ws')
            ->leftJoin('ws.workScheduleDays', 'wsd')
            ->leftJoin('ws.workScheduleBreaks', 'wsb')
            ->addSelect('wsd', 'wsb')
            ->where('ws.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Busca horarios por nombre
     */
    public function findByNameLike(string $name, Company $company): array
    {
        return $this->createQueryBuilder('ws')
            ->where('ws.company = :company')
            ->andWhere('ws.name LIKE :name')
            ->andWhere('ws.status = :status')
            ->setParameter('company', $company)
            ->setParameter('name', '%' . $name . '%')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ws.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Cuenta empleados asignados a un horario
     */
    public function countAssignedEmployees(WorkSchedule $workSchedule): int
    {
        return $this->getEntityManager()
            ->createQuery('
                SELECT COUNT(DISTINCT usa.user) 
                FROM App\Entity\App\UserScheduleAssignment usa 
                WHERE usa.workSchedule = :schedule 
                AND usa.status = :status
                AND (usa.effective_until IS NULL OR usa.effective_until >= CURRENT_DATE())
            ')
            ->setParameter('schedule', $workSchedule)
            ->setParameter('status', Status::ACTIVE)
            ->getSingleScalarResult();
    }

    /**
     * Encuentra horarios que se superponen en tiempo
     */
    public function findOverlappingSchedules(
        Company $company, 
        \DateTimeInterface $startTime, 
        \DateTimeInterface $endTime,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('ws')
            ->where('ws.company = :company')
            ->andWhere('ws.status = :status')
            ->andWhere('(
                (ws.start_time <= :startTime AND ws.end_time > :startTime) OR
                (ws.start_time < :endTime AND ws.end_time >= :endTime) OR
                (ws.start_time >= :startTime AND ws.end_time <= :endTime)
            )')
            ->setParameter('company', $company)
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeId) {
            $qb->andWhere('ws.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(WorkSchedule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WorkSchedule $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
