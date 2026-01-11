class C2WebSocket {
    constructor() {
        this.ws = null;
        this.connected = false;
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 10;
        this.reconnectDelay = 2000;
        this.listeners = new Map();
        this.pendingCommands = new Map();
    }

    connect() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const wsUrl = `${protocol}//${window.location.hostname}:8080`;

        this.ws = new WebSocket(wsUrl);

        this.ws.onopen = () => {
            this.connected = true;
            this.reconnectAttempts = 0;
            this.updateConnectionStatus(true);
            this.send({ type: 'panel_subscribe' });
            this.emit('connected');
        };

        this.ws.onclose = () => {
            this.connected = false;
            this.updateConnectionStatus(false);
            this.emit('disconnected');
            this.scheduleReconnect();
        };

        this.ws.onerror = () => {
            this.connected = false;
            this.updateConnectionStatus(false);
        };

        this.ws.onmessage = (event) => {
            try {
                const data = JSON.parse(event.data);
                this.handleMessage(data);
            } catch (e) { }
        };
    }

    scheduleReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            setTimeout(() => this.connect(), this.reconnectDelay);
        }
    }

    updateConnectionStatus(isConnected) {
        const statusEl = document.getElementById('connectionStatus');
        if (statusEl) {
            if (isConnected) {
                statusEl.classList.add('online');
                statusEl.classList.remove('offline');
                statusEl.title = 'Connected';
            } else {
                statusEl.classList.remove('online');
                statusEl.classList.add('offline');
                statusEl.title = 'Disconnected';
            }
        }
    }

    handleMessage(data) {
        console.log('[WS] Received:', data);
        switch (data.type) {
            case 'agent_connected':
                this.emit('agentConnected', data.agent);
                break;
            case 'agent_disconnected':
                this.emit('agentDisconnected', data.agent_id);
                break;
            case 'agent_list':
                this.emit('agentList', data.agents);
                break;
            case 'command_result':
                console.log('[WS] Command result received:', data);
                this.emit('commandResult', data);
                if (this.pendingCommands.has(data.command_id)) {
                    console.log('[WS] Calling callback for:', data.command_id);
                    const callback = this.pendingCommands.get(data.command_id);
                    callback(data);
                    this.pendingCommands.delete(data.command_id);
                }
                break;
            case 'stats_update':
                this.emit('statsUpdate', data.stats);
                break;
        }
    }

    send(data) {
        if (this.connected && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(data));
            return true;
        }
        return false;
    }

    sendCommand(target, targetType, command, callback) {
        const commandId = 'cmd_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);

        if (callback) {
            this.pendingCommands.set(commandId, callback);
        }

        this.send({
            type: 'send_command',
            command_id: commandId,
            target: target,
            target_type: targetType,
            command: command
        });

        return commandId;
    }

    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }

    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => callback(data));
        }
    }
}

const ws = new C2WebSocket();
