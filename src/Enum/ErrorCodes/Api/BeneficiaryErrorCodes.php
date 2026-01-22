<?php
namespace App\Enum\ErrorCodes\Api;

class BeneficiaryErrorCodes 
{
    public const BENEFICIARY_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'BEC-001',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const BENEFICIARY_NO_ACTIVE_BENEFICIARIES_FOUND = [
        'code' => 'BEC-002',
        'message' => 'No se encontraron beneficiarios activos para el usuario.',
        'http_code' => 404,
    ];

    public const BENEFICIARY_CREATE_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'BEC-003',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const BENEFICIARY_CREATE_CURP_ALREADY_EXISTS = [
        'code' => 'BEC-004',
        'message' => 'El CURP ya está registrado.',
        'http_code' => 409,
    ];

    public const BENEFICIARY_CREATE_PHOTO_UPLOAD_FAILED = [
        'code' => 'BEC-005',
        'message' => 'Error al subir la foto del beneficiario.',
        'http_code' => 500,
    ];

    public const BENEFICIARY_UPDATE_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'BEC-006',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const BENEFICIARY_UPDATE_BENEFICIARY_NOT_FOUND_OR_INACTIVE = [
        'code' => 'BEC-007',
        'message' => 'El beneficiario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const BENEFICIARY_UPDATE_PHOTO_UPLOAD_FAILED = [
        'code' => 'BEC-008',
        'message' => 'Error al subir la foto del beneficiario.',
        'http_code' => 500,
    ];

    public const BENEFICIARY_DELETE_USER_NOT_FOUND_OR_INACTIVE = [
        'code' => 'BEC-009',
        'message' => 'El usuario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const BENEFICIARY_DELETE_BENEFICIARY_NOT_FOUND_OR_INACTIVE = [
        'code' => 'BEC-010',
        'message' => 'El beneficiario no existe o no está activo.',
        'http_code' => 404,
    ];

    public const BENEFICIARY_IMAGE_UPLOAD_FAILED = [
        'code' => 'BEC-011',
        'message' => 'Error al subir la imagen del beneficiario.',
        'http_code' => 500,
    ];
}