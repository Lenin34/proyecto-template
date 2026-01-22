#!/bin/bash
set -e

echo "🚀 Iniciando configuración del proyecto..."

# Verificar si ya existe .env
if [ -f .env ]; then
    echo "⚠️  El archivo .env ya existe."
else
    echo "📝 Creando .env desde ejemplo..."
    cp .env.example .env
fi

# Verificar directorio .docker/env
if [ ! -d .docker/env ]; then
    mkdir -p .docker/env
fi

# Verificar si ya existe .docker/env/docker.env
if [ -f .docker/env/docker.env ]; then
    echo "⚠️  El archivo .docker/env/docker.env ya existe."
else
    echo "📝 Creando .docker/env/docker.env desde ejemplo..."
    cp .docker/env/docker.env.example .docker/env/docker.env
fi

echo ""
echo "✅ Configuración inicial de archivos completada."
echo "👉 Siguientes pasos:"
echo "1. Edita el archivo .env y .docker/env/docker.env con tus credenciales y configuración."
echo "2. Levanta los contenedores: docker compose up -d"
echo "3. Revisa los logs: docker compose logs -f webapp"
