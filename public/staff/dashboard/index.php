<?php
// public/staff/dashboard/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::staff();

$pdo = Database::pdo();
$__tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());

$stmt = $pdo->prepare('SELECT b.title FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?');
$stmt->execute([TenantContext::userId()]);
$branch = $stmt->fetchColumn() ?: 'your branch';

$page_title = 'Dashboard';
$who = $_SESSION['username'] ?? 'there';
ob_start();
?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h4 mb-1">Hi <?php echo htmlspecialchars($who); ?></h2>
    <p class="text-muted mb-0">
      You're working at <strong><?php echo htmlspecialchars($branch); ?></strong>
      (<?php echo htmlspecialchars($__tenant['name'] ?? 'your shop'); ?>).
      Recording sales and checking stock will appear here as we switch those modules on.
    </p>
  </div>
</div>

<div class="row g-3">
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Your branch</div>
        <div class="h5 mb-0 mt-1"><?php echo htmlspecialchars($branch); ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Coming soon</div>
        <div class="text-muted small mt-1">Record a sale, look up stock, print a receipt.</div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';