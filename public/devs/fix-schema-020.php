<?php
// public/devs/fix-schema-020.php
require_once __DIR__ . '/../../app/app.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::pdo();
echo 'Database: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

$result = Schema020Service::ensureApplied($pdo);
foreach ($result['log'] as $line) {
    echo $line . "\n";
}
echo "\n" . ($result['ok'] ? "Inventory schema ready (products.tenant_id exists).\n" : "Some items may still be missing — check log above.\n");
