<?php
namespace App\Enum\ErrorCodes\Api;

class ProfileErrorCodes
{
    public const PROFILE_OBTAIN_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'PC-001',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const PROFILE_UPDATE_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'PC-002',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const PROFILE_UPDATE_COMPANY_NOT_FOUND_OR_INACTIVE = [
        'code' => 'PC-003',
        'message' => 'La empresa no existe o no está activa.',
        'http_code' => 404,
    ];

    public const PROFILE_UPDATE_PHOTO_UPLOAD_FAILED = [
        'code' => 'PC-004',
        'message' => 'Error al subir la foto del perfil.',
        'http_code' => 500,
    ];

    public const PROFILE_UPDATE_IMAGE_UPLOAD_FAILED = [
        'code' => 'PC-005',
        'message' => 'Error al subir la imagen del perfil.',
        'http_code' => 500,
    ];

    public const PROFILE_UPDATE_EMAIL_EXISTS = [
        'code' => 'PC-006',
        'message' => 'El correo ya está en uso.',
        'http_code' => 409,
    ];
}