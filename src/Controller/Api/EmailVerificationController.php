<?php

namespace App\Controller\Api;

use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\EmailVerificationErrorCodes;
use App\Service\EmailVerificationService;
use App\Service\EntityProxyCleanerService;
use App\Service\ErrorResponseService;
use App\Service\RequestValidatorService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class EmailVerificationController extends AbstractController
{
    private TenantManager $tenantManager;
    private EmailVerificationService $emailVerificationService;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;
    private EntityProxyCleanerService $proxyCleanerService;

    public function __construct(
        TenantManager $tenantManager,
        EmailVerificationService $emailVerificationService,
        ErrorResponseService $errorResponseService,
        RequestValidatorService $requestValidatorService,
        EntityProxyCleanerService $proxyCleanerService
    ) {
        $this->tenantManager = $tenantManager;
        $this->emailVerificationService = $emailVerificationService;
        $this->errorResponseService = $errorResponseService;
        $this->requestValidatorService = $requestValidatorService;
        $this->proxyCleanerService = $proxyCleanerService;
    }

    #[Route('/users/{id}/email-verification/resend', name: 'api_email_verification_resend', methods: ['POST'])]
    public function resendEmailVerification(string $dominio, int $id): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(EmailVerificationErrorCodes::USER_NOT_FOUND);
        }

        // Limpiar problemas de proxy antes de usar el usuario
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        if (!$user->getEmail()) {
            return $this->errorResponseService->createErrorResponse(EmailVerificationErrorCodes::USER_EMAIL_NOT_FOUND);
        }

        $code = random_int(100000, 999999);
        $user->setVerificationCode((string) $code);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        $result = $this->emailVerificationService->sendVerificationCode($user->getEmail(), (string)$code, $dominio);

        if (!$result['success']) {
            return new JsonResponse([
                'error' => 'Error enviando email',
                'details' => $result['error'] ?? 'Error desconocido',
                'trace' => $result['trace'] ?? null,
                'code' => 500,
            ], 500);
        }

        return new JsonResponse([
            'message' => 'Código de verificación enviado por correo electrónico.',
            'code' => 200,
            'user_id' => $user->getId(),
        ], 200);
    }

    #[Route('/users/{id}/email-verification', name: 'api_email_verification_confirm', methods: ['PATCH'])]
    public function verifyEmailCode(string $dominio, int $id, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();
        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(EmailVerificationErrorCodes::USER_NOT_FOUND);
        }

        // Limpiar problemas de proxy antes de usar el usuario
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        $data = json_decode($request->getContent(), true);
        $providedCode = $data['verification_code'] ?? null;

        if (!$providedCode) {
            return $this->errorResponseService->createErrorResponse(EmailVerificationErrorCodes::MISSING_VERIFICATION_CODE);
        }

        if ($user->getVerificationCode() !== $providedCode) {
            return $this->errorResponseService->createErrorResponse(EmailVerificationErrorCodes::INVALID_VERIFICATION_CODE);
        }

        $user->setVerificationCode(null);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Correo electrónico verificado correctamente.',
            'code' => 200,
            'user_id' => $user->getId(),
        ], 200);
    }
}
