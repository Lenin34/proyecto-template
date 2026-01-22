<?php

namespace App\Enum\ErrorCodes\Api;

class EmailVerificationErrorCodes
{
    public const USER_NOT_FOUND = [
        'code' => 'USER_NOT_FOUND',
        'message' => 'No se encontró el usuario.',
        'httpCode' => 404,
    ];

    public const USER_EMAIL_NOT_FOUND = [
        'code' => 'USER_EMAIL_NOT_FOUND',
        'message' => 'El usuario no tiene un correo electrónico registrado.',
        'httpCode' => 400,
    ];

    public const MISSING_VERIFICATION_CODE = [
        'code' => 'MISSING_VERIFICATION_CODE',
        'message' => 'No se proporcionó el código de verificación.',
        'httpCode' => 400,
    ];

    public const INVALID_VERIFICATION_CODE = [
        'code' => 'INVALID_VERIFICATION_CODE',
        'message' => 'El código de verificación es incorrecto.',
        'httpCode' => 400,
    ];

    public const EMAIL_SENDING_FAILED = [
        'code' => 'EMAIL_SENDING_FAILED',
        'message' => 'No se pudo enviar el correo electrónico de verificación.',
        'httpCode' => 500,
    ];
}
