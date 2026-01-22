import os
import re

# Master CSS Block (extracted from user/index.html.twig)
MASTER_CSS = """
    <style>
        /* =====================================================
               PALETA REFINADA (Dark UI moderno, elegante y consistente)
               ===================================================== */
        :root {
            --color-bg-primary: #0f1419;
            --color-bg-secondary: #1a1f2e;
            --color-bg-tertiary: #232833;

            --color-surface: #1e1f25;
            --color-surface-hover: #2a2c33;

            --color-text-primary: #e8eaed;
            --color-text-secondary: #9aa0a6;
            --color-text-muted: #5f6368;

            --color-border: #3a3c45;
            --color-border-light: #4a4f5c;

            --color-accent-blue: #4a9eff;
            --color-accent-blue-hover: #5db0ff;

            --color-success: #3D791E;
            --color-danger: #dc3545;

            /* Inputs unificados */
            --input-bg: #2a2f3a;
            --input-bg-hover: #323745;
        }

        /* ============================================
                           UNIFIED TOOLBAR
                           ============================================ */
        .user-toolbar {
            background: var(--color-bg-secondary);
            border-bottom: 1px solid var(--color-border);
            padding: 1.5rem 0;
            margin-top: 140px;
        }

        .toolbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 2rem;
        }

        .toolbar-left .page-title {
            font-family: "League Spartan", sans-serif;
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--color-text-primary);
        }

        .toolbar-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Search Bar */
        .search-container {
            position: relative;
            min-width: 280px;
        }

        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-muted);
            font-size: 14px;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 0.75rem 0.5rem 2.5rem;
            background: var(--color-surface);
            border: 1px solid var(--color-border);
            border-radius: 8px;
            color: var(--color-text-primary);
            font-size: 14px;
            font-family: "Montserrat", sans-serif;
            transition: all 0.2s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--color-accent-blue);
            background: var(--color-bg-tertiary);
            box-shadow: 0 0 0 3px rgba(74, 158, 255, 0.1);
        }

        .form-label {
            color: #ffffff !important; /* Blanco puro y visible */
            font-family: "Montserrat", sans-serif;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }


        .search-input::placeholder {
            color: var(--color-text-muted);
        }

        /* Action Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1.5px solid;
            border-radius: 8px;
            font-family: "Montserrat", sans-serif;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-action-primary {
            background: transparent;
            border-color: var(--color-accent-blue);
            color: var(--color-accent-blue);
        }

        .btn-action-primary:hover {
            background: var(--color-accent-blue);
            color: #fff;
        }

        .btn-action-success {
            background: transparent;
            border-color: var(--color-success);
            color: var(--color-success);
        }

        .btn-action-success:hover {
            background: var(--color-success);
            color: #fff;
        }

        /* ============================================
                           TABLE DESIGN
                           ============================================ */
        .table-wrapper {
            background: var(--color-bg-secondary);
            border-radius: 12px;
            border: 1px solid var(--color-border);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-family: "Montserrat", sans-serif;
        }

        .data-table thead {
            background: var(--color-bg-tertiary);
            border-bottom: 2px solid var(--color-border);
        }

        .data-table thead th {
            padding: 1rem;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            color: var(--color-text-secondary);
        }

        .data-table tbody tr {
            border-bottom: 1px solid var(--color-border);
            transition: 0.15s ease;
        }

        .data-table tbody tr:hover {
            background: var(--color-surface-hover);
        }

        .data-table tbody td {
            padding: 0.9rem 0.75rem;
            color: var(--color-text-primary);
        }

        /* Gender Badge */
        .gender-badge {
            background: var(--color-surface);
            color: var(--color-text-primary);
        }

        /* ============================================
                           ACTION ICONS
                           ============================================ */
        .action-icon {
            width: 32px;
            height: 32px;
            background: var(--color-surface);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--color-text-secondary);
        }

        .action-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .action-icon-view:hover,
        .action-icon-edit:hover {
            background: var(--color-accent-blue);
            color: #fff;
        }

        .action-icon-delete:hover {
            background: var(--color-danger);
            color: #fff;
        }

        /* ============================================
                           MODAL REFINADO
                           ============================================ */

        /* Modal container */
        .modal-form {
            background: var(--color-bg-secondary) !important;
            border: 1px solid var(--color-border-light) !important;
            border-radius: 12px !important;
            box-shadow: 0 25px 70px rgba(0, 0, 0, 0.55) !important;
        }

        /* Header/Footer */
        .modal-form .modal-header,
        .modal-form .modal-footer {
            background: var(--color-bg-tertiary) !important;
            border-color: var(--color-border-light) !important;
        }

        .modal-form .modal-title {
            color: var(--color-text-primary) !important;
        }

        /* Inputs (UNIFICADOS) */
        .modal-form .form-control,
        .modal-form .form-select {
            background: var(--input-bg) !important;
            border: 1.4px solid var(--color-border) !important;
            color: var(--color-text-primary) !important;
            border-radius: 6px !important;
            padding: 0.65rem 0.85rem !important;
            font-size: 14px !important;
            font-family: "Montserrat", sans-serif !important;
            transition: 0.2s ease !important;
        }

        .modal-form .form-control:hover,
        .modal-form .form-select:hover {
            background: var(--input-bg-hover) !important;
        }

        .modal-form .form-control:focus,
        .modal-form .form-select:focus {
            background: var(--input-bg-hover) !important;
            border-color: var(--color-accent-blue) !important;
            box-shadow: 0 0 0 3px rgba(74, 158, 255, 0.12) !important;
        }

        /* File Upload */
        .modal-form .file-upload-display {
            background: var(--input-bg) !important;
            border: 1.4px dashed var(--color-border) !important;
        }

        .modal-form .file-upload-display:hover {
            background: var(--input-bg-hover) !important;
            border-color: var(--color-accent-blue) !important;
        }

        /* Placeholder */
        .modal-form .form-control::placeholder {
            color: var(--color-text-secondary) !important;
        }

        /* Date Input */
        .modal-form input[type="date"] {
            background: var(--input-bg) !important;
            color: var(--color-text-primary) !important;
            color-scheme: dark;
        }

        .modal-form input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(1);
        }

        /* Regions */
        .modal-form .regions-container {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--color-border);
        }

        /* 1) OCULTAR EL INPUT FILE NATIVO Y USAR SOLO TU CAJA CUSTOM */
        .modal-form .file-upload-wrapper input[type="file"] {
            position: absolute !important;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0 !important;
            cursor: pointer;
            z-index: 2;
            /* sin borde ni fondo visibles */
            border: 0 !important;
            background: transparent !important;
        }

        /* 2) FORZAR SELECTS OSCUROS (género, educación, etc.) */
        .modal-form select,
        .modal-form .form-select,
        .modal-form select.form-control-dark {
            background-color: var(--input-bg) !important;
            color: var(--color-text-primary) !important;
            border: 2px solid var(--color-border) !important;
            border-radius: 6px !important;
        }

        /* Flecha del select en modo dark */
        .modal-form .form-select {
            background-image: url("data:image/svg+xml, %3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23e8eaed' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.75rem center !important;
            background-size: 16px 12px !important;
            padding-right: 2.5rem !important;
            appearance: none !important;
            -webkit-appearance: none !important;
            -moz-appearance: none !important;
        }

        /* 3) ARREGLAR CAMPOS QUE EL AUTOFILL PINTA BLANCOS */
        .modal-form input:-webkit-autofill,
        .modal-form input:-webkit-autofill:hover,
        .modal-form input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0 1000px var(--input-bg) inset !important;
            -webkit-text-fill-color: var(--color-text-primary) !important;
        }

        /* Fecha en dark también, por si acaso */
        .modal-form input[type="date"] {
            background-color: var(--input-bg) !important;
            color: var(--color-text-primary) !important;
            border: 2px solid var(--color-border) !important;
        }

        /* FORZAR QUE EL FILE INPUT Y SU WRAPPER DE BOOTSTRAP SEAN OSCUROS Y SIN BLANCO */

        /* El envoltorio blanco de Bootstrap */
        .modal-form .form-control[type="file"],
        .modal-form input[type="file"].form-control {
            background: transparent !important;
            border: none !important;
            padding: 0 !important;
            margin: 0 !important;
            box-shadow: none !important;
            color: transparent !important;
        }

        /* El input file real (invisible) */
        .modal-form input[type="file"] {
            position: absolute !important;
            inset: 0;
            width: 100% !important;
            height: 100% !important;
            opacity: 0 !important;
            cursor: pointer;
            z-index: 3 !important;
            border: none !important;
            background: transparent !important;
        }

        /* El contenedor NUNCA debe verse blanco */
        .modal-form .file-upload-wrapper {
            background: none !important;
        }

        .modal-form .file-upload-wrapper *,
        .modal-form .file-upload-display {
            background: var(--input-bg) !important;
            border: 1.4px dashed var(--color-border) !important;
        }

        /* Backdrop */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.6) !important;
        }

        /* ============================================
                       DATATABLES CUSTOM STYLING
                       ============================================ */

        /* DataTables wrapper */
        .dataTables_wrapper {
            color: var(--color-text-primary);
            font-family: "Montserrat", sans-serif;
        }

        /* Search input */
        .dataTables_filter {
            margin-bottom: 1rem;
        }

        .dataTables_filter label {
            color: var(--color-text-secondary);
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .dataTables_filter input {
            background: var(--color-surface) !important;
            border: 1px solid var(--color-border) !important;
            border-radius: 8px !important;
            color: var(--color-text-primary) !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 14px !important;
            margin-left: 0.5rem !important;
            transition: all 0.2s ease;
            width: 250px !important;
        }

        .dataTables_filter input:focus {
            outline: none !important;
            border-color: var(--color-accent-blue) !important;
            background: var(--color-bg-tertiary) !important;
            box-shadow: 0 0 0 3px rgba(74, 158, 255, 0.1) !important;
        }

        /* Length selector */
        .dataTables_length {
            margin-bottom: 1rem;
        }

        .dataTables_length label {
            color: var(--color-text-secondary);
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dataTables_length select {
            background: var(--color-surface) !important;
            border: 1px solid var(--color-border) !important;
            border-radius: 6px !important;
            color: var(--color-text-primary) !important;
            padding: 0.4rem 2rem 0.4rem 0.75rem !important;
            font-size: 14px !important;
            margin: 0 0.5rem !important;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml, %3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23d1d5db' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3e%3c/svg%3e") !important;
            background-repeat: no-repeat !important;
            background-position: right 0.5rem center !important;
            background-size: 12px 10px !important;
        }

        .dataTables_length select:focus {
            outline: none !important;
            border-color: var(--color-accent-blue) !important;
            box-shadow: 0 0 0 3px rgba(74, 158, 255, 0.1) !important;
        }

        /* Info text */
        .dataTables_info {
            color: var(--color-text-secondary);
            font-size: 14px;
            padding: 1rem 0;
        }

        /* Pagination */
        .dataTables_paginate {
            padding: 1rem 0;
        }

        .dataTables_paginate .paginate_button {
            background: var(--color-surface) !important;
            border: 1px solid var(--color-border) !important;
            border-radius: 6px !important;
            color: var(--color-text-primary) !important;
            padding: 0.5rem 0.75rem !important;
            margin: 0 0.25rem !important;
            font-size: 14px !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            text-decoration: none !important;
            box-shadow: none !important;
        }

        .dataTables_paginate .paginate_button:hover {
            background: var(--color-surface-hover) !important;
            border-color: var(--color-accent-blue) !important;
            color: var(--color-accent-blue) !important;
            box-shadow: none !important;
        }

        .dataTables_paginate .paginate_button.current {
            background: var(--color-accent-blue) !important;
            border-color: var(--color-accent-blue) !important;
            color: #ffffff !important;
            box-shadow: 0 2px 8px rgba(74, 158, 255, 0.3) !important;
        }

        .dataTables_paginate .paginate_button.current:hover {
            background: var(--color-accent-blue-hover) !important;
            border-color: var(--color-accent-blue-hover) !important;
            color: #ffffff !important;
        }

        .dataTables_paginate .paginate_button.disabled,
        .dataTables_paginate .paginate_button.disabled:hover,
        .dataTables_paginate .paginate_button.disabled:active {
            opacity: 0.5 !important;
            cursor: not-allowed !important;
            background: var(--color-surface) !important;
            color: var(--color-text-muted) !important;
            border-color: var(--color-border) !important;
            box-shadow: none !important;
        }

        /* Previous and Next buttons specific styling */
        .dataTables_paginate .paginate_button.previous,
        .dataTables_paginate .paginate_button.next {
            font-weight: 500;
        }

        /* Processing indicator */
        .dataTables_processing {
            background: rgba(26, 31, 46, 0.95) !important;
            color: var(--color-text-primary) !important;
            border: 1px solid var(--color-border) !important;
            border-radius: 8px !important;
            padding: 1.5rem 2rem !important;
            font-size: 14px !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5) !important;
        }

        /* Empty table message */
        .dataTables_empty {
            color: var(--color-text-secondary) !important;
            padding: 3rem !important;
            text-align: center !important;
            font-size: 14px !important;
        }

        /* Row hover effect */
        .data-table tbody tr:hover {
            background: var(--color-surface-hover) !important;
        }

        /* Sorting icons */
        table.dataTable thead .sorting,
        table.dataTable thead .sorting_asc,
        table.dataTable thead .sorting_desc {
            cursor: pointer;
            position: relative;
        }

        table.dataTable thead .sorting:before,
        table.dataTable thead .sorting_asc:before,
        table.dataTable thead .sorting_desc:before,
        table.dataTable thead .sorting:after,
        table.dataTable thead .sorting_asc:after,
        table.dataTable thead .sorting_desc:after {
            opacity: 0.3;
            color: var(--color-text-secondary);
        }

        table.dataTable thead .sorting_asc:before,
        table.dataTable thead .sorting_desc:after {
            opacity: 1;
            color: var(--color-accent-blue);
        }

        /* ============================================
                           RESPONSIVE
                           ============================================ */
        @media (max-width: 992px) {
            .toolbar-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .toolbar-right {
                width: 100%;
                justify-content: space-between;
            }

            .search-container {
                width: 100%;
            }
        }
    </style>
"""

