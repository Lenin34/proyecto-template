<?php

namespace App\Controller;

use App\Entity\App\Event;
use App\Entity\App\Notification;
use App\Entity\App\User;
use App\Enum\ErrorCodes\EventErrorCodes;
use App\Enum\Status;
use App\Form\EventType;
use App\Message\SendPushNotification;
use App\Messenger\Stamp\TenantStamp;
use App\Repository\EventRepository;
use App\Service\ApplicationErrorService;
use App\Service\ImagePathService;
use App\Service\ImageUploadService;
use App\Service\NotificationAudienceService;
use App\Service\RegionAccessService;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{dominio}/event')]
final class EventController extends AbstractController
{
    private ImageUploadService $imageUploadService;
    private ImagePathService $imagePathService;
    private TenantManager $tenantManager;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private RegionAccessService $regionAccessService;
    private ApplicationErrorService $applicationErrorService;
    private NotificationAudienceService $notificationAudienceService;
    private MessageBusInterface $messageBus;
    private EntityManagerInterface $em;

    public function __construct(
        ImageUploadService $imageUploadService,
        ImagePathService $imagePathService,
        TenantManager $tenantManager,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrfTokenManager,
        RegionAccessService $regionAccessService,
        MessageBusInterface $messageBus,
        ApplicationErrorService $applicationErrorService,
        NotificationAudienceService $notificationAudienceService
    ) {
        $this->imageUploadService = $imageUploadService;
        $this->imagePathService = $imagePathService;
        $this->tenantManager = $tenantManager;
        $this->em = $em;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->regionAccessService = $regionAccessService;
        $this->messageBus = $messageBus;
        $this->applicationErrorService = $applicationErrorService;
        $this->notificationAudienceService = $notificationAudienceService;
    }

    #[Route('/', name: 'app_event_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            $event = new Event();
            $form = $this->createForm(EventType::class, $event, [
                'action' => $this->generateUrl('app_event_new', ['dominio' => $dominio]),
                'method' => 'POST',
            ]);

            $qb = $em->createQueryBuilder()
                ->select('e')
                ->from('App\Entity\App\Event', 'e')
                ->leftJoin('e.companies', 'c')
                ->where('e.status = :status')
                ->setParameter('status', Status::ACTIVE);

            // Security Logic: Admin ve todo, Lider solo sus empresas (+ globales)
            $this->regionAccessService->applyCompaniesFilter($qb, 'c', true);

            $qb->groupBy('e.id');
            $events = $qb->getQuery()->getResult();

