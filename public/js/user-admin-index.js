/**
 * User Admin Index JavaScript
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
    const config = window.userAdminConfig;
    if (!config) return;

    // Modals
    const modalShow = new bootstrap.Modal(document.getElementById('modalShowUserAdmin'));
    const modalEdit = new bootstrap.Modal(document.getElementById('modalEditUserAdmin'));

    loadDataTables().then(function () {
        console.log('✅ DataTables loaded successfully');

        // Initialize DataTables
        const table = $('#user-admin-datatable').DataTable({
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
                                data-item-name="${row.email || 'este usuario'}"
                                title="Eliminar"
                            >
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    }
                },
                { data: 'email', className: 'text-center fw-semibold' },
                { data: 'name', className: 'text-center' },
                { data: 'phone', className: 'text-center' },
                {
                    data: 'role',
                    className: 'text-center',
                    render: function (data) {
                        return `<span class="badge bg-primary">${data}</span>`;
                    }
                },
                { data: 'created_at', className: 'text-center' }
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
        $('#user-admin-datatable').on('click', '.btn-view', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/admin/user/${itemId}/details`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Populate Modal
                    document.getElementById('showUserAdminEmail').textContent = data.email;
                    document.getElementById('showUserAdminName').textContent = (data.name || '') + ' ' + (data.last_name || '');
                    document.getElementById('showUserAdminPhone').textContent = data.phone || 'N/A';
                    document.getElementById('showUserAdminCreated').textContent = data.created_at || 'N/A';
                    document.getElementById('showUserAdminRole').textContent = data.role ? data.role.name : 'Sin rol';
                    document.getElementById('showUserAdminCompany').textContent = data.company || 'Sin empresa';

                    const statusBadge = document.getElementById('showUserAdminStatus');
                    statusBadge.textContent = data.status === 'ACTIVE' ? 'Activo' : 'Inactivo';
                    statusBadge.className = `badge ${data.status === 'ACTIVE' ? 'bg-success' : 'bg-danger'}`;

                    const img = document.getElementById('showUserAdminPhoto');
                    if (data.photo) {
                        img.src = `/uploads/${data.photo}`; // Adjust path if needed
                        img.style.display = 'block';
                    } else {
                        img.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(data.name || 'User') + '&background=random';
                        img.style.display = 'block';
                    }

                    const regionsContainer = document.getElementById('showUserAdminRegions');
                    regionsContainer.innerHTML = '';
                    if (data.regions && data.regions.length > 0) {
                        data.regions.forEach(region => {
                            const badge = document.createElement('span');
                            badge.className = 'badge bg-secondary';
                            badge.textContent = region.name;
                            regionsContainer.appendChild(badge);
                        });
                    } else {
                        regionsContainer.innerHTML = '<span class="text-muted fst-italic">Sin regiones asignadas</span>';
                    }

                    modalShow.show();
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo cargar la información', 'error');
                    console.error(error);
                });
        });

        // Edit Button Handler
        $('#user-admin-datatable').on('click', '.btn-edit', function () {
            const itemId = $(this).data('item-id');

            Swal.fire({
                title: 'Cargando...',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            fetch(`/${config.dominio}/admin/user/${itemId}/details`)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.error) {
                        Swal.fire('Error', data.error, 'error');
                        return;
                    }

                    // Set form action
                    const form = document.getElementById('editUserAdminForm');
                    form.action = `/${config.dominio}/admin/user/${itemId}/edit`;

                    // Populate fields using Symfony's auto-generated IDs (user_admin_edit_*)
                    document.getElementById('user_admin_edit_name').value = data.name || '';
                    document.getElementById('user_admin_edit_last_name').value = data.last_name || '';
                    document.getElementById('user_admin_edit_email').value = data.email || '';
                    document.getElementById('user_admin_edit_phone_number').value = data.phone || '';
                    document.getElementById('user_admin_edit_password').value = ''; // Reset password

                    // Select Role
                    const roleSelect = document.getElementById('user_admin_edit_role');
                    if (data.role && roleSelect) {
                        roleSelect.value = data.role.id;
                    }

                    // Select Regions (Multi-select with Select2)
                    const regionsSelect = $('#user_admin_edit_regions');
                    if (regionsSelect.length) {
                        regionsSelect.val(data.regionIds).trigger('change');
                    }

                    // Select Company
                    const companySelect = $('#user_admin_edit_company');
                    if (companySelect.length) {
                        companySelect.val(data.companyId).trigger('change');
                    }

                    // Show current photo
                    const currentPhoto = document.getElementById('editUserAdminCurrentPhoto');
                    const uploadText = document.querySelector('#editUserAdminPhotoDisplay .file-upload-text');
                    const uploadIcon = document.querySelector('#editUserAdminPhotoDisplay .file-upload-icon');

                    if (data.photo) {
                        currentPhoto.src = `/uploads/${data.photo}`;
                        currentPhoto.style.display = 'block';
                        if (uploadText) uploadText.style.display = 'none';
                        if (uploadIcon) uploadIcon.style.display = 'none';
                    } else {
                        currentPhoto.style.display = 'none';
                        if (uploadText) uploadText.style.display = 'block';
                        if (uploadIcon) uploadIcon.style.display = 'block';
                    }

                    modalEdit.show();
                })
                .catch(error => {
                    Swal.fire('Error', 'No se pudo cargar la información', 'error');
                    console.error(error);
                });
        });

        // Edit Form Submit Handler
        document.getElementById('editUserAdminForm').addEventListener('submit', function (e) {
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

        // Delete handling with SweetAlert2
        $('#user-admin-datatable').on('click', '.action-icon-delete', function () {
            const itemId = $(this).data('item-id');
            const itemName = $(this).data('item-name');

            Swal.fire({
                title: '¿Eliminar administrador?',
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

                fetch(`/${config.dominio}/admin/user/${itemId}/delete`, {
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
        // New Admin Form Submit Handler
        const newAdminForm = document.getElementById('newAdminForm');
        if (newAdminForm) {
            newAdminForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const form = this;
                const formData = new FormData(form);

                Swal.fire({
                    title: 'Guardando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Close modal
                            const modalElement = document.getElementById('modalNew');
                            const modal = bootstrap.Modal.getInstance(modalElement);
                            modal.hide();

                            // Reset form
                            form.reset();
                            // Reset photo preview if exists
                            const photoPreview = document.getElementById('newAdminCurrentPhoto');
                            const uploadText = document.querySelector('#newAdminPhotoDisplay .file-upload-text');
                            const uploadIcon = document.querySelector('#newAdminPhotoDisplay .file-upload-icon');

                            if (photoPreview) {
                                photoPreview.style.display = 'none';
                                photoPreview.src = '';
                            }
                            if (uploadText) uploadText.style.display = 'block';
                            if (uploadIcon) uploadIcon.style.display = 'block';

                            // Reset Select2 if exists
                            $('#user_admin_regions').val(null).trigger('change');
                            $('#user_admin_company').val(null).trigger('change');

                            Swal.fire({
                                icon: 'success',
                                title: '¡Creado!',
                                text: data.message,
                                timer: 2000,
                                showConfirmButton: false,
                                didClose: () => {
                                    document.body.classList.remove('modal-open');
                                    const backdrops = document.getElementsByClassName('modal-backdrop');
                                    while (backdrops.length > 0) {
                                        backdrops[0].parentNode.removeChild(backdrops[0]);
                                    }
                                    document.body.style.paddingRight = '';
                                    document.body.style.overflow = '';
                                }
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
        }
    });
});
