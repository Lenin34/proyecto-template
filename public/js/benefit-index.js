/**
 * Benefit Index JavaScript
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
    const config = window.benefitConfig;
    if (!config) return;

    // Modals
    const modalShow = new bootstrap.Modal(document.getElementById('modalShowBenefit'));
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditBenefit'));
    const modalNew = new bootstrap.Modal(document.getElementById('modalNew'));

    loadDataTables().then(function () {
        console.log('✅ DataTables loaded successfully');

        // Initialize DataTables
        const table = $('#benefit-datatable').DataTable({
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
                                data-item-name="${row.title || 'este beneficio'}"
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
                { data: 'description', className: 'text-center' },
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

        // View Button Handler
        $('#benefit-datatable').on('click', '.btn-view', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/benefit/${itemId}/details`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Populate Modal
                    document.getElementById('showBenefitTitle').textContent = data.title;
                    document.getElementById('showBenefitDescription').textContent = data.description;
                    document.getElementById('showBenefitStartDate').textContent = data.validity_start_date;
                    document.getElementById('showBenefitEndDate').textContent = data.validity_end_date;

                    // Set Region
                    const regionDiv = document.getElementById('showBenefitRegion');
                    if (data.region && regionDiv) {
                        regionDiv.textContent = data.region.name;
                    } else if (regionDiv) {
                        regionDiv.textContent = 'Sin región';
                    }

                    const img = document.getElementById('showBenefitImage');
                    if (data.image) {
                        img.src = `/uploads/${data.image}`; // Adjust path if needed
                        img.style.display = 'block';
                    } else {
                        img.style.display = 'none';
                    }

                    const companiesContainer = document.getElementById('showBenefitCompanies');
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
        $('#benefit-datatable').on('click', '.btn-edit', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/benefit/${itemId}/details`)
                .then(response => response.json())
                .then(async data => {
                    if (data.error) {
                        Swal.close();
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Populate Modal
                    const form = document.getElementById('editBenefitForm');
                    form.action = `/${config.dominio}/benefit/${itemId}/edit`;

                    document.getElementById('editBenefitTitle').value = data.title;
                    document.getElementById('editBenefitDescription').value = data.description;
                    document.getElementById('editBenefitStartDate').value = data.validity_start_date;
                    document.getElementById('editBenefitEndDate').value = data.validity_end_date;

                    // Load regions and set selected value
                    const regionSelect = document.getElementById('editBenefitRegion');
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

                    // Set selected companies using Select2
                    const companySelect = $('#editBenefitCompanies');
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
        document.getElementById('editBenefitForm').addEventListener('submit', function (e) {
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
                        Swal.fire({
                            icon: 'success',
                            title: '¡Actualizado!',
                            text: data.message,
                            showConfirmButton: true,
                            confirmButtonText: 'Aceptar',
                            customClass: { popup: 'swal-dark-popup' }
                        }).then(() => {
                            // Close modal after SweetAlert is dismissed
                            modalEdit.hide();
                            // Remove any remaining backdrop
                            document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                            document.body.classList.remove('modal-open');
                            document.body.style.removeProperty('overflow');
                            document.body.style.removeProperty('padding-right');
                            // Reload DataTable
                            table.ajax.reload(null, false);
                        });
                    } else {
                        let errorMessage = data.message;
                        if (data.errors) {
                            errorMessage += '<br>' + data.errors.join('<br>');
                        }
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            html: errorMessage,
                            customClass: { popup: 'swal-dark-popup' }
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Ocurrió un error al guardar',
                        customClass: { popup: 'swal-dark-popup' }
                    });
                    console.error(error);
                });
        });

        // New Form Submit Handler
        const newBenefitForm = document.getElementById('newBenefitForm');
        // console.log('🔍 [BENEFIT-DEBUG] Searching for #newBenefitForm:', newBenefitForm);

        if (newBenefitForm) {
            // console.log('✅ [BENEFIT-DEBUG] Form found, attaching submit listener');
            newBenefitForm.addEventListener('submit', function (e) {
                // console.log('🚀 [BENEFIT-DEBUG] Submit event triggered!');
                e.preventDefault();

                const form = this;
                const formData = new FormData(form);

                // Debug FormData
                /* for (let [key, value] of formData.entries()) {
                    console.log(`📋 [BENEFIT-DEBUG] Field: ${key} = ${value}`);
                } */

                Swal.fire({
                    title: 'Guardando...',
                    text: 'Procesando beneficio...',
                    allowOutsideClick: false,
                    allowOutsideClick: false,
                    didOpen: () => {
                        // console.log('⏳ [BENEFIT-DEBUG] SweetAlert Loading shown');
                        Swal.showLoading()
                    }
                });

                // console.log('📡 [BENEFIT-DEBUG] Sending POST request to:', form.action);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(response => {
                        // console.log('📥 [BENEFIT-DEBUG] Response received status:', response.status);
                        return response.json();
                    })
                    .then(data => {
                        // console.log('📦 [BENEFIT-DEBUG] Data received:', data);

                        if (data.status === 'success') {
                            // console.log('✅ [BENEFIT-DEBUG] Success condition met');
                            Swal.fire({
                                icon: 'success',
                                title: '¡Creado!',
                                text: data.message,
                                showConfirmButton: true,
                                confirmButtonText: 'Aceptar',
                                customClass: { popup: 'swal-dark-popup' }
                            }).then(() => {
                                // Close modal after SweetAlert is dismissed
                                modalNew.hide();
                                // Reset form
                                form.reset();
                                $('#fileNameDisplay').text('Seleccionar imagen...');

                                // Remove any remaining backdrop
                                document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
                                document.body.classList.remove('modal-open');
                                document.body.style.removeProperty('overflow');
                                document.body.style.removeProperty('padding-right');
                                // Reload DataTable
                                table.ajax.reload(null, false);
                            });
                        }
                    })
                    .catch(error => {
                        // console.error('❌ [BENEFIT-DEBUG] Fetch error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ocurrió un error al guardar: ' + error.message,
                            customClass: { popup: 'swal-dark-popup' }
                        });
                    });
            });
        } else {
            console.error('❌ [BENEFIT-DEBUG] #newBenefitForm NOT FOUND in DOM during initialization');
        }

        // File Input Change Handler for New Form
        $('#newBenefitForm input[type="file"]').on('change', function () {
            const fileName = this.files[0] ? this.files[0].name : 'Seleccionar imagen...';
            $('#fileNameDisplay').text(fileName);
        });

        // Delete handling with SweetAlert2
        $('#benefit-datatable').on('click', '.action-icon-delete', function () {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');

            Swal.fire({
                title: '¿Eliminar beneficio?',
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
                body.append('_token', config.csrfToken);

                fetch(`/${config.dominio}/benefit/${itemId}/delete`, {
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
