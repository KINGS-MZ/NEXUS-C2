#!/bin/bash

cd "$(dirname "$0")"

if [ ! -d "vendor" ]; then
    echo "[*] Installing dependencies..."
    composer install --no-dev
fi

echo "================================"
echo "   NEXUS C2 - WebSocket Server"
echo "================================"
echo "[*] Starting on port 8080..."
echo ""

php server.php
