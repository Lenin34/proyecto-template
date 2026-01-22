<?php
namespace App\Enum\ErrorCodes\Api;

class AuthErrorCodes 
{
    public const AUTH_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'AC-001',
        'message' => 'Usuario no encontrado o inactivo.',
        'http_code' => 404,
    ];

    public const AUTH_COMPANY_NOT_FOUND_OR_INACTIVE = [
        'code' => 'AC-002',
        'message' => 'La empresa no existe o no está activa.',
        'http_code' => 404,
    ];

    public const AUTH_PHONE_VERIFICATION_FAILED = [
        'code' => 'AC-003',
        'message' => 'Falló el proceso de verificación del teléfono.',
        'http_code' => 500,
    ];

    public const AUTH_USER_INCORRECT_PASSWORD = [
        'code' => 'AC-004',
        'message' => 'Contraseña incorrecta.',
        'http_code' => 401,
    ];

    public const AUTH_EMAIL_ALREADY_REGISTERED = [
        'code' => 'AC-005',
        'message' => 'El correo electrónico ya está registrado.',
        'http_code' => 409,
    ];

    public const AUTH_PHONE_ALREADY_REGISTERED = [
        'code' => 'AC-006',
        'message' => 'El número de teléfono ya está registrado.',
        'http_code' => 409,
    ];

    public const AUTH_REGISTRATION_FAILED = [
        'code' => 'AC-007',
        'message' => 'Error durante el proceso de registro.',
        'http_code' => 500,
    ];

    public const AUTH_MISSING_PHONE_NUMBER = [
        'code' => 'AC-008',
        'message' => 'El número de teléfono es requerido.',
        'http_code' => 400,
    ];

    public const AUTH_SMS_SENDING_FAILED = [
        'code' => 'AC-009',
        'message' => 'Error al enviar el código de verificación por SMS.',
        'http_code' => 500,
    ];

    public const AUTH_INTERNAL_ERROR = [
        'code' => 'AC-010',
        'message' => 'Error interno del servidor.',
        'http_code' => 500,
    ];

    // ========================================
    // VALIDACIÓN DE DATOS
    // ========================================

    public const AUTH_INVALID_EMAIL_FORMAT = [
        'code' => 'AC-011',
        'message' => 'El formato del correo electrónico es inválido.',
        'http_code' => 400,
    ];

    public const AUTH_WEAK_PASSWORD = [
        'code' => 'AC-012',
        'message' => 'La contraseña no cumple con los requisitos de seguridad.',
        'http_code' => 400,
    ];

    public const AUTH_INVALID_PHONE_FORMAT = [
        'code' => 'AC-013',
        'message' => 'El formato del número de teléfono es inválido.',
        'http_code' => 400,
    ];

    public const AUTH_INVALID_CURP_FORMAT = [
        'code' => 'AC-014',
        'message' => 'El formato del CURP es inválido.',
        'http_code' => 400,
    ];

    // ========================================
    // LÍMITES Y RATE LIMITING
    // ========================================

    public const AUTH_TOO_MANY_LOGIN_ATTEMPTS = [
        'code' => 'AC-015',
        'message' => 'Demasiados intentos de inicio de sesión. Intente más tarde.',
        'http_code' => 429,
    ];

    public const AUTH_SMS_RATE_LIMIT_EXCEEDED = [
        'code' => 'AC-016',
        'message' => 'Se ha excedido el límite de SMS por hora.',
        'http_code' => 429,
    ];

    public const AUTH_EMAIL_RATE_LIMIT_EXCEEDED = [
        'code' => 'AC-017',
        'message' => 'Se ha excedido el límite de emails por hora.',
        'http_code' => 429,
    ];

    // ========================================
    // ESTADOS DE USUARIO
    // ========================================

    public const AUTH_USER_SUSPENDED = [
        'code' => 'AC-018',
        'message' => 'La cuenta de usuario está suspendida.',
        'http_code' => 403,
    ];

    public const AUTH_USER_NOT_VERIFIED = [
        'code' => 'AC-019',
        'message' => 'El usuario no ha verificado su cuenta.',
        'http_code' => 403,
    ];

    public const AUTH_ACCOUNT_LOCKED = [
        'code' => 'AC-020',
        'message' => 'La cuenta está bloqueada por seguridad.',
        'http_code' => 423,
    ];

    public const AUTH_USER_DELETED = [
        'code' => 'AC-021',
        'message' => 'La cuenta de usuario ha sido eliminada.',
        'http_code' => 410,
    ];

    // ========================================
    // TOKEN Y SESIÓN
    // ========================================

    public const AUTH_TOKEN_EXPIRED = [
        'code' => 'AC-022',
        'message' => 'El token de autenticación ha expirado.',
        'http_code' => 401,
    ];

    public const AUTH_TOKEN_INVALID = [
        'code' => 'AC-023',
        'message' => 'El token de autenticación es inválido.',
        'http_code' => 401,
    ];

    public const AUTH_SESSION_EXPIRED = [
        'code' => 'AC-024',
        'message' => 'La sesión ha expirado.',
        'http_code' => 401,
    ];

    public const AUTH_UNAUTHORIZED_ACCESS = [
        'code' => 'AC-025',
        'message' => 'Acceso no autorizado.',
        'http_code' => 401,
    ];

    // ========================================
    // VERIFICACIÓN
    // ========================================

    public const AUTH_VERIFICATION_CODE_EXPIRED = [
        'code' => 'AC-026',
        'message' => 'El código de verificación ha expirado.',
        'http_code' => 400,
    ];

    public const AUTH_VERIFICATION_CODE_INVALID = [
        'code' => 'AC-027',
        'message' => 'El código de verificación es inválido.',
        'http_code' => 400,
    ];

    public const AUTH_MAX_VERIFICATION_ATTEMPTS = [
        'code' => 'AC-028',
        'message' => 'Se ha excedido el número máximo de intentos de verificación.',
        'http_code' => 429,
    ];

    public const AUTH_VERIFICATION_ALREADY_COMPLETED = [
        'code' => 'AC-029',
        'message' => 'La verificación ya ha sido completada.',
        'http_code' => 400,
    ];

    // ========================================
    // CONTRASEÑAS
    // ========================================

    public const AUTH_PASSWORD_RECENTLY_USED = [
        'code' => 'AC-030',
        'message' => 'No puede usar una contraseña utilizada recientemente.',
        'http_code' => 400,
    ];

    public const AUTH_PASSWORD_EXPIRED = [
        'code' => 'AC-031',
        'message' => 'La contraseña ha expirado y debe ser cambiada.',
        'http_code' => 403,
    ];

    public const AUTH_CURRENT_PASSWORD_INCORRECT = [
        'code' => 'AC-032',
        'message' => 'La contraseña actual es incorrecta.',
        'http_code' => 400,
    ];

    // ========================================
    // EMPRESA Y TENANT
    // ========================================

    public const AUTH_TENANT_NOT_FOUND = [
        'code' => 'AC-033',
        'message' => 'El tenant especificado no existe.',
        'http_code' => 404,
    ];

    public const AUTH_COMPANY_SUSPENDED = [
        'code' => 'AC-034',
        'message' => 'La empresa está suspendida.',
        'http_code' => 403,
    ];

    public const AUTH_USER_NOT_BELONGS_TO_COMPANY = [
        'code' => 'AC-035',
        'message' => 'El usuario no pertenece a esta empresa.',
        'http_code' => 403,
    ];
}