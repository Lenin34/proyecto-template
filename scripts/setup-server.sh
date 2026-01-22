#!/bin/bash

# Script de configuración inicial del servidor
# Ejecutar UNA SOLA VEZ en el servidor de producción

set -euo pipefail

echo "🚀 Configurando servidor para app-ctm..."

# Variables
WORK_DIR="/home/github/wrkdirs/asnmx"
MYSQL_ROOT_PASSWORD="root"

# Crear directorios necesarios
echo "📁 Creando directorios..."
mkdir -p $WORK_DIR/.docker/{jwt,env}
mkdir -p $WORK_DIR/public/uploads
chmod 750 $WORK_DIR/.docker
chmod 755 $WORK_DIR/public/uploads

# Crear red de Docker
echo "🌐 Creando red de Docker..."
docker network inspect database >/dev/null 2>&1 || docker network create database

# Configurar MySQL persistente
echo "🐬 Configurando MySQL..."
if ! docker ps -q -f name=mysql | grep -q .; then
    docker run -d \
        --name mysql \
        --network database \
        --restart unless-stopped \
        --label persistent=true \
        -e MYSQL_DATABASE=msc-app-ts \
        -e MYSQL_ROOT_PASSWORD=$MYSQL_ROOT_PASSWORD \
        -e MYSQL_ROOT_HOST=% \
        -v mysql_data:/var/lib/mysql \
        -p 33306:3306 \
        mysql:8.0.35 \
        --default-authentication-plugin=mysql_native_password \
        --bind-address=0.0.0.0
    
    echo "⏳ Esperando a que MySQL esté listo..."
    sleep 10  # Dar tiempo inicial para que MySQL se inicie
    for i in {1..60}; do
        # Probar conexión TCP en lugar de socket
        if docker exec mysql mysqladmin ping -h"127.0.0.1" -P3306 -uroot -p$MYSQL_ROOT_PASSWORD --silent 2>/dev/null; then
            echo "✅ MySQL está listo!"
            break
        fi
        echo "Esperando a MySQL... (intento $i/60)"
        sleep 5
    done

    # Crear base de datos adicional
    echo "🗄️ Creando bases de datos..."
    # Usar variable de entorno para evitar warning de password en línea de comandos
    docker exec -e MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql mysql -h127.0.0.1 -P3306 -uroot -e "CREATE DATABASE IF NOT EXISTS \`msc-app-ctm\`;"
    docker exec -e MYSQL_PWD=$MYSQL_ROOT_PASSWORD mysql mysql -h127.0.0.1 -P3306 -uroot -e "SHOW DATABASES;"
else
    echo "✅ MySQL ya está ejecutándose"
fi

# Configurar phpMyAdmin
echo "🔧 Configurando phpMyAdmin..."
if ! docker ps -q -f name=phpmyadmin | grep -q .; then
    docker run -d \
        --name phpmyadmin \
        --network database \
        --restart unless-stopped \
        --label persistent=true \
        -e PMA_HOST=mysql \
        -e UPLOAD_LIMIT=100M \
        -p 8080:80 \
        phpmyadmin/phpmyadmin
    echo "✅ phpMyAdmin configurado"
else
    echo "✅ phpMyAdmin ya está ejecutándose"
fi

# Configurar Mailpit (opcional)
echo "📧 Configurando Mailpit..."
if ! docker ps -q -f name=mailpit | grep -q .; then
    docker run -d \
        --name mailpit \
        --network database \
        --restart unless-stopped \
        --label persistent=true \
        -p 1025:1025 \
        -p 8025:8025 \
        -e MP_SMTP_AUTH_ACCEPT_ANY=1 \
        -e MP_SMTP_AUTH_ALLOW_INSECURE=1 \
        axllent/mailpit
    echo "✅ Mailpit configurado"
else
    echo "✅ Mailpit ya está ejecutándose"
fi

# Configurar Mercure (opcional)
echo "⚡ Configurando Mercure..."
if ! docker ps -q -f name=mercure | grep -q .; then
    docker run -d \
        --name mercure \
        --network database \
        --restart unless-stopped \
        --label persistent=true \
        -p 1337:80 \
        -e SERVER_NAME=':80' \
        -e MERCURE_PUBLISHER_JWT_KEY='SANFEAFcV9JQTKlutJx3cAtudhAGHHkBCSdt3vDxINM=' \
        -e MERCURE_SUBSCRIBER_JWT_KEY='SANFEAFcV9JQTKlutJx3cAtudhAGHHkBCSdt3vDxINM=' \
        -e MERCURE_CORS_ALLOWED_ORIGINS='http://localhost:8004 http://104.198.241.255:8004' \
        -e MERCURE_ALLOW_ANONYMOUS='1' \
        -v mercure_data:/data \
        -v mercure_config:/config \
        dunglas/mercure
    echo "✅ Mercure configurado"
else
    echo "✅ Mercure ya está ejecutándose"
fi

echo ""
echo "🎉 ¡Configuración del servidor completada!"
echo ""
echo "📋 Servicios disponibles:"
echo "   - MySQL: puerto 33306"
echo "   - phpMyAdmin: http://104.198.241.255:8080"
echo "   - Mailpit: http://104.198.241.255:8025"
echo "   - Mercure: http://104.198.241.255:1337"
echo "   - Aplicación: https://sindicato.grupooptimo.mx"
echo ""
echo "🔄 Los deployments futuros solo actualizarán la aplicación, no los servicios."
