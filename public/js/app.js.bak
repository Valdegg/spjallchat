/**
 * Spjall Chat Client
 * Vanilla JavaScript WebSocket client
 */

// ============================================================
// CONFIGURATION
// ============================================================

const CONFIG = {
    // WebSocket server URL - adjust for your friend's server
    wsUrl: 'wss://unconceptually-unreciprocated-cordia.ngrok-free.dev',
    
    // Heartbeat interval (ms)
    pingInterval: 30000,
    
    // Reconnect delay (ms)
    reconnectDelay: 3000,
    
    // History page size
    historyLimit: 50
};

// ============================================================
// STATE
// ============================================================

const state = {
    socket: null,
    user: null,
    token: null,
    
    // Conversations
    conversations: [],
    currentConversation: 1, // Lobby by default
    
    // Users
    onlineUsers: [],
    allUsers: {}, // id → user
    
    // Messages per conversation
    messages: {}, // conversationId → [messages]
    hasMore: {},  // conversationId → boolean
    unread: {},   // conversationId → count
    
    // UI state
    isConnected: false,
    isLoading: true,
    pingTimer: null,
    reconnectTimer: null,
    windowFocused: true,
    titleInterval: null,
    originalTitle: 'Spjall'
};

// ============================================================
// DOM ELEMENTS
// ============================================================

const dom = {
    loading: document.getElementById('loading'),
    error: document.getElementById('error'),
    main: document.getElementById('main'),
    
    conversationName: document.getElementById('conversation-name'),
    messages: document.getElementById('messages'),
    messageInput: document.getElementById('message-input'),
    
    dmList: document.getElementById('dm-list'),
    groupList: document.getElementById('group-list'),
    onlineUsers: document.getElementById('online-users'),
    offlineUsers: document.getElementById('offline-users'),
    onlineCount: document.getElementById('online-count'),
    offlineCount: document.getElementById('offline-count'),
    
    currentUser: document.getElementById('current-user'),
    inviteBtn: document.getElementById('invite-btn'),
    logoutBtn: document.getElementById('logout-btn'),
    
    inviteModal: document.getElementById('invite-modal'),
    inviteUrl: document.getElementById('invite-url'),
    copyInvite: document.getElementById('copy-invite'),
    closeInvite: document.getElementById('close-invite'),
    
    groupBtn: document.getElementById('group-btn'),
    groupModal: document.getElementById('group-modal'),
    groupUserList: document.getElementById('group-user-list'),
    createGroup: document.getElementById('create-group'),
    closeGroup: document.getElementById('close-group')
};

// ============================================================
// INITIALIZATION
// ============================================================

function init() {
    // Check for token
    state.token = localStorage.getItem('spjall_token');
    const savedUser = localStorage.getItem('spjall_user');
    
    if (!state.token) {
        // No token, show need-invite page
        showNeedInvite();
        return;
    }
    
    if (savedUser) {
        state.user = JSON.parse(savedUser);
    }
    
    // Connect WebSocket
    connect();
    
    // Setup event listeners
    setupEventListeners();
}

