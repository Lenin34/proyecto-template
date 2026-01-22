#!/bin/bash

echo "=========================================="
echo "Watching Symfony logs for tenant: rs"
echo "=========================================="
echo ""
echo "Press Ctrl+C to stop"
echo ""

# Seguir los logs de Symfony en tiempo real
docker exec cc6877297de7 tail -f /var/www/html/var/log/rs-2025-09-29.log

