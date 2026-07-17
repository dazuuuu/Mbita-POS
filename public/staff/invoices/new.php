<?php
// public/staff/invoices/new.php — reception creates service invoice (payment at till)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

if (!TenantContext::can(Capabilities::INVOICES_MANAGE) && !TenantContext::can(Capabilities::COMMISSION_RECORD)) {
    header('Location: ' . public_path('auth/login.php?denied=1'));
    exit;
}

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
CommissionService::ensureSchema($pdo);

$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$commSvc = new CommissionService($pdo);
$svcSvc = new OfferedServiceService($pdo);
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);

$stmt = $pdo->prepare('SELECT branch_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$branchId = (int) ($stmt->fetchColumn() ?: 0) ?: null;

$services = $svcSvc->activeForTenant($tenantId, $branchId);

$staffRoles = StaffRoles::employeeRoleNames();
$placeholders = implode(',', array_fill(0, count($staffRoles), '?'));
$staffSql = "SELECT u.id, u.username, u.staff_type FROM users u
              JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND u.is_active = 1 AND r.role_name IN ({$placeholders})";
$staffParams = array_merge([$tenantId], $staffRoles);
if ($branchId) {
    $staffSql .= ' AND (u.branch_id = ? OR u.branch_id IS NULL)';
    $staffParams[] = $branchId;
}
$staffSql .= ' ORDER BY u.username ASC';
$st = $pdo->prepare($staffSql);
$st->execute($staffParams);
$branchStaff = $st->fetchAll() ?: [];

$errors = [];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serviceId = (int) ($_POST['service_id'] ?? 0);
    $agentId = (int) ($_POST['agent_user_id'] ?? 0);
    $res = $commSvc->recordInvoice($tenantId, $userId, [
        'item_type'       => 'service',
        'service_id'      => $serviceId,
        'agent_user_id'   => $agentId,
        'charged_amount'  => (float) ($_POST['charged_amount'] ?? 0),
        'customer_name'   => trim($_POST['customer_name'] ?? ''),
        'customer_phone'  => trim($_POST['customer_phone'] ?? ''),
        'branch_id'       => $branchId,
        'notes'           => trim($_POST['notes'] ?? ''),
    ]);
    if ($res['ok']) {
        $_SESSION['flash'] = ['success' => 'Invoice created. Customer can pay at till.'];
        header('Location: ' . ReceiptUrl::forCommission((int) $res['id']));
        exit;
    }
    $errors = $res['errors'];
}

$page_title = 'New service invoice';
ob_start();
?>
<?php if ($flash): ?>
  <?php foreach ($flash as $kind => $msg): ?>
    <div class="alert alert-<?php echo $kind === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($msg); ?></div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:14px;max-width:640px;">
  <div class="card-body p-4">
    <h2 class="h5 fw-bold mb-1">Check-in &amp; service invoice</h2>
    <p class="text-muted small mb-4">Record the customer and service, assign the staff member who performed it. Payment is collected separately at till.</p>

    <?php if (!$services): ?>
      <div class="alert alert-warning mb-0">No active services for this location. Add services in Settings first.</div>
    <?php elseif (!$branchStaff): ?>
      <div class="alert alert-warning mb-0">No staff at this branch to assign. Add staff first.</div>
    <?php else: ?>
    <form method="post" class="vstack gap-3">
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label fw-semibold">Customer name <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" name="customer_name" class="form-control" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Phone <span class="text-muted fw-normal">(optional)</span></label>
          <input type="text" name="customer_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
        </div>
      </div>

      <div>
        <label class="form-label fw-semibold">Assign staff <span class="text-danger">*</span></label>
        <select name="agent_user_id" class="form-select" required>
          <option value="">— Who performed the service? —</option>
          <?php foreach ($branchStaff as $s): ?>
          <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)($_POST['agent_user_id'] ?? 0) === (int)$s['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($s['username']); ?>
            (<?php echo htmlspecialchars(StaffRoles::typeLabels()[$s['staff_type'] ?? 'general'] ?? 'Staff'); ?>)
          </option>
          <?php endforeach; ?>
        </select>
        <?php if (!empty($errors['agent_user_id'])): ?><div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['agent_user_id']); ?></div><?php endif; ?>
      </div>

      <div>
        <label class="form-label fw-semibold">Service <span class="text-danger">*</span></label>
        <select name="service_id" id="serviceSelect" class="form-select" required>
          <option value="">— Select service —</option>
          <?php foreach ($services as $svc): ?>
          <option value="<?php echo (int)$svc['id']; ?>" data-price="<?php echo (float)$svc['charge_amount']; ?>"
            <?php echo (int)($_POST['service_id'] ?? 0) === (int)$svc['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($svc['name']); ?> — KES <?php echo number_format((float)$svc['charge_amount'], 0); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label class="form-label fw-semibold">Amount to charge (KES)</label>
        <input type="number" step="0.01" min="0" name="charged_amount" id="chargedAmount" class="form-control"
               value="<?php echo htmlspecialchars($_POST['charged_amount'] ?? ''); ?>" required>
        <?php if (!empty($errors['charged_amount'])): ?><div class="text-danger small mt-1"><?php echo htmlspecialchars($errors['charged_amount']); ?></div><?php endif; ?>
      </div>

      <div>
        <label class="form-label fw-semibold">Notes</label>
        <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
      </div>

      <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2 mb-0"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>

      <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="fas fa-file-invoice me-1"></i> Create invoice</button>
        <a href="<?php echo public_path('staff/payments/'); ?>" class="btn btn-outline-secondary">Pending payments</a>
      </div>
    </form>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  var sel = document.getElementById('serviceSelect');
  var amt = document.getElementById('chargedAmount');
  if (!sel || !amt) return;
  sel.addEventListener('change', function(){
    var opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.price) amt.value = opt.dataset.price;
  });
  if (sel.value && !amt.value) sel.dispatchEvent(new Event('change'));
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
