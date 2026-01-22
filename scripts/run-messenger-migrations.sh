#!/bin/bash

# Script para ejecutar migraciones de messenger_messages en todos los tenants
set -e

echo "=========================================="
echo "🚀 EJECUTANDO MIGRACIONES MESSENGER"
echo "=========================================="
echo ""

CONTAINER_NAME="asnmx"

echo "📦 Ejecutando migraciones en tenant: ts"
docker exec $CONTAINER_NAME php bin/console doctrine:migrations:migrate --em=ts --no-interaction

echo ""
echo "📦 Ejecutando migraciones en tenant: rs"
docker exec $CONTAINER_NAME php bin/console doctrine:migrations:migrate --em=rs --no-interaction

echo ""
echo "📦 Ejecutando migraciones en tenant: SNT"
docker exec $CONTAINER_NAME php bin/console doctrine:migrations:migrate --em=SNT --no-interaction

echo ""
echo "📦 Ejecutando migraciones en tenant: issemym"
docker exec $CONTAINER_NAME php bin/console doctrine:migrations:migrate --em=issemym --no-interaction

echo ""
echo "=========================================="
echo "✅ MIGRACIONES COMPLETADAS"
echo "=========================================="
echo ""
echo "Verificando tablas creadas..."
echo ""

docker exec $CONTAINER_NAME mysql -uroot -pMasoftCode2025Secure -e "
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
echo "Reiniciando workers..."
docker exec $CONTAINER_NAME supervisorctl restart messenger-consume:*

echo ""
echo "✅ ¡TODO LISTO! Sistema Messenger configurado correctamente."

