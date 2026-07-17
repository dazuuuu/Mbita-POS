<?php
// public/staff/dashboard/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::staff();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$modules = StaffNav::staffModules($pdo, $__tenant);

$stmt = $pdo->prepare('SELECT b.title FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?');
$stmt->execute([TenantContext::userId()]);
$branch = $stmt->fetchColumn() ?: 'All branches';

$staffType = $_SESSION['staff_type'] ?? null;
$roleLabel = StaffRoles::typeLabels()[$staffType] ?? 'Staff';

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
  <?php if (StaffNav::canCheckIn($modules)): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="<?php echo public_path('staff/checkin/'); ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Services</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-user-check text-primary me-2"></i>Customer check-in</div>
        <div class="small text-muted mt-1">Check in customer, assign staff, send unpaid receipt to till.</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (StaffNav::canManageAppointments($modules)): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="<?php echo public_path('staff/appointments/'); ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Services</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-calendar-check text-info me-2"></i>Appointments</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (StaffNav::canSellProducts($modules)): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="<?php echo public_path('staff/sales/new.php'); ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Products</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-cash-register text-success me-2"></i>Sell products</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (StaffNav::canProcessPayments()): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="<?php echo public_path('staff/payments/'); ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Till</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-money-bill-wave text-warning me-2"></i>Process payments</div>
        <div class="small text-muted mt-1">Cash, M-Pesa, or credit for checked-in services.</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (StaffNav::canViewCommission()): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="<?php echo public_path('staff/commissions/'); ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Earnings</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-coins text-warning me-2"></i>My commission</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (TenantContext::can(Capabilities::REPORTS_VIEW)): ?>
  <div class="col-12 col-md-6 col-lg-4">
    <a href="<?php echo public_path('super/reports/'); ?>" class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:12px;">
      <div class="card-body">
        <div class="text-muted small text-uppercase">Reports</div>
        <div class="h5 mb-0 mt-1"><i class="fas fa-chart-bar text-info me-2"></i>View reports</div>
      </div>
    </a>
  </div>
  <?php endif; ?>

  <?php if (!StaffNav::canCheckIn($modules) && !StaffNav::canSellProducts($modules) && !StaffNav::canProcessPayments() && !StaffNav::canManageAppointments($modules)): ?>
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
