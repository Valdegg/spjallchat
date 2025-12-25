<?php

/**
 * Simple PHP WebSocket Server
 * 
 * Run with: php websocket_server.php
 * Connects to SpjallServer for business logic
 */

declare(strict_types=1);

error_reporting(E_ALL);
set_time_limit(0);

// Load Spjall components
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';
require_once __DIR__ . '/src/UserService.php';
require_once __DIR__ . '/src/InviteService.php';
require_once __DIR__ . '/src/Connection.php';
require_once __DIR__ . '/src/Services/PresenceService.php';
require_once __DIR__ . '/src/SpjallServer.php';

// Configuration
$host = '0.0.0.0';
$port = 8081;

// Initialize database
Database::init(__DIR__ . '/data/spjallchat.db');

// Create Spjall server
$spjall = new SpjallServer();

// Track socket connections
$clients = [];

// Create WebSocket server socket
$server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($server, $host, $port);
socket_listen($server);
socket_set_nonblock($server);

echo "=== Spjall WebSocket Server ===\n";
echo "Listening on ws://$host:$port\n";
echo "Press Ctrl+C to stop\n\n";

// Main loop
while (true) {
    // Check for new connections
    $newClient = @socket_accept($server);
    if ($newClient) {
        socket_set_nonblock($newClient);
        $clientId = uniqid('ws_');
        $clients[$clientId] = [
            'socket' => $newClient,
            'handshake' => false,
            'buffer' => '',
            'wrapper' => null
        ];
        echo "[+] New connection: $clientId\n";
    }
    
    // Process existing clients
    foreach ($clients as $clientId => &$client) {
        $socket = $client['socket'];
        
        // Try to read data
        $data = @socket_read($socket, 8192);
        
        if ($data === false) {
            $error = socket_last_error($socket);
            socket_clear_error($socket);
            if ($error !== 11 && $error !== 35 && $error !== 0) { // EAGAIN/EWOULDBLOCK
                // Real error - disconnect
                disconnectClient($clientId, $clients, $spjall);
                continue;
            }
        } elseif ($data === '') {
            // Client disconnected cleanly
            disconnectClient($clientId, $clients, $spjall);
            continue;
        } elseif ($data !== null && strlen($data) > 0) {
            if (!$client['handshake']) {
                // Buffer data for handshake (might come in chunks)
                $client['buffer'] .= $data;
                
                // Check if we have complete HTTP headers (ends with \r\n\r\n)
                if (strpos($client['buffer'], "\r\n\r\n") !== false) {
                    $result = performHandshake($socket, $client['buffer']);
                    
                    if ($result === 'websocket') {
                        $client['handshake'] = true;
                        $client['buffer'] = '';
                        
                        // Create wrapper and connect to Spjall
                        $wrapper = new SocketWrapper($socket, $clientId);
                        $client['wrapper'] = $wrapper;
                        $spjall->onConnect($clientId, $wrapper);
                        
                        echo "[*] Handshake complete: $clientId\n";
                    } elseif ($result === 'http') {
                        // Regular HTTP request (health check, etc) - close gracefully
                        echo "[~] HTTP request (not WebSocket): $clientId\n";
                        @socket_close($socket);
                        unset($clients[$clientId]);
                    } else {
                        // Invalid request
                        echo "[!] Invalid handshake: $clientId\n";
                        disconnectClient($clientId, $clients, $spjall);
                    }
                }
            } else {
                // Decode WebSocket frame
                $decoded = decodeFrame($data);
                
                if ($decoded === null) {
                    // Could be partial frame - skip for now
                    continue;
                }
                
                if ($decoded['opcode'] === 8) {
                    // Close frame
                    disconnectClient($clientId, $clients, $spjall);
                    continue;
                }
                
                if ($decoded['opcode'] === 9) {
                    // Ping - send pong
                    $pong = encodeFrame($decoded['payload'], 10);
                    @socket_write($socket, $pong);
                    continue;
                }
                
                if ($decoded['opcode'] === 1) {
                    // Text frame - pass to Spjall
                    $message = $decoded['payload'];
                    echo "[<] $clientId: $message\n";
                    $spjall->onMessage($clientId, $message);
                }
            }
        }
    }
    unset($client); // Important: break reference
    
    // Small delay to prevent CPU spin
    usleep(10000); // 10ms
}

