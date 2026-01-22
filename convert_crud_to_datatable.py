#!/usr/bin/env python3
"""
CRUD to DataTables Converter
Automatically converts Symfony Twig CRUDs to modular DataTables architecture

Usage:
    python convert_crud_to_datatable.py --entity company
    python convert_crud_to_datatable.py --all --entities company,region
    python convert_crud_to_datatable.py --entity company --dry-run
"""

import re
import os
import sys
import argparse
import shutil
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Optional, Tuple

# Configuration for each CRUD entity
CRUD_CONFIG = {
    'company': {
        'entity_name': 'empresa',
        'entity_name_plural': 'empresas',
        'collection_var': 'companies',
        'single_var': 'company',
        'table_id': 'companies-datatable',
        'title': 'EMPRESAS',
        'header_class': 'header-sntiasg-b',
        'columns': ['NOMBRE', 'ACCIONES'],
        'column_names': ['name', 'actions'],
        'orderable_columns': [0],
        'column_widths': {1: '150px'},
        'route_prefix': 'app_company'
    },
    'region': {
        'entity_name': 'región',
        'entity_name_plural': 'regiones',
        'collection_var': 'regions',
        'single_var': 'region',
        'table_id': 'regions-datatable',
        'title': 'REGIONES',
        'header_class': 'header-sntiasg-b',
        'columns': ['NOMBRE', 'ACCIONES'],
        'column_names': ['name', 'actions'],
        'orderable_columns': [0],
        'column_widths': {1: '150px'},
        'route_prefix': 'app_region'
    },
    'beneficiary': {
        'entity_name': 'beneficiario',
        'entity_name_plural': 'beneficiarios',
        'collection_var': 'beneficiaries',
        'single_var': 'beneficiary',
        'table_id': 'beneficiaries-datatable',
        'title': 'BENEFICIARIOS',
        'header_class': 'header-sntiasg-b',
        'columns': ['NOMBRE', 'APELLIDO', 'PARENTESCO', 'FECHA NAC.', 
                   'GÉNERO', 'EDUCACIÓN', 'CURP', 'ACCIONES'],
        'column_names': ['name', 'lastName', 'kinship', 'birthday', 
                        'gender', 'education', 'curp', 'actions'],
        'orderable_columns': [0, 1, 2, 3],
        'column_widths': {0: '150px', 7: '150px'},
        'route_prefix': 'app_beneficiary'
    }
}

class Colors:
    """ANSI color codes for terminal output"""
    HEADER = '\033[95m'
    OKBLUE = '\033[94m'
    OKCYAN = '\033[96m'
    OKGREEN = '\033[92m'
    WARNING = '\033[93m'
    FAIL = '\033[91m'
    ENDC = '\033[0m'
    BOLD = '\033[1m'
    UNDERLINE = '\033[4m'

