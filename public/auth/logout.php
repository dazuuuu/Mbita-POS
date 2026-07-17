<?php
// public/auth/logout.php
require_once __DIR__ . '/../../app/app.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
$tenantId = (int) ($_SESSION['tenant_id'] ?? 0);
$role = $_SESSION['role'] ?? '';

if ($userId && $tenantId && StaffAttendanceService::shouldTrack($role)) {
    try {
        StaffAttendanceService::recordLogout(Database::pdo(), $tenantId, $userId);
    } catch (Throwable $e) {
        // Continue logout even if attendance write fails.
    }
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . public_path('auth/login.php'));
exit;
