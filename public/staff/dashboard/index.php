<?php
// public/staff/dashboard/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::staff();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$businessType = $__tenant['business_type'] ?? 'shop';

$stmt = $pdo->prepare('SELECT b.title FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?');
$stmt->execute([TenantContext::userId()]);
$branch = $stmt->fetchColumn() ?: 'All branches';

$staffType = $_SESSION['staff_type'] ?? null;
$roleLabel = StaffRoles::typeLabels()[$staffType] ?? 'Staff';
$caps = [
    'payments'     => TenantContext::can(Capabilities::PAYMENTS_RECEIVE) || TenantContext::can(Capabilities::PAYMENTS_UPDATE),
    'appointments' => TenantContext::can(Capabilities::APPOINTMENTS_MANAGE),
    'invoices'     => TenantContext::can(Capabilities::INVOICES_MANAGE),
    'sales'        => TenantContext::can(Capabilities::SALES_RECORD),
    'commission'   => TenantContext::can(Capabilities::COMMISSION_RECORD) || TenantContext::can(Capabilities::COMMISSION_VIEW),
    'inventory'    => TenantContext::can(Capabilities::INVENTORY_EDIT),
    'reports'      => TenantContext::can(Capabilities::REPORTS_VIEW),
];

$page_title = 'Dashboard';
$who = $_SESSION['username'] ?? 'there';
ob_start();
?>
<div class="card border-0 shadow-sm mb-4" style="border-radius:12px;">
  <div class="card-body p-4">
    <h2 class="h4 mb-1">Hi <?php echo htmlspecialchars($who); ?></h2>
    <p class="text-muted mb-0">
      You're signed in as <strong><?php echo htmlspecialchars($roleLabel); ?></strong>
      at <strong><?php echo htmlspecialchars($branch); ?></strong>
      (<?php echo htmlspecialchars($__tenant['name'] ?? 'your shop'); ?>).
    </p>
  </div>
</div>

<div class="row g-3">
  <?php if ($caps['sales']): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="/Curlz/public/staff/sales/new.php" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Quick action</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-cash-register text-primary me-2"></i>Make a sale</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if ($caps['commission']): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="/Curlz/public/staff/commissions/new.php" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Quick action</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-coins text-warning me-2"></i>Record commission</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if ($caps['payments']): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Your access</div>
        <div class="h6 mb-0 mt-1">Payments &amp; till</div>
        <div class="small text-muted">Receive, verify, and follow up on payments.</div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($caps['appointments'] || $caps['invoices']): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Front desk</div>
        <div class="h6 mb-0 mt-1">Reception tools</div>
        <div class="small text-muted">
          <?php
          $parts = [];
          if ($caps['appointments']) $parts[] = 'appointments';
          if ($caps['invoices']) $parts[] = 'invoices';
          if (TenantContext::can(Capabilities::CUSTOMERS_CHECKIN)) $parts[] = 'check-in';
          echo htmlspecialchars(implode(', ', $parts) ?: 'customer management');
          ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($caps['reports']): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="/Curlz/public/super/reports/" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Reports</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-chart-bar text-info me-2"></i>View reports</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (!$caps['sales'] && !$caps['commission'] && !$caps['payments'] && !$caps['appointments']): ?>
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body text-muted">
        Your manager hasn't assigned any actions yet. Ask them to update your authorization under Staff → Authorization.
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
