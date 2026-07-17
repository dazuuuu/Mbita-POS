<?php
// public/staff/notifications/dismiss.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$nid = (int) ($_POST['notification_id'] ?? 0);

if ($nid > 0) {
    $stmt = $pdo->prepare('SELECT id FROM staff_notifications WHERE id = ? AND tenant_id = ? LIMIT 1');
    $stmt->execute([$nid, $tenantId]);
    if ($stmt->fetch()) {
        (new StaffNotificationService($pdo))->markRead($nid, $userId);
    }
}

$back = public_path('staff/dashboard/');
if (TenantContext::role() === 'sales_agent') {
    $back = public_path('sales-agent/dashboard/');
}
header('Location: ' . $back);
exit;
