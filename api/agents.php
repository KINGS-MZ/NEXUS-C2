<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth_middleware.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();
requireAuth();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($action === 'list') {
            $stmt = db()->query("
                SELECT a.*, g.name as group_name, g.color as group_color 
                FROM agents a 
                LEFT JOIN groups g ON a.group_id = g.id 
                ORDER BY a.status DESC, a.last_seen DESC
            ");
            jsonResponse(['agents' => $stmt->fetchAll()]);
        } elseif ($action === 'get' && isset($_GET['id'])) {
            $stmt = db()->prepare("
                SELECT a.*, g.name as group_name 
                FROM agents a 
                LEFT JOIN groups g ON a.group_id = g.id 
                WHERE a.id = ?
            ");
            $stmt->execute([$_GET['id']]);
            jsonResponse(['agent' => $stmt->fetch()]);
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
        
    case 'POST':
        if ($action === 'update_group') {
            $agentId = $data['agent_id'] ?? '';
            $groupId = $data['group_id'] ?? null;
            
            if (empty($agentId)) {
                jsonResponse(['error' => 'Agent ID required'], 400);
            }
            
            $stmt = db()->prepare("UPDATE agents SET group_id = ? WHERE id = ?");
            $stmt->execute([$groupId ?: null, $agentId]);
            jsonResponse(['success' => true]);
        } elseif ($action === 'delete') {
            $agentId = $data['agent_id'] ?? '';
            
            if (empty($agentId)) {
                jsonResponse(['error' => 'Agent ID required'], 400);
            }
            
            $stmt = db()->prepare("DELETE FROM agents WHERE id = ?");
            $stmt->execute([$agentId]);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
