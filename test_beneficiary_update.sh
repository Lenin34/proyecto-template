#!/bin/bash

echo "🧪 Test de Actualización de Beneficiario"
echo "========================================"
echo ""

# Mostrar estado antes
echo "📊 Estado ANTES de la actualización:"
docker exec -it app-ctm-mysql-1 mysql -u root -proot -e "USE \`msc-app-ts\`; SELECT id, name, last_name, photo, updated_at FROM Beneficiary WHERE id = 4;" 2>/dev/null | grep -v "Warning"

echo ""
echo "🔍 Archivos físicos existentes para beneficiario 4:"
docker exec -it app-ctm-asnmx-1 find /var/www/html/public/uploads -name "*" -path "*/1/beneficiaries/4/*" -type f 2>/dev/null || echo "No se encontraron archivos"

echo ""
echo "📝 Instrucciones:"
echo "1. Actualiza el beneficiario ID 4 desde React Native"
echo "2. Presiona ENTER cuando hayas terminado la actualización"
echo "3. Veremos los logs y el estado después"

read -p "Presiona ENTER después de actualizar el beneficiario..."

echo ""
echo "📊 Estado DESPUÉS de la actualización:"
docker exec -it app-ctm-mysql-1 mysql -u root -proot -e "USE \`msc-app-ts\`; SELECT id, name, last_name, photo, updated_at FROM Beneficiary WHERE id = 4;" 2>/dev/null | grep -v "Warning"

echo ""
echo "🔍 Archivos físicos después de la actualización:"
docker exec -it app-ctm-asnmx-1 find /var/www/html/public/uploads -name "*" -path "*/1/beneficiaries/4/*" -type f 2>/dev/null || echo "No se encontraron archivos"

echo ""
echo "📋 Logs recientes (últimos 20 logs de Beneficiary Update):"
docker logs app-ctm-asnmx-1 --tail=100 2>/dev/null | grep "Beneficiary Update" | tail -20

echo ""
echo "📋 Logs de ImageUploadService:"
docker logs app-ctm-asnmx-1 --tail=100 2>/dev/null | grep "ImageUploadService" | tail -10

echo ""
echo "📋 Logs de handleBeneficiaryImage:"
docker logs app-ctm-asnmx-1 --tail=100 2>/dev/null | grep "handleBeneficiaryImage" | tail -10
