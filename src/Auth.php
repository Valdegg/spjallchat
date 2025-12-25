<?php

declare(strict_types=1);

/**
 * Authentication utilities - token generation and validation
 */
class Auth
{
    /**
     * Generate a secure random token
     * Returns 64 character hex string (32 bytes)
     */
    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validate a token and return the associated user
     * Returns user array or null if invalid
     */
    public static function validateToken(string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        $user = Database::queryOne(
            'SELECT id, nickname, created_at FROM users WHERE token = ?',
            [$token]
        );

        return $user;
    }

    /**
     * Get user by ID
     */
    public static function getUserById(int $id): ?array
    {
        return Database::queryOne(
            'SELECT id, nickname, created_at FROM users WHERE id = ?',
            [$id]
        );
    }
}

