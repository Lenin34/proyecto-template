<?php

namespace App\Controller;

use App\Entity\App\Notification;
use App\Enum\ErrorCodes\NotificationErrorCodes;
use App\Enum\Status;
use App\Form\NotificationType;
use App\Service\ApplicationErrorService;
use App\Service\ExpoNotificationService;
use App\Service\RegionAccessService;
use App\Service\TenantManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

#[Route('/{dominio}/notification')]
final class NotificationController extends AbstractController
{
    private TenantManager $tenantManager;
    private CsrfTokenManagerInterface $csrfTokenManager;
    private RegionAccessService $regionAccessService;

    public function __construct(
        TenantManager $tenantManager,
        CsrfTokenManagerInterface $csrfTokenManager,
        RegionAccessService $regionAccessService
    ) {
        $this->tenantManager = $tenantManager;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->regionAccessService = $regionAccessService;
    }

    #[Route('', name: 'app_notification_index', methods: ['GET'])]
    public function index(string $dominio): Response
    {
        $this->tenantManager->setCurrentTenant($dominio);
        try {
            $em = $this->tenantManager->getEntityManager();
            
            $notification = new Notification();
            $form = $this->createForm(NotificationType::class, $notification, [
                'action' => $this->generateUrl('app_notification_new', ['dominio' => $dominio]),
                'method' => 'POST',
            ]);

            // Generar token CSRF específico para eliminación
            $csrfToken = $this->csrfTokenManager->getToken('delete_notification')->getValue();

            // Fetch active regions for the edit modal (loaded AFTER form to avoid EM conflicts)
            $regions = $em->getRepository(\App\Entity\App\Region::class)->findBy(['status' => Status::ACTIVE]);

            return $this->render('notification/index.html.twig', [
                'form' => $form->createView(),
                'csrf_token_delete' => $csrfToken,
                'regions' => $regions,
            ]);
        } catch (\Exception $e) {
            throw $this->createNotFoundException('Tenant error: ' . $e->getMessage(), $e);
        }
    }

    // ... (datatable and new methods remain unchanged) ...

    #[Route('/datatable', name: 'app_notification_datatable', methods: ['GET'])]
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
        $orderDir = isset($order[0]['dir']) ? $order[0]['dir'] : 'asc';

        $columns = ['id', 'title', 'message', 'status'];
        $orderBy = isset($columns[$orderColumn]) ? $columns[$orderColumn] : 'id';

        $qb = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Notification', 'e')
            ->leftJoin('e.regions', 'r')
            ->where('e.status = :status')
            ->setParameter('status', Status::ACTIVE);

        // Security Logic: Admin ve todo, Lider solo sus regiones (+ globales)
        $this->regionAccessService->applyRegionsFilter($qb, 'r', true);

        // Total Records
        $totalRecordsQb = clone $qb;
        $totalRecords = (int) $totalRecordsQb->select('COUNT(DISTINCT e.id)')->getQuery()->getSingleScalarResult();

        if (!empty($searchValue)) {
            $qb->andWhere(
                $qb->expr()->orX(
                    $qb->expr()->like('e.title', ':search'),
                    $qb->expr()->like('e.message', ':search')
                )
            )->setParameter('search', '%' . $searchValue . '%');
        }

        $countQb = clone $qb;
        $totalFiltered = (int) $countQb->select('COUNT(DISTINCT e.id)')->getQuery()->getSingleScalarResult();

        // Fix ordering
        if (strpos($orderBy, '.') !== false) {
            [$alias, $field] = explode('.', $orderBy);
            $qb->orderBy($alias . '.' . $field, $orderDir);
        } else {
            $qb->orderBy('e.' . $orderBy, $orderDir);
        }

        $qb->setFirstResult($start)->setMaxResults($length);
        $qb->groupBy('e.id');

        $results = $qb->getQuery()->getResult();

        // Load regions and companies separately to avoid GROUP BY issues
        if (!empty($results)) {
            $notificationIds = array_map(fn($n) => $n->getId(), $results);

            // Eager load regions
            $em->createQueryBuilder()
                ->select('n', 'r')
                ->from('App\Entity\App\Notification', 'n')
                ->leftJoin('n.regions', 'r')
                ->where('n.id IN (:ids)')
                ->setParameter('ids', $notificationIds)
                ->getQuery()
                ->getResult();

            // Eager load companies
            $em->createQueryBuilder()
                ->select('n', 'c')
                ->from('App\Entity\App\Notification', 'n')
                ->leftJoin('n.companies', 'c')
                ->where('n.id IN (:ids)')
                ->setParameter('ids', $notificationIds)
                ->getQuery()
                ->getResult();
        }

