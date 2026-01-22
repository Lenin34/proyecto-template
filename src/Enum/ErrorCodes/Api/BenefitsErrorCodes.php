<?php
namespace App\Enum\ErrorCodes\Api;

class BenefitsErrorCodes
{
    public const BENEFITS_COMPANY_NOT_FOUND_OR_INACTIVE = [
        'code' => 'BC-001',
        'message' => 'La empresa no existe o no está activa.',
        'http_code' => 404,
    ];

    public const BENEFITS_ACTIVE_NOT_FOUND = [
        'code' => 'BC-002',
        'message' => 'No se encontraron beneficios activos para la empresa.',
        'http_code' => 404,
    ];

    public const BENEFITS_INTERNAL_ERROR = [
        'code' => 'BC-999',
        'message' => 'Error interno al procesar la solicitud de beneficios.',
        'http_code' => 500,
    ];
}