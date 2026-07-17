<?php
// public/staff/appointments/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::appointments();

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
$__tenant = (new Models\TenantModel($pdo))->find((int) TenantContext::tenantId());

$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$apptSvc = new AppointmentService($pdo);
$svcSvc = new OfferedServiceService($pdo);

$stmt = $pdo->prepare('SELECT branch_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$branchId = (int) ($stmt->fetchColumn() ?: 0) ?: null;

$services = $svcSvc->activeForTenant($tenantId, $branchId);
$staffRoles = StaffRoles::employeeRoleNames();
$ph = implode(',', array_fill(0, count($staffRoles), '?'));
$st = $pdo->prepare(
    "SELECT u.id, u.username FROM users u JOIN roles r ON r.id = u.role_id
      WHERE u.tenant_id = ? AND u.is_active = 1 AND r.role_name IN ({$ph}) ORDER BY u.username"
);
$st->execute(array_merge([$tenantId], $staffRoles));
$branchStaff = $st->fetchAll() ?: [];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_POST['action'] ?? '') === 'cancel') {
        $apptSvc->cancel($tenantId, (int) ($_POST['appointment_id'] ?? 0));
        $_SESSION['flash'] = ['success' => 'Appointment cancelled.'];
        header('Location: ' . public_path('staff/appointments/'));
        exit;
    }
    $res = $apptSvc->create($tenantId, $userId, [
        'customer_name'  => $_POST['customer_name'] ?? '',
        'customer_phone' => $_POST['customer_phone'] ?? '',
        'service_id'     => (int) ($_POST['service_id'] ?? 0),
        'agent_user_id'  => (int) ($_POST['agent_user_id'] ?? 0),
        'scheduled_at'   => ($_POST['scheduled_date'] ?? '') . ' ' . ($_POST['scheduled_time'] ?? '09:00'),
        'branch_id'      => $branchId,
        'notes'          => $_POST['notes'] ?? '',
    ]);
    if ($res['ok']) {
        $_SESSION['flash'] = ['success' => 'Appointment booked.'];
        header('Location: ' . public_path('staff/appointments/'));
        exit;
    }
    $errors = $res['errors'];
}

$from = date('Y-m-d 00:00:00');
$to = date('Y-m-d 23:59:59', strtotime('+14 days'));
$upcoming = $apptSvc->listForTenant($tenantId, $branchId, $from, $to);

$page_title = 'Appointments';
ob_start();
?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h5 fw-bold mb-1">Book appointment</h2>
        <p class="text-muted small mb-4">Schedule a future visit. On the day, use check-in to start service and generate till receipt.</p>
        <form method="post" class="vstack gap-3">
          <div>
            <label class="form-label fw-semibold">Customer name <span class="text-danger">*</span></label>
            <input type="text" name="customer_name" class="form-control" required value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
            <?php if (!empty($errors['customer_name'])): ?><div class="text-danger small"><?php echo htmlspecialchars($errors['customer_name']); ?></div><?php endif; ?>
          </div>
          <div>
            <label class="form-label fw-semibold">Phone</label>
            <input type="text" name="customer_phone" class="form-control" value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
          </div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label fw-semibold">Date <span class="text-danger">*</span></label>
              <input type="date" name="scheduled_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>"
                     value="<?php echo htmlspecialchars($_POST['scheduled_date'] ?? date('Y-m-d', strtotime('+1 day'))); ?>">
            </div>
            <div class="col-6">
              <label class="form-label fw-semibold">Time</label>
              <input type="time" name="scheduled_time" class="form-control" value="<?php echo htmlspecialchars($_POST['scheduled_time'] ?? '10:00'); ?>">
            </div>
          </div>
          <?php if (!empty($errors['scheduled_at'])): ?><div class="text-danger small"><?php echo htmlspecialchars($errors['scheduled_at']); ?></div><?php endif; ?>
          <div>
            <label class="form-label fw-semibold">Service</label>
            <select name="service_id" class="form-select">
              <option value="">— Optional —</option>
              <?php foreach ($services as $svc): ?>
              <option value="<?php echo (int)$svc['id']; ?>"><?php echo htmlspecialchars($svc['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label fw-semibold">Assign staff</label>
            <select name="agent_user_id" class="form-select">
              <option value="">— Any / TBD —</option>
              <?php foreach ($branchStaff as $s): ?>
              <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['username']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label class="form-label fw-semibold">Notes</label>
            <textarea name="notes" class="form-control" rows="2"></textarea>
          </div>
          <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
          <button type="submit" class="btn btn-primary"><i class="fas fa-calendar-plus me-1"></i> Book</button>
        </form>
        <a href="<?php echo public_path('staff/checkin/'); ?>" class="btn btn-outline-secondary w-100 mt-3">Walk-in check-in today</a>
      </div>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Upcoming (14 days)</h2>
        <?php if (!$upcoming): ?>
          <p class="text-muted mb-0">No upcoming appointments.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr class="text-muted small text-uppercase"><th>When</th><th>Customer</th><th>Service</th><th>Staff</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($upcoming as $a): ?>
              <tr>
                <td class="small text-nowrap"><?php echo date('D j M, g:i a', strtotime($a['scheduled_at'])); ?></td>
                <td class="small"><?php echo htmlspecialchars($a['customer_name']); ?></td>
                <td class="small"><?php echo htmlspecialchars($a['service_name'] ?: '—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($a['agent_name'] ?? '—'); ?></td>
                <td class="text-end text-nowrap">
                  <?php if ($a['status'] === 'scheduled'): ?>
                  <a class="btn btn-sm btn-primary" href="<?php echo public_path('staff/checkin/'); ?>?appointment=<?php echo (int)$a['id']; ?>">Check in</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Cancel this appointment?');">
                    <input type="hidden" name="action" value="cancel">
                    <input type="hidden" name="appointment_id" value="<?php echo (int)$a['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                  </form>
                  <?php else: ?>
                  <span class="badge bg-secondary"><?php echo htmlspecialchars($a['status']); ?></span>
                  <?php endif; ?>
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
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
