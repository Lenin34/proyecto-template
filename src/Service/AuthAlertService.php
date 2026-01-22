<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use App\Traits\SweetAlertTrait;
use App\Enum\ErrorCodes\Api\AuthErrorCodes;
use App\Enum\ErrorCodes\WebAuthErrorCodes;
use App\Service\ErrorCodeMappingService;

/**
 * Servicio especializado para manejo de alertas de autenticación con SweetAlert2
 */
class AuthAlertService
{
    use SweetAlertTrait;

    private LoggerInterface $logger;
    private ErrorCodeMappingService $errorMappingService;

    public function __construct(LoggerInterface $logger, ErrorCodeMappingService $errorMappingService)
    {
        $this->logger = $logger;
        $this->errorMappingService = $errorMappingService;
    }

    /**
     * Maneja errores de autenticación y retorna respuesta JSON para SweetAlert2
     */
    public function handleAuthenticationError(AuthenticationException $exception, array $context = []): Response
    {
        $messageKey = $exception->getMessageKey();
        $message = $this->translateAuthError($messageKey);
        
        $this->logger->warning('Authentication error handled', [
            'message_key' => $messageKey,
            'translated_message' => $message,
            'context' => $context
        ]);

        return $this->createAuthErrorResponse($message, $messageKey, $context);
    }

