#!/bin/bash

# Script de backup automático antes del despliegue
# Este script se ejecuta automáticamente antes de cada despliegue para proteger los datos

set -euo pipefail

echo "🛡️ Iniciando backup pre-despliegue..."

# Variables
BACKUP_DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/home/antoonio/backups/pre-deployment"
WORK_DIR="/home/github/wrkdirs/asnmx"

# Crear directorio de backups
mkdir -p "$BACKUP_DIR"

# Función para verificar si MySQL está disponible
check_mysql() {
    if docker exec mysql mysqladmin ping -h"localhost" -uroot -pMasoftCode2025Secure --silent 2>/dev/null; then
        return 0
    else
        return 1
    fi
}

# Verificar que MySQL esté funcionando
if ! check_mysql; then
    echo "⚠️ MySQL no está disponible. Saltando backup..."
    echo "🚨 ADVERTENCIA: Despliegue sin backup de seguridad"
    exit 0  # No fallar el despliegue, pero advertir
fi

echo "✅ MySQL disponible. Procediendo con backup..."

# Array de todas las bases de datos de tenants
databases=("Master" "msc-app-ts" "msc-app-rs" "msc-app-snt" "msc-app-issemym")

# Backup de cada base de datos
for db in "${databases[@]}"; do
    echo "📦 Creando backup de $db..."
    docker exec mysql mysqldump -uroot -pMasoftCode2025Secure \
        --single-transaction \
        --routines \
        --triggers \
        --add-drop-database \
        --databases "$db" > "$BACKUP_DIR/pre-deploy-${db}-$BACKUP_DATE.sql" || {
        echo "⚠️ Error en backup de $db"
    }
done

# Crear backup comprimido
echo "🗜️ Comprimiendo backups..."
cd "$BACKUP_DIR"
tar -czf "pre-deployment-backup-$BACKUP_DATE.tar.gz" pre-deploy-*-$BACKUP_DATE.sql
rm -f pre-deploy-*-$BACKUP_DATE.sql

# Verificar que el backup se creó correctamente
if [ -f "pre-deployment-backup-$BACKUP_DATE.tar.gz" ]; then
    BACKUP_SIZE=$(du -h "pre-deployment-backup-$BACKUP_DATE.tar.gz" | cut -f1)
    echo "✅ Backup pre-despliegue completado: $BACKUP_SIZE"
    echo "📁 Ubicación: $BACKUP_DIR/pre-deployment-backup-$BACKUP_DATE.tar.gz"
else
    echo "❌ Error: No se pudo crear el backup"
    exit 1
fi

# Limpiar backups antiguos (mantener últimos 10)
echo "🧹 Limpiando backups antiguos..."
cd "$BACKUP_DIR"
ls -t pre-deployment-backup-*.tar.gz | tail -n +11 | xargs -r rm -f

# Mostrar estadísticas
echo "📊 Estadísticas de backup:"
echo "   - Fecha: $BACKUP_DATE"
echo "   - Tamaño: $BACKUP_SIZE"
echo "   - Ubicación: $BACKUP_DIR"
echo "   - Backups disponibles: $(ls -1 pre-deployment-backup-*.tar.gz | wc -l)"

echo "🛡️ Backup pre-despliegue completado exitosamente"