ENTITIES = {
    "Region": {
        "template": "templates/region/index.html.twig",
        "title": "REGIONES",
        "new_route": "app_region_new",
        "table_id": "region-datatable"
    },
    "Company": {
        "template": "templates/company/index.html.twig",
        "title": "EMPRESAS",
        "new_route": "app_company_new",
        "table_id": "company-datatable"
    },
    "Benefit": {
        "template": "templates/benefit/index.html.twig",
        "title": "BENEFICIOS",
        "new_route": "app_benefit_new",
        "table_id": "benefit-datatable"
    },
    "Event": {
        "template": "templates/event/index.html.twig",
        "title": "EVENTOS",
        "new_route": "app_event_new",
        "table_id": "event-datatable"
    },
    "SocialMedia": {
        "template": "templates/social_media/index.html.twig",
        "title": "REDES SOCIALES",
        "new_route": "app_social_media_new",
        "table_id": "social_media-datatable"
    },
    "Notification": {
        "template": "templates/notification/index.html.twig",
        "title": "NOTIFICACIONES",
        "new_route": "app_notification_new",
        "table_id": "notification-datatable"
    },
    "UserAdmin": {
        "template": "templates/user/admin/index.html.twig",
        "title": "ADMINISTRADORES",
        "new_route": "app_user_admin_new",
        "table_id": "user_admin-datatable"
    }
}

