<?php

declare(strict_types=1);

/**
 * Invite code management
 */
class InviteService
{
    private const CODE_LENGTH = 8;
    private const CODE_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // No I, O, 0, 1 for clarity

    /**
     * Generate a new invite code
     * @param int|null $createdBy User ID of creator (null for seed invites)
     * @return string The invite code
     */
    public static function create(?int $createdBy): string
    {
        $code = self::generateCode();
        
        // Ensure uniqueness (regenerate if collision)
        while (self::exists($code)) {
            $code = self::generateCode();
        }

        Database::execute(
            'INSERT INTO invites (code, created_by, created_at) VALUES (?, ?, ?)',
            [$code, $createdBy, time()]
        );

        return $code;
    }

    /**
     * Check if an invite code exists (used or not)
     */
    public static function exists(string $code): bool
    {
        $result = Database::queryOne(
            'SELECT 1 FROM invites WHERE code = ?',
            [strtoupper($code)]
        );
        return $result !== null;
    }

    /**
     * Validate an invite code
     * Returns invite data if valid and unused, null otherwise
     */
    public static function validate(string $code): ?array
    {
        $invite = Database::queryOne(
            'SELECT id, code, created_by, used_by, created_at, used_at FROM invites WHERE code = ?',
            [strtoupper($code)]
        );

        if ($invite === null) {
            return null; // Code doesn't exist
        }

        if ($invite['used_by'] !== null) {
            return null; // Already used
        }

        return $invite;
    }

    /**
     * Check if an invite has been used
     */
    public static function isUsed(string $code): bool
    {
        $invite = Database::queryOne(
            'SELECT used_by FROM invites WHERE code = ?',
            [strtoupper($code)]
        );

        if ($invite === null) {
            return false; // Doesn't exist, not "used"
        }

        return $invite['used_by'] !== null;
    }

    /**
     * Consume an invite (mark as used)
     * @param string $code The invite code
     * @param int $userId The user who is using the invite
     * @return bool True if successful
     */
    public static function consume(string $code, int $userId): bool
    {
        $affected = Database::execute(
            'UPDATE invites SET used_by = ?, used_at = ? WHERE code = ? AND used_by IS NULL',
            [$userId, time(), strtoupper($code)]
        );

        return $affected > 0;
    }

    /**
     * Get invite by code (regardless of used status)
     */
    public static function getByCode(string $code): ?array
    {
        return Database::queryOne(
            'SELECT id, code, created_by, used_by, created_at, used_at FROM invites WHERE code = ?',
            [strtoupper($code)]
        );
    }

    /**
     * Generate a random invite code
     */
    private static function generateCode(): string
    {
        $code = '';
        $charsLength = strlen(self::CODE_CHARS);
        
        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_CHARS[random_int(0, $charsLength - 1)];
        }

        return $code;
    }

    /**
     * Build the full invite URL
     */
    public static function getUrl(string $code): string
    {
        // This will be configured based on the server setup
        $baseUrl = getenv('APP_URL') ?: 'http://localhost:8080';
        return $baseUrl . '/join/' . $code;
    }
}