class CrudConverter:
    """Converts traditional CRUD templates to DataTables modular architecture"""
    
    def __init__(self, crud_name: str, project_root: str = '.', dry_run: bool = False):
        if crud_name not in CRUD_CONFIG:
            raise ValueError(f"Entity '{crud_name}' not found in CRUD_CONFIG")
        
        self.crud_name = crud_name
        self.config = CRUD_CONFIG[crud_name]
        self.project_root = Path(project_root)
        self.dry_run = dry_run
        
        # Paths
        self.template_dir = self.project_root / 'templates' / crud_name
        self.js_dir = self.project_root / 'assets' / 'js' / 'crud'
        self.original_index = self.template_dir / 'index.html.twig'
        self.table_content = self.template_dir / '_table_content.html.twig'
        self.crud_js = self.js_dir / f'{crud_name}-crud.js'
        
    def log(self, message: str, color: str = Colors.OKBLUE):
        """Print colored log message"""
        print(f"{color}{message}{Colors.ENDC}")
    
    def backup_original(self) -> bool:
        """Create backup of original index.html.twig"""
        if not self.original_index.exists():
            self.log(f"❌ Original file not found: {self.original_index}", Colors.FAIL)
            return False
        
        timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
        backup_path = self.original_index.with_suffix(f'.backup_{timestamp}')
        
        if self.dry_run:
            self.log(f"[DRY RUN] Would create backup: {backup_path}", Colors.WARNING)
            return True
        
        try:
            shutil.copy2(self.original_index, backup_path)
            self.log(f"✅ Backup created: {backup_path}", Colors.OKGREEN)
            return True
        except Exception as e:
            self.log(f"❌ Failed to create backup: {e}", Colors.FAIL)
            return False
    
    def extract_table_content(self) -> Optional[str]:
        """Extract table HTML from original template"""
        if not self.original_index.exists():
            self.log(f"❌ File not found: {self.original_index}", Colors.FAIL)
            return None
        
        with open(self.original_index, 'r', encoding='utf-8') as f:
            content = f.read()
        
        # Find table block
        table_match = re.search(
            r'<table[^>]*>.*?</table>',
            content,
            re.DOTALL
        )
        
        if not table_match:
            self.log("❌ No table found in template", Colors.FAIL)
            return None
        
        table_html = table_match.group(0)
        self.log("✅ Table content extracted", Colors.OKGREEN)
        return table_html
    
    def generate_table_content_template(self, table_html: str) -> str:
        """Generate _table_content.html.twig"""
        template = f'''{{%% set dominio = app.request.attributes.get('dominio') %%}}

<div class="table-wrapper">
    <table id="{self.config['table_id']}" class="styled-table display compact nowrap w-100">
{table_html}
    </table>
</div>
'''
        # Update table tag to use new ID and classes
        template = re.sub(
            r'<table[^>]*>',
            f'<table id="{self.config["table_id"]}" class="styled-table display compact nowrap w-100">',
            template,
            count=1
        )
        
        # Ensure thead has proper class
        template = re.sub(
            r'<thead[^>]*>',
            '<thead class="table-primary text-dark">',
            template
        )
        
        return template.strip()
    
    def generate_clean_index_template(self) -> str:
        """Generate clean index.html.twig without JavaScript"""
        template = f'''{{%% extends 'base.html.twig' %%}}

{{%% set dominio = app.request.attributes.get('dominio') %%}}

{{%% block title %%}}{self.config['entity_name_plural'].capitalize()}{{%% endblock %%}}

{{%% block stylesheets %%}}
    {{{{ parent() }}}}
    <link rel="stylesheet" href="{{{{ asset('styles/dataTables.min.css') }}}}">
    <link rel="stylesheet" href="{{{{ asset('styles/datatables-custom.css') }}}}">
{{%% endblock %%}}

{{%% block body %%}}
    <section class="{self.config['header_class']}">
        <div class="container-fluid container-header">
            <h1 class="title-sntiasg">{self.config['title']}</h1>
        </div>
    </section>

    <div class="container-fluid px-5">
        {{# Controles superiores #}}
        <div class="row">
            <div class="col-12 d-flex justify-content-between my-3">
                <div class="new-entity">
                    <a href="{{{{ path('{self.config['route_prefix']}_new', {{'dominio': dominio}}) }}}}" 
                       class="btn-g fw-bold"
                       title="Dar de alta">
                        <i class="fas fa-plus me-2"></i>
                        DAR DE ALTA
                    </a>
                </div>
            </div>
        </div>

        {{# CONTENEDOR DINÁMICO PARA LA TABLA #}}
        <div id="table-ajax-container" 
             data-dominio="{{{{ dominio }}}}" 
             class="px-4">
            {{{{ include('{self.crud_name}/_table_content.html.twig') }}}}
        </div>
    </div>
{{%% endblock %%}}

{{%% block javascripts %%}}
    {{{{ parent() }}}}
    <script src="{{{{ asset('js/dataTables.min.js') }}}}"></script>
    <script src="{{{{ asset('js/crud/{self.crud_name}-crud.js') }}}}" type="module"></script>
{{%% endblock %%}}
'''
        return template.strip()
    
    def generate_crud_js(self) -> str:
        """Generate entity-crud.js file"""
        
        # Build columnDefs
        column_defs = []
        column_defs.append(
            "            {\n"
            f"                targets: {len(self.config['columns']) - 1}, // Columna de acciones\n"
            "                orderable: false,\n"
            "                searchable: false\n"
            "            }"
        )
        
        # Add custom widths
        for col_idx, width in self.config.get('column_widths', {}).items():
            column_defs.append(
                "            {\n"
                f"                targets: {col_idx},\n"
                f"                width: '{width}'\n"
                "            }"
            )
        
        column_defs_str = ',\n'.join(column_defs)
        
        js_content = f'''/**
 * {self.config['entity_name_plural'].capitalize()} CRUD - Configuración específica
 */

import {{ CrudManager }} from './crud-manager.js';

/**
 * Obtiene el dominio de la URL actual
 */
function getDominio() {{
    const container = document.getElementById('table-ajax-container');
    if (container && container.dataset.dominio) {{
        return container.dataset.dominio;
    }}
    
    // Alternativa: extraer del path
    const pathParts = window.location.pathname.split('/');
    return pathParts[1] || '';
}}

/**
 * Inicializa el CRUD de {self.config['entity_name_plural']} cuando el DOM esté listo
 */
document.addEventListener('DOMContentLoaded', function() {{
    const dominio = getDominio();
    
    // Configuración específica para {self.config['entity_name_plural']}
    const {self.crud_name}Crud = new CrudManager({{
        tableId: '{self.config['table_id']}',
        dominio: dominio,
        entityName: '{self.config['entity_name']}',
        entityNamePlural: '{self.config['entity_name_plural']}',
        
        // Definiciones de columnas específicas
        columnDefs: [
{column_defs_str}
        ],
        
        // Opciones adicionales de DataTable
        dataTableOptions: {{
            pageLength: 25,
            order: [[0, 'asc']], // Ordenar por primera columna
            responsive: true,
            autoWidth: false
        }}
    }});

    // Hacer disponible globalmente si es necesario
    window.{self.crud_name}CrudManager = {self.crud_name}Crud;
    
    console.log('{self.config['entity_name_plural'].capitalize()} CRUD Manager inicializado correctamente');
}});
'''
        return js_content.strip()
    
    def write_file(self, path: Path, content: str) -> bool:
        """Write content to file"""
        if self.dry_run:
            self.log(f"[DRY RUN] Would write to: {path}", Colors.WARNING)
            self.log(f"[DRY RUN] Content preview (first 200 chars):\n{content[:200]}...\n", Colors.OKCYAN)
            return True
        
        try:
            path.parent.mkdir(parents=True, exist_ok=True)
            with open(path, 'w', encoding='utf-8') as f:
                f.write(content)
            self.log(f"✅ Created: {path}", Colors.OKGREEN)
            return True
        except Exception as e:
            self.log(f"❌ Failed to write {path}: {e}", Colors.FAIL)
            return False
    
    def convert(self) -> bool:
        """Execute full conversion"""
        self.log(f"\n{'='*60}", Colors.HEADER)
        self.log(f"Converting {self.crud_name.upper()} CRUD to DataTables", Colors.HEADER)
        self.log(f"{'='*60}\n", Colors.HEADER)
        
        if self.dry_run:
            self.log("🔍 DRY RUN MODE - No files will be modified\n", Colors.WARNING)
        
        # Step 1: Backup original
        self.log("📦 Step 1: Creating backup...", Colors.BOLD)
        if not self.backup_original():
            return False
        
        # Step 2: Extract table
        self.log("\n📋 Step 2: Extracting table content...", Colors.BOLD)
        table_html = self.extract_table_content()
        if not table_html:
            return False
        
        # Step 3: Generate _table_content.html.twig
        self.log("\n🔨 Step 3: Generating _table_content.html.twig...", Colors.BOLD)
        table_content = self.generate_table_content_template(table_html)
        if not self.write_file(self.table_content, table_content):
            return False
        
        # Step 4: Generate clean index.html.twig
        self.log("\n🔨 Step 4: Generating clean index.html.twig...", Colors.BOLD)
        clean_index = self.generate_clean_index_template()
        if not self.write_file(self.original_index, clean_index):
            return False
        
        # Step 5: Generate entity-crud.js
        self.log("\n🔨 Step 5: Generating {}-crud.js...".format(self.crud_name), Colors.BOLD)
        crud_js = self.generate_crud_js()
        if not self.write_file(self.crud_js, crud_js):
            return False
        
        # Summary
        self.log(f"\n{'='*60}", Colors.OKGREEN)
        self.log(f"✅ Conversion completed successfully!", Colors.OKGREEN)
        self.log(f"{'='*60}\n", Colors.OKGREEN)
        
        if not self.dry_run:
            self.log("Files created/modified:", Colors.BOLD)
            self.log(f"  ✓ {self.table_content}")
            self.log(f"  ✓ {self.original_index}")
            self.log(f"  ✓ {self.crud_js}")
            
            self.log("\nNext steps:", Colors.WARNING)
            self.log("  1. Review the generated files")
            self.log(f"  2. Test /{self.crud_name} in your browser")
            self.log("  3. Verify DataTables functionality (search, pagination, sorting)")
        
        return True


