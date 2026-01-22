#!/bin/bash

echo "🚀 Iniciando entrypoint de la plantilla..."
set -e

cd /var/www/html

# Composer install solo si falta vendor/autoload.php
if [ ! -f vendor/autoload.php ]; then
  echo "Ejecutando composer install..."
  composer install --no-interaction --optimize-autoloader || { echo "composer install falló"; exit 1; }
fi

if [ "$APP_ENV" = "prod" ]; then
    echo "🔒 MODO PRODUCCIÓN DETECTADO - Protecciones de datos activadas"
    export DOCTRINE_DISABLE_SCHEMA_DROP=1
fi

echo "Esperando a que MySQL (mysql:3306) esté disponible..."
until mysqladmin ping -h"mysql" -P3306 --silent; do
    sleep 2
    echo "Esperando a MySQL (mysql:3306)..."
done

echo "Verificación de conexión MySQL completada."
echo "=== CONFIGURANDO BASE DE DATOS ==="

process_tenant_migrations() {
    local TENANT_NAME="$1"
    local DATABASE_URL_VAR="$2"
    local CONNECTION_NAME="$3"
    local EM_NAME="$4"

    local DB_URL="${!DATABASE_URL_VAR}"

    if [ -z "$DB_URL" ]; then
        echo "⏭️  Saltando tenant $TENANT_NAME: Variable $DATABASE_URL_VAR no definida"
        return 0
    fi

    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🗄️  Procesando tenant: $TENANT_NAME"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

    echo "📦 Creando base de datos $TENANT_NAME si no existe..."
    DATABASE_URL="$DB_URL" php bin/console doctrine:database:create --connection=$CONNECTION_NAME --if-not-exists --env=prod || true

    local TABLES_COUNT=$(DATABASE_URL="$DB_URL" php bin/console dbal:run-sql "SELECT COUNT(*) as count FROM information_schema.tables WHERE table_schema = DATABASE()" --env=prod 2>/dev/null | grep -o '[0-9]\+' | tail -1 || echo "0")
    TABLES_COUNT=${TABLES_COUNT:-0}

    if [ "$TABLES_COUNT" -gt 0 ]; then
        echo "✅ Base de datos $TENANT_NAME existente con $TABLES_COUNT tablas"
        echo "🔄 Ejecutando migraciones para $TENANT_NAME..."
        DATABASE_URL="$DB_URL" php bin/console doctrine:migrations:migrate --em=$EM_NAME --no-interaction --env=prod || {
             DATABASE_URL="$DB_URL" php bin/console doctrine:migrations:version --add --all --em=$EM_NAME --no-interaction --env=prod || true
        }
    else
        echo "🆕 Base de datos $TENANT_NAME vacía. Creando schema inicial..."
        DATABASE_URL="$DB_URL" php bin/console doctrine:schema:create --em=$EM_NAME --env=prod || {
             DATABASE_URL="$DB_URL" php bin/console doctrine:migrations:migrate --em=$EM_NAME --no-interaction --env=prod || true
        }
        DATABASE_URL="$DB_URL" php bin/console doctrine:migrations:version --add --all --em=$EM_NAME --no-interaction --env=prod || true
    fi

    if [ "$EM_NAME" != "master" ]; then
         DATABASE_URL="$DB_URL" php bin/console doctrine:schema:update --force --em=$EM_NAME --env=prod || true
    fi
}

if [ -z "$DATABASE_URL_TENANT_A" ]; then
    echo "⚠️  ADVERTENCIA: La variable DATABASE_URL_TENANT_A no está definida."
fi

echo ""
echo "╔════════════════════════════════════════════════════╗"
echo "║     PROCESANDO MIGRACIONES DE TODOS LOS TENANTS    ║"
echo "╚════════════════════════════════════════════════════╝"

# Tenants Ejemplo
process_tenant_migrations "TENANT_A" "DATABASE_URL_TENANT_A" "tenant_a" "tenant_a"
process_tenant_migrations "TENANT_B" "DATABASE_URL_TENANT_B" "tenant_b" "tenant_b"
process_tenant_migrations "TENANT_C" "DATABASE_URL_TENANT_C" "tenant_c" "tenant_c"

# Tenant Master
process_tenant_migrations "MASTER" "DATABASE_URL_MASTER" "master" "master"

echo "🧹 Limpiando caché Symfony..."
php bin/console cache:clear || { echo "cache:clear falló"; exit 1; }

mkdir -p config/jwt
if [ ! -f config/jwt/private.pem ]; then
    php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction
fi

mkdir -p public/uploads/{event,benefit,notification,social_media,users,forms}
chown -R www-data:www-data var/ config/jwt/ public/uploads/
chmod -R 777 var/
chmod -R 755 public/uploads/
chmod 644 config/jwt/private.pem config/jwt/public.pem

echo "👷 Iniciando Supervisor..."
service supervisor start || echo "⚠️ Supervisor failed to start"

exec apache2-foreground
