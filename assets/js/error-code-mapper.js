/**
 * Error Code Mapper
 * Mapea códigos técnicos de error a mensajes amigables para el usuario
 */

/**
 * Mapeo de códigos de error a mensajes amigables en español
 */
const ErrorCodeMessages = {
    // ========== Errores de Autenticación ==========
    'AUTH_LOGIN_FAILED': 'Usuario o contraseña incorrectos',
    'USER_NOT_FOUND': 'El usuario no existe en el sistema',
    'INVALID_CREDENTIALS': 'Las credenciales proporcionadas son inválidas',
    'ACCOUNT_DISABLED': 'Tu cuenta ha sido desactivada. Contacta al administrador',
    'ACCOUNT_LOCKED': 'Tu cuenta está temporalmente bloqueada por seguridad',
    'ACCOUNT_SUSPENDED': 'Tu cuenta ha sido suspendida',
    'INVALID_LOGIN_METHOD': 'Método de inicio de sesión no válido',
    'AUTHENTICATION_REQUIRED': 'Debes iniciar sesión para continuar',

    // ========== Errores de Sesión ==========
    'SESSION_EXPIRED': 'Tu sesión ha expirado',
    'SESSION_INVALID': 'Sesión inválida',
    'TOKEN_EXPIRED': 'El token de autenticación ha expirado',
    'TOKEN_INVALID': 'El token de autenticación no es válido',
    'CSRF_TOKEN_INVALID': 'Token de seguridad inválido. Recarga la página e intenta de nuevo',

    // ========== Errores de Recuperación de Contraseña ==========
    'PASSWORD_RESET_USER_NOT_FOUND': 'No se encontró un usuario con ese correo electrónico',
    'PASSWORD_RESET_EMAIL_FAILED': 'No se pudo enviar el correo de recuperación. Intenta más tarde',
    'PASSWORD_RESET_TOKEN_EXPIRED': 'El enlace de recuperación ha caducado',
    'PASSWORD_RESET_TOKEN_INVALID': 'El enlace de recuperación no es válido',
    'PASSWORD_RESET_RATE_LIMIT': 'Has excedido el límite de solicitudes. Espera unos minutos',
    'PASSWORD_TOO_WEAK': 'La contraseña debe ser más segura',
    'PASSWORD_SAME_AS_OLD': 'La nueva contraseña no puede ser igual a la anterior',
    'PASSWORD_UPDATE_FAILED': 'No se pudo actualizar la contraseña',

    // ========== Errores de Verificación ==========
    'VERIFICATION_CODE_EXPIRED': 'El código de verificación ha expirado',
    'VERIFICATION_CODE_INVALID': 'El código de verificación es incorrecto',
    'VERIFICATION_MAX_ATTEMPTS': 'Has excedido el número máximo de intentos',
    'VERIFICATION_ALREADY_COMPLETED': 'Esta cuenta ya ha sido verificada',
    'VERIFICATION_SMS_FAILED': 'No se pudo enviar el SMS de verificación',
    'VERIFICATION_EMAIL_FAILED': 'No se pudo enviar el correo de verificación',

    // ========== Errores de Validación ==========
    'FORM_VALIDATION_FAILED': 'Por favor verifica los datos ingresados',
    'REQUIRED_FIELD_MISSING': 'Hay campos obligatorios sin completar',
    'INVALID_EMAIL_FORMAT': 'El formato del correo electrónico no es válido',
    'INVALID_PHONE_FORMAT': 'El formato del número de teléfono no es válido',
    'INVALID_DATE_FORMAT': 'El formato de la fecha no es válido',

    // ========== Errores de Tenant/Organización ==========
    'DOMAIN_NOT_FOUND': 'El dominio especificado no existe',
    'TENANT_NOT_FOUND': 'La organización no fue encontrada',
    'TENANT_ERROR': 'Error al procesar la información de la organización',
    'TENANT_INACTIVE': 'La organización está inactiva',
    'TENANT_SUSPENDED': 'La organización ha sido suspendida',

    // ========== Errores de Permisos ==========
    'PERMISSION_DENIED': 'No tienes permisos para realizar esta acción',
    'ACCESS_DENIED': 'Acceso denegado',
    'ROLE_NOT_FOUND': 'El rol especificado no existe',
    'INSUFFICIENT_PERMISSIONS': 'Permisos insuficientes para esta operación',

    // ========== Errores de Datos ==========
    'NO_DATA': 'No se encontraron datos',
    'DATA_NOT_FOUND': 'Información no encontrada',
    'DUPLICATE_ENTRY': 'Este registro ya existe en el sistema',
    'INVALID_DATA': 'Los datos proporcionados no son válidos',
    'DATA_INTEGRITY_ERROR': 'Error de integridad de datos',

    // ========== Errores de Procesamiento ==========
    'PROCESSING_ERROR': 'Error al procesar la solicitud',
    'OPERATION_FAILED': 'La operación no pudo completarse',
    'SAVE_FAILED': 'No se pudo guardar la información',
    'UPDATE_FAILED': 'No se pudo actualizar la información',
    'DELETE_FAILED': 'No se pudo eliminar el registro',

    // ========== Errores de Red/Servidor ==========
    'NETWORK_ERROR': 'Error de conexión. Verifica tu internet',
    'INTERNAL_SERVER_ERROR': 'Error interno del servidor',
    'SERVICE_UNAVAILABLE': 'El servicio no está disponible temporalmente',
    'TIMEOUT_ERROR': 'La operación tardó demasiado tiempo',
    'DATABASE_ERROR': 'Error de base de datos. Contacta al soporte',

    // ========== Errores de Límites ==========
    'RATE_LIMIT_EXCEEDED': 'Has excedido el límite de solicitudes',
    'MAX_FILE_SIZE_EXCEEDED': 'El archivo excede el tamaño máximo permitido',
    'MAX_UPLOADS_EXCEEDED': 'Has excedido el número máximo de archivos',

    // ========== Errores de Archivos ==========
    'FILE_UPLOAD_FAILED': 'Error al subir el archivo',
    'FILE_NOT_FOUND': 'Archivo no encontrado',
    'INVALID_FILE_TYPE': 'Tipo de archivo no permitido',
    'FILE_CORRUPTED': 'El archivo está corrupto o dañado',

    // ========== Otros Errores Comunes ==========
    'UNKNOWN_CODE': 'Error desconocido',
    'UNKNOWN_ERROR': 'Ha ocurrido un error inesperado',
    'SYSTEM_ERROR': 'Error del sistema',
    'MAINTENANCE_MODE': 'El sistema está en mantenimiento',
    'FEATURE_DISABLED': 'Esta funcionalidad no está disponible',
};

