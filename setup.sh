#!/bin/bash

# NEXUS C2 Installation Script for Ubuntu/Debian

echo "================================="
echo "     NEXUS C2 - Installer"
echo "       Created by @Imad"
echo "================================="

# Check for root
if [ "$EUID" -ne 0 ]; then 
  echo "Please run as root"
  exit 1
fi

echo "[*] Updating package lists..."
apt-get update
apt-get install -y software-properties-common

echo "[*] Adding PHP repository..."
add-apt-repository -y ppa:ondrej/php
apt-get update

echo "[*] Installing dependencies (PHP 8.2)..."
apt-get install -y php8.2 php8.2-sqlite3 php8.2-curl php8.2-xml php8.2-mbstring python3 python3-pip unzip curl

# Install Composer
if ! command -v composer &> /dev/null; then
    echo "[*] Installing Composer..."
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
else
    echo "[*] Composer is already installed."
fi

# Install Ratchet dependencies
if [ -d "websocket" ]; then
    echo "[*] Installing WebSocket dependencies..."
    cd websocket
    rm -f composer.lock # Remove lock file to allow fresh resolution
    composer update --no-interaction
    cd ..
else
    echo "[Error] websocket directory not found."
    exit 1
fi

# Create data directory
echo "[*] Setting up database directory..."
mkdir -p data
chmod 777 data

# Ask to create a dedicated user
echo ""
read -p "[?] Do you want to create a dedicated user 'nexus' to run the server? (Recommended) [y/N]: " create_user
if [[ "$create_user" =~ ^[Yy]$ ]]; then
    if id "nexus" &>/dev/null; then
        echo "[*] User 'nexus' already exists."
    else
        echo "[*] Creating user 'nexus'..."
        useradd -m -s /bin/bash nexus
    fi
    
    echo "[*] Transferring ownership of files to 'nexus'..."
    chown -R nexus:nexus .
    
    echo "[+] Permissions set. You can now run the server as 'nexus'."
fi

echo "================================="
echo "   Installation Complete!"
echo "================================="
if [[ "$create_user" =~ ^[Yy]$ ]]; then
    echo "1. Switch to the nexus user: su - nexus"
    echo "2. Navigate to folder: cd $(pwd)"
    echo "3. Start server: ./run_server.sh"
else
    echo "1. Configure your web server (Apache/Nginx) to point to this directory."
    echo "2. Start the WebSocket server using: ./run_server.sh"
fi
echo "3. Default credentials: admin / admin"
