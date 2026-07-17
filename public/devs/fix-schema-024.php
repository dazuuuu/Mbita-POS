<?php
// public/devs/fix-schema-024.php
require_once __DIR__ . '/../../app/app.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = Database::pdo();
echo 'Database: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n\n";

$result = Schema024Service::ensureApplied($pdo);
foreach ($result['log'] as $line) {
    echo $line . "\n";
}
echo "\n" . ($result['ok'] ? "All migration 024 objects ready.\n" : "Some items may still be missing — check log above.\n");