/**
 * Sugerencias para ayudar al usuario a resolver problemas
 */
const ErrorSuggestions = {
    // Autenticación
    'AUTH_LOGIN_FAILED': 'Verifica que tu usuario y contraseña sean correctos',
    'USER_NOT_FOUND': 'Asegúrate de haber escrito correctamente tu correo electrónico',
    'ACCOUNT_LOCKED': 'Espera 30 minutos o contacta al administrador',
    'INVALID_CREDENTIALS': 'Revisa mayúsculas, minúsculas y caracteres especiales',

    // Sesión
    'SESSION_EXPIRED': 'Vuelve a iniciar sesión para continuar',
    'TOKEN_EXPIRED': 'Refresca la página e intenta de nuevo',
    'CSRF_TOKEN_INVALID': 'Recarga la página (F5) antes de intentar nuevamente',

    // Recuperación de contraseña
    'PASSWORD_RESET_TOKEN_EXPIRED': 'Solicita un nuevo enlace de recuperación desde el formulario',
    'PASSWORD_RESET_EMAIL_FAILED': 'Verifica tu conexión a internet e intenta nuevamente',
    'PASSWORD_TOO_WEAK': 'Usa al menos 8 caracteres, mayúsculas, minúsculas y números',
    'PASSWORD_RESET_RATE_LIMIT': 'Espera unos minutos antes de solicitar otro código',

    // Verificación
    'VERIFICATION_CODE_EXPIRED': 'Solicita un nuevo código de verificación',
    'VERIFICATION_CODE_INVALID': 'Verifica que el código sea correcto',
    'VERIFICATION_MAX_ATTEMPTS': 'Espera 10 minutos antes de intentar de nuevo',

    // Red
    'NETWORK_ERROR': 'Verifica tu conexión a internet y vuelve a intentar',
    'TIMEOUT_ERROR': 'Verifica tu conexión y vuelve a intentar',
    'SERVICE_UNAVAILABLE': 'Intenta de nuevo en unos minutos',

    // Archivos
    'MAX_FILE_SIZE_EXCEEDED': 'Reduce el tamaño del archivo o comprime la imagen',
    'INVALID_FILE_TYPE': 'Solo se permiten archivos PDF, JPG, PNG o DOCX',
    'FILE_UPLOAD_FAILED': 'Verifica tu conexión e intenta subir el archivo nuevamente',

    // General
    'INTERNAL_SERVER_ERROR': 'Si el problema persiste, contacta al soporte técnico',
    'RATE_LIMIT_EXCEEDED': 'Espera un momento antes de realizar más acciones',
};

