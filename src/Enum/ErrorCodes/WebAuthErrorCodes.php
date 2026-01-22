<?php

namespace App\Enum\ErrorCodes;

/**
 * Códigos de error específicos para autenticación web (login form)
 */
class WebAuthErrorCodes
{
    // ========================================
    // ERRORES DE CREDENCIALES
    // ========================================

    public const INVALID_CREDENTIALS = [
        'code' => 'WA-001',
        'message' => 'Las credenciales proporcionadas son incorrectas. Verifique su email y contraseña.',
        'http_code' => 401,
    ];

    public const BAD_CREDENTIALS = [
        'code' => 'WA-002', 
        'message' => 'Email o contraseña incorrectos. Por favor, inténtelo de nuevo.',
        'http_code' => 401,
    ];

    public const USER_NOT_FOUND = [
        'code' => 'WA-003',
        'message' => 'No se encontró una cuenta con este email. Verifique que esté registrado.',
        'http_code' => 404,
    ];

    public const ACCOUNT_EXPIRED = [
        'code' => 'WA-004',
        'message' => 'Su cuenta ha expirado. Contacte al administrador.',
        'http_code' => 403,
    ];

    public const CREDENTIALS_EXPIRED = [
        'code' => 'WA-005',
        'message' => 'Sus credenciales han expirado. Debe cambiar su contraseña.',
        'http_code' => 403,
    ];

    public const ACCOUNT_DISABLED = [
        'code' => 'WA-006',
        'message' => 'Su cuenta está deshabilitada. Contacte al administrador.',
        'http_code' => 403,
    ];

    public const ACCOUNT_LOCKED = [
        'code' => 'WA-007',
        'message' => 'Su cuenta está bloqueada por seguridad. Contacte al administrador.',
        'http_code' => 423,
    ];

    public const INSUFFICIENT_ROLE_PERMISSIONS = [
        'code' => 'WA-036',
        'message' => 'No tiene los permisos necesarios para acceder al sistema. Su cuenta requiere permisos de administrador o líder.',
        'http_code' => 403,
    ];

    // ========================================
    // ERRORES DE SEGURIDAD
    // ========================================

    public const INVALID_CSRF_TOKEN = [
        'code' => 'WA-008',
        'message' => 'Token de seguridad inválido. Recargue la página e intente nuevamente.',
        'http_code' => 403,
    ];

    public const TOO_MANY_LOGIN_ATTEMPTS = [
        'code' => 'WA-009',
        'message' => 'Demasiados intentos fallidos. Intente nuevamente en unos minutos.',
        'http_code' => 429,
    ];

    public const SUSPICIOUS_ACTIVITY = [
        'code' => 'WA-010',
        'message' => 'Se detectó actividad sospechosa. Su cuenta ha sido bloqueada temporalmente.',
        'http_code' => 423,
    ];

    // ========================================
    // ERRORES DE TENANT/DOMINIO
    // ========================================

    public const TENANT_NOT_FOUND = [
        'code' => 'WA-011',
        'message' => 'El dominio especificado no existe o no está disponible.',
        'http_code' => 404,
    ];

    public const INVALID_TENANT = [
        'code' => 'WA-012',
        'message' => 'Dominio inválido. Verifique la URL de acceso.',
        'http_code' => 400,
    ];

    public const TENANT_SUSPENDED = [
        'code' => 'WA-013',
        'message' => 'El servicio está temporalmente suspendido para este dominio.',
        'http_code' => 503,
    ];

    // ========================================
    // ERRORES DE SESIÓN
    // ========================================

    public const SESSION_EXPIRED = [
        'code' => 'WA-014',
        'message' => 'Su sesión ha expirado. Por favor, inicie sesión nuevamente.',
        'http_code' => 401,
    ];

    public const SESSION_INVALID = [
        'code' => 'WA-015',
        'message' => 'Sesión inválida. Por favor, inicie sesión nuevamente.',
        'http_code' => 401,
    ];

    public const CONCURRENT_SESSION_LIMIT = [
        'code' => 'WA-016',
        'message' => 'Se ha excedido el límite de sesiones concurrentes.',
        'http_code' => 409,
    ];

    // ========================================
    // ERRORES DE CONEXIÓN/SISTEMA
    // ========================================

    public const DATABASE_CONNECTION_FAILED = [
        'code' => 'WA-017',
        'message' => 'Error de conexión con la base de datos. Intente nuevamente en unos momentos.',
        'http_code' => 503,
    ];

    public const SERVICE_UNAVAILABLE = [
        'code' => 'WA-018',
        'message' => 'Servicio temporalmente no disponible. Intente más tarde.',
        'http_code' => 503,
    ];

