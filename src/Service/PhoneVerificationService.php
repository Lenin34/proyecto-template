<?php
namespace App\Service;

use App\Entity\App\User;
use App\Enum\ErrorCodes\PhoneVerificationErrorCodes;
use Psr\Log\LoggerInterface;

class PhoneVerificationService
{
    private TwilioWhatsAppService $twilioWhatsAppService;
    private ApplicationErrorService $applicationErrorService;
    private LoggerInterface $logger;
    private FallbackNotificationService $fallbackService;
    private const PHONE_VERIFICATION_TEMPLATE = 'app_sindicatos_verification';

    public function __construct(
        TwilioWhatsAppService $twilioWhatsAppService,
        ApplicationErrorService $applicationErrorService,
        LoggerInterface $logger,
        FallbackNotificationService $fallbackService
    ) {
        $this->applicationErrorService = $applicationErrorService;
        $this->twilioWhatsAppService = $twilioWhatsAppService;
        $this->logger = $logger;
        $this->fallbackService = $fallbackService;
    }

    public function generateAndSendCode(User $user, $entityManager = null): bool
    {
        if ($user->getPhoneNumber() === null) {
            $this->applicationErrorService->createError(PhoneVerificationErrorCodes::PHONE_VERIFICATION_PHONE_NUMBER_NOT_FOUND, [
                'user_id' => $user->getId(),
            ]);

            return false;
        }

        try {
            // Si se proporciona EntityManager, usar la lógica mejorada
            if ($entityManager) {
                // Buscar la entidad nuevamente para asegurar que esté managed
                $userId = $user->getId();
                $freshUser = $entityManager->find(User::class, $userId);

                if (!$freshUser) {
                    $this->logger->error('Reenvío: No se pudo encontrar usuario para actualizar código', [
                        'user_id' => $userId
                    ]);
                    return false;
                }

                // Obtener código anterior para logging
                $previousCode = $freshUser->getVerificationCode();

                // Generar nuevo código diferente al anterior
                $verificationCode = random_int(100000, 999999);

                // Asegurar que el código sea diferente al anterior
                $attempts = 0;
                while ($verificationCode == $previousCode && $attempts < 10) {
                    $verificationCode = random_int(100000, 999999);
                    $attempts++;
                }

                $this->logger->info('Reenvío: Generando nuevo código', [
                    'user_id' => $userId,
                    'previous_code' => $previousCode,
                    'new_code' => $verificationCode,
                    'attempts' => $attempts
                ]);

                // Establecer nuevo código en la entidad fresca
                $freshUser->setVerificationCode((string) $verificationCode);
                $freshUser->setUpdatedAt(new \DateTimeImmutable());

                // Guardar cambios
                $entityManager->flush();

                // Usar la entidad fresca para el resto del proceso
                $user = $freshUser;
            } else {
                // Lógica simple para compatibilidad hacia atrás
                $verificationCode = random_int(100000, 999999);
                $user->setVerificationCode((string) $verificationCode);
            }

            $message = "Este es tu código de verificacion para tu app de sindicato, por favor no lo compartas con nadie: " . $verificationCode;

            $response = $this->twilioWhatsAppService->sendSMSMessage($user->getPhoneNumber(), $message);

            if (!$response) {
                // Usar servicio de respaldo cuando Twilio falla
                $this->fallbackService->logSMSFallback($user->getPhoneNumber(), $message);

                $this->logger->warning('SMS no enviado por Twilio - usando fallback', [
                    'user_id' => $user->getId(),
                    'phone_number' => $user->getPhoneNumber(),
                    'verification_code' => $verificationCode,
                    'fallback_used' => true
                ]);

                // IMPORTANTE: Retornamos true para que la app continúe funcionando
                // El código se guardó en la base de datos, el usuario puede usarlo
                return true;
            }

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Reenvío: Excepción durante generación de código', [
                'user_id' => $user->getId(),
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->applicationErrorService->createError(PhoneVerificationErrorCodes::PHONE_VERIFICATION_ERROR, [
                'user_id' => $user->getId(),
                'error_message' => $e->getMessage(),
            ]);

            return false;
        }
    }
    
    public function verifyCode(User $user, string $code): bool
    {
        if ($user->getVerificationCode() !== $code) {
            $this->applicationErrorService->createError(PhoneVerificationErrorCodes::PHONE_VERIFICATION_CODE_MISMATCH, [
                'user_id' => $user->getId(),
                'code' => $code,
            ]);

            return false;
        }

        try {
            $user->setVerified(true);
            $user->setVerificationCode(null);
    
            return true;
        } catch (\Exception $e) {
            $this->applicationErrorService->createError(PhoneVerificationErrorCodes::PHONE_VERIFICATION_VERIFICATION_ERROR, [
                'user_id' => $user->getId(),
                'error_message' => $e->getMessage(),
            ]);
            
            return false;
        }

    }

    public function sendVerificationCode(string $phone_number, int $code): bool
    {
        try {
            $message = "Este es tu código de verificación para tu app de sindicato, por favor no lo compartas con nadie: " . $code;
            
            $response = $this->twilioWhatsAppService->sendSMSMessage($phone_number, $message);

            if (!$response) {
                // Usar servicio de respaldo cuando Twilio falla
                $this->fallbackService->logSMSFallback($phone_number, $message);

                $this->logger->warning('SMS de verificación no enviado por Twilio - usando fallback', [
                    'phone_number' => $phone_number,
                    'verification_code' => $code,
                    'fallback_used' => true
                ]);

                // IMPORTANTE: Retornamos true para que la app continúe funcionando
                return true;
            }

            return true;
        } catch (\Exception $e) {
            $this->applicationErrorService->createError(PhoneVerificationErrorCodes::PHONE_VERIFICATION_ERROR, [
                'phone_number' => $phone_number,
                'error_message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Método específico para enviar código de recuperación de contraseña
     */
    public function sendPasswordResetCode(string $phone_number, int $code): bool
    {
        try {
            $message = "Este es tu código de verificación para restablecer tu contraseña, por favor no lo compartas con nadie: " . $code;

            $response = $this->twilioWhatsAppService->sendSMSMessage($phone_number, $message);

            if (!$response) {
                $this->applicationErrorService->createError(PhoneVerificationErrorCodes::PHONE_VERIFICATION_MESSAGE_NOT_SENT, [
                    'phone_number' => $phone_number,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            $this->applicationErrorService->createError(PhoneVerificationErrorCodes::PHONE_VERIFICATION_ERROR, [
                'phone_number' => $phone_number,
                'error_message' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

    