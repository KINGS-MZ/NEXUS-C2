<?php

require_once __DIR__ . '/config.php';

function requireAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
        header('Location: index.php');
        exit;
    }
    
    if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
        session_destroy();
        header('Location: /c2/index.php?timeout=1');
        exit;
    }
    
    $_SESSION['last_activity'] = time();
}

function isAuthenticated() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']) && isset($_SESSION['last_activity']);
}
