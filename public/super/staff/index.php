<?php
// public/super/staff/index.php — create staff with PIN (no email login)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
Schema025Service::ensureApplied(Database::pdo());

$pdo = Database::pdo();
$tenantId = TenantContext::tenantId();
$svc = new StaffService($pdo);
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$businessType = $__tenant['business_type'] ?? 'shop';

$errors = [];
$old = ['name' => '', 'pin' => '', 'staff_type' => 'general'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $res = $svc->delete((int) $tenantId, (int) ($_POST['staff_id'] ?? 0));
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
            ? 'Staff member removed.'
            : $res['error'];
        header('Location: ' . public_path('super/staff/'));
        exit;
    }

    if ($action === 'create') {
        $old = [
            'name'       => trim($_POST['name'] ?? ''),
            'pin'        => trim($_POST['pin'] ?? ''),
            'staff_type' => $_POST['staff_type'] ?? 'general',
        ];

        $res = $svc->create((int) $tenantId, $old);

        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Staff account created. They can log in with shop code '
                . htmlspecialchars($__tenant['slug'] ?? '') . ' and their PIN.';
            header('Location: ' . public_path('super/staff/authorization.php')?staff=) $res['user_id']);
            exit;
        }
        $errors = $res['errors'];
    }
}

$staff = $svc->listForTenant((int) $tenantId);
$typeLabels = StaffRoles::typeLabels();
$page_title = 'Staff';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Add staff</h2>
        <p class="text-muted small mb-3">
          Staff log in with your shop code <strong><?php echo htmlspecialchars($__tenant['slug'] ?? ''); ?></strong>
          and a 4–5 digit PIN — no email required. Set their role, then delegate features on the authorization page.
        </p>
        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create">
          <div class="mb-3">
            <label class="form-label">Full name</label>
            <input name="name" class="form-control" placeholder="e.g. Alice Wanjiru" required value="<?php echo htmlspecialchars($old['name']); ?>">
            <?php if (!empty($errors['name'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Login PIN (4–5 digits)</label>
            <input name="pin" type="password" inputmode="numeric" pattern="\d{4,5}" maxlength="5"
                   class="form-control" placeholder="e.g. 1234" required autocomplete="off">
            <?php if (!empty($errors['pin'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['pin']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="staff_type" class="form-select" required>
              <?php foreach ($typeLabels as $val => $label):
                if ($businessType === 'shop' && $val === 'barber') {
                    continue;
                }
                if ($businessType === 'barbershop_salon' && $val === 'general') {
                    continue;
                }
              ?>
              <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($old['staff_type'] ?? '') === $val ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <small class="text-muted">Each role has default permissions you can customize after creation.</small>
            <?php if (!empty($errors['staff_type'])): ?><small class="text-danger d-block"><?php echo htmlspecialchars($errors['staff_type']); ?></small><?php endif; ?>
          </div>
          <button class="btn btn-primary">Create staff &amp; set permissions</button>
        </form>
      </div>
    </div>

    <div class="card border-0 shadow-sm mt-3" style="border-radius:12px;">
      <div class="card-body p-4 small text-muted">
        <strong>Role guide</strong>
        <ul class="mb-0 ps-3 mt-2">
          <?php foreach (StaffRoles::typeDescriptions() as $type => $desc):
            if ($businessType === 'shop' && $type === 'barber') continue;
            if ($businessType === 'barbershop_salon' && $type === 'general') continue;
          ?>
          <li class="mb-1"><strong><?php echo htmlspecialchars(StaffRoles::typeLabels()[$type]); ?>:</strong> <?php echo htmlspecialchars($desc); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="h5 mb-0">Your team <span class="badge bg-light text-dark"><?php echo count($staff); ?></span></h2>
          <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path(\'super/staff/authorization.php\'); ?>">Authorization</a>
        </div>
        <?php if (!$staff): ?>
          <div class="text-muted">No staff yet. Add your first team member on the left.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Role</th><th>Branch</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($staff as $s):
                  $typeKey = $s['staff_type'] ?? 'general';
                  $roleLabel = $typeLabels[$typeKey] ?? ucfirst($s['role_name'] ?? 'Staff');
                ?>
                <tr>
                  <td class="fw-semibold"><?php echo htmlspecialchars($s['username']); ?></td>
                  <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($roleLabel); ?></span></td>
                  <td><?php echo htmlspecialchars($s['branch_title'] ?? 'All branches'); ?></td>
                  <td>
                    <?php if ((int)$s['is_active'] === 1): ?>
                      <span class="badge bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo public_path('super/staff/authorization.php'); ?>?staff=<?php echo (int)$s['id']; ?>">Permissions</a>
                    <form method="post" class="d-inline" onsubmit="return confirm('Permanently delete this staff member and ALL their sales?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
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
include __DIR__ . '/../../templates/tenants/layout.php';
