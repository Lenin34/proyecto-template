<?php
namespace App\Enum\ErrorCodes;

class PushNotificationErrorCodes
{
    public const PUSH_NOTIFICATION_NO_USERS_TOKENS = [
        'code' => 'PNS-001',
        'message' => 'No hay tokens de dispositivos a enviar la notificación.',
    ]; 

    public const PUSH_NOTIFICATION_SOME_SEND_FAILED = [
        'code' => 'PNS-002',
        'message' => 'No se pudo enviar la notificación a algunos dispositivos.',
    ];

    public const PUSH_NOTIFICATION_SEND_FAILED = [
        'code' => 'PNS-003',
        'message' => 'Error al enviar la notificación en lote.',
    ];
}