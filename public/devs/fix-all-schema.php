<?php
// public/devs/fix-all-schema.php — run ALL schema fixes safely (recommended)
require_once __DIR__ . '/../../app/app.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::pdo();
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();

echo "Mbita POS — general schema fix\n";
echo "Database: {$dbName}\n";
echo str_repeat('=', 50) . "\n\n";

$result = GeneralMigrationService::ensureApplied($pdo);

echo "Actions taken:\n";
if ($result['log']) {
    foreach ($result['log'] as $line) {
        echo "  {$line}\n";
    }
} else {
    echo "  (nothing to change — schema already up to date)\n";
}

echo "\nStatus check:\n";
foreach ($result['status']['checks'] as $check) {
    echo ($check['ok'] ? '[OK]  ' : '[FAIL]') . ' ' . $check['label'] . "\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
if ($result['ok']) {
    echo "All checks passed. Refresh Settings in your browser.\n";
} else {
    echo "Some checks still failing. Copy this output and share if you need help.\n";
    echo "You can also run databases/general_migration.sql in MySQL Workbench\n";
    echo "— skip any line that says 'Duplicate column name'.\n";
}
