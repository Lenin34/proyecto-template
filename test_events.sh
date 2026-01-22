#!/bin/bash
# Script simple para probar el endpoint de eventos

# Configuración
API_URL="http://localhost:8004/issemym/api"

echo "========================================="
echo "Test del Endpoint de Eventos"
echo "========================================="
echo ""
echo "Este script te ayudará a probar el endpoint de eventos"
echo ""

# Paso 1: Obtener credenciales
echo "Paso 1: Necesitamos credenciales de un usuario válido"
echo "Por favor ingresa:"
read -p "Email: " EMAIL
read -sp "Contraseña: " PASSWORD
echo ""
echo ""

# Paso 2: Login
echo "Paso 2: Autenticando..."
LOGIN_DATA="{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}"

RESPONSE=$(curl -s -X POST "${API_URL}/login" \
  -H "Content-Type: application/json" \
  -d "$LOGIN_DATA")

# Verificar si el login fue exitoso
TOKEN=$(echo "$RESPONSE" | jq -r '.token // empty')
COMPANY_ID=$(echo "$RESPONSE" | jq -r '.company_id // empty')

if [ -z "$TOKEN" ] || [ "$TOKEN" == "null" ]; then
  echo "❌ Error en el login"
  echo "$RESPONSE" | jq '.'
  exit 1
fi

echo "✅ Login exitoso"
echo "Token: ${TOKEN:0:30}..."
echo "Company ID: $COMPANY_ID"
echo ""

# Paso 3: Consultar eventos
echo "Paso 3: Consultando eventos..."
echo "URL: ${API_URL}/events?company_id=${COMPANY_ID}&start_date=2024-01-01&end_date=2026-12-31&amount=20"
echo ""

EVENTS=$(curl -s -X GET "${API_URL}/events?company_id=${COMPANY_ID}&start_date=2024-01-01&end_date=2026-12-31&amount=20" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $TOKEN")

# Mostrar resultado
CODE=$(echo "$EVENTS" | jq -r '.code // .error_code // "ERROR"')

if [ "$CODE" == "200" ]; then
  NUM=$(echo "$EVENTS" | jq '.events | length')
  echo "✅ Éxito! Se encontraron $NUM eventos"
  echo ""
  echo "Eventos:"
  echo "$EVENTS" | jq '.events[] | {id, title, start_date, end_date}'
elif [ "$CODE" == "EC-003" ]; then
  echo "⚠️  No se encontraron eventos para esta empresa"
  echo "Esto puede ser normal si no hay eventos registrados"
else
  echo "❌ Error al consultar eventos"
  echo "Código: $CODE"
  echo "$EVENTS" | jq '.'
fi
