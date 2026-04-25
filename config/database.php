<?php

/**
 * config/database.php — Database Connection
 */

declare(strict_types=1);

static $__db_instance = null;

function db(): PDO
{
    global $__db_instance;

    if ($__db_instance === null) {
        try {
            $host = getenv('DB_HOST') ?: 'localhost';
            $dbname = getenv('DB_NAME');
            $user = getenv('DB_USER');
            $pass = getenv('DB_PASS');
            $charset = 'utf8mb4';

            if (!$dbname || !$user || !$pass) {
                throw new PDOException('Database credentials not set in environment');
            }

            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

            $__db_instance = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            die('Database connection failed. Please try again later.');
        }
    }

    return $__db_instance;
}

function db_select(string $query, array $params = []): array
{
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        return [];
    }
}

function db_execute(string $query, array $params = []): int
{
    try {
        $stmt = db()->prepare($query);
        $stmt->execute($params);
        return $stmt->rowCount();
    } catch (PDOException $e) {
        error_log('DB Error: ' . $e->getMessage());
        return 0;
    }
}

function db_last_insert_id(): string
{
    return db()->lastInsertId();
}

/**
 * Run a callable inside a database transaction.
 * Rolls back on exception; re-throws the exception.
 */
function db_transaction(callable $fn): void
{
    db()->beginTransaction();
    try {
        $fn();
        db()->commit();
    } catch (Throwable $e) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        throw $e;
    }
}

/**
 * Insert an in-app notification for a customer.
 *
 * @param int    $userId   Customer ID
 * @param string $message  Notification text
 * @param string $type     Notification type (e.g. 'order_update')
 * @param string $link     Optional URL to link to
 */
function createNotification(int $userId, string $message, string $type = 'info', string $link = ''): void
{
    if ($userId <= 0) return;
    try {
        db()->prepare(
            "INSERT INTO notifications (user_id, type, message, link, is_read, created_at)
             VALUES (?, ?, ?, ?, 0, NOW())"
        )->execute([$userId, $type, $message, $link ?: null]);
    } catch (Throwable $e) {
        error_log('createNotification error: ' . $e->getMessage());
    }
}