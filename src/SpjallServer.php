<?php

declare(strict_types=1);

/**
 * Main Spjall Server Class
 * 
 * This is the central class that runs persistently and manages:
 * - All WebSocket connections
 * - Online users (in memory)
 * - Message routing to handlers
 * 
 * Your friend's WebSocket server should create an instance of this
 * and call the appropriate methods on connection events.
 */
class SpjallServer
{
    /** @var array<string, Connection> connectionId → Connection */
    private array $connections = [];

    private PresenceService $presence;

    public function __construct()
    {
        $this->presence = new PresenceService();
    }

    /**
     * Called when a new WebSocket connection is established
     * 
     * @param string $connectionId Unique ID for this connection
     * @param mixed $socket The raw WebSocket connection object
     */
    public function onConnect(string $connectionId, mixed $socket): void
    {
        $conn = new Connection($connectionId, $socket);
        $this->connections[$connectionId] = $conn;
        
        // Connection is not authenticated yet - client must send 'auth' event
        // We could start a timeout here to disconnect if no auth within N seconds
    }

    /**
     * Called when a WebSocket connection is closed
     */
    public function onDisconnect(string $connectionId): void
    {
        $conn = $this->connections[$connectionId] ?? null;
        
        if ($conn === null) {
            return;
        }

        // Handle presence update if user was authenticated
        if ($conn->isAuthenticated()) {
            $userId = $conn->getUserId();
            $wentOffline = $this->presence->connectionDisconnected($connectionId);
            
            if ($wentOffline) {
                // Broadcast user_offline to all authenticated connections
                $this->broadcast('user_offline', ['user_id' => $userId]);
            }
        }

        unset($this->connections[$connectionId]);
    }

    /**
     * Called when a message is received from a client
     * 
     * @param string $connectionId The connection that sent the message
     * @param string $data Raw JSON message data
     */
    public function onMessage(string $connectionId, string $data): void
    {
        $conn = $this->connections[$connectionId] ?? null;
        
        if ($conn === null) {
            return;
        }

        $conn->touch();

        // Parse JSON
        $message = json_decode($data, true);
        
        if (!is_array($message) || !isset($message['type'])) {
            $conn->sendError('Invalid message format', 'INVALID_REQUEST');
            return;
        }

        $type = $message['type'];
        $payload = $message['payload'] ?? [];

        // Route to handler
        $this->handleEvent($conn, $type, $payload);
    }

    /**
     * Route an event to the appropriate handler
     */
    private function handleEvent(Connection $conn, string $type, array $payload): void
    {
        // Auth is required for most events
        if ($type !== 'auth' && !$conn->isAuthenticated()) {
            $conn->sendError('Not authenticated', 'NOT_AUTHENTICATED');
            return;
        }

        switch ($type) {
            case 'auth':
                $this->handleAuth($conn, $payload);
                break;
                
            case 'ping':
                $conn->sendEvent('pong');
                break;
                
            case 'send_message':
                $this->handleSendMessage($conn, $payload);
                break;
                
            case 'create_dm':
                $this->handleCreateDm($conn, $payload);
                break;
                
            case 'create_group':
                $this->handleCreateGroup($conn, $payload);
                break;
                
            case 'load_history':
                $this->handleLoadHistory($conn, $payload);
                break;
                
            case 'create_invite':
                $this->handleCreateInvite($conn, $payload);
                break;
                
            default:
                $conn->sendError("Unknown event type: $type", 'UNKNOWN_EVENT');
        }
    }

    /**
     * Handle authentication
     */
    private function handleAuth(Connection $conn, array $payload): void
    {
        $token = $payload['token'] ?? '';

        if (empty($token)) {
            $conn->sendEvent('auth_error', ['message' => 'Token is required']);
            return;
        }

        $user = Auth::validateToken($token);

        if ($user === null) {
            $conn->sendEvent('auth_error', ['message' => 'Invalid token']);
            return;
        }

        // Associate user with connection
        $conn->setUser($user);

        // Update presence
        $isNewlyOnline = $this->presence->userConnected(
            $user['id'],
            ['id' => $user['id'], 'nickname' => $user['nickname']],
            $conn->getId()
        );

        // Get user's conversations
        $conversations = $this->getUserConversations($user['id']);

        // Get online users
        $onlineUsers = $this->presence->getOnlineUsers();

        // Send auth success
        $conn->sendEvent('auth_ok', [
            'user' => [
                'id' => $user['id'],
                'nickname' => $user['nickname']
            ],
            'conversations' => $conversations,
            'online_users' => $onlineUsers
        ]);

        // Broadcast user_online if they just came online
        if ($isNewlyOnline) {
            $this->broadcastExcept('user_online', [
                'user' => ['id' => $user['id'], 'nickname' => $user['nickname']]
            ], $conn->getId());
        }
    }