def unify_styles(entity_name, config):
    file_path = config['template']
    if not os.path.exists(file_path):
        print(f"❌ Template not found: {file_path}")
        return

    with open(file_path, 'r') as f:
        content = f.read()

    # 1. Extract existing table headers
    table_headers_match = re.search(r'<thead>(.*?)</thead>', content, re.DOTALL)
    table_headers = table_headers_match.group(1) if table_headers_match else "<tr><th>ID</th><th>Acciones</th></tr>"

    # 2. Extract existing modals or includes
    modals = ""
    # Simple heuristic: find includes that look like delete forms or modals
    includes = re.findall(r'{{ include\(.*?\delete_form.*?\) }}', content)
    # We won't keep the delete form include inside the table, but we might want to keep other modals
    # Actually, the delete modal is usually handled by JS now, so we might not need the include if it was per-row
    # But if there's a global modal, we should keep it.
    
    # Let's check for any modal divs defined in the body
    modal_divs = re.findall(r'<div class="modal.*?>.*?</div>\s*</div>', content, re.DOTALL)
    # This is risky, regex for HTML is bad. 
    # Instead, we will assume standard structure and just provide the standard delete modal if not present
    
    modals = """
    <!-- Modal Delete (Standard) -->
    <div class="modal fade" id="modalDelete" tabindex="-1" aria-labelledby="modalDeleteLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-form">
                <div class="modal-header border-0">
                    <h5 class="modal-title fw-bold" id="modalDeleteLabel">Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-exclamation-circle text-danger fa-3x mb-3"></i>
                    <p class="text-white mb-0">¿Estás seguro de que deseas eliminar este registro?</p>
                    <p class="text-muted small mt-2">Esta acción no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnConfirmDelete" class="btn btn-danger px-4">Eliminar</button>
                </div>
            </div>
        </div>
    </div>
    """

    # 3. Construct the new Body
    new_body = f"""
    <section class="header-sntiasg-b">
        <div class="container-fluid container-header">
            <h1 class="title-sntiasg">{config['title']}</h1>
        </div>
    </section>

    <div class="container text-center">
        <div class="row">
            <div class="col-12 d-flex justify-content-between my-3">
                <div class="col-3 new-user">
                    <a href="{{{{ path('{config['new_route']}', {{'dominio': dominio}}) }}}}" class="btn-g fw-bold">NUEVO</a>
                </div>
            </div>
        </div>

        <div class="table-wrapper">
            <table id="{config['table_id']}" class="data-table" style="width:100%">
                <thead>
                    {table_headers}
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>
    
    {modals}
    """

    # 4. Replace Body Block
    # Regex to find {% block body %}...{% endblock %}
    # This is tricky with nested blocks, but usually body is top level
    content = re.sub(r'{% block body %}.*?{% endblock %}', 
                     f"{{% block body %}}\n{new_body}\n{{% endblock %}}", 
                     content, flags=re.DOTALL)

    # 5. Replace Stylesheets Block
    # We want to replace the entire stylesheets block to ensure we have the master CSS
    # But we must keep {{ parent() }}
    new_stylesheets = f"""
{{% block stylesheets %}}
    {{{{ parent() }}}}
    {MASTER_CSS}
{{% endblock %}}
    """
    
    if "{% block stylesheets %}" in content:
        content = re.sub(r'{% block stylesheets %}.*?{% endblock %}', 
                         new_stylesheets, 
                         content, flags=re.DOTALL)
    else:
        # Insert before body if not exists
        content = content.replace("{% block body %}", f"{new_stylesheets}\n{{% block body %}}")

    with open(file_path, 'w') as f:
        f.write(content)
    
    print(f"✅ Unified styles for {entity_name}")

for entity, config in ENTITIES.items():
    unify_styles(entity, config)
