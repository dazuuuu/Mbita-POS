<?php
// public/sales-agent/dashboard/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::salesAgent();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$schemaReady = CommissionService::ensureSchema($pdo);
$svc = new CommissionService($pdo);
$svcSvc = new OfferedServiceService($pdo);
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);

$services = $svcSvc->activeForTenant($tenantId);
try {
    $stmt = $pdo->prepare("SELECT id, name, selling_price FROM products WHERE tenant_id = ? AND status = 'active' ORDER BY name LIMIT 12");
    $stmt->execute([$tenantId]);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $products = [];
}

$today = $svc->todayTotal($tenantId, $userId);
$unpaid = $svc->unpaidTotal($tenantId, $userId);
$recent = array_slice($svc->unpaidSales($tenantId, $userId), 0, 5);

$page_title = 'Dashboard';
$who = $_SESSION['username'] ?? 'there';
ob_start();
?>
<?php if (!$schemaReady): ?>
<div class="alert alert-warning border-0 shadow-sm mb-4" style="border-radius:12px;">
  <strong>Database setup needed.</strong> Commission tables are missing on this database.
  If you are the shop owner, visit
  <a href="<?php echo public_path('devs/run-migrations.php'); ?>">run migrations</a>
  once, then reload this page.
</div>
<?php endif; ?>
<div class="row g-3 mb-4">
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;border-left:4px solid #22c55e!important;">
      <div class="card-body p-4">
        <div class="text-muted small text-uppercase">Commission earned today</div>
        <div class="display-6 fw-bold text-success mt-1">KES <?php echo number_format($today, 2); ?></div>
        <p class="text-muted small mb-0 mt-2">Unpaid sales recorded today. Resets when your manager pays you.</p>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="text-muted small text-uppercase">Total unpaid commission</div>
        <div class="h3 fw-bold mt-1">KES <?php echo number_format($unpaid, 2); ?></div>
        <a class="btn btn-sm btn-primary mt-2" href="<?php echo public_path('sales-agent/sales/new.php'); ?>">Record a sale</a>
      </div>
    </div>
  </div>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <div>
        <h2 class="h6 mb-1"><i class="fas fa-scissors text-primary me-2"></i>Services to sell</h2>
        <p class="text-muted small mb-0">Most of your commission comes from services. Tap to start a sale.</p>
      </div>
      <a class="btn btn-sm btn-primary" href="<?php echo public_path('sales-agent/sales/new.php'); ?>">Service + product sale</a>
    </div>
    <?php if (!$services): ?>
      <div class="alert alert-warning mb-0">
        No services set up yet. Ask your manager to add services under <strong>Super → Services</strong>
        (e.g. Haircut, Shave, Colour).
      </div>
    <?php else: ?>
    <div class="row g-2">
      <?php foreach ($services as $s): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="<?php echo public_path('sales-agent/sales/new.php'); ?>?service_id=<?php echo (int)$s['id']; ?>"
           class="d-block text-decoration-none border rounded p-3 h-100 bg-white hover-shadow"
           style="border-radius:10px!important;transition:box-shadow .15s;">
          <div class="fw-semibold text-dark"><?php echo htmlspecialchars($s['name']); ?></div>
          <div class="text-success fw-semibold mt-1">KES <?php echo number_format((float)$s['charge_amount'], 2); ?></div>
          <?php if (!empty($s['description'])): ?>
          <div class="text-muted small mt-1"><?php echo htmlspecialchars(strlen($s['description']) > 48 ? substr($s['description'], 0, 45) . '...' : $s['description']); ?></div>
          <?php endif; ?>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($products): ?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h6 mb-3"><i class="fas fa-box text-secondary me-2"></i>Products</h2>
    <div class="row g-2">
      <?php foreach ($products as $p): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <a href="<?php echo public_path('sales-agent/sales/new.php'); ?>?product_id=<?php echo (int)$p['id']; ?>"
           class="d-block text-decoration-none border rounded p-3 h-100 bg-white"
           style="border-radius:10px!important;">
          <div class="fw-semibold text-dark"><?php echo htmlspecialchars($p['name']); ?></div>
          <div class="text-success fw-semibold mt-1">KES <?php echo number_format((float)$p['selling_price'], 2); ?></div>
        </a>
      </div>
      <?php endforeach; ?>
    </div>
    <a class="btn btn-sm btn-outline-secondary mt-3" href="<?php echo public_path('sales-agent/sales/new.php'); ?>">View all in sale form</a>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h6 mb-3">Recent unpaid sales</h2>
    <?php if (!$recent): ?>
      <p class="text-muted mb-0">No sales recorded yet. <a href="<?php echo public_path('sales-agent/sales/new.php'); ?>">Record your first sale</a>.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr class="text-muted small"><th>When</th><th>Item</th><th>Charged</th><th>Commission</th></tr></thead>
        <tbody>
          <?php foreach ($recent as $s): ?>
          <tr>
            <td><?php echo date('j M H:i', strtotime($s['created_at'])); ?></td>
            <td><?php echo htmlspecialchars($s['item_name']); ?></td>
            <td>KES <?php echo number_format((float)$s['charged_amount'], 2); ?></td>
            <td class="text-success fw-semibold">KES <?php echo number_format((float)$s['total_commission'], 2); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <a class="btn btn-sm btn-outline-secondary mt-2" href="<?php echo public_path('sales-agent/sales/'); ?>">View all</a>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/sales-agent/layout.php';
