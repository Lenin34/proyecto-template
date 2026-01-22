/**
 * Notification Index JavaScript
 * Standardized for Admin Theme
 */

function loadDataTables() {
    return new Promise((resolve, reject) => {
        if (typeof $.fn.DataTable !== 'undefined') {
            resolve();
            return;
        }
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css';
        document.head.appendChild(css);

        const script1 = document.createElement('script');
        script1.src = 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js';
        script1.onload = function () {
            const script2 = document.createElement('script');
            script2.src = 'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js';
            script2.onload = resolve;
            script2.onerror = reject;
            document.head.appendChild(script2);
        };
        script1.onerror = reject;
        document.head.appendChild(script1);
    });
}

$(document).ready(function () {
    const config = window.notificationConfig;
    if (!config) return;

    // Modals
    const modalNewEl = document.getElementById('modalNew');
    const modalNew = new bootstrap.Modal(modalNewEl);
    const modalShow = new bootstrap.Modal(document.getElementById('modalShowNotification'));
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditNotification'));
    const modalStats = new bootstrap.Modal(document.getElementById('modalStats'));

    // ... (rest of Select2 init) ...
    // Note: I will use MultiReplace to insert the button and the handler separately to be cleaner.

    // Initialize Select2 when New Modal is shown
    $('#modalNew').on('shown.bs.modal', function () {
        const selectRegions = $(this).find('#notification_regions');
        const selectCompanies = $(this).find('#notification_companies');

        // Initialize Regions Select2
        if (selectRegions.length && !selectRegions.hasClass('select2-hidden-accessible')) {
            selectRegions.select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalNew'),
                placeholder: 'Selecciona una o más regiones (vacío = todas)',
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
                templateResult: function (option) {
                    if (!option.id) return option.text;
                    return $('<span><i class="fas fa-map-marker-alt me-2"></i>' + option.text + '</span>');
                },
                templateSelection: function (option) {
                    if (!option.id) return option.text;
                    return $('<span><i class="fas fa-map-marker-alt me-1"></i>' + option.text + '</span>');
                }
            });
        }

        // Initialize Companies Select2 with grouped data
        if (selectCompanies.length && !selectCompanies.hasClass('select2-hidden-accessible')) {
            // Load companies grouped by region
            fetch(`/${config.dominio}/company/list`)
                .then(response => response.json())
                .then(groupedData => {
                    // Clear existing options
                    selectCompanies.empty();

                    // Build optgroups
                    groupedData.forEach(group => {
                        const optgroup = $('<optgroup>').attr('label', group.region_name);
                        group.companies.forEach(company => {
                            optgroup.append($('<option>').val(company.id).text(company.name));
                        });
                        selectCompanies.append(optgroup);
                    });

                    // Initialize Select2
                    selectCompanies.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#modalNew'),
                        placeholder: 'Selecciona empresas (vacío = todas)',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                        templateResult: function (option) {
                            if (!option.id) return option.text;
                            return $('<span><i class="fas fa-building me-2"></i>' + option.text + '</span>');
                        },
                        templateSelection: function (option) {
                            if (!option.id) return option.text;
                            return $('<span><i class="fas fa-building me-1"></i>' + option.text + '</span>');
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading companies:', error);
                });
        }
    });

    // Initialize Select2 when Edit Modal is shown
    $('#modalEditNotification').on('shown.bs.modal', function () {
        const selectRegions = $(this).find('#editNotificationRegions');
        const selectCompanies = $(this).find('#editNotificationCompanies');

        // Initialize Regions Select2
        if (selectRegions.length && !selectRegions.hasClass('select2-hidden-accessible')) {
            selectRegions.select2({
                theme: 'bootstrap-5',
                dropdownParent: $('#modalEditNotification'),
                placeholder: 'Selecciona una o más regiones (vacío = todas)',
                allowClear: true,
                width: '100%',
                closeOnSelect: false,
                templateResult: function (option) {
                    if (!option.id) return option.text;
                    return $('<span><i class="fas fa-map-marker-alt me-2"></i>' + option.text + '</span>');
                },
                templateSelection: function (option) {
                    if (!option.id) return option.text;
                    return $('<span><i class="fas fa-map-marker-alt me-1"></i>' + option.text + '</span>');
                }
            });
        }

        // Initialize Companies Select2 with grouped data
        if (selectCompanies.length && !selectCompanies.hasClass('select2-hidden-accessible')) {
            // Load companies grouped by region
            fetch(`/${config.dominio}/company/list`)
                .then(response => response.json())
                .then(groupedData => {
                    // Clear existing options
                    selectCompanies.empty();

                    // Build optgroups
                    groupedData.forEach(group => {
                        const optgroup = $('<optgroup>').attr('label', group.region_name);
                        group.companies.forEach(company => {
                            optgroup.append($('<option>').val(company.id).text(company.name));
                        });
                        selectCompanies.append(optgroup);
                    });

                    // Initialize Select2
                    selectCompanies.select2({
                        theme: 'bootstrap-5',
                        dropdownParent: $('#modalEditNotification'),
                        placeholder: 'Selecciona empresas (vacío = todas)',
                        allowClear: true,
                        width: '100%',
                        closeOnSelect: false,
                        templateResult: function (option) {
                            if (!option.id) return option.text;
                            return $('<span><i class="fas fa-building me-2"></i>' + option.text + '</span>');
                        },
                        templateSelection: function (option) {
                            if (!option.id) return option.text;
                            return $('<span><i class="fas fa-building me-1"></i>' + option.text + '</span>');
                        }
                    });
                })
                .catch(error => {
                    console.error('Error loading companies:', error);
                });
        }
    });

    loadDataTables().then(function () {
        console.log('✅ DataTables loaded successfully');

        // Initialize DataTables
        const table = $('#notification-datatable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: config.datatableUrl,
                type: 'GET',
                error: function (xhr, error, code) {
                    console.error('DataTables AJAX error:', error, code);
                }
            },
            columns: [
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: 'text-center',
                    render: function (data, type, row) {
                        return `
                        <div class="action-icons">
                            <button class="action-icon action-icon-view btn-view" data-item-id="${row.id}" title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-icon action-icon-info btn-stats" data-item-id="${row.id}" title="Ver Estadísticas">
                                <i class="fas fa-chart-bar"></i>
                            </button>
                            <button class="action-icon action-icon-edit btn-edit" data-item-id="${row.id}" title="Editar">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button class="action-icon action-icon-success btn-send" data-item-id="${row.id}" data-item-title="${row.title || 'esta notificación'}" title="Enviar Notificación">
                                <i class="fas fa-paper-plane"></i>
                            </button>
                            <button
                                type="button"
                                class="action-icon action-icon-delete"
                                data-item-id="${row.id}"
                                data-item-name="${row.title || 'esta notificación'}"
                                title="Eliminar"
                            >
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    }
                },
                { data: 'title', className: 'text-center fw-semibold' },
                { data: 'message', className: 'text-center' },
                {
                    data: 'regions',
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        if (!data || data.length === 0) {
                            return '<span class="text-muted fst-italic">Todas las regiones</span>';
                        }
                        return data.map(region =>
                            `<span class="badge bg-info me-1"><i class="fas fa-map-marker-alt me-1"></i>${region}</span>`
                        ).join(' ');
                    }
                },
                {
                    data: 'companies',
                    className: 'text-center',
                    orderable: false,
                    render: function (data, type, row) {
                        if (!data || data.length === 0) {
                            return '<span class="text-muted fst-italic">Todas las empresas</span>';
                        }
                        return data.map(company =>
                            `<span class="badge bg-secondary me-1"><i class="fas fa-building me-1"></i>${company}</span>`
                        ).join(' ');
                    }
                }
            ],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[1, 'asc']],
            language: {
                processing: "Procesando...",
                search: "Buscar: ",
                lengthMenu: "Mostrar&nbsp;&nbsp;_MENU_&nbsp;&nbsp;registros",
                info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
                infoEmpty: "Mostrando 0 a 0 de 0 registros",
                infoFiltered: "(filtrado de _MAX_ registros totales)",
                loadingRecords: "Cargando...",
                zeroRecords: "No se encontraron registros",
                emptyTable: "No hay datos disponibles",
                paginate: {
                    first: "Primero",
                    previous: "Anterior",
                    next: "Siguiente",
                    last: "Último"
                }
            },
            dom: "<'datatable-top row mb-3'<'col-md-6 d-flex align-items-center'l><'col-md-6 d-flex justify-content-end'f>>" +
                "rt" +
                "<'datatable-bottom row mt-3'<'col-md-5'i><'col-md-7'p>>",
            drawCallback: function () {
                const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltips.map(el => new bootstrap.Tooltip(el));
            }
        });

        // New Form Submit Handler (Click on Button)
        const btnSaveNew = document.getElementById('btnSaveNewNotification');
        if (btnSaveNew) {
            btnSaveNew.addEventListener('click', function (e) {
                e.preventDefault();

                const form = document.getElementById('newNotificationForm');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return;
                }

                const formData = new FormData(form);

                Swal.fire({
                    title: 'Creando...',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // 1. Hide modal via Bootstrap API
                            modalNew.hide();

                            // 2. Manual cleanup to ensure backdrop is gone
                            setTimeout(() => {
                                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = '';
                                document.body.style.paddingRight = '';

                                // 3. Show success message
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Creado!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                });
                            }, 300); // Small delay to let Bootstrap try first

                            form.reset();
                            form.classList.remove('was-validated');
                            // Destroy and reset Select2
                            $('#notification_regions').val(null).trigger('change');
                            $('#notification_companies').val(null).trigger('change');
                            table.ajax.reload(null, false);
                        } else {
                            let errorMessage = data.message;
                            if (data.errors) {
                                errorMessage += '<br>' + data.errors.join('<br>');
                            }
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                html: errorMessage
                            });
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'Ocurrió un error al crear la notificación', 'error');
                        console.error(error);
                    });
            });
        }

        // View Button Handler
        $('#notification-datatable').on('click', '.btn-view', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/notification/${itemId}/details`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Populate Modal
                    document.getElementById('showNotificationTitle').textContent = data.title;
                    document.getElementById('showNotificationMessage').textContent = data.message;

                    const regionsContainer = document.getElementById('showNotificationRegions');
                    regionsContainer.innerHTML = '';
                    if (data.regions && data.regions.length > 0) {
                        data.regions.forEach(region => {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-secondary me-1';
                            badge.textContent = region.name;
                            regionsContainer.appendChild(badge);
                        });
                    } else {
                        regionsContainer.innerHTML = '<span class="text-muted fst-italic">Aplica a todas las regiones</span>';
                    }

                    modalShow.show();
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo cargar la información', 'error');
                    console.error(error);
                });
        });

        // Edit Button Handler
        $('#notification-datatable').on('click', '.btn-edit', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/notification/${itemId}/details`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Populate Modal
                    const form = document.getElementById('editNotificationForm');
                    form.action = `/${config.dominio}/notification/${itemId}/edit`;

                    document.getElementById('editNotificationTitle').value = data.title;
                    document.getElementById('editNotificationMessage').value = data.message;

                    // Select Regions using Select2 API
                    const regionsSelect = $('#editNotificationRegions');
                    regionsSelect.val(data.regionIds).trigger('change');

                    // Select Companies using Select2 API
                    const companiesSelect = $('#editNotificationCompanies');
                    companiesSelect.val(data.companyIds).trigger('change');

                    modalEdit.show();
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo cargar la información', 'error');
                    console.error(error);
                });
        });

        // Edit Form Submit Handler
        document.getElementById('editNotificationForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const form = this;
            const formData = new FormData(form);

            Swal.fire({
                title: 'Guardando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(form.action, {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        modalEdit.hide();
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                        table.ajax.reload(null, false);
                    } else {
                        let errorMessage = data.message;
                        if (data.errors) {
                            errorMessage += '<br>' + data.errors.join('<br>');
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: errorMessage
                        });
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Ocurrió un error al guardar', 'error');
                    console.error(error);
                });
        });

        // Send Notification Button Handler
        $('#notification-datatable').on('click', '.btn-send', function () {
            const itemId = $(this).data('item-id');
            const itemTitle = $(this).data('item-title');

            Swal.fire({
                title: '¿Enviar notificación?',
                html: `¿Está seguro de enviar <strong>${itemTitle}</strong>?<br><small class="text-muted">Se enviará a todos los usuarios de las regiones seleccionadas.</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-paper-plane me-1"></i> Sí, enviar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;

                Swal.fire({
                    title: 'Enviando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch(`/${config.dominio}/notification/${itemId}/send`, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    }
                })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', [...response.headers.entries()]);
                        return response.json();
                    })
                    .then(data => {
                        console.log('Response data:', data);
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Enviado!',
                                text: data.message,
                                timer: 3000,
                                showConfirmButton: false
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: data.message || 'Error al enviar la notificación'
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Fetch error:', error);
                        Swal.fire('Error', 'No se pudo enviar la notificación: ' + error.message, 'error');
                    });
            });
        });

        // Delete handling with SweetAlert2
        $('#notification-datatable').on('click', '.action-icon-delete', function () {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');

            Swal.fire({
                title: '¿Eliminar notificación?',
                html: `¿Está seguro de eliminar a <strong>${itemName}</strong>?<br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                reverseButtons: true
            }).then((result) => {
                if (!result.isConfirmed) return;

                Swal.fire({
                    title: 'Eliminando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading()
                });

                const body = new URLSearchParams();
                body.append('_token', config.csrfTokenDelete);

                fetch(`/${config.dominio}/notification/${itemId}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
                    },
                    body: body
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: '¡Eliminado!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    })
                    .catch(error => {
                        Swal.fire('Error', 'No se pudo eliminar el registro', 'error');
                        console.error(error);
                    });
            });
        });

        // Stats Button Handler
        $('#notification-datatable').on('click', '.btn-stats', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando estadísticas...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/api/notifications/${itemId}/read-statistics`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.error) {
                        Swal.fire('Error', data.error || 'Error desconocido', 'error');
                        return;
                    }

                    // Update Stats Counters
                    const totalElement = document.getElementById('statsTotalReads');
                    if (totalElement) totalElement.textContent = data.total;

                    // Update Table
                    const tbody = document.getElementById('statsTableBody');
                    if (tbody) {
                        tbody.innerHTML = '';

                        if (!data.data || data.data.length === 0) {
                            tbody.innerHTML = '<tr><td colspan="4" class="text-center">No hay lecturas registradas</td></tr>';
                        } else {
                            data.data.forEach(item => {
                                const date = item.read_at ? new Date(item.read_at).toLocaleString() : '-';
                                const row = `
                                    <tr>
                                        <td>${item.user_name || 'Desconocido'}</td>
                                        <td>${item.user_email || '-'}</td>
                                        <td>${item.company_name || '-'}</td>
                                        <td>${date}</td>
                                    </tr>
                                `;
                                tbody.insertAdjacentHTML('beforeend', row);
                            });
                        }
                    }

                    modalStats.show();
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudieron cargar las estadísticas', 'error');
                    console.error(error);
                });
        });
    });
});
