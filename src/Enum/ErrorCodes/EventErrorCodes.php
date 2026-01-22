<?php

namespace App\Enum\ErrorCodes;

/**
 * Códigos de error específicos para la gestión de eventos
 */
class EventErrorCodes
{
    // ========================================
    // ERRORES DE VALIDACIÓN
    // ========================================

    public const EVENT_VALIDATION_ERROR = [
        'code' => 'EV-001',
        'message' => 'Error de validación en los datos del evento.',
        'http_code' => 400,
    ];

    public const EVENT_MISSING_REQUIRED_FIELDS = [
        'code' => 'EV-002',
        'message' => 'Por favor, completa todos los campos obligatorios (título, descripción, fechas).',
        'http_code' => 400,
    ];

    public const EVENT_INVALID_DATE_RANGE = [
        'code' => 'EV-003',
        'message' => 'La fecha de inicio debe ser anterior a la fecha de fin.',
        'http_code' => 400,
    ];

    public const EVENT_INVALID_IMAGE_FORMAT = [
        'code' => 'EV-004',
        'message' => 'El formato de imagen no es válido. Solo se permiten JPG, PNG o GIF.',
        'http_code' => 400,
    ];

    public const EVENT_IMAGE_TOO_LARGE = [
        'code' => 'EV-005',
        'message' => 'La imagen es demasiado grande. El tamaño máximo permitido es 5MB.',
        'http_code' => 400,
    ];

    // ========================================
    // ERRORES DE EXISTENCIA
    // ========================================

    public const EVENT_NOT_FOUND = [
        'code' => 'EV-101',
        'message' => 'El evento no existe o no está disponible.',
        'http_code' => 404,
    ];

    public const EVENT_ALREADY_EXISTS = [
        'code' => 'EV-102',
        'message' => 'Ya existe un evento con ese título en las mismas fechas.',
        'http_code' => 409,
    ];

    // ========================================
    // ERRORES DE OPERACIONES
    // ========================================

    public const EVENT_CREATE_FAILED = [
        'code' => 'EV-201',
        'message' => 'No se pudo crear el evento. Por favor, inténtalo de nuevo.',
        'http_code' => 500,
    ];

    public const EVENT_UPDATE_FAILED = [
        'code' => 'EV-202',
        'message' => 'No se pudo actualizar el evento. Por favor, inténtalo de nuevo.',
        'http_code' => 500,
    ];

    public const EVENT_DELETE_FAILED = [
        'code' => 'EV-203',
        'message' => 'No se pudo eliminar el evento. Por favor, inténtalo de nuevo.',
        'http_code' => 500,
    ];

    public const EVENT_IMAGE_UPLOAD_FAILED = [
        'code' => 'EV-204',
        'message' => 'No se pudo cargar la imagen del evento.',
        'http_code' => 500,
    ];

    // ========================================
    // ERRORES DE RELACIONES
    // ========================================

    public const EVENT_COMPANY_NOT_FOUND = [
        'code' => 'EV-301',
        'message' => 'Una o más empresas seleccionadas no existen o no están activas.',
        'http_code' => 404,
    ];

    public const EVENT_NO_COMPANIES_SELECTED = [
        'code' => 'EV-302',
        'message' => 'Debes seleccionar al menos una empresa para el evento.',
        'http_code' => 400,
    ];

    public const EVENT_REGION_NOT_FOUND = [
        'code' => 'EV-303',
        'message' => 'La región seleccionada no existe o no está activa.',
        'http_code' => 404,
    ];

    // ========================================
    // ERRORES DE PERMISOS
    // ========================================

    public const EVENT_ACCESS_DENIED = [
        'code' => 'EV-401',
        'message' => 'No tienes permisos para acceder a este evento.',
        'http_code' => 403,
    ];

    public const EVENT_MODIFICATION_DENIED = [
        'code' => 'EV-402',
        'message' => 'No tienes permisos para modificar este evento.',
        'http_code' => 403,
    ];

    // ========================================
    // ERRORES DE ESTADO
    // ========================================

    public const EVENT_ALREADY_INACTIVE = [
        'code' => 'EV-501',
        'message' => 'El evento ya está inactivo.',
        'http_code' => 400,
    ];

    public const EVENT_CANNOT_MODIFY_PAST = [
        'code' => 'EV-502',
        'message' => 'No se puede modificar un evento que ya finalizó.',
        'http_code' => 400,
    ];

    /**
     * Obtiene la información de error por código
     */
    public static function getErrorInfo(string $code): ?array
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        
        foreach ($constants as $constant) {
            if (is_array($constant) && isset($constant['code']) && $constant['code'] === $code) {
                return $constant;
            }
        }
        
        return null;
    }
}

