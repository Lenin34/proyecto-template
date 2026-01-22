#!/bin/bash

# Script de Diagnóstico de Notificaciones
# Uso: ./diagnostico_notificaciones.sh [tenant] [user_id]

TENANT=${1:-issemym}
USER_ID=${2:-8}
BASE_URL="http://192.168.200.151:8004"

echo "=================================="
echo "🔍 DIAGNÓSTICO DE NOTIFICACIONES"
echo "=================================="
echo "Tenant: $TENANT"
echo "User ID: $USER_ID"
echo "Base URL: $BASE_URL"
echo ""

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Función para imprimir con color
print_status() {
    if [ $1 -eq 0 ]; then
        echo -e "${GREEN}✅ $2${NC}"
    else
        echo -e "${RED}❌ $2${NC}"
    fi
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

# 1. Verificar conectividad
echo "1️⃣  Verificando conectividad al servidor..."
curl -s -o /dev/null -w "%{http_code}" "$BASE_URL" > /tmp/status_code.txt
STATUS_CODE=$(cat /tmp/status_code.txt)
if [ "$STATUS_CODE" -eq 200 ] || [ "$STATUS_CODE" -eq 302 ]; then
    print_status 0 "Servidor accesible (HTTP $STATUS_CODE)"
else
    print_status 1 "Servidor no accesible (HTTP $STATUS_CODE)"
fi
echo ""

# 2. Probar endpoint de contador de notificaciones no leídas
echo "2️⃣  Probando endpoint: GET /api/users/$USER_ID/notifications/unread-count"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE_URL/$TENANT/api/users/$USER_ID/notifications/unread-count")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
    print_status 0 "Endpoint funciona correctamente (HTTP $HTTP_CODE)"
    echo "   Respuesta: $BODY"
    
    # Verificar si la respuesta es JSON válido
    if echo "$BODY" | jq . > /dev/null 2>&1; then
        SUCCESS=$(echo "$BODY" | jq -r '.success')
        UNREAD_COUNT=$(echo "$BODY" | jq -r '.unread_count')
        
        if [ "$SUCCESS" = "true" ]; then
            print_status 0 "Respuesta válida - Notificaciones no leídas: $UNREAD_COUNT"
        else
            ERROR_MSG=$(echo "$BODY" | jq -r '.error')
            print_status 1 "Error en respuesta: $ERROR_MSG"
        fi
    else
        print_warning "Respuesta no es JSON válido"
    fi
else
    print_status 1 "Endpoint falló (HTTP $HTTP_CODE)"
    echo "   Respuesta: $BODY"
fi
echo ""

# 3. Probar endpoint de lista de notificaciones
echo "3️⃣  Probando endpoint: GET /api/users/$USER_ID/notifications"
RESPONSE=$(curl -s -w "\n%{http_code}" "$BASE_URL/$TENANT/api/users/$USER_ID/notifications?page=1&pageSize=5")
HTTP_CODE=$(echo "$RESPONSE" | tail -n1)
BODY=$(echo "$RESPONSE" | sed '$d')

if [ "$HTTP_CODE" -eq 200 ]; then
    print_status 0 "Endpoint funciona correctamente (HTTP $HTTP_CODE)"
    
    if echo "$BODY" | jq . > /dev/null 2>&1; then
        SUCCESS=$(echo "$BODY" | jq -r '.success')
        TOTAL=$(echo "$BODY" | jq -r '.total')
        
        if [ "$SUCCESS" = "true" ]; then
            print_status 0 "Respuesta válida - Total de notificaciones: $TOTAL"
            
            # Mostrar primeras 3 notificaciones
            echo "   Primeras notificaciones:"
            echo "$BODY" | jq -r '.data[:3][] | "   - [\(.id)] \(.title) (Leída: \(.is_read))"'
        else
            ERROR_MSG=$(echo "$BODY" | jq -r '.error')
            print_status 1 "Error en respuesta: $ERROR_MSG"
        fi
    else
        print_warning "Respuesta no es JSON válido"
    fi
else
    print_status 1 "Endpoint falló (HTTP $HTTP_CODE)"
    echo "   Respuesta: $BODY"
fi
echo ""

# 4. Verificar tokens de dispositivo en base de datos
echo "4️⃣  Verificando tokens de dispositivo en base de datos..."
echo "   (Requiere acceso a MySQL)"

# Detectar si estamos en Docker
if [ -f "/.dockerenv" ] || grep -q docker /proc/1/cgroup 2>/dev/null; then
    print_warning "Ejecutando en Docker - usando mysql desde contenedor"
    MYSQL_CMD="mysql -h mysql -u root -proot"
else
    print_warning "Ejecutando en host - usando mysql local"
    MYSQL_CMD="mysql -u root -p"
fi

# Intentar consultar la base de datos
if command -v mysql &> /dev/null; then
    echo "   Consultando DeviceToken para user_id=$USER_ID..."
    
    QUERY="USE $TENANT; SELECT dt.id, dt.token, dt.created_at, dt.updated_at, u.email FROM DeviceToken dt JOIN User u ON dt.user_id = u.id WHERE u.id = $USER_ID;"
    
    # Nota: Esto puede fallar si no hay acceso a MySQL o credenciales incorrectas
    echo "$QUERY" | $MYSQL_CMD 2>/dev/null || print_warning "No se pudo conectar a MySQL (esto es normal si no tienes acceso directo)"
else
    print_warning "MySQL client no está instalado - saltando verificación de BD"
fi
echo ""

# 5. Verificar formato de tokens
echo "5️⃣  Verificando formato de tokens Expo..."
echo "   Los tokens válidos deben tener formato: ExponentPushToken[xxx] o ExpoPushToken[xxx]"

# Esto requeriría acceso a la BD, por ahora solo mostramos el patrón esperado
print_warning "Verificación manual requerida - revisar logs del servidor al enviar notificación"
echo ""

# 6. Verificar configuración de Expo
echo "6️⃣  Verificando configuración de Expo..."
if [ -f ".env" ]; then
    EXPO_TOKEN=$(grep "EXPO_ACCESS_TOKEN" .env | cut -d '=' -f2)
    if [ -n "$EXPO_TOKEN" ]; then
        TOKEN_LENGTH=${#EXPO_TOKEN}
        print_status 0 "EXPO_ACCESS_TOKEN configurado (longitud: $TOKEN_LENGTH caracteres)"
        echo "   Token (primeros 10 caracteres): ${EXPO_TOKEN:0:10}..."
    else
        print_status 1 "EXPO_ACCESS_TOKEN no configurado en .env"
    fi
else
    print_warning "Archivo .env no encontrado en directorio actual"
fi
echo ""

# 7. Verificar logs recientes
echo "7️⃣  Verificando logs recientes del servidor..."
print_warning "Revisar manualmente los logs del servidor para más detalles"

# Intentar mostrar últimas líneas de logs si están disponibles
LOG_FILES=(
    "/var/log/apache2/error.log"
    "/var/log/nginx/error.log"
    "/var/www/html/var/log/dev.log"
    "/var/www/html/var/log/prod.log"
)

LOG_FOUND=false
for LOG_FILE in "${LOG_FILES[@]}"; do
    if [ -f "$LOG_FILE" ]; then
        LOG_FOUND=true
        echo "   Últimas 5 líneas de $LOG_FILE:"
        tail -n 5 "$LOG_FILE" 2>/dev/null | sed 's/^/   /'
        break
    fi
done

if [ "$LOG_FOUND" = false ]; then
    print_warning "No se encontraron archivos de log en ubicaciones estándar"
    echo "   Comandos útiles para ver logs:"
    echo "   - tail -f /var/log/apache2/error.log"
    echo "   - tail -f /var/log/nginx/error.log"
    echo "   - docker logs -f <container_name>"
fi
echo ""

# Resumen
echo "=================================="
echo "📊 RESUMEN"
echo "=================================="
echo "Para más información, consultar:"
echo "  - DIAGNOSTICO_NOTIFICACIONES.md"
echo "  - Logs del servidor en tiempo real"
echo ""
echo "Comandos útiles:"
echo "  # Ver logs en tiempo real"
echo "  tail -f /var/log/apache2/error.log"
echo ""
echo "  # Probar envío de notificación manualmente"
echo "  curl -X POST '$BASE_URL/$TENANT/notification/{id}/send' \\"
echo "       -H 'X-Requested-With: XMLHttpRequest' \\"
echo "       -H 'Accept: application/json'"
echo ""
echo "=================================="
