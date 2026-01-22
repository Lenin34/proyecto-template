/**
 * Bulk Upload Error Management System
 * Manejo centralizado de errores para la carga masiva de usuarios
 * 
 * @author MasoftCode
 * @version 1.0.0
 */

// ============================================
// ENUM: Códigos de Error
// ============================================
const BulkUploadErrorCodes = Object.freeze({
    // Errores de Archivo (1xx)
    FILE_NOT_SELECTED: 'E101',
    FILE_INVALID_TYPE: 'E102',
    FILE_TOO_LARGE: 'E103',
    FILE_EMPTY: 'E104',
    FILE_CORRUPTED: 'E105',
    FILE_READ_ERROR: 'E106',

    // Errores de Parseo (2xx)
    PARSE_FAILED: 'E201',
    PARSE_NO_DATA: 'E202',
    PARSE_INVALID_HEADERS: 'E203',
    PARSE_MISSING_COLUMNS: 'E204',

    // Errores de Validación de Fila (3xx)
    ROW_EMPTY: 'E301',
    ROW_MISSING_REQUIRED: 'E302',
    ROW_INVALID_EMAIL: 'E303',
    ROW_DUPLICATE_EMAIL: 'E304',
    ROW_INVALID_DATE: 'E305',
    ROW_INVALID_CURP: 'E306',
    ROW_INVALID_PHONE: 'E307',

    // Errores de Envío (4xx)
    SUBMIT_FAILED: 'E401',
    SUBMIT_TIMEOUT: 'E402',
    SUBMIT_NETWORK_ERROR: 'E403',
    SUBMIT_SERVER_ERROR: 'E404',

    // Errores de Sistema (5xx)
    BROWSER_NOT_SUPPORTED: 'E501',
    LIBRARY_NOT_LOADED: 'E502',
    UNKNOWN_ERROR: 'E599'
});

// ============================================
// ENUM: Severidad del Error
// ============================================
const ErrorSeverity = Object.freeze({
    INFO: 'info',
    WARNING: 'warning',
    ERROR: 'error',
    CRITICAL: 'critical'
});

