<?php
// app/app.php
// Single front bootstrap for the SaaS pages. Supersedes the duplicated
// init.php / bootstrap.php autoloaders. Every page does:
//     require_once __DIR__ . '/<relative>/app/app.php';

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__)); // app/ -> project root
}

// Composer (PHPMailer, etc.)
if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

spl_autoload_register(function ($class) {
    // Namespaced: Models\X, Controllers\X
    foreach (['Models\\' => '/app/models/', 'Controllers\\' => '/app/controllers/'] as $prefix => $dir) {
        if (strpos($class, $prefix) === 0) {
            $file = ROOT_PATH . $dir . substr($class, strlen($prefix)) . '.php';
            if (is_file($file)) { require_once $file; return; }
        }
    }
    // Global infrastructure classes live in helpers/ or services/.
    foreach (['/app/helpers/', '/app/services/'] as $dir) {
        $file = ROOT_PATH . $dir . $class . '.php';
        if (is_file($file)) { require_once $file; return; }
    }
});

// Global helpers loaded on every request (not autoloaded by class name alone in all setups).
require_once ROOT_PATH . '/app/helpers/SchemaHelper.php';
require_once ROOT_PATH . '/app/helpers/AppUrl.php';
require_once ROOT_PATH . '/app/helpers/TenantModules.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Rehydrate the current tenant/user/capabilities for this request.
TenantContext::boot();

// Do NOT open a DB connection or run migrations here — every page loads this file.
// Pages that need the database call Database::pdo() themselves.
// Pages that need schema 025 call Schema025Service::ensureApplied() themselves.