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
     * Generate an invite code linked to a roundtable conversation
     * @param int $createdBy User ID of creator
     * @param int $conversationId The roundtable conversation ID
     * @param int $totalSpots Number of open spots
     * @return string The invite code
     */
    public static function createForRoundtable(int $createdBy, int $conversationId, int $totalSpots): string
    {
        $code = self::generateCode();

        while (self::exists($code)) {
            $code = self::generateCode();
        }

        Database::execute(
            'INSERT INTO invites (code, created_by, created_at, conversation_id, total_spots) VALUES (?, ?, ?, ?, ?)',
            [$code, $createdBy, time(), $conversationId, $totalSpots]
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
            'SELECT id, code, created_by, used_by, created_at, used_at, conversation_id, total_spots FROM invites WHERE code = ?',
            [strtoupper($code)]
        );

        if ($invite === null) {
            return null;
        }

        // Roundtable invite: check spots
        if ($invite['conversation_id'] !== null) {
            $useCount = Database::queryOne(
                'SELECT COUNT(*) as cnt FROM invite_uses WHERE invite_id = ?',
                [$invite['id']]
            );
            if ((int)$useCount['cnt'] >= (int)$invite['total_spots']) {
                return null; // All spots filled
            }
            return $invite;
        }

        // Plain invite: check used_by
        if ($invite['used_by'] !== null) {
            return null;
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
     * Consume a roundtable invite spot (atomic)
     * @param int $inviteId The invite ID
     * @param int $userId The user claiming the spot
     * @return bool True if spot was claimed
     */
    public static function consumeRoundtableSpot(int $inviteId, int $userId): bool
    {
        $pdo = Database::get();
        $pdo->beginTransaction();
        try {
            // Check if user already used this invite
            $existing = Database::queryOne(
                'SELECT 1 FROM invite_uses WHERE invite_id = ? AND user_id = ?',
                [$inviteId, $userId]
            );
            if ($existing !== null) {
                $pdo->rollBack();
                return false;
            }

            // Atomic spot claim: only insert if spots remain
            $stmt = $pdo->prepare(
                'INSERT INTO invite_uses (invite_id, user_id, used_at)
                 SELECT ?, ?, ?
                 WHERE (SELECT COUNT(*) FROM invite_uses WHERE invite_id = ?) <
                       (SELECT total_spots FROM invites WHERE id = ?)'
            );
            $stmt->execute([$inviteId, $userId, time(), $inviteId, $inviteId]);

            if ($stmt->rowCount() === 0) {
                $pdo->rollBack();
                return false; // No spots left
            }

            $pdo->commit();
            return true;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Get remaining spots for a roundtable invite
     */
    public static function getSpotsRemaining(int $inviteId): int
    {
        $result = Database::queryOne(
            'SELECT i.total_spots - COUNT(iu.invite_id) as remaining
             FROM invites i
             LEFT JOIN invite_uses iu ON i.id = iu.invite_id
             WHERE i.id = ?
             GROUP BY i.id',
            [$inviteId]
        );
        return $result ? max(0, (int)$result['remaining']) : 0;
    }

    /**
     * Get the roundtable invite for a conversation
     */
    public static function getByConversationId(int $conversationId): ?array
    {
        return Database::queryOne(
            'SELECT id, code, created_by, conversation_id, total_spots, created_at FROM invites WHERE conversation_id = ?',
            [$conversationId]
        );
    }

    /**
     * Get invite by code (regardless of used status)
     */
    public static function getByCode(string $code): ?array
    {
        return Database::queryOne(
            'SELECT id, code, created_by, used_by, created_at, used_at, conversation_id, total_spots FROM invites WHERE code = ?',
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

