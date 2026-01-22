#!/bin/bash

echo "🧪 Test del Nuevo Endpoint de Foto"
echo "=================================="
echo ""

# Mostrar estado antes
echo "📊 Estado ANTES de la actualización:"
docker exec -it app-ctm-mysql-1 mysql -u root -proot -e "USE \`msc-app-ts\`; SELECT id, name, last_name, photo, updated_at FROM Beneficiary WHERE id = 4;" 2>/dev/null | grep -v "Warning"

echo ""
echo "🔍 Archivos físicos existentes para beneficiario 4:"
docker exec -it app-ctm-asnmx-1 find /var/www/html/public/uploads -name "*" -path "*/1/beneficiaries/4/*" -type f 2>/dev/null || echo "No se encontraron archivos"

echo ""
echo "📝 Instrucciones para React Native:"
echo "1. Cambia el endpoint en React Native a:"
echo "   const endpoint = \`\${API_URL}/users/\${userId}/beneficiary/\${beneficiarioId}/photo\`;"
echo ""
echo "2. O usa la nueva función updateBeneficiarioPhoto():"
echo "   const result = await updateBeneficiarioPhoto(1, 4, photoUri);"
echo ""
echo "3. El endpoint específico es: /ts/api/users/1/beneficiary/4/photo"
echo ""
echo "4. Presiona ENTER cuando hayas actualizado la foto usando el NUEVO endpoint"

read -p "Presiona ENTER después de usar el NUEVO endpoint..."

echo ""
echo "📊 Estado DESPUÉS de usar el nuevo endpoint:"
docker exec -it app-ctm-mysql-1 mysql -u root -proot -e "USE \`msc-app-ts\`; SELECT id, name, last_name, photo, updated_at FROM Beneficiary WHERE id = 4;" 2>/dev/null | grep -v "Warning"

echo ""
echo "🔍 Archivos físicos después de la actualización:"
docker exec -it app-ctm-asnmx-1 find /var/www/html/public/uploads -name "*" -path "*/1/beneficiaries/4/*" -type f 2>/dev/null || echo "No se encontraron archivos"

echo ""
echo "📋 Logs del NUEVO endpoint (últimos 20):"
docker logs app-ctm-asnmx-1 --tail=50 2>/dev/null | grep -E "(BENEFICIARY UPDATE PHOTO|updateBeneficiaryPhoto)" | tail -20

echo ""
echo "📋 Logs de procesamiento de foto:"
docker logs app-ctm-asnmx-1 --tail=50 2>/dev/null | grep -E "(📸|✅|❌)" | tail -15
