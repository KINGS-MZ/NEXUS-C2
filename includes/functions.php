<?php

function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function formatDate($datetime) {
    if (!$datetime) return 'Never';
    $dt = new DateTime($datetime);
    return $dt->format('M d, Y H:i:s');
}

function getRelativeTime($datetime) {
    if (!$datetime) return 'Never';
    $now = new DateTime();
    $dt = new DateTime($datetime);
    $diff = $now->getTimestamp() - $dt->getTimestamp();
    
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return floor($diff / 86400) . 'd ago';
}
