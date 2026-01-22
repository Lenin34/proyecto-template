/**
 * User Admin CRUD - Configuración específica
 */

import { CrudManager } from './crud-manager.js';

/**
 * Obtiene el dominio de la URL actual
 */
function getDominio() {
    const container = document.getElementById('table-ajax-container');
    if (container && container.dataset.dominio) {
        return container.dataset.dominio;
    }

    // Alternativa: extraer del path
    const pathParts = window.location.pathname.split('/');
    return pathParts[1] || '';
}

/**
 * Inicializa el CRUD de usuarios del sistema cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function () {
    const dominio = getDominio();

    // Configuración específica para usuarios del sistema
    const userAdminCrud = new CrudManager({
        tableId: 'user-admin-datatable',
        dominio: dominio,
        entityName: 'usuario del sistema',
        entityNamePlural: 'usuarios del sistema',

        // Definiciones de columnas específicas
        columnDefs: [
            {
                targets: 0, // Columna de acciones
                orderable: false,
                searchable: false,
                width: '120px'
            },
            {
                targets: 1, // Nombre de usuario
                className: 'text-start fw-bold'
            }
        ],

        // Opciones adicionales de DataTable
        dataTableOptions: {
            pageLength: 25,
            order: [[1, 'asc']], // Ordenar por nombre
            responsive: true,
            autoWidth: false
        }
    });

    // Hacer disponible globalmente si es necesario
    window.userAdminCrudManager = userAdminCrud;

    console.log('User Admin CRUD Manager inicializado correctamente');
});
