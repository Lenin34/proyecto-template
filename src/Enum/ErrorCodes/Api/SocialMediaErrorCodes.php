<?php
namespace App\Enum\ErrorCodes\Api;

class SocialMediaErrorCodes
{
    public const SOCIAL_MEDIA_INVALID_DATE_RANGE = [
        'code' => 'SMC-001',
        'message' => 'Ambas fechas, start_date y end_date, deben estar presentes o ausentes.',
        'http_code' => 400,
    ];

    public const SOCIAL_MEDIA_COMPANY_NOT_FOUND_OR_INACTIVE = [
        'code' => 'SMC-002',
        'message' => 'La empresa no existe o no está activa.',
        'http_code' => 404,
    ];

    public const SOCIAL_MEDIA_POST_NOT_FOUND = [
        'code' => 'SMC-003',
        'message' => 'No se encontraron posts de redes sociales en el rango de fechas especificado.',
        'http_code' => 404,
    ];
}