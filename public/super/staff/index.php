<?php
// public/super/staff/index.php — create staff with PIN (no email login)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();
Schema025Service::ensureApplied(Database::pdo());
Schema026Service::ensureApplied(Database::pdo());
Schema029Service::ensureApplied(Database::pdo());

$pdo = Database::pdo();
$tenantId = TenantContext::tenantId();
$svc = new StaffService($pdo);
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$__locations = (new Models\BranchModel($pdo))->listWithCounts();
$branchFilter = (int) ($_GET['branch'] ?? 0);
$defaultBranch = $branchFilter ?: (int) ($__locations[0]['id'] ?? 0);
$modules = TenantModules::effectiveForTenant($__tenant, $__locations);
$showWholesale = !empty($modules[TenantModules::WHOLESALE]);
$availableTypes = StaffRoles::availableStaffTypes($modules);

$editId = (int) ($_GET['edit'] ?? $_POST['staff_id'] ?? 0);
$editRow = $editId ? $svc->findStaff((int) $tenantId, $editId) : null;
if (!$editRow) {
    $editId = 0;
}

$errors = [];
$old = ['name' => '', 'pin' => '', 'staff_type' => 'general', 'branch_id' => ''];

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

    if ($action === 'toggle') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $enable = !empty($_POST['enable']);
        $ok = $enable ? $svc->activate((int) $tenantId, $staffId) : $svc->deactivate((int) $tenantId, $staffId);
        $_SESSION['flash'][$ok ? 'success' : 'error'] = $ok
            ? ($enable ? 'Staff member reactivated.' : 'Staff member deactivated.')
            : 'Could not update staff status.';
        header('Location: ' . public_path('super/staff/'));
        exit;
    }

    if ($action === 'update') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $old = [
            'name'       => trim($_POST['name'] ?? ''),
            'staff_type' => $_POST['staff_type'] ?? 'general',
            'branch_id'  => $_POST['branch_id'] ?? '',
            'pin'        => trim($_POST['pin'] ?? ''),
        ];
        $res = $svc->update((int) $tenantId, $staffId, $old);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Staff member updated.';
            header('Location: ' . public_path('super/staff/'));
            exit;
        }
        $errors = $res['errors'];
        $editId = $staffId;
        $editRow = $svc->findStaff((int) $tenantId, $staffId);
    }

    if ($action === 'create') {
        $old = [
            'name'       => trim($_POST['name'] ?? ''),
            'pin'        => trim($_POST['pin'] ?? ''),
            'staff_type' => $_POST['staff_type'] ?? 'general',
            'branch_id'  => $_POST['branch_id'] ?? '',
        ];

        $res = $svc->create((int) $tenantId, $old);

        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Staff account created. They can sign in with their PIN on the Staff login screen.';
            header('Location: ' . public_path('super/staff/authorization.php') . '?staff=' . (int) $res['user_id']);
            exit;
        }
        $errors = $res['errors'];
    }
}

$formOld = $editRow ? [
    'name'       => $old['name'] !== '' ? $old['name'] : $editRow['username'],
    'staff_type' => $old['staff_type'] ?? ($editRow['staff_type'] ?? 'general'),
    'branch_id'  => $old['branch_id'] !== '' ? $old['branch_id'] : ($editRow['branch_id'] ?? ''),
    'pin'        => '',
] : $old;

