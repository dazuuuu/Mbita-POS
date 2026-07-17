<?php
// public/devs/run-migrations.php
// Dev-only: apply all SQL migrations in order. Visit once after a fresh DB setup.
// DELETE or protect this file before production.
require_once __DIR__ . '/../../app/app.php';

header('Content-Type: text/plain; charset=utf-8');

$dir = ROOT_PATH . '/databases/migrations';
$files = glob($dir . '/*.sql');
sort($files, SORT_NATURAL);

if (!$files) {
    echo "No migration files found in {$dir}\n";
    exit(1);
}

$pdo = Database::pdo();
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(120) NOT NULL UNIQUE,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = $pdo->query('SELECT filename FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($applied);

echo "Database: " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

foreach ($files as $file) {
    $name = basename($file);
    if (isset($applied[$name])) {
        echo "[skip] {$name}\n";
        continue;
    }

    $sql = file_get_contents($file);
    if ($sql === false || trim($sql) === '') {
        echo "[empty] {$name}\n";
        continue;
    }

    echo "[run]  {$name} ... ";
    try {
        runSqlFile($pdo, $sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (?)');
        $stmt->execute([$name]);
        echo "OK\n";
    } catch (Throwable $e) {
        echo "FAILED\n  " . $e->getMessage() . "\n";
    }
}

echo "\nDone.\n";

function runSqlFile(PDO $pdo, string $sql): void
{
    // Strip line comments; keep statements intact.
    $lines = preg_split('/\R/', $sql);
    $buf = '';
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if ($trim === '' || strpos($trim, '--') === 0) {
            continue;
        }
        // Never switch DB mid-run — use the connection from app/config/database.php.
        if (preg_match('/^(CREATE DATABASE|USE)\b/i', $trim)) {
            continue;
        }
        $buf .= $line . "\n";
    }

    $parts = preg_split('/;\s*\n/', $buf);
    foreach ($parts as $stmt) {
        $stmt = trim($stmt);
        if ($stmt === '') {
            continue;
        }
        $pdo->exec($stmt);
    }
}
