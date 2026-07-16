<?php
// public/super/commissions/detail.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$agentId = (int) ($_GET['agent'] ?? 0);
CommissionService::ensureSchema($pdo);
$svc = new CommissionService($pdo);

$stmt = $pdo->prepare(
    "SELECT u.username, u.email, r.role_name FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.id = ? AND u.tenant_id = ? LIMIT 1"
);
$stmt->execute([$agentId, $tenantId]);
$agent = $stmt->fetch();
if (!$agent) {
    header('Location: ' . public_path('super/commissions/'));
    exit;
}

$sales = $svc->unpaidSales($tenantId, $agentId);
$total = $svc->unpaidTotal($tenantId, $agentId);
$page_title = 'Commission details';
ob_start();
?>
<div class="mb-3">
  <a href="<?php echo public_path('super/commissions/'); ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i>Back</a>
</div>
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h5 mb-1"><?php echo htmlspecialchars($agent['username']); ?></h2>
    <p class="text-muted mb-2"><?php echo htmlspecialchars($agent['email']); ?></p>
    <div class="h4 text-success mb-0">Unpaid: KES <?php echo number_format($total, 2); ?></div>
  </div>
</div>
<?php if ($sales): ?>
<div class="table-responsive card border-0 shadow-sm" style="border-radius:12px;">
  <table class="table align-middle mb-0">
    <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Item</th><th>Charged</th><th>Base</th><th>Extra</th><th>Total</th></tr></thead>
    <tbody>
      <?php foreach ($sales as $s): ?>
      <tr>
        <td class="small"><?php echo date('j M H:i', strtotime($s['created_at'])); ?></td>
        <td><?php echo htmlspecialchars($s['item_name']); ?> <span class="badge bg-light text-dark"><?php echo $s['item_type']; ?></span></td>
        <td>KES <?php echo number_format((float)$s['charged_amount'], 2); ?></td>
        <td>KES <?php echo number_format((float)$s['base_commission'], 2); ?></td>
        <td>KES <?php echo number_format((float)$s['overage_commission'], 2); ?></td>
        <td class="fw-semibold">KES <?php echo number_format((float)$s['total_commission'], 2); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php else: ?>
<p class="text-muted">No unpaid sales for this agent.</p>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
