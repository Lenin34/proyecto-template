document.addEventListener('DOMContentLoaded', function () {
    // Inicializar Select2 en modales
    initSelect2();

    // Manejar apertura del modal de creación
    const modalNewForm = document.getElementById('modalNewForm');
    if (modalNewForm) {
        modalNewForm.addEventListener('show.bs.modal', function () {
            // Limpiar formulario
            document.getElementById('formNewForm').reset();
            $('#new_companyIds').val(null).trigger('change');
            clearErrors('formNewForm');
        });
    }

    // Manejar envío del formulario de creación
    const btnSaveNewForm = document.getElementById('btnSaveNewForm');
    if (btnSaveNewForm) {
        btnSaveNewForm.addEventListener('click', function () {
            setButtonLoading(this, true);
            submitForm('formNewForm', window.formConfig.urls.new, 'POST', function (response) {
                // Éxito
                Swal.fire({
                    icon: 'success',
                    title: '¡Creado!',
                    text: 'El formulario ha sido creado exitosamente.',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.reload();
                });
            }, () => setButtonLoading(btnSaveNewForm, false));
        });
    }

    // Manejar apertura del modal de edición
    const modalEditForm = document.getElementById('modalEditForm');
    if (modalEditForm) {
        // Delegación de eventos para botones de edición (ya que DataTables puede recrear el DOM)
        document.body.addEventListener('click', function (e) {
            const btnEdit = e.target.closest('.btn-edit-modal');
            if (btnEdit) {
                const formId = btnEdit.dataset.id;
                loadFormData(formId);
                const modal = new bootstrap.Modal(modalEditForm);
                modal.show();
            }
        });
    }

    // Manejar envío del formulario de edición
    const btnSaveEditForm = document.getElementById('btnSaveEditForm');
    if (btnSaveEditForm) {
        btnSaveEditForm.addEventListener('click', function () {
            setButtonLoading(this, true);
            const formId = document.getElementById('edit_id').value;
            const url = window.formConfig.urls.editBase.replace('0', formId);

            submitForm('formEditForm', url, 'POST', function (response) {
                Swal.fire({
                    icon: 'success',
                    title: '¡Actualizado!',
                    text: 'El formulario ha sido actualizado exitosamente.',
                    showConfirmButton: false,
                    timer: 1500
                }).then(() => {
                    window.location.reload();
                });
            }, () => setButtonLoading(btnSaveEditForm, false));
        });
        // Manejar eliminación de formulario
        document.body.addEventListener('click', function (e) {
            const btnDelete = e.target.closest('.btn-delete-form');
            if (btnDelete) {
                const formId = btnDelete.dataset.id;
                const formName = btnDelete.dataset.name;
                const dominio = window.formConfig.dominio;

                // Validate that we have all required data
                if (!formId || !dominio) {
                    console.error('Missing formId or dominio', { formId, dominio });
                    Swal.fire('Error', 'Datos incompletos para eliminar el formulario', 'error');
                    return;
                }

                Swal.fire({
                    title: '¿Eliminar formulario?',
                    html: `¿Está seguro de eliminar el formulario <strong>${formName}</strong>?<br><small class="text-muted">Esta acción no se puede deshacer.</small>`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-trash-alt me-1"></i> Sí, eliminar',
                    cancelButtonText: '<i class="fas fa-times me-1"></i> Cancelar',
                    reverseButtons: true,
                    didOpen: () => {
                        const confirmBtn = Swal.getConfirmButton();
                        if (confirmBtn) {
                            confirmBtn.style.setProperty('background-color', '#dc3545', 'important');
                            confirmBtn.style.setProperty('border-color', '#dc3545', 'important');
                            confirmBtn.classList.add('btn', 'btn-danger');
                        }
                        const cancelBtn = Swal.getCancelButton();
                        if (cancelBtn) {
                            cancelBtn.classList.add('btn', 'btn-secondary');
                        }
                    },
                    customClass: {
                        popup: 'swal-dark-popup'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Eliminando...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            allowEscapeKey: false,
                            showConfirmButton: false,
                            didOpen: () => Swal.showLoading()
                        });

                        const formData = new FormData();
                        formData.append('_token', window.formConfig.csrfToken);

                        // Debug: verificar que tenemos el token
                        console.log('Eliminando formulario:', { formId, token: window.formConfig.csrfToken ? 'presente' : 'FALTA' });

                        fetch(`/${dominio}/admin/forms/${formId}/delete`, {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            }
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Eliminado!',
                                        text: data.message || 'El formulario ha sido eliminado exitosamente.',
                                        showConfirmButton: false,
                                        timer: 1500
                                    }).then(() => {
                                        window.location.reload();
                                    });
                                } else {
                                    throw new Error(data.message || 'Error al eliminar el formulario');
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire('Error', error.message || 'No se pudo eliminar el formulario', 'error');
                            });
                    }
                });
            }
        });
    }
});

function setButtonLoading(btn, isLoading) {
    if (isLoading) {
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.dataset.originalText = originalText;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
    } else {
        btn.disabled = false;
        if (btn.dataset.originalText) {
            btn.innerHTML = btn.dataset.originalText;
        }
    }
}

function formatState(state) {
    if (!state.id) {
        return state.text;
    }
    // Usar icono de edificio para empresas
    var $state = $(
        '<span><i class="fas fa-building me-2"></i>' + state.text + '</span>'
    );
    return $state;
};

