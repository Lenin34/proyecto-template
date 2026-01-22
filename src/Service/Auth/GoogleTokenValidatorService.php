<?php

namespace App\Service\Auth;

use App\Entity\App\User;
use App\Enum\Status;
use App\Security\JwtTokenGenerator;
use App\Service\ErrorResponseService;
use Google\Client as GoogleClient;
use Symfony\Component\HttpFoundation\JsonResponse;

class GoogleTokenValidatorService
{
    public function __construct(
        private GoogleClient $googleClient,
        private JwtTokenGenerator $jwtTokenGenerator,
        private ErrorResponseService $errorResponseService,
        private \Doctrine\ORM\EntityManagerInterface $em
    ) {}

    public function validateAndGenerateToken(string $idToken): JsonResponse
    {
        $payload = $this->googleClient->verifyIdToken($idToken);

        if (!$payload || !isset($payload['email'])) {
            return $this->errorResponseService->createErrorResponse('GOOGLE_INVALID_TOKEN');
        }

        $user = $this->em->getRepository(User::class)->findOneBy([
            'email' => $payload['email'],
            'status' => Status::ACTIVE,
        ]);

        if (!$user) {
            return $this->errorResponseService->createErrorResponse('GOOGLE_USER_NOT_REGISTERED', [
                'email' => $payload['email'],
            ]);
        }

        $jwt = $this->jwtTokenGenerator->generateToken($user);

        return new JsonResponse([
            'token' => $jwt,
            'message' => 'Inicio de sesión exitoso.',
        ], 200);
    }
}
