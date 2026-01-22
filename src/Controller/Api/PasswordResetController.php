<?php

namespace App\Controller\Api;

use App\DTO\Auth\ForgotPasswordResetRequest;
use App\DTO\Auth\PasswordResetEmailRequest;
use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\PasswordResetErrorCodes;
use App\Enum\Status;
use App\Service\EmailVerificationService;
use App\Service\EntityProxyCleanerService;
use App\Service\ErrorResponseService;
use App\Service\PhoneVerificationService;
use App\Service\RequestValidatorService;
use App\Service\TenantManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class PasswordResetController extends AbstractController
{
    private TenantManager $tenantManager;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;
    private EntityProxyCleanerService $proxyCleanerService;
    private EmailVerificationService $emailVerificationService;
    private PhoneVerificationService $phoneVerificationService;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        TenantManager $tenantManager,
        ErrorResponseService $errorResponseService,
        RequestValidatorService $requestValidatorService,
        EntityProxyCleanerService $proxyCleanerService,
        EmailVerificationService $emailVerificationService,
        PhoneVerificationService $phoneVerificationService,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->tenantManager = $tenantManager;
        $this->errorResponseService = $errorResponseService;
        $this->requestValidatorService = $requestValidatorService;
        $this->proxyCleanerService = $proxyCleanerService;
        $this->emailVerificationService = $emailVerificationService;
        $this->phoneVerificationService = $phoneVerificationService;
        $this->passwordHasher = $passwordHasher;
    }

    /**
     * Iniciar recuperación de contraseña por email
     * POST /users/password-reset/email
     * Body: {"email": "user@example.com"}
     */
    #[Route('/users/password-reset/email', name: 'api_password_reset_email', methods: ['POST'])]
    public function initiatePasswordResetByEmail(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $dto = $this->requestValidatorService->validateAndMap($request, PasswordResetEmailRequest::class);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        // Buscar usuario activo por email usando consulta directa
        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.email = :email')
            ->andWhere('u.status = :status')
            ->setParameter('email', $dto->email)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::USER_NOT_FOUND_FOR_EMAIL);
        }

        // Limpiar problemas de proxy antes de usar el usuario
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        // Generar código de verificación
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode((string)$verificationCode);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        // Enviar email
        $result = $this->emailVerificationService->sendVerificationCode($dto->email, (string)$verificationCode, $dominio);
        
        if (!$result['success']) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::EMAIL_SENDING_FAILED);
        }

        return new JsonResponse([
            'message' => 'Código de verificación enviado exitosamente.',
            'code' => 200,
            'user_id' => $user->getId(),
        ], 200);
    }

    /**
     * Iniciar recuperación de contraseña por teléfono
     * POST /users/password-reset/phone
     * Body: {"phone_number": "1234567890"}
     */
    #[Route('/users/password-reset/phone', name: 'api_password_reset_phone', methods: ['POST'])]
    public function passwordResetPhone(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $phoneNumber = $data['phone_number'] ?? null;

        if (!$phoneNumber) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::PHONE_NUMBER_NOT_PROVIDED);
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.phone_number = :phone_number')
            ->andWhere('u.status = :status')
            ->setParameter('phone_number', $phoneNumber)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::USER_NOT_FOUND_FOR_EMAIL);
        }

        // Limpiar y recargar la entidad User para evitar problemas de proxy
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        // Generate and send verification code
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode((string)$verificationCode);
        $user->setUpdatedAt(new \DateTimeImmutable());

        try {
            $em->persist($user);
            $em->flush();

            // Send verification code via SMS (using password reset specific method)
            $sent = $this->phoneVerificationService->sendPasswordResetCode($phoneNumber, $verificationCode);

            if (!$sent) {
                return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::SMS_SENDING_FAILED);
            }

            return new JsonResponse([
                'message' => 'Código de verificación enviado al teléfono.',
                'code' => 200,
                'user_id' => $user->getId()
            ], 200);

        } catch (\Exception $e) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::INTERNAL_ERROR);
        }
    }

    /**
     * 1. Iniciar recuperación por teléfono
     * POST /password-reset/phone
     * Body: {"phone_number": "5512345678"}
     */
    #[Route('/password-reset/phone', name: 'api_password_reset_phone_new', methods: ['POST'])]
    public function initiatePasswordResetByPhone(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $phoneNumber = $data['phone_number'] ?? null;

        if (!$phoneNumber) {
            return new JsonResponse(['success' => false, 'error' => 'Phone number is required'], 400);
        }

        // Buscar usuario activo por teléfono usando consulta directa
        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.phone_number = :phone_number')
            ->andWhere('u.status = :status')
            ->setParameter('phone_number', $phoneNumber)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return new JsonResponse(['success' => false, 'error' => 'User not found'], 404);
        }

        // Limpiar problemas de proxy
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        // Generar código de verificación
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode((string)$verificationCode);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        // Enviar SMS usando el método específico para recuperación de contraseña
        $sent = $this->phoneVerificationService->sendPasswordResetCode($phoneNumber, $verificationCode);

        if (!$sent) {
            return new JsonResponse(['success' => false, 'error' => 'Failed to send SMS'], 500);
        }

        return new JsonResponse([
            'success' => true,
            'user_id' => $user->getId(),
            'message' => 'Verification code sent via SMS'
        ]);
    }

    /**
     * 2. Verificar código de recuperación
     * PATCH /password-reset/verify
     * Body: {"user_id": 123, "verification_code": "123456"}
     */
    #[Route('/password-reset/verify', name: 'api_password_reset_verify', methods: ['PATCH'])]
    public function verifyPasswordResetCode(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;
        $verificationCode = $data['verification_code'] ?? null;

        if (!$userId || !$verificationCode) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::INTERNAL_ERROR, ['fields' => ['user_id', 'verification_code']]);
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::USER_NOT_FOUND);
        }

        // Limpiar problemas de proxy
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        if ($user->getVerificationCode() !== $verificationCode) {
            return $this->errorResponseService->createErrorResponse([
                'code' => 'PR-008',
                'message' => 'El código ingresado es inválido.',
                'http_code' => 400,
            ]);
        }

        // Marcar como verificado (opcional: puedes agregar un campo específico para esto)
        $user->setUpdatedAt(new \DateTimeImmutable());
        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Verification code confirmed'
        ]);
    }

    /**
     * 3. Reenviar código de recuperación
     * POST /password-reset/resend
     * Body: {"user_id": 123}
     */
    #[Route('/password-reset/resend', name: 'api_password_reset_resend', methods: ['POST'])]
    public function resendPasswordResetCode(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::INTERNAL_ERROR, ['fields' => ['user_id']]);
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::USER_NOT_FOUND);
        }

        // Limpiar problemas de proxy
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        // Generar nuevo código
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode((string)$verificationCode);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        // Reenviar SMS
        $sent = $this->phoneVerificationService->sendPasswordResetCode($user->getPhoneNumber(), $verificationCode);

        if (!$sent) {
            return new JsonResponse(['success' => false, 'error' => 'Failed to resend SMS'], 500);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Verification code resent via SMS'
        ]);
    }

    /**
     * 4. Iniciar recuperación por email
     * POST /password-reset/email
     * Body: {"email": "user@example.com"}
     */
    #[Route('/password-reset/email', name: 'api_password_reset_email_new', methods: ['POST'])]
    public function initiatePasswordResetByEmailNew(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? null;

        if (!$email) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::EMAIL_NOT_PROVIDED);
        }

        // Buscar usuario activo por email usando consulta directa
        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.email = :email')
            ->andWhere('u.status = :status')
            ->setParameter('email', $email)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::USER_NOT_FOUND);
        }

        // Limpiar problemas de proxy
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        // Generar código de verificación
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode((string)$verificationCode);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        // Enviar email
        $result = $this->emailVerificationService->sendVerificationCode($email, (string)$verificationCode, $dominio);

        if (!$result['success']) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::EMAIL_SENDING_FAILED);
        }

        return new JsonResponse([
            'success' => true,
            'user_id' => $user->getId(),
            'message' => 'Verification code sent via email'
        ]);
    }

    /**
     * 5. Verificar código de recuperación por email
     * PATCH /password-reset/email/verify
     * Body: {"user_id": 123, "verification_code": "123456"}
     */
    #[Route('/password-reset/email/verify', name: 'api_password_reset_email_verify', methods: ['PATCH'])]
    public function verifyPasswordResetEmailCode(string $dominio, Request $request): JsonResponse
    {
        // Reutilizar la misma lógica que el verificador general
        return $this->verifyPasswordResetCode($dominio, $request);
    }

    /**
     * 6. Reenviar código de recuperación por email
     * POST /password-reset/email/resend
     * Body: {"user_id": 123}
     */
    #[Route('/password-reset/email/resend', name: 'api_password_reset_email_resend', methods: ['POST'])]
    public function resendPasswordResetEmailCode(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::INTERNAL_ERROR, ['fields' => ['user_id']]);
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::USER_NOT_FOUND);
        }

        // Limpiar problemas de proxy
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        if (!$user->getEmail()) {
            return $this->errorResponseService->createErrorResponse([
                'code' => 'PR-009',
                'message' => 'El usuario no tiene email registrado.',
                'http_code' => 400,
            ]);
        }

        // Generar nuevo código
        $verificationCode = random_int(100000, 999999);
        $user->setVerificationCode((string)$verificationCode);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        // Reenviar email
        $result = $this->emailVerificationService->sendVerificationCode($user->getEmail(), (string)$verificationCode, $dominio);

        if (!$result['success']) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::EMAIL_SENDING_FAILED);
        }

        return new JsonResponse([
            'success' => true,
            'message' => 'Verification code resent via email'
        ]);
    }

    /**
     * Restablecer contraseña después de verificación
     * PATCH /users/{userId}/reset-password
     * Body: {"new_password": "newpassword123"}
     */
    #[Route('/users/{userId}/reset-password', name: 'api_reset_password', methods: ['PATCH'])]
    public function resetPassword(string $dominio, int $userId, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $dto = $this->requestValidatorService->validateAndMap($request, ForgotPasswordResetRequest::class);

        if ($dto instanceof JsonResponse) {
            return $dto;
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->setParameter('id', $userId)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PasswordResetErrorCodes::USER_NOT_FOUND);
        }

        // Limpiar y recargar la entidad User para evitar problemas de proxy
        $user = $this->proxyCleanerService->cleanAndReloadUser($user, $em);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->new_password);
        $user->setPassword($hashedPassword);
        $user->setUpdatedAt(new \DateTimeImmutable());

        $em->persist($user);
        $em->flush();

        return new JsonResponse([
            'message' => 'Contraseña restablecida correctamente.',
            'code' => 200,
        ], 200);
    }
}
