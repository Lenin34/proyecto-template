<?php
namespace App\Enum\ErrorCodes\Api;

class HistoryErrorCodes
{
    public const HISTORY_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'HC-001',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const HISTORY_EVENT_NOT_FOUND = [
        'code' => 'HC-002',
        'message' => 'El evento no existe.',
        'http_code' => 404,
    ];
}