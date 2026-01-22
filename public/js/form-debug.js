/**
 * Form Debug Helper
 * Sistema de debugging para formularios con campos textarea
 */

class FormDebugger {
    constructor() {
        this.debugMode = true; // Cambiar a false en producción
        this.logPrefix = '[FORM_DEBUG]';
    }

    /**
     * Inicializa el debugging para todos los formularios
     */
    init() {
        if (!this.debugMode) return;

        console.log(this.logPrefix, 'Form debugger initialized');
        
        // Interceptar todos los formularios
        document.querySelectorAll('form').forEach(form => {
            this.attachFormDebugger(form);
        });

        // Interceptar formularios dinámicos
        this.observeNewForms();
    }

    /**
     * Adjunta debugging a un formulario específico
     */
    attachFormDebugger(form) {
        if (form.hasAttribute('data-debug-attached')) return;
        form.setAttribute('data-debug-attached', 'true');

        console.log(this.logPrefix, 'Attaching debugger to form:', form);

        // Interceptar envío del formulario
        form.addEventListener('submit', (e) => {
            this.debugFormSubmission(form, e);
        });

        // Monitorear campos textarea específicamente
        const textareas = form.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            this.attachTextareaDebugger(textarea);
        });
    }

    /**
     * Debug específico para campos textarea
     */
    attachTextareaDebugger(textarea) {
        console.log(this.logPrefix, 'Monitoring textarea:', textarea.name);

        // Monitorear cambios en tiempo real
        textarea.addEventListener('input', () => {
            this.validateTextareaContent(textarea);
        });

        // Validar antes del envío
        textarea.addEventListener('blur', () => {
            this.validateTextareaContent(textarea);
        });
    }

    /**
     * Valida el contenido de un textarea
     */
    validateTextareaContent(textarea) {
        const value = textarea.value;
        const issues = [];

        // Verificar longitud
        if (value.length > 65535) {
            issues.push('Texto demasiado largo (máximo 65535 caracteres)');
        }

        // Verificar caracteres problemáticos
        if (value.includes('\0')) {
            issues.push('Contiene bytes nulos');
        }

        // Verificar encoding
        try {
            const encoded = encodeURIComponent(value);
            if (encoded.includes('%00')) {
                issues.push('Contiene caracteres de control');
            }
        } catch (e) {
            issues.push('Error de encoding');
        }

        // Verificar líneas muy largas
        const lines = value.split('\n');
        const longLines = lines.filter(line => line.length > 1000);
        if (longLines.length > 0) {
            issues.push(`${longLines.length} líneas muy largas (>1000 caracteres)`);
        }

        if (issues.length > 0) {
            console.warn(this.logPrefix, 'Textarea issues detected:', {
                field: textarea.name,
                issues: issues,
                length: value.length,
                lines: lines.length
            });

            // Mostrar advertencia visual
            this.showTextareaWarning(textarea, issues);
        } else {
            this.clearTextareaWarning(textarea);
        }

        return issues.length === 0;
    }

    /**
     * Muestra advertencia visual en textarea
     */
    showTextareaWarning(textarea, issues) {
        // Remover advertencia anterior
        this.clearTextareaWarning(textarea);

        // Agregar clase de advertencia
        textarea.classList.add('textarea-warning');

        // Crear elemento de advertencia
        const warning = document.createElement('div');
        warning.className = 'textarea-debug-warning alert alert-warning mt-2';
        warning.innerHTML = `
            <strong>⚠️ Advertencias en el campo:</strong>
            <ul class="mb-0 mt-1">
                ${issues.map(issue => `<li>${issue}</li>`).join('')}
            </ul>
        `;
        warning.setAttribute('data-debug-warning', 'true');

        // Insertar después del textarea
        textarea.parentNode.insertBefore(warning, textarea.nextSibling);
    }

    /**
     * Limpia advertencias visuales
     */
    clearTextareaWarning(textarea) {
        textarea.classList.remove('textarea-warning');
        
        // Remover elementos de advertencia
        const warnings = textarea.parentNode.querySelectorAll('[data-debug-warning="true"]');
        warnings.forEach(warning => warning.remove());
    }

    /**
     * Debug del envío del formulario
     */
    debugFormSubmission(form, event) {
        console.group(this.logPrefix + ' Form Submission Debug');
        
        const formData = new FormData(form);
        const debugData = {
            form: {
                action: form.action,
                method: form.method,
                enctype: form.enctype,
                fieldCount: form.elements.length
            },
            fields: {},
            textareas: {},
            issues: []
        };

        // Analizar todos los campos
        for (let [key, value] of formData.entries()) {
            debugData.fields[key] = {
                type: typeof value,
                length: value.toString().length,
                value: value.toString().substring(0, 100) + (value.toString().length > 100 ? '...' : '')
            };
        }

        // Análisis específico de textareas
        const textareas = form.querySelectorAll('textarea');
        textareas.forEach(textarea => {
            const isValid = this.validateTextareaContent(textarea);
            debugData.textareas[textarea.name] = {
                valid: isValid,
                length: textarea.value.length,
                lines: textarea.value.split('\n').length,
                encoding: this.detectEncoding(textarea.value)
            };

            if (!isValid) {
                debugData.issues.push(`Textarea ${textarea.name} has validation issues`);
            }
        });

        console.log('Form Debug Data:', debugData);

        // Si hay problemas críticos, preguntar al usuario
        if (debugData.issues.length > 0) {
            console.warn('Issues detected:', debugData.issues);
            
            if (window.Swal) {
                event.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Problemas detectados en el formulario',
                    html: `
                        <p>Se detectaron los siguientes problemas:</p>
                        <ul class="text-left">
                            ${debugData.issues.map(issue => `<li>${issue}</li>`).join('')}
                        </ul>
                        <p>¿Desea enviar el formulario de todos modos?</p>
                    `,
                    showCancelButton: true,
                    confirmButtonText: 'Enviar de todos modos',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Remover el event listener temporalmente y reenviar
                        form.removeEventListener('submit', this.debugFormSubmission);
                        form.submit();
                    }
                });
            }
        }

        console.groupEnd();
    }

    /**
     * Detecta el encoding de un string
     */
    detectEncoding(str) {
        try {
            const utf8 = encodeURIComponent(str);
            return 'UTF-8';
        } catch (e) {
            return 'Unknown';
        }
    }

    /**
     * Observa nuevos formularios agregados dinámicamente
     */
    observeNewForms() {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node.nodeType === Node.ELEMENT_NODE) {
                        // Verificar si el nodo es un formulario
                        if (node.tagName === 'FORM') {
                            this.attachFormDebugger(node);
                        }
                        // Verificar formularios dentro del nodo
                        const forms = node.querySelectorAll ? node.querySelectorAll('form') : [];
                        forms.forEach(form => this.attachFormDebugger(form));
                    }
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    /**
     * Función para debugging manual
     */
    debugForm(formSelector) {
        const form = document.querySelector(formSelector);
        if (form) {
            this.debugFormSubmission(form, { preventDefault: () => {} });
        } else {
            console.error(this.logPrefix, 'Form not found:', formSelector);
        }
    }
}

// CSS para las advertencias
const debugCSS = `
    .textarea-warning {
        border-color: #ffc107 !important;
        box-shadow: 0 0 0 0.2rem rgba(255, 193, 7, 0.25) !important;
    }
    
    .textarea-debug-warning {
        font-size: 0.875rem;
    }
    
    .textarea-debug-warning ul {
        padding-left: 1.5rem;
    }
`;

// Inyectar CSS
const style = document.createElement('style');
style.textContent = debugCSS;
document.head.appendChild(style);

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.formDebugger = new FormDebugger();
    window.formDebugger.init();
});

// Función global para debugging manual
window.debugForm = function(selector) {
    if (window.formDebugger) {
        window.formDebugger.debugForm(selector);
    }
};
