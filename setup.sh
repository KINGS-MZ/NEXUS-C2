#!/bin/bash

# NEXUS C2 Auto-Installer & Runner
# Created by @Imad

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
WHITE='\033[1;37m'
NC='\033[0m' # No Color

clear

echo -e "${PURPLE}"
echo "                                                                          "
echo "    ███╗   ██╗███████╗██╗  ██╗██╗   ██╗███████╗     ██████╗██████╗        "
echo "    ████╗  ██║██╔════╝╚██╗██╔╝██║   ██║██╔════╝    ██╔════╝╚════██╗       "
echo "    ██╔██╗ ██║█████╗   ╚███╔╝ ██║   ██║███████╗    ██║      █████╔╝       "
echo "    ██║╚██╗██║██╔══╝   ██╔██╗ ██║   ██║╚════██║    ██║     ██╔═══╝        "
echo "    ██║ ╚████║███████╗██╔╝ ██╗╚██████╔╝███████║    ╚██████╗███████╗       "
echo "    ╚═╝  ╚═══╝╚══════╝╚═╝  ╚═╝ ╚═════╝ ╚══════╝     ╚═════╝╚══════╝       "
echo -e "${NC}"
echo -e "${CYAN}════════════════════════════════════════════════════════════════════${NC}"
echo -e "${WHITE}                    Command & Control Framework${NC}"
echo -e "${YELLOW}                        Created by @Imad${NC}"
echo -e "${CYAN}════════════════════════════════════════════════════════════════════${NC}"
echo ""

# Configuration
INSTALL_DIR="/var/www/nexus-c2"
WEB_USER="www-data"
export COMPOSER_ALLOW_SUPERUSER=1

echo -e "${GREEN}[1/7]${NC} ${WHITE}Installing System Dependencies...${NC}"
sudo apt-get update -q
sudo apt-get install -y -q software-properties-common
# Try installing PHP 8.2, fallback to default if PPA fails
if ! sudo add-apt-repository -y ppa:ondrej/php 2>/dev/null; then
    echo -e "${YELLOW}[!] PPA install failed, continuing with default repositories${NC}"
fi
sudo apt-get update -q --fix-missing
if ! sudo apt-get install -y -q php8.2 php8.2-sqlite3 php8.2-curl php8.2-xml php8.2-mbstring libapache2-mod-php8.2; then
    echo -e "${YELLOW}[!] PHP 8.2 not found, using default PHP version${NC}"
    sudo apt-get install -y -q php php-sqlite3 php-curl php-xml php-mbstring libapache2-mod-php
fi
sudo apt-get install -y -q python3 python3-pip unzip curl apache2

echo -e "${CYAN}[*]${NC} ${WHITE}Installing Python Dependencies...${NC}"

# 1. Clean up previous broken installs
sudo rm -f /usr/local/bin/pyinstaller 2>/dev/null
sudo rm -f /usr/bin/pyinstaller 2>/dev/null
# Clean both system and user installs to start fresh
sudo apt-get remove -y pyinstaller 2>/dev/null
sudo -H pip3 uninstall -y pyinstaller websocket-client 2>/dev/null
sudo pip3 uninstall -y pyinstaller websocket-client 2>/dev/null

# 2. Install GLOBALLY using sudo -H to target /usr/local/bin
echo -e "${YELLOW}[*]${NC} Installing PyInstaller globally..."
sudo -H pip3 install pyinstaller websocket-client --break-system-packages 2>/dev/null || sudo -H pip3 install pyinstaller websocket-client

# 3. Find the REAL binary (not a symlink)
PYINSTALLER_PATH=""
# Check common global locations first
if [ -f "/usr/local/bin/pyinstaller" ]; then
    PYINSTALLER_PATH="/usr/local/bin/pyinstaller"
elif [ -f "/usr/bin/pyinstaller" ]; then
    PYINSTALLER_PATH="/usr/bin/pyinstaller"
else
    # Search in other locations
    PYINSTALLER_PATH=$(find /usr -name pyinstaller -type f 2>/dev/null | grep -v "site-packages" | head -n 1)
fi

# 4. Create proper symlinks (if needed) and checking permissions
if [ -n "$PYINSTALLER_PATH" ] && [ -f "$PYINSTALLER_PATH" ]; then
    echo -e "${GREEN}[✓]${NC} Found PyInstaller at $PYINSTALLER_PATH"
    
    # Ensure it's executable
    sudo chmod 755 "$PYINSTALLER_PATH"
    
    # Check if we need to link to /usr/bin (for www-data)
    if [ "$PYINSTALLER_PATH" != "/usr/bin/pyinstaller" ]; then
        sudo ln -sf "$PYINSTALLER_PATH" /usr/bin/pyinstaller
        echo -e "${GREEN}[+]${NC} Linked to /usr/bin/pyinstaller"
    fi
    
    # Verify execution
    if /usr/bin/pyinstaller --version >/dev/null 2>&1; then
        echo -e "${GREEN}[✓]${NC} PyInstaller verification passed"
    else
        echo -e "${RED}[!]${NC} PyInstaller found but execution failed"
    fi
else
    echo -e "${RED}[!]${NC} PyInstaller binary NOT found. Trying fallback install..."
    # Fallback: install to user and move
    pip3 install pyinstaller websocket-client --user
    USER_PATH=$(python3 -m site --user-base)/bin/pyinstaller
    if [ -f "$USER_PATH" ]; then
        sudo mv "$USER_PATH" /usr/local/bin/pyinstaller
        sudo chmod 755 /usr/local/bin/pyinstaller
        sudo ln -sf /usr/local/bin/pyinstaller /usr/bin/pyinstaller
        echo -e "${GREEN}[✓]${NC} PyInstaller installed via fallback and moved to global"
    fi
