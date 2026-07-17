<?php
// public/api/billing/status.php
// The registration "check your phone" screen polls this. On a successful payment
// it establishes the owner's session and returns the dashboard URL.
require_once __DIR__ . '/../../../app/app.php';
header('Content-Type: application/json');

$checkout = $_GET['checkout'] ?? '';
if ($checkout === '') { echo json_encode(['status' => 'unknown']); exit; }

$pdo  = Database::pdo();
$bill = new BillingService($pdo);            // no M-Pesa instance needed to read status
$st   = $bill->status($checkout);

if (!$st) { echo json_encode(['status' => 'unknown']); exit; }

if ($st['status'] === 'success') {
    // Activated by the callback already — log them straight in (first session).
    $stmt = $pdo->prepare('SELECT u.*, r.role_name FROM users u JOIN roles r ON u.role_id = r.id WHERE u.id = ?');
    $stmt->execute([(int) $st['user_id']]);
    $user = $stmt->fetch();
    if ($user) {
        session_regenerate_id(true);
        TenantContext::establish($pdo, $user);
        $_SESSION['username']     = $user['username'];
        $_SESSION['logged_in']    = true;
        $_SESSION['otp_verified'] = true;   // first session right after payment
        $_SESSION['first_login']  = true;
        unset($_SESSION['reg_pending']);
    }
    echo json_encode(['status' => 'success', 'redirect' => '/Curlz/public/super/dashboard/']);
    exit;
}

echo json_encode(['status' => $st['status']]);