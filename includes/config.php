<?php

define('DB_PATH', __DIR__ . '/../data/c2.db');
define('WS_HOST', '127.0.0.1');
define('WS_PORT', 8080);
define('SESSION_TIMEOUT', 3600);
define('APP_NAME', 'NEXUS C2');

ini_set('display_errors', 0);
error_reporting(0);

session_set_cookie_params([
    'lifetime' => SESSION_TIMEOUT,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict'
]);