    /**
     * Crea respuesta de error de autenticación con formato SweetAlert2
     */
    private function createAuthErrorResponse(string $message, string $originalKey, array $context = []): Response
    {
        $data = [
            'success' => false,
            'type' => 'error',
            'title' => 'Error de Autenticación',
            'message' => $message,
            'swal' => true,
            'icon' => 'error',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Intentar de nuevo',
            'timer' => null, // No auto-close para errores de auth
            'allowOutsideClick' => true,
            'allowEscapeKey' => true,
            'original_error' => $originalKey
        ];

        // Agregar contexto adicional si es necesario
        if (!empty($context)) {
            $data['context'] = $context;
        }

        return new Response(
            json_encode($data),
            401,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Traduce las claves de error de autenticación a mensajes amigables usando los ErrorCodes
     */
    private function translateAuthError(string $messageKey): string
    {
        // Usar el servicio de mapeo para obtener el código de error web apropiado
        $webErrorCode = $this->errorMappingService->mapSymfonySecurityError($messageKey);
        return $webErrorCode['message'];
    }

    /**
     * Maneja errores específicos de recuperación de contraseña
     */
    public function handlePasswordResetError(string $errorCode, array $context = []): Response
    {
        $message = $this->translatePasswordResetError($errorCode);
        
        return $this->createPasswordResetErrorResponse($message, $errorCode, $context);
    }

    /**
     * Traduce errores de recuperación de contraseña usando los códigos existentes
     */
    private function translatePasswordResetError(string $errorCode): string
    {
        // Mapeo de códigos personalizados a códigos de error existentes
        $errorMapping = [
            'USER_NOT_FOUND' => WebAuthErrorCodes::PASSWORD_RESET_USER_NOT_FOUND,
            'EMAIL_SEND_FAILED' => WebAuthErrorCodes::PASSWORD_RESET_EMAIL_FAILED,
            'TOKEN_EXPIRED' => WebAuthErrorCodes::PASSWORD_RESET_TOKEN_EXPIRED,
            'TOKEN_INVALID' => WebAuthErrorCodes::PASSWORD_RESET_TOKEN_INVALID,
            'PASSWORD_TOO_WEAK' => WebAuthErrorCodes::PASSWORD_TOO_WEAK,
            'RATE_LIMIT_EXCEEDED' => WebAuthErrorCodes::PASSWORD_RESET_RATE_LIMIT,
            'PASSWORD_UPDATE_FAILED' => WebAuthErrorCodes::INTERNAL_SERVER_ERROR,
            'INVALID_METHOD' => WebAuthErrorCodes::FORM_VALIDATION_FAILED,
            'TENANT_ERROR' => WebAuthErrorCodes::TENANT_NOT_FOUND,
            'SYSTEM_ERROR' => WebAuthErrorCodes::INTERNAL_SERVER_ERROR,
        ];

        // Si encontramos un mapeo, usar el mensaje del código de error
        if (isset($errorMapping[$errorCode])) {
            return $errorMapping[$errorCode]['message'];
        }

        // Fallback
        return WebAuthErrorCodes::INTERNAL_SERVER_ERROR['message'];
    }

    /**
     * Crea respuesta de error para recuperación de contraseña
     */
    private function createPasswordResetErrorResponse(string $message, string $errorCode, array $context = []): Response
    {
        $data = [
            'success' => false,
            'type' => 'error',
            'title' => 'Error de Recuperación',
            'message' => $message,
            'swal' => true,
            'icon' => 'error',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Entendido',
            'error_code' => $errorCode
        ];

        if (!empty($context)) {
            $data['context'] = $context;
        }

        return new Response(
            json_encode($data),
            400,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Maneja errores de verificación de cuenta
     */
    public function handleVerificationError(string $errorCode, array $context = []): Response
    {
        $message = $this->translateVerificationError($errorCode);
        
        $data = [
            'success' => false,
            'type' => 'error',
            'title' => 'Error de Verificación',
            'message' => $message,
            'swal' => true,
            'icon' => 'error',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Entendido',
            'error_code' => $errorCode
        ];

        return new Response(
            json_encode($data),
            400,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Traduce errores de verificación de cuenta usando los códigos existentes
     */
    private function translateVerificationError(string $errorCode): string
    {
        // Mapeo de códigos personalizados a códigos de error existentes
        $errorMapping = [
            'CODE_EXPIRED' => WebAuthErrorCodes::VERIFICATION_CODE_EXPIRED,
            'CODE_INVALID' => WebAuthErrorCodes::VERIFICATION_CODE_INVALID,
            'MAX_ATTEMPTS_EXCEEDED' => WebAuthErrorCodes::VERIFICATION_MAX_ATTEMPTS,
            'ALREADY_VERIFIED' => WebAuthErrorCodes::VERIFICATION_ALREADY_COMPLETED,
            'SMS_SEND_FAILED' => WebAuthErrorCodes::VERIFICATION_SMS_FAILED,
        ];

        // Si encontramos un mapeo, usar el mensaje del código de error
        if (isset($errorMapping[$errorCode])) {
            return $errorMapping[$errorCode]['message'];
        }

        // Fallback
        return WebAuthErrorCodes::INTERNAL_SERVER_ERROR['message'];
    }

    /**
     * Crea respuesta de éxito para autenticación
     */
    public function createSuccessResponse(string $message, array $options = []): Response
    {
        $data = [
            'success' => true,
            'type' => 'success',
            'title' => 'Éxito',
            'message' => $message,
            'swal' => true,
            'icon' => 'success',
            'showConfirmButton' => false,
            'timer' => 2000
        ];

        // Agregar redirección si se especifica
        if (isset($options['redirect'])) {
            $data['redirect'] = $options['redirect'];
            $data['timer'] = 1500; // Menos tiempo antes de redirigir
        }

        // Agregar todas las opciones adicionales al array de datos
        foreach ($options as $key => $value) {
            if ($key !== 'redirect') { // redirect ya se maneja arriba
                $data[$key] = $value;
            }
        }

        return new Response(
            json_encode($data),
            200,
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Maneja alertas de sesión expirada
     */
    public function handleSessionExpired(string $redirectUrl = '/login'): Response
    {
        $errorInfo = WebAuthErrorCodes::SESSION_EXPIRED;

        $data = [
            'success' => false,
            'type' => 'warning',
            'title' => 'Sesión Expirada',
            'message' => $errorInfo['message'],
            'swal' => true,
            'icon' => 'warning',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Ir al login',
            'allowOutsideClick' => false,
            'allowEscapeKey' => false,
            'redirect' => $redirectUrl,
            'error_code' => $errorInfo['code']
        ];

        return new Response(
            json_encode($data),
            $errorInfo['http_code'],
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Maneja errores usando códigos de AuthErrorCodes (API)
     */
    public function handleApiAuthError(array $errorCode, array $context = []): Response
    {
        $data = [
            'success' => false,
            'type' => 'error',
            'title' => 'Error de Autenticación',
            'message' => $errorCode['message'],
            'swal' => true,
            'icon' => 'error',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Entendido',
            'error_code' => $errorCode['code']
        ];

        if (!empty($context)) {
            $data['context'] = $context;
        }

        return new Response(
            json_encode($data),
            $errorCode['http_code'],
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Maneja errores usando códigos de WebAuthErrorCodes
     */
    public function handleWebAuthError(array $errorCode, array $context = []): Response
    {
        $data = [
            'success' => false,
            'type' => 'error',
            'title' => 'Error de Autenticación',
            'message' => $errorCode['message'],
            'swal' => true,
            'icon' => 'error',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Intentar de nuevo',
            'error_code' => $errorCode['code']
        ];

        if (!empty($context)) {
            $data['context'] = $context;
        }

        return new Response(
            json_encode($data),
            $errorCode['http_code'],
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Obtiene información de error por código desde cualquier enum
     */
    public function getErrorByCode(string $code): ?array
    {
        // Buscar en WebAuthErrorCodes
        $webAuthError = WebAuthErrorCodes::getErrorInfo($code);
        if ($webAuthError) {
            return $webAuthError;
        }

        // Buscar en AuthErrorCodes (API)
        $reflection = new \ReflectionClass(AuthErrorCodes::class);
        $constants = $reflection->getConstants();

        foreach ($constants as $constant) {
            if (is_array($constant) && isset($constant['code']) && $constant['code'] === $code) {
                return $constant;
            }
        }

        return null;
    }

    /**
     * Maneja errores por código de error específico
     */
    public function handleErrorByCode(string $errorCode, array $context = []): Response
    {
        $errorInfo = $this->getErrorByCode($errorCode);

        if (!$errorInfo) {
            // Fallback si no se encuentra el código
            $errorInfo = WebAuthErrorCodes::INTERNAL_SERVER_ERROR;
        }

        return $this->handleWebAuthError($errorInfo, $context);
    }

    /**
     * Crea respuesta de error con código específico y contexto
     */
    public function createErrorResponse(array $errorCode, array $context = [], array $swalOptions = []): Response
    {
        $data = [
            'success' => false,
            'type' => 'error',
            'title' => $swalOptions['title'] ?? 'Error',
            'message' => $errorCode['message'],
            'swal' => true,
            'icon' => $swalOptions['icon'] ?? 'error',
            'showConfirmButton' => $swalOptions['showConfirmButton'] ?? true,
            'confirmButtonText' => $swalOptions['confirmButtonText'] ?? 'Entendido',
            'error_code' => $errorCode['code']
        ];

        // Agregar opciones adicionales de SweetAlert
        foreach (['timer', 'allowOutsideClick', 'allowEscapeKey', 'redirect'] as $option) {
            if (isset($swalOptions[$option])) {
                $data[$option] = $swalOptions[$option];
            }
        }

        if (!empty($context)) {
            $data['context'] = $context;
        }

        return new Response(
            json_encode($data),
            $errorCode['http_code'],
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Crea respuesta de error enriquecida con sugerencias y contexto
     */
    public function createEnrichedErrorResponse(array $errorCode, array $context = [], array $swalOptions = []): Response
    {
        // Obtener información enriquecida del error
        $enrichedError = $this->errorMappingService->getEnrichedErrorInfo($errorCode, $context);

        $data = [
            'success' => false,
            'type' => 'error',
            'title' => $swalOptions['title'] ?? 'Error de Autenticación',
            'message' => $enrichedError['message'],
            'swal' => true,
            'icon' => $swalOptions['icon'] ?? 'error',
            'showConfirmButton' => $swalOptions['showConfirmButton'] ?? true,
            'confirmButtonText' => $swalOptions['confirmButtonText'] ?? 'Entendido',
            'error_code' => $enrichedError['code'],
            'severity' => $this->errorMappingService->getErrorSeverity($enrichedError['code']),
            'suggestions' => $enrichedError['suggestions'],
            'requires_immediate_action' => $this->errorMappingService->requiresImmediateAction($enrichedError['code'])
        ];

        // Agregar opciones adicionales de SweetAlert
        foreach (['timer', 'allowOutsideClick', 'allowEscapeKey', 'redirect'] as $option) {
            if (isset($swalOptions[$option])) {
                $data[$option] = $swalOptions[$option];
            }
        }

        if (!empty($enrichedError['context'])) {
            $data['context'] = $enrichedError['context'];
        }

        return new Response(
            json_encode($data),
            $enrichedError['http_code'],
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Maneja errores de API mapeándolos a errores web
     */
    public function handleMappedApiError(array $apiErrorCode, array $context = []): Response
    {
        $webErrorCode = $this->errorMappingService->mapApiToWebError($apiErrorCode);
        return $this->createEnrichedErrorResponse($webErrorCode, $context);
    }

    /**
     * Maneja errores por contexto específico
     */
    public function handleContextualError(string $context, string $errorType, array $additionalContext = []): Response
    {
        $errorCode = $this->errorMappingService->getWebErrorForContext($context, $errorType);
        return $this->createEnrichedErrorResponse($errorCode, $additionalContext);
    }
}
