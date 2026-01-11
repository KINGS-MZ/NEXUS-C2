<?php
require_once __DIR__ . '/../includes/auth_middleware.php';
session_start();
requireAuth();

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Define paths
$serverDir = realpath(__DIR__ . '/../websocket');
$pidFile = $serverDir . '/server.pid';
$logFile = $serverDir . '/server.log';

function isServerRunning() {
    global $pidFile;
    
    if (!file_exists($pidFile)) {
        return false;
    }
    
    $pid = trim(file_get_contents($pidFile));
    if (empty($pid)) {
        return false;
    }
    
    // Check if process is running (Windows)
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("tasklist /FI \"PID eq {$pid}\" 2>NUL", $output, $returnVar);
        foreach ($output as $line) {
            if (strpos($line, $pid) !== false) {
                return true;
            }
        }
        return false;
    } else {
        // Linux/Mac
        return file_exists("/proc/{$pid}");
    }
}

function getServerPid() {
    global $pidFile;
    if (file_exists($pidFile)) {
        return trim(file_get_contents($pidFile));
    }
    return null;
}

function startServer() {
    global $serverDir, $pidFile, $logFile;
    
    if (isServerRunning()) {
        return ['success' => false, 'message' => 'Server is already running'];
    }
    
    // Windows command to start PHP in background
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $phpPath = 'php'; // Assumes PHP is in PATH
        $serverScript = $serverDir . '/server.php';
        
        // Use PowerShell to start the process in background and capture PID
        $cmd = "powershell -Command \"Start-Process -FilePath '{$phpPath}' -ArgumentList '\"{$serverScript}\"' -WindowStyle Hidden -PassThru | Select-Object -ExpandProperty Id\"";
        
        exec($cmd, $output, $returnVar);
        
        if ($returnVar === 0 && !empty($output[0])) {
            $pid = trim($output[0]);
            file_put_contents($pidFile, $pid);
            
            // Give it a moment to start
            usleep(500000);
            
            if (isServerRunning()) {
                return ['success' => true, 'message' => 'Server started', 'pid' => $pid];
            } else {
                return ['success' => false, 'message' => 'Server failed to start'];
            }
        }
        
        return ['success' => false, 'message' => 'Failed to start server process'];
    } else {
        // Linux/Mac
        $cmd = "cd {$serverDir} && nohup php server.php > {$logFile} 2>&1 & echo $!";
        $pid = trim(shell_exec($cmd));
        
        if ($pid) {
            file_put_contents($pidFile, $pid);
            usleep(500000);
            
            if (isServerRunning()) {
                return ['success' => true, 'message' => 'Server started', 'pid' => $pid];
            }
        }
        
        return ['success' => false, 'message' => 'Failed to start server'];
    }
}

function stopServer() {
    global $pidFile;
    
    if (!isServerRunning()) {
        // Clean up PID file if it exists
        if (file_exists($pidFile)) {
            unlink($pidFile);
        }
        return ['success' => true, 'message' => 'Server is not running'];
    }
    
    $pid = getServerPid();
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("taskkill /PID {$pid} /F 2>NUL", $output, $returnVar);
    } else {
        exec("kill -9 {$pid} 2>/dev/null", $output, $returnVar);
    }
    
    // Clean up PID file
    if (file_exists($pidFile)) {
        unlink($pidFile);
    }
    
    usleep(300000);
    
    if (!isServerRunning()) {
        return ['success' => true, 'message' => 'Server stopped'];
    }
    
    return ['success' => false, 'message' => 'Failed to stop server'];
}

function restartServer() {
    $stopResult = stopServer();
    usleep(500000);
    return startServer();
}

function getStatus() {
    $running = isServerRunning();
    $pid = getServerPid();
    
    return [
        'success' => true,
        'running' => $running,
        'pid' => $running ? $pid : null,
        'port' => 8080
    ];
}

// Handle actions
switch ($action) {
    case 'start':
        echo json_encode(startServer());
        break;
    case 'stop':
        echo json_encode(stopServer());
        break;
    case 'restart':
        echo json_encode(restartServer());
        break;
    case 'status':
        echo json_encode(getStatus());
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
