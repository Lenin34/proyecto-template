#!/bin/bash

echo "=========================================="
echo "Watching logs for app-ctm container"
echo "=========================================="
echo ""
echo "Press Ctrl+C to stop"
echo ""

# Seguir los logs del contenedor en tiempo real
docker logs -f cc6877297de7 2>&1 | grep -E "(SecurityController|DefaultController|DashboardController|LOGIN|DASHBOARD|User)"

