<?php
// app/helpers/Database.php
// Single source of the PDO connection. Replaces the database.php / db_connect.php
// duplication so there is exactly one place where the connection (and, through the
// base Model, the tenant scope) is established.

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require ROOT_PATH . '/app/config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['dbname'],
                $cfg['charset'] ?? 'utf8mb4'
            );

            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 3,
            ]);
            // Fail fast when MySQL is not running (avoids "endless loading" tabs).
            if (defined('PDO::MYSQL_ATTR_CONNECT_TIMEOUT')) {
                self::$pdo->setAttribute(PDO::MYSQL_ATTR_CONNECT_TIMEOUT, 3);
            }
        }
        return self::$pdo;
    }

    /** For tests: inject a pre-built PDO (e.g. a throwaway test database). */
    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}