$staff = $svc->listForTenant((int) $tenantId, $branchFilter ?: null);
$typeLabels = StaffRoles::typeLabels();
$page_title = 'Staff';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1"><?php echo $editRow ? 'Edit staff' : 'Add staff'; ?></h2>
        <p class="text-muted small mb-3">
          <?php if ($editRow): ?>
            Update name, role, or branch. Leave PIN blank to keep the current one. Staff cannot change their own PIN.
          <?php else: ?>
            Staff tap <strong>Staff</strong> on the login screen and enter their PIN — no shop code needed.
          <?php endif; ?>
        </p>
        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="<?php echo $editRow ? 'update' : 'create'; ?>">
          <?php if ($editRow): ?><input type="hidden" name="staff_id" value="<?php echo (int)$editRow['id']; ?>"><?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Full name</label>
            <input name="name" class="form-control" placeholder="e.g. Alice Wanjiru" required value="<?php echo htmlspecialchars($formOld['name']); ?>">
            <?php if (!empty($errors['name'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label"><?php echo $editRow ? 'New PIN (optional)' : 'Login PIN (4–5 digits)'; ?></label>
            <input name="pin" type="password" inputmode="numeric" pattern="\d{4,5}" maxlength="5"
                   class="form-control" placeholder="<?php echo $editRow ? 'Leave blank to keep current PIN' : 'e.g. 1234'; ?>"
                   <?php echo $editRow ? '' : 'required'; ?> autocomplete="off">
            <?php if (!empty($errors['pin'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['pin']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="staff_type" class="form-select" required>
              <?php foreach ($typeLabels as $val => $label):
                if (!in_array($val, $availableTypes, true)) {
                    continue;
                }
              ?>
              <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($formOld['staff_type'] ?? '') === $val ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['staff_type'])): ?><small class="text-danger d-block"><?php echo htmlspecialchars($errors['staff_type']); ?></small><?php endif; ?>
          </div>
          <?php if ($__locations): ?>
          <div class="mb-3">
            <label class="form-label"><?php echo $editRow ? 'Branch / shop (transfer)' : 'Branch / shop'; ?></label>
            <select name="branch_id" class="form-select" required>
              <option value="">— Select location —</option>
              <?php foreach ($__locations as $loc): ?>
              <option value="<?php echo (int)$loc['id']; ?>" <?php echo (string)($formOld['branch_id'] ?? $defaultBranch) === (string)$loc['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($loc['title']); ?> (<?php echo htmlspecialchars(TenantModules::locationLabel($loc)); ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <?php if ($editRow): ?><small class="text-muted">Change location to transfer this employee to another branch or shop.</small><?php endif; ?>
            <?php if (!empty($errors['branch_id'])): ?><small class="text-danger d-block"><?php echo htmlspecialchars($errors['branch_id']); ?></small><?php endif; ?>
          </div>
          <?php endif; ?>
          <button class="btn btn-primary"><?php echo $editRow ? 'Save changes' : 'Create staff &amp; set permissions'; ?></button>
          <?php if ($editRow): ?><a class="btn btn-link" href="<?php echo public_path('super/staff/'); ?>">Cancel</a><?php endif; ?>
        </form>
      </div>
    </div>

    <?php if (!$editRow): ?>
    <div class="card border-0 shadow-sm mt-3" style="border-radius:12px;">
      <div class="card-body p-4 small text-muted">
        <strong>Role guide</strong>
        <ul class="mb-0 ps-3 mt-2">
          <?php foreach (StaffRoles::typeDescriptions() as $type => $desc):
            if (!in_array($type, $availableTypes, true)) continue;
          ?>
          <li class="mb-1"><strong><?php echo htmlspecialchars(StaffRoles::typeLabels()[$type]); ?>:</strong> <?php echo htmlspecialchars($desc); ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <h2 class="h5 mb-0">Your team <span class="badge bg-light text-dark"><?php echo count($staff); ?></span></h2>
          <div class="d-flex gap-2 align-items-center flex-wrap">
            <?php if ($__locations): ?>
            <form method="get" class="d-flex gap-2 align-items-center">
              <label class="small text-muted mb-0">Location</label>
              <select name="branch" class="form-select form-select-sm" style="width:auto;" onchange="this.form.submit()">
                <option value="">All locations</option>
                <?php foreach ($__locations as $loc): ?>
                <option value="<?php echo (int)$loc['id']; ?>" <?php echo $branchFilter === (int)$loc['id'] ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($loc['title']); ?>
                </option>
                <?php endforeach; ?>
              </select>
            </form>
            <?php endif; ?>
            <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('super/staff/authorization.php'); ?>">User Access</a>
          </div>
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
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo public_path('super/staff/'); ?>?edit=<?php echo (int)$s['id']; ?>">Edit</a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('super/staff/authorization.php'); ?>?staff=<?php echo (int)$s['id']; ?>">Permissions</a>
                    <form method="post" class="d-inline">
                      <input type="hidden" name="action" value="toggle">
                      <input type="hidden" name="staff_id" value="<?php echo (int) $s['id']; ?>">
                      <input type="hidden" name="enable" value="<?php echo (int)$s['is_active'] ? '0' : '1'; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-secondary"><?php echo (int)$s['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                    </form>
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
