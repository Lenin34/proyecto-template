<?php
namespace App\Enum\ErrorCodes;

class ImageUploadErrorCodes 
{
    public const IMAGE_UPLOAD_SERVICE_INVALID_FILE_TYPE = [
        'code' => 'IUS-001',
        'message' => 'El tipo de archivo no es válido. Solo se permiten imágenes JPEG, PNG y GIF.',
    ];

    public const IMAGE_UPLOAD_SERVICE_FILE_TOO_LARGE = [
        'code' => 'IUS-002',
        'message' => 'El tamaño del archivo excede el límite permitido.',
    ];

    public const IMAGE_UPLOAD_SERVICE_DIRECTORY_CREATION_FAILED = [
        'code' => 'IUS-003',
        'message' => 'Error al crear el directorio de carga.',
    ];

    public const IMAGE_UPLOAD_SERVICE_UPLOAD_FAILED = [
        'code' => 'IUS-004',
        'message' => 'Error al subir la imagen.',
    ];

    public const IMAGE_UPLOAD_SERVICE_IMAGE_COMPRESSION_FAILED = [
        'code' => 'IUS-005',
        'message' => 'Error al comprimir la imagen.',
    ]; 

    public const IMAGE_UPLOAD_SERVICE_JPEG_COMPRESSION_FAILED = [
        'code' => 'IUS-006',
        'message' => 'Error al comprimir la imagen JPEG.',
    ];

    public const IMAGE_UPLOAD_SERVICE_PNG_COMPRESSION_FAILED = [
        'code' => 'IUS-007',
        'message' => 'Error al comprimir la imagen PNG.',
    ];

    public const IMAGE_UPLOAD_SERVICE_GIF_COMPRESSION_FAILED = [
        'code' => 'IUS-008',
        'message' => 'Error al comprimir la imagen GIF.',
    ];

    public const IMAGE_UPLOAD_SERVICE_FILE_TYPE_NOT_SUPPORTED = [
        'code' => 'IUS-009',
        'message' => 'El tipo de archivo no es compatible.',
    ];

    public const IMAGE_UPLOAD_SERVICE_COMPRESSION_FAILED = [
        'code' => 'IUS-010',
        'message' => 'Error al comprimir la imagen.',
    ]; 
}