function initSelect2() {
    // Configuracion comun para Select2
    const select2Config = {
        theme: 'bootstrap-5',
        placeholder: 'Seleccionar empresas (vacio = todas)',
        allowClear: true,
        width: '100%',
        dropdownParent: null, // Se ajustara dinamicamente
        data: window.availableCompanies || [],
        templateResult: formatState,
        templateSelection: formatState
    };

    // Inicializar en modal New
    $('#new_companyIds').select2({
        ...select2Config,
        dropdownParent: $('#modalNewForm')
    });

    // Inicializar en modal Edit
    $('#edit_companyIds').select2({
        ...select2Config,
        dropdownParent: $('#modalEditForm')
    });
}

function loadFormData(id) {
    const url = window.formConfig.urls.detailsBase.replace('0', id) + '?format=json';

    // Mostrar loading o deshabilitar inputs
    const form = document.getElementById('formEditForm');
    form.classList.add('opacity-50');

    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => {
            if (!response.ok) throw new Error('Error al cargar datos');
            return response.json();
        })
        .then(data => {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_description').value = data.description || '';

            // Cargar empresas en Select2
            if (data.companyIds && Array.isArray(data.companyIds)) {
                $('#edit_companyIds').val(data.companyIds).trigger('change');
            } else {
                $('#edit_companyIds').val(null).trigger('change');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire('Error', 'No se pudieron cargar los datos del formulario', 'error');
        })
        .finally(() => {
            form.classList.remove('opacity-50');
        });
}

function submitForm(formId, url, method, successCallback, errorCallback) {
    const form = document.getElementById(formId);
    clearErrors(formId);

    // Recopilar datos
    const formData = new FormData(form);
    const data = {};
    formData.forEach((value, key) => {
        // Parsear nombres de campos de Symfony: form_template[name] -> name
        let cleanKey = key;
        const match = key.match(/\[(.*?)\]/);
        if (match) {
            cleanKey = match[1];
        }

        // Manejar arrays (como companyIds[])
        if (key.endsWith('[]')) {
            // Si venia con corchetes de array, el cleanKey ya los perdio en el match anterior si estaba dentro de otro corchete
            // Pero si es directo como companyIds[], el match podria haber fallado o ser diferente.
            // Caso 1: form_template[companies][] -> cleanKey = companies
            // Caso 2: companies[] -> cleanKey = companies

            if (key.includes('[')) {
                // Re-evaluar cleanKey para arrays anidados o simples
                const parts = key.split('[');
                if (parts.length > 1) {
                    // form_template[companies][] -> parts[0]=form_template, parts[1]=companies], parts[2]=]
                    // Queremos lo que esta dentro del primer par de corchetes que tenga contenido
                    const innerMatch = key.match(/\[([^\]]+)\]/);
                    if (innerMatch) {
                        cleanKey = innerMatch[1];
                    } else {
                        // Fallback por si es root[]
                        cleanKey = parts[0];
                    }
                }
            } else {
                cleanKey = key.slice(0, -2);
            }

            if (!data[cleanKey]) data[cleanKey] = [];
            data[cleanKey].push(value);
        } else {
            data[cleanKey] = value;
        }
    });

    // Para Select2 multiple, si está vacío, FormData no envía nada.
    // Aseguramos que companyIds sea un array vacío si no hay selección
    if (!data.companyIds && (formId === 'formNewForm' || formId === 'formEditForm')) {
        // Verificar si el select existe en el form
        if (form.querySelector('select[name="companyIds[]"]')) {
            data.companyIds = [];
        }
    }
    // Si Select2 tiene valores, FormData los captura uno por uno.
    // Nuestra lógica de arriba ya los agrupa en un array.

    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
        .then(async response => {
            const json = await response.json();
            if (!response.ok) {
                throw { status: response.status, data: json };
            }
            return json;
        })
        .then(response => {
            if (successCallback) successCallback(response);
        })
        .catch(error => {
            console.error('Error:', error);
            if (errorCallback) errorCallback();
            if (error.data && error.data.errors) {
                showErrors(formId, error.data.errors);
            } else {
                Swal.fire('Error', error.data?.message || 'Ocurrió un error inesperado', 'error');
            }
        });
}

function clearErrors(formId) {
    const form = document.getElementById(formId);
    const invalidInputs = form.querySelectorAll('.is-invalid');
    invalidInputs.forEach(input => input.classList.remove('is-invalid'));
    const feedbacks = form.querySelectorAll('.invalid-feedback');
    feedbacks.forEach(fb => fb.style.display = 'none'); // Ocultar feedbacks custom
}

function showErrors(formId, errors) {
    const form = document.getElementById(formId);

    // errors es un objeto { campo: "mensaje" }
    for (const [field, message] of Object.entries(errors)) {
        // Buscar input por nombre. Nota: los nombres en DTO pueden diferir de los del form si no coinciden exactamente.
        // Asumimos coincidencia: name -> name, description -> description
        const input = form.querySelector(`[name="${field}"]`) || form.querySelector(`[name="${field}[]"]`);
        if (input) {
            input.classList.add('is-invalid');

            // Buscar o crear div de feedback
            let feedback = input.nextElementSibling;
            if (!feedback || !feedback.classList.contains('invalid-feedback')) {
                // Intentar buscar en el padre si es select2
                if (input.classList.contains('select2-hidden-accessible')) {
                    const container = input.nextElementSibling; // .select2-container
                    if (container) {
                        feedback = container.nextElementSibling;
                    }
                }
            }

            if (feedback && feedback.classList.contains('invalid-feedback')) {
                feedback.textContent = message;
                feedback.style.display = 'block';
            }
        }
    }
}
