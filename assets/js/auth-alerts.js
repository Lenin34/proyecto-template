/**
 * Sistema de alertas de autenticación con SweetAlert2
 * Maneja errores y mensajes de autenticación de forma consistente
 */

// Configuración global para alertas de autenticación
window.AuthAlerts = {

    /**
     * Maneja respuestas de autenticación del servidor
     */
    handleAuthResponse: function (response, options = {}) {
        if (response.swal) {
            return this.showSwalAlert(response, options);
        } else {
            return this.showFallbackAlert(response, options);
        }
    },

    /**
     * Muestra alerta usando configuración SweetAlert2 del servidor
     */
    showSwalAlert: function (data, options = {}) {
        let config = {
            icon: data.icon || (data.success ? 'success' : 'error'),
            title: data.title || (data.success ? 'Éxito' : 'Error'),
            text: data.message,
            showConfirmButton: data.showConfirmButton !== false,
            confirmButtonText: data.confirmButtonText || (data.success ? 'Continuar' : 'Entendido'),
            allowOutsideClick: data.allowOutsideClick !== false,
            allowEscapeKey: data.allowEscapeKey !== false,
            timer: data.timer || null,
            ...options.swalConfig
        };

        // Si hay sugerencias, mostrarlas en el HTML
        if (data.suggestions && data.suggestions.length > 0) {
            let suggestionsHtml = '<div style="margin-top: 15px; text-align: left;">';
            suggestionsHtml += '<strong>💡 Sugerencias:</strong><ul style="margin: 10px 0; padding-left: 20px;">';
            data.suggestions.forEach(suggestion => {
                suggestionsHtml += `<li style="margin: 5px 0;">${suggestion}</li>`;
            });
            suggestionsHtml += '</ul></div>';

            config.html = `<div style="text-align: center;">${data.message}</div>${suggestionsHtml}`;
            delete config.text;
        }

        // Ajustar configuración basada en la severidad
        if (data.severity) {
            switch (data.severity) {
                case 'critical':
                    config.icon = 'error';
                    config.iconColor = '#d33';
                    config.allowOutsideClick = false;
                    config.allowEscapeKey = false;
                    break;
                case 'high':
                    config.icon = 'warning';
                    config.iconColor = '#f39c12';
                    break;
                case 'medium':
                    config.icon = 'info';
                    config.iconColor = '#3085d6';
                    break;
                case 'low':
                    config.icon = 'info';
                    config.iconColor = '#17a2b8';
                    break;
            }
        }

        // Manejar código de error - NO mostrarlo al usuario, solo en consola
        if (data.error_code) {
            // Registrar en consola para debugging
            console.error('🔴 Error Code:', data.error_code);
            console.error('📄 Full Error Data:', data);

            // Si hay ErrorCodeMapper disponible, obtener sugerencia
            if (window.ErrorCodeMapper) {
                const errorInfo = window.ErrorCodeMapper.getErrorInfo(data.error_code);

                // Si hay sugerencia y no hay sugerencias del servidor, mostrarla
                if (errorInfo.suggestion && !data.suggestions) {
                    const suggestionHtml = `<div style="margin-top: 15px; padding: 12px; background-color: #f8f9fa; border-left: 3px solid #17a2b8; text-align: left;">
                        <strong style="color: #17a2b8;">💡 Sugerencia:</strong><br>
                        <span style="color: #495057; margin-top: 5px; display: block;">${errorInfo.suggestion}</span>
                    </div>`;

                    if (config.html) {
                        config.html += suggestionHtml;
                    } else {
                        config.html = `<div style="text-align: center;">${data.message}</div>${suggestionHtml}`;
                        delete config.text;
                    }
                }

                // Ajustar icono si el mapper tiene uno específico
                if (!data.icon && errorInfo.icon) {
                    config.icon = errorInfo.icon;
                }

                // Si es crítico, ajustar configuración
                if (errorInfo.isCritical) {
                    config.allowOutsideClick = false;
                    config.allowEscapeKey = false;
                }
            }
        }

        return Swal.fire(config).then((result) => {
            if (data.redirect && (result.isConfirmed || result.isDismissed)) {
                window.location.href = data.redirect;
            }

            if (options.onComplete) {
                options.onComplete(result, data);
            }
        });
    },

    /**
     * Muestra alerta de fallback para respuestas sin formato SweetAlert
     */
    showFallbackAlert: function (data, options = {}) {
        const config = {
            icon: data.success ? 'success' : 'error',
            title: data.success ? 'Éxito' : 'Error',
            text: data.message || (data.success ? 'Operación exitosa' : 'Ha ocurrido un error'),
            confirmButtonText: data.success ? 'Continuar' : 'Entendido',
            ...options.swalConfig
        };

        return Swal.fire(config).then((result) => {
            if (data.redirect && result.isConfirmed) {
                window.location.href = data.redirect;
            }

            if (options.onComplete) {
                options.onComplete(result, data);
            }
        });
    },

    /**
     * Maneja errores de conexión/red
     */
    showNetworkError: function (error = null) {
        return Swal.fire({
            icon: 'error',
            title: 'Error de Conexión',
            text: 'No se pudo conectar con el servidor. Verifique su conexión a internet e intente nuevamente.',
            confirmButtonText: 'Intentar de nuevo',
            showConfirmButton: true
        });
    },

    /**
     * Maneja errores de validación de formularios
     */
    showValidationErrors: function (errors, title = 'Errores de validación') {
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

    /**
     * Muestra alerta de sesión expirada
     */
    showSessionExpired: function (redirectUrl = null) {
        // Si no se proporciona URL, construir la del tenant actual
        if (!redirectUrl) {
            const dominio = window.appConfig ? window.appConfig.dominio : 'issemym';
            redirectUrl = `/${dominio}/login`;
        }

        return Swal.fire({
            icon: 'warning',
            title: 'Sesión Expirada',
            text: 'Su sesión ha expirado. Será redirigido al login.',
            showConfirmButton: true,
            confirmButtonText: 'Ir al login',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(() => {
            window.location.href = redirectUrl;
        });
    },

    /**
     * Muestra alerta de mantenimiento
     */
    showMaintenanceAlert: function () {
        return Swal.fire({
            icon: 'info',
            title: 'Mantenimiento',
            text: 'El sistema está en mantenimiento. Intente más tarde.',
            showConfirmButton: true,
            confirmButtonText: 'Entendido',
            allowOutsideClick: false
        });
    },

    /**
     * Maneja respuestas de formularios de autenticación
     */
    handleFormResponse: function (response, formElement, options = {}) {
        const defaultOptions = {
            resetForm: false,
            focusField: null,
            onSuccess: null,
            onError: null
        };

        const config = { ...defaultOptions, ...options };

        return response.json().then(data => {
            // Manejar error específico de CSRF token
            if (data.original_error === 'Invalid CSRF token.' ||
                (data.message && data.message.includes('Token de seguridad inválido'))) {

                console.log('CSRF token inválido, recargando...');
                this.refreshCSRFToken(formElement).then(() => {
                    this.showAlert({
                        icon: 'warning',
                        title: 'Token Expirado',
                        text: 'El token de seguridad ha expirado. Se ha actualizado automáticamente. Intente nuevamente.',
                        confirmButtonText: 'Entendido'
                    });
                });
                return;
            }

            if (data.swal || data.success !== undefined) {
                this.handleAuthResponse(data, {
                    onComplete: (result, responseData) => {
                        if (responseData.success) {
                            if (config.onSuccess) {
                                config.onSuccess(result, responseData);
                            } else if (responseData.redirect) {
                                // Redirección automática si no hay callback personalizado
                                setTimeout(() => {
                                    window.location.href = responseData.redirect;
                                }, 1500);
                            }
                        } else {
                            if (config.resetForm && formElement) {
                                formElement.reset();
                            }

                            if (config.focusField) {
                                const field = document.getElementById(config.focusField);
                                if (field) {
                                    field.focus();
                                }
                            }

                            if (config.onError) {
                                config.onError(result, responseData);
                            }
                        }
                    }
                });
            } else {
                // Respuesta no reconocida
                this.showFallbackAlert({
                    success: false,
                    message: 'Respuesta del servidor no reconocida'
                });
            }
        }).catch(error => {
            console.error('Error procesando respuesta:', error);
            this.showNetworkError(error);
        });
    },

    /**
     * Interceptor para errores HTTP comunes
     */
    handleHttpError: function (status, message = null) {
        switch (status) {
            case 401:
                this.showSessionExpired();
                break;
            case 403:
                Swal.fire({
                    icon: 'error',
                    title: 'Acceso Denegado',
                    text: 'No tiene permisos para realizar esta acción',
                    confirmButtonText: 'Entendido'
                });
                break;
            case 404:
                Swal.fire({
                    icon: 'error',
                    title: 'No Encontrado',
                    text: 'El recurso solicitado no fue encontrado',
                    confirmButtonText: 'Entendido'
                });
                break;
            case 429:
                Swal.fire({
                    icon: 'warning',
                    title: 'Demasiadas Solicitudes',
                    text: 'Ha excedido el límite de solicitudes. Intente más tarde.',
                    confirmButtonText: 'Entendido'
                });
                break;
            case 500:
                Swal.fire({
                    icon: 'error',
                    title: 'Error del Servidor',
                    text: 'Error interno del servidor. Contacte al administrador.',
                    confirmButtonText: 'Entendido'
                });
                break;
            case 503:
                this.showMaintenanceAlert();
                break;
            default:
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: message || 'Ha ocurrido un error inesperado',
                    confirmButtonText: 'Entendido'
                });
        }
    },

    /**
     * Utilidad para mostrar spinner en botones durante peticiones
     */
    toggleButtonLoading: function (buttonId, isLoading = true) {
        const button = document.getElementById(buttonId);
        if (!button) return;

        const text = button.querySelector('[id$="Text"]');
        const spinner = button.querySelector('[id$="Spinner"]');

        if (isLoading) {
            button.disabled = true;
            if (text) text.classList.add('d-none');
            if (spinner) spinner.classList.remove('d-none');
        } else {
            button.disabled = false;
            if (text) text.classList.remove('d-none');
            if (spinner) spinner.classList.add('d-none');
        }
    },

    /**
     * Configura un formulario para usar el sistema de alertas
     */
    setupForm: function (formId, options = {}) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const buttonId = options.buttonId;
            if (buttonId) {
                this.toggleButtonLoading(buttonId, true);
            }

            fetch(form.action || window.location.href, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    if (buttonId) {
                        this.toggleButtonLoading(buttonId, false);
                    }

                    return this.handleFormResponse(response, form, options);
                })
                .catch(error => {
                    if (buttonId) {
                        this.toggleButtonLoading(buttonId, false);
                    }

                    console.error('Error en formulario:', error);
                    this.showNetworkError(error);
                });
        });
    },

    /**
     * Recarga el token CSRF del formulario
     */
    refreshCSRFToken: function (form) {
        return fetch(window.location.href, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.text())
            .then(html => {
                // Extraer el nuevo token CSRF del HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newTokenInput = doc.querySelector('input[name="_csrf_token"]');

                if (newTokenInput) {
                    const currentTokenInput = form.querySelector('input[name="_csrf_token"]');
                    if (currentTokenInput) {
                        currentTokenInput.value = newTokenInput.value;
                        console.log('CSRF token actualizado:', newTokenInput.value.substring(0, 20) + '...');
                    }
                }
            })
            .catch(error => {
                console.error('Error al recargar CSRF token:', error);
            });
    }
};

// Auto-configurar formularios comunes al cargar la página
document.addEventListener('DOMContentLoaded', function () {
    // NOTA: El formulario de login NO se configura aquí para evitar conflictos con Symfony Security
    // El login debe usar el flujo tradicional de Symfony, no AJAX

    // Solo configurar otros formularios que no sean de autenticación principal
    // if (document.getElementById('loginForm')) {
    //     // Login deshabilitado para usar flujo tradicional de Symfony
    // }

    // Configurar formulario de recuperación de contraseña si existe
    if (document.getElementById('forgetPasswordForm')) {
        AuthAlerts.setupForm('forgetPasswordForm', {
            buttonId: 'sendCodeBtn',
            focusField: 'inputEmail'
        });
    }

    // Configurar formulario de verificación de código si existe
    if (document.getElementById('verifyCodeForm')) {
        AuthAlerts.setupForm('verifyCodeForm', {
            buttonId: 'changePasswordBtn'
        });
    }
});