fi

echo -e "${GREEN}[2/7]${NC} ${WHITE}Installing Composer...${NC}"
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

echo -e "${GREEN}[3/7]${NC} ${WHITE}Deploying Files to $INSTALL_DIR...${NC}"
sudo mkdir -p $INSTALL_DIR

# Backup existing database if it exists
if [ -f "$INSTALL_DIR/data/c2.db" ]; then
    echo -e "${YELLOW}[*]${NC} Backing up existing database..."
    sudo cp "$INSTALL_DIR/data/c2.db" "$INSTALL_DIR/data/c2.db.bak_$(date +%s)"
fi

# Copy files from current directory to install dir
sudo cp -r ./* $INSTALL_DIR/
# Fix permissions
sudo chown -R $WEB_USER:$WEB_USER $INSTALL_DIR
sudo chmod -R 755 $INSTALL_DIR
# Ensure data directory is writable
sudo mkdir -p $INSTALL_DIR/data
sudo chmod -R 777 $INSTALL_DIR/data

echo -e "${GREEN}[4/7]${NC} ${WHITE}Installing PHP Dependencies...${NC}"
if [ -d "$INSTALL_DIR/websocket" ]; then
    cd $INSTALL_DIR/websocket
    sudo rm -f composer.lock
    sudo composer update --no-interaction
    cd ..
fi

echo -e "${GREEN}[5/7]${NC} ${WHITE}Configuring Apache...${NC}"
cat <<EOL | sudo tee /etc/apache2/sites-available/nexus-c2.conf
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot $INSTALL_DIR
    <Directory $INSTALL_DIR>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/error.log
    CustomLog \${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
EOL

sudo a2dissite 000-default.conf 2>/dev/null
sudo a2ensite nexus-c2.conf
sudo a2enmod rewrite
sudo systemctl restart apache2

echo -e "${GREEN}[6/7]${NC} ${WHITE}Configuring WebSocket Service...${NC}"
cat <<EOL | sudo tee /etc/systemd/system/nexus-c2-socket.service
[Unit]
Description=NEXUS C2 WebSocket Server
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$INSTALL_DIR
ExecStart=/usr/bin/php $INSTALL_DIR/websocket/server.php
Restart=on-failure
RestartSec=5s

[Install]
WantedBy=multi-user.target
EOL

sudo systemctl daemon-reload
sudo systemctl enable nexus-c2-socket
sudo systemctl restart nexus-c2-socket

echo -e "${GREEN}[7/7]${NC} ${WHITE}Configuring Permissions & Firewall...${NC}"
# Allow www-data to manage the service without password
echo "$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start nexus-c2-socket, /bin/systemctl stop nexus-c2-socket, /bin/systemctl restart nexus-c2-socket, /bin/systemctl status nexus-c2-socket, /bin/systemctl is-active nexus-c2-socket" | sudo tee /etc/sudoers.d/nexus-c2 > /dev/null
sudo chmod 0440 /etc/sudoers.d/nexus-c2

# Open firewall ports
if command -v ufw &> /dev/null; then
    sudo ufw allow 80/tcp > /dev/null 2>&1
    sudo ufw allow 8080/tcp > /dev/null 2>&1
fi

echo ""
echo -e "${CYAN}═══════════════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}                    ✓ DEPLOYMENT COMPLETE ✓${NC}"
echo -e "${CYAN}═══════════════════════════════════════════════════════════════════${NC}"
echo ""

# Verify services
sleep 2
if sudo systemctl is-active --quiet nexus-c2-socket; then
    echo -e "  ${GREEN}●${NC} WebSocket Server    ${GREEN}RUNNING${NC}"
else
    echo -e "  ${RED}●${NC} WebSocket Server    ${RED}FAILED${NC}"
fi

if sudo systemctl is-active --quiet apache2; then
    echo -e "  ${GREEN}●${NC} Apache Web Server   ${GREEN}RUNNING${NC}"
else
    echo -e "  ${RED}●${NC} Apache Web Server   ${RED}FAILED${NC}"
fi

SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo -e "${CYAN}───────────────────────────────────────────────────────────────────${NC}"
echo -e "  ${WHITE}Web Panel:${NC}      ${YELLOW}http://$SERVER_IP/${NC}"
echo -e "  ${WHITE}Credentials:${NC}    ${YELLOW}admin / admin${NC}"
echo -e "${CYAN}───────────────────────────────────────────────────────────────────${NC}"
echo -e "  ${WHITE}WebSocket:${NC}      ${YELLOW}ws://$SERVER_IP:8080${NC}"
echo -e "  ${WHITE}Service:${NC}        ${YELLOW}nexus-c2-socket${NC}"
echo -e "${CYAN}───────────────────────────────────────────────────────────────────${NC}"
echo -e "  ${WHITE}Beacon Config:${NC}  IP: ${GREEN}$SERVER_IP${NC}  Port: ${GREEN}8080${NC}"
echo -e "${CYAN}───────────────────────────────────────────────────────────────────${NC}"
echo ""
echo -e "  ${PURPLE}Commands:${NC}"
echo -e "    Start:  ${CYAN}sudo systemctl start nexus-c2-socket${NC}"
echo -e "    Stop:   ${CYAN}sudo systemctl stop nexus-c2-socket${NC}"
echo -e "    Logs:   ${CYAN}journalctl -u nexus-c2-socket -f${NC}"
echo ""
echo -e "${CYAN}═══════════════════════════════════════════════════════════════════${NC}"
echo ""