// ============================================
// Mensajes de Error en Español
// ============================================
const BulkUploadErrorMessages = Object.freeze({
    [BulkUploadErrorCodes.FILE_NOT_SELECTED]: {
        title: 'Archivo no seleccionado',
        message: 'Por favor, selecciona un archivo Excel (.xlsx o .xls) para continuar.',
        severity: ErrorSeverity.WARNING
    },
    [BulkUploadErrorCodes.FILE_INVALID_TYPE]: {
        title: 'Tipo de archivo inválido',
        message: 'El archivo debe ser un Excel (.xlsx o .xls). Otros formatos no son compatibles.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.FILE_TOO_LARGE]: {
        title: 'Archivo demasiado grande',
        message: 'El archivo excede el tamaño máximo permitido de 5MB.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.FILE_EMPTY]: {
        title: 'Archivo vacío',
        message: 'El archivo seleccionado no contiene datos. Por favor, verifica el contenido.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.FILE_CORRUPTED]: {
        title: 'Archivo corrupto',
        message: 'No se pudo leer el archivo. Puede estar dañado o en un formato no soportado.',
        severity: ErrorSeverity.CRITICAL
    },
    [BulkUploadErrorCodes.FILE_READ_ERROR]: {
        title: 'Error de lectura',
        message: 'Ocurrió un error al leer el archivo. Intenta nuevamente.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.PARSE_FAILED]: {
        title: 'Error de procesamiento',
        message: 'No se pudo procesar el contenido del archivo Excel.',
        severity: ErrorSeverity.CRITICAL
    },
    [BulkUploadErrorCodes.PARSE_NO_DATA]: {
        title: 'Sin datos',
        message: 'El archivo no contiene filas de datos para importar (solo encabezados).',
        severity: ErrorSeverity.WARNING
    },
    [BulkUploadErrorCodes.PARSE_INVALID_HEADERS]: {
        title: 'Encabezados inválidos',
        message: 'Los encabezados del archivo no coinciden con la plantilla esperada.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.PARSE_MISSING_COLUMNS]: {
        title: 'Columnas faltantes',
        message: 'El archivo no tiene todas las columnas requeridas. Descarga la plantilla correcta.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.ROW_EMPTY]: {
        title: 'Fila vacía',
        message: 'La fila no contiene datos.',
        severity: ErrorSeverity.INFO
    },
    [BulkUploadErrorCodes.ROW_MISSING_REQUIRED]: {
        title: 'Campos requeridos',
        message: 'Faltan campos obligatorios: Nombre y Apellidos son requeridos.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.ROW_INVALID_EMAIL]: {
        title: 'Email inválido',
        message: 'El formato del correo electrónico no es válido.',
        severity: ErrorSeverity.WARNING
    },
    [BulkUploadErrorCodes.ROW_DUPLICATE_EMAIL]: {
        title: 'Email duplicado',
        message: 'Este correo electrónico ya existe en el archivo o en el sistema.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.ROW_INVALID_DATE]: {
        title: 'Fecha inválida',
        message: 'El formato de fecha no es válido. Use: DD/MM/YYYY o YYYY-MM-DD.',
        severity: ErrorSeverity.WARNING
    },
    [BulkUploadErrorCodes.ROW_INVALID_CURP]: {
        title: 'CURP inválido',
        message: 'El formato del CURP no es válido (debe tener 18 caracteres).',
        severity: ErrorSeverity.WARNING
    },
    [BulkUploadErrorCodes.ROW_INVALID_PHONE]: {
        title: 'Teléfono inválido',
        message: 'El formato del número telefónico no es válido.',
        severity: ErrorSeverity.WARNING
    },
    [BulkUploadErrorCodes.SUBMIT_FAILED]: {
        title: 'Error al enviar',
        message: 'No se pudieron guardar los usuarios. Intenta nuevamente.',
        severity: ErrorSeverity.CRITICAL
    },
    [BulkUploadErrorCodes.SUBMIT_TIMEOUT]: {
        title: 'Tiempo agotado',
        message: 'La solicitud tardó demasiado. Verifica tu conexión e intenta nuevamente.',
        severity: ErrorSeverity.ERROR
    },
    [BulkUploadErrorCodes.SUBMIT_NETWORK_ERROR]: {
        title: 'Error de red',
        message: 'No hay conexión a internet. Verifica tu conexión e intenta nuevamente.',
        severity: ErrorSeverity.CRITICAL
    },
    [BulkUploadErrorCodes.SUBMIT_SERVER_ERROR]: {
        title: 'Error del servidor',
        message: 'El servidor respondió con un error. Contacta al administrador.',
        severity: ErrorSeverity.CRITICAL
    },
    [BulkUploadErrorCodes.BROWSER_NOT_SUPPORTED]: {
        title: 'Navegador no soportado',
        message: 'Tu navegador no soporta esta funcionalidad. Usa Chrome, Firefox o Edge.',
        severity: ErrorSeverity.CRITICAL
    },
    [BulkUploadErrorCodes.LIBRARY_NOT_LOADED]: {
        title: 'Error de carga',
        message: 'No se cargaron las librerías necesarias. Recarga la página.',
        severity: ErrorSeverity.CRITICAL
    },
    [BulkUploadErrorCodes.UNKNOWN_ERROR]: {
        title: 'Error desconocido',
        message: 'Ocurrió un error inesperado. Intenta nuevamente o contacta al administrador.',
        severity: ErrorSeverity.CRITICAL
    }
});

// ============================================
// Clase: Error de Carga Masiva
// ============================================
class BulkUploadError extends Error {
    constructor(code, details = {}) {
        const errorInfo = BulkUploadErrorMessages[code] || BulkUploadErrorMessages[BulkUploadErrorCodes.UNKNOWN_ERROR];
        super(errorInfo.message);

        this.name = 'BulkUploadError';
        this.code = code;
        this.title = errorInfo.title;
        this.severity = errorInfo.severity;
        this.details = details;
        this.timestamp = new Date().toISOString();
    }

    toJSON() {
        return {
            code: this.code,
            title: this.title,
            message: this.message,
            severity: this.severity,
            details: this.details,
            timestamp: this.timestamp
        };
    }
}

// ============================================
// Clase: Gestor de Errores
// ============================================
class BulkUploadErrorHandler {
    constructor() {
        this.errors = [];
        this.warnings = [];
    }

