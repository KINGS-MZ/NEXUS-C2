<?php

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/C2Server.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;

$port = 8080;

echo "=================================\n";
echo "   NEXUS C2 - WebSocket Server\n";
echo "   Created by @Imad\n";
echo "=================================\n";
echo "[*] Starting on port {$port}...\n\n";

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new C2Server()
        )
    ),
    $port
);

echo "[*] Server running. Press Ctrl+C to stop.\n\n";

$server->run();