function showNeedInvite() {
    dom.loading.style.display = 'none';
    dom.error.style.display = 'none';
    dom.main.style.display = 'none';
    
    document.getElementById('app').innerHTML = `
        <div class="state">
            <div class="need-invite">
                <h1>Spjall</h1>
                <p>Log in or enter an invite code to join.</p>
                <div class="buttons">
                    <a href="/login" class="btn primary">Log In</a>
                </div>
                <div class="divider">or</div>
                <form id="invite-form">
                    <input type="text" id="invite-code" placeholder="Invite code" maxlength="20" autocomplete="off">
                    <button type="submit">Join</button>
                </form>
            </div>
        </div>
    `;
    
    // Add styles for this page
    const style = document.createElement('style');
    style.textContent = `
        .need-invite {
            text-align: center;
        }
        .need-invite h1 {
            color: var(--text-bright);
            font-size: 24px;
            font-weight: normal;
            margin-bottom: 8px;
        }
        .need-invite p {
            color: var(--text-dim);
            margin-bottom: 20px;
        }
        .need-invite .buttons {
            margin-bottom: 16px;
        }
        .need-invite .btn {
            display: inline-block;
            padding: 10px 24px;
            font-family: inherit;
            font-size: 14px;
            text-decoration: none;
            border: 1px solid var(--border);
            color: var(--text);
        }
        .need-invite .btn.primary {
            background: var(--accent-dim);
            border-color: var(--accent-dim);
            color: var(--text-bright);
        }
        .need-invite .btn:hover {
            background: var(--accent);
            border-color: var(--accent);
        }
        .need-invite .divider {
            color: var(--text-dim);
            font-size: 12px;
            margin: 16px 0;
        }
        .need-invite form {
            display: flex;
            gap: 8px;
            justify-content: center;
        }
        .need-invite input {
            padding: 8px 12px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            color: var(--text-bright);
            font-family: inherit;
            font-size: 14px;
            width: 140px;
        }
        .need-invite input:focus {
            outline: none;
            border-color: var(--accent-dim);
        }
        .need-invite button {
            padding: 8px 16px;
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            color: var(--text);
            font-family: inherit;
            cursor: pointer;
        }
        .need-invite button:hover {
            border-color: var(--accent-dim);
            color: var(--text-bright);
        }
    `;
    document.head.appendChild(style);
    
    // Handle invite form submit
    document.getElementById('invite-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const code = document.getElementById('invite-code').value.trim().toUpperCase();
        if (code) {
            window.location.href = '/join/' + code;
        }
    });
}

function setupEventListeners() {
    // Message input
    dom.messageInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    
    // Scroll for history loading
    dom.messages.addEventListener('scroll', () => {
        if (dom.messages.scrollTop === 0) {
            loadMoreHistory();
        }
    });
    
    // Lobby click
    document.querySelector('.conversation-item[data-id="1"]').addEventListener('click', () => {
        switchConversation(1);
    });
    
    // Invite button
    dom.inviteBtn.addEventListener('click', () => {
        send('create_invite', {});
    });
    
    // Copy invite
    dom.copyInvite.addEventListener('click', () => {
        dom.inviteUrl.select();
        document.execCommand('copy');
    });
    
    // Close invite modal
    dom.closeInvite.addEventListener('click', () => {
        dom.inviteModal.style.display = 'none';
    });
    
    // Logout
    dom.logoutBtn.addEventListener('click', () => {
        localStorage.removeItem('spjall_token');
        localStorage.removeItem('spjall_user');
        window.location.reload();
    });
    
    // Group button
    dom.groupBtn.addEventListener('click', openGroupModal);
    
    dom.closeGroup.addEventListener('click', () => {
        dom.groupModal.style.display = 'none';
    });
    
    dom.createGroup.addEventListener('click', () => {
        const checked = dom.groupUserList.querySelectorAll('input:checked');
        const userIds = Array.from(checked).map(el => parseInt(el.value));
        
        if (userIds.length < 1) {
            alert('Select at least 1 other user');
            return;
        }
        
        send('create_group', { user_ids: userIds });
        dom.groupModal.style.display = 'none';
    });
    
    // Window focus tracking for notifications
    window.addEventListener('focus', () => {
        state.windowFocused = true;
        clearUnreadTitle();
    });
    
    window.addEventListener('blur', () => {
        state.windowFocused = false;
    });
    
    // Request notification permission
    if ('Notification' in window && Notification.permission === 'default') {
        // Ask for permission after first user interaction
        document.addEventListener('click', requestNotificationPermission, { once: true });
    }
}

function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// ============================================================
// WEBSOCKET
// ============================================================

