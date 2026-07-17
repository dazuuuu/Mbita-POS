<?php
// public/components/staff/notification_overlay.php — owner message overlay for staff
if (!TenantContext::check() || empty($_SESSION['logged_in'])) {
    return;
}
$role = TenantContext::role() ?? '';
if (!StaffAttendanceService::shouldTrack($role)) {
    return;
}

try {
    $pdo = Database::pdo();
    $tenantId = (int) TenantContext::tenantId();
    $userId = (int) TenantContext::userId();
    if (!$tenantId || !$userId) {
        return;
    }
    $pending = (new StaffNotificationService($pdo))->pendingForUser($tenantId, $userId);
} catch (Throwable $e) {
    return;
}

if (!$pending) {
    return;
}

$tplLabels = ['late' => 'Late', 'memo' => 'Memo', 'notice' => 'Notice'];
$tplKey = $pending['template_type'] ?? 'memo';
$tplLabel = $tplLabels[$tplKey] ?? 'Notice';
$dismissUrl = public_path('staff/notifications/dismiss.php');
$nid = (int) $pending['id'];
?>
<div id="staffNotifyOverlay" class="staff-notify-overlay" role="dialog" aria-modal="true">
  <div class="card border-0 shadow-sm staff-notify-card">
    <div class="card-body p-4 text-center">
      <span class="badge bg-secondary mb-2"><?php echo htmlspecialchars($tplLabel); ?></span>
      <h2 class="h5 fw-bold mb-3"><?php echo htmlspecialchars($pending['title']); ?></h2>
      <div class="text-start bg-light border rounded p-3 mb-3 small"><?php echo nl2br(htmlspecialchars($pending['body'])); ?></div>
      <p class="text-muted small mb-3">
        From management · <?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($pending['created_at']))); ?>
      </p>
      <form method="post" action="<?php echo htmlspecialchars($dismissUrl); ?>">
        <input type="hidden" name="notification_id" value="<?php echo $nid; ?>">
        <button type="submit" class="btn btn-primary w-100">Got it — close</button>
      </form>
    </div>
  </div>
</div>
<style>
.staff-notify-overlay{
  position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45);
  display:flex;align-items:center;justify-content:center;padding:20px;
}
.staff-notify-card{max-width:480px;width:100%;border-radius:14px;}
</style>
