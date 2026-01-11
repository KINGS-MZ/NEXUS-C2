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

# Try to add PPA, but continue if it fails
sudo add-apt-repository -y ppa:ondrej/php 2>/dev/null || echo "[*] Using default repositories"
sudo apt-get update -q

# Try PHP 8.2, fallback to default PHP if not available
if ! sudo apt-get install -y -q php8.2 php8.2-sqlite3 php8.2-curl php8.2-xml php8.2-mbstring libapache2-mod-php8.2 2>/dev/null; then
    echo "[*] PHP 8.2 not available, using default PHP version"
    sudo apt-get install -y -q php php-sqlite3 php-curl php-xml php-mbstring libapache2-mod-php
fi

sudo apt-get install -y -q python3 python3-pip unzip curl apache2

echo "[*] Installing Python Dependencies..."

# Clean up any previous installations
sudo pip3 uninstall -y pyinstaller websocket-client 2>/dev/null
sudo apt-get remove -y python3-pyinstaller 2>/dev/null
sudo rm -f /usr/local/bin/pyinstaller /usr/bin/pyinstaller 2>/dev/null

# Install using pip3 (works with older pip versions)
sudo -H pip3 install pyinstaller websocket-client

# Find where PyInstaller was installed
PYINSTALLER_LOCATIONS=(
    "/usr/local/bin/pyinstaller"
    "/root/.local/bin/pyinstaller"
    "$(python3 -m site --user-base)/bin/pyinstaller"
)

PYINSTALLER_PATH=""
for loc in "${PYINSTALLER_LOCATIONS[@]}"; do
    if [ -f "$loc" ]; then
        PYINSTALLER_PATH="$loc"
        echo "[+] Found PyInstaller at: $PYINSTALLER_PATH"
        break
    fi
done

# If still not found, search more broadly
if [ -z "$PYINSTALLER_PATH" ]; then
    PYINSTALLER_PATH=$(find /usr /root -name pyinstaller -type f 2>/dev/null | grep -E "bin/pyinstaller$" | head -n 1)
fi

# Copy to /usr/bin and make it globally accessible
if [ -n "$PYINSTALLER_PATH" ] && [ -f "$PYINSTALLER_PATH" ]; then
    sudo cp "$PYINSTALLER_PATH" /usr/bin/pyinstaller
    sudo chmod 755 /usr/bin/pyinstaller
    echo "[✓] PyInstaller installed to /usr/bin/pyinstaller"
else
    echo "[!] Warning: PyInstaller binary not found, creating wrapper..."
    # Create wrapper script as fallback
    cat <<'WRAPPER' | sudo tee /usr/bin/pyinstaller
#!/bin/bash
python3 -m PyInstaller "$@"
WRAPPER
    sudo chmod 755 /usr/bin/pyinstaller
fi

# Verify PyInstaller works
if /usr/bin/pyinstaller --version >/dev/null 2>&1; then
    echo "[✓] PyInstaller verification passed: $(/usr/bin/pyinstaller --version)"
else
    echo "[!] Warning: PyInstaller may not work correctly"
fi

# Make Python packages accessible to all users
sudo chmod -R 755 /usr/local/lib/python*/dist-packages 2>/dev/null
sudo chmod -R 755 /usr/lib/python3/dist-packages 2>/dev/null

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

# CRITICAL: Fix PATH for www-data to find pyinstaller
echo "[*] Configuring environment for web user..."
sudo mkdir -p /var/www
cat <<'ENVFILE' | sudo tee /var/www/.bashrc
export PATH="/usr/bin:/usr/local/bin:/bin:$PATH"
ENVFILE
sudo chown www-data:www-data /var/www/.bashrc

echo "================================="
echo "   DEPLOYMENT COMPLETE!"
echo "================================="
echo "Web Panel: http://$(hostname -I | awk '{print $1}')/"
echo "Socket Server: Running on port 8080 (Service: nexus-c2-socket)"
echo "Credentials: admin / admin"
echo "================================="
echo ""
echo "PyInstaller location: /usr/bin/pyinstaller"
echo "Test command: sudo -u www-data /usr/bin/pyinstaller --version"
echo "================================="
