<?php

namespace App\Repository;

use App\Entity\App\WorkSchedule;
use App\Entity\App\WorkScheduleBreak;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkScheduleBreak>
 */
class WorkScheduleBreakRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkScheduleBreak::class);
    }

    /**
     * Encuentra descansos de un horario ordenados por hora
     */
    public function findByScheduleOrderedByTime(WorkSchedule $schedule): array
    {
        return $this->createQueryBuilder('wsb')
            ->where('wsb.workSchedule = :schedule')
            ->setParameter('schedule', $schedule)
            ->orderBy('wsb.start_time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra descansos pagados de un horario
     */
    public function findPaidBreaksBySchedule(WorkSchedule $schedule): array
    {
        return $this->createQueryBuilder('wsb')
            ->where('wsb.workSchedule = :schedule')
            ->andWhere('wsb.is_paid = true')
            ->setParameter('schedule', $schedule)
            ->orderBy('wsb.start_time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra descansos no pagados de un horario
     */
    public function findUnpaidBreaksBySchedule(WorkSchedule $schedule): array
    {
        return $this->createQueryBuilder('wsb')
            ->where('wsb.workSchedule = :schedule')
            ->andWhere('wsb.is_paid = false')
            ->setParameter('schedule', $schedule)
            ->orderBy('wsb.start_time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra descanso que contiene una hora específica
     */
    public function findBreakContainingTime(WorkSchedule $schedule, \DateTimeInterface $time): ?WorkScheduleBreak
    {
        $timeStr = $time->format('H:i:s');
        
        return $this->createQueryBuilder('wsb')
            ->where('wsb.workSchedule = :schedule')
            ->andWhere('wsb.start_time <= :time')
            ->andWhere('wsb.end_time >= :time')
            ->setParameter('schedule', $schedule)
            ->setParameter('time', $timeStr)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcula total de minutos de descansos no pagados
     */
    public function getTotalUnpaidBreakMinutes(WorkSchedule $schedule): int
    {
        $breaks = $this->findUnpaidBreaksBySchedule($schedule);
        $totalMinutes = 0;

        foreach ($breaks as $break) {
            $totalMinutes += $break->getDurationMinutes();
        }

        return $totalMinutes;
    }

    /**
     * Verifica si hay descansos que se superponen
     */
    public function findOverlappingBreaks(
        WorkSchedule $schedule,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('wsb')
            ->where('wsb.workSchedule = :schedule')
            ->andWhere('(
                (wsb.start_time <= :startTime AND wsb.end_time > :startTime) OR
                (wsb.start_time < :endTime AND wsb.end_time >= :endTime) OR
                (wsb.start_time >= :startTime AND wsb.end_time <= :endTime)
            )')
            ->setParameter('schedule', $schedule)
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime);

        if ($excludeId) {
            $qb->andWhere('wsb.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    public function save(WorkScheduleBreak $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WorkScheduleBreak $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