function connect() {
    showLoading('Connecting...');
    
    try {
        state.socket = new WebSocket(CONFIG.wsUrl);
    } catch (err) {
        showError('Failed to connect: ' + err.message);
        scheduleReconnect();
        return;
    }
    
    state.socket.onopen = () => {
        console.log('WebSocket connected');
        showLoading('Authenticating...');
        
        // Authenticate
        send('auth', { token: state.token });
    };
    
    state.socket.onmessage = (event) => {
        try {
            const message = JSON.parse(event.data);
            handleMessage(message);
        } catch (err) {
            console.error('Failed to parse message:', err);
        }
    };
    
    state.socket.onclose = () => {
        console.log('WebSocket closed');
        state.isConnected = false;
        stopPing();
        
        if (!state.isLoading) {
            showError('Connection lost. Reconnecting...');
        }
        
        scheduleReconnect();
    };
    
    state.socket.onerror = (err) => {
        console.error('WebSocket error:', err);
    };
}

function scheduleReconnect() {
    if (state.reconnectTimer) return;
    
    state.reconnectTimer = setTimeout(() => {
        state.reconnectTimer = null;
        connect();
    }, CONFIG.reconnectDelay);
}

function send(type, payload) {
    if (!state.socket || state.socket.readyState !== WebSocket.OPEN) {
        console.error('Cannot send, socket not open');
        return;
    }
    
    state.socket.send(JSON.stringify({ type, payload }));
}

function startPing() {
    stopPing();
    state.pingTimer = setInterval(() => {
        send('ping', {});
    }, CONFIG.pingInterval);
}

function stopPing() {
    if (state.pingTimer) {
        clearInterval(state.pingTimer);
        state.pingTimer = null;
    }
}

// ============================================================
// MESSAGE HANDLERS
// ============================================================

function handleMessage(msg) {
    const { type, payload } = msg;
    
    switch (type) {
        case 'auth_ok':
            handleAuthOk(payload);
            break;
        case 'auth_error':
            handleAuthError(payload);
            break;
        case 'message':
            handleNewMessage(payload);
            break;
        case 'history':
            handleHistory(payload);
            break;
        case 'user_online':
            handleUserOnline(payload);
            break;
        case 'user_offline':
            handleUserOffline(payload);
            break;
        case 'conversation_created':
            handleConversationCreated(payload);
            break;
        case 'invite_created':
            handleInviteCreated(payload);
            break;
        case 'pong':
            // Heartbeat response, nothing to do
            break;
        case 'error':
            console.error('Server error:', payload.message);
            break;
        default:
            console.log('Unknown message type:', type);
    }
}

function handleAuthOk(payload) {
    state.user = payload.user;
    state.conversations = payload.conversations;
    state.onlineUsers = payload.online_users;
    state.isConnected = true;
    state.isLoading = false;
    
    // Store user
    localStorage.setItem('spjall_user', JSON.stringify(state.user));
    
    // Build user map
    state.onlineUsers.forEach(u => {
        state.allUsers[u.id] = u;
    });
    
    // Initialize messages for all conversations
    state.conversations.forEach(c => {
        if (!state.messages[c.id]) {
            state.messages[c.id] = [];
            state.hasMore[c.id] = true;
        }
        // Add members to user map
        c.members.forEach(m => {
            state.allUsers[m.id] = m;
        });
    });
    
    // Show UI
    showMain();
    renderSidebar();
    renderMessages();
    
    // Load history for current conversation
    loadHistory(state.currentConversation);
    
    // Start heartbeat
    startPing();
}

function handleAuthError(payload) {
    showError(payload.message || 'Authentication failed');
    localStorage.removeItem('spjall_token');
    localStorage.removeItem('spjall_user');
    
    setTimeout(() => {
        window.location.href = '/join/WELCOME1';
    }, 2000);
}

function handleNewMessage(message) {
    const convId = message.conversation_id;
    const isOwnMessage = message.user_id === state.user.id;
    
    if (!state.messages[convId]) {
        state.messages[convId] = [];
    }
    
    state.messages[convId].push(message);
    
    // If this is the current conversation and window is focused, render it
    if (convId === state.currentConversation && state.windowFocused) {
        appendMessage(message);
        scrollToBottom();
    } else if (convId === state.currentConversation) {
        // Current conversation but window not focused
        appendMessage(message);
        scrollToBottom();
        if (!isOwnMessage) {
            showNotification(message);
            updateUnreadTitle();
        }
    } else {
        // Different conversation - mark as unread
        if (!isOwnMessage) {
            state.unread[convId] = (state.unread[convId] || 0) + 1;
            renderSidebar();
            showNotification(message);
            updateUnreadTitle();
        }
    }
}

