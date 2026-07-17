<?php
// public/components/staff/notification_overlay.php — full-page owner message (AD style)
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
  <div class="staff-notify-card">
    <div class="staff-notify-badge"><?php echo htmlspecialchars($tplLabel); ?></div>
    <h2 class="staff-notify-title"><?php echo htmlspecialchars($pending['title']); ?></h2>
    <div class="staff-notify-body"><?php echo nl2br(htmlspecialchars($pending['body'])); ?></div>
    <p class="staff-notify-meta text-muted small">
      From management · <?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($pending['created_at']))); ?>
    </p>
    <form method="post" action="<?php echo htmlspecialchars($dismissUrl); ?>" class="staff-notify-actions">
      <input type="hidden" name="notification_id" value="<?php echo $nid; ?>">
      <button type="submit" class="btn btn-primary btn-lg w-100">Got it — close</button>
    </form>
  </div>
</div>
<style>
.staff-notify-overlay{
  position:fixed;inset:0;z-index:10000;background:rgba(15,23,42,.92);
  display:flex;align-items:center;justify-content:center;padding:20px;
}
.staff-notify-card{
  background:#fff;color:#0f172a;max-width:520px;width:100%;border-radius:16px;
  padding:28px 26px;box-shadow:0 24px 60px rgba(0,0,0,.35);text-align:center;
}
.staff-notify-badge{
  display:inline-block;background:#0f172a;color:#fff;font-size:.72rem;font-weight:700;
  letter-spacing:.12em;text-transform:uppercase;padding:6px 14px;border-radius:999px;margin-bottom:14px;
}
.staff-notify-title{font-size:1.45rem;font-weight:800;margin:0 0 16px;line-height:1.25;}
.staff-notify-body{font-size:1rem;line-height:1.55;text-align:left;background:#f8fafc;
  border:1px solid #e2e8f0;border-radius:10px;padding:16px;margin-bottom:14px;white-space:pre-wrap;}
.staff-notify-meta{margin-bottom:18px;}
</style>
