/**
 * User Index JavaScript
 * Maneja DataTables, modales y funcionalidad de la página de agremiados
 */

/**
 * Carga DataTables dinámicamente si no está disponible
 * @returns {Promise} Promesa que se resuelve cuando DataTables está listo
 */
function loadDataTables() {
    return new Promise((resolve, reject) => {
        // Check if DataTables is already loaded
        if (typeof $.fn.DataTable !== 'undefined') {
            resolve();
            return;
        }

        // Load DataTables CSS
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = 'https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css';
        document.head.appendChild(css);

        // Load DataTables JS
        const script1 = document.createElement('script');
        script1.src = 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js';
        script1.onload = function () {
            const script2 = document.createElement('script');
            script2.src = 'https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js';
            script2.onload = function () {
                resolve();
            };
            script2.onerror = reject;
            document.head.appendChild(script2);
        };
        script1.onerror = reject;
        document.head.appendChild(script1);
    });
}

/**
 * Inicializa la página de usuarios
 * @param {Object} config - Configuración con dominio, datatableUrl y csrfToken
 */
function initUserIndex(config) {
    const { dominio, datatableUrl, csrfToken } = config;

    // Load DataTables first
    loadDataTables().then(function () {
        console.log('✅ DataTables loaded successfully');

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Initialize DataTables with server-side processing
        const table = $('#users-datatable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: datatableUrl,
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
                            <button type="button" class="action-icon action-icon-view" data-user-id="${row.id}" title="Ver detalle">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button type="button" class="action-icon action-icon-edit" data-user-id="${row.id}" title="Editar">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <button
                                type="button"
                                class="action-icon action-icon-delete"
                                data-user-id="${row.id}"
                                data-user-name="${row.name || 'este usuario'}"
                                title="Eliminar"
                            >
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    `;
                    }
                },
                { data: 'name', className: 'text-center fw-semibold' },
                { data: 'company', className: 'text-center' },
                { data: 'region', className: 'text-center' },
                { data: 'birthday', className: 'text-center' },
                { data: 'phone_number', className: 'text-center' },
                { data: 'email', className: 'text-center' },
                { data: 'employee_number', className: 'text-center' },
                { data: 'curp', className: 'text-center' },
                {
                    data: 'gender',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (!data) return `<div class="text-center">-</div>`;
                        const initial = data.charAt(0).toUpperCase();
                        return `
                            <div class="text-center">
                                <span class="gender-badge" data-bs-toggle="tooltip" title="${data}">
                                    ${initial}
                                </span>
                            </div>
                        `;
                    }
                },
                { data: 'education', className: 'text-center' }
            ],

            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
            order: [[1, 'asc']],

            language: {
                processing: "Procesando...",
                search: "Buscar: ",
                lengthMenu: "Mostrar&nbsp;&nbsp;_MENU_&nbsp;&nbsp;registros",
                info: "Mostrando _START_ a _END_ de _TOTAL_ agremiados",
                infoEmpty: "Mostrando 0 a 0 de 0 agremiados",
                infoFiltered: "(filtrado de _MAX_ agremiados totales)",
                loadingRecords: "Cargando...",
                zeroRecords: "No se encontraron agremiados",
                emptyTable: "No hay agremiados disponibles",
                paginate: {
                    first: "Primero",
                    previous: "Anterior",
                    next: "Siguiente",
                    last: "Último"
                }
            },

            dom:
                "<'datatable-top row mb-3'<'col-md-6 d-flex align-items-center'l><'col-md-6 d-flex justify-content-end'f>>" +
                "rt" +
                "<'datatable-bottom row mt-3'<'col-md-5'i><'col-md-7'p>>",

            drawCallback: function () {
                const tooltips = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltips.map(el => new bootstrap.Tooltip(el));
            }
        });

        console.log('✅ DataTable initialized successfully');

        // Delete handling with SweetAlert2
        $('#users-datatable').on('click', '.action-icon-delete', function () {
            const userId = $(this).data('user-id');
            const userName = $(this).data('user-name') || 'este usuario';

            Swal.fire({
                title: '¿Eliminar usuario?',
                html: `¿Está seguro de eliminar a <strong>${userName}</strong>?<br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Sí, eliminar',
                cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                reverseButtons: true,
                customClass: { popup: 'swal-dark-popup' }
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

                // Crear formulario con el token CSRF del config


                const form = document.createElement('form');
                form.method = 'POST';
                form.action = `/${dominio}/user/${userId}`;



                const tokenField = document.createElement('input');
                tokenField.type = 'hidden';
                tokenField.name = '_token';
                tokenField.value = csrfToken; // Usa el token del config (closure)

                form.appendChild(tokenField);
                document.body.appendChild(form);


                form.submit();
            });
        });

        // View user handling
        $('#users-datatable').on('click', '.action-icon-view', function () {
            const userId = $(this).data('user-id');
            const modalElement = document.getElementById('modalShowUser');
            const modal = new bootstrap.Modal(modalElement);

            // Reset fields
            document.getElementById('showUserPhotoContainer').innerHTML = '<div class="file-upload-icon"><i class="fas fa-user"></i></div>';
            document.getElementById('showUserName').value = 'Cargando...';
            document.getElementById('showUserLastName').value = '';
            document.getElementById('showUserEmail').value = '';
            document.getElementById('showUserPhone').value = '';
            document.getElementById('showUserCurp').value = '';
            document.getElementById('showUserBirthday').value = '';
            document.getElementById('showUserGender').value = '';
            document.getElementById('showUserEducation').value = '';
            document.getElementById('showUserEmployeeNumber').value = '';
            document.getElementById('showUserCompany').value = '';
            document.getElementById('showUserRegions').innerHTML = '<span class="text-muted">Cargando...</span>';
            document.getElementById('showUserBeneficiaries').innerHTML = '<div class="col-12 text-center text-muted">Cargando beneficiarios...</div>';

            modal.show();

            // Fetch details
            fetch(`/${dominio}/user/${userId}/details`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    // Populate fields
                    document.getElementById('showUserName').value = data.name || '';
                    document.getElementById('showUserLastName').value = data.lastName || '';
                    document.getElementById('showUserEmail').value = data.email || '';
                    document.getElementById('showUserPhone').value = data.phone || '';
                    document.getElementById('showUserCurp').value = data.curp || '';
                    document.getElementById('showUserBirthday').value = data.birthday || '';
                    document.getElementById('showUserGender').value = data.gender || '';
                    document.getElementById('showUserEducation').value = data.education || '';
                    document.getElementById('showUserEmployeeNumber').value = data.employeeNumber || '';
                    document.getElementById('showUserCompany').value = data.company || '';

                    // Photo
                    const photoContainer = document.getElementById('showUserPhotoContainer');
                    if (data.photo) {
                        photoContainer.innerHTML = `<img src="/uploads/${data.photo}" class="img-fluid rounded-circle" style="width: 150px; height: 150px; object-fit: cover;">`;
                    } else {
                        photoContainer.innerHTML = '<div class="file-upload-icon"><i class="fas fa-user"></i></div>';
                    }

                    // Regions
                    const regionsContainer = document.getElementById('showUserRegions');
                    if (data.regions && data.regions.length > 0) {
                        regionsContainer.innerHTML = data.regions.map(r => `<span class="badge bg-primary">${r}</span>`).join('');
                    } else {
                        regionsContainer.innerHTML = '<span class="text-muted">Sin regiones asignadas</span>';
                    }

                    // Beneficiaries
                    const beneficiariesContainer = document.getElementById('showUserBeneficiaries');
                    if (data.beneficiaries && data.beneficiaries.length > 0) {
                        beneficiariesContainer.innerHTML = data.beneficiaries.map(b => `
                            <div class="col-md-6">
                                <div class="card bg-dark text-white border-secondary h-100">
                                    <div class="card-body d-flex align-items-center">
                                        <div class="flex-shrink-0 me-3">
                                            ${b.photo ?
                                `<img src="/uploads/${b.photo}" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;">` :
                                `<div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;"><i class="fas fa-user text-white"></i></div>`
                            }
                                        </div>
                                        <div>
                                            <h6 class="mb-1">${b.name} ${b.lastName}</h6>
                                            <small class="text-muted d-block">${b.kinship}</small>
                                            <small class="text-muted">${b.birthday || 'N/A'}</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        beneficiariesContainer.innerHTML = '<div class="col-12 text-center text-white">No hay beneficiarios registrados.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudieron cargar los detalles del usuario.',
                        customClass: { popup: 'swal-dark-popup' }
                    });
                    modal.hide();
                });
        });

        // Edit user handling
        $('#users-datatable').on('click', '.action-icon-edit', function () {
            const userId = $(this).data('user-id');
            const modalElement = document.getElementById('modalEditUser');
            const modal = new bootstrap.Modal(modalElement);
            const form = document.getElementById('editUserForm');

            // Update form action
            form.action = `/${dominio}/user/${userId}/edit`;

            // Reset fields
            document.getElementById('editPhotoDisplay').innerHTML = '<div class="file-upload-icon"><i class="fas fa-camera"></i></div><div class="file-upload-text"><div class="upload-title">Seleccionar nueva foto</div><div class="upload-subtitle">JPG, PNG o GIF (máx. 5MB)</div></div>';
            form.reset();
            // Reset company display
            const companyNameDisplay = document.getElementById('editUserCompanyName');
            if (companyNameDisplay) companyNameDisplay.textContent = '-';

            modal.show();

            // Load companies if needed (check if options exist besides default)
            const companySelect = $('#editUserCompany');
            if (companySelect.find('option').length <= 1) {
                fetch(`/${dominio}/user/companies`)
                    .then(response => response.json())
                    .then(companies => {
                        companies.forEach(company => {
                            const option = new Option(company.name, company.id, false, false);
                            companySelect.append(option);
                        });
                        // Trigger fetch details after companies loaded to ensure selection works
                        fetchUserDetailsForEdit(userId, dominio);
                    })
                    .catch(error => console.error('Error loading companies:', error));
            } else {
                fetchUserDetailsForEdit(userId, dominio);
            }
        });

        function fetchUserDetailsForEdit(userId, dominio) {
            fetch(`/${dominio}/user/${userId}/details`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    // Populate fields
                    document.getElementById('editUserName').value = data.name || '';
                    document.getElementById('editUserLastName').value = data.lastName || '';
                    document.getElementById('editUserEmail').value = data.email || '';
                    document.getElementById('editUserPhone').value = data.phone || '';
                    document.getElementById('editUserCurp').value = data.curp || '';
                    document.getElementById('editUserBirthday').value = data.birthday || ''; // Expecting YYYY-MM-DD
                    document.getElementById('editUserGender').value = data.gender || '';
                    document.getElementById('editUserEducation').value = data.education || '';
                    document.getElementById('editUserEmployeeNumber').value = data.employeeNumber || '';

                    // Set company in the select and show company name
                    if (data.companyId) {
                        $('#editUserCompany').val(data.companyId).trigger('change');
                    }
                    // Show company name in the display field
                    const companyNameDisplay = document.getElementById('editUserCompanyName');
                    if (companyNameDisplay) {
                        companyNameDisplay.textContent = data.company || '-';
                    }

                    if (data.roleId) {
                        document.getElementById('editUserRole').value = data.roleId;
                    }

                    // Photo Preview
                    if (data.photo) {
                        const display = document.getElementById('editPhotoDisplay');
                        display.innerHTML = `<img src="/uploads/${data.photo}" class="img-fluid rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">`;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudieron cargar los datos del usuario para editar.',
                        customClass: { popup: 'swal-dark-popup' }
                    });
                });
        }

        // Handle Edit User Form Submission
        const editUserForm = document.getElementById('editUserForm');
        if (editUserForm) {
            editUserForm.addEventListener('submit', function (e) {
                e.preventDefault();

                const formData = new FormData(this);
                const actionUrl = this.action;

                // Show loading
                Swal.fire({
                    title: 'Actualizando...',
                    text: 'Por favor espere',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: () => Swal.showLoading(),
                    customClass: { popup: 'swal-dark-popup' }
                });

                fetch(actionUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            // Close modal first to avoid backdrop conflicts
                            const modalElement = document.getElementById('modalEditUser');
                            const modal = bootstrap.Modal.getInstance(modalElement);
                            modal.hide();

                            Swal.fire({
                                icon: 'success',
                                title: '¡Actualizado!',
                                text: data.message,
                                customClass: { popup: 'swal-dark-popup' },
                                didClose: () => {
                                    // Force cleanup of any stuck backdrops
                                    document.body.classList.remove('modal-open');
                                    const backdrops = document.getElementsByClassName('modal-backdrop');
                                    while (backdrops.length > 0) {
                                        backdrops[0].parentNode.removeChild(backdrops[0]);
                                    }
                                    document.body.style.paddingRight = '';
                                    document.body.style.overflow = '';
                                }
                            }).then(() => {
                                // Reload DataTable
                                $('#users-datatable').DataTable().ajax.reload(null, false);
                            });
                        } else {
                            let errorMessage = data.message || 'Error al actualizar usuario';
                            if (data.errors && data.errors.length > 0) {
                                errorMessage += ':<br>' + data.errors.join('<br>');
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
                        console.error('Error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Ocurrió un error inesperado al procesar la solicitud.',
                            customClass: { popup: 'swal-dark-popup' }
                        });
                    });
            });
        }

        // Custom file upload display for Edit Modal
        const editPhotoInput = document.getElementById('editPhotoInput');
        if (editPhotoInput) {
            editPhotoInput.addEventListener('change', function (e) {
                const display = document.getElementById('editPhotoDisplay');
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function (e) {
                        display.innerHTML = `<img src="${e.target.result}" class="img-fluid rounded-circle" style="width: 100px; height: 100px; object-fit: cover;">`;
                    }
                    reader.readAsDataURL(this.files[0]);
                }
            });
        }


        // Custom file upload display
        const photoInput = document.getElementById('photoInput');
        if (photoInput) {
            photoInput.addEventListener('change', function (e) {
                const uploadTitle = document.getElementById('uploadTitle');
                if (this.files && this.files[0]) {
                    uploadTitle.textContent = this.files[0].name;
                } else {
                    uploadTitle.textContent = 'Seleccionar foto de perfil';
                }
            });
        }
    }).catch(function (error) {
        console.error('❌ Error loading DataTables:', error);
    });
}

// Auto-initialize when DOM is ready if config is available
$(document).ready(function () {
    // Check if config was set globally
    if (typeof window.userIndexConfig !== 'undefined') {
        initUserIndex(window.userIndexConfig);
    }
});

