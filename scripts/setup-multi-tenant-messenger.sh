#!/bin/bash

# Script maestro para configurar Symfony Messenger Multi-Tenant
# Ejecuta todos los pasos necesarios para la configuración

set -e  # Detener en caso de error

echo "=========================================="
echo "🚀 CONFIGURACIÓN MULTI-TENANT MESSENGER"
echo "=========================================="
echo ""

# Obtener el nombre del contenedor
CONTAINER_NAME=$(docker ps --format '{{.Names}}' | grep -E 'asnmx|app-ctm' | head -n 1)

if [ -z "$CONTAINER_NAME" ]; then
    echo "❌ ERROR: No se encontró el contenedor de la aplicación"
    echo "Contenedores en ejecución:"
    docker ps --format "table {{.Names}}\t{{.Status}}"
    exit 1
fi

echo "✅ Contenedor encontrado: $CONTAINER_NAME"
echo ""

# ============================================
# PASO 1: Crear tablas messenger_messages
# ============================================

echo "=========================================="
echo "📦 PASO 1: Creando tablas messenger_messages"
echo "=========================================="
echo ""

echo "Creando tabla en tenant: ts"
docker exec $CONTAINER_NAME php bin/console doctrine:schema:update --force --em=ts

echo "Creando tabla en tenant: rs"
docker exec $CONTAINER_NAME php bin/console doctrine:schema:update --force --em=rs

echo "Creando tabla en tenant: SNT"
docker exec $CONTAINER_NAME php bin/console doctrine:schema:update --force --em=SNT

echo "Creando tabla en tenant: issemym"
docker exec $CONTAINER_NAME php bin/console doctrine:schema:update --force --em=issemym

echo ""
echo "✅ Tablas messenger_messages creadas en todos los tenants"
echo ""

# ============================================
# PASO 2: Verificar tablas
# ============================================

echo "=========================================="
echo "🔍 PASO 2: Verificando tablas creadas"
echo "=========================================="
echo ""

docker exec $CONTAINER_NAME mysql -uroot -ppassword -e "
SELECT 
    'ts' as tenant,
    COUNT(*) as table_exists 
FROM information_schema.tables 
WHERE table_schema = 'msc-app-ts' 
AND table_name = 'messenger_messages'
UNION ALL
SELECT 
    'rs' as tenant,
    COUNT(*) as table_exists 
FROM information_schema.tables 
WHERE table_schema = 'msc-app-rs' 
AND table_name = 'messenger_messages'
UNION ALL
SELECT 
    'SNT' as tenant,
    COUNT(*) as table_exists 
FROM information_schema.tables 
WHERE table_schema = 'msc-app-snt' 
AND table_name = 'messenger_messages'
UNION ALL
SELECT 
    'issemym' as tenant,
    COUNT(*) as table_exists 
FROM information_schema.tables 
WHERE table_schema = 'msc-app-issemym' 
AND table_name = 'messenger_messages';
"

echo ""
echo "✅ Verificación completada (1 = existe, 0 = no existe)"
echo ""

# ============================================
# PASO 3: Reiniciar Supervisor
# ============================================

echo "=========================================="
echo "🔄 PASO 3: Reiniciando workers de Supervisor"
echo "=========================================="
echo ""

echo "Recargando configuración de Supervisor..."
docker exec $CONTAINER_NAME supervisorctl reread

echo "Actualizando Supervisor..."
docker exec $CONTAINER_NAME supervisorctl update

echo "Reiniciando workers de messenger..."
docker exec $CONTAINER_NAME supervisorctl restart messenger-consume:*

echo ""
echo "✅ Workers reiniciados"
echo ""

# ============================================
# PASO 4: Verificar estado de workers
# ============================================

echo "=========================================="
echo "📊 PASO 4: Estado de los workers"
echo "=========================================="
echo ""

docker exec $CONTAINER_NAME supervisorctl status messenger-consume:*

echo ""

# ============================================
# RESUMEN FINAL
# ============================================

echo "=========================================="
echo "✅ CONFIGURACIÓN COMPLETADA"
echo "=========================================="
echo ""
echo "📋 Próximos pasos:"
echo ""
echo "1. Crear usuarios de prueba (opcional):"
echo "   ./scripts/generate-password-hash.sh 123456"
echo "   # Copiar el hash y actualizar migrations/create_test_users.sql"
echo "   docker exec -i $CONTAINER_NAME mysql -uroot -ppassword msc-app-ts < migrations/create_test_users.sql"
echo ""
echo "2. Probar el sistema:"
echo "   - Crear un beneficio o evento desde la interfaz web"
echo "   - Verificar logs: docker exec $CONTAINER_NAME tail -f /var/www/html/var/log/messenger_consume.log"
echo ""
echo "3. Monitorear colas:"
echo "   docker exec $CONTAINER_NAME php bin/console messenger:stats"
echo ""

