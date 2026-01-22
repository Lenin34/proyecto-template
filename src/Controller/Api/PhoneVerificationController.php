<?php
namespace App\Controller\Api;

use App\DTO\PhoneVerification\PhoneNumberVerificationRequest;
use App\DTO\PhoneVerification\PhoneVerificationRequest;
use App\Entity\App\PhoneVerification;
use App\Entity\App\User;
use App\Enum\ErrorCodes\Api\PhoneVerificationErrorCodes;
use App\Enum\Status;
use App\Service\ErrorResponseService;
use App\Service\PhoneVerificationService;
use App\Service\RequestValidatorService;
use App\Service\TenantManager;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/{dominio}/api')]
class PhoneVerificationController extends AbstractController
{
    private TenantManager $tenantManager;
    private PhoneVerificationService $phoneVerificationService;
    private ErrorResponseService $errorResponseService;
    private RequestValidatorService $requestValidatorService;
    private LoggerInterface $logger;

    public function __construct(
        TenantManager $tenantManager,
        PhoneVerificationService $phoneVerificationService,
        ErrorResponseService $errorResponseService,
        RequestValidatorService $requestValidatorService,
        LoggerInterface $logger
    ) {
        $this->tenantManager = $tenantManager;
        $this->phoneVerificationService = $phoneVerificationService;
        $this->errorResponseService = $errorResponseService;
        $this->requestValidatorService = $requestValidatorService;
        $this->logger = $logger;
    }

    #[Route('/verify-phone', name: 'api_verify_phone', methods: ['POST'])]
    public function verifyPhone(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $phone = $data['phone'] ?? null;

        if (!$phone) {
            return $this->json(['error' => 'Número de teléfono no proporcionado'], 400);
        }

        // Generar código de verificación
        $code = sprintf('%06d', random_int(0, 999999));

        $verification = new PhoneVerification();
        $verification->setPhone($phone);
        $verification->setCode($code);
        $verification->setExpiresAt(new \DateTime('+15 minutes'));
        $verification->setVerified(false);

        $em->persist($verification);
        $em->flush();

        // Aquí iría la lógica para enviar el SMS con el código
        // Por ahora, solo retornamos el código (en producción no se debería hacer esto)
        return $this->json([
            'message' => 'Código de verificación enviado',
            'code' => $code // Solo para desarrollo
        ]);
    }

    #[Route('/confirm-phone', name: 'api_confirm_phone', methods: ['POST'])]
    public function confirmPhone(string $dominio, Request $request): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $data = json_decode($request->getContent(), true);
        $phone = $data['phone'] ?? null;
        $code = $data['code'] ?? null;

        if (!$phone || !$code) {
            return $this->json(['error' => 'Teléfono o código no proporcionado'], 400);
        }

