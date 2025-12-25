<?php

declare(strict_types=1);

/**
 * Wrapper for a WebSocket connection
 * 
 * Your friend's server will create these and pass them to SpjallServer.
 * Adjust the send() method to match his WebSocket implementation.
 */
class Connection
{
    private string $id;
    private mixed $socket;  // The actual WebSocket connection object
    private ?int $userId = null;
    private ?array $user = null;
    private int $connectedAt;
    private int $lastActivity;

    public function __construct(string $id, mixed $socket)
    {
        $this->id = $id;
        $this->socket = $socket;
        $this->connectedAt = time();
        $this->lastActivity = time();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSocket(): mixed
    {
        return $this->socket;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }

    /**
     * Associate this connection with a user (after auth)
     */
    public function setUser(array $user): void
    {
        $this->userId = (int) $user['id'];
        $this->user = $user;
    }

    /**
     * Update last activity timestamp
     */
    public function touch(): void
    {
        $this->lastActivity = time();
    }

    public function getLastActivity(): int
    {
        return $this->lastActivity;
    }

    public function getConnectedAt(): int
    {
        return $this->connectedAt;
    }

    /**
     * Send a message to this connection
     */
    public function send(array $message): void
    {
        $json = json_encode($message);
        
        // If socket has a send method (our SocketWrapper)
        if (method_exists($this->socket, 'send')) {
            $this->socket->send($json);
            return;
        }
        
        // If it's a resource (raw socket)
        if (is_resource($this->socket)) {
            fwrite($this->socket, $json);
            return;
        }
        
        // Fallback: log error
        error_log("Connection::send() - Unknown socket type, cannot send message");
    }

    /**
     * Send a typed event
     */
    public function sendEvent(string $type, array $payload = []): void
    {
        $this->send([
            'type' => $type,
            'payload' => $payload
        ]);
    }

    /**
     * Send an error
     */
    public function sendError(string $message, string $code = 'ERROR'): void
    {
        $this->sendEvent('error', [
            'message' => $message,
            'code' => $code
        ]);
    }
}

