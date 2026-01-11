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
            $agentId = $_GET['agent_id'] ?? null;
            $limit = min((int)($_GET['limit'] ?? 50), 100);
            
            if ($agentId) {
                $stmt = db()->prepare("
                    SELECT c.*, a.hostname 
                    FROM commands c 
                    LEFT JOIN agents a ON c.agent_id = a.id 
                    WHERE c.agent_id = ? 
                    ORDER BY c.created_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$agentId, $limit]);
            } else {
                $stmt = db()->prepare("
                    SELECT c.*, a.hostname 
                    FROM commands c 
                    LEFT JOIN agents a ON c.agent_id = a.id 
                    ORDER BY c.created_at DESC 
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
            }
            jsonResponse(['commands' => $stmt->fetchAll()]);
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
        
    case 'POST':
        if ($action === 'clear') {
            $agentId = $data['agent_id'] ?? null;
            
            if ($agentId) {
                $stmt = db()->prepare("DELETE FROM commands WHERE agent_id = ?");
                $stmt->execute([$agentId]);
            } else {
                db()->exec("DELETE FROM commands");
            }
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
