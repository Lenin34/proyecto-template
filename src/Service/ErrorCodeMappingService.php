<?php

namespace App\Service;

use App\Enum\ErrorCodes\Api\AuthErrorCodes;
use App\Enum\ErrorCodes\WebAuthErrorCodes;

/**
 * Servicio para mapear códigos de error entre diferentes contextos (API vs Web)
 */
class ErrorCodeMappingService
{
    /**
     * Mapea códigos de error de API a códigos de error web
     */
    public function mapApiToWebError(array $apiErrorCode): array
    {
        // Mapeo directo de códigos de API a códigos web
        $mapping = [
            // Errores de usuario
            AuthErrorCodes::AUTH_USER_NOT_FOUND_OR_INACTIVE['code'] => WebAuthErrorCodes::USER_NOT_FOUND,
            AuthErrorCodes::AUTH_USER_INCORRECT_PASSWORD['code'] => WebAuthErrorCodes::BAD_CREDENTIALS,
            AuthErrorCodes::AUTH_USER_SUSPENDED['code'] => WebAuthErrorCodes::ACCOUNT_DISABLED,
            AuthErrorCodes::AUTH_USER_NOT_VERIFIED['code'] => WebAuthErrorCodes::ACCOUNT_DISABLED,
            AuthErrorCodes::AUTH_ACCOUNT_LOCKED['code'] => WebAuthErrorCodes::ACCOUNT_LOCKED,
            AuthErrorCodes::AUTH_USER_DELETED['code'] => WebAuthErrorCodes::ACCOUNT_DISABLED,

            // Errores de credenciales
            AuthErrorCodes::AUTH_WEAK_PASSWORD['code'] => WebAuthErrorCodes::PASSWORD_TOO_WEAK,
            AuthErrorCodes::AUTH_PASSWORD_EXPIRED['code'] => WebAuthErrorCodes::CREDENTIALS_EXPIRED,
            AuthErrorCodes::AUTH_CURRENT_PASSWORD_INCORRECT['code'] => WebAuthErrorCodes::BAD_CREDENTIALS,

            // Errores de rate limiting
            AuthErrorCodes::AUTH_TOO_MANY_LOGIN_ATTEMPTS['code'] => WebAuthErrorCodes::TOO_MANY_LOGIN_ATTEMPTS,
            AuthErrorCodes::AUTH_SMS_RATE_LIMIT_EXCEEDED['code'] => WebAuthErrorCodes::PASSWORD_RESET_RATE_LIMIT,
            AuthErrorCodes::AUTH_EMAIL_RATE_LIMIT_EXCEEDED['code'] => WebAuthErrorCodes::PASSWORD_RESET_RATE_LIMIT,

            // Errores de token y sesión
            AuthErrorCodes::AUTH_TOKEN_EXPIRED['code'] => WebAuthErrorCodes::SESSION_EXPIRED,
            AuthErrorCodes::AUTH_TOKEN_INVALID['code'] => WebAuthErrorCodes::SESSION_INVALID,
            AuthErrorCodes::AUTH_SESSION_EXPIRED['code'] => WebAuthErrorCodes::SESSION_EXPIRED,
            AuthErrorCodes::AUTH_UNAUTHORIZED_ACCESS['code'] => WebAuthErrorCodes::ACCOUNT_DISABLED,

            // Errores de verificación
            AuthErrorCodes::AUTH_VERIFICATION_CODE_EXPIRED['code'] => WebAuthErrorCodes::VERIFICATION_CODE_EXPIRED,
            AuthErrorCodes::AUTH_VERIFICATION_CODE_INVALID['code'] => WebAuthErrorCodes::VERIFICATION_CODE_INVALID,
            AuthErrorCodes::AUTH_MAX_VERIFICATION_ATTEMPTS['code'] => WebAuthErrorCodes::VERIFICATION_MAX_ATTEMPTS,
            AuthErrorCodes::AUTH_VERIFICATION_ALREADY_COMPLETED['code'] => WebAuthErrorCodes::VERIFICATION_ALREADY_COMPLETED,

            // Errores de tenant
            AuthErrorCodes::AUTH_TENANT_NOT_FOUND['code'] => WebAuthErrorCodes::TENANT_NOT_FOUND,
            AuthErrorCodes::AUTH_COMPANY_SUSPENDED['code'] => WebAuthErrorCodes::TENANT_SUSPENDED,
            AuthErrorCodes::AUTH_COMPANY_NOT_FOUND_OR_INACTIVE['code'] => WebAuthErrorCodes::TENANT_NOT_FOUND,

            // Errores de validación
            AuthErrorCodes::AUTH_INVALID_EMAIL_FORMAT['code'] => WebAuthErrorCodes::INVALID_EMAIL_FORMAT,
            AuthErrorCodes::AUTH_INVALID_PHONE_FORMAT['code'] => WebAuthErrorCodes::FORM_VALIDATION_FAILED,
            AuthErrorCodes::AUTH_INVALID_CURP_FORMAT['code'] => WebAuthErrorCodes::FORM_VALIDATION_FAILED,

            // Errores de sistema
            AuthErrorCodes::AUTH_INTERNAL_ERROR['code'] => WebAuthErrorCodes::INTERNAL_SERVER_ERROR,
            AuthErrorCodes::AUTH_REGISTRATION_FAILED['code'] => WebAuthErrorCodes::INTERNAL_SERVER_ERROR,
            AuthErrorCodes::AUTH_PHONE_VERIFICATION_FAILED['code'] => WebAuthErrorCodes::VERIFICATION_SMS_FAILED,
            AuthErrorCodes::AUTH_SMS_SENDING_FAILED['code'] => WebAuthErrorCodes::VERIFICATION_SMS_FAILED,
        ];

        $apiCode = $apiErrorCode['code'];
        
        if (isset($mapping[$apiCode])) {
            return $mapping[$apiCode];
        }

        // Fallback por defecto
        return WebAuthErrorCodes::INTERNAL_SERVER_ERROR;
    }

