/**
 * Bulk Upload Manager
 * Maneja la carga masiva de usuarios con parseo en frontend usando SheetJS
 * 
 * @author MasoftCode
 * @version 1.0.0
 * @requires SheetJS (xlsx.js)
 * @requires bulk-upload-errors.js
 */

class BulkUploadManager {
    constructor(options = {}) {
        // Configuración
        this.config = {
            modalId: options.modalId || 'modalCargaMasiva',
            fileInputId: options.fileInputId || 'bulkUploadFile',
            formId: options.formId || 'bulkUploadForm',
            previewContainerId: options.previewContainerId || 'bulkUploadPreview',
            submitBtnId: options.submitBtnId || 'bulkUploadSubmitBtn',
            uploadUrl: options.uploadUrl || '',
            checkDuplicatesUrl: options.checkDuplicatesUrl || '',
            bulkUploadAjaxUrl: options.bulkUploadAjaxUrl || '',
            maxFileSize: options.maxFileSize || 5 * 1024 * 1024, // 5MB
            rowsPerPage: options.rowsPerPage || 10,
            allowedExtensions: ['.xlsx', '.xls'],
            requiredColumns: ['NOMBRE', 'APELLIDOS'],
            expectedHeaders: [
                'NOMBRE', 'APELLIDOS', 'EMPRESA', 'FECHA DE NACIMIENTO',
                'TELÉFONO', 'CORREO ELECTRÓNICO', 'N° DE EMPLEADO',
                'CURP', 'GENERO', 'EDUCACIÓN', 'REGIÓN'
            ]
        };

        // Estado
        this.state = {
            file: null,
            parsedData: [],
            validatedData: [],
            currentPage: 1,
            totalPages: 1,
            isLoading: false,
            emailsInFile: new Set(),
            searchQuery: '',
            filterMode: 'all' // 'all', 'valid', 'errors'
        };

        // Gestor de errores
        this.errorHandler = new BulkUploadErrorHandler();

        // Inicializar
        this.init();
    }

    /**
     * Inicializa el manager
     */
    init() {
        // Verificar dependencias
        if (typeof XLSX === 'undefined') {
            console.error('SheetJS (XLSX) library not loaded. Loading from CDN...');
            this.loadXLSXLibrary();
        }

        this.bindEvents();
    }

    /**
     * Carga la librería XLSX dinámicamente
     */
    loadXLSXLibrary() {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js';
        script.onload = () => console.log('SheetJS loaded successfully');
        script.onerror = () => {
            this.errorHandler.addError(BulkUploadErrorCodes.LIBRARY_NOT_LOADED);
            this.errorHandler.showAlert();
        };
        document.head.appendChild(script);
    }