// Cleanup
socket_close($server);

// ============================================================
// HELPER FUNCTIONS
// ============================================================

function disconnectClient(string $clientId, array &$clients, SpjallServer $spjall): void
{
    if (!isset($clients[$clientId])) return;
    
    echo "[-] Disconnected: $clientId\n";
    
    if ($clients[$clientId]['handshake']) {
        $spjall->onDisconnect($clientId);
    }
    @socket_close($clients[$clientId]['socket']);
    unset($clients[$clientId]);
}

/**
 * Perform WebSocket handshake
 * @return string 'websocket' if upgrade successful, 'http' if regular HTTP, 'error' if invalid
 */
function performHandshake($socket, string $request): string
{
    // Check if this is a WebSocket upgrade request
    if (stripos($request, 'Upgrade: websocket') === false) {
        // Not a WebSocket request - might be health check
        // Send a simple HTTP response
        $response = "HTTP/1.1 200 OK\r\n" .
                    "Content-Type: text/plain\r\n" .
                    "Content-Length: 2\r\n" .
                    "Connection: close\r\n\r\nOK";
        @socket_write($socket, $response);
        return 'http';
    }
    
    // Parse WebSocket key
    if (!preg_match('/Sec-WebSocket-Key:\s*(.+?)\r\n/i', $request, $matches)) {
        return 'error';
    }
    
    $key = trim($matches[1]);
    $acceptKey = base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
    
    $response = "HTTP/1.1 101 Switching Protocols\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: $acceptKey\r\n\r\n";
    
    $written = @socket_write($socket, $response);
    return $written !== false ? 'websocket' : 'error';
}

function decodeFrame(string $data): ?array
{
    if (strlen($data) < 2) return null;
    
    $firstByte = ord($data[0]);
    $secondByte = ord($data[1]);
    
    $opcode = $firstByte & 0x0F;
    $masked = ($secondByte & 0x80) !== 0;
    $payloadLen = $secondByte & 0x7F;
    
    $offset = 2;
    
    if ($payloadLen === 126) {
        if (strlen($data) < 4) return null;
        $payloadLen = unpack('n', substr($data, 2, 2))[1];
        $offset = 4;
    } elseif ($payloadLen === 127) {
        if (strlen($data) < 10) return null;
        $payloadLen = unpack('J', substr($data, 2, 8))[1];
        $offset = 10;
    }
    
    if ($masked) {
        if (strlen($data) < $offset + 4) return null;
        $mask = substr($data, $offset, 4);
        $offset += 4;
    }
    
    if (strlen($data) < $offset + $payloadLen) return null;
    
    $payload = substr($data, $offset, $payloadLen);
    
    if ($masked) {
        for ($i = 0; $i < strlen($payload); $i++) {
            $payload[$i] = chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
    }
    
    return [
        'opcode' => $opcode,
        'payload' => $payload
    ];
}

function encodeFrame(string $payload, int $opcode = 1): string
{
    $len = strlen($payload);
    $frame = chr(0x80 | $opcode); // FIN + opcode
    
    if ($len < 126) {
        $frame .= chr($len);
    } elseif ($len < 65536) {
        $frame .= chr(126) . pack('n', $len);
    } else {
        $frame .= chr(127) . pack('J', $len);
    }
    
    return $frame . $payload;
}

// ============================================================
// SOCKET WRAPPER CLASS
// ============================================================

/**
 * Wrapper to make raw socket work with our Connection class
 */
class SocketWrapper
{
    private $socket;
    private string $id;
    
    public function __construct($socket, string $id)
    {
        $this->socket = $socket;
        $this->id = $id;
    }
    
    public function send(string $data): void
    {
        $frame = encodeFrame($data);
        $result = @socket_write($this->socket, $frame);
        if ($result !== false) {
            echo "[>] {$this->id}: " . substr($data, 0, 80) . (strlen($data) > 80 ? '...' : '') . "\n";
        }
    }
}
