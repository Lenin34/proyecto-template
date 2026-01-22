<?php

namespace App\Enum\ErrorCodes;

class WorkScheduleErrorCodes
{
    // Errores de validación de campos obligatorios
    public const MISSING_REQUIRED_FIELDS = [
        'code' => 'WS-001',
        'message' => 'Por favor, completa todos los campos obligatorios (nombre, hora de inicio y hora de fin)',
        'http_code' => 400,
    ];

    // Errores de duplicación
    public const DUPLICATE_SCHEDULE_NAME = [
        'code' => 'WS-002',
        'message' => 'Ya existe un horario con ese nombre. Por favor, elige un nombre diferente',
        'http_code' => 409,
    ];

    // Errores de dependencias
    public const DEPENDENCY_CONSTRAINT_ERROR = [
        'code' => 'WS-003',
        'message' => 'No se puede completar la operación debido a dependencias existentes',
        'http_code' => 400,
    ];

    // Errores de conexión
    public const DATABASE_CONNECTION_ERROR = [
        'code' => 'WS-004',
        'message' => 'Error de conexión con la base de datos. Inténtalo de nuevo en unos momentos',
        'http_code' => 500,
    ];

    // Errores de validación de horarios
    public const INVALID_TIME_FORMAT = [
        'code' => 'WS-005',
        'message' => 'Formato de hora inválido. Use el formato HH:MM (24 horas)',
        'http_code' => 400,
    ];

    public const INVALID_TIME_RANGE = [
        'code' => 'WS-006',
        'message' => 'La hora de fin debe ser posterior a la hora de inicio',
        'http_code' => 400,
    ];

    public const TIME_VALIDATION_ERROR = [
        'code' => 'WS-007',
        'message' => 'Las horas proporcionadas no son válidas',
        'http_code' => 400,
    ];

    // Errores de empresa
    public const COMPANY_NOT_FOUND = [
        'code' => 'WS-008',
        'message' => 'La empresa seleccionada no existe o no está activa',
        'http_code' => 404,
    ];

    public const COMPANY_ACCESS_DENIED = [
        'code' => 'WS-009',
        'message' => 'No tienes permisos para asignar horarios a esta empresa',
        'http_code' => 403,
    ];

    // Errores de días de trabajo
    public const NO_WORKING_DAYS_SELECTED = [
        'code' => 'WS-010',
        'message' => 'Debe seleccionar al menos un día de trabajo',
        'http_code' => 400,
    ];

    public const INVALID_WORKING_DAY = [
        'code' => 'WS-011',
        'message' => 'Día de trabajo inválido. Los días deben estar entre 1 (Lunes) y 7 (Domingo)',
        'http_code' => 400,
    ];

    // Errores de descansos
    public const INVALID_BREAK_TIME_RANGE = [
        'code' => 'WS-012',
        'message' => 'Los horarios de descanso deben estar dentro del horario de trabajo',
        'http_code' => 400,
    ];

    public const OVERLAPPING_BREAKS = [
        'code' => 'WS-013',
        'message' => 'Los períodos de descanso no pueden solaparse entre sí',
        'http_code' => 400,
    ];

    public const INVALID_BREAK_DURATION = [
        'code' => 'WS-014',
        'message' => 'La duración del descanso debe ser mayor a 0 minutos',
        'http_code' => 400,
    ];

    // Errores de horario no encontrado
    public const SCHEDULE_NOT_FOUND = [
        'code' => 'WS-015',
        'message' => 'El horario solicitado no existe',
        'http_code' => 404,
    ];

    public const SCHEDULE_INACTIVE = [
        'code' => 'WS-016',
        'message' => 'El horario no está activo',
        'http_code' => 400,
    ];

    // Errores de eliminación
    public const SCHEDULE_HAS_ASSIGNMENTS = [
        'code' => 'WS-017',
        'message' => 'No se puede eliminar el horario porque tiene empleados asignados',
        'http_code' => 400,
    ];

    public const SCHEDULE_DELETE_FAILED = [
        'code' => 'WS-018',
        'message' => 'Error al eliminar el horario',
        'http_code' => 500,
    ];

    // Errores de actualización
    public const SCHEDULE_UPDATE_FAILED = [
        'code' => 'WS-019',
        'message' => 'Error al actualizar el horario',
        'http_code' => 500,
    ];

    // Errores de tenant/multi-tenancy
    public const TENANT_NOT_CONFIGURED = [
        'code' => 'WS-020',
        'message' => 'EntityManager no disponible. Verifique la configuración del tenant',
        'http_code' => 500,
    ];

    // Errores generales
    public const GENERAL_ERROR = [
        'code' => 'WS-999',
        'message' => 'Ocurrió un error inesperado. Por favor, verifica los datos e inténtalo de nuevo',
        'http_code' => 500,
    ];

    public const VALIDATION_ERROR = [
        'code' => 'WS-998',
        'message' => 'Error de validación en los datos proporcionados',
        'http_code' => 400,
    ];

    /**
     * Obtiene todos los códigos de error como array asociativo
     */
    public static function getAllErrorCodes(): array
    {
        $reflection = new \ReflectionClass(self::class);
        $constants = $reflection->getConstants();
        
        $errorCodes = [];
        foreach ($constants as $name => $value) {
            if (is_array($value) && isset($value['code'])) {
                $errorCodes[$value['code']] = $value;
            }
        }
        
        return $errorCodes;
    }

    /**
     * Obtiene un código de error por su código
     */
    public static function getErrorByCode(string $code): ?array
    {
        $allErrors = self::getAllErrorCodes();
        return $allErrors[$code] ?? null;
    }
}