    public const INTERNAL_SERVER_ERROR = [
        'code' => 'WA-019',
        'message' => 'Error interno del servidor. Contacte al administrador si el problema persiste.',
        'http_code' => 500,
    ];

    // ========================================
    // ERRORES DE RECUPERACIÓN DE CONTRASEÑA
    // ========================================

    public const PASSWORD_RESET_USER_NOT_FOUND = [
        'code' => 'WA-020',
        'message' => 'No se encontró una cuenta con este email.',
        'http_code' => 404,
    ];

    public const PASSWORD_RESET_EMAIL_FAILED = [
        'code' => 'WA-021',
        'message' => 'Error al enviar el email de recuperación. Intente nuevamente.',
        'http_code' => 500,
    ];

    public const PASSWORD_RESET_TOKEN_EXPIRED = [
        'code' => 'WA-022',
        'message' => 'El enlace de recuperación ha expirado. Solicite uno nuevo.',
        'http_code' => 400,
    ];

    public const PASSWORD_RESET_TOKEN_INVALID = [
        'code' => 'WA-023',
        'message' => 'El enlace de recuperación es inválido.',
        'http_code' => 400,
    ];

    public const PASSWORD_TOO_WEAK = [
        'code' => 'WA-024',
        'message' => 'La nueva contraseña no cumple con los requisitos de seguridad.',
        'http_code' => 400,
    ];

    public const PASSWORD_MISMATCH = [
        'code' => 'WA-025',
        'message' => 'Las contraseñas no coinciden. Por favor, verifique que ambas sean idénticas.',
        'http_code' => 400,
    ];

    public const PASSWORD_RESET_RATE_LIMIT = [
        'code' => 'WA-025',
        'message' => 'Demasiadas solicitudes de recuperación. Intente nuevamente en unos minutos.',
        'http_code' => 429,
    ];

    // ========================================
    // ERRORES DE VERIFICACIÓN
    // ========================================

    public const VERIFICATION_CODE_EXPIRED = [
        'code' => 'WA-026',
        'message' => 'El código de verificación ha expirado. Solicite uno nuevo.',
        'http_code' => 400,
    ];

    public const VERIFICATION_CODE_INVALID = [
        'code' => 'WA-027',
        'message' => 'El código de verificación es incorrecto.',
        'http_code' => 400,
    ];

    public const VERIFICATION_MAX_ATTEMPTS = [
        'code' => 'WA-028',
        'message' => 'Se ha excedido el número máximo de intentos de verificación.',
        'http_code' => 429,
    ];

    public const VERIFICATION_ALREADY_COMPLETED = [
        'code' => 'WA-029',
        'message' => 'Su cuenta ya está verificada.',
        'http_code' => 400,
    ];

    public const VERIFICATION_SMS_FAILED = [
        'code' => 'WA-030',
        'message' => 'Error al enviar el código por SMS. Intente nuevamente.',
        'http_code' => 500,
    ];

    // ========================================
    // ERRORES DE VALIDACIÓN
    // ========================================

    public const INVALID_EMAIL_FORMAT = [
        'code' => 'WA-031',
        'message' => 'El formato del email es inválido.',
        'http_code' => 400,
    ];

    public const MISSING_REQUIRED_FIELDS = [
        'code' => 'WA-032',
        'message' => 'Faltan campos requeridos en el formulario.',
        'http_code' => 400,
    ];

    public const FORM_VALIDATION_FAILED = [
        'code' => 'WA-033',
        'message' => 'Los datos del formulario no son válidos.',
        'http_code' => 400,
    ];

    // ========================================
    // ERRORES DE MANTENIMIENTO
    // ========================================

    public const MAINTENANCE_MODE = [
        'code' => 'WA-034',
        'message' => 'El sistema está en mantenimiento. Intente más tarde.',
        'http_code' => 503,
    ];

    public const FEATURE_DISABLED = [
        'code' => 'WA-035',
        'message' => 'Esta funcionalidad está temporalmente deshabilitada.',
        'http_code' => 503,
    ];

    /**
     * Obtiene la información de error por código
     */
    public static function getErrorInfo(string $code): ?array
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        
        foreach ($constants as $constant) {
            if (is_array($constant) && isset($constant['code']) && $constant['code'] === $code) {
                return $constant;
            }
        }
        
        return null;
    }

    /**
     * Obtiene todos los códigos de error disponibles
     */
    public static function getAllErrorCodes(): array
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        
        return array_filter($constants, function($constant) {
            return is_array($constant) && isset($constant['code']);
        });
    }
}
