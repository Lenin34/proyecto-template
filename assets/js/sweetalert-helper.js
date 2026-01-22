/**
 * SweetAlert2 Helper Functions
 * Funciones centralizadas para manejar alertas en toda la aplicación
 */

// Configuración global de SweetAlert2
const SwalConfig = {
    toast: {
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        showCloseButton: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    },
    modal: {
        showConfirmButton: true,
        showCancelButton: false,
        confirmButtonColor: '#0d6efd',
        cancelButtonColor: '#6c757d'
    }
};

// Funciones de alertas tipo toast
window.SwalHelper = {

    // Alertas de éxito
    success: function (message, title = 'Éxito') {
        return Swal.fire({
            ...SwalConfig.toast,
            icon: 'success',
            title: title,
            text: message
        });
    },

    // Alertas de error
    error: function (message, title = 'Error') {
        return Swal.fire({
            ...SwalConfig.toast,
            icon: 'error',
            title: title,
            text: message,
            timer: 5000,
            showConfirmButton: true
        });
    },

    // Alertas de advertencia
    warning: function (message, title = 'Advertencia') {
        return Swal.fire({
            ...SwalConfig.toast,
            icon: 'warning',
            title: title,
            text: message,
            timer: 4000
        });
    },

    // Alertas de información
    info: function (message, title = 'Información') {
        return Swal.fire({
            ...SwalConfig.toast,
            icon: 'info',
            title: title,
            text: message
        });
    },

    // Confirmaciones
    confirm: function (options = {}) {
        const defaultOptions = {
            title: '¿Está seguro?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true,
            ...SwalConfig.modal
        };

        return Swal.fire({
            ...defaultOptions,
            ...options
        });
    },

    // Confirmación de eliminación
    confirmDelete: function (itemName = 'este elemento') {
        // Usar SwalHelper.confirm directamente para evitar problemas de contexto 'this'
        return SwalHelper.confirm({
            title: '¿Eliminar ' + itemName + '?',
            text: 'Esta acción no se puede deshacer',
            icon: 'warning',
            confirmButtonText: 'Sí, eliminar',
            confirmButtonColor: '#dc3545'
        });
    },

    // Prompt para input
    prompt: function (options = {}) {
        const defaultOptions = {
            title: 'Ingrese el valor',
            input: 'text',
            showCancelButton: true,
            confirmButtonText: 'Aceptar',
            cancelButtonText: 'Cancelar',
            inputValidator: (value) => {
                if (!value) {
                    return 'Este campo es obligatorio';
                }
            },
            ...SwalConfig.modal
        };

        return Swal.fire({
            ...defaultOptions,
            ...options
        });
    },

    // Loading/Progress
    loading: function (title = 'Procesando...') {
        return Swal.fire({
            title: title,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
    },

    // Cerrar loading
    close: function () {
        Swal.close();
    },

    // Alerta de validación con errores múltiples
    validationErrors: function (errors, title = 'Errores de validación') {
        let errorList = '';

        if (typeof errors === 'object') {
            Object.keys(errors).forEach(field => {
                const fieldErrors = Array.isArray(errors[field]) ? errors[field] : [errors[field]];
                fieldErrors.forEach(error => {
                    errorList += `• ${error}<br>`;
                });
            });
        } else {
            errorList = errors;
        }

        return Swal.fire({
            icon: 'error',
            title: title,
            html: errorList,
            showConfirmButton: true,
            confirmButtonText: 'Entendido'
        });
    },

    // Alerta de éxito con opciones adicionales
    successWithOptions: function (message, options = {}) {
        return Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: message,
            showConfirmButton: true,
            confirmButtonText: 'Continuar',
            ...options
        });
    },

    // Alerta personalizada para formularios
    formSuccess: function (message, redirectUrl = null) {
        return Swal.fire({
            icon: 'success',
            title: 'Formulario enviado',
            text: message,
            showConfirmButton: true,
            confirmButtonText: redirectUrl ? 'Continuar' : 'Aceptar'
        }).then((result) => {
            if (result.isConfirmed && redirectUrl) {
                window.location.href = redirectUrl;
            }
        });
    },

    // Alerta para exportaciones
    exportSuccess: function (fileName) {
        return this.success(`Archivo "${fileName}" descargado exitosamente`, 'Exportación completa');
    },

    // Alerta para importaciones
    importSuccess: function (itemsCount) {
        return this.success(`Se importaron ${itemsCount} elementos exitosamente`, 'Importación completa');
    },

    // Alerta de conexión/red
    networkError: function () {
        return this.error(
            'Verifique su conexión a internet e intente nuevamente',
            'Error de conexión'
        );
    },

    // Alerta de permisos
    permissionDenied: function () {
        return this.error(
            'No tiene permisos para realizar esta acción',
            'Acceso denegado'
        );
    },

    // Alerta de sesión expirada
    sessionExpired: function () {
        const dominio = window.appConfig ? window.appConfig.dominio : 'issemym';
        const redirectUrl = `/${dominio}/login`;

        return Swal.fire({
            icon: 'warning',
            title: 'Sesión expirada',
            text: 'Su sesión ha expirado. Será redirigido al login.',
            showConfirmButton: true,
            confirmButtonText: 'Ir al login',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            window.location.href = redirectUrl;
        });
    },

    // Alerta de mantenimiento
    maintenance: function () {
        return this.warning(
            'El sistema está en mantenimiento. Intente más tarde.',
            'Mantenimiento programado'
        );
    }
};

