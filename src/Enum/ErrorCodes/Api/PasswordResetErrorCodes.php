<?php

namespace App\Enum\ErrorCodes\Api;

class PasswordResetErrorCodes
{
    public const EMAIL_NOT_PROVIDED = [
        'code' => 'PR-001',
        'message' => 'El email es obligatorio.',
        'http_code' => 400,
    ];

    public const USER_NOT_FOUND_FOR_EMAIL = [
        'code' => 'PR-002',
        'message' => 'No se encontró un usuario activo con este email.',
        'http_code' => 404,
    ];

    public const EMAIL_SENDING_FAILED = [
        'code' => 'PR-003',
        'message' => 'Error al enviar el código de verificación por email.',
        'http_code' => 500,
    ];

    public const USER_NOT_FOUND = [
        'code' => 'PR-004',
        'message' => 'No se encontró el usuario.',
        'http_code' => 404,
    ];

    public const PHONE_NUMBER_NOT_PROVIDED = [
        'code' => 'PR-005',
        'message' => 'El número de teléfono es obligatorio.',
        'http_code' => 400,
    ];

    public const SMS_SENDING_FAILED = [
        'code' => 'PR-006',
        'message' => 'Error al enviar el código de verificación por SMS.',
        'http_code' => 500,
    ];

    public const INTERNAL_ERROR = [
        'code' => 'PR-007',
        'message' => 'Error interno del servidor.',
        'http_code' => 500,
    ];
}
