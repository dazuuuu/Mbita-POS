<?php
// public/staff/payments/index.php — process pending service invoices at till
require_once __DIR__ . '/../../../app/app.php';
PageGuard::payments();

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
CommissionService::ensureSchema($pdo);

$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$commSvc = new CommissionService($pdo);
$custSvc = new CustomerService($pdo);

$serviceCreditsOn = !empty($__tenant['service_credits_enabled']) || !empty($__tenant['credits_enabled']);
$defaultCreditDays = (int) ($__tenant['default_credit_days'] ?? 30);
$defaultCreditLimit = (float) ($__tenant['default_credit_limit'] ?? 5000);

$stmt = $pdo->prepare('SELECT branch_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$branchId = (int) ($stmt->fetchColumn() ?: 0) ?: null;

$staffRoles = StaffRoles::employeeRoleNames();
$placeholders = implode(',', array_fill(0, count($staffRoles), '?'));
$staffSql = "SELECT u.id, u.username, u.staff_type, r.role_name FROM users u
              JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND u.is_active = 1 AND r.role_name IN ({$placeholders})
               AND (u.staff_type = 'barber' OR r.role_name = 'barber')";
$staffParams = array_merge([$tenantId], $staffRoles);
if ($branchId) {
    $staffSql .= ' AND (u.branch_id = ? OR u.branch_id IS NULL)';
    $staffParams[] = $branchId;
}
$staffSql .= ' ORDER BY u.username ASC';
$st = $pdo->prepare($staffSql);
$st->execute($staffParams);
$serviceStaff = $st->fetchAll() ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'process_payment') {
    $saleId = (int) ($_POST['sale_id'] ?? 0);
    $res = $commSvc->processPayment($tenantId, $saleId, [
        'payment_method' => $_POST['payment_method'] ?? 'cash',
        'credit_days'      => (int) ($_POST['credit_days'] ?? $defaultCreditDays),
        'agent_user_id'    => (int) ($_POST['agent_user_id'] ?? 0),
    ]);
    if ($res['ok']) {
        $_SESSION['flash'] = ['success' => 'Payment recorded. Commission credited to assigned staff.'];
        header('Location: ' . ReceiptUrl::forCommission($saleId));
        exit;
    }
    $_SESSION['flash'] = ['error' => $res['error'] ?? 'Could not process payment.'];
    header('Location: ' . public_path('staff/payments/'));
    exit;
}

$pending = $commSvc->pendingPayments($tenantId, $branchId);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

function payment_credit_available(array $row, array $tenant): float
{
    $limit = $row['customer_credit_limit'] ?? null;
    if ($limit === null || $limit === '') {
        $limit = (float) ($tenant['default_credit_limit'] ?? 0);
    } else {
        $limit = (float) $limit;
    }
    $used = (float) ($row['credit_balance'] ?? 0);
    return max(0, round($limit - $used, 2));
}

$page_title = 'Process payments';
ob_start();
?>
<?php if ($flash): ?>
  <?php foreach ($flash as $kind => $msg): ?>
    <div class="alert alert-<?php echo $kind === 'success' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($msg); ?></div>
  <?php endforeach; ?>
<?php endif; ?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <div>
    <h2 class="h5 fw-bold mb-1">Process payments</h2>
    <p class="text-muted small mb-0">Unpaid check-ins from the floor. Assign the stylist/barber, then collect cash, M-Pesa<?php echo $serviceCreditsOn ? ', or credit' : ''; ?>.</p>
  </div>
  <?php if (TenantContext::can(Capabilities::CUSTOMERS_CHECKIN) || TenantContext::can(Capabilities::INVOICES_MANAGE)): ?>
  <a href="<?php echo public_path('staff/checkin/'); ?>" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-user-check me-1"></i> New check-in
  </a>
  <?php endif; ?>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-4">
    <?php if (!$pending): ?>
      <div class="text-muted text-center py-4">
        <i class="fas fa-check-circle fa-2x mb-2 d-block text-success" style="opacity:.5;"></i>
        No pending payments — all caught up.
      </div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr class="text-muted small text-uppercase">
            <th>Receipt</th><th>When</th><th>Customer</th><th>Service</th><th>Stylist</th><th>Credit avail.</th><th class="text-end">Amount</th><th>Pay with</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $p):
            $creditAvail = payment_credit_available($p, $__tenant);
            $canCredit = $serviceCreditsOn && !empty($p['customer_id']) && $creditAvail >= (float)$p['charged_amount'];
            $needsAssign = empty($p['agent_user_id']);
          ?>
          <tr>
            <td class="fw-semibold small">
              <a href="<?php echo ReceiptUrl::forCommission((int)$p['id']); ?>"><?php echo htmlspecialchars($p['receipt_number']); ?></a>
            </td>
            <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($p['created_at'])); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['customer_name'] ?: '—'); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['item_name']); ?></td>
            <td class="small">
              <?php if ($needsAssign && $serviceStaff): ?>
              <select name="agent_user_id" form="pay-<?php echo (int)$p['id']; ?>" class="form-select form-select-sm" required>
                <option value="">Assign stylist…</option>
                <?php foreach ($serviceStaff as $s): ?>
                <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['username']); ?></option>
                <?php endforeach; ?>
              </select>
              <?php elseif ($needsAssign): ?>
              <span class="text-warning">No barbers on branch</span>
              <?php else: ?>
              <?php echo htmlspecialchars($p['agent_name'] ?? '—'); ?>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if (!empty($p['customer_id'])): ?>
                <span class="<?php echo $canCredit ? 'text-success' : 'text-muted'; ?>">
                  KES <?php echo number_format($creditAvail, 0); ?>
                </span>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end fw-semibold">KES <?php echo number_format((float)$p['charged_amount'], 0); ?></td>
            <td class="text-end">
              <form method="post" id="pay-<?php echo (int)$p['id']; ?>" class="d-flex flex-column gap-1 align-items-end payment-form">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="sale_id" value="<?php echo (int)$p['id']; ?>">
                <?php if (!$needsAssign): ?>
                <input type="hidden" name="agent_user_id" value="<?php echo (int)$p['agent_user_id']; ?>">
                <?php endif; ?>
                <div class="d-flex gap-1 flex-wrap justify-content-end">
                  <select name="payment_method" class="form-select form-select-sm pay-method" style="width:110px;">
                    <option value="cash">Cash</option>
                    <option value="mpesa">M-Pesa</option>
                    <?php if ($serviceCreditsOn && !empty($p['customer_id'])): ?>
                    <option value="credit" <?php echo !$canCredit ? 'disabled' : ''; ?>>Credit</option>
                    <?php endif; ?>
                  </select>
                  <button type="submit" class="btn btn-sm btn-success">Pay</button>
                </div>
                <?php if ($serviceCreditsOn && !empty($p['customer_id'])): ?>
                <div class="credit-days-row d-none small text-muted">
                  Credit for <input type="number" name="credit_days" class="form-control form-control-sm d-inline-block" style="width:60px;" min="1" max="365" value="<?php echo (int)$defaultCreditDays; ?>"> days
                  <?php if (!$canCredit): ?>
                    <span class="text-danger d-block">Insufficient credit (need KES <?php echo number_format((float)$p['charged_amount'], 0); ?>)</span>
                  <?php endif; ?>
                </div>
                <?php endif; ?>
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

<script>
document.querySelectorAll('.pay-method').forEach(function(sel){
  sel.addEventListener('change', function(){
    var form = sel.closest('form');
    var row = form && form.querySelector('.credit-days-row');
    if (row) row.classList.toggle('d-none', sel.value !== 'credit');
  });
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
