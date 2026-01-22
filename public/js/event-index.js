/**
 * Event Index JavaScript
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
    const config = window.eventConfig;
    if (!config) return;

    // Modals
    const modalShow = new bootstrap.Modal(document.getElementById('modalShowEvent'));
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditEvent'));

    loadDataTables().then(function () {
        console.log('✅ DataTables loaded successfully');

        // Initialize DataTables
        const table = $('#event-datatable').DataTable({
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
                            <button class="action-icon action-icon-edit btn-edit" data-item-id="${row.id}" title="Editar">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button
                                type="button"
                                class="action-icon action-icon-delete"
                                data-item-id="${row.id}"
                                data-item-name="${row.title || 'este evento'}"
                                title="Eliminar"
                            >
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    }
                },
                { data: 'title', className: 'text-center fw-semibold' },
                { data: 'region', className: 'text-center' },
                { data: 'companies', className: 'text-center', orderable: false },
                { data: 'date', className: 'text-center' }
            ],
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[4, 'desc']], // Ordenar por columna 4 (fecha/created_at) descendente - eventos más recientes primero
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

        // View Button Handler
        $('#event-datatable').on('click', '.btn-view', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/event/${itemId}/details`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Populate Modal
                    document.getElementById('showEventTitle').textContent = data.title;
                    document.getElementById('showEventDescription').textContent = data.description;
                    document.getElementById('showEventStartDate').textContent = data.start_date ? data.start_date.replace('T', ' ') : '';
                    document.getElementById('showEventEndDate').textContent = data.end_date ? data.end_date.replace('T', ' ') : '';

                    // Set Region
                    const regionDiv = document.getElementById('showEventRegion');
                    if (regionDiv) {
                        if (data.region && data.region.name) {
                            regionDiv.textContent = data.region.name;
                            regionDiv.className = 'badge bg-primary fs-6';
                            regionDiv.style.display = 'inline-block';
                        } else {
                            regionDiv.textContent = 'Sin región';
                            regionDiv.className = 'badge bg-secondary fs-6';
                            regionDiv.style.display = 'inline-block';
                        }
                    }

                    const img = document.getElementById('showEventImage');
                    if (data.image) {
                        img.src = `/uploads/${data.image}`; // Adjust path if needed
                        img.style.display = 'block';
                    } else {
                        img.style.display = 'none';
                    }

                    const companiesContainer = document.getElementById('showEventCompanies');
                    companiesContainer.innerHTML = '';
                    if (data.companies && data.companies.length > 0) {
                        data.companies.forEach(company => {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-secondary';
                            badge.textContent = company.name;
                            companiesContainer.appendChild(badge);
                        });
                    } else {
                        companiesContainer.innerHTML = '<span class="text-muted fst-italic">Todas las empresas</span>';
                    }

                    modalShow.show();
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo cargar la información', 'error');
                    console.error(error);
                });
        });

        // Edit Button Handler
        $('#event-datatable').on('click', '.btn-edit', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/event/${itemId}/details`)
                .then(response => response.json())
                .then(async data => {
                    if (data.error) {
                        Swal.close();
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Populate Modal
                    const form = document.getElementById('editEventForm');
                    form.action = `/${config.dominio}/event/${itemId}/edit`;

                    document.getElementById('editEventTitle').value = data.title;
                    document.getElementById('editEventDescription').value = data.description;
                    document.getElementById('editEventStartDate').value = data.start_date;
                    document.getElementById('editEventEndDate').value = data.end_date;

                    // Load regions and set selected value
                    const regionSelect = document.getElementById('editEventRegion');
                    if (regionSelect) {
                        try {
                            const regionsResponse = await fetch(`/${config.dominio}/region/list`);
                            const regions = await regionsResponse.json();

                            regionSelect.innerHTML = '<option value="">Seleccionar región</option>';
                            regions.forEach(region => {
                                const option = new Option(
                                    region.name,
                                    region.id,
                                    false,
                                    data.region && region.id === data.region.id
                                );
                                regionSelect.appendChild(option);
                            });
                        } catch (error) {
                            console.error('Error loading regions:', error);
                            Swal.close();
                            Swal.fire('Error', 'No se pudieron cargar las regiones', 'error');
                            return;
                        }
                    }

                    // Set selected companies directly (no need for region selection)
                    const companySelect = $('#editEventCompanies');
                    if (data.companyIds && data.companyIds.length > 0) {
                        companySelect.val(data.companyIds.map(id => id.toString())).trigger('change');
                    } else {
                        companySelect.val(null).trigger('change');
                    }

                    Swal.close();
                    modalEdit.show();
                })
                .catch(error => {
                    Swal.close();
                    Swal.fire('Error', 'No se pudo cargar la información', 'error');
                    console.error(error);
                });
        });

        // Edit Form Submit Handler
        document.getElementById('editEventForm').addEventListener('submit', function (e) {
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

        // New Form Submit Handler
        const newEventForm = document.getElementById('newEventForm');
        // console.log('🔍 [EVENT-DEBUG] Searching for #newEventForm:', newEventForm);

        if (newEventForm) {
            // console.log('✅ [EVENT-DEBUG] Form found, attaching submit listener');
            newEventForm.addEventListener('submit', function (e) {
                // console.log('🚀 [EVENT-DEBUG] Submit event triggered!');
                e.preventDefault();

                const form = this;
                const formData = new FormData(form);

                // Debug FormData
                /* for (let [key, value] of formData.entries()) {
                    console.log(`📋 [EVENT-DEBUG] Field: ${key} = ${value}`);
                } */

                Swal.fire({
                    title: 'Guardando...',
                    text: 'Procesando evento...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        // console.log('⏳ [EVENT-DEBUG] SweetAlert Loading shown');
                        Swal.showLoading()
                    }
                });

                // console.log('📡 [EVENT-DEBUG] Sending POST request to:', form.action);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => {
                        // console.log('📥 [EVENT-DEBUG] Response received status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        // console.log('📦 [EVENT-DEBUG] Data received:', data);

                        if (data.status === 'success') {
                            // console.log('✅ [EVENT-DEBUG] Success condition met');
                            // Close modal
                            const modal = bootstrap.Modal.getInstance(document.getElementById('modalNew'));
                            if (modal) {
                                modal.hide();
                            } else {
                                // Fallback if bootstrap instance not found
                                $('#modalNew').modal('hide');
                            }

                            // Reset form
                            form.reset();
                            // Reset select2 if exists
                            $('#newEventCompanies').val(null).trigger('change');
                            // Reset image preview
                            const imgPreview = document.getElementById('eventImagePreview');
                            if (imgPreview) {
                                imgPreview.src = '';
                                imgPreview.style.display = 'none';
                            }
                            const uploadText = document.querySelector('#eventImageDisplay .file-upload-text');
                            if (uploadText) uploadText.style.display = 'block';
                            const uploadIcon = document.querySelector('#eventImageDisplay .file-upload-icon');
                            if (uploadIcon) uploadIcon.style.display = 'block';

                            Swal.fire({
                                icon: 'success',
                                title: '¡Creado!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false
                            });
                            table.ajax.reload(null, false);
                        } else {
                            // console.warn('⚠️ [EVENT-DEBUG] Server returned error:', data.message);
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
                        // console.error('❌ [EVENT-DEBUG] Fetch error:', error);
                        Swal.fire('Error', 'Ocurrió un error al guardar: ' + error.message, 'error');
                    });
            });
        } else {
            // console.error('❌ [EVENT-DEBUG] #newEventForm NOT FOUND in DOM during initialization');
        }

        // Delete handling with SweetAlert2
        $('#event-datatable').on('click', '.action-icon-delete', function () {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');

            Swal.fire({
                title: '¿Eliminar evento?',
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

                fetch(`/${config.dominio}/event/${itemId}/delete`, {
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
    });
});
