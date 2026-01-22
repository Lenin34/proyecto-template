/**
 * CrudManager - Clase reutilizable para gestión de CRUDs con DataTables
 * @version 1.0.0
 * @author app-ctm Team
 */

export class CrudManager {
    /**
     * @param {Object} config - Configuración del CRUD
     * @param {string} config.tableId - ID del elemento tabla
     * @param {string} config.dominio - Dominio actual
     * @param {string} config.entityName - Nombre de la entidad (singular)
     * @param {string} config.entityNamePlural - Nombre de la entidad (plural)
     * @param {Array} config.columnDefs - Definiciones personalizadas de columnas para DataTables
     * @param {Object} config.dataTableOptions - Opciones adicionales para DataTables
     */
    constructor(config) {
        this.config = {
            tableId: config.tableId || 'data-table',
            dominio: config.dominio,
            entityName: config.entityName || 'registro',
            entityNamePlural: config.entityNamePlural || 'registros',
            columnDefs: config.columnDefs || [],
            dataTableOptions: config.dataTableOptions || {},
            ...config
        };

        this.table = null;
        this.modalElement = null;
        this.modal = null;

        this.init();
    }

    /**
     * Inicializa el CRUD Manager
     */
    init() {
        this.initDataTable();
        this.initModal();
        this.attachEventHandlers();
    }

    /**
     * Inicializa DataTable con configuración en español
     */
    initDataTable() {
        const tableElement = document.getElementById(this.config.tableId);

        if (!tableElement) {
            console.warn(`Tabla con ID "${this.config.tableId}" no encontrada`);
            return;
        }

        // Configuración base de DataTables
        const baseConfig = {
            responsive: true,
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            order: [[1, 'asc']], // Ordenar por segunda columna (primera suele ser acciones)
            language: this.getSpanishTranslation(),
            columnDefs: [
                {
                    targets: 0, // Primera columna (acciones)
                    orderable: false,
                    searchable: false
                },
                ...this.config.columnDefs
            ],
            // Custom DOM structure for better styling control
            dom: '<"modern-table-wrapper"' +
                '<"row p-3 align-items-center"' +
                '<"col-sm-12 col-md-6"l>' +
                '<"col-sm-12 col-md-6"f>' +
                '>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row p-3 align-items-center"' +
                '<"col-sm-12 col-md-5"i>' +
                '<"col-sm-12 col-md-7"p>' +
                '>' +
                '>',
            drawCallback: () => {
                this.attachEventHandlers();
            },
            initComplete: function () {
                // Add modern class to table
                $(this).addClass('modern-datatable');

                // Customize search input
                const searchInput = $(this).closest('.dataTables_wrapper').find('.dataTables_filter input');
                searchInput.attr('placeholder', 'Buscar registros...');
                searchInput.removeClass('form-control-sm'); // Remove default small size if present

                // Remove the "Search:" label text but keep the input
                const searchLabel = $(this).closest('.dataTables_wrapper').find('.dataTables_filter label');
                // We want to keep the label element for the input but remove the text "Buscar:"
                // The text is usually a text node inside the label.
                searchLabel.contents().filter(function () {
                    return this.nodeType === 3; // Text nodes
                }).remove();
            }
        };

        // Merge con opciones personalizadas
        const finalConfig = { ...baseConfig, ...this.config.dataTableOptions };

        // Inicializar DataTable usando jQuery (desde CDN)
        this.table = $(`#${this.config.tableId}`).DataTable(finalConfig);
    }

