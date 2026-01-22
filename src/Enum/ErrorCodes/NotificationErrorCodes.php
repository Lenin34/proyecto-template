<?php
namespace App\Enum\ErrorCodes;

class NotificationErrorCodes {
    public const NOTIFICATION_NOT_FOUND = [
        'code' => 'NC-000',
        'message' => 'La notificación no existe.',
    ];

    public const NOTIFICATION_NOT_ACTIVE = [
        'code' => 'NC-001',
        'message' => 'La notificación no está activa.',
    ];

    public const NOTIFICATION_NO_REGION = [
        'code' => 'NC-002',
        'message' => 'La notificación no tiene región asociada.',
    ];

    public const NOTIFICATION_NO_USERS = [
        'code' => 'NC-003',
        'message' => 'No hay usuarios asociados a las regiones.',
    ];

    public const NOTIFICATION_NO_USERS_TOKENS = [
        'code' => 'NC-004',
        'message' => 'No hay tokens de dispositivos asociados a los usuarios en estas regiones.',
    ];

    public const NOTIFICATION_SEND_FAILED = [
        'code' => 'NC-005',
        'message' => 'No se pudo enviar la notificación a algunos dispositivos.',
    ];
}