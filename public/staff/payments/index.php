<?php
// public/staff/payments/index.php — process pending service invoices at till
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
CommissionService::ensureSchema($pdo);

$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$commSvc = new CommissionService($pdo);

if (!TenantContext::can(Capabilities::PAYMENTS_RECEIVE)) {
    header('Location: ' . public_path('auth/login.php?denied=1'));
    exit;
}

$stmt = $pdo->prepare('SELECT branch_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$branchId = (int) ($stmt->fetchColumn() ?: 0) ?: null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'process_payment') {
    $saleId = (int) ($_POST['sale_id'] ?? 0);
    $res = $commSvc->processPayment($tenantId, $saleId, [
        'payment_method' => $_POST['payment_method'] ?? 'cash',
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
    <h2 class="h5 fw-bold mb-1">Pending payments</h2>
    <p class="text-muted small mb-0">Service invoices waiting for cash or M-Pesa at till. Commission goes to the assigned staff when paid.</p>
  </div>
  <?php if (TenantContext::can(Capabilities::INVOICES_MANAGE)): ?>
  <a href="<?php echo public_path('staff/invoices/new.php'); ?>" class="btn btn-outline-primary btn-sm">
    <i class="fas fa-plus me-1"></i> New invoice
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
            <th>Invoice</th><th>When</th><th>Customer</th><th>Service</th><th>Staff</th><th class="text-end">Amount</th><th></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($pending as $p): ?>
          <tr>
            <td class="fw-semibold small"><?php echo htmlspecialchars($p['receipt_number']); ?></td>
            <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($p['created_at'])); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['customer_name'] ?: '—'); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['item_name']); ?></td>
            <td class="small"><?php echo htmlspecialchars($p['agent_name'] ?? '—'); ?></td>
            <td class="text-end fw-semibold">KES <?php echo number_format((float)$p['charged_amount'], 0); ?></td>
            <td class="text-end text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="<?php echo ReceiptUrl::forCommission((int)$p['id']); ?>">View</a>
              <form method="post" class="d-inline-flex gap-1 align-items-center">
                <input type="hidden" name="action" value="process_payment">
                <input type="hidden" name="sale_id" value="<?php echo (int)$p['id']; ?>">
                <select name="payment_method" class="form-select form-select-sm" style="width:auto;">
                  <option value="cash">Cash</option>
                  <option value="mpesa">M-Pesa</option>
                </select>
                <button class="btn btn-sm btn-success">Pay</button>
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
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
