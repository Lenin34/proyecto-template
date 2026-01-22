<?php

namespace App\Controller\Api;

use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\EmailVerificationErrorCodes;
use App\Enum\Status;
use App\Service\AppUrlService;
use App\Service\Auth\CredentialJwtService;
use App\Service\ErrorResponseService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{dominio}/api')]
final class CredentialToken extends AbstractController
{
    private TenantManager $tenantManager;
    private ErrorResponseService $errorResponseService;
    private CredentialJwtService $credentialJwtService;
    private AppUrlService $appUrlService;

    public function __construct(
        TenantManager $tenantManager,
        ErrorResponseService $errorResponseService,
        CredentialJwtService $credentialJwtService,
        AppUrlService $appUrlService
    ) {
        $this->tenantManager = $tenantManager;
        $this->errorResponseService = $errorResponseService;
        $this->credentialJwtService = $credentialJwtService;
        $this->appUrlService = $appUrlService;
    }

    #[Route('/credential/token', name: 'app_credential_token', methods: ['GET'])]
    public function generateTokenUrl(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();
        
        $email = $request->query->get('email');

        if (!$email) {
            return $this->errorResponseService->createErrorResponse(
                EmailVerificationErrorCodes::USER_EMAIL_NOT_FOUND
            );
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.email = :email')
            ->andWhere('u.status = :status')
            ->andWhere('u.verified = :verified')
            ->setParameter('email', $email)
            ->setParameter('status', Status::ACTIVE)
            ->setParameter('verified', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();


        if (!$user) {
            return $this->errorResponseService->createErrorResponse(
                EmailVerificationErrorCodes::USER_NOT_FOUND
            );
        }

        try {
            $jwt = $this->credentialJwtService->generateCredentialToken($user);
        } catch (\Throwable $e) {
            return $this->errorResponseService->createErrorResponse([
                'code' => 'JWT_ERROR',
                'message' => 'Error al generar token: ' . $e->getMessage(),
                'http_code' => 500
            ]);
        }

        if (!$jwt) {
            return new JsonResponse([
                'message' => 'Unable to generate credential token',
                'code' => 200
            ]);
        }

        // Obtener el tenant actual de forma dinámica
        $currentTenant = $this->tenantManager->getCurrentTenant() ?? $dominio;
        $link = $this->appUrlService->getBaseUrl(). '/' . $currentTenant . '/credential/check?token=' . $jwt;


        return new JsonResponse([
            'link' => $link,
            'code' => 200
        ]);
    }
}