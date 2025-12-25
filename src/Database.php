<?php

declare(strict_types=1);

/**
 * Database connection singleton for SQLite
 */
class Database
{
    private static ?PDO $pdo = null;
    private static string $path = '';

    /**
     * Initialize the database connection
     */
    public static function init(string $path): void
    {
        self::$path = $path;
        self::$pdo = null; // Reset connection if reinitializing
    }

    /**
     * Get the PDO connection instance
     */
    public static function get(): PDO
    {
        if (self::$pdo === null) {
            if (empty(self::$path)) {
                self::$path = __DIR__ . '/../data/spjallchat.db';
            }

            self::$pdo = new PDO('sqlite:' . self::$path);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            // Enable foreign keys
            self::$pdo->exec('PRAGMA foreign_keys = ON');
        }

        return self::$pdo;
    }

    /**
     * Execute a query and return all results
     */
    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a query and return a single row
     */
    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    /**
     * Execute an insert/update/delete and return affected rows
     */
    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::get()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get the last inserted ID
     */
    public static function lastInsertId(): int
    {
        return (int) self::get()->lastInsertId();
    }
}