    /**
     * Traducciones al español para DataTables
     */
    getSpanishTranslation() {
        return {
            "decimal": "",
            "emptyTable": `SIN ${this.config.entityNamePlural.toUpperCase()} DISPONIBLES`,
            "info": `Mostrando _START_ a _END_ de _TOTAL_ ${this.config.entityNamePlural}`,
            "infoEmpty": `Mostrando 0 a 0 de 0 ${this.config.entityNamePlural}`,
            "infoFiltered": `(filtrado de _MAX_ ${this.config.entityNamePlural} totales)`,
            "infoPostFix": "",
            "thousands": ",",
            "lengthMenu": `Mostrar _MENU_ ${this.config.entityNamePlural}`,
            "loadingRecords": "Cargando...",
            "processing": "Procesando...",
            "search": "Buscar:",
            "zeroRecords": `No se encontraron ${this.config.entityNamePlural} coincidentes`,
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            },
            "aria": {
                "sortAscending": ": activar para ordenar la columna de manera ascendente",
                "sortDescending": ": activar para ordenar la columna de manera descendente"
            }
        };
    }

    /**
     * Inicializa el modal dinámico
     */
    initModal() {
        this.modalElement = document.getElementById('dynamic-form-modal');
        this.modalContent = document.getElementById('dynamic-modal-content');

        if (this.modalElement && typeof bootstrap !== 'undefined') {
            this.modal = new bootstrap.Modal(this.modalElement);
        } else {
            console.warn('Modal no encontrado o Bootstrap no disponible');
        }
    }

    /**
     * Adjunta event handlers a botones de modal
     */
    attachEventHandlers() {
        // Botones que abren modal con AJAX (EDITAR)
        document.querySelectorAll('.ajax-modal-trigger').forEach(btn => {
            btn.removeEventListener('click', this.handleModalTrigger); // Evitar duplicados
            btn.addEventListener('click', this.handleModalTrigger.bind(this));
        });

        // Botones "Nuevo" - Todos los selectores posibles
        const newButtons = document.querySelectorAll('.new-user a, .new-entity a, .new-user-admin a, [data-new-entity]');
        newButtons.forEach(btn => {
            btn.removeEventListener('click', this.handleModalTrigger);
            btn.addEventListener('click', this.handleModalTrigger.bind(this));
        });
    }

    /**
     * Handler para abrir modal con contenido AJAX
     */
    handleModalTrigger(e) {
        e.preventDefault();
        const url = e.currentTarget.dataset.url || e.currentTarget.href;
        this.openModal(url);
    }

    /**
     * Abre el modal y carga contenido por AJAX
     * @param {string} url - URL para cargar el contenido
     */
    openModal(url) {
        if (!this.modal) {
            console.error('Modal no está inicializado');
            return;
        }

        console.log('[CrudManager] Opening modal with URL:', url);

        // Mostrar spinner mientras carga
        this.modalContent.innerHTML = `
            <div class="text-center p-5">
                <i class="fas fa-spinner fa-spin fa-3x text-white"></i>
                <p class="text-white mt-3">Cargando...</p>
            </div>
        `;

        this.modal.show();

        // Cargar contenido
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                console.log('[CrudManager] Response status:', response.status);
                console.log('[CrudManager] Response headers:', response.headers);

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.text();
            })
            .then(html => {
                console.log('[CrudManager] HTML received, length:', html.length);
                console.log('[CrudManager] HTML preview:', html.substring(0, 200));
                this.modalContent.innerHTML = html;
                this.initializeForm();
            })
            .catch(error => {
                console.error('[CrudManager] Error loading modal:', error);
                this.modalContent.innerHTML = `
                <div class="alert alert-danger m-3">
                    <h4>Error al cargar el formulario</h4>
                    <p>${error.message}</p>
                    <p class="small">URL: ${url}</p>
                    <p class="small">Revisa la consola para más detalles.</p>
                </div>
            `;
            });
    }

    /**
     * Inicializa el formulario dentro del modal
     */
    initializeForm() {
        // Buscar formulario de manera más flexible
        const form = this.modalContent.querySelector('form') ||
            document.getElementById('user-form') ||
            document.querySelector('form[name*="user"]') ||
            document.querySelector('form[name*="company"]') ||
            document.querySelector('form[name*="region"]');

        if (!form) {
            console.warn('Formulario no encontrado en modal');
            return;
        }

        console.log('Formulario detectado:', form.id || form.name || 'sin ID');


        // Cargar empresas si existe el select
        const companySelect = document.getElementById('company-select');
        if (companySelect) {
            this.loadCompanies(companySelect, form.dataset.companyId);
        }

        // Manejar submit del formulario
        form.addEventListener('submit', this.handleFormSubmit.bind(this, form));
    }

    /**
     * Carga empresas por AJAX
     */
    loadCompanies(selectElement, currentCompanyId = null) {
        const dominio = this.config.dominio;

        fetch(`/${dominio}/social-media/companies`)
            .then(response => response.json())
            .then(companies => {
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.name;
                    if (currentCompanyId && company.id == currentCompanyId) {
                        option.selected = true;
                    }
                    selectElement.appendChild(option);
                });
            })
            .catch(error => console.error('Error loading companies:', error));
    }

    /**
     * Maneja el submit del formulario
     */
    handleFormSubmit(form, e) {
        e.preventDefault();

        // Validación básica
        const companySelect = form.querySelector('#company-select');
        const errorDiv = form.querySelector('#company-error');

        if (companySelect && !companySelect.value) {
            if (errorDiv) errorDiv.style.display = 'block';
            companySelect.classList.add('is-invalid');
            return;
        }

        if (errorDiv) errorDiv.style.display = 'none';
        if (companySelect) companySelect.classList.remove('is-invalid');

        // Submit por AJAX
        const formData = new FormData(form);
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalBtnText = submitBtn.innerHTML;

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => {
                const contentType = response.headers.get('content-type');

                if (contentType && contentType.includes('application/json')) {
                    return response.json().then(data => {
                        if (data.status === 'success') {
                            // Éxito: recargar página
                            window.location.reload();
                        } else {
                            console.error('Form error:', data);
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalBtnText;
                        }
                    });
                } else {
                    // HTML = errores de validación
                    return response.text().then(html => {
                        this.modalContent.innerHTML = html;
                        this.initializeForm(); // Re-inicializar
                    });
                }
            })
            .catch(error => {
                console.error('Error submitting form:', error);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnText;
            });
    }

    /**
     * Recarga la tabla (útil después de operaciones CRUD)
     */
    reloadTable() {
        if (this.table) {
            this.table.ajax.reload();
        }
    }

    /**
     * Destruye la instancia de DataTable
     */
    destroy() {
        if (this.table) {
            this.table.destroy();
        }
    }
}
