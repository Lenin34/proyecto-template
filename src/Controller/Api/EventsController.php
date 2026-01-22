<?php
namespace App\Controller\Api;

use App\DTO\EventsGetRequest;
use App\Entity\App\Company;
use App\Entity\App\Event;
use App\Enum\ErrorCodes\Api\EventsErrorCodes;
use App\Enum\Status;
use App\Service\ErrorResponseService;
use App\Service\ImagePathService;
use App\Service\RequestValidatorService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class EventsController extends AbstractController
{
    private TenantManager $tenantManager;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;
    private ImagePathService $imagePathService;

    public function __construct(
        TenantManager $tenantManager,
        ErrorResponseService $errorResponseService,
        RequestValidatorService $requestValidatorService,
        ImagePathService $imagePathService
    ) {
        $this->tenantManager = $tenantManager;
        $this->errorResponseService = $errorResponseService;
        $this->requestValidatorService = $requestValidatorService;
        $this->imagePathService = $imagePathService;
    }


    public function list(string $dominio): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $events = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Event', 'e')
            ->where('e.status = :status')
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($events as $event) {
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'start_date' => $event->getStartDate()->format('Y-m-d H:i:s'),
                'end_date' => $event->getEndDate()->format('Y-m-d H:i:s'),
                /*'location' => $event->getLocation(),*/
                'image' => $event->getImage(),
                'status' => $event->getStatus()
            ];
        }

        return $this->json($data);
    }

