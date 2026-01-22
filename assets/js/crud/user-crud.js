/**
 * User CRUD - Configuración específica para gestión de usuarios/agremiados
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
 * Inicializa el CRUD de usuarios cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function () {
    const dominio = getDominio();

    // Configuración específica para usuarios
    const userCrud = new CrudManager({
        tableId: 'users-datatable',
        dominio: dominio,
        entityName: 'agremiado',
        entityNamePlural: 'agremiados',

        // Definiciones de columnas específicas
        columnDefs: [
            {
                targets: 0, // Columna de acciones
                orderable: false,
                searchable: false,
                width: '120px'
            },
            {
                targets: 1, // Nombre completo
                className: 'text-start fw-bold'
            },
            {
                targets: 3, // Fecha de nacimiento
                width: '120px'
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
    window.userCrudManager = userCrud;

    console.log('User CRUD Manager inicializado correctamente');
});