    /**
     * Handle sending a message
     */
    private function handleSendMessage(Connection $conn, array $payload): void
    {
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $content = trim($payload['content'] ?? '');

        // Validate
        if ($conversationId <= 0) {
            $conn->sendError('Invalid conversation', 'INVALID_CONVERSATION');
            return;
        }

        if (empty($content)) {
            $conn->sendError('Message cannot be empty', 'EMPTY_CONTENT');
            return;
        }

        if (strlen($content) > 4000) {
            $conn->sendError('Message too long (max 4000 chars)', 'CONTENT_TOO_LONG');
            return;
        }

        $userId = $conn->getUserId();

        // Check membership (lobby is open to all, others need membership check)
        if (!$this->canAccessConversation($userId, $conversationId)) {
            $conn->sendError('Not a member of this conversation', 'NOT_MEMBER');
            return;
        }

        // Save to database
        $now = time();
        Database::execute(
            'INSERT INTO messages (conversation_id, user_id, content, created_at) VALUES (?, ?, ?, ?)',
            [$conversationId, $userId, $content, $now]
        );
        $messageId = Database::lastInsertId();

        // Build message object
        $message = [
            'id' => $messageId,
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'content' => $content,
            'created_at' => $now
        ];

        // Broadcast to conversation participants
        $this->broadcastToConversation($conversationId, 'message', $message);
    }

    /**
     * Handle creating a DM
     */
    private function handleCreateDm(Connection $conn, array $payload): void
    {
        $targetUserId = (int) ($payload['user_id'] ?? 0);
        $userId = $conn->getUserId();

        if ($targetUserId <= 0) {
            $conn->sendError('Invalid user', 'INVALID_USER');
            return;
        }

        if ($targetUserId === $userId) {
            $conn->sendError('Cannot create DM with yourself', 'INVALID_USER');
            return;
        }

        // Check if target user exists
        $targetUser = UserService::getById($targetUserId);
        if ($targetUser === null) {
            $conn->sendError('User not found', 'INVALID_USER');
            return;
        }

        // Check if DM already exists
        $existingDm = $this->findExistingDm($userId, $targetUserId);
        if ($existingDm !== null) {
            // Return existing conversation
            $conn->sendEvent('conversation_created', [
                'conversation' => $existingDm
            ]);
            return;
        }

        // Create new DM conversation
        $now = time();
        Database::execute(
            "INSERT INTO conversations (type, created_at) VALUES ('dm', ?)",
            [$now]
        );
        $convId = Database::lastInsertId();

        // Add both users as members
        Database::execute(
            'INSERT INTO conversation_members (conversation_id, user_id, joined_at) VALUES (?, ?, ?)',
            [$convId, $userId, $now]
        );
        Database::execute(
            'INSERT INTO conversation_members (conversation_id, user_id, joined_at) VALUES (?, ?, ?)',
            [$convId, $targetUserId, $now]
        );

        // Build conversation object
        $currentUser = $conn->getUser();
        $conversation = [
            'id' => $convId,
            'type' => 'dm',
            'members' => [
                ['id' => $targetUser['id'], 'nickname' => $targetUser['nickname']]
            ]
        ];
        
        $conversationForTarget = [
            'id' => $convId,
            'type' => 'dm',
            'members' => [
                ['id' => $currentUser['id'], 'nickname' => $currentUser['nickname']]
            ]
        ];

        // Notify both users
        $conn->sendEvent('conversation_created', ['conversation' => $conversation]);
        $this->sendToUser($targetUserId, 'conversation_created', ['conversation' => $conversationForTarget]);
    }

