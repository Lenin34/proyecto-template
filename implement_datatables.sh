#!/bin/bash

# Script para implementar DataTables con server-side processing en múltiples entidades
# Autor: Sistema automatizado
# Fecha: 2025-11-28

set -e  # Salir si hay algún error

# Colores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}  DataTables Implementation Script${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Definir las entidades a procesar
declare -A ENTITIES=(
    ["SocialMedia"]="social_media"
    ["AdminUser"]="admin_user"
    ["Company"]="company"
    ["Region"]="region"
    ["Benefit"]="benefit"
    ["Notification"]="notification"
    ["Event"]="event"
)

# Función para crear el endpoint DataTable en el controlador
create_datatable_endpoint() {
    local entity_class=$1
    local entity_var=$2
    local controller_file="src/Controller/${entity_class}Controller.php"
    
    echo -e "${YELLOW}Procesando: ${entity_class}${NC}"
    
    if [ ! -f "$controller_file" ]; then
        echo -e "${RED}  ✗ Controlador no encontrado: $controller_file${NC}"
        return 1
    fi
    
    # Verificar si ya existe el endpoint datatable
    if grep -q "app_${entity_var}_datatable" "$controller_file"; then
        echo -e "${YELLOW}  ⚠ Endpoint datatable ya existe, saltando...${NC}"
        return 0
    fi
    
    echo -e "${GREEN}  ✓ Agregando endpoint datatable...${NC}"
    
    # Aquí iría la lógica para agregar el endpoint
    # Por ahora solo mostramos el mensaje
    echo -e "${BLUE}    → Endpoint: app_${entity_var}_datatable${NC}"
}

# Función para actualizar la vista Twig
update_twig_template() {
    local entity_var=$1
    local template_file="templates/${entity_var}/index.html.twig"
    
    if [ ! -f "$template_file" ]; then
        echo -e "${RED}  ✗ Template no encontrado: $template_file${NC}"
        return 1
    fi
    
    echo -e "${GREEN}  ✓ Actualizando template Twig...${NC}"
    echo -e "${BLUE}    → Template: $template_file${NC}"
}

# Procesar cada entidad
for entity_class in "${!ENTITIES[@]}"; do
    entity_var="${ENTITIES[$entity_class]}"
    echo ""
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}Procesando entidad: ${entity_class}${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    create_datatable_endpoint "$entity_class" "$entity_var"
    update_twig_template "$entity_var"
done

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}  Proceso completado${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}Nota: Este es un script de demostración.${NC}"
echo -e "${YELLOW}Para implementación completa, se requiere:${NC}"
echo -e "  1. Agregar endpoints datatable en controladores"
echo -e "  2. Actualizar templates Twig"
echo -e "  3. Agregar estilos CSS"
echo -e "  4. Limpiar caché de Symfony"
echo ""
