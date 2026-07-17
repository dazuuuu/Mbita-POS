<?php
// public/super/sales-agents/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
require_once ROOT_PATH . '/app/services/emails/sales_agent_invite_email.php';

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$svc = new SalesAgentService($pdo);
$errors = [];
$old = ['email' => '', 'name' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';
    if ($action === 'delete') {
        $res = $svc->delete($tenantId, (int) ($_POST['agent_id'] ?? 0));
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? 'Sales agent removed.' : $res['error'];
        header('Location: /Curlz/public/super/sales-agents/');
        exit;
    }

    $old = ['email' => trim($_POST['email'] ?? ''), 'name' => trim($_POST['name'] ?? '')];
    $appCfg = require ROOT_PATH . '/app/config/app.php';
    $loginUrl = rtrim($appCfg['app_url'] ?? 'http://localhost/Curlz', '/') . '/public/auth/login.php';
    $notify = function (array $info) use ($loginUrl) {
        $msg = build_sales_agent_invite_email($info['name'], $info['temp_password'], $loginUrl, $info['shop']);
        (new MailService())->send($info['email'], $msg['subject'], $msg['html']);
    };
    $res = $svc->create($tenantId, $old, $notify);
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Sales agent created — invite emailed to ' . $old['email'] . '.';
        header('Location: /Curlz/public/super/sales-agents/');
        exit;
    }
    $errors = $res['errors'];
}

$agents = $svc->listForTenant($tenantId);
$page_title = 'Sales agents';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add sales agent</h2>
        <p class="text-muted small mb-3">Sales agents log what they sell, track commission earned, and get paid when you confirm from the commissions page.</p>
        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
        <form method="post">
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Name <span class="text-muted">(optional)</span></label>
            <input name="name" class="form-control" value="<?php echo htmlspecialchars($old['name']); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($old['email']); ?>">
            <?php if (!empty($errors['email'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['email']); ?></small><?php endif; ?>
          </div>
          <button class="btn btn-primary">Create &amp; send invite</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Sales agents <span class="badge bg-light text-dark"><?php echo count($agents); ?></span></h2>
        <?php if (!$agents): ?>
          <p class="text-muted mb-0">No sales agents yet.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Email</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($agents as $a): ?>
              <tr>
                <td class="fw-semibold"><?php echo htmlspecialchars($a['username']); ?></td>
                <td class="text-muted"><?php echo htmlspecialchars($a['email']); ?></td>
                <td>
                  <?php if ((int)$a['must_reset_password']): ?>
                    <span class="badge bg-warning text-dark">Pending first login</span>
                  <?php elseif ((int)$a['is_active']): ?>
                    <span class="badge bg-success">Active</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Disabled</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Remove this sales agent?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="agent_id" value="<?php echo (int)$a['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