    /**
     * Handle creating a group
     */
    private function handleCreateGroup(Connection $conn, array $payload): void
    {
        $userIds = $payload['user_ids'] ?? [];
        $userId = $conn->getUserId();

        if (!is_array($userIds) || count($userIds) < 2) {
            $conn->sendError('Group requires at least 2 other users', 'INVALID_REQUEST');
            return;
        }

        // Add creator to the list
        $allUserIds = array_unique(array_merge([$userId], array_map('intval', $userIds)));

        if (count($allUserIds) < 3) {
            $conn->sendError('Group requires at least 3 total members', 'INVALID_REQUEST');
            return;
        }

        // Verify all users exist
        $members = [];
        foreach ($allUserIds as $uid) {
            $user = UserService::getById($uid);
            if ($user === null) {
                $conn->sendError("User $uid not found", 'INVALID_USER');
                return;
            }
            $members[$uid] = $user;
        }

        // Create group conversation
        $now = time();
        Database::execute(
            "INSERT INTO conversations (type, created_at) VALUES ('group', ?)",
            [$now]
        );
        $convId = Database::lastInsertId();

        // Add all users as members
        foreach ($allUserIds as $uid) {
            Database::execute(
                'INSERT INTO conversation_members (conversation_id, user_id, joined_at) VALUES (?, ?, ?)',
                [$convId, $uid, $now]
            );
        }

        // Notify all members
        foreach ($allUserIds as $uid) {
            $otherMembers = array_filter($members, fn($m) => $m['id'] !== $uid);
            $conversation = [
                'id' => $convId,
                'type' => 'group',
                'members' => array_values(array_map(fn($m) => [
                    'id' => $m['id'],
                    'nickname' => $m['nickname']
                ], $otherMembers))
            ];
            
            $this->sendToUser($uid, 'conversation_created', ['conversation' => $conversation]);
        }
    }

    /**
     * Handle loading message history
     */
    private function handleLoadHistory(Connection $conn, array $payload): void
    {
        $conversationId = (int) ($payload['conversation_id'] ?? 0);
        $before = (int) ($payload['before'] ?? time());
        $limit = min(100, max(1, (int) ($payload['limit'] ?? 50)));

        $userId = $conn->getUserId();

        if (!$this->canAccessConversation($userId, $conversationId)) {
            $conn->sendError('Not a member of this conversation', 'NOT_MEMBER');
            return;
        }

        // Load messages
        $messages = Database::query(
            'SELECT id, conversation_id, user_id, content, created_at 
             FROM messages 
             WHERE conversation_id = ? AND created_at < ?
             ORDER BY created_at DESC
             LIMIT ?',
            [$conversationId, $before, $limit + 1]
        );

        $hasMore = count($messages) > $limit;
        if ($hasMore) {
            array_pop($messages);
        }

        // Reverse to chronological order
        $messages = array_reverse($messages);

        // Convert types
        $messages = array_map(fn($m) => [
            'id' => (int) $m['id'],
            'conversation_id' => (int) $m['conversation_id'],
            'user_id' => (int) $m['user_id'],
            'content' => $m['content'],
            'created_at' => (int) $m['created_at']
        ], $messages);

        $conn->sendEvent('history', [
            'conversation_id' => $conversationId,
            'messages' => $messages,
            'has_more' => $hasMore
        ]);
    }

    /**
     * Handle creating an invite
     */
    private function handleCreateInvite(Connection $conn, array $payload): void
    {
        $userId = $conn->getUserId();
        
        $code = InviteService::create($userId);
        $url = InviteService::getUrl($code);

        $conn->sendEvent('invite_created', [
            'code' => $code,
            'url' => $url
        ]);
    }

    // ========== Helper Methods ==========

    /**
     * Check if a user can access a conversation
     */
    private function canAccessConversation(int $userId, int $conversationId): bool
    {
        // Get conversation type
        $conv = Database::queryOne(
            'SELECT type FROM conversations WHERE id = ?',
            [$conversationId]
        );

        if ($conv === null) {
            return false;
        }

        // Lobby is accessible to all
        if ($conv['type'] === 'lobby') {
            return true;
        }

        // Check membership for DMs and groups
        $member = Database::queryOne(
            'SELECT 1 FROM conversation_members WHERE conversation_id = ? AND user_id = ?',
            [$conversationId, $userId]
        );

        return $member !== null;
    }

