/**
 * Regiones CRUD - Configuración específica
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
 * Inicializa el CRUD de regiones cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function() {
    const dominio = getDominio();
    
    // Configuración específica para regiones
    const regionCrud = new CrudManager({
        tableId: 'regions-datatable',
        dominio: dominio,
        entityName: 'región',
        entityNamePlural: 'regiones',
        
        // Definiciones de columnas específicas
        columnDefs: [
            {
                targets: 1, // Columna de acciones
                orderable: false,
                searchable: false
            },
            {
                targets: 1,
                width: '150px'
            }
        ],
        
        // Opciones adicionales de DataTable
        dataTableOptions: {
            pageLength: 25,
            order: [[0, 'asc']], // Ordenar por primera columna
            responsive: true,
            autoWidth: false
        }
    });

    // Hacer disponible globalmente si es necesario
    window.regionCrudManager = regionCrud;
    
    console.log('Regiones CRUD Manager inicializado correctamente');
});