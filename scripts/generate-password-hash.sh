#!/bin/bash

# Script para generar el hash bcrypt de una contraseña
# Uso: ./scripts/generate-password-hash.sh [contraseña]

PASSWORD="${1:-123456}"

echo "=========================================="
echo "🔐 GENERADOR DE HASH DE CONTRASEÑA"
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
echo "🔑 Generando hash para contraseña: $PASSWORD"
echo ""

# Generar el hash
HASH=$(docker exec $CONTAINER_NAME php -r "echo password_hash('$PASSWORD', PASSWORD_BCRYPT, ['cost' => 13]);")

echo "=========================================="
echo "✅ HASH GENERADO"
echo "=========================================="
echo ""
echo "$HASH"
echo ""
echo "=========================================="
echo "📋 INSTRUCCIONES"
echo "=========================================="
echo ""
echo "1. Copia el hash generado arriba"
echo "2. Edita el archivo: migrations/create_test_users.sql"
echo "3. Reemplaza la línea:"
echo "   SET @password_hash = '\$2y\$13\$8K1p/H0eJ92uIEgnJZ5Av.xRZpMsZQKV8KJnZ5L5L5L5L5L5L5L5u';"
echo "4. Con:"
echo "   SET @password_hash = '$HASH';"
echo ""
echo "O ejecuta directamente:"
echo "sed -i \"s|SET @password_hash = '.*';|SET @password_hash = '$HASH';|\" migrations/create_test_users.sql"
echo ""

