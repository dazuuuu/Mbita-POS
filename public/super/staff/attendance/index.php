<?php
// public/super/staff/attendance/index.php — staff login/logout tracking
require_once __DIR__ . '/../../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
StaffAttendanceService::ensureSchema($pdo);

$tenantId = (int) TenantContext::tenantId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$attSvc = new StaffAttendanceService($pdo);
$staffSvc = new StaffService($pdo);

$from = trim($_GET['from'] ?? date('Y-m-d', strtotime('-7 days')));
$to   = trim($_GET['to'] ?? date('Y-m-d'));
$staffFilter = (int) ($_GET['staff'] ?? 0);

$allStaff = $staffSvc->listForTenant($tenantId);
$rows = $attSvc->listForTenant($tenantId, $from, $to, $staffFilter ?: null);

$typeLabels = StaffRoles::typeLabels();

$page_title = 'Staff attendance';
ob_start();
?>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <p class="text-muted small mb-0">
      <strong>Late</strong> = login after 8:00 AM ·
      <strong>Early leave</strong> = logout before 3:00 PM
    </p>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('super/staff/'); ?>">
    <i class="fas fa-arrow-left me-1"></i>Back to staff
  </a>
</div>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <form method="get" class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label small fw-semibold">From</label>
        <input type="date" name="from" class="form-control" value="<?php echo htmlspecialchars($from); ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">To</label>
        <input type="date" name="to" class="form-control" value="<?php echo htmlspecialchars($to); ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Staff member</label>
        <select name="staff" class="form-select">
          <option value="">All staff</option>
          <?php foreach ($allStaff as $s): ?>
          <option value="<?php echo (int) $s['id']; ?>" <?php echo $staffFilter === (int) $s['id'] ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($s['username']); ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary w-100">Filter</button>
      </div>
    </form>
  </div>
</div>

<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-0">
    <?php if (!$rows): ?>
    <div class="p-5 text-center text-muted">No login records for this period yet.</div>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
          <tr class="small text-uppercase text-muted">
            <th class="ps-4">Date</th>
            <th>Staff</th>
            <th>Branch</th>
            <th>Logged in</th>
            <th>Logged out</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r):
            $loginLate = ($r['login_status'] ?? '') === 'late';
            $logoutEarly = ($r['logout_status'] ?? '') === 'early';
            $stillIn = empty($r['logout_at']);
            $typeKey = $r['staff_type'] ?? 'general';
          ?>
          <tr>
            <td class="ps-4 small"><?php echo htmlspecialchars(date('D, j M Y', strtotime($r['session_date']))); ?></td>
            <td>
              <div class="fw-semibold"><?php echo htmlspecialchars($r['username']); ?></div>
              <div class="small text-muted"><?php echo htmlspecialchars($typeLabels[$typeKey] ?? 'Staff'); ?></div>
            </td>
            <td class="small"><?php echo htmlspecialchars($r['branch_title'] ?? '—'); ?></td>
            <td class="small">
              <?php echo htmlspecialchars(date('g:i a', strtotime($r['login_at']))); ?>
              <?php if ($loginLate): ?>
              <span class="badge bg-warning text-dark ms-1">Late</span>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ($stillIn): ?>
              <span class="text-success">Still signed in</span>
              <?php else: ?>
              <?php echo htmlspecialchars(date('g:i a', strtotime($r['logout_at']))); ?>
              <?php if ($logoutEarly): ?>
              <span class="badge bg-info text-dark ms-1">Early</span>
              <?php endif; ?>
              <?php endif; ?>
            </td>
            <td class="small">
              <?php if ($loginLate && $logoutEarly): ?>
              <span class="text-danger">Late &amp; early</span>
              <?php elseif ($loginLate): ?>
              <span class="text-warning">Late arrival</span>
              <?php elseif ($logoutEarly): ?>
              <span class="text-info">Left early</span>
              <?php elseif ($stillIn): ?>
              <span class="text-muted">On shift</span>
              <?php else: ?>
              <span class="text-success">OK</span>
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
<?php
$content = ob_get_clean();
include __DIR__ . '/../../../templates/tenants/layout.php';