    /**
     * Find existing DM between two users
     */
    private function findExistingDm(int $userId1, int $userId2): ?array
    {
        $dm = Database::queryOne(
            "SELECT c.id FROM conversations c
             INNER JOIN conversation_members cm1 ON c.id = cm1.conversation_id AND cm1.user_id = ?
             INNER JOIN conversation_members cm2 ON c.id = cm2.conversation_id AND cm2.user_id = ?
             WHERE c.type = 'dm'",
            [$userId1, $userId2]
        );

        if ($dm === null) {
            return null;
        }

        $otherUser = UserService::getById($userId2);
        return [
            'id' => (int) $dm['id'],
            'type' => 'dm',
            'members' => [
                ['id' => $otherUser['id'], 'nickname' => $otherUser['nickname']]
            ]
        ];
    }

    /**
     * Get all conversations for a user
     */
    private function getUserConversations(int $userId): array
    {
        $conversations = [];

        // Always include lobby
        $lobby = Database::queryOne("SELECT id FROM conversations WHERE type = 'lobby' LIMIT 1");
        if ($lobby) {
            $conversations[] = [
                'id' => (int) $lobby['id'],
                'type' => 'lobby',
                'members' => []
            ];
        }

        // Get user's DMs and groups
        $userConvs = Database::query(
            "SELECT c.id, c.type FROM conversations c
             INNER JOIN conversation_members cm ON c.id = cm.conversation_id
             WHERE cm.user_id = ? AND c.type != 'lobby'
             ORDER BY c.created_at DESC",
            [$userId]
        );

        foreach ($userConvs as $conv) {
            $members = Database::query(
                "SELECT u.id, u.nickname FROM users u
                 INNER JOIN conversation_members cm ON u.id = cm.user_id
                 WHERE cm.conversation_id = ? AND u.id != ?",
                [$conv['id'], $userId]
            );

            $conversations[] = [
                'id' => (int) $conv['id'],
                'type' => $conv['type'],
                'members' => array_map(fn($m) => [
                    'id' => (int) $m['id'],
                    'nickname' => $m['nickname']
                ], $members)
            ];
        }

        return $conversations;
    }

    /**
     * Broadcast to all authenticated connections
     */
    private function broadcast(string $type, array $payload): void
    {
        foreach ($this->connections as $conn) {
            if ($conn->isAuthenticated()) {
                $conn->sendEvent($type, $payload);
            }
        }
    }

    /**
     * Broadcast to all authenticated connections except one
     */
    private function broadcastExcept(string $type, array $payload, string $excludeConnectionId): void
    {
        foreach ($this->connections as $conn) {
            if ($conn->isAuthenticated() && $conn->getId() !== $excludeConnectionId) {
                $conn->sendEvent($type, $payload);
            }
        }
    }

    /**
     * Broadcast to all participants of a conversation
     */
    private function broadcastToConversation(int $conversationId, string $type, array $payload): void
    {
        // Get conversation type
        $conv = Database::queryOne('SELECT type FROM conversations WHERE id = ?', [$conversationId]);
        
        if ($conv === null) {
            return;
        }

        if ($conv['type'] === 'lobby') {
            // Lobby: send to all authenticated
            $this->broadcast($type, $payload);
            return;
        }

        // DM/Group: send to members who are online
        $members = Database::query(
            'SELECT user_id FROM conversation_members WHERE conversation_id = ?',
            [$conversationId]
        );

        foreach ($members as $member) {
            $this->sendToUser((int) $member['user_id'], $type, $payload);
        }
    }

    /**
     * Send to all connections of a specific user
     */
    private function sendToUser(int $userId, string $type, array $payload): void
    {
        $connectionIds = $this->presence->getConnectionsForUser($userId);
        
        foreach ($connectionIds as $connId) {
            if (isset($this->connections[$connId])) {
                $this->connections[$connId]->sendEvent($type, $payload);
            }
        }
    }

    // ========== Stats ==========

    public function getStats(): array
    {
        return [
            'connections' => count($this->connections),
            'online_users' => $this->presence->getOnlineUserCount()
        ];
    }
}

