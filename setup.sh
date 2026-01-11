#!/bin/bash

# NEXUS C2 Auto-Installer & Runner
# Created by @Imad

echo "================================="
echo "   NEXUS C2 - Installer"
echo "   Created by @Imad"
echo "================================="

echo "
  _   _  ________   __  _    _  _____
 | \ | ||  ____\ \ / / | |  | |/ ____|
 |  \| || |__   \ V /  | |  | | (___
 | . \ ||  __|   > <   | |  | |\___ \\
 | |\  || |____ / . \  | |__| |____) |
 |_| \_||______/_/ \_\  \____/|_____/
"

# Configuration
INSTALL_DIR="/var/www/nexus-c2"
WEB_USER="www-data"
export COMPOSER_ALLOW_SUPERUSER=1

echo "[1/6] Installing System Dependencies (PHP 8.2)..."
sudo apt-get update -q
sudo apt-get install -y -q software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt-get update -q
sudo apt-get install -y -q php8.2 php8.2-sqlite3 php8.2-curl php8.2-xml php8.2-mbstring python3 python3-pip unzip curl apache2 libapache2-mod-php8.2

echo "[*] Installing Python Dependencies..."
sudo pip3 install pyinstaller websocket-client --break-system-packages

echo "[2/6] Installing Composer..."
if ! command -v composer &> /dev/null; then
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

echo "[3/6] Deploying Files to $INSTALL_DIR..."
sudo mkdir -p $INSTALL_DIR

# Backup existing database if it exists
if [ -f "$INSTALL_DIR/data/c2.db" ]; then
    echo "[*] Backing up existing database..."
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

echo "[4/6] Installing PHP Dependencies..."
if [ -d "$INSTALL_DIR/websocket" ]; then
    cd $INSTALL_DIR/websocket
    sudo rm -f composer.lock
    sudo composer update --no-interaction
    cd ..
fi

echo "[5/6] Configuring Apache..."
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

sudo a2dissite 000-default.conf
sudo a2ensite nexus-c2.conf
sudo a2enmod rewrite
sudo systemctl restart apache2

echo "[6/6] Configuring WebSocket Service..."
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

echo "[*] Configuring sudo permission for Web Panel..."
# Allow www-data to manage the service without password
echo "$WEB_USER ALL=(ALL) NOPASSWD: /bin/systemctl start nexus-c2-socket, /bin/systemctl stop nexus-c2-socket, /bin/systemctl restart nexus-c2-socket, /bin/systemctl status nexus-c2-socket, /bin/systemctl is-active nexus-c2-socket" | sudo tee /etc/sudoers.d/nexus-c2
sudo chmod 0440 /etc/sudoers.d/nexus-c2

echo "================================="
echo "   DEPLOYMENT COMPLETE!"
echo "================================="
echo "Web Panel: http://$(hostname -I | awk '{print $1}')/"
echo "Socket Server: Running on port 8080 (Service: nexus-c2-socket)"
echo "Credentials: admin / admin"
echo "================================="
