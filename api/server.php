<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
session_start();
requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Define paths
$serverDir = realpath(__DIR__ . '/../websocket');
$pidFile = $serverDir . '/server.pid';

// Detect OS
$isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

function isServerRunning() {
    global $pidFile, $isWindows;
    
    if ($isWindows) {
        if (!file_exists($pidFile)) return false;
        $pid = trim(file_get_contents($pidFile));
        if (empty($pid)) return false;
        
        exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output);
        foreach ($output as $line) {
            if (strpos($line, $pid) !== false) return true;
        }
        return false;
    } else {
        // Linux: Check systemd service status
        exec('sudo /bin/systemctl is-active nexus-c2-socket', $output, $returnVar);
        // 'active' means running. 'inactive' or 'failed' means not running.
        return isset($output[0]) && trim($output[0]) === 'active';
    }
}

function startServer() {
    global $serverDir, $pidFile, $isWindows;
    
    if (isServerRunning()) {
        return ['success' => false, 'message' => 'Server is already running'];
    }
    
    if ($isWindows) {
        $phpPath = 'php'; 
        $serverScript = $serverDir . '/server.php';
        $cmd = "powershell -Command \"Start-Process -FilePath '{$phpPath}' -ArgumentList '\"{$serverScript}\"' -WindowStyle Hidden -PassThru | Select-Object -ExpandProperty Id\"";
        exec($cmd, $output, $returnVar);
        
        if ($returnVar === 0 && !empty($output[0])) {
            $pid = trim($output[0]);
            file_put_contents($pidFile, $pid);
            usleep(500000);
            return isServerRunning() 
                ? ['success' => true, 'message' => 'Server started', 'pid' => $pid]
                : ['success' => false, 'message' => 'Server failed to start'];
        }
        return ['success' => false, 'message' => 'Failed to start server process'];
    } else {
        // Linux: Start systemd service
        exec('sudo /bin/systemctl start nexus-c2-socket', $output, $returnVar);
        usleep(1000000); // Wait 1s
        
        if (isServerRunning()) {
            return ['success' => true, 'message' => 'Server started (Service)'];
        } else {
            // Capture error if possible
            return ['success' => false, 'message' => 'Failed to start service. Check logs.'];
        }
    }
}

function stopServer() {
    global $pidFile, $isWindows;
    
    if (!$isWindows) {
        // Linux: Stop systemd service
        exec('sudo /bin/systemctl stop nexus-c2-socket', $output, $returnVar);
        usleep(500000);
        return !isServerRunning() 
            ? ['success' => true, 'message' => 'Server stopped']
            : ['success' => false, 'message' => 'Failed to stop service'];
    }
    
    // Windows logic
    if (!isServerRunning()) {
        if (file_exists($pidFile)) unlink($pidFile);
        return ['success' => true, 'message' => 'Server is not running'];
    }
    
    $pid = trim(file_get_contents($pidFile));
    exec("taskkill /PID {$pid} /F 2>NUL");
    if (file_exists($pidFile)) unlink($pidFile);
    usleep(300000);
    
    return !isServerRunning()
        ? ['success' => true, 'message' => 'Server stopped']
        : ['success' => false, 'message' => 'Failed to stop server'];
}

function restartServer() {
    global $isWindows;
    if (!$isWindows) {
         exec('sudo /bin/systemctl restart nexus-c2-socket');
         usleep(1000000);
         return isServerRunning() 
            ? ['success' => true, 'message' => 'Server restarted']
            : ['success' => false, 'message' => 'Failed to restart'];
    }
    
    $stopResult = stopServer();
    usleep(500000);
    return startServer();
}

function getStatus() {
    $running = isServerRunning();
    return [
        'success' => true,
        'running' => $running,
        'pid' => null, // Not relevant for systemd
        'port' => 8080
    ];
}

// Handle actions
switch ($action) {
    case 'start': echo json_encode(startServer()); break;
    case 'stop': echo json_encode(stopServer()); break;
    case 'restart': echo json_encode(restartServer()); break;
    case 'status': echo json_encode(getStatus()); break;
    default: echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