        $verification = $em->createQueryBuilder()
            ->select('pv')
            ->from('App\Entity\App\PhoneVerification', 'pv')
            ->where('pv.phone = :phone')
            ->andWhere('pv.code = :code')
            ->andWhere('pv.verified = :verified')
            ->setParameter('phone', $phone)
            ->setParameter('code', $code)
            ->setParameter('verified', false)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$verification) {
            return $this->json(['error' => 'Código de verificación inválido'], 400);
        }

        if ($verification->getExpiresAt() < new \DateTime()) {
            return $this->json(['error' => 'Código de verificación expirado'], 400);
        }

        $verification->setVerified(true);
        $em->flush();

        return $this->json(['message' => 'Teléfono verificado correctamente']);
    }

    /**
     * Endpoint para React Native: Verificar número de teléfono y enviar código SMS
     * POST /verify-phone-number
     * Body: {"phone_number": "5610676487"}
     */
    #[Route('/verify-phone-number', name: 'api_verify_phone_number', methods: ['POST'])]
    public function verifyPhoneNumber(string $dominio, Request $request): JsonResponse
    {
        $this->logger->info('Iniciando verificación de número de teléfono', [
            'tenant' => $dominio,
            'request_data' => json_decode($request->getContent(), true)
        ]);

        try {
            $em = $this->tenantManager->getEntityManager();

            // Validar request
            $phoneNumberRequest = $this->requestValidatorService->validateAndMap($request, PhoneNumberVerificationRequest::class);
            if ($phoneNumberRequest instanceof JsonResponse) {
                return $phoneNumberRequest;
            }

            // Normalizar teléfono
            $phoneNumber = $this->normalizePhoneNumber($phoneNumberRequest->phone_number);

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
                $this->logger->warning('Número de teléfono no encontrado', [
                    'tenant' => $dominio,
                    'phone_number' => $phoneNumber
                ]);
                return $this->errorResponseService->createErrorResponse(
                    PhoneVerificationErrorCodes::PHONE_NUMBER_NOT_FOUND,
                    ['phone_number' => $phoneNumber]
                );
            }

            // Si ya está verificado, devolver error específico
            if ($user->isVerified()) {
                $this->logger->info('Usuario ya está verificado', [
                    'tenant' => $dominio,
                    'user_id' => $user->getId(),
                    'phone_number' => $phoneNumber
                ]);
                return $this->errorResponseService->createErrorResponse(
                    PhoneVerificationErrorCodes::PHONE_NUMBER_USER_ALREADY_VERIFIED,
                    [
                        'phone_number' => $phoneNumber,
                        'user_id' => $user->getId()
                    ]
                );
            }

            // Obtener código anterior para logging
            $previousCode = $user->getVerificationCode();

            // Generar nuevo código diferente al anterior
            $verificationCode = random_int(100000, 999999);

            // Asegurar que el código sea diferente al anterior
            $attempts = 0;
            while ($verificationCode == $previousCode && $attempts < 10) {
                $verificationCode = random_int(100000, 999999);
                $attempts++;
            }

            $this->logger->info('Generando código de verificación', [
                'tenant' => $dominio,
                'user_id' => $user->getId(),
                'previous_code' => $previousCode,
                'new_code' => $verificationCode,
                'attempts' => $attempts
            ]);

            // Buscar la entidad nuevamente para asegurar que esté managed
            $userId = $user->getId();
            $freshUser = $em->find(User::class, $userId);

            if (!$freshUser) {
                $this->logger->error('No se pudo encontrar usuario para actualizar código', [
                    'tenant' => $dominio,
                    'user_id' => $userId
                ]);
                return $this->errorResponseService->createErrorResponse(
                    PhoneVerificationErrorCodes::PHONE_VERIFICATION_DATABASE_ERROR,
                    [
                        'user_id' => $userId,
                        'operation' => 'find_user_for_update',
                        'error' => 'Usuario no encontrado'
                    ]
                );
            }

            // Establecer nuevo código en la entidad fresca
            $freshUser->setVerificationCode((string)$verificationCode);
            $freshUser->setUpdatedAt(new \DateTimeImmutable());

            $this->logger->info('Actualizando código con entidad fresca', [
                'tenant' => $dominio,
                'user_id' => $userId,
                'code_to_save' => $verificationCode
            ]);

            // Guardar cambios
            $em->flush();

            // Usar la entidad fresca para el resto del proceso
            $user = $freshUser;

            $this->logger->info('Después de flush - código debería estar guardado', [
                'tenant' => $dominio,
                'user_id' => $user->getId(),
                'expected_code' => $verificationCode
            ]);

            // Enviar SMS
            $smsResult = $this->phoneVerificationService->sendVerificationCode($phoneNumber, $verificationCode);
            if (!$smsResult) {
                $this->logger->error('Error al enviar SMS de verificación', [
                    'tenant' => $dominio,
                    'user_id' => $user->getId(),
                    'phone_number' => $phoneNumber
                ]);
                return $this->errorResponseService->createErrorResponse(
                    PhoneVerificationErrorCodes::PHONE_NUMBER_SMS_SENDING_FAILED,
                    [
                        'user_id' => $user->getId(),
                        'phone_number' => $phoneNumber
                    ]
                );
            }

            $this->logger->info('Verificación de teléfono completada exitosamente', [
                'tenant' => $dominio,
                'user_id' => $user->getId(),
                'phone_number' => $phoneNumber
            ]);

            return new JsonResponse([
                'message' => 'Código de verificación enviado exitosamente.',
                'code' => 200,
                'user_id' => $user->getId(),
                'phone_number' => $phoneNumber
            ], 200);

        } catch (\Exception $e) {
            $this->logger->critical('Excepción no manejada en verificación de teléfono', [
                'tenant' => $dominio,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponseService->createErrorResponse(
                PhoneVerificationErrorCodes::PHONE_VERIFICATION_REQUEST_PROCESSING_ERROR,
                [
                    'error' => 'Error interno del servidor',
                    'details' => $e->getMessage()
                ]
            );
        }
    }

    /**
     * Normaliza el número de teléfono removiendo espacios, guiones y paréntesis
     */
    private function normalizePhoneNumber(string $phoneNumber): string
    {
        // Remover espacios, guiones, paréntesis
        $cleaned = preg_replace('/[\s\-\(\)]/', '', $phoneNumber);

        // Si empieza con +52, removerlo (código de país de México)
        if (str_starts_with($cleaned, '+52')) {
            $cleaned = substr($cleaned, 3);
        }

        // Si empieza con 52 y tiene más de 10 dígitos, remover el 52
        if (str_starts_with($cleaned, '52') && strlen($cleaned) > 10) {
            $cleaned = substr($cleaned, 2);
        }

        return $cleaned;
    }

    #[Route('/users/{id}/phone-verification', name: 'api_phone_verification', methods: ['PATCH'])]
    public function phoneVerification(string $dominio,Request $request, $id): JsonResponse
    {
        $this->logger->info('Iniciando verificación de código telefónico', [
            'tenant' => $dominio,
            'user_id' => $id,
            'request_data' => json_decode($request->getContent(), true)
        ]);

        try {
            $em = $this->tenantManager->getEntityManager();
            $id = (int) $id;

        $phoneVerificationRequest = $this->requestValidatorService->validateAndMap($request, PhoneVerificationRequest::class);
        if ($phoneVerificationRequest instanceof JsonResponse) {
            return $phoneVerificationRequest;
        }

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->andWhere('u.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PhoneVerificationErrorCodes::PHONE_VERIFICATION_USER_NOT_FOUND_OR_INACTIVE,
                [
                    'userId' => $id,
                ]
            );
        }

        if ($user->isVerified()) {
            return $this->errorResponseService->createErrorResponse(PhoneVerificationErrorCodes::PHONE_VERIFICATION_USER_ALREADY_VERIFIED,
                [
                    'userId' => $id,
                ]
            );
        }

        if ($user->getVerificationCode() === null) {
            return $this->errorResponseService->createErrorResponse(PhoneVerificationErrorCodes::PHONE_VERIFICATION_USER_NO_VERIFICATION_CODE,
                [
                    'userId' => $id,
                ]
            );  
        }

        if (!$this->phoneVerificationService->verifyCode($user, $phoneVerificationRequest->verification_code)) {
            return $this->errorResponseService->createErrorResponse(PhoneVerificationErrorCodes::PHONE_VERIFICATION_CODE_INCORRECT,
                [
                    'userId' => $id,
                    'verification_code' => $phoneVerificationRequest->verification_code,
                ]
            );
        }

        // Buscar la entidad nuevamente para asegurar que esté managed
        $userId = $user->getId();
        $freshUser = $em->find(User::class, $userId);

        if (!$freshUser) {
            $this->logger->error('No se pudo encontrar usuario para actualizar después de verificación', [
                'tenant' => $dominio,
                'user_id' => $userId
            ]);
            return $this->errorResponseService->createErrorResponse(
                PhoneVerificationErrorCodes::PHONE_VERIFICATION_DATABASE_ERROR,
                [
                    'userId' => $userId,
                    'operation' => 'find_user_after_verification',
                    'error' => 'Usuario no encontrado'
                ]
            );
        }

        // Marcar usuario como verificado y actualizar timestamps
        $freshUser->setVerified(true);
        $freshUser->setVerificationCode(null); // Limpiar código después de verificación exitosa
        $freshUser->setUpdatedAt(new \DateTimeImmutable());
        $freshUser->setLastSeen(new \DateTimeImmutable());

        $em->flush();

        $this->logger->info('Verificación de código completada exitosamente', [
            'tenant' => $dominio,
            'user_id' => $id
        ]);

        return new JsonResponse([
            'message' => 'El usuario ha sido verificado con éxito.',
            'code' => 200,
        ], 200);

        } catch (\Exception $e) {
            $this->logger->critical('Excepción no manejada en verificación de código', [
                'tenant' => $dominio,
                'user_id' => $id,
                'exception' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->errorResponseService->createErrorResponse(
                PhoneVerificationErrorCodes::PHONE_VERIFICATION_REQUEST_PROCESSING_ERROR,
                [
                    'userId' => $id,
                    'error' => 'Error interno del servidor',
                    'details' => $e->getMessage()
                ]
            );
        }
    }

    #[Route('/users/{id}/phone-verification/resend', name: 'api_phone_verification_resend', methods: ['POST'])]
    public function resendPhoneVerification(string $dominio, int $id): JsonResponse
    {
        $em = $this->tenantManager->getEntityManager();

        $user = $em->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\App\User', 'u')
            ->where('u.id = :id')
            ->andWhere('u.status = :status')
            ->setParameter('id', $id)
            ->setParameter('status', Status::ACTIVE)
            ->getQuery()
            ->getOneOrNullResult();
        if (!$user) {
            return $this->errorResponseService->createErrorResponse(PhoneVerificationErrorCodes::PHONE_VERIFICATION_RESEND_USER_NOT_FOUND_OR_INACTIVE,
                [
                    'userId' => $id,
                ]
            );        
        }

        if ($user->isVerified()) {
            return $this->errorResponseService->createErrorResponse(PhoneVerificationErrorCodes::PHONE_VERIFICATION_RESEND_USER_ALREADY_VERIFIED,
                [
                    'userId' => $id,
                ]
            );
        }

        if (!$this->phoneVerificationService->generateAndSendCode($user, $em)) {
            return  $this->errorResponseService->createErrorResponse(PhoneVerificationErrorCodes::PHONE_VERIFICATION_RESEND_CODE_FAILED,
                [
                    'userId' => $id,
                ]
            );
        }

        // Buscar la entidad nuevamente para asegurar que esté managed
        $userId = $user->getId();
        $freshUser = $em->find(User::class, $userId);

        if ($freshUser) {
            $freshUser->setLastSeen(new \DateTimeImmutable());
            $freshUser->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
        }

        return new JsonResponse([
            'message' => 'Código de verificación reenviado con éxito.',
            'code' => 200,
            'user_id' => $user->getId(),
        ], 200);
    }
}