<?php

declare(strict_types=1);

/**
 * User management
 */
class UserService
{
    private const NICKNAME_MIN_LENGTH = 2;
    private const NICKNAME_MAX_LENGTH = 20;
    private const NICKNAME_PATTERN = '/^[a-zA-Z0-9_-]+$/';
    private const PASSWORD_MIN_LENGTH = 4;

    /**
     * Create a new user with password
     * @param string $nickname The user's chosen nickname
     * @param string $password The user's password
     * @return array User data including token
     * @throws InvalidArgumentException If nickname or password is invalid
     */
    public static function create(string $nickname, string $password): array
    {
        // Validate nickname
        $error = self::validateNickname($nickname);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        // Validate password
        $error = self::validatePassword($password);
        if ($error !== null) {
            throw new InvalidArgumentException($error);
        }

        // Hash password
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        
        // Generate token for WebSocket auth
        $token = Auth::generateToken();
        $now = time();

        // Insert user
        Database::execute(
            'INSERT INTO users (nickname, password_hash, token, created_at) VALUES (?, ?, ?, ?)',
            [$nickname, $passwordHash, $token, $now]
        );

        $userId = Database::lastInsertId();

        return [
            'id' => $userId,
            'nickname' => $nickname,
            'token' => $token,
            'created_at' => $now
        ];
    }

    /**
     * Authenticate user with nickname and password
     * @return array|null User data with token, or null if invalid
     */
    public static function authenticate(string $nickname, string $password): ?array
    {
        $user = Database::queryOne(
            'SELECT id, nickname, password_hash, token, created_at FROM users WHERE LOWER(nickname) = LOWER(?)',
            [$nickname]
        );

        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Return user data (without password hash)
        return [
            'id' => (int) $user['id'],
            'nickname' => $user['nickname'],
            'token' => $user['token'],
            'created_at' => (int) $user['created_at']
        ];
    }

    /**
     * Get user by ID (without sensitive data)
     */
    public static function getById(int $id): ?array
    {
        return Database::queryOne(
            'SELECT id, nickname, created_at FROM users WHERE id = ?',
            [$id]
        );
    }

    /**
     * Get user by token (without exposing token or password)
     */
    public static function getByToken(string $token): ?array
    {
        return Database::queryOne(
            'SELECT id, nickname, created_at FROM users WHERE token = ?',
            [$token]
        );
    }

    /**
     * Get user by nickname
     */
    public static function getByNickname(string $nickname): ?array
    {
        return Database::queryOne(
            'SELECT id, nickname, created_at FROM users WHERE LOWER(nickname) = LOWER(?)',
            [$nickname]
        );
    }

    /**
     * Check if a nickname already exists
     */
    public static function nicknameExists(string $nickname): bool
    {
        $result = Database::queryOne(
            'SELECT 1 FROM users WHERE LOWER(nickname) = LOWER(?)',
            [$nickname]
        );
        return $result !== null;
    }

    /**
     * Validate a nickname
     * @return string|null Error message or null if valid
     */
    public static function validateNickname(string $nickname): ?string
    {
        $nickname = trim($nickname);

        if (strlen($nickname) < self::NICKNAME_MIN_LENGTH) {
            return 'Nickname must be at least ' . self::NICKNAME_MIN_LENGTH . ' characters';
        }

        if (strlen($nickname) > self::NICKNAME_MAX_LENGTH) {
            return 'Nickname must be at most ' . self::NICKNAME_MAX_LENGTH . ' characters';
        }

        if (!preg_match(self::NICKNAME_PATTERN, $nickname)) {
            return 'Nickname can only contain letters, numbers, underscores, and hyphens';
        }

        if (self::nicknameExists($nickname)) {
            return 'Nickname is already taken';
        }

        return null;
    }

    /**
     * Validate a password
     * @return string|null Error message or null if valid
     */
    public static function validatePassword(string $password): ?string
    {
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return 'Password must be at least ' . self::PASSWORD_MIN_LENGTH . ' characters';
        }

        return null;
    }

    /**
     * Get all users (for presence list)
     */
    public static function getAll(): array
    {
        return Database::query(
            'SELECT id, nickname, created_at FROM users ORDER BY nickname'
        );
    }
}
