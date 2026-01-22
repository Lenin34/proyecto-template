<?php
namespace App\Enum\ErrorCodes\Api;

class DeviceTokenErrorCodes
{
    public const DEVICE_TOKEN_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'DTC-001',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const DEVICE_TOKEN_ALREADY_EXISTS = [
        'code' => 'DTC-002',
        'message' => 'El token ya existe.',
        'http_code' => 400,
    ];
}
    