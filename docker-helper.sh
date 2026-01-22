#!/bin/bash

# 🐳 Docker Helper Script para app-ctm
# Este script te ayuda a manejar los contenedores de manera más eficiente

set -e

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Función para mostrar ayuda
show_help() {
    echo -e "${BLUE}🐳 Docker Helper Script para app-ctm${NC}"
    echo ""
    echo "Uso: $0 [comando]"
    echo ""
    echo "Comandos disponibles:"
    echo -e "  ${GREEN}start${NC}     - Inicia todos los contenedores"
    echo -e "  ${GREEN}stop${NC}      - Detiene todos los contenedores"
    echo -e "  ${GREEN}restart${NC}   - Reinicia todos los contenedores"
    echo -e "  ${GREEN}status${NC}    - Muestra el estado de los contenedores"
    echo -e "  ${GREEN}logs${NC}      - Muestra los logs de la aplicación"
    echo -e "  ${GREEN}clean${NC}     - Limpia contenedores, imágenes y volúmenes no utilizados"
    echo -e "  ${GREEN}rebuild${NC}   - Reconstruye y reinicia todos los contenedores"
    echo -e "  ${GREEN}db${NC}        - Conecta a la base de datos MySQL"
    echo -e "  ${GREEN}help${NC}      - Muestra esta ayuda"
    echo ""
}

# Función para verificar si Docker está corriendo
check_docker() {
    if ! docker info >/dev/null 2>&1; then
        echo -e "${RED}❌ Docker no está corriendo. Por favor inicia Docker primero.${NC}"
        exit 1
    fi
}

# Función para iniciar contenedores
start_containers() {
    echo -e "${BLUE}🚀 Iniciando contenedores...${NC}"
    docker compose up -d
    echo -e "${GREEN}✅ Contenedores iniciados exitosamente${NC}"
    show_status
}

# Función para detener contenedores
stop_containers() {
    echo -e "${YELLOW}🛑 Deteniendo contenedores...${NC}"
    docker compose down
    echo -e "${GREEN}✅ Contenedores detenidos exitosamente${NC}"
}

# Función para reiniciar contenedores
restart_containers() {
    echo -e "${YELLOW}🔄 Reiniciando contenedores...${NC}"
    docker compose restart
    echo -e "${GREEN}✅ Contenedores reiniciados exitosamente${NC}"
    show_status
}

# Función para mostrar estado
show_status() {
    echo -e "${BLUE}📊 Estado de los contenedores:${NC}"
    docker compose ps
    echo ""
    echo -e "${BLUE}💾 Volúmenes:${NC}"
    docker volume ls | grep app-ctm
}

# Función para mostrar logs
show_logs() {
    echo -e "${BLUE}📋 Logs de la aplicación (últimas 50 líneas):${NC}"
    docker compose logs --tail=50 asnmx
}

# Función para limpiar Docker
clean_docker() {
    echo -e "${YELLOW}🧹 Limpiando Docker...${NC}"
    echo "Esto eliminará:"
    echo "- Contenedores detenidos"
    echo "- Imágenes no utilizadas"
    echo "- Redes no utilizadas"
    echo "- Cache de build"
    echo ""
    read -p "¿Estás seguro? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker system prune -f
        echo -e "${GREEN}✅ Limpieza completada${NC}"
    else
        echo -e "${YELLOW}❌ Limpieza cancelada${NC}"
    fi
}

# Función para reconstruir contenedores
rebuild_containers() {
    echo -e "${YELLOW}🔨 Reconstruyendo contenedores...${NC}"
    echo "Esto detendrá todos los contenedores y los reconstruirá desde cero."
    echo ""
    read -p "¿Estás seguro? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        docker compose down
        docker compose build --no-cache
        docker compose up -d
        echo -e "${GREEN}✅ Contenedores reconstruidos exitosamente${NC}"
        show_status
    else
        echo -e "${YELLOW}❌ Reconstrucción cancelada${NC}"
    fi
}

# Función para conectar a la base de datos
connect_db() {
    echo -e "${BLUE}🗄️ Conectando a MySQL...${NC}"
    echo "Usuario: root"
    echo "Contraseña: root"
    echo ""
    docker exec -it app-ctm-mysql-1 mysql -u root -proot
}

# Verificar Docker
check_docker

# Procesar argumentos
case "${1:-help}" in
    start)
        start_containers
        ;;
    stop)
        stop_containers
        ;;
    restart)
        restart_containers
        ;;
    status)
        show_status
        ;;
    logs)
        show_logs
        ;;
    clean)
        clean_docker
        ;;
    rebuild)
        rebuild_containers
        ;;
    db)
        connect_db
        ;;
    help|*)
        show_help
        ;;
esac