    /**
     * Obtiene el código de error web apropiado basado en el contexto
     */
    public function getWebErrorForContext(string $context, string $errorType): array
    {
        $contextMappings = [
            'login' => [
                'invalid_credentials' => WebAuthErrorCodes::INVALID_CREDENTIALS,
                'user_not_found' => WebAuthErrorCodes::USER_NOT_FOUND,
                'account_disabled' => WebAuthErrorCodes::ACCOUNT_DISABLED,
                'account_locked' => WebAuthErrorCodes::ACCOUNT_LOCKED,
                'too_many_attempts' => WebAuthErrorCodes::TOO_MANY_LOGIN_ATTEMPTS,
                'csrf_invalid' => WebAuthErrorCodes::INVALID_CSRF_TOKEN,
                'tenant_not_found' => WebAuthErrorCodes::TENANT_NOT_FOUND,
                'session_expired' => WebAuthErrorCodes::SESSION_EXPIRED,
            ],
            'password_reset' => [
                'user_not_found' => WebAuthErrorCodes::PASSWORD_RESET_USER_NOT_FOUND,
                'email_failed' => WebAuthErrorCodes::PASSWORD_RESET_EMAIL_FAILED,
                'token_expired' => WebAuthErrorCodes::PASSWORD_RESET_TOKEN_EXPIRED,
                'token_invalid' => WebAuthErrorCodes::PASSWORD_RESET_TOKEN_INVALID,
                'password_weak' => WebAuthErrorCodes::PASSWORD_TOO_WEAK,
                'password_mismatch' => WebAuthErrorCodes::PASSWORD_MISMATCH,
                'rate_limit' => WebAuthErrorCodes::PASSWORD_RESET_RATE_LIMIT,
            ],
            'verification' => [
                'code_expired' => WebAuthErrorCodes::VERIFICATION_CODE_EXPIRED,
                'code_invalid' => WebAuthErrorCodes::VERIFICATION_CODE_INVALID,
                'max_attempts' => WebAuthErrorCodes::VERIFICATION_MAX_ATTEMPTS,
                'already_verified' => WebAuthErrorCodes::VERIFICATION_ALREADY_COMPLETED,
                'sms_failed' => WebAuthErrorCodes::VERIFICATION_SMS_FAILED,
            ],
            'system' => [
                'database_error' => WebAuthErrorCodes::DATABASE_CONNECTION_FAILED,
                'service_unavailable' => WebAuthErrorCodes::SERVICE_UNAVAILABLE,
                'internal_error' => WebAuthErrorCodes::INTERNAL_SERVER_ERROR,
                'maintenance' => WebAuthErrorCodes::MAINTENANCE_MODE,
                'feature_disabled' => WebAuthErrorCodes::FEATURE_DISABLED,
            ]
        ];

        if (isset($contextMappings[$context][$errorType])) {
            return $contextMappings[$context][$errorType];
        }

        // Fallback
        return WebAuthErrorCodes::INTERNAL_SERVER_ERROR;
    }

    /**
     * Convierte un error de Symfony Security a código de error web
     */
    public function mapSymfonySecurityError(string $securityErrorKey): array
    {
        $mapping = [
            'Invalid credentials.' => WebAuthErrorCodes::INVALID_CREDENTIALS,
            'Bad credentials.' => WebAuthErrorCodes::BAD_CREDENTIALS,
            'Username could not be found.' => WebAuthErrorCodes::USER_NOT_FOUND,
            'Account has expired.' => WebAuthErrorCodes::ACCOUNT_EXPIRED,
            'Credentials have expired.' => WebAuthErrorCodes::CREDENTIALS_EXPIRED,
            'Account is disabled.' => WebAuthErrorCodes::ACCOUNT_DISABLED,
            'Account is locked.' => WebAuthErrorCodes::ACCOUNT_LOCKED,
            'Too many failed login attempts.' => WebAuthErrorCodes::TOO_MANY_LOGIN_ATTEMPTS,
            'Invalid CSRF token.' => WebAuthErrorCodes::INVALID_CSRF_TOKEN,
            'The CSRF token is invalid.' => WebAuthErrorCodes::INVALID_CSRF_TOKEN,
        ];

        return $mapping[$securityErrorKey] ?? WebAuthErrorCodes::INVALID_CREDENTIALS;
    }

