<?php
namespace App\Enum\ErrorCodes\Api;

class EventsErrorCodes
{
    public const EVENTS_INVALID_DATE_RANGE = [
        'code' => 'EC-001',
        'message' => 'Ambas fechas, start_date y end_date, deben estar presentes o ausentes.',
        'http_code' => 400,
    ];

    public const EVENTS_COMPANY_NOT_FOUND_OR_INACTIVE = [
        'code' => 'EC-002',
        'message' => 'La empresa no existe o no está activa.',
        'http_code' => 404,
    ];

    public const EVENTS_NO_EVENTS_FOUND = [
        'code' => 'EC-003',
        'message' => 'No se encontraron eventos en el rango de fechas especificado.',
        'http_code' => 404,
    ];
}