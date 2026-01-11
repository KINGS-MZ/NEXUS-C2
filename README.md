# NEXUS C2 Framework - Setup Guide

## Quick Start

### Step 1: Install Composer
- **Windows**: https://getcomposer.org/Composer-Setup.exe
- **Ubuntu**: `sudo apt install composer`

### Step 2: Install Dependencies
```bash
cd c2/websocket
composer install
```

### Step 3: Start Web Server
- **Windows (XAMPP)**: Start Apache in XAMPP Control Panel
- **Ubuntu**: `sudo apt install apache2 php php-sqlite3 && sudo systemctl start apache2`

### Step 4: Start WebSocket Server
- **Windows**: Double-click `websocket/start_server.bat`
- **Ubuntu**: `cd websocket && chmod +x start_server.sh && ./start_server.sh`

### Step 5: Access Panel
Open: http://localhost/c2/

**Login**: `admin` / `admin`

### Step 6: Build Agent
```bash
cd agent
# Windows
build.bat
# Ubuntu
pip install pyinstaller websocket-client && pyinstaller --onefile --noconsole beacon.py
```

## Ports
| Service | Port |
|---------|------|
| Web Panel | 80 |
| WebSocket | 8080 |
