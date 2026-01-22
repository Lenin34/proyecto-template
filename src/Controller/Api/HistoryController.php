<?php
namespace App\Controller\Api;

use App\DTO\HistoryRequest;
use App\Entity\App\History;
use App\Entity\App\HistoryEvents;
use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\HistoryErrorCodes;
use App\Enum\Status;
use App\Service\ErrorResponseService;
use App\Service\RequestValidatorService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class HistoryController extends AbstractController
{
    private TenantManager $tenantManager;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;

    public function __construct(
        TenantManager $tenantManager,
        ErrorResponseService $errorResponseService,
        RequestValidatorService $requestValidatorService,
    ) {
        $this->tenantManager = $tenantManager;
        $this->errorResponseService = $errorResponseService;
        $this->requestValidatorService = $requestValidatorService;
    }

    #[Route('/history', name: 'api_history_list', methods: ['GET'])]
    public function list(string $dominio): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $histories = $em->createQueryBuilder()
            ->select('h')
            ->from('App\Entity\App\History', 'h')
            ->getQuery()
            ->getResult();

        $data = [];
        foreach ($histories as $history) {
            $data[] = [
                'id' => $history->getId(),
                'action' => $history->getAction(),
                'description' => $history->getDescription(),
                'createdAt' => $history->getCreatedAt()->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $history->getUser()->getId(),
                    'email' => $history->getUser()->getEmail()
                ]
            ];
        }

        return $this->json($data);
    }

    #[Route('/history', name: 'api_history_create', methods: ['POST'])]
    public function create(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);

        $history = new History();
        $history->setAction($data['action']);
        $history->setDescription($data['description']);
        $history->setUser($this->getUser());
        $history->setCreatedAt(new \DateTime());

        $em->persist($history);
        $em->flush();

        return $this->json([
            'id' => $history->getId(),
            'action' => $history->getAction(),
            'description' => $history->getDescription(),
            'createdAt' => $history->getCreatedAt()->format('Y-m-d H:i:s'),
            'user' => [
                'id' => $history->getUser()->getId(),
                'email' => $history->getUser()->getEmail()
            ]
        ], 201);
    }

    #[Route('/users/{userId}/history', name: 'api_history', methods: ['POST'])]
    public function createHistory(string $dominio, int $userId, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();
        
        $historyRequest = $this->requestValidatorService->validateAndMap($request, HistoryRequest::class);
        if ($historyRequest instanceof JsonResponse) {
            return $historyRequest;
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->andWhere('u.status = :status')
            ->setParameter('id', $userId)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$user) {
            return $this->errorResponseService->createErrorResponse(HistoryErrorCodes::HISTORY_USER_NOT_FOUND_OR_INACTIVE,
                [
                    'userId' => $userId,
                ]
            );
        }

        $historyEvent = $em->createQueryBuilder()
            ->select('he')
            ->from('App\Entity\App\HistoryEvents', 'he')
            ->where('he.name = :name')
            ->setParameter('name', $historyRequest->event_name)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$historyEvent) {
            return $this->errorResponseService->createErrorResponse(HistoryErrorCodes::HISTORY_EVENT_NOT_FOUND,
                [
                    'event_name' => $historyRequest->event_name,
                ]
            );
        }

        $history = new History();
        $history->setUser($user);
        $history->setEventType($historyEvent);
        $history->setCreatedAt(new \DateTimeImmutable());

        $user->setLastSeen(new \DateTimeImmutable());

        $em->persist($history);
        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'El evento fue guardado correctamente.',
            'code' => 200,
        ], 200);
    }
}