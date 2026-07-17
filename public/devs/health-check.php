<?php
// public/devs/health-check.php — quick diagnostics (remove or restrict in production)
require_once __DIR__ . '/../../app/app.php';
header('Content-Type: text/plain; charset=utf-8');

echo "Mbita POS health check\n";
echo str_repeat('=', 40) . "\n\n";

echo "PHP version: " . PHP_VERSION . "\n";
echo "ROOT_PATH: " . ROOT_PATH . "\n";
echo "base_path: " . AppUrl::basePath() . "\n";
echo "login URL: " . public_path('auth/login.php') . "\n\n";

$files = [
    'app/config/paths.php',
    'app/helpers/AppUrl.php',
    'app/helpers/StaffRoles.php',
    'app/services/Schema020Service.php',
    'app/services/Schema025Service.php',
    'app/services/Schema026Service.php',
    'public/auth/login.php',
];
echo "Required files:\n";
foreach ($files as $f) {
    $ok = is_file(ROOT_PATH . '/' . $f);
    echo ($ok ? '[OK]' : '[MISSING]') . " {$f}\n";
}

echo "\nParse check (login.php):\n";
$out = [];
$code = 0;
exec('php -l ' . escapeshellarg(ROOT_PATH . '/public/auth/login.php') . ' 2>&1', $out, $code);
echo implode("\n", $out) . "\n";

echo "\nDatabase:\n";
try {
    $pdo = Database::pdo();
    echo "[OK] Connected to " . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
    GeneralMigrationService::ensureApplied($pdo);
    echo "[OK] General migration check ran\n";
    $report = GeneralMigrationService::statusReport($pdo);
    foreach ($report['checks'] as $c) {
        echo ($c['ok'] ? '[OK]' : '[FAIL]') . ' ' . $c['label'] . "\n";
    }
    Schema025Service::ensureApplied($pdo);
    echo "[OK] Schema 025 check ran\n";
    $s020 = Schema020Service::ensureApplied($pdo);
    echo ($s020['ok'] ? '[OK]' : '[WARN]') . " Schema 020 (inventory/products.tenant_id)\n";
    foreach ($s020['log'] as $line) {
        echo "  - {$line}\n";
    }
    $s026 = Schema026Service::ensureApplied($pdo);
    echo ($s026['ok'] ? '[OK]' : '[WARN]') . " Schema 026 (modules + branch types)\n";
} catch (Throwable $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
}

echo "\nOpen login at:\n" . public_path('auth/login.php') . "\n";
