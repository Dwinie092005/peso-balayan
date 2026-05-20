<?php
/**
 * PESO Balayan – Database Connection (PDO Singleton)
 * File: app/core/Database.php
 *
 * Provides a single reusable PDO instance throughout the app.
 * Uses prepared statements only — no raw string queries.
 */

namespace App\Core;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    private function __construct() {}
    private function __clone()     {}

    /**
     * Get the singleton PDO connection.
     */
    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST,
                DB_NAME,
                DB_CHARSET
            );

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET . " COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never expose raw PDO messages in production
                if (APP_DEBUG) {
                    die('Database connection error: ' . $e->getMessage());
                }
                die('A database error occurred. Please try again later.');
            }
        }

        return self::$instance;
    }

    /**
     * Convenience: prepare and execute, returns PDOStatement.
     */
    public static function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Last inserted ID.
     */
    public static function lastInsertId(): string
    {
        return self::getConnection()->lastInsertId();
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): void
    {
        self::getConnection()->beginTransaction();
    }

    /**
     * Commit a transaction.
     */
    public static function commit(): void
    {
        self::getConnection()->commit();
    }

    /**
     * Roll back a transaction.
     */
    public static function rollback(): void
    {
        self::getConnection()->rollBack();
    }
}