        $data = [];
        foreach ($results as $item) {
            // Get region names
            $regionNames = [];
            foreach ($item->getRegions() as $region) {
                $regionNames[] = $region->getName();
            }

            // Get company names
            $companyNames = [];
            foreach ($item->getCompanies() as $company) {
                $companyNames[] = $company->getName();
            }

            $data[] = [
                'id' => $item->getId(),
                'title' => $item->getTitle(),
                'message' => mb_strimwidth($item->getMessage() ?? '', 0, 50, '...'),
                'regions' => $regionNames,
                'companies' => $companyNames,
            ];
        }

        return new JsonResponse([
            'draw' => $draw,
            'recordsTotal' => $totalRecords,
            'recordsFiltered' => $totalFiltered,
            'data' => $data,
        ]);
    }

    #[Route('/new', name: 'app_notification_new', methods: ['GET', 'POST'])]
    public function new(string $dominio, Request $request): Response
    {
        // Validate dominio parameter
        if (empty($dominio)) {
            if ($request->isXmlHttpRequest() || $request->headers->get('Content-Type') === 'application/json') {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Dominio no especificado en la ruta'
                ], 400);
            }
            throw $this->createNotFoundException('Dominio no especificado');
        }

        $this->tenantManager->setCurrentTenant($dominio);

        // Get EntityManager with specific error handling
        try {
            $em = $this->tenantManager->getEntityManager();
        } catch (\Exception $e) {
            // Log the tenant error with details
            error_log(sprintf(
                'TenantManager error in notification creation - Dominio: %s, Error: %s, File: %s, Line: %d',
                $dominio,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Error de configuración del tenant: ' . $e->getMessage()
                ], 500);
            }
            throw $this->createNotFoundException('Error de configuración del tenant: ' . $e->getMessage());
        }

        try {
            $notification = new Notification();
            $form = $this->createForm(NotificationType::class, $notification);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Log notification creation attempt
                error_log(sprintf(
                    'Creating notification - Dominio: %s, Title: %s',
                    $dominio,
                    $notification->getTitle()
                ));

                $notification->setStatus(Status::ACTIVE);
                $notification->setCreatedAt(new \DateTimeImmutable());
                $notification->setUpdatedAt(new \DateTimeImmutable());

                // Regions are already handled by the form via handleRequest
                // No manual processing needed

                // Persist notification
                try {
                    $em->persist($notification);
                    $em->flush();
                    
                    error_log("Notification created successfully - ID: " . $notification->getId());
                    
                    // Return JSON for AJAX requests
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'status' => 'success',
                            'message' => 'Notificación creada correctamente'
                        ]);
                    }
                    
                    $this->addFlash('success', 'Notificación creada correctamente');
                    return $this->redirectToRoute('app_notification_index', ['dominio' => $dominio], Response::HTTP_SEE_OTHER);
                    
                } catch (\Exception $e) {
                    error_log(sprintf(
                        'Database error saving notification - Error: %s, File: %s, Line: %d, Trace: %s',
                        $e->getMessage(),
                        $e->getFile(),
                        $e->getLine(),
                        $e->getTraceAsString()
                    ));
                    
                    if ($request->isXmlHttpRequest()) {
                        return new JsonResponse([
                            'status' => 'error',
                            'message' => 'Error al guardar en la base de datos: ' . $e->getMessage()
                        ], 500);
                    }
                    throw $e;
                }
            }

            // Form validation errors
            if ($form->isSubmitted() && !$form->isValid()) {
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                
                if ($request->isXmlHttpRequest()) {
                    return new JsonResponse([
                        'status' => 'error',
                        'message' => 'Error de validación',
                        'errors' => $errors
                    ], 400);
                }
            }

            // For GET requests or invalid POST
            return $this->render('notification/new.html.twig', [
                'notification' => $notification,
                'form' => $form,
            ]);
            
        } catch (\Exception $e) {
            // Log the complete error
            error_log(sprintf(
                'Unexpected error in notification creation - Dominio: %s, Error: %s, File: %s, Line: %d, Trace: %s',
                $dominio,
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                $e->getTraceAsString()
            ));
            
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse([
                    'status' => 'error',
                    'message' => 'Error inesperado: ' . $e->getMessage()
                ], 500);
            }
            
            throw $this->createNotFoundException('Error al procesar la solicitud: ' . $e->getMessage());
        }
    }

    #[Route('/{id}/details', name: 'app_notification_details', methods: ['GET'])]
    public function details(string $dominio, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['error' => 'Dominio no especificado'], 400);
        }

        try {
            $em = $this->tenantManager->getEntityManager();

            $notification = $em->createQueryBuilder()
                ->select('n', 'r', 'c')
                ->from('App\Entity\App\Notification', 'n')
                ->leftJoin('n.regions', 'r')
                ->leftJoin('n.companies', 'c')
                ->where('n.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$notification) {
                return new JsonResponse(['error' => 'Notificación no encontrada'], 404);
            }

            $regions = [];
            foreach ($notification->getRegions() as $region) {
                $regions[] = [
                    'id' => $region->getId(),
                    'name' => $region->getName()
                ];
            }

            $companies = [];
            foreach ($notification->getCompanies() as $company) {
                $companies[] = [
                    'id' => $company->getId(),
                    'name' => $company->getName()
                ];
            }

            return new JsonResponse([
                'id' => $notification->getId(),
                'title' => $notification->getTitle(),
                'message' => $notification->getMessage(),
                'regions' => $regions,
                'regionIds' => array_column($regions, 'id'),
                'companies' => $companies,
                'companyIds' => array_column($companies, 'id')
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/edit', name: 'app_notification_edit', methods: ['POST'])]
    public function edit(string $dominio, Request $request, int $id): JsonResponse
    {
        if (empty($dominio)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Dominio no especificado'], 400);
        }
        $this->tenantManager->setCurrentTenant($dominio);
        try {
            $em = $this->tenantManager->getEntityManager();

            $notification = $em->createQueryBuilder()
                ->select('n')
                ->from('App\Entity\App\Notification', 'n')
                ->where('n.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$notification) {
                return new JsonResponse(['status' => 'error', 'message' => 'Notificación no encontrada'], 404);
            }

            $form = $this->createForm(NotificationType::class, $notification, [
                'csrf_protection' => true,
            ]);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Regions are handled automatically by handleRequest()
                // No manual processing needed
                
                $notification->setUpdatedAt(new \DateTimeImmutable());
                $em->flush();

                return new JsonResponse(['status' => 'success', 'message' => 'Notificación actualizada correctamente']);
            }

            // Get form errors
            $errors = [];
            foreach ($form->getErrors(true) as $error) {
                $errors[] = $error->getMessage();
            }

            return new JsonResponse(['status' => 'error', 'message' => 'Error de validación', 'errors' => $errors], 400);

        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/delete', name: 'app_notification_delete', methods: ['POST'])]
    public function delete(string $dominio, Request $request, int $id): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $notification = $em->createQueryBuilder()
                ->select('n')
                ->from('App\Entity\App\Notification', 'n')
                ->where('n.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$notification) {
                return new JsonResponse(['status' => 'error', 'message' => 'Notificación no encontrada'], 404);
            }

            // Validar token CSRF (using specific 'delete_notification' token)
            if ($this->isCsrfTokenValid('delete_notification', $request->request->get('_token'))) {
                $notification->setStatus(Status::INACTIVE);
                $em->flush();
                return new JsonResponse(['status' => 'success', 'message' => 'Notificación eliminada correctamente']);
            } else {
                return new JsonResponse(['status' => 'error', 'message' => 'Token de seguridad inválido'], 403);
            }

        } catch (\Exception $e) {
            return new JsonResponse(['status' => 'error', 'message' => 'Error del servidor: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/send', name: 'app_notification_send', methods: ['GET', 'POST'])]
    public function expoNotification(
        string $dominio,
        int $id,
        Request $request,
        ApplicationErrorService $applicationErrorService,
        ExpoNotificationService $expoNotificationService
    ): Response
    {
        $isAjax = $request->isXmlHttpRequest() || $request->headers->get('Accept') === 'application/json';
        
        // Helper function for responses
        $respond = function(string $status, string $message, int $httpCode = 200) use ($isAjax, $dominio) {
            if ($isAjax) {
                return new JsonResponse(['status' => $status, 'message' => $message], $httpCode);
            }
            $this->addFlash($status, $message);
            return $this->redirectToRoute('app_notification_index', ['dominio' => $dominio]);
        };

        try {
            // CRÍTICO: Configurar el tenant ANTES de obtener el EntityManager
            $this->tenantManager->setCurrentTenant($dominio);
            
            // IMPORTANTE: Cargar la notificación con sus regiones usando el EntityManager del tenant correcto
            $em = $this->tenantManager->getEntityManager();
            
            // Usar QueryBuilder con JOIN FETCH para cargar las regiones y empresas
            $notification = $em->createQueryBuilder()
                ->select('n', 'r', 'c')
                ->from(Notification::class, 'n')
                ->leftJoin('n.regions', 'r')
                ->leftJoin('n.companies', 'c')
                ->where('n.id = :id')
                ->setParameter('id', $id)
                ->getQuery()
                ->getOneOrNullResult();
            
            if (!$notification) {
                return $respond('error', 'Notificación no encontrada', 404);
            }
            
            // DEBUG: Verificar conexión y datos
            $connection = $em->getConnection();
            $dbName = $connection->getDatabase();
            
            // Verificar directamente en SQL
            $sql = "SELECT nr.notification_id, nr.region_id FROM notification_region nr WHERE nr.notification_id = ?";
            $stmt = $connection->prepare($sql);
            $result = $stmt->executeQuery([$id]);
            $dbRegions = $result->fetchAllAssociative();

            if ($notification->getStatus() !== Status::ACTIVE) {
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NOT_ACTIVE, [
                    'notification_id' => $notification->getId(),
                ]);
                return $respond('error', NotificationErrorCodes::NOTIFICATION_NOT_ACTIVE['message'], 400);
            }

            $regions = $notification->getRegions();
            $companies = $notification->getCompanies();

            // Si Doctrine no cargó las regiones pero existen en BD, cargarlas manualmente
            if ($regions->isEmpty() && !empty($dbRegions)) {
                foreach ($dbRegions as $dbRegion) {
                    $region = $em->getRepository(\App\Entity\App\Region::class)->find($dbRegion['region_id']);
                    if ($region) {
                        $notification->addRegion($region);
                    }
                }
                $regions = $notification->getRegions();
            }

            // Determine notification type and collect users accordingly
            $hasRegions = !$regions->isEmpty();
            $hasCompanies = !$companies->isEmpty();

            error_log("=== NOTIFICATION AUDIENCE ANALYSIS ===");
            error_log("Has Regions: " . ($hasRegions ? 'YES (' . $regions->count() . ')' : 'NO'));
            error_log("Has Companies: " . ($hasCompanies ? 'YES (' . $companies->count() . ')' : 'NO'));

            $uniqueUsers = [];

            if ($hasCompanies) {
                // CASE 1 & 3: Companies are specified (with or without regions)
                // When companies are specified, ONLY send to those companies
                error_log("CASE: Companies specified - sending to selected companies only");
                foreach ($companies as $company) {
                    $companyUsers = $company->getUsers()->toArray();
                    error_log("  Company {$company->getId()} ({$company->getName()}): " . count($companyUsers) . " users");
                    foreach ($companyUsers as $user) {
                        if ($user->getStatus() === \App\Enum\Status::ACTIVE) {
                            $uniqueUsers[$user->getId()] = $user;
                        }
                    }
                }
            } elseif ($hasRegions) {
                // CASE 2: Only regions specified (no companies)
                // Send to ALL companies in those regions + direct region users
                error_log("CASE: Regions only - sending to all companies in selected regions");
                foreach ($regions as $region) {
                    // All companies in the region
                    $regionCompanies = $region->getCompanies();
                    error_log("  Region {$region->getId()} ({$region->getName()}): {$regionCompanies->count()} companies");

                    foreach ($regionCompanies as $company) {
                        $companyUsers = $company->getUsers()->toArray();
                        error_log("    Company {$company->getId()} ({$company->getName()}): " . count($companyUsers) . " users");
                        foreach ($companyUsers as $user) {
                            if ($user->getStatus() === \App\Enum\Status::ACTIVE) {
                                $uniqueUsers[$user->getId()] = $user;
                            }
                        }
                    }

                    // Direct region users
                    $directUsers = $region->getUsers()->toArray();
                    error_log("    Direct region users: " . count($directUsers));
                    foreach ($directUsers as $user) {
                        if ($user->getStatus() === \App\Enum\Status::ACTIVE) {
                            $uniqueUsers[$user->getId()] = $user;
                        }
                    }
                }
            } else {
                // CASE 4: Neither regions nor companies specified
                // GLOBAL notification - send to ALL users in ALL regions
                error_log("CASE: Global notification - sending to ALL users in ALL regions");

                $allRegions = $em->getRepository(\App\Entity\App\Region::class)->findBy(['status' => \App\Enum\Status::ACTIVE]);
                error_log("  Found " . count($allRegions) . " active regions");

                foreach ($allRegions as $region) {
                    // All companies in each region
                    $regionCompanies = $region->getCompanies();
                    foreach ($regionCompanies as $company) {
                        $companyUsers = $company->getUsers()->toArray();
                        foreach ($companyUsers as $user) {
                            if ($user->getStatus() === \App\Enum\Status::ACTIVE) {
                                $uniqueUsers[$user->getId()] = $user;
                            }
                        }
                    }

                    // Direct region users
                    $directUsers = $region->getUsers()->toArray();
                    foreach ($directUsers as $user) {
                        if ($user->getStatus() === \App\Enum\Status::ACTIVE) {
                            $uniqueUsers[$user->getId()] = $user;
                        }
                    }
                }
            }

            $users = array_values($uniqueUsers);
            error_log("Total unique active users: " . count($users));

            if (empty($users)) {
                error_log("ERROR: No users found");
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_USERS, [
                    'notification_id' => $notification->getId(),
                ]);
                return $respond('error', NotificationErrorCodes::NOTIFICATION_NO_USERS['message'], 400);
            }

            error_log("Calculating unread counts for " . count($users) . " users...");
            
            $messages = [];
            foreach ($users as $user) {
                // Get pre-calculated unread count FOR THE USER
                // We increment by 1 because the current notification wasn't counted as "sent" yet in DB 
                // but it will be after this process. Actually, better get current and add 1.
                $unreadCount = $this->getUserUnreadCount($em, $user->getId());
                // Since this notification is just being sent, it might not be in the count yet
                // if sent_date is null. But we usually set sent_date AFTER sending.
                // So the count returned will be the count BEFORE this one.
                $badgeCount = $unreadCount + 1;

                $tokens = $user->getDeviceTokens();
                foreach ($tokens as $token) {
                    $tokenValue = $token->getToken();
                    // Validate token format before adding to messages
                    if (preg_match('/^Expo(nent)?PushToken\[[\w-]+\]$/', $tokenValue)) {
                        $messages[] = [
                            'to' => $tokenValue,
                            'title' => $notification->getTitle(),
                            'body' => $notification->getMessage(),
                            'sound' => 'default',
                            'channelId' => 'default',
                            'priority' => 'high',
                            'badge' => $badgeCount,
                            'data' => [
                                'type' => 'notification',
                                'id' => $notification->getId()
                            ]
                        ];
                    }
                }
            }

            if (empty($messages)) {
                error_log("ERROR: No valid messages to send (empty or invalid tokens)");
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_USERS_TOKENS, [
                    'notification_id' => $notification->getId(),
                ]);
                return $respond('error', 'No se encontraron tokens válidos para enviar', 400);
            }

            error_log("Calling ExpoNotificationService::sendBulkExpoNotifications() with " . count($messages) . " messages");
            $result = $expoNotificationService->sendBulkExpoNotifications($messages);

            error_log("Expo API Result: " . json_encode($result));

            if ($result['success']) {
                error_log("SUCCESS: Notifications sent");

                // Update sent_date field to track successful notification delivery
                $notification->setSentDate(new \DateTimeImmutable());
                $em->flush();
                error_log("Notification sent_date updated to: " . $notification->getSentDate()->format('Y-m-d H:i:s'));

                return $respond('success', 'Notificaciones enviadas correctamente (' . count($deviceTokens) . ' dispositivos)');
            } else {
                error_log("ERROR: Send failed - " . ($result['error'] ?? 'Unknown error'));
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_SEND_FAILED, [
                    'notification_id' => $notification->getId(),
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
                return $respond('error', NotificationErrorCodes::NOTIFICATION_SEND_FAILED['message'] . ': ' . ($result['error'] ?? ''), 500);
            }

        } catch (\Exception $e) {
            error_log("EXCEPTION in expoNotification: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            if ($isAjax) {
                return new JsonResponse([
                    'status' => 'error', 
                    'message' => 'Error interno: ' . $e->getMessage()
                ], 500);
            }
            throw $this->createNotFoundException('Error: ' . $e->getMessage());
        }
    }

    /*    #[Route('/{id}/send', name: 'app_notification_send', methods: ['POST'])]
        public function send(
            Notification $notification,
            PushNotificationService $pushNotificationService,
            ApplicationErrorService $applicationErrorService,
        ): Response {
            if($notification->getStatus() !== Status::ACTIVE) {
                $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NOT_ACTIVE['message']);
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NOT_ACTIVE, [
                    'notification_id' => $notification->getId(),
                ]);

                return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
            }

            $companies = $notification->getCompanies();
            if($companies->isEmpty()) {
                $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NO_COMPANY['message']);
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_COMPANY, [
                    'notification_id' => $notification->getId(),
                ]);

                return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
            }

            $users = [];
            foreach ($companies as $company) {
                foreach ($company->getUsers() as $user) {
                    $users[] = $user;
                }
            }

            if (empty($users)) {
                $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NO_USERS['message']);
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_USERS, [
                    'notification_id' => $notification->getId(),
                ]);

                return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
            }

            $deviceTokens = [];
            foreach ($users as $user) {
                $tokens = $user->getDeviceTokens()->toArray();
                $deviceTokens = array_merge($deviceTokens, $tokens);
            }
            if (empty($deviceTokens)) {
                $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_NO_USERS_TOKENS['message']);
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_NO_USERS_TOKENS, [
                    'notification_id' => $notification->getId(),
                ]);

                return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
            }

            $title = $notification->getTitle();
            $message = $notification->getMessage();

            $maxRetries = 3;
            $failedTokens = $deviceTokens;

            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                if (empty($batchResult['failed'])) {
                    break;
                }

                $batchResult = $pushNotificationService->sendBatchNotification($failedTokens, $title, $message);
                $failedTokens = $batchResult['failed'];

                if (!empty($failedTokens)) {
                    $this->addFlash('warning', 'Intento ' . $attempt . ': No se pudo enviar la notificación a algunos dispositivos.');
                }
            }

            if (empty($failedTokens)) {
                $this->addFlash('success', 'Notificaciónes enviada correctamente');
            } else {
                $this->addFlash('error', NotificationErrorCodes::NOTIFICATION_SEND_FAILED['message'] . implode(', ', $failedTokens));
                $applicationErrorService->createError(NotificationErrorCodes::NOTIFICATION_SEND_FAILED, [
                    'notification_id' => $notification->getId(),
                    'failed_tokens' => $failedTokens,
                ]);
            }

            return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
        }
    */

    private function getUserUnreadCount(EntityManagerInterface $em, int $userId): int
    {
        try {
            $connection = $em->getConnection();
            
            // Obtener todas las notificaciones activas
            $qb = $em->createQueryBuilder()
                ->select('COUNT(DISTINCT n.id)')
                ->from(Notification::class, 'n')
                ->leftJoin('n.regions', 'r')
                ->leftJoin('n.companies', 'c')
                ->leftJoin('c.users', 'u')
                ->leftJoin('r.users', 'ru')
                ->where('n.status = :status')
                ->andWhere('n.sent_date IS NOT NULL')
                ->setParameter('status', Status::ACTIVE);

            $qb->andWhere(
                $qb->expr()->orX(
                    'u.id = :userId',
                    'ru.id = :userId',
                    $qb->expr()->andX(
                        $qb->expr()->isNull('r.id'),
                        $qb->expr()->isNull('c.id')
                    )
                )
            )->setParameter('userId', $userId);

            $sqlRead = "SELECT notification_id FROM user_notification_read WHERE user_id = :userId";
            
            // Safe fetch column
            try {
                $readNotificationIds = $connection->fetchFirstColumn($sqlRead, ['userId' => $userId]);
            } catch (\Exception $e) {
                // Table might not exist yet in some environments
                $readNotificationIds = [];
            }

            if (!empty($readNotificationIds)) {
                $qb->andWhere('n.id NOT IN (:readIds)')
                   ->setParameter('readIds', $readNotificationIds);
            }

            return (int) $qb->getQuery()->getSingleScalarResult();
        } catch (\Exception $e) {
            error_log("Error in getUserUnreadCount: " . $e->getMessage());
            return 0;
        }
    }
}