<?php
// public/devs/fix-schema-024.php
require_once __DIR__ . '/../../app/app.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::pdo();
echo 'Database: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

SchemaHelper::clearCache();
$result = Schema024Service::ensureApplied($pdo);
foreach ($result['log'] as $line) {
    echo $line . "\n";
}

echo "\n024 checks:\n";
$report = GeneralMigrationService::statusReport($pdo);
foreach ($report['checks'] as $c) {
    if (str_starts_with($c['label'], 'tenants.') || $c['label'] === 'table customers' || str_starts_with($c['label'], 'commission_sales')) {
        echo ($c['ok'] ? '[OK]' : '[FAIL]') . ' ' . $c['label'] . "\n";
    }
}

echo "\n" . ($result['ok'] ? "Migration 024 ready.\n" : "Still missing items — run fix-all-schema.php instead.\n");
