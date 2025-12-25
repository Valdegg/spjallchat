<?php

/**
 * Spjall Server Entry Point
 * 
 * This is a template showing how to integrate SpjallServer with your WebSocket implementation.
 * Your friend should adapt this to work with his specific WebSocket server.
 * 
 * Run with: php server.php
 */

declare(strict_types=1);

// ============================================================
// LOAD DEPENDENCIES
// ============================================================

require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/UserService.php';
require_once __DIR__ . '/src/InviteService.php';
require_once __DIR__ . '/src/Connection.php';
require_once __DIR__ . '/src/Services/PresenceService.php';
require_once __DIR__ . '/src/SpjallServer.php';

// ============================================================
// INITIALIZE
// ============================================================

// Initialize database
Database::init(__DIR__ . '/data/spjallchat.db');

// Create the Spjall server instance
$spjall = new SpjallServer();

echo "=== Spjall Chat Server ===\n";
echo "SpjallServer instance created.\n";
echo "\n";

// ============================================================
// INTEGRATION EXAMPLE
// 
// Your friend's WebSocket server should call these methods:
// ============================================================

/*

// When a new WebSocket connection is established:
$connectionId = uniqid('conn_');  // Generate unique ID
$spjall->onConnect($connectionId, $websocketConnection);

// When a message is received from a client:
$spjall->onMessage($connectionId, $jsonMessageString);

// When a connection is closed:
$spjall->onDisconnect($connectionId);

*/

// ============================================================
// EXAMPLE: Simulating the flow (for testing without WebSocket)
// ============================================================

echo "--- Simulation Mode ---\n";
echo "This demonstrates the message flow without a real WebSocket.\n\n";

// Create a mock socket that just prints messages
class MockSocket {
    public string $name;
    
    public function __construct(string $name) {
        $this->name = $name;
    }
    
    public function send(string $data): void {
        $decoded = json_decode($data, true);
        $type = $decoded['type'] ?? 'unknown';
        echo "[{$this->name}] ← $type: " . substr($data, 0, 100) . (strlen($data) > 100 ? '...' : '') . "\n";
    }
}

// Check if we have any users to test with
$users = Database::query('SELECT id, nickname, token FROM users LIMIT 1');

if (empty($users)) {
    echo "No users found. Create one first:\n";
    echo "  1. Run: ./setup.sh\n";
    echo "  2. Visit: http://localhost:8080/join/WELCOME1\n";
    echo "  3. Enter a nickname\n";
    echo "  4. Run this script again\n";
    exit(1);
}

$testUser = $users[0];
echo "Found test user: {$testUser['nickname']} (ID: {$testUser['id']})\n\n";

// Simulate connection
$connId = 'test_conn_1';
$mockSocket = new MockSocket('User1');
$spjall->onConnect($connId, $mockSocket);
echo "[Server] Connection established: $connId\n";

// Simulate auth
echo "[User1] → auth\n";
$spjall->onMessage($connId, json_encode([
    'type' => 'auth',
    'payload' => ['token' => $testUser['token']]
]));

echo "\n";

// Simulate ping
echo "[User1] → ping\n";
$spjall->onMessage($connId, json_encode([
    'type' => 'ping',
    'payload' => []
]));

echo "\n";

// Simulate sending a message to lobby
echo "[User1] → send_message (to lobby)\n";
$spjall->onMessage($connId, json_encode([
    'type' => 'send_message',
    'payload' => [
        'conversation_id' => 1,  // Lobby is always ID 1
        'content' => 'Hello from the test script!'
    ]
]));

echo "\n";

// Simulate loading history
echo "[User1] → load_history\n";
$spjall->onMessage($connId, json_encode([
    'type' => 'load_history',
    'payload' => [
        'conversation_id' => 1,
        'limit' => 10
    ]
]));

echo "\n";

// Simulate creating an invite
echo "[User1] → create_invite\n";
$spjall->onMessage($connId, json_encode([
    'type' => 'create_invite',
    'payload' => []
]));

echo "\n";

// Stats
$stats = $spjall->getStats();
echo "--- Server Stats ---\n";
echo "Connections: {$stats['connections']}\n";
echo "Online users: {$stats['online_users']}\n";

echo "\n";

// Simulate disconnect
$spjall->onDisconnect($connId);
echo "[Server] Connection closed: $connId\n";

$stats = $spjall->getStats();
echo "Connections after disconnect: {$stats['connections']}\n";
echo "Online users after disconnect: {$stats['online_users']}\n";

echo "\n=== Simulation Complete ===\n";

