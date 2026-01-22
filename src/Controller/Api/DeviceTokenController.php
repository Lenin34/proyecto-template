<?php
namespace App\Controller\Api;

use App\DTO\DeviceTokenRequest;
use App\Entity\App\DeviceToken;
use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\DeviceTokenErrorCodes;
use App\Enum\Status;
use App\Service\ErrorResponseService;
use App\Service\RequestValidatorService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class DeviceTokenController extends AbstractController
{
    private TenantManager $tenantManager;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;

    public function __construct(
        TenantManager $tenantManager,
        ErrorResponseService $errorResponseService,
        RequestValidatorService $requestValidatorService
    ) {
        $this->tenantManager = $tenantManager;
        $this->errorResponseService = $errorResponseService;
        $this->requestValidatorService = $requestValidatorService;
    }

    #[Route('/users/{userId}/device-token', name: 'api_device_token', methods: ['POST'])]
    public function register(string $dominio, $userId, Request $request): JsonResponse
    {
        try {
            $em = $this->tenantManager->getEntityManager();

            $deviceTokenRequest = $this->requestValidatorService->validateAndMap($request, DeviceTokenRequest::class);
            if ($deviceTokenRequest instanceof JsonResponse) {
                return $deviceTokenRequest;
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
                return $this->errorResponseService->createErrorResponse(DeviceTokenErrorCodes::DEVICE_TOKEN_USER_NOT_FOUND_OR_INACTIVE,
                    [
                        'user_id' => $userId,
                    ]
                );
            }


/*        // Verificar si el token ya existe para este usuario usando consulta directa
        $existingToken = $em->createQueryBuilder()
            ->select('dt')
            ->from('App\Entity\App\DeviceToken', 'dt')
            ->where('dt.user = :user')
            ->andWhere('dt.token = :token')
            ->setParameter('user', $user)
            ->setParameter('token', $deviceTokenRequest->device_token)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingToken) {
            return $this->errorResponseService->createErrorResponse(DeviceTokenErrorCodes::DEVICE_TOKEN_ALREADY_EXISTS,
                [
                    'device_token' => $deviceTokenRequest->device_token,
                    'user_id' => $userId,
                ]
            );
        }

        // Verificar si el token existe*/

            //Verificar si el token existe usando consulta directa
            $existingToken = $em->createQueryBuilder()
                ->select('dt')
                ->from('App\Entity\App\DeviceToken', 'dt')
                ->where('dt.token = :token')
                ->setParameter('token', $deviceTokenRequest->device_token)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingToken) {
                if ($existingToken->getUser()->getId() !== $userId) {
                    $existingToken->setUser($user);
                    $em->flush(); // No necesitamos persist para entidades existentes
                } else {
                    return $this->errorResponseService->createErrorResponse(DeviceTokenErrorCodes::DEVICE_TOKEN_ALREADY_EXISTS,
                        [
                            'device_token' => $deviceTokenRequest->device_token,
                            'user_id' => $userId,
                        ]
                    );
                }

                return $this->json(['message' => 'Token actualizado correctamente']);
            }

            $deviceToken = new DeviceToken();
            $deviceToken->setToken($deviceTokenRequest->device_token);
            $deviceToken->setCreatedAt(new \DateTimeImmutable());
            $deviceToken->setUpdatedAt(new \DateTimeImmutable());

            // Obtener una referencia fresca del usuario para evitar problemas de cascade
            $userReference = $em->getReference(User::class, $user->getId());
            $deviceToken->setUser($userReference);

            $em->persist($deviceToken);
            $em->flush();

            return $this->json(['message' => 'Token registrado correctamente']);
        } catch (\Exception $e) {
            // Log the error for debugging
            error_log('Device token error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

            return new JsonResponse([
                'code' => 500,
                'error' => 'Error interno del sistema',
                'message' => 'Se ha producido un error temporal. Por favor intente nuevamente.',
                'debug' => $e->getMessage() // Temporal para debugging
            ], 500);
        }
    }

    #[Route('/device-token/{token}', name: 'api_device_token_delete', methods: ['DELETE'])]
    public function delete(string $dominio, string $token): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $deviceToken = $em->createQueryBuilder()
            ->select('dt')
            ->from('App\Entity\App\DeviceToken', 'dt')
            ->where('dt.user = :user')
            ->andWhere('dt.token = :token')
            ->setParameter('user', $this->getUser())
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();

        if ($deviceToken) {
            $em->remove($deviceToken);
            $em->flush();
        }

        return $this->json(null, 204);
    }
}