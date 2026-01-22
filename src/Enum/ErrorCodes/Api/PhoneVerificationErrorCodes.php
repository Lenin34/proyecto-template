<?php
namespace App\Enum\ErrorCodes\Api;

class PhoneVerificationErrorCodes
{
    public const PHONE_VERIFICATION_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'PVC-001',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const PHONE_VERIFICATION_USER_ALREADY_VERIFIED = [
        'code' => 'PVC-002',
        'message' => 'El usuario ya está verificado.',
        'http_code' => 400,
    ];

    public const PHONE_VERIFICATION_USER_NO_VERIFICATION_CODE = [
        'code' => 'PVC-003',
        'message' => 'El usuario no tiene un código de verificación.',
        'http_code' => 400,
    ];

    public const PHONE_VERIFICATION_CODE_INCORRECT = [
        'code' => 'PVC-004',
        'message' => 'El código de verificación es incorrecto.',
        'http_code' => 400,
    ];

    public const PHONE_VERIFICATION_VALIDATION_ERROR = [
        'code' => 'PVC-005',
        'message' => 'Error de validación en los datos enviados.',
        'http_code' => 400,
    ];

    public const PHONE_VERIFICATION_DATABASE_ERROR = [
        'code' => 'PVC-006',
        'message' => 'Error en la base de datos durante la verificación.',
        'http_code' => 500,
    ];

    public const PHONE_VERIFICATION_INTERNAL_ERROR = [
        'code' => 'PVC-007',
        'message' => 'Error interno del servidor durante la verificación.',
        'http_code' => 500,
    ];

    public const PHONE_VERIFICATION_REQUEST_PROCESSING_ERROR = [
        'code' => 'PVC-008',
        'message' => 'Error al procesar la solicitud de verificación.',
        'http_code' => 500,
    ];

    // Códigos para verificación de número de teléfono
    public const PHONE_NUMBER_NOT_PROVIDED = [
        'code' => 'PVC-009',
        'message' => 'El número de teléfono es obligatorio.',
        'http_code' => 400,
    ];

    public const PHONE_NUMBER_INVALID_FORMAT = [
        'code' => 'PVC-010',
        'message' => 'El formato del número de teléfono no es válido.',
        'http_code' => 400,
    ];

    public const PHONE_NUMBER_NOT_FOUND = [
        'code' => 'PVC-011',
        'message' => 'El número de teléfono no está registrado en el sistema.',
        'http_code' => 404,
    ];

    public const PHONE_NUMBER_USER_INACTIVE = [
        'code' => 'PVC-012',
        'message' => 'El usuario asociado al teléfono no está activo.',
        'http_code' => 400,
    ];

    public const PHONE_NUMBER_USER_ALREADY_VERIFIED = [
        'code' => 'PVC-013',
        'message' => 'El usuario ya está verificado.',
        'http_code' => 400,
    ];

    public const PHONE_NUMBER_CODE_GENERATION_FAILED = [
        'code' => 'PVC-014',
        'message' => 'Error al generar el código de verificación.',
        'http_code' => 500,
    ];

    public const PHONE_NUMBER_SMS_SENDING_FAILED = [
        'code' => 'PVC-015',
        'message' => 'Error al enviar el código de verificación por SMS.',
        'http_code' => 500,
    ];

    public const PHONE_VERIFICATION_RESEND_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'PVC-005',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const PHONE_VERIFICATION_RESEND_USER_ALREADY_VERIFIED = [
        'code' => 'PVC-006',
        'message' => 'El usuario ya está verificado.',
        'http_code' => 400,
    ];

    public const PHONE_VERIFICATION_RESEND_CODE_FAILED = [
        'code' => 'PVC-007',
        'message' => 'Error al enviar el código de verificación.',
        'http_code' => 500,
    ];
}