    /**
     * Vincula eventos del DOM
     */
    bindEvents() {
        const bindEventHandlers = () => {
            const fileInput = document.getElementById(this.config.fileInputId);
            const modal = document.getElementById(this.config.modalId);

            console.log('[BulkUpload] Binding events...', { fileInput: !!fileInput, modal: !!modal });

            if (fileInput) {
                fileInput.addEventListener('change', (e) => {
                    console.log('[BulkUpload] File selected:', e.target.files[0]?.name);
                    this.handleFileSelect(e);
                });
            } else {
                console.error('[BulkUpload] File input not found:', this.config.fileInputId);
            }

            if (modal) {
                modal.addEventListener('hidden.bs.modal', () => this.resetState());
            }
        };

        // Si el DOM ya está cargado, ejecutar inmediatamente
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bindEventHandlers);
        } else {
            // DOM ya está listo, ejecutar ahora
            bindEventHandlers();
        }
    }

    /**
     * Maneja la selección de archivo
     */
    async handleFileSelect(event) {
        const file = event.target.files[0];

        if (!file) {
            this.errorHandler.addError(BulkUploadErrorCodes.FILE_NOT_SELECTED);
            return;
        }

        // Validar tipo de archivo
        const extension = '.' + file.name.split('.').pop().toLowerCase();
        if (!this.config.allowedExtensions.includes(extension)) {
            this.errorHandler.addError(BulkUploadErrorCodes.FILE_INVALID_TYPE, {
                fileName: file.name,
                extension
            });
            this.errorHandler.showAlert();
            event.target.value = '';
            return;
        }

        // Validar tamaño
        if (file.size > this.config.maxFileSize) {
            this.errorHandler.addError(BulkUploadErrorCodes.FILE_TOO_LARGE, {
                fileSize: (file.size / 1024 / 1024).toFixed(2) + 'MB',
                maxSize: (this.config.maxFileSize / 1024 / 1024) + 'MB'
            });
            this.errorHandler.showAlert();
            event.target.value = '';
            return;
        }

        this.state.file = file;
        this.showLoading(true);

        try {
            await this.parseExcel(file);
            await this.validateDuplicatesRemote(); // Nueva validación remota
            this.renderPreview(); // Renderizar después de validar todo
        } catch (error) {
            console.error('Error parsing Excel:', error);
            this.errorHandler.addError(BulkUploadErrorCodes.PARSE_FAILED, { error: error.message });
            this.errorHandler.showAlert();
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Parsea el archivo Excel
     */
    parseExcel(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();

            reader.onload = (e) => {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array', cellDates: true });

                    // Obtener primera hoja
                    const sheetName = workbook.SheetNames[0];
                    const worksheet = workbook.Sheets[sheetName];

                    // Convertir a JSON
                    const jsonData = XLSX.utils.sheet_to_json(worksheet, {
                        header: 1,
                        raw: false,
                        dateNF: 'yyyy-mm-dd'
                    });

                    if (!jsonData || jsonData.length === 0) {
                        this.errorHandler.addError(BulkUploadErrorCodes.FILE_EMPTY);
                        this.errorHandler.showAlert();
                        reject(new Error('Empty file'));
                        return;
                    }

                    // Validar encabezados
                    const headers = jsonData[0];
                    if (!this.validateHeaders(headers)) {
                        reject(new Error('Invalid headers'));
                        return;
                    }

                    // Procesar filas de datos
                    this.processRows(jsonData);

                    resolve(this.state.parsedData);

                } catch (error) {
                    this.errorHandler.addError(BulkUploadErrorCodes.FILE_CORRUPTED, { error: error.message });
                    reject(error);
                }
            };

            reader.onerror = () => {
                this.errorHandler.addError(BulkUploadErrorCodes.FILE_READ_ERROR);
                reject(new Error('File read error'));
            };

            reader.readAsArrayBuffer(file);
        });
    }

    /**
     * Valida los encabezados del Excel
     */
    validateHeaders(headers) {
        if (!headers || headers.length < 2) {
            this.errorHandler.addError(BulkUploadErrorCodes.PARSE_MISSING_COLUMNS, {
                found: headers ? headers.length : 0,
                expected: this.config.expectedHeaders.length
            });
            this.errorHandler.showAlert();
            return false;
        }

        // Normalizar encabezados
        const normalizedHeaders = headers.map(h =>
            h ? h.toString().toUpperCase().trim() : ''
        );

        // Verificar columnas requeridas
        const missingRequired = this.config.requiredColumns.filter(col =>
            !normalizedHeaders.some(h => h.includes(col))
        );

        if (missingRequired.length > 0) {
            this.errorHandler.addError(BulkUploadErrorCodes.PARSE_MISSING_COLUMNS, {
                missing: missingRequired
            });
            this.errorHandler.showAlert();
            return false;
        }

        return true;
    }

    /**
     * Procesa las filas del Excel
     */
    processRows(jsonData) {
        this.state.parsedData = [];
        this.state.validatedData = [];
        this.state.emailsInFile.clear();
        this.errorHandler.clear();

        const headers = jsonData[0];
        const dataRows = jsonData.slice(1);

        dataRows.forEach((row, index) => {
            const rowNumber = index + 2; // +2 porque Excel empieza en 1 y la fila 1 es headers

            // Verificar si la fila está vacía
            if (!row || row.every(cell => !cell || cell.toString().trim() === '')) {
                return; // Skip empty rows silently
            }

            const rowData = this.mapRowToObject(row, headers);
            rowData._rowNumber = rowNumber;
            rowData._errors = [];
            rowData._isValid = true;

            // Validar fila
            this.validateRow(rowData);

            this.state.parsedData.push(rowData);

            if (rowData._isValid || rowData._errors.every(e => e.severity !== 'error')) {
                this.state.validatedData.push(rowData);
            }
        });

        // Actualizar paginación
        this.state.totalPages = Math.ceil(this.state.parsedData.length / this.config.rowsPerPage);
        this.state.currentPage = 1;

        if (this.state.parsedData.length === 0) {
            this.errorHandler.addError(BulkUploadErrorCodes.PARSE_NO_DATA);
        }
    }

    /**
     * Mapea una fila a un objeto con nombres de columnas
     */
    mapRowToObject(row, headers) {
        const obj = {};

        // Mapeo de headers a propiedades
        const headerMap = {
            'NOMBRE': 'nombre',
            'APELLIDOS': 'apellidos',
            'EMPRESA': 'empresa',
            'FECHA DE NACIMIENTO': 'fechaNacimiento',
            'TELÉFONO': 'telefono',
            'CORREO ELECTRÓNICO': 'email',
            'N° DE EMPLEADO': 'numEmpleado',
            'CURP': 'curp',
            'GENERO': 'genero',
            'GÉNERO': 'genero',
            'EDUCACIÓN': 'educacion',
            'EDUCACION': 'educacion',
            'REGIÓN': 'region',
            'REGION': 'region'
        };

        headers.forEach((header, index) => {
            const normalizedHeader = header ? header.toString().toUpperCase().trim() : '';
            const propName = headerMap[normalizedHeader] || `col${index}`;
            obj[propName] = row[index] !== undefined ? row[index].toString().trim() : '';
        });

        return obj;
    }

    /**
     * Valida una fila de datos
     */
    validateRow(rowData) {
        const rowNumber = rowData._rowNumber;

        // Validar campos requeridos
        if (!rowData.nombre || !rowData.apellidos) {
            rowData._errors.push({
                code: BulkUploadErrorCodes.ROW_MISSING_REQUIRED,
                message: 'Nombre y Apellidos son requeridos',
                severity: 'error'
            });
            rowData._isValid = false;
            this.errorHandler.addError(BulkUploadErrorCodes.ROW_MISSING_REQUIRED, { row: rowNumber });
        }

        // Validar email
        if (rowData.email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(rowData.email)) {
                rowData._errors.push({
                    code: BulkUploadErrorCodes.ROW_INVALID_EMAIL,
                    message: 'Email inválido',
                    severity: 'warning'
                });
                this.errorHandler.addError(BulkUploadErrorCodes.ROW_INVALID_EMAIL, {
                    row: rowNumber,
                    email: rowData.email
                });
            }

            // Verificar duplicados en el archivo
            if (this.state.emailsInFile.has(rowData.email.toLowerCase())) {
                rowData._errors.push({
                    code: BulkUploadErrorCodes.ROW_DUPLICATE_EMAIL,
                    message: 'Email duplicado en el archivo',
                    severity: 'error'
                });
                rowData._isValid = false;
                this.errorHandler.addError(BulkUploadErrorCodes.ROW_DUPLICATE_EMAIL, {
                    row: rowNumber,
                    email: rowData.email
                });
            } else {
                this.state.emailsInFile.add(rowData.email.toLowerCase());
            }
        }

        // Validar CURP (18 caracteres alfanuméricos)
        if (rowData.curp) {
            const curpRegex = /^[A-Z]{4}[0-9]{6}[HM][A-Z]{5}[A-Z0-9][0-9]$/i;
            if (rowData.curp.length !== 18 && rowData.curp.length > 0) {
                rowData._errors.push({
                    code: BulkUploadErrorCodes.ROW_INVALID_CURP,
                    message: 'CURP debe tener 18 caracteres',
                    severity: 'warning'
                });
                this.errorHandler.addError(BulkUploadErrorCodes.ROW_INVALID_CURP, {
                    row: rowNumber,
                    curp: rowData.curp
                });
            }
        }

        // Validar fecha de nacimiento
        if (rowData.fechaNacimiento) {
            const dateFormats = [
                /^\d{4}-\d{2}-\d{2}$/,           // YYYY-MM-DD
                /^\d{2}\/\d{2}\/\d{4}$/,         // DD/MM/YYYY
                /^\d{2}-\d{2}-\d{4}$/            // DD-MM-YYYY
            ];

            const isValidDate = dateFormats.some(regex => regex.test(rowData.fechaNacimiento));
            if (!isValidDate && rowData.fechaNacimiento.length > 0) {
                rowData._errors.push({
                    code: BulkUploadErrorCodes.ROW_INVALID_DATE,
                    message: 'Formato de fecha inválido',
                    severity: 'warning'
                });
                this.errorHandler.addError(BulkUploadErrorCodes.ROW_INVALID_DATE, {
                    row: rowNumber,
                    date: rowData.fechaNacimiento
                });
            }
        }

        return rowData._isValid;
    }

    /**
     * Valida duplicados contra el servidor
     */
    async validateDuplicatesRemote() {
        if (!this.config.checkDuplicatesUrl || this.state.emailsInFile.size === 0) {
            return;
        }

        const emails = Array.from(this.state.emailsInFile);

        try {
            const response = await fetch(this.config.checkDuplicatesUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ emails })
            });

            if (!response.ok) throw new Error('Network response was not ok');

            const data = await response.json();
            const duplicates = new Set(data.duplicates.map(e => e.toLowerCase()));

            if (duplicates.size > 0) {
                this.state.parsedData.forEach(row => {
                    if (row.email && duplicates.has(row.email.toLowerCase())) {
                        row._isValid = false;
                        row._errors.push({
                            code: BulkUploadErrorCodes.ROW_DUPLICATE_EMAIL,
                            message: 'Email ya registrado en el sistema',
                            severity: 'error'
                        });

                        this.errorHandler.addError(BulkUploadErrorCodes.ROW_DUPLICATE_EMAIL, {
                            row: row._rowNumber,
                            email: row.email
                        });
                    }
                });
            }

        } catch (error) {
            console.error('Error checking duplicates:', error);
            // No bloqueamos el proceso si falla la validación remota, pero avisamos
            this.errorHandler.addError(BulkUploadErrorCodes.SUBMIT_NETWORK_ERROR, {
                details: 'No se pudo verificar duplicados en el servidor'
            });
        }
    }

    /**
     * Muestra/oculta el indicador de carga
     */
    showLoading(show) {
        this.state.isLoading = show;

        const container = document.getElementById(this.config.previewContainerId);
        const step1 = document.getElementById('bulkUploadStep1');

        if (!container) {
            console.error('[BulkUpload] Preview container not found');
            return;
        }

        if (show) {
            // Ocultar Step 1 (selección de archivo)
            if (step1) step1.style.display = 'none';

            container.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status" style="width: 4rem; height: 4rem;">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-3 text-white">Procesando archivo Excel...</p>
                    <p class="small text-white-50">Esto puede tomar unos segundos</p>
                </div>
            `;
            container.style.display = 'block';
        }
    }

    /**
     * Obtiene los datos filtrados según búsqueda y modo de filtro
     */
    getFilteredData() {
        let data = this.state.parsedData;

        // Aplicar filtro por estado
        if (this.state.filterMode === 'valid') {
            data = data.filter(r => r._isValid);
        } else if (this.state.filterMode === 'errors') {
            data = data.filter(r => !r._isValid);
        }

        // Aplicar búsqueda
        if (this.state.searchQuery.trim()) {
            const query = this.state.searchQuery.toLowerCase().trim();
            data = data.filter(row => {
                return (row.nombre && row.nombre.toLowerCase().includes(query)) ||
                       (row.apellidos && row.apellidos.toLowerCase().includes(query)) ||
                       (row.email && row.email.toLowerCase().includes(query)) ||
                       (row.empresa && row.empresa.toLowerCase().includes(query)) ||
                       (row.telefono && row.telefono.toLowerCase().includes(query)) ||
                       (row._rowNumber && row._rowNumber.toString().includes(query));
            });
        }

        return data;
    }

    /**
     * Maneja el cambio en el campo de búsqueda
     */
    handleSearch(query) {
        this.state.searchQuery = query;
        this.state.currentPage = 1;
        this.renderPreview();
    }

    /**
     * Maneja el cambio de filtro
     */
    handleFilterChange(mode) {
        this.state.filterMode = mode;
        this.state.currentPage = 1;
        this.renderPreview();
    }

    /**
     * Renderiza la tabla de preview
     */
    renderPreview() {
        const container = document.getElementById(this.config.previewContainerId);
        const step1 = document.getElementById('bulkUploadStep1');

        if (!container) {
            console.error('[BulkUpload] Preview container not found');
            return;
        }

        // Ocultar Step 1
        if (step1) step1.style.display = 'none';

        // Obtener datos totales para estadísticas
        const allData = this.state.parsedData;
        const totalValidCount = allData.filter(r => r._isValid).length;
        const totalErrorCount = allData.length - totalValidCount;

        // Obtener datos filtrados para la tabla
        const filteredData = this.getFilteredData();
        const { currentPage } = this.state;
        const { rowsPerPage } = this.config;

        // Recalcular paginación basada en datos filtrados
        const filteredTotalPages = Math.max(1, Math.ceil(filteredData.length / rowsPerPage));
        this.state.totalPages = filteredTotalPages;

        // Ajustar página actual si es necesario
        if (currentPage > filteredTotalPages) {
            this.state.currentPage = filteredTotalPages;
        }

        const startIndex = (this.state.currentPage - 1) * rowsPerPage;
        const endIndex = startIndex + rowsPerPage;
        const pageData = filteredData.slice(startIndex, endIndex);

        let html = `
            <div class="bulk-upload-preview">
                <!-- Resumen -->
                <div class="preview-summary mb-3 p-3 bg-dark rounded">
                    <div class="row text-center">
                        <div class="col-4">
                            <span class="fs-4 fw-bold text-white">${allData.length}</span>
                            <br><small class="text-white-50">Total registros</small>
                        </div>
                        <div class="col-4">
                            <span class="fs-4 fw-bold text-success">${totalValidCount}</span>
                            <br><small class="text-white-50">Válidos</small>
                        </div>
                        <div class="col-4">
                            <span class="fs-4 fw-bold ${totalErrorCount > 0 ? 'text-danger' : 'text-success'}">${totalErrorCount}</span>
                            <br><small class="text-white-50">Con errores</small>
                        </div>
                    </div>
                    ${totalErrorCount > 0 ? `
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-danger btn-sm" onclick="bulkUploadManager.discardErrors()">
                                <i class="fas fa-trash-alt me-2"></i>Descartar todos los errores (${totalErrorCount})
                            </button>
                        </div>
                    ` : ''}
                </div>

                <!-- Barra de búsqueda y filtros -->
                <div class="search-filter-bar mb-3 p-2 bg-dark rounded d-flex flex-wrap gap-2 align-items-center">
                    <!-- Campo de búsqueda -->
                    <div class="flex-grow-1" style="min-width: 200px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-secondary border-secondary text-white">
                                <i class="fas fa-search"></i>
                            </span>
                            <input type="text"
                                   class="form-control bg-dark text-white border-secondary"
                                   placeholder="Buscar por nombre, email, empresa..."
                                   value="${this.escapeHtml(this.state.searchQuery)}"
                                   onkeyup="bulkUploadManager.handleSearch(this.value)"
                                   id="bulkUploadSearchInput">
                            ${this.state.searchQuery ? `
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="bulkUploadManager.handleSearch(''); document.getElementById('bulkUploadSearchInput').value = '';">
                                    <i class="fas fa-times"></i>
                                </button>
                            ` : ''}
                        </div>
                    </div>

                    <!-- Filtros por estado -->
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button"
                                class="btn ${this.state.filterMode === 'all' ? 'btn-primary' : 'btn-outline-secondary'}"
                                onclick="bulkUploadManager.handleFilterChange('all')">
                            <i class="fas fa-list me-1"></i>Todos (${allData.length})
                        </button>
                        <button type="button"
                                class="btn ${this.state.filterMode === 'valid' ? 'btn-success' : 'btn-outline-secondary'}"
                                onclick="bulkUploadManager.handleFilterChange('valid')">
                            <i class="fas fa-check me-1"></i>Válidos (${totalValidCount})
                        </button>
                        <button type="button"
                                class="btn ${this.state.filterMode === 'errors' ? 'btn-danger' : 'btn-outline-secondary'}"
                                onclick="bulkUploadManager.handleFilterChange('errors')"
                                ${totalErrorCount === 0 ? 'disabled' : ''}>
                            <i class="fas fa-exclamation-circle me-1"></i>Errores (${totalErrorCount})
                        </button>
                    </div>
                </div>

                <!-- Indicador de resultados filtrados -->
                ${(this.state.searchQuery || this.state.filterMode !== 'all') ? `
                    <div class="alert alert-info py-2 px-3 mb-2 d-flex justify-content-between align-items-center" style="background-color: rgba(13, 202, 240, 0.1); border-color: rgba(13, 202, 240, 0.3);">
                        <small class="text-info">
                            <i class="fas fa-filter me-1"></i>
                            Mostrando ${filteredData.length} de ${allData.length} registros
                            ${this.state.searchQuery ? ` que coinciden con "${this.escapeHtml(this.state.searchQuery)}"` : ''}
                            ${this.state.filterMode !== 'all' ? ` (filtro: ${this.state.filterMode === 'valid' ? 'válidos' : 'con errores'})` : ''}
                        </small>
                        <button class="btn btn-link btn-sm text-info p-0"
                                onclick="bulkUploadManager.clearFilters()">
                            <i class="fas fa-times me-1"></i>Limpiar filtros
                        </button>
                    </div>
                ` : ''}

                <!-- Tabla -->
                <div class="table-responsive" style="max-height: 350px; overflow-y: auto;">
                    <table class="table table-dark table-striped table-hover mb-0 align-middle">
                        <thead class="sticky-top">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Nombre</th>
                                <th>Apellidos</th>
                                <th>Empresa</th>
                                <th>Email</th>
                                <th>Teléfono</th>
                                <th>Región</th>
                                <th style="width: 150px;">Estado</th>
                                <th style="width: 50px;"></th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        if (pageData.length === 0) {
            html += `
                <tr>
                    <td colspan="9" class="text-center text-white-50 py-4">
                        ${this.state.searchQuery || this.state.filterMode !== 'all'
                            ? '<i class="fas fa-search me-2"></i>No se encontraron registros con los filtros aplicados'
                            : 'No hay datos para mostrar'}
                    </td>
                </tr>
            `;
        } else {
            pageData.forEach((row) => {
                // Encontrar el índice real en parsedData para poder eliminar correctamente
                const globalIndex = this.state.parsedData.findIndex(r => r._rowNumber === row._rowNumber);

                let statusHtml = '';
                if (row._isValid) {
                    statusHtml = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Válido</span>';
                } else {
                    const firstError = row._errors[0]?.message || 'Error desconocido';
                    const errCount = row._errors.length;
                    const moreErrors = errCount > 1 ? ` (+${errCount - 1})` : '';

                    statusHtml = `
                        <div class="text-danger small fw-bold" title="${row._errors.map(e => e.message).join(', ')}">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            ${firstError}${moreErrors}
                        </div>
                    `;
                }

                const rowClass = row._isValid ? '' : 'bg-danger bg-opacity-10';

                html += `
                    <tr class="${rowClass}">
                        <td>${row._rowNumber}</td>
                        <td>${this.highlightSearch(row.nombre || '-')}</td>
                        <td>${this.highlightSearch(row.apellidos || '-')}</td>
                        <td>${this.highlightSearch(row.empresa || '-')}</td>
                        <td>${this.highlightSearch(row.email || '-')}</td>
                        <td>${this.highlightSearch(row.telefono || '-')}</td>
                        <td>${this.escapeHtml(row.region || '-')}</td>
                        <td>${statusHtml}</td>
                        <td class="text-end">
                            <button class="btn btn-link text-danger p-0" onclick="bulkUploadManager.removeRow(${globalIndex})" title="Eliminar fila">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        html += `
                        </tbody>
                    </table>
                </div>

                <!-- Paginación -->
                ${this.renderPagination(filteredData.length)}

                <!-- Botones de acción -->
                <div class="d-flex justify-content-between mt-3">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancelar
                    </button>
                    <button type="button" class="btn btn-success"
                            onclick="bulkUploadManager.submitUsers()"
                            ${totalValidCount === 0 ? 'disabled' : ''}>
                        <i class="fas fa-upload me-2"></i>
                        Subir ${totalValidCount} Usuario${totalValidCount !== 1 ? 's' : ''}
                    </button>
                </div>
            </div>
        `;

        container.innerHTML = html;
        container.style.display = 'block';

        // Log de validación
        if (this.errorHandler.hasErrors() || this.errorHandler.hasWarnings()) {
            console.log('[BulkUpload] Validation summary:', this.errorHandler.getSummary());
        }
    }

    /**
     * Limpia todos los filtros
     */
    clearFilters() {
        this.state.searchQuery = '';
        this.state.filterMode = 'all';
        this.state.currentPage = 1;
        this.renderPreview();
    }

    /**
     * Resalta el texto de búsqueda en un string
     */
    highlightSearch(text) {
        if (!text || !this.state.searchQuery.trim()) {
            return this.escapeHtml(text);
        }

        const escaped = this.escapeHtml(text);
        const query = this.state.searchQuery.trim();
        const regex = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');

        return escaped.replace(regex, '<mark class="bg-warning text-dark px-0">$1</mark>');
    }

    /**
     * Renderiza la paginación
     */
    renderPagination() {
        const { currentPage, totalPages } = this.state;

        if (totalPages <= 1) return '';

        let html = `
            <nav class="mt-3">
                <ul class="pagination pagination-sm justify-content-center mb-0">
                    <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="bulkUploadManager.goToPage(${currentPage - 1}); return false;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
        `;

        // Mostrar máximo 5 páginas
        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, startPage + 4);

        for (let i = startPage; i <= endPage; i++) {
            html += `
                <li class="page-item ${i === currentPage ? 'active' : ''}">
                    <a class="page-link" href="#" onclick="bulkUploadManager.goToPage(${i}); return false;">${i}</a>
                </li>
            `;
        }

        html += `
                    <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                        <a class="page-link" href="#" onclick="bulkUploadManager.goToPage(${currentPage + 1}); return false;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
                <div class="text-center text-muted mt-1">
                    <small>Página ${currentPage} de ${totalPages}</small>
                </div>
            </nav>
        `;

        return html;
    }

    /**
     * Navega a una página específica
     */
    goToPage(page) {
        if (page < 1 || page > this.state.totalPages) return;
        this.state.currentPage = page;
        this.renderPreview();
    }

    /**
     * Elimina una fila específica
     */
    removeRow(index) {
        if (index >= 0 && index < this.state.parsedData.length) {
            this.state.parsedData.splice(index, 1);

            // Recalcular paginación si es necesario
            const totalPages = Math.ceil(this.state.parsedData.length / this.config.rowsPerPage);
            this.state.totalPages = Math.max(1, totalPages);
            if (this.state.currentPage > this.state.totalPages) {
                this.state.currentPage = this.state.totalPages;
            }

            this.renderPreview();
        }
    }

    /**
     * Descarta todas las filas con errores
     */
    discardErrors() {
        const initialCount = this.state.parsedData.length;
        this.state.parsedData = this.state.parsedData.filter(row => row._isValid);
        const removedCount = initialCount - this.state.parsedData.length;

        if (removedCount > 0) {
            // Recalcular paginación
            const totalPages = Math.ceil(this.state.parsedData.length / this.config.rowsPerPage);
            this.state.totalPages = Math.max(1, totalPages);
            this.state.currentPage = 1;

            this.renderPreview();

            Swal.fire({
                icon: 'success',
                title: 'Errores descartados',
                text: `Se han eliminado ${removedCount} filas con errores.`,
                timer: 2000,
                showConfirmButton: false,
                customClass: { popup: 'swal-dark-popup' }
            });
        }
    }

    /**
     * Envía los usuarios válidos al servidor via AJAX
     */
    async submitUsers() {
        const validData = this.state.parsedData.filter(r => r._isValid);

        if (validData.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Sin datos válidos',
                text: 'No hay usuarios válidos para importar.',
                confirmButtonColor: '#0B3F61'
            });
            return;
        }

        // Confirmar antes de enviar
        const result = await Swal.fire({
            icon: 'question',
            title: '¿Confirmar importación?',
            html: `Se importarán <strong>${validData.length}</strong> usuario(s) al sistema.`,
            showCancelButton: true,
            confirmButtonText: 'Sí, importar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#28a745',
            cancelButtonColor: '#6c757d'
        });

        if (!result.isConfirmed) return;

        // Mostrar loading
        Swal.fire({
            title: 'Importando usuarios...',
            html: 'Por favor espera mientras se procesan los datos.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        try {
            // Preparar datos para enviar (solo campos necesarios, sin metadatos internos)
            const usersToSend = validData.map(row => ({
                nombre: row.nombre,
                apellidos: row.apellidos,
                empresa: row.empresa,
                fechaNacimiento: row.fechaNacimiento,
                telefono: row.telefono,
                email: row.email,
                numEmpleado: row.numEmpleado,
                curp: row.curp,
                genero: row.genero,
                educacion: row.educacion,
                region: row.region,
                _rowNumber: row._rowNumber
            }));

            // Enviar via AJAX al nuevo endpoint
            const response = await fetch(this.config.bulkUploadAjaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ users: usersToSend })
            });

            const data = await response.json();

            if (!response.ok) {
                throw new Error(data.message || 'Error en el servidor');
            }

            // Mostrar resultado
            if (data.success) {
                let resultHtml = `<p>Se importaron <strong>${data.usersAdded}</strong> de ${data.totalReceived} usuario(s).</p>`;

                if (data.errors && data.errors.length > 0) {
                    resultHtml += `<div class="text-start mt-3"><p class="text-warning mb-2">Algunos registros tuvieron errores:</p><ul class="small">`;
                    data.errors.slice(0, 5).forEach(err => {
                        resultHtml += `<li>Fila ${err.row}: ${err.message}</li>`;
                    });
                    if (data.errors.length > 5) {
                        resultHtml += `<li>... y ${data.errors.length - 5} más</li>`;
                    }
                    resultHtml += `</ul></div>`;
                }

                await Swal.fire({
                    icon: data.usersAdded > 0 ? 'success' : 'warning',
                    title: data.usersAdded > 0 ? '¡Importación completada!' : 'Sin importaciones',
                    html: resultHtml,
                    confirmButtonColor: '#0B3F61'
                });

                // Cerrar modal y recargar página si hubo éxito
                if (data.usersAdded > 0) {
                    this.cancelUpload();
                    window.location.reload();
                }
            } else {
                throw new Error(data.message || 'Error desconocido');
            }

        } catch (error) {
            console.error('Submit error:', error);
            this.errorHandler.addError(BulkUploadErrorCodes.SUBMIT_FAILED, { error: error.message });

            Swal.fire({
                icon: 'error',
                title: 'Error al importar',
                text: error.message || 'Ocurrió un error al enviar los datos. Intenta nuevamente.',
                confirmButtonColor: '#0B3F61'
            });
        }
    }

    /**
     * Cancela la carga y cierra el modal
     */
    cancelUpload() {
        // Cerrar modal usando Bootstrap API
        const modalEl = document.getElementById(this.config.modalId);
        if (modalEl) {
            // Intentar obtener instancia existente
            let modalInstance = bootstrap.Modal.getInstance(modalEl);

            if (!modalInstance) {
                // Si no existe, crear una nueva solo para cerrarlo
                modalInstance = new bootstrap.Modal(modalEl);
            }

            modalInstance.hide();
        }

        // La limpieza del estado (resetState) se maneja en el evento 'hidden.bs.modal'
        // definido en bindEvents(), así aseguramos que se limpie solo cuando
        // la animación de cierre haya terminado.
    }

    /**
     * Resetea el estado completo
     */
    resetState() {
        this.state = {
            file: null,
            parsedData: [],
            validatedData: [],
            currentPage: 1,
            totalPages: 1,
            isLoading: false,
            emailsInFile: new Set(),
            searchQuery: '',
            filterMode: 'all'
        };

        this.errorHandler.clear();

        // Limpiar input de archivo
        const fileInput = document.getElementById(this.config.fileInputId);
        if (fileInput) {
            fileInput.value = '';
        }

        // Ocultar preview y mostrar Step 1 de nuevo
        const container = document.getElementById(this.config.previewContainerId);
        const step1 = document.getElementById('bulkUploadStep1');

        if (container) {
            container.innerHTML = '';
            container.style.display = 'none';
        }

        if (step1) {
            step1.style.display = 'block';
        }
    }

    /**
     * Escapa HTML para prevenir XSS
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// ============================================
// Inicialización Global
// ============================================
let bulkUploadManager;

document.addEventListener('DOMContentLoaded', function () {
    // Solo inicializar si existe el modal de carga masiva
    if (document.getElementById('modalCargaMasiva')) {
        bulkUploadManager = new BulkUploadManager({
            modalId: 'modalCargaMasiva',
            fileInputId: 'bulkUploadFile',
            formId: 'bulkUploadForm',
            previewContainerId: 'bulkUploadPreview',
            uploadUrl: window.userIndexConfig?.uploadUrl || '',
            checkDuplicatesUrl: window.userIndexConfig?.checkDuplicatesUrl || '',
            bulkUploadAjaxUrl: window.userIndexConfig?.bulkUploadAjaxUrl || ''
        });

        // Exponer globalmente para debugging
        window.bulkUploadManager = bulkUploadManager;
    }
});
