<?php
namespace App\Enum\ErrorCodes\Api;

class UserErrorCodes
{
    public const USER_NOT_FOUND = [
        'code' => 'UC-001',
        'message' => 'El usuario no existe.',
        'http_code' => 404,
    ];

    public const USER_INACTIVE = [
        'code' => 'UC-002',
        'message' => 'El usuario no está activo.',
        'http_code' => 400,
    ];

    public const USER_ALREADY_EXISTS = [
        'code' => 'UC-003',
        'message' => 'El usuario ya existe.',
        'http_code' => 409,
    ];

    public const USER_VALIDATION_ERROR = [
        'code' => 'UC-004',
        'message' => 'Error de validación en los datos del usuario.',
        'http_code' => 400,
    ];

    public const USER_UPDATE_FAILED = [
        'code' => 'UC-005',
        'message' => 'Error al actualizar el usuario.',
        'http_code' => 500,
    ];

    public const USER_DELETE_FAILED = [
        'code' => 'UC-006',
        'message' => 'Error al eliminar el usuario.',
        'http_code' => 500,
    ];
} 