    /**
     * Obtiene información de error enriquecida con contexto adicional
     */
    public function getEnrichedErrorInfo(array $errorCode, array $context = []): array
    {
        $enriched = $errorCode;
        
        // Agregar sugerencias basadas en el tipo de error
        $enriched['suggestions'] = $this->getErrorSuggestions($errorCode['code']);
        
        // Agregar información de contexto
        if (!empty($context)) {
            $enriched['context'] = $context;
        }
        
        // Agregar timestamp
        $enriched['timestamp'] = date('c');
        
        return $enriched;
    }

    /**
     * Obtiene sugerencias para resolver errores específicos
     */
    private function getErrorSuggestions(string $errorCode): array
    {
        $suggestions = [
            'WA-001' => [
                'Verifique que el email esté escrito correctamente',
                'Asegúrese de que la contraseña sea la correcta',
                'Intente usar la función "¿Olvidaste tu contraseña?"'
            ],
            'WA-002' => [
                'Revise las mayúsculas y minúsculas en su contraseña',
                'Verifique que no tenga activado el Caps Lock',
                'Intente copiar y pegar su contraseña si la tiene guardada'
            ],
            'WA-003' => [
                'Verifique que el email esté registrado en el sistema',
                'Contacte al administrador si cree que debería tener acceso',
                'Intente registrarse si es un usuario nuevo'
            ],
            'WA-007' => [
                'Contacte al administrador del sistema',
                'Espere unos minutos antes de intentar nuevamente',
                'Verifique si hay notificaciones de seguridad en su email'
            ],
            'WA-008' => [
                'Recargue la página completamente (Ctrl+F5)',
                'Limpie la caché de su navegador',
                'Intente desde una ventana de incógnito'
            ],
            'WA-009' => [
                'Espere 15 minutos antes de intentar nuevamente',
                'Use la función de recuperación de contraseña',
                'Contacte al soporte si el problema persiste'
            ],
            'WA-020' => [
                'Verifique que el email esté escrito correctamente',
                'Asegúrese de usar el email registrado en el sistema',
                'Contacte al administrador si necesita registrar el email'
            ],
            'WA-022' => [
                'Solicite un nuevo enlace de recuperación',
                'Los enlaces expiran por seguridad después de cierto tiempo',
                'Revise su bandeja de spam por el nuevo enlace'
            ],
            'WA-025' => [
                'Asegúrese de escribir la misma contraseña en ambos campos',
                'Verifique que no haya espacios adicionales al inicio o final',
                'Intente copiar y pegar la contraseña en ambos campos'
            ]
        ];

        return $suggestions[$errorCode] ?? [
            'Intente nuevamente en unos momentos',
            'Contacte al soporte técnico si el problema persiste'
        ];
    }

    /**
     * Determina si un error requiere acción inmediata del usuario
     */
    public function requiresImmediateAction(string $errorCode): bool
    {
        $immediateActionCodes = [
            'WA-007', // Account locked
            'WA-008', // Invalid CSRF token
            'WA-022', // Token expired
            'WA-024', // Password too weak
            'WA-034', // Maintenance mode
        ];

        return in_array($errorCode, $immediateActionCodes);
    }

    /**
     * Obtiene el nivel de severidad del error
     */
    public function getErrorSeverity(string $errorCode): string
    {
        $severityMap = [
            // Críticos - requieren atención inmediata
            'WA-007' => 'critical',  // Account locked
            'WA-017' => 'critical',  // Database connection failed
            'WA-019' => 'critical',  // Internal server error
            'WA-034' => 'critical',  // Maintenance mode
            
            // Altos - problemas de seguridad o acceso
            'WA-008' => 'high',      // Invalid CSRF token
            'WA-009' => 'high',      // Too many attempts
            'WA-010' => 'high',      // Suspicious activity
            'WA-025' => 'high',      // Rate limit exceeded
            
            // Medios - errores de usuario o configuración
            'WA-001' => 'medium',    // Invalid credentials
            'WA-002' => 'medium',    // Bad credentials
            'WA-003' => 'medium',    // User not found
            'WA-020' => 'medium',    // Password reset user not found
            'WA-022' => 'medium',    // Token expired
            
            // Bajos - errores menores o informativos
            'WA-024' => 'low',       // Password too weak
            'WA-026' => 'low',       // Verification code expired
            'WA-027' => 'low',       // Verification code invalid
            'WA-031' => 'low',       // Invalid email format
        ];

        return $severityMap[$errorCode] ?? 'medium';
    }
}