// Funciones de conveniencia globales
window.showSuccess = SwalHelper.success;
window.showError = SwalHelper.error;
window.showWarning = SwalHelper.warning;
window.showInfo = SwalHelper.info;
window.confirmAction = SwalHelper.confirm;
window.confirmDelete = SwalHelper.confirmDelete.bind(SwalHelper);
window.showLoading = SwalHelper.loading;
window.hideLoading = SwalHelper.close;

// Manejo automático de respuestas AJAX
window.handleAjaxResponse = function (response) {
    if (response.success) {
        if (response.message) {
            SwalHelper.success(response.message);
        }
        if (response.redirect) {
            setTimeout(() => {
                window.location.href = response.redirect;
            }, 1500);
        }
    } else {
        if (response.errors) {
            SwalHelper.validationErrors(response.errors);
        } else if (response.message) {
            SwalHelper.error(response.message);
        } else {
            SwalHelper.error('Ocurrió un error inesperado');
        }
    }
};

// Interceptor para errores HTTP comunes
window.handleHttpError = function (status, message = null) {
    switch (status) {
        case 401:
            SwalHelper.sessionExpired();
            break;
        case 403:
            SwalHelper.permissionDenied();
            break;
        case 404:
            SwalHelper.error('El recurso solicitado no fue encontrado', 'No encontrado');
            break;
        case 500:
            SwalHelper.error('Error interno del servidor. Contacte al administrador.', 'Error del servidor');
            break;
        case 503:
            SwalHelper.maintenance();
            break;
        default:
            SwalHelper.error(message || 'Ocurrió un error inesperado');
    }
};

// Interceptor para formularios con SweetAlert2
window.setupFormInterceptors = function () {
    // Interceptar envíos de formularios para mostrar loading
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function () {
            // Solo mostrar loading si no es un formulario de vista previa
            if (!form.classList.contains('preview-form') && !form.hasAttribute('data-no-loading')) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';

                    // Restaurar después de 10 segundos como fallback
                    setTimeout(() => {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = originalText;
                    }, 10000);
                }
            }
        });
    });
};

// Función para manejar respuestas de exportación
window.handleExportResponse = function (response, fileName) {
    if (response.ok) {
        SwalHelper.exportSuccess(fileName);
    } else {
        SwalHelper.error('Error al generar el archivo de exportación');
    }
    hideLoading();
};

// Auto-inicialización
document.addEventListener('DOMContentLoaded', function () {
    // Configurar interceptores para fetch
    const originalFetch = window.fetch;
    window.fetch = function (...args) {
        return originalFetch.apply(this, args)
            .then(response => {
                if (!response.ok) {
                    handleHttpError(response.status);
                }
                return response;
            })
            .catch(error => {
                if (error.name === 'TypeError' && error.message.includes('fetch')) {
                    SwalHelper.networkError();
                }
                throw error;
            });
    };

    // Configurar interceptores de formularios
    setupFormInterceptors();

    // Interceptar enlaces de exportación
    document.querySelectorAll('a[href*="/export/"]').forEach(link => {
        link.addEventListener('click', function () {
            showLoading('Preparando exportación...');

            // Ocultar loading después de 3 segundos (tiempo estimado de descarga)
            setTimeout(() => {
                hideLoading();
                showSuccess('Archivo descargado exitosamente');
            }, 3000);
        });
    });

    console.log('SweetAlert2 Helper loaded successfully');
});
