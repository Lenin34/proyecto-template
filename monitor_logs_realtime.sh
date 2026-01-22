#!/bin/bash

echo "📡 Monitor de Logs en Tiempo Real"
echo "================================="
echo ""
echo "🔍 Monitoreando logs del contenedor app-ctm-asnmx-1"
echo "🎯 Buscando: BENEFICIARY UPDATE, Beneficiary Update, ImageUploadService, handleBeneficiaryImage"
echo ""
echo "📝 Instrucciones:"
echo "1. Deja este script corriendo"
echo "2. En otra terminal/app, actualiza el beneficiario desde React Native"
echo "3. Los logs aparecerán aquí en tiempo real"
echo "4. Presiona Ctrl+C para detener"
echo ""
echo "🚀 Iniciando monitoreo..."
echo "========================="

# Seguir los logs en tiempo real y filtrar por nuestras palabras clave
docker logs app-ctm-asnmx-1 -f 2>&1 | grep --line-buffered -E "(BENEFICIARY UPDATE|Beneficiary Update|ImageUploadService|handleBeneficiaryImage|📸|🚀|❌|✅)" | while read line; do
    echo "$(date '+%H:%M:%S') | $line"
done
