<?php

namespace App\Enum\ErrorCodes;

class TenantLogoErrorCodes
{
    // Errores generales del servicio
    public const TENANT_LOGO_SERVICE_TENANT_NOT_FOUND = 'TENANT_LOGO_SERVICE_TENANT_NOT_FOUND';
    public const TENANT_LOGO_SERVICE_UPLOAD_FAILED = 'TENANT_LOGO_SERVICE_UPLOAD_FAILED';
    public const TENANT_LOGO_SERVICE_DELETE_FAILED = 'TENANT_LOGO_SERVICE_DELETE_FAILED';
    public const TENANT_LOGO_SERVICE_UPDATE_FAILED = 'TENANT_LOGO_SERVICE_UPDATE_FAILED';
    
    // Errores de validación
    public const TENANT_LOGO_SERVICE_INVALID_TENANT_DOMAIN = 'TENANT_LOGO_SERVICE_INVALID_TENANT_DOMAIN';
    public const TENANT_LOGO_SERVICE_TENANT_INACTIVE = 'TENANT_LOGO_SERVICE_TENANT_INACTIVE';
    
    // Errores de archivo
    public const TENANT_LOGO_SERVICE_FILE_NOT_FOUND = 'TENANT_LOGO_SERVICE_FILE_NOT_FOUND';
    public const TENANT_LOGO_SERVICE_FILE_DELETE_FAILED = 'TENANT_LOGO_SERVICE_FILE_DELETE_FAILED';
    public const TENANT_LOGO_SERVICE_INVALID_FILE_PATH = 'TENANT_LOGO_SERVICE_INVALID_FILE_PATH';
    
    // Errores de base de datos
    public const TENANT_LOGO_SERVICE_DATABASE_ERROR = 'TENANT_LOGO_SERVICE_DATABASE_ERROR';
    public const TENANT_LOGO_SERVICE_ENTITY_NOT_FOUND = 'TENANT_LOGO_SERVICE_ENTITY_NOT_FOUND';
    
    // Errores de configuración
    public const TENANT_LOGO_SERVICE_UPLOADS_DIRECTORY_NOT_FOUND = 'TENANT_LOGO_SERVICE_UPLOADS_DIRECTORY_NOT_FOUND';
    public const TENANT_LOGO_SERVICE_PERMISSIONS_ERROR = 'TENANT_LOGO_SERVICE_PERMISSIONS_ERROR';

    /**
     * Obtiene todos los códigos de error disponibles
     */
    public static function getAllCodes(): array
    {
        return [
            self::TENANT_LOGO_SERVICE_TENANT_NOT_FOUND,
            self::TENANT_LOGO_SERVICE_UPLOAD_FAILED,
            self::TENANT_LOGO_SERVICE_DELETE_FAILED,
            self::TENANT_LOGO_SERVICE_UPDATE_FAILED,
            self::TENANT_LOGO_SERVICE_INVALID_TENANT_DOMAIN,
            self::TENANT_LOGO_SERVICE_TENANT_INACTIVE,
            self::TENANT_LOGO_SERVICE_FILE_NOT_FOUND,
            self::TENANT_LOGO_SERVICE_FILE_DELETE_FAILED,
            self::TENANT_LOGO_SERVICE_INVALID_FILE_PATH,
            self::TENANT_LOGO_SERVICE_DATABASE_ERROR,
            self::TENANT_LOGO_SERVICE_ENTITY_NOT_FOUND,
            self::TENANT_LOGO_SERVICE_UPLOADS_DIRECTORY_NOT_FOUND,
            self::TENANT_LOGO_SERVICE_PERMISSIONS_ERROR,
        ];
    }

    /**
     * Obtiene mensajes descriptivos para los códigos de error
     */
    public static function getErrorMessages(): array
    {
        return [
            self::TENANT_LOGO_SERVICE_TENANT_NOT_FOUND => 'El tenant especificado no fue encontrado',
            self::TENANT_LOGO_SERVICE_UPLOAD_FAILED => 'Error al subir el logo del tenant',
            self::TENANT_LOGO_SERVICE_DELETE_FAILED => 'Error al eliminar el logo del tenant',
            self::TENANT_LOGO_SERVICE_UPDATE_FAILED => 'Error al actualizar el logo del tenant',
            self::TENANT_LOGO_SERVICE_INVALID_TENANT_DOMAIN => 'El dominio del tenant no es válido',
            self::TENANT_LOGO_SERVICE_TENANT_INACTIVE => 'El tenant está inactivo',
            self::TENANT_LOGO_SERVICE_FILE_NOT_FOUND => 'El archivo de logo no fue encontrado',
            self::TENANT_LOGO_SERVICE_FILE_DELETE_FAILED => 'Error al eliminar el archivo de logo',
            self::TENANT_LOGO_SERVICE_INVALID_FILE_PATH => 'La ruta del archivo de logo no es válida',
            self::TENANT_LOGO_SERVICE_DATABASE_ERROR => 'Error de base de datos al gestionar el logo',
            self::TENANT_LOGO_SERVICE_ENTITY_NOT_FOUND => 'La entidad del tenant no fue encontrada',
            self::TENANT_LOGO_SERVICE_UPLOADS_DIRECTORY_NOT_FOUND => 'El directorio de uploads no fue encontrado',
            self::TENANT_LOGO_SERVICE_PERMISSIONS_ERROR => 'Error de permisos al gestionar archivos de logo',
        ];
    }

    /**
     * Obtiene el mensaje para un código de error específico
     */
    public static function getMessage(string $errorCode): string
    {
        $messages = self::getErrorMessages();
        return $messages[$errorCode] ?? 'Error desconocido en el servicio de logos de tenant';
    }
}
