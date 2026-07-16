<?php
// public/super/commissions/index.php — view unpaid commission & confirm payouts
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
CommissionService::ensureSchema($pdo);
$svc = new CommissionService($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'pay') {
    $agentId = (int) ($_POST['agent_id'] ?? 0);
    $res = $svc->payAgent($tenantId, $agentId, (int) TenantContext::userId(), trim($_POST['notes'] ?? '') ?: null);
    if ($res['ok']) {
        $_SESSION['flash']['success'] = 'Paid KES ' . number_format($res['amount'], 2) . ' — agent commission balance has been reset.';
    } else {
        $_SESSION['flash']['error'] = $res['error'];
    }
    header('Location: ' . public_path('super/commissions/'));
    exit;
}

$summaries = $svc->agentSummaries($tenantId);
$history = $svc->payoutHistory($tenantId);
$page_title = 'Commissions';
ob_start();
?>
<div class="row g-4 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Unpaid commission</h2>
        <p class="text-muted small mb-3">When you pay an agent, confirm here — their earned commission resets and they start fresh for new sales.</p>
        <?php
        $withUnpaid = array_filter($summaries, fn($s) => (float)$s['unpaid_total'] > 0);
        if (!$withUnpaid): ?>
          <p class="text-muted mb-0">No unpaid commission right now.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr class="text-muted small text-uppercase"><th>Agent</th><th>Role</th><th>Sales</th><th>Unpaid total</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($withUnpaid as $s): ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo htmlspecialchars($s['username']); ?></div>
                  <small class="text-muted"><?php echo htmlspecialchars($s['email']); ?></small>
                </td>
                <td><span class="badge bg-light text-dark"><?php echo $s['role_name'] === 'sales_agent' ? 'Sales agent' : 'Staff'; ?></span></td>
                <td><?php echo (int)$s['sale_count']; ?></td>
                <td class="fw-bold text-success">KES <?php echo number_format((float)$s['unpaid_total'], 2); ?></td>
                <td class="text-end">
                  <form method="post" class="d-inline" onsubmit="return confirm('Confirm payment of KES <?php echo number_format((float)$s['unpaid_total'], 2); ?>? This will reset their commission balance.');">
                    <input type="hidden" name="action" value="pay">
                    <input type="hidden" name="agent_id" value="<?php echo (int)$s['id']; ?>">
                    <button class="btn btn-sm btn-success">Pay &amp; confirm</button>
                  </form>
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('super/commissions/detail.php'); ?>?agent=<?php echo (int)$s['id']; ?>">Details</a>
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

<div class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h6 mb-3">Recent payouts</h2>
    <?php if (!$history): ?>
      <p class="text-muted mb-0">No payouts recorded yet.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead><tr class="text-muted small"><th>Date</th><th>Agent</th><th>Amount</th><th>Sales</th><th>Paid by</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
          <tr>
            <td><?php echo date('j M Y H:i', strtotime($h['paid_at'])); ?></td>
            <td><?php echo htmlspecialchars($h['agent_name']); ?></td>
            <td>KES <?php echo number_format((float)$h['amount_paid'], 2); ?></td>
            <td><?php echo (int)$h['sales_count']; ?></td>
            <td><?php echo htmlspecialchars($h['paid_by_name']); ?></td>
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
include __DIR__ . '/../../templates/tenants/layout.php';
