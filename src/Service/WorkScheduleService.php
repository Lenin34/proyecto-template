<?php

namespace App\Service;

use App\Entity\WorkSchedule;
use App\Entity\WorkScheduleDay;
use App\Entity\WorkScheduleBreak;
use App\Entity\Company;
use App\Enum\Status;
use App\Enum\ErrorCodes\WorkScheduleErrorCodes;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class WorkScheduleService
{
    public function __construct(
        private TenantManager $tenantManager
    ) {}

    /**
     * Crea un nuevo horario de trabajo con sus días y descansos
     */
    public function createWorkSchedule(Request $request): WorkSchedule
    {
        // Validar datos obligatorios
        $name = $request->request->get('name');
        $startTime = $request->request->get('start_time');
        $endTime = $request->request->get('end_time');

        if (!$name || !$startTime || !$endTime) {
            throw new \InvalidArgumentException(WorkScheduleErrorCodes::MISSING_REQUIRED_FIELDS['message']);
        }

        // IMPORTANTE: El tenant ya debe estar configurado desde el controlador
        $entityManager = $this->tenantManager->getEntityManager();

        if (!$entityManager) {
            throw new \RuntimeException(WorkScheduleErrorCodes::TENANT_NOT_CONFIGURED['message']);
        }

        $entityManager->beginTransaction();
        
        try {
            // Validar formato de horas
            if (!$this->isValidTimeFormat($startTime) || !$this->isValidTimeFormat($endTime)) {
                throw new \InvalidArgumentException(WorkScheduleErrorCodes::INVALID_TIME_FORMAT['message']);
            }

            // Crear objetos DateTime para validación
            $startDateTime = \DateTime::createFromFormat('H:i', $startTime);
            $endDateTime = \DateTime::createFromFormat('H:i', $endTime);

            if (!$startDateTime || !$endDateTime) {
                throw new \InvalidArgumentException(WorkScheduleErrorCodes::TIME_VALIDATION_ERROR['message']);
            }

            // Validar que la hora de fin sea posterior a la de inicio
            if ($startDateTime >= $endDateTime) {
                throw new \InvalidArgumentException(WorkScheduleErrorCodes::INVALID_TIME_RANGE['message']);
            }

            // Crear horario principal
            $schedule = new WorkSchedule();
            $schedule->setName($name);
            $schedule->setStartTime($startDateTime);
            $schedule->setEndTime($endDateTime);
            $schedule->setStatus(Status::ACTIVE);
            $schedule->setCreatedAt(new \DateTime());
            $schedule->setUpdatedAt(new \DateTime());

            // Asignar empresa si se especifica
            $this->assignCompanyToSchedule($schedule, $request, $entityManager);

            $entityManager->persist($schedule);

            // Crear días de trabajo
            $this->createWorkingDays($schedule, $request, $entityManager);

            // Crear descansos
            $this->createScheduleBreaks($schedule, $request, $entityManager);

            // Guardar todo de una vez
            $entityManager->flush();
            $entityManager->commit();

            return $schedule;

        } catch (\Exception $e) {
            $entityManager->rollback();
            throw new \RuntimeException('Error al crear el horario: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Actualiza un horario existente
     */
    public function updateWorkSchedule(WorkSchedule $schedule, Request $request): WorkSchedule
    {
        // Validar datos obligatorios
        $name = $request->request->get('name');
        $startTime = $request->request->get('start_time');
        $endTime = $request->request->get('end_time');
        
        error_log("DEBUG UPDATE: name=$name, startTime=$startTime, endTime=$endTime");

        if (!$name || !$startTime || !$endTime) {
            throw new \InvalidArgumentException(WorkScheduleErrorCodes::MISSING_REQUIRED_FIELDS['message']);
        }

        // Validar formato de horas (igual que en create)
        if (!$this->isValidTimeFormat($startTime) || !$this->isValidTimeFormat($endTime)) {
            throw new \InvalidArgumentException(WorkScheduleErrorCodes::INVALID_TIME_FORMAT['message']);
        }

        // Crear objetos DateTime para validación
        $startDateTime = \DateTime::createFromFormat('H:i', $startTime);
        $endDateTime = \DateTime::createFromFormat('H:i', $endTime);

        if (!$startDateTime || !$endDateTime) {
            throw new \InvalidArgumentException(WorkScheduleErrorCodes::TIME_VALIDATION_ERROR['message']);
        }

        // Validar que la hora de fin sea posterior a la de inicio
        if ($startDateTime >= $endDateTime) {
            throw new \InvalidArgumentException(WorkScheduleErrorCodes::INVALID_TIME_RANGE['message']);
        }

        $entityManager = $this->tenantManager->getEntityManager();

        if (!$entityManager) {
            throw new \RuntimeException(WorkScheduleErrorCodes::TENANT_NOT_CONFIGURED['message']);
        }

        error_log("DEBUG UPDATE: Starting transaction");
        $entityManager->beginTransaction();

        try {
            error_log("DEBUG UPDATE: Updating main schedule");
            // Actualizar horario principal
            $schedule->setName($name);
            $schedule->setStartTime($startDateTime);
            $schedule->setEndTime($endDateTime);
            $schedule->setUpdatedAt(new \DateTime());

            error_log("DEBUG UPDATE: Assigning company");
            // Actualizar empresa
            $this->assignCompanyToSchedule($schedule, $request, $entityManager);

            error_log("DEBUG UPDATE: Updating working days");
            // Actualizar días de trabajo (eliminar existentes y crear nuevos)
            $this->updateWorkingDays($schedule, $request, $entityManager);

            error_log("DEBUG UPDATE: Updating breaks");
            // Actualizar descansos (eliminar existentes y crear nuevos)
            $this->updateScheduleBreaks($schedule, $request, $entityManager);

            error_log("DEBUG UPDATE: Flushing changes");
            $entityManager->flush();

            error_log("DEBUG UPDATE: Committing transaction");
            $entityManager->commit();

            error_log("DEBUG UPDATE: Success!");
            return $schedule;

        } catch (\Exception $e) {
            error_log("DEBUG UPDATE: Exception caught: " . $e->getMessage());
            error_log("DEBUG UPDATE: Exception trace: " . $e->getTraceAsString());
            $entityManager->rollback();
            throw new \RuntimeException(WorkScheduleErrorCodes::SCHEDULE_UPDATE_FAILED['message'] . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Elimina un horario (soft delete) y sus asignaciones
     */
    public function deleteWorkSchedule(WorkSchedule $schedule): void
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            // Verificar si el horario ya está eliminado
            if ($schedule->getStatus() === Status::DELETED) {
                throw new \InvalidArgumentException(WorkScheduleErrorCodes::SCHEDULE_INACTIVE['message']);
            }

            $entityManager->beginTransaction();

            // Eliminar asignaciones de usuarios (soft delete)
            $assignments = $entityManager->getRepository(\App\Entity\UserScheduleAssignment::class)
                ->findBy(['workSchedule' => $schedule, 'status' => Status::ACTIVE]);

            foreach ($assignments as $assignment) {
                $assignment->setStatus(Status::DELETED);
                $assignment->setUpdatedAt(new \DateTime());
            }

            // Eliminar el horario (soft delete)
            $schedule->setStatus(Status::DELETED);
            $schedule->setUpdatedAt(new \DateTime());

            $entityManager->flush();
            $entityManager->commit();

        } catch (\Exception $e) {
            if ($entityManager->getConnection()->isTransactionActive()) {
                $entityManager->rollback();
            }
            throw new \RuntimeException(WorkScheduleErrorCodes::SCHEDULE_DELETE_FAILED['message'] . ': ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Asigna una empresa al horario si se especifica
     */
    private function assignCompanyToSchedule(WorkSchedule $schedule, Request $request, EntityManagerInterface $entityManager): void
    {
        $companyId = $request->request->get('company_id');

        if ($companyId) {
            $company = $entityManager->getRepository(Company::class)->find($companyId);
            if ($company && $company->getStatus() === Status::ACTIVE) {
                $schedule->setCompany($company);
            }
        } else {
            $schedule->setCompany(null);
        }
    }

    /**
     * Crea los días de trabajo para el horario
     */
    private function createWorkingDays(WorkSchedule $schedule, Request $request, EntityManagerInterface $entityManager): void
    {
        $workingDays = $request->request->all('working_days') ?? [];

        for ($day = 1; $day <= 7; $day++) {
            $scheduleDay = new WorkScheduleDay();
            $scheduleDay->setWorkSchedule($schedule);
            $scheduleDay->setDayOfWeek($day);
            $scheduleDay->setIsWorkingDay(in_array($day, $workingDays));
            $scheduleDay->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($scheduleDay);
        }
    }

    /**
     * Crea los descansos para el horario
     */
    private function createScheduleBreaks(WorkSchedule $schedule, Request $request, EntityManagerInterface $entityManager): void
    {
        $breaks = $request->request->all('breaks') ?? [];

        foreach ($breaks as $breakData) {
            if ($this->isValidBreakData($breakData)) {
                $scheduleBreak = new WorkScheduleBreak();
                $scheduleBreak->setWorkSchedule($schedule);
                $scheduleBreak->setBreakName($breakData['name']);
                $scheduleBreak->setStartTime(\DateTime::createFromFormat('H:i', $breakData['start_time']));
                $scheduleBreak->setEndTime(\DateTime::createFromFormat('H:i', $breakData['end_time']));
                $scheduleBreak->setIsPaid($breakData['is_paid'] ?? false);
                $scheduleBreak->setCreatedAt(new \DateTimeImmutable());

                $entityManager->persist($scheduleBreak);
            }
        }
    }

    /**
     * Valida que los datos del descanso sean correctos
     */
    private function isValidBreakData(array $breakData): bool
    {
        return !empty($breakData['name']) && 
               !empty($breakData['start_time']) && 
               !empty($breakData['end_time']);
    }

    /**
     * Actualiza los días de trabajo para el horario (elimina existentes y crea nuevos)
     */
    private function updateWorkingDays(WorkSchedule $schedule, Request $request, EntityManagerInterface $entityManager): void
    {
        // Eliminar días existentes
        foreach ($schedule->getWorkScheduleDays() as $existingDay) {
            $entityManager->remove($existingDay);
        }

        // Crear nuevos días
        $this->createWorkingDays($schedule, $request, $entityManager);
    }

    /**
     * Actualiza los descansos para el horario (elimina existentes y crea nuevos)
     */
    private function updateScheduleBreaks(WorkSchedule $schedule, Request $request, EntityManagerInterface $entityManager): void
    {
        // Eliminar descansos existentes
        foreach ($schedule->getWorkScheduleBreaks() as $existingBreak) {
            $entityManager->remove($existingBreak);
        }

        // Crear nuevos descansos
        $this->createScheduleBreaks($schedule, $request, $entityManager);
    }

    /**
     * Obtiene estadísticas de horarios
     */
    public function getScheduleStats(): array
    {
        $entityManager = $this->tenantManager->getEntityManager();

        $totalSchedules = $entityManager->getRepository(WorkSchedule::class)
            ->count(['status' => Status::ACTIVE]);

        $totalAssignments = $entityManager->getRepository(\App\Entity\UserScheduleAssignment::class)
            ->count(['status' => Status::ACTIVE]);

        return [
            'total_schedules' => $totalSchedules,
            'total_assignments' => $totalAssignments
        ];
    }

    /**
     * Obtiene horarios recientes
     */
    public function getRecentSchedules(int $limit = 5): array
    {
        $entityManager = $this->tenantManager->getEntityManager();

        return $entityManager->getRepository(WorkSchedule::class)
            ->findBy(
                ['status' => Status::ACTIVE],
                ['created_at' => 'DESC'],
                $limit
            );
    }

    /**
     * Valida que el formato de hora sea correcto (HH:MM)
     */
    private function isValidTimeFormat(string $time): bool
    {
        return (bool) preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
    }
}
