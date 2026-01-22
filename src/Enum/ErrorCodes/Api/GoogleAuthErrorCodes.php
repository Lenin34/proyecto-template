<?php

namespace App\Enum\ErrorCodes\Api;

class GoogleAuthErrorCodes
{
    public const TOKEN_NOT_PROVIDED = [
        'code' => 'GOOGLE_AUTH_TOKEN_NOT_PROVIDED',
        'message' => 'No se recibió el token de Google.',
    ];

    public const INVALID_TOKEN = [
        'code' => 'GOOGLE_AUTH_INVALID_TOKEN',
        'message' => 'El token de Google no es válido.',
    ];

    public const EMAIL_NOT_FOUND_IN_TOKEN = [
        'code' => 'GOOGLE_AUTH_EMAIL_NOT_FOUND',
        'message' => 'No se pudo obtener el correo del token de Google.',
    ];

    public const USER_NOT_REGISTERED = [
        'code' => 'GOOGLE_AUTH_USER_NOT_FOUND',
        'message' => 'El usuario no está registrado en el sistema.',
    ];
}
