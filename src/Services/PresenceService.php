<?php

declare(strict_types=1);

/**
 * In-memory presence tracking
 * 
 * Tracks which users are online and their connections.
 * Lives entirely in memory - no database needed.
 */
class PresenceService
{
    /** @var array<int, array> userId → user data */
    private array $onlineUsers = [];

    /** @var array<int, array<string>> userId → [connectionId, ...] */
    private array $userConnections = [];

    /** @var array<string, int> connectionId → userId */
    private array $connectionUsers = [];

    /**
     * Mark a user as online (called after successful auth)
     * 
     * @return bool True if this is the user's first connection (newly online)
     */
    public function userConnected(int $userId, array $user, string $connectionId): bool
    {
        $isNewlyOnline = !isset($this->onlineUsers[$userId]);

        // Store user data
        $this->onlineUsers[$userId] = $user;

        // Track connection
        if (!isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = [];
        }
        $this->userConnections[$userId][] = $connectionId;
        $this->connectionUsers[$connectionId] = $userId;

        return $isNewlyOnline;
    }

    /**
     * Handle a disconnection
     * 
     * @return bool True if user is now fully offline (no more connections)
     */
    public function connectionDisconnected(string $connectionId): bool
    {
        if (!isset($this->connectionUsers[$connectionId])) {
            return false;
        }

        $userId = $this->connectionUsers[$connectionId];
        unset($this->connectionUsers[$connectionId]);

        // Remove this connection from user's list
        if (isset($this->userConnections[$userId])) {
            $this->userConnections[$userId] = array_values(
                array_filter(
                    $this->userConnections[$userId],
                    fn($id) => $id !== $connectionId
                )
            );

            // If no more connections, user is offline
            if (empty($this->userConnections[$userId])) {
                unset($this->userConnections[$userId]);
                unset($this->onlineUsers[$userId]);
                return true;
            }
        }

        return false;
    }

    /**
     * Get user ID for a connection
     */
    public function getUserIdForConnection(string $connectionId): ?int
    {
        return $this->connectionUsers[$connectionId] ?? null;
    }

    /**
     * Check if a user is online
     */
    public function isOnline(int $userId): bool
    {
        return isset($this->onlineUsers[$userId]);
    }

    /**
     * Get all online users
     * 
     * @return array<array> List of user objects
     */
    public function getOnlineUsers(): array
    {
        return array_values($this->onlineUsers);
    }

    /**
     * Get online user IDs
     * 
     * @return array<int>
     */
    public function getOnlineUserIds(): array
    {
        return array_keys($this->onlineUsers);
    }

    /**
     * Get connection IDs for a user
     * 
     * @return array<string>
     */
    public function getConnectionsForUser(int $userId): array
    {
        return $this->userConnections[$userId] ?? [];
    }

    /**
     * Get total connection count
     */
    public function getConnectionCount(): int
    {
        return count($this->connectionUsers);
    }

    /**
     * Get online user count
     */
    public function getOnlineUserCount(): int
    {
        return count($this->onlineUsers);
    }
}