/**
 * Iconos personalizados para ciertos tipos de error
 */
const ErrorIcons = {
    'SESSION_EXPIRED': 'warning',
    'TOKEN_EXPIRED': 'warning',
    'ACCOUNT_LOCKED': 'warning',
    'RATE_LIMIT_EXCEEDED': 'warning',
    'NETWORK_ERROR': 'error',
    'MAINTENANCE_MODE': 'info',
};

/**
 * Obtiene el mensaje amigable para un código de error
 * @param {string} code - Código de error técnico
 * @returns {string} - Mensaje amigable
 */
function getErrorMessage(code) {
    if (!code) {
        return ErrorCodeMessages['UNKNOWN_ERROR'];
    }
    return ErrorCodeMessages[code] || ErrorCodeMessages['UNKNOWN_ERROR'];
}

/**
 * Obtiene una sugerencia para resolver el error
 * @param {string} code - Código de error técnico
 * @returns {string|null} - Sugerencia o null si no hay
 */
function getErrorSuggestion(code) {
    return ErrorSuggestions[code] || null;
}

/**
 * Obtiene el icono apropiado para un código de error
 * @param {string} code - Código de error técnico
 * @returns {string} - Tipo de icono para SweetAlert
 */
function getErrorIcon(code) {
    return ErrorIcons[code] || 'error';
}

/**
 * Determina si un error es crítico y requiere acción inmediata
 * @param {string} code - Código de error técnico
 * @returns {boolean}
 */
function isCriticalError(code) {
    const criticalErrors = [
        'ACCOUNT_SUSPENDED',
        'ACCOUNT_DISABLED',
        'TENANT_SUSPENDED',
        'PERMISSION_DENIED',
        'DATABASE_ERROR'
    ];
    return criticalErrors.includes(code);
}

/**
 * Obtiene información completa de un error
 * @param {string} code - Código de error técnico
 * @returns {Object} - Objeto con toda la información del error
 */
function getErrorInfo(code) {
    return {
        code: code,
        message: getErrorMessage(code),
        suggestion: getErrorSuggestion(code),
        icon: getErrorIcon(code),
        isCritical: isCriticalError(code)
    };
}

// Exportar funciones para uso global
window.ErrorCodeMapper = {
    getErrorMessage,
    getErrorSuggestion,
    getErrorIcon,
    isCriticalError,
    getErrorInfo,
    // Exportar constantes para referencia (opcional)
    Messages: ErrorCodeMessages,
    Suggestions: ErrorSuggestions
};

console.log('Error Code Mapper loaded successfully');
