<?php
namespace App\Enum\ErrorCodes;

class PhoneVerificationErrorCodes
{
    public const PHONE_VERIFICATION_PHONE_NUMBER_NOT_FOUND = [
        'code' => 'PVS-001',
        'message' => 'Numero de teléfono del usuario no esta registrado.',
    ];


    public const PHONE_VERIFICATION_MESSAGE_NOT_SENT = [
        'code' => 'PVS-002',
        'message' => 'El mensaje no se pudo enviar a WhatsApp.',
    ];


    public const PHONE_VERIFICATION_ERROR = [
        'code' => 'PVS-003',
        'message' => 'Error al enviar el mensaje de verificación.',
    ];


    public const PHONE_VERIFICATION_CODE_MISMATCH = [
        'code' => 'PVS-004',
        'message' => 'El código de verificación no coincide.',
    ];


    public const PHONE_VERIFICATION_VERIFICATION_ERROR = [
        'code' => 'PVS-005',
        'message' => 'Error al verificar el código de verificación.',
    ];
}
