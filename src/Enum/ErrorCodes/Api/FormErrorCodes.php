<?php
namespace App\Enum\ErrorCodes\Api;

class FormErrorCodes
{
    // Errores de Formulario (E)
    public const FORM_INVALID_STRUCTURE = [
        'code' => 'E1001',
        'message' => 'Estructura de formulario inválida',
        'http_code' => 400,
    ];

    public const FORM_MISSING_REQUIRED_FIELD = [
        'code' => 'E1002',
        'message' => 'Campo requerido faltante',
        'http_code' => 400,
    ];

    public const FORM_UNSUPPORTED_FIELD_TYPE = [
        'code' => 'E1003',
        'message' => 'Tipo de campo no soportado',
        'http_code' => 400,
    ];

    public const FORM_FIELD_VALIDATION_FAILED = [
        'code' => 'E1004',
        'message' => 'Validación de campo fallida',
        'http_code' => 400,
    ];

    public const FORM_FIELDS_LIMIT_EXCEEDED = [
        'code' => 'E1005',
        'message' => 'Límite de campos excedido',
        'http_code' => 400,
    ];

    public const FORM_INVALID_FIELD_OPTIONS = [
        'code' => 'E1006',
        'message' => 'Opciones de campo inválidas',
        'http_code' => 400,
    ];

    public const FORM_INVALID_FIELD_ORDER = [
        'code' => 'E1007',
        'message' => 'Orden de campos inválido',
        'http_code' => 400,
    ];

    public const FORM_INVALID_IMAGE = [
        'code' => 'E1008',
        'message' => 'Imagen de formulario inválida o muy grande',
        'http_code' => 400,
    ];

    public const FORM_DESCRIPTION_TOO_LONG = [
        'code' => 'E1009',
        'message' => 'Descripción de formulario demasiado larga',
        'http_code' => 400,
    ];

    public const FORM_DUPLICATE = [
        'code' => 'E1010',
        'message' => 'Formulario duplicado',
        'http_code' => 409,
    ];

    // Errores de Usuario (U)
    public const USER_UNAUTHORIZED_ACCESS = [
        'code' => 'U2001',
        'message' => 'Acceso no autorizado',
        'http_code' => 401,
    ];

    public const USER_INSUFFICIENT_PERMISSIONS = [
        'code' => 'U2002',
        'message' => 'Permisos insuficientes',
        'http_code' => 403,
    ];

    public const USER_SESSION_EXPIRED = [
        'code' => 'U2003',
        'message' => 'Sesión expirada',
        'http_code' => 401,
    ];

    public const USER_NOT_FOUND = [
        'code' => 'U2004',
        'message' => 'Usuario no encontrado',
        'http_code' => 404,
    ];

    public const USER_ACTION_NOT_ALLOWED = [
        'code' => 'U2005',
        'message' => 'Acción no permitida para el rol actual',
        'http_code' => 403,
    ];

    public const USER_SUBMISSIONS_LIMIT_REACHED = [
        'code' => 'U2006',
        'message' => 'Límite de envíos alcanzado',
        'http_code' => 429,
    ];

    public const USER_COMPANY_UNAUTHORIZED = [
        'code' => 'U2007',
        'message' => 'Empresa no autorizada para este formulario',
        'http_code' => 403,
    ];

    // Errores de Formulario/Entidad (F)
    public const ENTITY_FORM_NOT_FOUND = [
        'code' => 'F3001',
        'message' => 'Formulario no encontrado',
        'http_code' => 404,
    ];

    public const ENTITY_FIELD_NOT_FOUND = [
        'code' => 'F3002',
        'message' => 'Campo no encontrado',
        'http_code' => 404,
    ];

    public const ENTITY_SUBMISSION_NOT_FOUND = [
        'code' => 'F3003',
        'message' => 'Envío no encontrado',
        'http_code' => 404,
    ];

    public const ENTITY_FORM_INACTIVE = [
        'code' => 'F3004',
        'message' => 'Formulario inactivo',
        'http_code' => 400,
    ];

    public const ENTITY_INVALID_FIELD_RESPONSE = [
        'code' => 'F3005',
        'message' => 'Respuesta de campo inválida',
        'http_code' => 400,
    ];

    public const ENTITY_INVALID_ATTACHMENT = [
        'code' => 'F3006',
        'message' => 'Archivo adjunto inválido o muy grande',
        'http_code' => 400,
    ];

    public const ENTITY_UNSUPPORTED_FILE_FORMAT = [
        'code' => 'F3007',
        'message' => 'Formato de archivo no soportado',
        'http_code' => 400,
    ];

    public const ENTITY_DATE_OUT_OF_RANGE = [
        'code' => 'F3008',
        'message' => 'Fecha fuera de rango permitido',
        'http_code' => 400,
    ];

    public const ENTITY_INVALID_OPTION_SELECTED = [
        'code' => 'F3009',
        'message' => 'Opción seleccionada inválida',
        'http_code' => 400,
    ];

    // Errores de Sistema (S)
    public const SYSTEM_INTERNAL_ERROR = [
        'code' => 'S4001',
        'message' => 'Error interno del servidor',
        'http_code' => 500,
    ];

    public const SYSTEM_DATABASE_UNAVAILABLE = [
        'code' => 'S4002',
        'message' => 'Base de datos no disponible',
        'http_code' => 503,
    ];

    public const SYSTEM_FILE_SAVE_ERROR = [
        'code' => 'S4003',
        'message' => 'Error al guardar archivo',
        'http_code' => 500,
    ];

    public const SYSTEM_CONNECTION_ERROR = [
        'code' => 'S4004',
        'message' => 'Error de conexión',
        'http_code' => 503,
    ];

    public const SYSTEM_STORAGE_SERVICE_UNAVAILABLE = [
        'code' => 'S4005',
        'message' => 'Servicio de almacenamiento no disponible',
        'http_code' => 503,
    ];

    public const SYSTEM_TENANT_CONFIGURATION_ERROR = [
        'code' => 'S4006',
        'message' => 'Error en la configuración del tenant',
        'http_code' => 500,
    ];

    public const SYSTEM_STORAGE_LIMIT_EXCEEDED = [
        'code' => 'S4007',
        'message' => 'Límite de almacenamiento excedido',
        'http_code' => 507,
    ];
}