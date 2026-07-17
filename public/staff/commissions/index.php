<?php
// public/staff/commissions/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::commissionAgent();
if (!StaffRoles::isEmployeeRole(TenantContext::role())) {
    header('Location: ' . public_path('sales-agent/sales/'));
    exit;
}

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$svc = new CommissionService($pdo);

$sales = $svc->unpaidSales($tenantId, $userId);
$total = $svc->unpaidTotal($tenantId, $userId);
$today = $svc->todayTotal($tenantId, $userId);

$page_title = 'My commission';
ob_start();
?>
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <div class="text-muted small">Commission today</div>
      <div class="h4 text-success mb-0">KES <?php echo number_format($today, 2); ?></div>
    </div></div>
  </div>
  <div class="col-md-4">
    <div class="card border-0 shadow-sm"><div class="card-body">
      <div class="text-muted small">Unpaid total</div>
      <div class="h4 mb-0">KES <?php echo number_format($total, 2); ?></div>
    </div></div>
  </div>
  <div class="col-md-4 d-flex align-items-center">
    <a class="btn btn-primary" href="<?php echo public_path('staff/commissions/new.php'); ?>">+ Record sale</a>
  </div>
</div>
<div class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h6 mb-3">Unpaid commissioned sales</h2>
    <?php if (!$sales): ?>
      <p class="text-muted mb-0">No unpaid sales yet.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table align-middle">
            <thead><tr class="text-muted small text-uppercase"><th>Date</th><th>Receipt</th><th>Item</th><th>Charged</th><th>Commission</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($sales as $s): ?>
              <tr>
                <td><?php echo date('j M Y H:i', strtotime($s['created_at'])); ?></td>
                <td class="small"><?php echo htmlspecialchars($s['receipt_number'] ?? '—'); ?></td>
                <td><?php echo htmlspecialchars($s['item_name']); ?></td>
                <td>KES <?php echo number_format((float)$s['charged_amount'], 2); ?></td>
                <td class="text-success fw-semibold">KES <?php echo number_format((float)$s['total_commission'], 2); ?></td>
                <td><a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('commission/receipt.php'); ?>?id=<?php echo (int)$s['id']; ?>">Receipt</a></td>
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