    /**
     * Registra un error
     */
    addError(code, details = {}) {
        const error = new BulkUploadError(code, details);

        if (error.severity === ErrorSeverity.WARNING || error.severity === ErrorSeverity.INFO) {
            this.warnings.push(error);
        } else {
            this.errors.push(error);
        }

        // Log en consola para debugging
        console.warn(`[BulkUpload ${error.code}]`, error.title, error.details);

        return error;
    }

    /**
     * Verifica si hay errores críticos
     */
    hasErrors() {
        return this.errors.length > 0;
    }

    /**
     * Verifica si hay advertencias
     */
    hasWarnings() {
        return this.warnings.length > 0;
    }

    /**
     * Obtiene todos los errores
     */
    getErrors() {
        return this.errors;
    }

    /**
     * Obtiene todas las advertencias
     */
    getWarnings() {
        return this.warnings;
    }

    /**
     * Obtiene errores de una fila específica
     */
    getRowErrors(rowNumber) {
        return this.errors.filter(e => e.details.row === rowNumber);
    }

    /**
     * Limpia todos los errores
     */
    clear() {
        this.errors = [];
        this.warnings = [];
    }

    /**
     * Genera resumen de errores para mostrar
     */
    getSummary() {
        const summary = {
            totalErrors: this.errors.length,
            totalWarnings: this.warnings.length,
            byCode: {},
            affectedRows: new Set()
        };

        [...this.errors, ...this.warnings].forEach(error => {
            if (!summary.byCode[error.code]) {
                summary.byCode[error.code] = {
                    count: 0,
                    title: error.title,
                    severity: error.severity
                };
            }
            summary.byCode[error.code].count++;

            if (error.details.row) {
                summary.affectedRows.add(error.details.row);
            }
        });

        summary.affectedRows = Array.from(summary.affectedRows).sort((a, b) => a - b);

        return summary;
    }

    /**
     * Muestra errores con SweetAlert2
     */
    showAlert() {
        if (!this.hasErrors() && !this.hasWarnings()) {
            return;
        }

        const summary = this.getSummary();
        let html = '';

        if (summary.totalErrors > 0) {
            html += `<div class="text-danger mb-2"><strong>❌ ${summary.totalErrors} error(es) encontrado(s)</strong></div>`;
        }

        if (summary.totalWarnings > 0) {
            html += `<div class="text-warning mb-2"><strong>⚠️ ${summary.totalWarnings} advertencia(s)</strong></div>`;
        }

        if (summary.affectedRows.length > 0 && summary.affectedRows.length <= 10) {
            html += `<div class="mt-2"><small>Filas afectadas: ${summary.affectedRows.join(', ')}</small></div>`;
        }

        // Mostrar primeros 5 errores
        const allIssues = [...this.errors, ...this.warnings].slice(0, 5);
        if (allIssues.length > 0) {
            html += '<ul class="text-start mt-3" style="font-size: 0.9em;">';
            allIssues.forEach(e => {
                const icon = e.severity === ErrorSeverity.ERROR || e.severity === ErrorSeverity.CRITICAL ? '❌' : '⚠️';
                const rowInfo = e.details.row ? ` (Fila ${e.details.row})` : '';
                html += `<li>${icon} ${e.title}${rowInfo}</li>`;
            });
            html += '</ul>';
        }

        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: summary.totalErrors > 0 ? 'error' : 'warning',
                title: summary.totalErrors > 0 ? 'Errores en el archivo' : 'Advertencias',
                html: html,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#0B3F61'
            });
        } else {
            // Usar SweetAlert2 en lugar de alert() nativo
            Swal.fire({
                icon: summary.totalErrors > 0 ? 'error' : 'warning',
                title: summary.totalErrors > 0 ? 'Errores detectados' : 'Advertencias detectadas',
                text: `Errores: ${summary.totalErrors}, Advertencias: ${summary.totalWarnings}`,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#0B3F61'
            });
        }
    }
}

// ============================================
// Exportar para uso global
// ============================================
window.BulkUploadErrorCodes = BulkUploadErrorCodes;
window.ErrorSeverity = ErrorSeverity;
window.BulkUploadError = BulkUploadError;
window.BulkUploadErrorHandler = BulkUploadErrorHandler;
