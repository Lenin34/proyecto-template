import './styles/master.css';

import 'select2/dist/css/select2.min.css';

import { initSelect2Multiple } from './select/initSelect2.js';

import 'bootstrap';

import 'bootstrap/dist/css/bootstrap.min.css';

import 'bootstrap/dist/js/bootstrap.bundle.min.js';


document.addEventListener('DOMContentLoaded', () => {
    initSelect2Multiple();

    // Confirmacion con SweetAlert2 para formularios de activacion/desactivacion/eliminacion
    document.querySelectorAll('.form-delete').forEach(form => {
        form.addEventListener('submit', function(e) {
            const dominio = form.getAttribute('data-dominio') || 'este tenant';
            if (window.SwalHelper && typeof window.SwalHelper.confirmDelete === 'function') {
                e.preventDefault();
                window.SwalHelper.confirmDelete(dominio).then(result => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            } else {
                if (!confirm('Eliminar ' + dominio + '?')) {
                    e.preventDefault();
                }
            }
        });
    });

    // Acciones Seleccionar/Deseleccionar todo en Modulos
    const selectAll = document.getElementById('features-select-all');
    const clearAll = document.getElementById('features-clear-all');
    const featureChecks = document.querySelectorAll('.choice-input[type="checkbox"]');

    selectAll?.addEventListener('click', (e) => {
        e.preventDefault();
        featureChecks.forEach(chk => chk.checked = true);
    });

    clearAll?.addEventListener('click', (e) => {
        e.preventDefault();
        featureChecks.forEach(chk => chk.checked = false);
    });

    // Permitir click en toda la fila para alternar el checkbox
    document.querySelectorAll('.fieldset-item').forEach(row => {
        row.addEventListener('click', (e) => {
            const isControl = e.target.closest('input, label, a, button');
            if (isControl) return; // evitar doble toggle
            const chk = row.querySelector('input[type="checkbox"]');
            if (chk) chk.checked = !chk.checked;
        });
    });

    // Mostrar nombre de archivo seleccionado en labels
    ['aviso','logo'].forEach(id => {
        const input = document.getElementById(id);
        if (!input) return;
        input.addEventListener('change', () => {
            const file = input.files && input.files[0] ? input.files[0].name : 'Archivo';
            const label = document.querySelector(`label[for="${id}"]`);
            if (label) label.textContent = file || 'Archivo';
        });
    });
});