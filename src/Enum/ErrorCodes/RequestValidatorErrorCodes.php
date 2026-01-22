<?php
namespace App\Enum\ErrorCodes;

class RequestValidatorErrorCodes
{
    public const REQUEST_VALIDATOR_INVALID_DATA_FORMAT = [
        'code' => 'RVS-001',
        'message' => 'El formato de los datos no es válido.',
        'http_code' => 400,
    ];

    public const REQUEST_VALIDATOR_VALIDATION_ERROR = [
        'code' => 'RVS-002',
        'message' => 'Errores de validación',
        'http_code' => 400,
    ];
}