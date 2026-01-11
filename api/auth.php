<?php

require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $data = $_POST;
}

$action = $data['action'] ?? '';

switch ($action) {
    case 'login':
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            jsonResponse(['error' => 'Username and password required'], 400);
        }
        
        $stmt = db()->prepare("SELECT id, password_hash FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($password, $user['password_hash'])) {
            jsonResponse(['error' => 'Invalid credentials'], 401);
        }
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $username;
        $_SESSION['last_activity'] = time();
        
        jsonResponse(['success' => true, 'redirect' => 'dashboard.php']);
        break;
        
    case 'logout':
        session_destroy();
        jsonResponse(['success' => true]);
        break;
        
    case 'check':
        if (isset($_SESSION['user_id'])) {
            jsonResponse(['authenticated' => true, 'username' => $_SESSION['username']]);
        } else {
            jsonResponse(['authenticated' => false], 401);
        }
        break;
        
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}
