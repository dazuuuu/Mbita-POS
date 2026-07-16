<?php
// public/super/staff/authorization.php — delegate features per staff member
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
Schema025Service::ensureApplied(Database::pdo());

$pdo = Database::pdo();
$svc = new StaffService($pdo);
$tenantId = TenantContext::tenantId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$businessType = $__tenant['business_type'] ?? 'shop';

$groups = StaffRoles::permissionGroups($businessType);
$manageable = StaffRoles::manageableCapabilities($businessType);

$flash = '';
$staffId = (int) ($_GET['staff'] ?? $_POST['staff_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $staffId) {
    $staff = $svc->findStaff($tenantId, $staffId);
    if ($staff) {
        $roleName = $staff['role_name'] ?? StaffRoles::roleForType($staff['staff_type'] ?? 'general');
        $roleDefaults = $svc->roleDefaultCaps($roleName);
        $desired = array_keys($_POST['caps'] ?? []);
        $desired = array_values(array_intersect($desired, $manageable));
        $svc->setCapabilities($tenantId, $staffId, $desired, $manageable, $roleDefaults);

        if (!empty($_POST['new_pin'])) {
            $pinRes = $svc->updatePin($tenantId, $staffId, trim($_POST['new_pin']));
            if (!$pinRes['ok']) {
                $_SESSION['flash']['error'] = $pinRes['error'];
                header('Location: ' . public_path('super/staff/authorization.php') . '?staff=' . $staffId);
                exit;
            }
        }

        $_SESSION['flash']['success'] = 'Authorization updated for ' . ($staff['username'] ?? 'staff') . '.';
        header('Location: ' . public_path('super/staff/authorization.php') . '?staff=' . $staffId);
        exit;
    }
    $flash = 'That staff member was not found.';
}

$staff = $staffId ? $svc->findStaff($tenantId, $staffId) : null;
$roleName = $staff ? ($staff['role_name'] ?? StaffRoles::roleForType($staff['staff_type'] ?? 'general')) : 'staff';
$roleId = $staff ? (int) ($staff['role_id'] ?? 0) : 0;
if (!$roleId) {
    $roleId = (int) ($svc->staffRoleId($staff['staff_type'] ?? null) ?? 0);
}
$roleDefaults = $svc->roleDefaultCaps($roleName);
$effective = $staff && $roleId ? $svc->effectiveCaps((int) $staff['id'], (int) $roleId) : [];
$allStaff = $svc->listForTenant($tenantId);
$typeLabels = StaffRoles::typeLabels();

$page_title = 'Staff authorization';
ob_start();
?>
<?php if (!empty($_SESSION['flash']['success'])): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
  <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
<?php endif; ?>
<?php if ($flash): ?><div class="alert alert-danger"><?php echo htmlspecialchars($flash); ?></div><?php endif; ?>

<?php if (!$staff): ?>
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h1 class="h5 mb-0 fw-bold">Staff authorization</h1>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('super/staff/'); ?>"><i class="fas fa-arrow-left me-1"></i>Back to staff</a>
  </div>
  <p class="text-muted">Choose a staff member to delegate what they can do. Each role starts with sensible defaults — toggle any feature on or off.</p>
  <?php if (!$allStaff): ?>
    <div class="card border-0 shadow-sm" style="border-radius:14px;"><div class="card-body p-5 text-center text-muted">
      No staff yet. <a href="<?php echo public_path('super/staff/'); ?>">Add a staff member</a> first.
    </div></div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($allStaff as $s):
      $rn = $s['role_name'] ?? StaffRoles::roleForType($s['staff_type'] ?? 'general');
      $rid = (int) ($s['role_id'] ?? $svc->staffRoleId($s['staff_type'] ?? null) ?? 0);
      $eff = $rid ? $svc->effectiveCaps((int) $s['id'], (int) $rid) : [];
      $defs = $svc->roleDefaultCaps($rn);
      $extra = count(array_diff(array_intersect($eff, $manageable), $defs));
      $typeKey = $s['staff_type'] ?? 'general';
    ?>
    <div class="col-12 col-md-6 col-lg-4">
      <a class="card border-0 shadow-sm h-100 text-decoration-none text-reset" style="border-radius:14px;" href="?staff=<?php echo (int) $s['id']; ?>">
        <div class="card-body p-3 d-flex align-items-center gap-3">
          <span class="d-flex align-items-center justify-content-center flex-shrink-0" style="width:44px;height:44px;border-radius:11px;background:#eef2ff;color:#4f46e5;font-weight:700;">
            <?php echo strtoupper(substr($s['username'] ?? '?', 0, 1)); ?>
          </span>
          <div class="flex-grow-1 min-w-0">
            <div class="fw-semibold text-truncate"><?php echo htmlspecialchars($s['username']); ?></div>
            <div class="small text-muted text-truncate"><?php echo htmlspecialchars($typeLabels[$typeKey] ?? $rn); ?></div>
          </div>
          <span class="badge <?php echo $extra ? 'bg-success' : 'bg-light text-dark'; ?>"><?php echo $extra ? ('+' . $extra . ' granted') : 'defaults'; ?></span>
        </div>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

<?php else:
  $typeKey = $staff['staff_type'] ?? 'general';
?>
  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
      <h1 class="h5 mb-0 fw-bold"><?php echo htmlspecialchars($staff['username']); ?></h1>
      <div class="small text-muted">
        <?php echo htmlspecialchars($typeLabels[$typeKey] ?? $roleName); ?>
        · <?php echo htmlspecialchars($staff['branch_title'] ?? 'All branches'); ?>
        · PIN login only
      </div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('super/staff/authorization.php'); ?>"><i class="fas fa-arrow-left me-1"></i>All staff</a>
  </div>

  <div class="alert alert-info py-2 small"><i class="fas fa-circle-info me-1"></i> Changes take effect the next time this staff member logs in with their PIN.</div>

  <form method="post">
    <input type="hidden" name="staff_id" value="<?php echo (int) $staff['id']; ?>">

    <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3">Reset PIN</h2>
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <label class="form-label small">New PIN (4–5 digits)</label>
            <input name="new_pin" type="password" inputmode="numeric" pattern="\d{4,5}" maxlength="5" class="form-control" placeholder="Leave blank to keep current" autocomplete="off">
          </div>
          <div class="col-md-8">
            <p class="small text-muted mb-0">Staff sign in at the login page → <strong>Staff PIN</strong> tab using shop code <code><?php echo htmlspecialchars($__tenant['slug'] ?? ''); ?></code>.</p>
          </div>
        </div>
      </div>
    </div>

    <?php foreach ($groups as $title => $rows): ?>
    <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><?php echo htmlspecialchars($title); ?></h2>
        <?php foreach ($rows as $r):
          [$cap, $label, $desc] = $r;
          $isDefault = in_array($cap, $roleDefaults, true);
          $isOn = in_array($cap, $effective, true);
          $id = 'cap_' . str_replace('.', '_', $cap); ?>
        <div class="form-check form-switch perm-row d-flex align-items-start gap-2 py-2 <?php echo $isOn ? '' : 'is-off'; ?>" style="padding-left:3.2em;">
          <input class="form-check-input mt-1" type="checkbox" role="switch" id="<?php echo $id; ?>" name="caps[<?php echo $cap; ?>]" value="1" <?php echo $isOn ? 'checked' : ''; ?>>
          <label class="form-check-label flex-grow-1" for="<?php echo $id; ?>">
            <span class="fw-semibold"><?php echo htmlspecialchars($label); ?></span>
            <?php if (!$isDefault): ?><span class="badge bg-light text-dark ms-1" style="font-weight:500;">off by default</span><?php endif; ?>
            <span class="d-block small text-muted"><?php echo htmlspecialchars($desc); ?></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="d-flex gap-2">
      <button class="btn btn-primary" type="submit"><i class="fas fa-floppy-disk me-1"></i>Save authorization</button>
      <a class="btn btn-outline-secondary" href="<?php echo public_path('super/staff/authorization.php'); ?>">Cancel</a>
    </div>
  </form>

  <style>
    .perm-row.is-off .form-check-label .fw-semibold { color:#94a3b8; }
    .perm-row.is-off { opacity:.85; }
  </style>
  <script>
    document.querySelectorAll('.perm-row .form-check-input').forEach(function (cb) {
      cb.addEventListener('change', function () { cb.closest('.perm-row').classList.toggle('is-off', !cb.checked); });
    });
  </script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
