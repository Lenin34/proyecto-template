#!/bin/bash

echo "🔍 Monitor de Actualizaciones de Beneficiarios"
echo "=============================================="
echo ""
echo "Mostrando estado actual de beneficiarios con fotos:"
echo ""

# Función para mostrar el estado actual
show_current_state() {
    docker exec -it app-ctm-mysql-1 mysql -u root -proot -e "
    USE \`msc-app-ts\`; 
    SELECT 
        id, 
        name, 
        last_name, 
        photo, 
        updated_at 
    FROM Beneficiary 
    WHERE photo IS NOT NULL 
    ORDER BY updated_at DESC 
    LIMIT 5;
    " 2>/dev/null | grep -v "Warning"
}

# Función para mostrar cambios detallados
show_detailed_changes() {
    echo ""
    echo "📊 Historial detallado de cambios:"
    echo "=================================="
    docker exec -it app-ctm-mysql-1 mysql -u root -proot -e "
    USE \`msc-app-ts\`; 
    SELECT 
        id,
        CONCAT(name, ' ', last_name) as full_name,
        photo,
        created_at,
        updated_at,
        CASE 
            WHEN updated_at > created_at THEN 'ACTUALIZADO'
            ELSE 'CREADO'
        END as status
    FROM Beneficiary 
    ORDER BY updated_at DESC 
    LIMIT 10;
    " 2>/dev/null | grep -v "Warning"
}

# Función para monitorear cambios en tiempo real
monitor_changes() {
    echo ""
    echo "🔄 Iniciando monitoreo en tiempo real..."
    echo "Presiona Ctrl+C para detener"
    echo ""
    
    # Guardar estado inicial
    LAST_UPDATE=$(docker exec app-ctm-mysql-1 mysql -u root -proot -e "USE \`msc-app-ts\`; SELECT MAX(updated_at) FROM Beneficiary;" 2>/dev/null | tail -n 1)
    
    while true; do
        sleep 2
        
        # Verificar si hay cambios
        CURRENT_UPDATE=$(docker exec app-ctm-mysql-1 mysql -u root -proot -e "USE \`msc-app-ts\`; SELECT MAX(updated_at) FROM Beneficiary;" 2>/dev/null | tail -n 1)
        
        if [ "$CURRENT_UPDATE" != "$LAST_UPDATE" ]; then
            echo "🚨 ¡CAMBIO DETECTADO! $(date)"
            echo "=================================="
            
            # Mostrar el beneficiario que cambió
            docker exec app-ctm-mysql-1 mysql -u root -proot -e "
            USE \`msc-app-ts\`; 
            SELECT 
                id,
                CONCAT('👤 ', name, ' ', last_name) as beneficiario,
                CONCAT('📸 ', COALESCE(photo, 'SIN FOTO')) as foto,
                CONCAT('⏰ ', updated_at) as actualizado
            FROM Beneficiary 
            WHERE updated_at = '$CURRENT_UPDATE';
            " 2>/dev/null | grep -v "Warning"
            
            echo ""
            LAST_UPDATE="$CURRENT_UPDATE"
        fi
    done
}

# Mostrar estado actual
show_current_state

# Mostrar cambios detallados
show_detailed_changes

# Preguntar si quiere monitorear en tiempo real
echo ""
echo "¿Quieres monitorear cambios en tiempo real? (y/n)"
read -r response

if [[ "$response" =~ ^[Yy]$ ]]; then
    monitor_changes
else
    echo ""
    echo "✅ Para monitorear manualmente, ejecuta:"
    echo "   ./monitor_beneficiary_updates.sh"
    echo ""
    echo "📝 Para ver solo el estado actual:"
    echo "   docker exec -it app-ctm-mysql-1 mysql -u root -proot -e \"USE \\\`msc-app-ts\\\`; SELECT id, name, last_name, photo, updated_at FROM Beneficiary WHERE photo IS NOT NULL ORDER BY updated_at DESC;\""
fi
