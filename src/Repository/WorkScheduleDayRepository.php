<?php

namespace App\Repository;

use App\Entity\App\WorkSchedule;
use App\Entity\App\WorkScheduleDay;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WorkScheduleDay>
 */
class WorkScheduleDayRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkScheduleDay::class);
    }

    /**
     * Encuentra días laborales de un horario
     */
    public function findWorkingDaysBySchedule(WorkSchedule $schedule): array
    {
        return $this->createQueryBuilder('wsd')
            ->where('wsd.workSchedule = :schedule')
            ->andWhere('wsd.is_working_day = true')
            ->setParameter('schedule', $schedule)
            ->orderBy('wsd.day_of_week', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Encuentra configuración de un día específico
     */
    public function findByScheduleAndDay(WorkSchedule $schedule, int $dayOfWeek): ?WorkScheduleDay
    {
        return $this->createQueryBuilder('wsd')
            ->where('wsd.workSchedule = :schedule')
            ->andWhere('wsd.day_of_week = :dayOfWeek')
            ->setParameter('schedule', $schedule)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Verifica si un día es laboral para un horario
     */
    public function isWorkingDay(WorkSchedule $schedule, int $dayOfWeek): bool
    {
        $result = $this->createQueryBuilder('wsd')
            ->select('wsd.is_working_day')
            ->where('wsd.workSchedule = :schedule')
            ->andWhere('wsd.day_of_week = :dayOfWeek')
            ->setParameter('schedule', $schedule)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->getQuery()
            ->getOneOrNullResult();

        return $result ? $result['is_working_day'] : false;
    }

    public function save(WorkScheduleDay $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(WorkScheduleDay $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