function showNotification(message) {
    // Get sender name
    const sender = state.allUsers[message.user_id];
    const senderName = sender ? sender.nickname : 'Someone';
    
    // Get conversation name
    const conv = state.conversations.find(c => c.id === message.conversation_id);
    let title = senderName;
    if (conv && conv.type === 'lobby') {
        title = `${senderName} in Lobby`;
    } else if (conv && conv.type === 'group') {
        title = `${senderName} in group`;
    }
    
    // Browser notification (if permitted and window not focused)
    if (!state.windowFocused && 'Notification' in window && Notification.permission === 'granted') {
        const notification = new Notification(title, {
            body: message.content.substring(0, 100),
            tag: 'spjall-' + message.conversation_id, // Collapse multiple from same conv
            renotify: true
        });
        
        notification.onclick = () => {
            window.focus();
            switchConversation(message.conversation_id);
            notification.close();
        };
        
        // Auto-close after 5 seconds
        setTimeout(() => notification.close(), 5000);
    }
}

function updateUnreadTitle() {
    const totalUnread = Object.values(state.unread).reduce((sum, n) => sum + n, 0);
    
    if (totalUnread > 0) {
        document.title = `(${totalUnread}) ${state.originalTitle}`;
        
        // Flash title if not already flashing
        if (!state.titleInterval) {
            let showCount = true;
            state.titleInterval = setInterval(() => {
                document.title = showCount ? `(${totalUnread}) ${state.originalTitle}` : state.originalTitle;
                showCount = !showCount;
            }, 1000);
        }
    } else {
        clearUnreadTitle();
    }
}

function clearUnreadTitle() {
    if (state.titleInterval) {
        clearInterval(state.titleInterval);
        state.titleInterval = null;
    }
    document.title = state.originalTitle;
}

function handleHistory(payload) {
    const convId = payload.conversation_id;
    const messages = payload.messages;
    
    state.hasMore[convId] = payload.has_more;
    
    if (!state.messages[convId]) {
        state.messages[convId] = [];
    }
    
    // Prepend messages (they're older)
    state.messages[convId] = [...messages, ...state.messages[convId]];
    
    // If current conversation, render
    if (convId === state.currentConversation) {
        renderMessages();
    }
    
    // Remove loading indicator
    const loadingEl = dom.messages.querySelector('.loading-history');
    if (loadingEl) {
        loadingEl.remove();
    }
}

function handleUserOnline(payload) {
    const user = payload.user;
    state.allUsers[user.id] = user;
    
    // Add to online list if not already there
    if (!state.onlineUsers.find(u => u.id === user.id)) {
        state.onlineUsers.push(user);
    }
    
    renderSidebar();
    addSystemMessage(`${user.nickname} is now online`);
}

function handleUserOffline(payload) {
    const userId = payload.user_id;
    
    // Remove from online list
    state.onlineUsers = state.onlineUsers.filter(u => u.id !== userId);
    
    renderSidebar();
    
    const user = state.allUsers[userId];
    if (user) {
        addSystemMessage(`${user.nickname} went offline`);
    }
}

function handleConversationCreated(payload) {
    const conv = payload.conversation;
    
    // Add members to user map
    conv.members.forEach(m => {
        state.allUsers[m.id] = m;
    });
    
    // Add to conversations
    state.conversations.push(conv);
    state.messages[conv.id] = [];
    state.hasMore[conv.id] = false;
    
    renderSidebar();
    
    // Switch to the new conversation
    switchConversation(conv.id);
}

function handleInviteCreated(payload) {
    // payload.url is already a full URL from the server
    dom.inviteUrl.value = payload.url;
    dom.inviteModal.style.display = 'flex';
}

// ============================================================
// UI RENDERING
// ============================================================

function showLoading(text) {
    dom.loading.querySelector('.status').textContent = text;
    dom.loading.style.display = 'flex';
    dom.error.style.display = 'none';
    dom.main.style.display = 'none';
}

