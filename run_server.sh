#!/bin/bash

# NEXUS C2 - Server Helper Script
# Created by @Imad

INSTALL_DIR="/var/www/nexus-c2"

echo "[*] Check Environment..."

# 1. Check if running in installed directory with vendor
if [ -f "websocket/vendor/autoload.php" ]; then
    echo "[*] Dependencies found. Starting local server..."
    php websocket/server.php
    exit 0
fi

# 2. Check if installed globally
if [ -f "$INSTALL_DIR/websocket/vendor/autoload.php" ]; then
    echo "[!] Dependencies missing in current folder."
    echo "[*] Found installed version at $INSTALL_DIR"
    echo "[*] Switching execution to installed server..."
    
    # Check if service is already running
    if systemctl is-active --quiet nexus-c2-socket; then
        echo "[!] Server is ALREADY RUNNING explicitly as a system service."
        echo "    To view status: sudo systemctl status nexus-c2-socket"
        echo "    To stop: sudo systemctl stop nexus-c2-socket"
        echo "    To restart: sudo systemctl restart nexus-c2-socket"
        echo ""
        echo "    You can also control it from the Dashboard sidebar!"
        exit 0
    else
        echo "[*] Starting server via system service..."
        sudo systemctl start nexus-c2-socket
        echo "[*] Server started in background."
        exit 0
    fi
fi

# 3. Fallback: Dependencies missing everywhere
echo "[X] ERROR: Dependencies missing!"
echo "    Please run the installer first:"
echo "    chmod +x setup.sh"
echo "    ./setup.sh"
exit 1
