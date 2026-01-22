#!/bin/bash

# Script para diagnosticar el estado del worker de Symfony Messenger
# Uso: ./scripts/check-messenger-worker.sh

echo "=========================================="
echo "🔍 DIAGNÓSTICO DEL WORKER DE NOTIFICACIONES"
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

# 1. Verificar si Supervisor está ejecutándose
echo "1️⃣ Verificando estado de Supervisor..."
docker exec $CONTAINER_NAME service supervisor status || echo "⚠️ Supervisor no está ejecutándose"
echo ""

# 2. Verificar procesos del worker
echo "2️⃣ Verificando procesos del worker de Messenger..."
docker exec $CONTAINER_NAME supervisorctl status messenger-consume:* || echo "⚠️ No se encontraron workers activos"
echo ""

# 3. Verificar logs del worker
echo "3️⃣ Últimas 20 líneas del log del worker:"
docker exec $CONTAINER_NAME tail -n 20 /var/www/html/var/log/messenger_consume.log 2>/dev/null || echo "⚠️ No se encontró el archivo de log del worker"
echo ""

# 4. Verificar mensajes pendientes en la cola
echo "4️⃣ Verificando mensajes pendientes en la cola..."
docker exec $CONTAINER_NAME php bin/console messenger:stats || echo "⚠️ No se pudo obtener estadísticas de la cola"
echo ""

# 5. Verificar tabla de mensajes en la base de datos
echo "5️⃣ Verificando tabla messenger_messages en la base de datos..."
docker exec $CONTAINER_NAME mysql -uroot -pMasoftCode2025Secure -e "SELECT COUNT(*) as pending_messages FROM messenger_messages WHERE delivered_at IS NULL;" msc-app-issemym 2>/dev/null || echo "⚠️ No se pudo consultar la base de datos"
echo ""

# 6. Verificar logs de notificaciones
echo "6️⃣ Últimas 30 líneas del log de notificaciones de beneficios:"
docker exec $CONTAINER_NAME tail -n 30 /var/www/html/var/log/benefit_notification.log 2>/dev/null || echo "⚠️ No se encontró el archivo de log de notificaciones"
echo ""

echo "=========================================="
echo "✅ DIAGNÓSTICO COMPLETADO"
echo "=========================================="
echo ""
echo "📋 ACCIONES RECOMENDADAS:"
echo ""
echo "Si Supervisor no está ejecutándose:"
echo "  docker exec $CONTAINER_NAME service supervisor start"
echo ""
echo "Si los workers no están activos:"
echo "  docker exec $CONTAINER_NAME supervisorctl reread"
echo "  docker exec $CONTAINER_NAME supervisorctl update"
echo "  docker exec $CONTAINER_NAME supervisorctl start messenger-consume:*"
echo ""
echo "Para reiniciar los workers:"
echo "  docker exec $CONTAINER_NAME supervisorctl restart messenger-consume:*"
echo ""
echo "Para ver logs en tiempo real:"
echo "  docker exec $CONTAINER_NAME tail -f /var/www/html/var/log/messenger_consume.log"
echo ""