function showError(text) {
    dom.error.querySelector('.status').textContent = text;
    dom.error.style.display = 'flex';
    dom.loading.style.display = 'none';
    dom.main.style.display = 'none';
}

function showMain() {
    dom.main.style.display = 'flex';
    dom.loading.style.display = 'none';
    dom.error.style.display = 'none';
    
    dom.currentUser.textContent = state.user.nickname;
    dom.messageInput.focus();
}

function renderSidebar() {
    // Update Lobby unread indicator
    const lobbyEl = document.querySelector('.conversation-item[data-id="1"]');
    if (lobbyEl) {
        const lobbyUnread = state.unread[1] || 0;
        const existingBadge = lobbyEl.querySelector('.unread-badge');
        if (lobbyUnread > 0 && state.currentConversation !== 1) {
            if (!existingBadge) {
                lobbyEl.insertAdjacentHTML('beforeend', '<span class="unread-badge"></span>');
            }
        } else if (existingBadge) {
            existingBadge.remove();
        }
        lobbyEl.classList.toggle('active', state.currentConversation === 1);
    }
    
    // DMs
    dom.dmList.innerHTML = state.conversations
        .filter(c => c.type === 'dm')
        .map(c => {
            const otherUser = c.members[0];
            const name = otherUser ? otherUser.nickname : 'Unknown';
            const active = c.id === state.currentConversation ? 'active' : '';
            const unread = state.unread[c.id] ? '<span class="unread-badge"></span>' : '';
            return `<div class="conversation-item ${active}" data-id="${c.id}" onclick="switchConversation(${c.id})"><span>@ ${name}</span>${unread}</div>`;
        })
        .join('');
    
    // Groups
    dom.groupList.innerHTML = state.conversations
        .filter(c => c.type === 'group')
        .map(c => {
            const names = c.members.map(m => m.nickname).join(', ');
            const active = c.id === state.currentConversation ? 'active' : '';
            const unread = state.unread[c.id] ? '<span class="unread-badge"></span>' : '';
            return `<div class="conversation-item ${active}" data-id="${c.id}" onclick="switchConversation(${c.id})" title="${names}"><span>&amp; ${c.members.length + 1} users</span>${unread}</div>`;
        })
        .join('');
    
    // Online users
    const onlineHtml = state.onlineUsers
        .filter(u => u.id !== state.user.id)
        .map(u => `<div class="user-item" onclick="startDm(${u.id})"><span class="user-dot"></span><span class="user-name">${u.nickname}</span></div>`)
        .join('');
    dom.onlineUsers.innerHTML = onlineHtml || '<div class="user-item"><span class="user-name" style="color: var(--text-dim)">—</span></div>';
    dom.onlineCount.textContent = `(${state.onlineUsers.filter(u => u.id !== state.user.id).length})`;
    
    // Offline users (known users who are not online)
    const onlineIds = new Set(state.onlineUsers.map(u => u.id));
    const offlineUsers = Object.values(state.allUsers).filter(u => !onlineIds.has(u.id) && u.id !== state.user.id);
    
    const offlineHtml = offlineUsers
        .map(u => `<div class="user-item" onclick="startDm(${u.id})"><span class="user-dot offline"></span><span class="user-name">${u.nickname}</span></div>`)
        .join('');
    dom.offlineUsers.innerHTML = offlineHtml || '<div class="user-item"><span class="user-name" style="color: var(--text-dim)">—</span></div>';
    dom.offlineCount.textContent = `(${offlineUsers.length})`;
}

function renderMessages() {
    const messages = state.messages[state.currentConversation] || [];
    
    dom.messages.innerHTML = messages.map(m => createMessageHtml(m)).join('');
    scrollToBottom();
}

function createMessageHtml(msg) {
    const user = state.allUsers[msg.user_id];
    const nick = user ? user.nickname : `user_${msg.user_id}`;
    const isSelf = msg.user_id === state.user.id;
    const time = formatTime(msg.created_at);
    
    return `<div class="message">
        <span class="time">${time}</span>
        <span class="nick ${isSelf ? 'self' : ''}">&lt;${nick}&gt;</span>
        <span class="text">${escapeHtml(msg.content)}</span>
    </div>`;
}

