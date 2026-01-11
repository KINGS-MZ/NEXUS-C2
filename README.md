# NEXUS C2 Framework 🚀

![Version](https://img.shields.io/badge/version-1.0.0-blue.svg) ![License](https://img.shields.io/badge/license-MIT-green.svg) ![Author](https://img.shields.io/badge/created%20by-%40Imad-cyan.svg)

**NEXUS C2** is a lightweight, advanced Command and Control framework designed for Red Team operations and cybersecurity education. Built with a focus on speed, stealth, and real-time communication.

---

## 🔥 Features

- **Real-Time Communication**: Instant command execution via WebSockets (Ratchet).
- **Advanced Dashboard**: Sleek, dark-mode UI with live status updates and terminal-like interaction.
- **Group Management**: Organize agents into groups for bulk command execution.
- **Stealthy Agents**: Python-based beacons with robust reconnection logic and system reconnaissance.
- **Secure Architecture**: User authentication, session management, and database encryption.
- **Zero-Touch Deployment**: Fully automated installer setup for Ubuntu/Debian servers.

---

## 🛠️ Installation

### Prerequisites
- **Server**: Ubuntu 20.04/22.04 or Debian 11+ (Root access required)
- **Client**: Any modern web browser

### One-Click Deployment
1. **Clone/Upload** this repository to your server.
2. **Run the Installer**:
   ```bash
   chmod +x setup.sh
   sudo ./setup.sh
   ```
   
   > **Note**: The installer will automatically configure Apache, SQLite, PHP 8.2, and Systemd services for you. It handles everything!

3. **Access the Panel**:
   - URL: `http://<your-server-ip>/`
   - Default Credentials: `admin` / `admin`

---

## 💻 Usage

### 1. Generating a Beacon
- Go to the **Payloads** tab in the dashboard.
- Enter your server IP and Port (Default: 8080).
- Click **Build** to generate a `beacon.py` agent.

### 2. Managing Agents
- **Dashboard**: View all connected agents, their OS, IP, and status.
- **Terminal**: Open a direct shell to any agent to execute system commands.
- **Groups**: Assign agents to groups (e.g., "Windows-Targets") for organized attacks.

### 3. Server Control
- Use the **Start/Stop/Restart** controls in the dashboard sidebar to manage the WebSocket listener directly from the web interface.

---

## ⚠️ Legal Disclaimer
This software is provided for **educational and authorized testing purposes only**. The creator assumes no liability and is not responsible for any misuse or damage caused by this program.

---

## 👨‍💻 Credits

**Created & Designed by [@Imad]**

---
© 2026 NEXUS C2. All rights reserved.
