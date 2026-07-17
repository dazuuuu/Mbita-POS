<?php
// public/super/notifications/index.php — broadcast messages to staff (full-screen on their devices)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
StaffNotificationService::ensureSchema($pdo);

$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$svc = new StaffNotificationService($pdo);
$templates = StaffNotificationService::templates();

$selectedTpl = $_POST['template'] ?? $_GET['template'] ?? 'memo';
if (!isset($templates[$selectedTpl])) {
    $selectedTpl = 'memo';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $res = $svc->create(
        $tenantId,
        $userId,
        $_POST['template'] ?? 'memo',
        $_POST['title'] ?? '',
        $_POST['body'] ?? ''
    );
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Notification sent. Staff will see it full-screen on their next page.';
        header('Location: ' . public_path('super/notifications/'));
        exit;
    }
    $_SESSION['flash']['error'] = $res['error'] ?? 'Could not send notification.';
}

$sent = $svc->listForTenant($tenantId);
$staffCount = $svc->activeStaffCount($tenantId);
$prefill = $templates[$selectedTpl];

$page_title = 'Notify staff';
ob_start();
?>
<?php if (!empty($_SESSION['flash']['success'])): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
<?php endif; ?>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Send notification</h2>
        <p class="text-muted small">Staff see this as a full-page message (like an ad) until they tap <strong>Got it — close</strong>.</p>

        <form method="post" id="notifyForm">
          <input type="hidden" name="action" value="send">

          <label class="form-label fw-semibold">Template</label>
          <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach ($templates as $key => $tpl): ?>
            <a href="?template=<?php echo urlencode($key); ?>"
               class="btn btn-sm <?php echo $selectedTpl === $key ? 'btn-primary' : 'btn-outline-secondary'; ?>">
              <?php echo htmlspecialchars($tpl['label']); ?>
            </a>
            <?php endforeach; ?>
          </div>
          <input type="hidden" name="template" value="<?php echo htmlspecialchars($selectedTpl); ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold">Title</label>
            <input type="text" name="title" class="form-control" required maxlength="160"
                   value="<?php echo htmlspecialchars($_POST['title'] ?? $prefill['title']); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Message</label>
            <textarea name="body" class="form-control" rows="7" required><?php echo htmlspecialchars($_POST['body'] ?? $prefill['body']); ?></textarea>
          </div>

          <p class="small text-muted mb-3">
            Goes to <strong><?php echo (int) $staffCount; ?></strong> active staff member<?php echo $staffCount === 1 ? '' : 's'; ?>.
          </p>
          <button type="submit" class="btn btn-primary">
            <i class="fas fa-paper-plane me-1"></i>Send to all staff
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Preview (staff view)</h2>
        <div class="border rounded-3 p-4 bg-dark text-white text-center" style="min-height:220px;">
          <span class="badge bg-light text-dark mb-2"><?php echo htmlspecialchars($prefill['label']); ?></span>
          <div class="fw-bold fs-5 mb-2" id="previewTitle"><?php echo htmlspecialchars($prefill['title']); ?></div>
          <div class="small text-start bg-white bg-opacity-10 rounded p-3 text-white-50" id="previewBody" style="white-space:pre-wrap;"><?php echo htmlspecialchars($prefill['body']); ?></div>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Recent notifications</h2>
        <?php if (!$sent): ?>
          <p class="text-muted small mb-0">Nothing sent yet.</p>
        <?php else: ?>
          <ul class="list-group list-group-flush">
            <?php foreach ($sent as $n):
              $lbl = $templates[$n['template_type']]['label'] ?? ucfirst($n['template_type']);
            ?>
            <li class="list-group-item px-0">
              <div class="d-flex justify-content-between gap-2">
                <div>
                  <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($lbl); ?></span>
                  <strong><?php echo htmlspecialchars($n['title']); ?></strong>
                  <div class="small text-muted"><?php echo htmlspecialchars(date('j M Y, g:i a', strtotime($n['created_at']))); ?>
                    · <?php echo (int) ($n['read_count'] ?? 0); ?> dismissed</div>
                </div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var form = document.getElementById('notifyForm');
  if (!form) return;
  var title = form.querySelector('[name=title]');
  var body = form.querySelector('[name=body]');
  var pt = document.getElementById('previewTitle');
  var pb = document.getElementById('previewBody');
  function sync(){
    if (pt && title) pt.textContent = title.value;
    if (pb && body) pb.textContent = body.value;
  }
  title && title.addEventListener('input', sync);
  body && body.addEventListener('input', sync);
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