def main():
    parser = argparse.ArgumentParser(
        description='Convert Symfony Twig CRUDs to DataTables architecture',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog='''
Examples:
  %(prog)s --entity company
  %(prog)s --entity company --dry-run
  %(prog)s --all --entities company,region
        '''
    )
    
    parser.add_argument(
        '--entity',
        type=str,
        choices=list(CRUD_CONFIG.keys()),
        help='Entity to convert'
    )
    
    parser.add_argument(
        '--entities',
        type=str,
        help='Comma-separated list of entities (used with --all)'
    )
    
    parser.add_argument(
        '--all',
        action='store_true',
        help='Convert all specified entities'
    )
    
    parser.add_argument(
        '--dry-run',
        action='store_true',
        help='Preview changes without modifying files'
    )
    
    parser.add_argument(
        '--project-root',
        type=str,
        default='.',
        help='Path to project root (default: current directory)'
    )
    
    args = parser.parse_args()
    
    # Determine entities to convert
    entities_to_convert = []
    
    if args.all and args.entities:
        entities_to_convert = [e.strip() for e in args.entities.split(',')]
    elif args.entity:
        entities_to_convert = [args.entity]
    else:
        parser.print_help()
        return 1
    
    # Validate entities
    for entity in entities_to_convert:
        if entity not in CRUD_CONFIG:
            print(f"{Colors.FAIL}Error: Unknown entity '{entity}'{Colors.ENDC}")
            print(f"Available entities: {', '.join(CRUD_CONFIG.keys())}")
            return 1
    
    # Convert each entity
    success_count = 0
    for entity in entities_to_convert:
        try:
            converter = CrudConverter(entity, args.project_root, args.dry_run)
            if converter.convert():
                success_count += 1
            else:
                print(f"{Colors.FAIL}Failed to convert {entity}{Colors.ENDC}")
        except Exception as e:
            print(f"{Colors.FAIL}Error converting {entity}: {e}{Colors.ENDC}")
            import traceback
            traceback.print_exc()
    
    # Final summary
    print(f"\n{Colors.BOLD}{'='*60}{Colors.ENDC}")
    print(f"{Colors.OKGREEN}✅ Converted {success_count}/{len(entities_to_convert)} entities{Colors.ENDC}")
    print(f"{Colors.BOLD}{'='*60}{Colors.ENDC}\n")
    
    return 0 if success_count == len(entities_to_convert) else 1


if __name__ == '__main__':
    sys.exit(main())