            // Get active regions and companies for the modal form
            $regions = $em->createQueryBuilder()
                ->select('r', 'c')
                ->from('App\Entity\App\Region', 'r')
                ->leftJoin('r.companies', 'c')
                ->where('r.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('r.name', 'ASC')
                ->getQuery()
                ->getResult();

            // Generar token CSRF específico para eliminación
            $csrfToken = $this->csrfTokenManager->getToken('delete_event')->getValue();

            return $this->render('event/index.html.twig', [
                'form' => $form->createView(),
                'events' => $events,
                'regions' => $regions,
                'csrf_token_delete' => $csrfToken,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    // ... (datatable and new methods remain unchanged) ...

    #[Route('/{id}/details', name: 'app_event_details', methods: ['GET'])]
    public function details(string $dominio, int $id): JsonResponse
    {
        if (empty($dominio)) {
            $errorCode = EventErrorCodes::EVENT_VALIDATION_ERROR;
            return new JsonResponse([
                'error' => $errorCode['message'],
                'error_code' => $errorCode['code']
            ], $errorCode['http_code']);
        }

        try {
            $em = $this->tenantManager->getEntityManager();

            $event = $em->createQueryBuilder()
                ->select('e', 'c', 'r')
                ->from('App\Entity\App\Event', 'e')
                ->leftJoin('e.companies', 'c')
                ->leftJoin('e.region', 'r')
                ->where('e.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$event) {
                $errorCode = EventErrorCodes::EVENT_NOT_FOUND;
                return new JsonResponse([
                    'error' => $errorCode['message'],
                    'error_code' => $errorCode['code']
                ], $errorCode['http_code']);
            }

            $companies = [];
            foreach ($event->getCompanies() as $company) {
                $companies[] = [
                    'id' => $company->getId(),
                    'name' => $company->getName()
                ];
            }

            return new JsonResponse([
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'start_date' => $event->getStartDate() ? $event->getStartDate()->format('Y-m-d\TH:i') : null,
                'end_date' => $event->getEndDate() ? $event->getEndDate()->format('Y-m-d\TH:i') : null,
                'image' => $event->getImage(),
                'region' => $event->getRegion() ? [
                    'id' => $event->getRegion()->getId(),
                    'name' => mb_check_encoding($event->getRegion()->getName(), 'UTF-8') ? $event->getRegion()->getName() : utf8_encode($event->getRegion()->getName())
                ] : null,
                'companies' => $companies,
                'companyIds' => array_column($companies, 'id')
            ]);

        } catch (\Exception $e) {
            $errorCode = EventErrorCodes::EVENT_CREATE_FAILED;
            return new JsonResponse([
                'error' => $errorCode['message'],
                'error_code' => $errorCode['code'],
                'details' => $e->getMessage()
            ], $errorCode['http_code']);
        }
    }

    #[Route('/{id}/edit', name: 'app_event_edit', methods: ['POST'])]
    public function edit(string $dominio, Request $request, int $id): JsonResponse
    {
        if (empty($dominio)) {
            $errorCode = EventErrorCodes::EVENT_VALIDATION_ERROR;
            return new JsonResponse([
                'status' => 'error',
                'message' => $errorCode['message'],
                'error_code' => $errorCode['code']
            ], $errorCode['http_code']);
        }
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $event = $entityManager->createQueryBuilder()
                ->select('e')
                ->from('App\Entity\App\Event', 'e')
                ->where('e.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$event) {
                $errorCode = EventErrorCodes::EVENT_NOT_FOUND;
                return new JsonResponse([
                    'status' => 'error',
                    'message' => $errorCode['message'],
                    'error_code' => $errorCode['code']
                ], $errorCode['http_code']);
            }

            $form = $this->createForm(EventType::class, $event, [
                'csrf_protection' => true,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {

                /** @var UploadedFile $imageFile */
                $imageFile = $form->get('image')->getData();

                if ($imageFile) {
                    $relativePath = $this->imageUploadService->uploadImage($imageFile, 'event');
                    if (!$relativePath) {
                        $errorCode = EventErrorCodes::EVENT_IMAGE_UPLOAD_FAILED;
                        return new JsonResponse([
                            'status' => 'error',
                            'message' => $errorCode['message'],
                            'error_code' => $errorCode['code']
                        ], $errorCode['http_code']);
                    }
                    $event->setImage($relativePath);
                }

                $event->setUpdatedAt(new \DateTimeImmutable());

                // Clear existing companies
                foreach ($event->getCompanies() as $company) {
                    $event->removeCompany($company);
                }

                // Handle selected companies
                $selectedCompanies = $request->request->all('selected_companies');
                if ($selectedCompanies) {
                    $companyNotFound = false;
                    foreach ($selectedCompanies as $companyId) {
                        $company = $entityManager->createQueryBuilder()
                            ->select('c')
                            ->from('App\Entity\App\Company', 'c')
                            ->where('c.id = :id')
                            ->andWhere('c.status = :status')
                            ->setParameter('id', $companyId)
                            ->setParameter('status', Status::ACTIVE)
                            ->getQuery()
                            ->getOneOrNullResult();
                        if ($company) {
                            $event->addCompany($company);
                        } else {
                            $companyNotFound = true;
                        }
                    }

                    if ($companyNotFound) {
                        $errorCode = EventErrorCodes::EVENT_COMPANY_NOT_FOUND;
                        return new JsonResponse([
                            'status' => 'error',
                            'message' => $errorCode['message'],
                            'error_code' => $errorCode['code']
                        ], $errorCode['http_code']);
                    }
                }

                $entityManager->flush();

                return new JsonResponse(['status' => 'success', 'message' => 'Evento actualizado correctamente']);
            }

            // Get form errors
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            $errorCode = EventErrorCodes::EVENT_VALIDATION_ERROR;
            return new JsonResponse([
                'status' => 'error',
                'message' => $errorCode['message'],
                'error_code' => $errorCode['code'],
                'errors' => $errors
            ], $errorCode['http_code']);

        } catch (\Exception $e) {
            $errorCode = EventErrorCodes::EVENT_UPDATE_FAILED;
            return new JsonResponse([
                'status' => 'error',
                'message' => $errorCode['message'],
                'error_code' => $errorCode['code'],
                'details' => $e->getMessage()
            ], $errorCode['http_code']);
        }
    }

    #[Route('/{id}/delete', name: 'app_event_delete', methods: ['POST'])]
    public function delete(string $dominio, Request $request, int $id): JsonResponse
    {
        try {
            $entityManager = $this->tenantManager->getEntityManager();

            $event = $entityManager->createQueryBuilder()
                ->select('e')
                ->from('App\Entity\App\Event', 'e')
                ->where('e.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$event) {
                $errorCode = EventErrorCodes::EVENT_NOT_FOUND;
                return new JsonResponse([
                    'status' => 'error',
                    'message' => $errorCode['message'],
                    'error_code' => $errorCode['code']
                ], $errorCode['http_code']);
            }

            $submittedToken = $request->request->get('_token');
            $isValid = $this->isCsrfTokenValid('delete_event', $submittedToken);

            if (!$isValid) {
                // Debugging Logs
                error_log('❌ CSRF Error en EventController::delete');
                error_log('Token recibido: ' . ($submittedToken ?? 'NULL'));
                error_log('ID de Token esperado: delete_event');

                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Token de seguridad inválido',
                    'error_code' => 'CSRF-001'
                ], 403);
            }

            if ($event->getStatus() === Status::INACTIVE) {
                $errorCode = EventErrorCodes::EVENT_ALREADY_INACTIVE;
                return new JsonResponse([
                    'status' => 'error',
                    'message' => $errorCode['message'],
                    'error_code' => $errorCode['code']
                ], $errorCode['http_code']);
            }

            $event->setStatus(Status::INACTIVE);
            $event->setUpdatedAt(new \DateTimeImmutable());

            $entityManager->persist($event);
            $entityManager->flush();

            return new JsonResponse(['status' => 'success', 'message' => 'Evento eliminado correctamente']);

        } catch (\Exception $e) {
            $errorCode = EventErrorCodes::EVENT_DELETE_FAILED;
            return new JsonResponse([
                'status' => 'error',
                'message' => $errorCode['message'],
                'error_code' => $errorCode['code'],
                'details' => $e->getMessage()
            ], $errorCode['http_code']);
        }
    }
    
    #[Route('/new', name: 'app_event_new', methods: ['GET', 'POST'])]
    public function new(string $dominio, Request $request): Response
    {
        try {
            $em = $this->tenantManager->getEntityManager();
            $event = new Event();
            $form = $this->createForm(EventType::class, $event);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
            // FORCE DEBUG LOGGING
            file_put_contents('php://stderr', "\n\n🚀 [EVENT-CONTROLLER] Form IS submitted and valid!\n");
            file_put_contents('php://stderr', "🚀 [EVENT-CONTROLLER] POST Data: " . print_r($_POST, true) . "\n");
            
            // Backup log to file
            file_put_contents(__DIR__ . '/../../var/log/event_debug.log', date('Y-m-d H:i:s') . " - Form submitted\n", FILE_APPEND);
            file_put_contents(__DIR__ . '/../../var/log/event_debug.log', "POST: " . print_r($_POST, true) . "\n", FILE_APPEND);

            /** @var UploadedFile $imageFile */
                $imageFile = $form->get('image')->getData();

                if ($imageFile) {
                    $relativePath = $this->imageUploadService->uploadImage($imageFile, 'event');
                    if (!$relativePath) {
                        $errorCode = EventErrorCodes::EVENT_IMAGE_UPLOAD_FAILED;
                        if ($request->isXmlHttpRequest()) {
                            return new JsonResponse([
                                'status' => 'error',
                                'message' => $errorCode['message'],
                                'error_code' => $errorCode['code']
                            ], $errorCode['http_code']);
                        }
                        $this->addFlash('error', $errorCode['message']);
                        return $this->redirectToRoute('app_event_index', ['dominio' => $dominio]);
                    }
                    $event->setImage($relativePath);
                }

                $event->setStatus(Status::ACTIVE);
                $event->setCreatedAt(new \DateTimeImmutable());
                $event->setUpdatedAt(new \DateTimeImmutable());

                // Handle selected companies
                $selectedCompanies = $request->request->all('selected_companies');
                if ($selectedCompanies) {
                    $companyNotFound = false;
                    foreach ($selectedCompanies as $companyId) {
                        $company = $em->createQueryBuilder()
                            ->select('c')
                            ->from('App\Entity\App\Company', 'c')
                            ->where('c.id = :id')
                            ->andWhere('c.status = :status')
                            ->setParameter('id', $companyId)
                            ->setParameter('status', Status::ACTIVE)
                            ->getQuery()
                            ->getOneOrNullResult();
                        if ($company) {
                            $event->addCompany($company);
                        } else {
                            $companyNotFound = true;
                        }
                    }

                    if ($companyNotFound) {
                        $errorCode = EventErrorCodes::EVENT_COMPANY_NOT_FOUND;
                        if ($request->isXmlHttpRequest()) {
                            return new JsonResponse([
                                'status' => 'error',
                                'message' => $errorCode['message'],
                                'error_code' => $errorCode['code']
                            ], $errorCode['http_code']);
                        }
                        $this->addFlash('error', $errorCode['message']);
                        return $this->redirectToRoute('app_event_index', ['dominio' => $dominio]);
                    }
                }

                $em->persist($event);
                $em->flush();

                // Check if notification should be sent
                $sendNotification = $request->request->get('send_notification', false);

                // Debug log to file
                $logFile = __DIR__ . '/../../var/log/event_notification.log';
                file_put_contents($logFile, "\n=== EVENT CREATION " . date('Y-m-d H:i:s') . " ===\n", FILE_APPEND);
                file_put_contents($logFile, "Checkbox value: " . ($sendNotification ? 'CHECKED (' . print_r($sendNotification, true) . ')' : 'UNCHECKED') . "\n", FILE_APPEND);
                file_put_contents($logFile, "Event ID: " . $event->getId() . "\n", FILE_APPEND);
                file_put_contents($logFile, "Event Title: " . $event->getTitle() . "\n", FILE_APPEND);

                // Send push notification AND create Notification entity
                if ($sendNotification) {
                    try {
                        // 1. Create Notification Entity
                        $notification = new Notification();
                        $notification->setTitle('Nuevo Evento: ' . mb_strimwidth($event->getTitle(), 0, 25, '...'));
                        // Truncate message to 100 chars as per entity definition
                        $notification->setMessage(mb_strimwidth($event->getDescription() ?? 'Se ha publicado un nuevo evento', 0, 100, '...'));
                        $notification->setCreatedAt(new \DateTimeImmutable());
                        $notification->setUpdatedAt(new \DateTimeImmutable());
                        $notification->setStatus(Status::ACTIVE);

                        // Add Event's Region to Notification
                        if ($event->getRegion()) {
                            $notification->addRegion($event->getRegion());
                        }

                        // Add Event's Companies to Notification (for proper audience tracking)
                        foreach ($event->getCompanies() as $company) {
                            $notification->addCompany($company);
                        }

                        $em->persist($notification);
                        $em->flush();
                        
                        error_log("🔔 [EVENT-NOTIFICATION] Notification Entity created with ID: " . $notification->getId());

                        // 2. Send Push
                        $region = $event->getRegion();
                        file_put_contents($logFile, "Event region: " . ($region ? $region->getName() . " (ID: {$region->getId()})" : 'NULL') . "\n", FILE_APPEND);

                        // Get companies associated with this event
                        $companies = $event->getCompanies();
                        file_put_contents($logFile, "Event has " . $companies->count() . " linked companies\n", FILE_APPEND);

                        // Use NotificationAudienceService to get the target users
                        $users = $this->notificationAudienceService->getAudienceUsers($region, $companies);
                        
                        file_put_contents($logFile, "ℹ️ NotificationAudienceService found " . count($users) . " unique active users.\n", FILE_APPEND);

                        if (empty($users)) {
                            file_put_contents($logFile, "⚠️ No active users found in audience - notification not sent\n", FILE_APPEND);
                            $deviceTokens = [];
                        } else {
                            // Collect device tokens from the found users
                            $deviceTokens = [];
                            foreach ($users as $user) {
                                // Log user details for debugging (optional, can be reduced in prod)
                                // file_put_contents($logFile, "  User: {$user->getEmail()} (ID: {$user->getId()})\n", FILE_APPEND);
                                
                                foreach ($user->getDeviceTokens() as $tokenData) {
                                    $token = $tokenData->getToken();
                                    if ($token && !in_array($token, $deviceTokens)) {      
                                        $deviceTokens[] = $token;
                                    }
                                }
                            }
                        }
                            // Eliminar duplicados
                            $deviceTokens = array_unique($deviceTokens);
                            file_put_contents($logFile, "Total unique device tokens: " . count($deviceTokens) . "\n", FILE_APPEND);

                            if (!empty($deviceTokens)) {
                                // Dispatch async message with TenantStamp for multi-tenant routing
                                $currentTenant = $this->tenantManager->getCurrentTenant();
                                $message = new SendPushNotification(
                                    $notification->getTitle(),
                                    $notification->getMessage() ?? 'Nueva notificación',
                                    $deviceTokens,
                                    $notification->getId(),
                                    $currentTenant,
                                    $event->getId(),
                                    null
                                );

                                $this->messageBus->dispatch($message, [
                                    new TenantStamp($currentTenant)
                                ]);

                                file_put_contents($logFile, "✅ Notification dispatched to async queue for tenant: {$currentTenant}!\n", FILE_APPEND);
                            } else {
                                file_put_contents($logFile, "⚠️ No device tokens found - notification not sent\n", FILE_APPEND);
                            }
                    } catch (\Exception $e) {
                         file_put_contents($logFile, "❌ Exception: " . $e->getMessage() . "\n", FILE_APPEND);
                         file_put_contents($logFile, "Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND);

                        // Log error but don't fail the event creation
                        $this->applicationErrorService->createError([
                            'code' => 'EVENT-NOTIF-002',
                            'message' => 'Excepción al despachar notificación push para evento'
                        ], [
                            'event_id' => $event->getId(),
                            'exception' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                    }
                } else {
                    file_put_contents($logFile, "⚠️ Checkbox not checked - notification not sent\n", FILE_APPEND);
                }

                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse(['status' => 'success', 'message' => 'Evento creado y notificación en cola']);
                }

                return $this->redirectToRoute('app_event_index', ['dominio' => $dominio], Response::HTTP_SEE_OTHER);
            }

            if ($request->isXmlHttpRequest() && $form->isSubmitted()) {
                 // Get form errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $errorCode = EventErrorCodes::EVENT_VALIDATION_ERROR;
                return new JsonResponse([
                    'status' => 'error',
                    'message' => $errorCode['message'],
                    'error_code' => $errorCode['code'],
                    'errors' => $errors
                ], $errorCode['http_code']);
            }

            return $this->render('event/new.html.twig', [
                'event' => $event,
                'form' => $form,
            ]);
        } catch (\Exception $e) {
             if ($request->isXmlHttpRequest()) {
                $errorCode = EventErrorCodes::EVENT_CREATE_FAILED;
                return new JsonResponse([
                    'status' => 'error',
                    'message' => $errorCode['message'],
                    'error_code' => $errorCode['code'],
                    'details' => $e->getMessage()
                ], $errorCode['http_code']);
            }
            throw $e;
        }
    }

    // ... (new, show, edit, delete methods remain unchanged) ...

    #[Route('/datatable', name: 'app_event_datatable', methods: ['GET'])]
    public function datatable(string $dominio, Request $request): JsonResponse
    {
        if (empty($dominio)) {
            throw $this->createNotFoundException('Dominio no especificado en la ruta.');
        }

        $em = $this->tenantManager->getEntityManager();

        // DataTables parameters
        $draw = (int) $request->query->get('draw', 1);
        $start = (int) $request->query->get('start', 0);
        $length = (int) $request->query->get('length', 25);
        
        $search = $request->query->all('search');
        $searchValue = isset($search['value']) ? $search['value'] : '';
        
        $order = $request->query->all('order');
        $orderColumn = isset($order[0]['column']) ? (int) $order[0]['column'] : 0;
        $orderDir = isset($order[0]['dir']) ? $order[0]['dir'] : 'desc';

        // DataTables columns in the UI are: [0] Actions, [1] Título, [2] Región, [3] Empresas, [4] Fecha
        // Map incoming order column index to entity fields
        $columns = [
            0 => null,          // Actions (not orderable)
            1 => 'title',       // Título
            2 => null,          // Región (not orderable for now)
            3 => null,          // Empresas (not orderable, computed field)
            4 => 'created_at',  // Fecha - ordenar por created_at para mostrar más recientes primero
        ];
        $orderBy = isset($columns[$orderColumn]) && $columns[$orderColumn] ? $columns[$orderColumn] : 'created_at';

        $qb = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Event', 'e')
            ->leftJoin('e.companies', 'c')
            ->where('e.status = :status')
            ->setParameter('status', Status::ACTIVE);

        // Security Logic: Admin ve todo, Lider solo sus empresas (+ globales)
        $this->regionAccessService->applyCompaniesFilter($qb, 'c', true);

        // Total Records (Accessible by user)
        $totalRecordsQb = clone $qb;
        $totalRecords = (int) $totalRecordsQb->select('COUNT(DISTINCT e.id)')->getQuery()->getSingleScalarResult();

        if (!empty($searchValue)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('e.title', ':search'),
                    $qb->expr()->like('e.description', ':search')
                )
            )->setParameter('search', '%' . $searchValue . '%');
        }

        $countQb = clone $qb;
        $totalFiltered = (int) $countQb->select('COUNT(DISTINCT e.id)')->getQuery()->getSingleScalarResult();

        // Fix ordering for joined fields
        if (strpos($orderBy, '.') !== false) {
            [$alias, $field] = explode('.', $orderBy);
            $qb->orderBy($alias . '.' . $field, $orderDir);
        } else {
            $qb->orderBy('e.' . $orderBy, $orderDir);
        }

        $qb->setFirstResult($start)->setMaxResults($length);
        $qb->groupBy('e.id');

        $results = $qb->getQuery()->getResult();

        $data = [];
        foreach ($results as $item) {
            $start = $item->getStartDate();
            $end = $item->getEndDate();
            $dateLabel = '';
            if ($start && $end) {
                $dateLabel = $start->format('d/m/Y H:i') . ' - ' . $end->format('d/m/Y H:i');
            } elseif ($start) {
                $dateLabel = $start->format('d/m/Y H:i');
            } elseif ($end) {
                $dateLabel = $end->format('d/m/Y H:i');
            }

            $data[] = [
                'id' => $item->getId(),
                'title' => $item->getTitle(),
                'region' => $item->getRegion() ? $item->getRegion()->getName() : 'Sin región',
                'companies' => $item->getCompanyNames(),
                'date' => $dateLabel,
            ];
        }

        return new JsonResponse([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }



    }