function appendMessage(msg) {
    dom.messages.insertAdjacentHTML('beforeend', createMessageHtml(msg));
}

function addSystemMessage(text) {
    const time = formatTime(Math.floor(Date.now() / 1000));
    dom.messages.insertAdjacentHTML('beforeend', 
        `<div class="message system"><span class="time">${time}</span> ${escapeHtml(text)}</div>`
    );
    scrollToBottom();
}

function scrollToBottom() {
    dom.messages.scrollTop = dom.messages.scrollHeight;
}

// ============================================================
// ACTIONS
// ============================================================

function sendMessage() {
    const content = dom.messageInput.value.trim();
    if (!content) return;
    
    send('send_message', {
        conversation_id: state.currentConversation,
        content: content
    });
    
    dom.messageInput.value = '';
}

function switchConversation(convId) {
    state.currentConversation = convId;
    
    // Clear unread for this conversation
    if (state.unread[convId]) {
        delete state.unread[convId];
        updateUnreadTitle();
    }
    
    // Update header
    const conv = state.conversations.find(c => c.id === convId);
    if (conv) {
        if (conv.type === 'lobby') {
            dom.conversationName.textContent = '# Lobby';
        } else if (conv.type === 'dm') {
            const other = conv.members[0];
            dom.conversationName.textContent = '@ ' + (other ? other.nickname : 'Unknown');
        } else {
            dom.conversationName.textContent = '& ' + conv.members.map(m => m.nickname).join(', ');
        }
    }
    
    // Update sidebar active state
    document.querySelectorAll('.conversation-item').forEach(el => {
        el.classList.toggle('active', parseInt(el.dataset.id) === convId);
    });
    
    // Render messages and sidebar
    renderSidebar();
    renderMessages();
    
    // Load history if needed
    if (!state.messages[convId] || state.messages[convId].length === 0) {
        loadHistory(convId);
    }
    
    dom.messageInput.focus();
}

function loadHistory(convId) {
    const messages = state.messages[convId] || [];
    const oldestTime = messages.length > 0 ? messages[0].created_at : Math.floor(Date.now() / 1000);
    
    send('load_history', {
        conversation_id: convId,
        before: oldestTime,
        limit: CONFIG.historyLimit
    });
}

function loadMoreHistory() {
    if (!state.hasMore[state.currentConversation]) return;
    
    // Add loading indicator
    if (!dom.messages.querySelector('.loading-history')) {
        dom.messages.insertAdjacentHTML('afterbegin', '<div class="loading-history">Loading...</div>');
    }
    
    loadHistory(state.currentConversation);
}

function startDm(userId) {
    // Check if DM already exists
    const existingDm = state.conversations.find(c => 
        c.type === 'dm' && c.members.some(m => m.id === userId)
    );
    
    if (existingDm) {
        switchConversation(existingDm.id);
        return;
    }
    
    // Create new DM
    send('create_dm', { user_id: userId });
}

function openGroupModal() {
    // Get all known users except self
    const users = Object.values(state.allUsers).filter(u => u.id !== state.user.id);
    
    if (users.length < 1) {
        alert('No other users to create a group with');
        return;
    }
    
    // Render checkboxes
    dom.groupUserList.innerHTML = users.map(u => {
        const isOnline = state.onlineUsers.some(ou => ou.id === u.id);
        const dot = isOnline ? '●' : '○';
        return `
            <div class="group-user-option">
                <input type="checkbox" id="group-user-${u.id}" value="${u.id}">
                <label for="group-user-${u.id}">${dot} ${u.nickname}</label>
            </div>
        `;
    }).join('');
    
    dom.groupModal.style.display = 'flex';
}

// ============================================================
// UTILITIES
// ============================================================

function formatTime(timestamp) {
    const date = new Date(timestamp * 1000);
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');
    return `${hours}:${minutes}`;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Make switchConversation and startDm available globally for onclick
window.switchConversation = switchConversation;
window.startDm = startDm;

// ============================================================
// START
// ============================================================

init();

