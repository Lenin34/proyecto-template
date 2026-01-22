<?php

namespace App\Controller;

use App\Entity\App\Company;
use App\Entity\App\UserScheduleAssignment;
use App\Entity\App\WorkSchedule;
use App\Entity\App\WorkScheduleBreak;
use App\Entity\App\WorkScheduleDay;
use App\Enum\Status;
use App\Enum\ErrorCodes\WorkScheduleErrorCodes;
use App\Service\TenantManager;
use App\Service\WorkScheduleService;
use App\Service\ErrorResponseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/{dominio}/admin/schedules')]
#[IsGranted('ROLE_ADMIN')]
class WorkScheduleController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TenantManager $tenantManager,
        private WorkScheduleService $workScheduleService,
        private ErrorResponseService $errorResponseService
    ) {}

    #[Route('', name: 'work_schedule_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        $em = $this->tenantManager->getEntityManager();

        // Obtener estadísticas usando el servicio
        $stats = $this->workScheduleService->getScheduleStats();

        $companies = $em->createQueryBuilder()
            ->select('c')
            ->from('App\Entity\App\Company', 'c')
            ->where('c.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getResult();

        // Obtener horarios recientes usando el servicio
        $recentSchedules = $this->workScheduleService->getRecentSchedules(5);

        return $this->render('work_schedule/index.html.twig', [
            'total_schedules' => $stats['total_schedules'],
            'total_assignments' => $stats['total_assignments'],
            'companies' => $companies,
            'recent_schedules' => $recentSchedules,
            'dominio' => $dominio
        ]);
    }

    #[Route('/list', name: 'work_schedule_list', methods: ['GET'])]
    public function list(string $dominio): Response
    {
        $em = $this->tenantManager->getEntityManager();

        $schedules = $em->createQueryBuilder()
            ->select('ws, c')
            ->from('App\Entity\App\WorkSchedule', 'ws')
            ->leftJoin('ws.company', 'c')
            ->where('ws.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('ws.created_at', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('work_schedule/list.html.twig', [
            'schedules' => $schedules,
            'dominio' => $dominio
        ]);
    }

    #[Route('/new', name: 'work_schedule_new', methods: ['GET', 'POST'])]
    public function new(Request $request, string $dominio): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            if ($request->isMethod('POST')) {
                try {
                    // IMPORTANTE: Configurar el tenant ANTES de usar el servicio
                    $schedule = $this->workScheduleService->createWorkSchedule($request);

                    // Si es una petición AJAX, devolver JSON
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'success' => true,
                            'message' => sprintf('El horario "%s" ha sido creado exitosamente', $schedule->getName()),
                            'redirect' => $this->generateUrl('work_schedule_show', ['id' => $schedule->getId(), 'dominio' => $dominio]),
                            'schedule' => [
                                'id' => $schedule->getId(),
                                'name' => $schedule->getName(),
                                'start_time' => $schedule->getStartTime()->format('H:i'),
                                'end_time' => $schedule->getEndTime()->format('H:i')
                            ]
                        ]);
                    }

                    $this->addFlash('success', 'Horario creado correctamente');
                    return $this->redirectToRoute('work_schedule_show', ['id' => $schedule->getId(), 'dominio' => $dominio]);
                } catch (\Exception $e) {
                    // Si es una petición AJAX, devolver JSON con error
                    if ($request->isXmlHttpRequest()) {
                        $errorCode = $this->getWorkScheduleErrorCode($e);
                        return new JsonResponse([
                            'success' => false,
                            'message' => $errorCode['message'],
                            'errors' => [$errorCode['message']],
                            'error_type' => $errorCode['code'],
                            'error_code' => $errorCode['code']
                        ], $errorCode['http_code']);
                    }

                    $this->addFlash('error', $e->getMessage());
                    return $this->redirectToRoute('work_schedule_new', ['dominio' => $dominio]);
                }
            }

            $companies = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getResult();

            return $this->render('work_schedule/new.html.twig', [
                'companies' => $companies,
                'dominio' => $dominio
            ]);

        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    #[Route('/{id}', name: 'work_schedule_show', methods: ['GET'])]
    public function show(int $id, string $dominio): Response
    {
        $em = $this->tenantManager->getEntityManager();

        $schedule = $em->find('App\Entity\App\WorkSchedule', $id);

        if (!$schedule || $schedule->getStatus() === Status::DELETED) {
            throw $this->createNotFoundException('Horario no encontrado');
        }

        // Obtener días y descansos usando consultas directas
        $days = $em->createQueryBuilder()
            ->select('wd')
            ->from('App\Entity\App\WorkScheduleDay', 'wd')
            ->where('wd.workSchedule = :schedule')
            ->setParameter('schedule', $schedule)
            ->orderBy('wd.dayOfWeek', 'ASC')
            ->getQuery()
            ->getResult();

        $breaks = $em->createQueryBuilder()
            ->select('wb')
            ->from('App\Entity\App\WorkScheduleBreak', 'wb')
            ->where('wb.workSchedule = :schedule')
            ->setParameter('schedule', $schedule)
            ->orderBy('wb.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        // Obtener asignaciones usando consulta directa
        $assignments = $em->createQueryBuilder()
            ->select('usa, u')
            ->from('App\Entity\App\UserScheduleAssignment', 'usa')
            ->leftJoin('usa.user', 'u')
            ->where('usa.workSchedule = :schedule')
            ->andWhere('usa.status = :status')
            ->setParameter('schedule', $schedule)
            ->setParameter('status', Status::ACTIVE)
            ->orderBy('u.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('work_schedule/show.html.twig', [
            'schedule' => $schedule,
            'days' => $days,
            'breaks' => $breaks,
            'assignments' => $assignments,
            'dominio' => $dominio
        ]);
    }

    #[Route('/{id}/edit', name: 'work_schedule_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id, string $dominio): Response
    {
        $em = $this->tenantManager->getEntityManager();

        $schedule = $em->find('App\Entity\App\WorkSchedule', $id);

        if (!$schedule || $schedule->getStatus() === Status::DELETED) {
            throw $this->createNotFoundException('Horario no encontrado');
        }

        if ($request->isMethod('POST')) {
            try {

                $updatedSchedule = $this->workScheduleService->updateWorkSchedule($schedule, $request);

                // Si es una petición AJAX, devolver JSON
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'success' => true,
                        'message' => sprintf('El horario "%s" ha sido actualizado exitosamente', $updatedSchedule->getName()),
                        'redirect' => $this->generateUrl('work_schedule_show', ['id' => $updatedSchedule->getId(), 'dominio' => $dominio]),
                        'schedule' => [
                            'id' => $updatedSchedule->getId(),
                            'name' => $updatedSchedule->getName(),
                            'start_time' => $updatedSchedule->getStartTime()->format('H:i'),
                            'end_time' => $updatedSchedule->getEndTime()->format('H:i')
                        ]
                    ]);
                }

                $this->addFlash('success', 'Horario actualizado correctamente');
                return $this->redirectToRoute('work_schedule_show', ['id' => $schedule->getId(), 'dominio' => $dominio]);
            } catch (\Exception $e) {
                // Si es una petición AJAX, devolver JSON con error
                if ($request->isXmlHttpRequest()) {
                    $errorCode = $this->getWorkScheduleErrorCode($e);
                    return new JsonResponse([
                        'success' => false,
                        'message' => $errorCode['message'],
                        'errors' => [$errorCode['message']],
                        'error_type' => $errorCode['code'],
                        'error_code' => $errorCode['code']
                    ], $errorCode['http_code']);
                }

                $this->addFlash('error', $e->getMessage());
                return $this->redirectToRoute('work_schedule_edit', ['id' => $schedule->getId(), 'dominio' => $dominio]);
            }
        }

        $companies = $em->createQueryBuilder()
            ->select('c')
            ->from('App\Entity\App\Company', 'c')
            ->where('c.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getResult();

        $days = $em->createQueryBuilder()
            ->select('wd')
            ->from('App\Entity\App\WorkScheduleDay', 'wd')
            ->where('wd.workSchedule = :schedule')
            ->setParameter('schedule', $schedule)
            ->orderBy('wd.dayOfWeek', 'ASC')
            ->getQuery()
            ->getResult();

        $breaks = $em->createQueryBuilder()
            ->select('wb')
            ->from('App\Entity\App\WorkScheduleBreak', 'wb')
            ->where('wb.workSchedule = :schedule')
            ->setParameter('schedule', $schedule)
            ->orderBy('wb.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('work_schedule/edit.html.twig', [
            'schedule' => $schedule,
            'companies' => $companies,
            'days' => $days,
            'breaks' => $breaks,
            'dominio' => $dominio
        ]);
    }

    #[Route('/{id}/delete', name: 'work_schedule_delete', methods: ['POST'])]
    public function delete(int $id, string $dominio): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $schedule = $em->find('App\Entity\App\WorkSchedule', $id);

        if (!$schedule) {
            $errorCode = WorkScheduleErrorCodes::SCHEDULE_NOT_FOUND;
            return new JsonResponse([
                'success' => false,
                'message' => $errorCode['message'],
                'error_code' => $errorCode['code'],
                'error_type' => $errorCode['code']
            ], $errorCode['http_code']);
        }

        if ($schedule->getStatus() === Status::DELETED) {
            $errorCode = WorkScheduleErrorCodes::SCHEDULE_INACTIVE;
            return new JsonResponse([
                'success' => false,
                'message' => $errorCode['message'],
                'error_code' => $errorCode['code'],
                'error_type' => $errorCode['code']
            ], $errorCode['http_code']);
        }

        try {
            // Verificar si tiene asignaciones antes de eliminar usando consulta directa
            $assignments = $em->createQueryBuilder()
                ->select('usa')
                ->from('App\Entity\App\UserScheduleAssignment', 'usa')
                ->where('usa.workSchedule = :schedule')
                ->andWhere('usa.status = :status')
                ->setParameter('schedule', $schedule)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getResult();

            $scheduleName = $schedule->getName();
            $this->workScheduleService->deleteWorkSchedule($schedule);

            $message = $assignments ?
                sprintf('El horario "%s" y sus %d asignación(es) han sido eliminados correctamente', $scheduleName, count($assignments)) :
                sprintf('El horario "%s" ha sido eliminado correctamente', $scheduleName);

            return new JsonResponse([
                'success' => true,
                'message' => $message,
                'deleted_assignments' => count($assignments)
            ]);
        } catch (\Exception $e) {
            $errorCode = $this->getWorkScheduleErrorCode($e);
            return new JsonResponse([
                'success' => false,
                'message' => $errorCode['message'],
                'error_code' => $errorCode['code'],
                'error_type' => $errorCode['code']
            ], $errorCode['http_code']);
        }
    }

    /**
     * Obtiene el código de error apropiado para WorkSchedule basado en la excepción
     */
    private function getWorkScheduleErrorCode(\Exception $e): array
    {
        $message = $e->getMessage();

        // Buscar el código de error basado en el mensaje
        foreach (WorkScheduleErrorCodes::getAllErrorCodes() as $errorCode) {
            if (strpos($message, $errorCode['message']) !== false) {
                return $errorCode;
            }
        }

        // Si no se encuentra un código específico, usar el apropiado por tipo de excepción
        if ($e instanceof \InvalidArgumentException) {
            return WorkScheduleErrorCodes::VALIDATION_ERROR;
        }

        // Error genérico por defecto
        return WorkScheduleErrorCodes::GENERAL_ERROR;
    }
}