/*    #[Route('/events/{id}', name: 'api_events_show', methods: ['GET'])]
    public function show(string $dominio, int $id): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $event = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Event', 'e')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$event) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'date' => $event->getDate()->format('Y-m-d H:i:s'),
            'location' => $event->getLocation(),
            'image' => $event->getImage(),
            'active' => $event->isActive()
        ]);
    }

    #[Route('/events', name: 'api_events_create', methods: ['POST'])]
    public function create(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);

        $event = new Event();
        $event->setTitle($data['title']);
        $event->setDescription($data['description']);
        $event->setDate(new \DateTime($data['date']));
        $event->setLocation($data['location']);
        $event->setImage($data['image'] ?? null);
        $event->setActive($data['active'] ?? true);

        $em->persist($event);
        $em->flush();

        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'date' => $event->getDate()->format('Y-m-d H:i:s'),
            'location' => $event->getLocation(),
            'image' => $event->getImage(),
            'active' => $event->isActive()
        ], 201);
    }

    #[Route('/events/{id}', name: 'api_events_update', methods: ['PUT'])]
    public function update(string $dominio, int $id, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $event = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Event', 'e')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$event) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['title'])) {
            $event->setTitle($data['title']);
        }
        if (isset($data['description'])) {
            $event->setDescription($data['description']);
        }
        if (isset($data['date'])) {
            $event->setDate(new \DateTime($data['date']));
        }
        if (isset($data['location'])) {
            $event->setLocation($data['location']);
        }
        if (isset($data['image'])) {
            $event->setImage($data['image']);
        }
        if (isset($data['active'])) {
            $event->setActive($data['active']);
        }

        $em->flush();

        return $this->json([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'date' => $event->getDate()->format('Y-m-d H:i:s'),
            'location' => $event->getLocation(),
            'image' => $event->getImage(),
            'active' => $event->isActive()
        ]);
    }

    #[Route('/events/{id}', name: 'api_events_delete', methods: ['DELETE'])]
    public function delete(string $dominio, int $id): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $event = $em->createQueryBuilder()
            ->select('e')
            ->from('App\Entity\App\Event', 'e')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$event) {
            return $this->json(['error' => 'Evento no encontrado'], 404);
        }

        $em->remove($event);
        $em->flush();

        return $this->json(null, 204);
    }*/
    #[Route('/events', name: 'api_events_list', methods: ['GET'])]
    public function getEvents(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $eventsGetRequest = $this->requestValidatorService->validateAndMap($request, EventsGetRequest::class, true);
        if ($eventsGetRequest instanceof JsonResponse) {
            return $eventsGetRequest;
        }
        
        if (($eventsGetRequest->start_date !== null && $eventsGetRequest->end_date === null) ||
            ($eventsGetRequest->start_date === null && $eventsGetRequest->end_date !== null)) {
            return $this->errorResponseService->createErrorResponse(EventsErrorCodes::EVENTS_INVALID_DATE_RANGE,
                [
                    'start_date' => $eventsGetRequest->start_date,
                    'end_date' => $eventsGetRequest->end_date,
                ]
            );
        }

        // Si se proporciona company_id, validar que existe y está activa
        if ($eventsGetRequest->company_id !== null) {
            $company = $em->createQueryBuilder()
                ->select('c')
                ->from('App\Entity\App\Company', 'c')
                ->where('c.id = :id')
                ->andWhere('c.status = :status')
                ->setParameter('id', $eventsGetRequest->company_id)
                ->setParameter('status', Status::ACTIVE)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$company) {
                return $this->errorResponseService->createErrorResponse(EventsErrorCodes::EVENTS_COMPANY_NOT_FOUND_OR_INACTIVE,
                    [
                        'company_id' => $eventsGetRequest->company_id,
                    ]
                );
            }

            // ✅ CORREGIDO: Usar subquery para incluir eventos globales (sin empresas) Y eventos de la empresa
            // Lógica: Incluir evento SI:
            //   1. El evento tiene la empresa solicitada asociada, O
            //   2. El evento NO tiene ninguna empresa asociada (evento global)
            $qb = $em->createQueryBuilder();
            
            $activeEvents = $qb
                ->select('e')
                ->from('App\\Entity\\App\\Event', 'e')
                ->where('e.status = :status')
                ->andWhere(
                    $qb->expr()->orX(
                        // Condición 1: El evento está asociado a la empresa solicitada
                        $qb->expr()->exists(
                            $em->createQueryBuilder()
                                ->select('1')
                                ->from('App\\Entity\\App\\Event', 'e2')
                                ->join('e2.companies', 'c')
                                ->where('e2.id = e.id')
                                ->andWhere('c.id = :companyId')
                                ->getDQL()
                        ),
                        // Condición 2: El evento NO tiene empresas asociadas (global)
                        $qb->expr()->not(
                            $qb->expr()->exists(
                                $em->createQueryBuilder()
                                    ->select('1')
                                    ->from('App\\Entity\\App\\Event', 'e3')
                                    ->join('e3.companies', 'c2')
                                    ->where('e3.id = e.id')
                                    ->getDQL()
                            )
                        )
                    )
                )
                ->setParameter('status', Status::ACTIVE)
                ->setParameter('companyId', $company->getId())
                ->orderBy('e.created_at', 'DESC')
                ->getQuery()
                ->getResult();
        } else {
            // Si no se proporciona company_id, devolver todos los eventos activos
            $activeEvents = $em->createQueryBuilder()
                ->select('e')
                ->from('App\Entity\App\Event', 'e')
                ->where('e.status = :status')
                ->setParameter('status', Status::ACTIVE)
                ->orderBy('e.created_at', 'DESC')
                ->getQuery()
                ->getResult();
        }

        $filteredEvents = $this->filterEvents($activeEvents, $eventsGetRequest);
        if (empty($filteredEvents)) {
            return $this->errorResponseService->createErrorResponse(EventsErrorCodes::EVENTS_NO_EVENTS_FOUND,
                [
                    'company_id' => $eventsGetRequest->company_id,
                    'start_date' => $eventsGetRequest->start_date,
                    'end_date' => $eventsGetRequest->end_date,
                ]
            );
        }

        if ($eventsGetRequest->amount !== null) {
            $filteredEvents = array_slice($filteredEvents, 0, $eventsGetRequest->amount);
        }

        $response = array_map(fn($event) => $this->mapEventToArray($event), $filteredEvents);

        return new JsonResponse([
            'events' => $response,
            'code' => 200,
        ], 200);
    }

    private function filterEvents(Array $events, EventsGetRequest $eventsGetRequest): array
    {
        $filteredEvents = [];

        foreach ($events as $event) {
            if ($event->getStatus() === Status::ACTIVE) {
                $includeEvent = true;

                if ($eventsGetRequest->start_date !== null) {
                    $eventStartDate = $event->getStartDate();
                    $eventEndDate = $event->getEndDate();
                    $startDate = new \DateTime($eventsGetRequest->start_date . ' 00:00:00');
                    $endDate = new \DateTime($eventsGetRequest->end_date . ' 23:59:59');

                    if ($eventStartDate > $endDate || $eventEndDate < $startDate) {
                        $includeEvent = false;
                    }
                }

                if ($includeEvent) {
                    $filteredEvents[] = $event;
                }
            }
        }

        usort($filteredEvents, function ($a, $b) {
            return $a->getStartDate() <=> $b->getStartDate();
        });

        return $filteredEvents;
    }

    private function mapEventToArray($event): array
    {
        return [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'start_date' => $event->getStartDate()->format('Y-m-d H:i:s'),
            'end_date' => $event->getEndDate()->format('Y-m-d H:i:s'),
            'image' => $this->imagePathService->generateFullPath($event->getImage()),
        ];
    }
}