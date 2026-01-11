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
                SELECT g.*, COUNT(a.id) as agent_count 
                FROM groups g 
                LEFT JOIN agents a ON g.id = a.group_id 
                GROUP BY g.id 
                ORDER BY g.name
            ");
            jsonResponse(['groups' => $stmt->fetchAll()]);
        } elseif ($action === 'get' && isset($_GET['id'])) {
            $stmt = db()->prepare("SELECT * FROM groups WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            $group = $stmt->fetch();
            
            $stmt = db()->prepare("SELECT * FROM agents WHERE group_id = ?");
            $stmt->execute([$_GET['id']]);
            $group['agents'] = $stmt->fetchAll();
            
            jsonResponse(['group' => $group]);
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
        
    case 'POST':
        if ($action === 'create') {
            $name = sanitize($data['name'] ?? '');
            $description = sanitize($data['description'] ?? '');
            $color = $data['color'] ?? '#00d4ff';
            
            if (empty($name)) {
                jsonResponse(['error' => 'Group name required'], 400);
            }
            
            $stmt = db()->prepare("INSERT INTO groups (name, description, color) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $color]);
            jsonResponse(['success' => true, 'id' => db()->lastInsertId()]);
        } elseif ($action === 'update') {
            $id = $data['id'] ?? '';
            $name = sanitize($data['name'] ?? '');
            $description = sanitize($data['description'] ?? '');
            $color = $data['color'] ?? '#00d4ff';
            
            if (empty($id) || empty($name)) {
                jsonResponse(['error' => 'ID and name required'], 400);
            }
            
            $stmt = db()->prepare("UPDATE groups SET name = ?, description = ?, color = ? WHERE id = ?");
            $stmt->execute([$name, $description, $color, $id]);
            jsonResponse(['success' => true]);
        } elseif ($action === 'delete') {
            $id = $data['id'] ?? '';
            
            if (empty($id)) {
                jsonResponse(['error' => 'Group ID required'], 400);
            }
            
            $stmt = db()->prepare("DELETE FROM groups WHERE id = ?");
            $stmt->execute([$id]);
            jsonResponse(['success' => true]);
        } else {
            jsonResponse(['error' => 'Invalid action'], 400);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Method not allowed'], 405);
}
