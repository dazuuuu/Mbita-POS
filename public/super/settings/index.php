<?php
// public/super/settings/index.php — locations (branch/shop), modules, receipts, customers, staff
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
SchemaHelper::clearCache();
GeneralMigrationService::ensureApplied($pdo);
Schema024Service::ensureApplied($pdo);
Schema026Service::ensureApplied($pdo);
Schema020Service::ensureApplied($pdo);

$tenantId = (int) TenantContext::tenantId();
$tenantModel = new Models\TenantModel($pdo);
$branchModel = new Models\BranchModel($pdo);
$staffSvc = new StaffService($pdo);
$custSvc = new CustomerService($pdo);
$tab = $_GET['tab'] ?? 'locations';

function save_tenant_logo(array $file, int $tenantId): array
{
    if ($file['error'] !== UPLOAD_ERR_OK)               return ['ok' => false, 'error' => 'Upload failed.'];
    if ($file['size'] > 2 * 1024 * 1024)                return ['ok' => false, 'error' => 'Logo must be under 2MB.'];
    $info = @getimagesize($file['tmp_name']);
    $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
    if (!$info || !isset($allowed[$info['mime']]))      return ['ok' => false, 'error' => 'Logo must be PNG, JPG or WEBP.'];
    $dir = ROOT_PATH . '/public/uploads/branding';
    if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
    $name = 'tenant_' . $tenantId . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$info['mime']];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        return ['ok' => false, 'error' => 'Could not save the logo.'];
    }
    return ['ok' => true, 'path' => '/public/uploads/branding/' . $name];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_branch') {
        $title = trim($_POST['title'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $branchType = $_POST['branch_type'] ?? 'barbershop';
        if (!TenantModules::isBranchType($branchType)) {
            $_SESSION['flash']['error'] = 'Choose Barbershop, Salon, or Barbershop & Salon.';
        } else {
            $res = $branchModel->create($title, $location, $branchType);
            $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
                ? 'Branch "' . $title . '" created. Set its features on the Modules tab.'
                : ($res['error'] ?? 'Could not create branch.');
        }
        header('Location: ' . public_path('super/settings/?tab=locations')); exit;
    }

    if ($action === 'create_shop') {
        $title = trim($_POST['shop_title'] ?? '');
        $location = trim($_POST['shop_location'] ?? '');
        $res = $branchModel->create($title, $location, 'shop');
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
            ? 'Shop "' . $title . '" created with retail & wholesale POS defaults. Add products next.'
            : ($res['error'] ?? 'Could not create shop.');
        header('Location: ' . public_path('super/settings/?tab=locations')); exit;
    }

    if ($action === 'update_location') {
        $branchId = (int) ($_POST['branch_id'] ?? 0);
        $branch = $branchModel->find($branchId);
        $title = trim($_POST['title'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $branchType = $_POST['branch_type'] ?? null;
        if ($branch && ($branch['branch_type'] ?? '') === 'shop') {
            $branchType = 'shop';
        }
        $res = $branchModel->updateLocation($branchId, $title, $location, $branchType);
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
            ? 'Location updated.'
            : ($res['error'] ?? 'Could not save changes.');
        header('Location: ' . public_path('super/settings/?tab=locations')); exit;
    }

    if ($action === 'delete_location') {
        $res = $branchModel->deleteSafe((int) ($_POST['branch_id'] ?? 0));
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
            ? 'Location removed.'
            : ($res['error'] ?? 'Could not delete location.');
        header('Location: ' . public_path('super/settings/?tab=locations')); exit;
    }

    if ($action === 'location_modules') {
        $branchId = (int) ($_POST['branch_id'] ?? 0);
        $mods = TenantModules::sanitizePosted($_POST['modules'] ?? []);
        if ($branchModel->updateModules($branchId, $mods)) {
            $_SESSION['flash']['success'] = 'Modules saved for this location.';
        } else {
            $_SESSION['flash']['error'] = 'Could not save modules. Run fix-schema-026.php once.';
        }
        header('Location: ' . public_path('super/settings/?tab=modules&branch=' . $branchId)); exit;
    }

    if ($action === 'receipts') {
        $data = [
            'name'           => trim($_POST['name'] ?? ''),
            'phone'          => trim($_POST['phone'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
            'location'       => trim($_POST['location'] ?? ''),
            'kra_pin'        => trim($_POST['kra_pin'] ?? ''),
            'currency'       => trim($_POST['currency'] ?? 'KES'),
            'receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
            'credits_enabled'=> !empty($_POST['credits_enabled']) ? 1 : 0,
        ];
        if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
            $logo = save_tenant_logo($_FILES['logo'], $tenantId);
            if ($logo['ok']) { $data['logo_path'] = $logo['path']; }
            else { $_SESSION['flash']['error'] = $logo['error']; }
        }
        if ($data['name'] === '') {
            $_SESSION['flash']['error'] = 'Business name is required.';
        } else {
            $tenantModel->updateSettings($tenantId, $data);
            if (empty($_SESSION['flash']['error'])) {
                $_SESSION['flash']['success'] = 'Receipt settings saved.';
            }
        }
        header('Location: ' . public_path('super/settings/?tab=receipts')); exit;
    }

    if ($action === 'customer_save') {
        $in = ['name' => $_POST['name'] ?? '', 'phone' => $_POST['phone'] ?? '', 'email' => $_POST['email'] ?? '', 'notes' => $_POST['notes'] ?? ''];
        $cid = (int) ($_POST['customer_id'] ?? 0);
        $res = $cid ? $custSvc->update($tenantId, $cid, $in) : $custSvc->create($tenantId, $in);
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? 'Customer saved.' : ($res['errors']['name'] ?? $res['errors']['_'] ?? 'Could not save.');
        header('Location: ' . public_path('super/settings/?tab=customers')); exit;
    }

    if ($action === 'customer_delete') {
        $custSvc->delete($tenantId, (int) ($_POST['customer_id'] ?? 0));
        $_SESSION['flash']['success'] = 'Customer removed.';
        header('Location: ' . public_path('super/settings/?tab=customers')); exit;
    }

    if ($action === 'purge_staff') {
        $confirm = trim($_POST['confirm_name'] ?? '');
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $staff = $staffSvc->findStaff($tenantId, $staffId);
        if (!$staff || $confirm !== $staff['username']) {
            $_SESSION['flash']['error'] = 'Type the staff member\'s exact name to confirm permanent deletion.';
        } else {
            $res = $staffSvc->purge($tenantId, $staffId);
            $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
                ? 'Staff member and all their sales data permanently removed.'
                : $res['error'];
        }
        header('Location: ' . public_path('super/settings/?tab=staff')); exit;
    }

    if ($action === 'staff_toggle') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $enable = !empty($_POST['enable']);
        $ok = $enable ? $staffSvc->activate($tenantId, $staffId) : $staffSvc->deactivate($tenantId, $staffId);
        $_SESSION['flash'][$ok ? 'success' : 'error'] = $ok
            ? ($enable ? 'Staff member reactivated.' : 'Staff member deactivated.')
            : 'Could not update staff status.';
        header('Location: ' . public_path('super/settings/?tab=staff')); exit;
    }

    if ($action === 'staff_update') {
        $staffId = (int) ($_POST['staff_id'] ?? 0);
        $in = [
            'name'       => $_POST['name'] ?? '',
            'staff_type' => $_POST['staff_type'] ?? 'general',
            'branch_id'  => $_POST['branch_id'] ?? '',
            'pin'        => trim($_POST['pin'] ?? ''),
        ];
        $res = $staffSvc->update($tenantId, $staffId, $in);
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
            ? 'Staff member updated.'
            : ($res['errors']['name'] ?? $res['errors']['pin'] ?? $res['errors']['_'] ?? 'Could not save changes.');
        header('Location: ' . public_path('super/settings/?tab=staff' . ($res['ok'] ? '' : '&edit_staff=' . $staffId))); exit;
    }
}

$__tenant = $tenantModel->find($tenantId);
$locations = $branchModel->listWithCounts();
$__locations = $locations;
$staff = $staffSvc->listForTenant($tenantId);
$schemaReady = SchemaHelper::migration024Ready($pdo);
$modulesReady = SchemaHelper::columnExists($pdo, 'branches', 'modules');
$branchTypes = TenantModules::branchTypeLabels();
$selectedBranchId = (int) ($_GET['branch'] ?? ($locations[0]['id'] ?? 0));
$selectedBranch = $selectedBranchId ? $branchModel->find($selectedBranchId) : null;
$locationModules = $selectedBranch ? TenantModules::fromBranch($selectedBranch) : [];

$customers = $schemaReady ? $custSvc->listForTenant($tenantId) : [];
$editCustomer = (int) ($_GET['edit_customer'] ?? 0);
$editCustomerRow = $editCustomer ? $custSvc->find($tenantId, $editCustomer) : null;

$oldBranch = ['title' => '', 'location' => '', 'branch_type' => 'barbershop'];
$oldShop = ['shop_title' => '', 'shop_location' => ''];
$editLocationId = (int) ($_GET['edit_location'] ?? 0);
$editLocation = $editLocationId ? $branchModel->find($editLocationId) : null;
$editStaffId = (int) ($_GET['edit_staff'] ?? 0);
$editStaffRow = $editStaffId ? $staffSvc->findStaff($tenantId, $editStaffId) : null;
$modules = TenantModules::effectiveForTenant($__tenant, $locations);
$staffTypes = StaffRoles::availableStaffTypes($modules);

$page_title = 'Settings';
ob_start();
?>
<?php if (!$schemaReady): ?>
<div class="alert alert-warning">
  <strong>Database update needed.</strong> KRA PIN, receipts, customers and credits require migration 024.
  Open <a href="<?php echo public_path('devs/fix-all-schema.php'); ?>" class="alert-link">fix-all-schema.php</a> once (recommended), then refresh.
</div>
<?php endif; ?>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'locations' ? 'active' : ''; ?>" href="?tab=locations">Branches &amp; Shops</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'modules' ? 'active' : ''; ?>" href="?tab=modules">Modules</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'receipts' ? 'active' : ''; ?>" href="?tab=receipts">Receipts</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'customers' ? 'active' : ''; ?>" href="?tab=customers">Customers</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'staff' ? 'active' : ''; ?>" href="?tab=staff">Staff</a></li>
</ul>

<?php if ($tab === 'locations'): ?>
<p class="text-muted small mb-4">Start here. Create a <strong>branch</strong> (barbershop / salon) or a <strong>shop</strong> (retail &amp; wholesale POS). Then choose features for each on the <a href="?tab=modules">Modules</a> tab.</p>
<div class="row g-4">
  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1"><i class="fas fa-scissors text-primary me-1"></i> Create branch</h2>
        <p class="text-muted small mb-3">Barbershop, salon, or both. Title is the name your staff will see.</p>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create_branch">
          <div class="mb-3">
            <label class="form-label">Branch name</label>
            <input name="title" class="form-control" placeholder="e.g. Westy Barbershop" value="<?php echo htmlspecialchars($oldBranch['title']); ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="branch_type" class="form-select" required>
              <?php foreach ($branchTypes as $val => $label): ?>
              <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($oldBranch['branch_type'] ?? '') === $val ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($label); ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Location <span class="text-muted">(optional)</span></label>
            <input name="location" class="form-control" placeholder="e.g. Westlands Mall" value="<?php echo htmlspecialchars($oldBranch['location']); ?>">
          </div>
          <button class="btn btn-primary w-100">Create branch</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1"><i class="fas fa-store text-success me-1"></i> Create shop</h2>
        <p class="text-muted small mb-3">Retail &amp; wholesale POS. Products get retail + wholesale prices when you add stock.</p>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="create_shop">
          <div class="mb-3">
            <label class="form-label">Shop name</label>
            <input name="shop_title" class="form-control" placeholder="e.g. Main Store" value="<?php echo htmlspecialchars($oldShop['shop_title']); ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Location <span class="text-muted">(optional)</span></label>
            <input name="shop_location" class="form-control" placeholder="e.g. CBD" value="<?php echo htmlspecialchars($oldShop['shop_location']); ?>">
          </div>
          <button class="btn btn-success w-100">Create shop</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Your locations <span class="badge bg-light text-dark"><?php echo count($locations); ?></span></h2>
        <?php if (!$locations): ?>
          <p class="text-muted small mb-0">No branches or shops yet. Create one on the left.</p>
        <?php else: ?>
          <div class="list-group list-group-flush">
            <?php foreach ($locations as $loc):
              $typeLabel = TenantModules::locationLabel($loc);
              $isShop = ($loc['branch_type'] ?? 'shop') === 'shop';
              $isEditing = $editLocation && (int)$editLocation['id'] === (int)$loc['id'];
            ?>
            <?php if ($isEditing): ?>
            <form method="post" class="list-group-item px-0 border-0 mb-3">
              <input type="hidden" name="action" value="update_location">
              <input type="hidden" name="branch_id" value="<?php echo (int)$loc['id']; ?>">
              <div class="mb-2">
                <label class="form-label small mb-1">Name</label>
                <input name="title" class="form-control form-control-sm" required value="<?php echo htmlspecialchars($loc['title']); ?>">
              </div>
              <?php if (!$isShop): ?>
              <div class="mb-2">
                <label class="form-label small mb-1">Type</label>
                <select name="branch_type" class="form-select form-select-sm">
                  <?php foreach ($branchTypes as $val => $label): ?>
                  <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($loc['branch_type'] ?? '') === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <?php else: ?>
              <input type="hidden" name="branch_type" value="shop">
              <?php endif; ?>
              <div class="mb-2">
                <label class="form-label small mb-1">Location</label>
                <input name="location" class="form-control form-control-sm" value="<?php echo htmlspecialchars($loc['location'] ?? ''); ?>">
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <button class="btn btn-sm btn-primary">Save</button>
                <a class="btn btn-sm btn-outline-secondary" href="?tab=locations">Cancel</a>
              </div>
            </form>
            <?php else: ?>
            <div class="list-group-item px-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
              <div>
                <div class="fw-semibold"><?php echo htmlspecialchars($loc['title']); ?></div>
                <small class="text-muted"><?php echo htmlspecialchars($typeLabel); ?><?php echo $loc['location'] ? ' · ' . htmlspecialchars($loc['location']) : ''; ?><?php if ((int)($loc['staff_count'] ?? 0) > 0): ?> · <?php echo (int)$loc['staff_count']; ?> staff<?php endif; ?></small>
              </div>
              <div class="d-flex gap-1 flex-wrap">
                <a class="btn btn-sm btn-outline-secondary" href="?tab=locations&edit_location=<?php echo (int)$loc['id']; ?>">Edit</a>
                <a class="btn btn-sm btn-outline-primary" href="?tab=modules&branch=<?php echo (int)$loc['id']; ?>">Modules</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this location? This cannot be undone.');">
                  <input type="hidden" name="action" value="delete_location">
                  <input type="hidden" name="branch_id" value="<?php echo (int)$loc['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
                </form>
              </div>
            </div>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php elseif ($tab === 'modules'): ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">Modules per location</h2>
        <p class="text-muted small mb-4">Choose what each branch or shop can do — services, products, commissions, reception, staff, etc.</p>
        <?php if (!$locations): ?>
          <div class="alert alert-info mb-0">Create a branch or shop on the <a href="?tab=locations">Branches &amp; Shops</a> tab first.</div>
        <?php elseif (!$modulesReady): ?>
          <div class="alert alert-warning">Run <a href="<?php echo public_path('devs/fix-schema-026.php'); ?>">fix-schema-026.php</a> once, then refresh.</div>
        <?php else: ?>
          <form method="get" class="mb-4">
            <input type="hidden" name="tab" value="modules">
            <label class="form-label fw-semibold">Location</label>
            <select name="branch" class="form-select" onchange="this.form.submit()">
              <?php foreach ($locations as $loc): ?>
              <option value="<?php echo (int)$loc['id']; ?>" <?php echo $selectedBranchId === (int)$loc['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($loc['title']); ?> (<?php echo htmlspecialchars(TenantModules::locationLabel($loc)); ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php if ($selectedBranch): ?>
          <form method="post">
            <input type="hidden" name="action" value="location_modules">
            <input type="hidden" name="branch_id" value="<?php echo (int)$selectedBranch['id']; ?>">
            <p class="small text-muted mb-3">Configuring: <strong><?php echo htmlspecialchars($selectedBranch['title']); ?></strong></p>
            <?php foreach (TenantModules::labels() as $key => $label): ?>
            <div class="form-check form-switch mb-3">
              <input class="form-check-input" type="checkbox" role="switch" id="mod_<?php echo $key; ?>"
                name="modules[<?php echo htmlspecialchars($key); ?>]" value="1"
                <?php echo !empty($locationModules[$key]) ? 'checked' : ''; ?>>
              <label class="form-check-label" for="mod_<?php echo $key; ?>">
                <span class="fw-semibold"><?php echo htmlspecialchars($label); ?></span>
                <span class="d-block small text-muted"><?php echo htmlspecialchars(TenantModules::descriptions()[$key]); ?></span>
              </label>
            </div>
            <?php endforeach; ?>
            <button class="btn btn-primary">Save modules for this location</button>
          </form>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm p-4 small text-muted" style="border-radius:12px;">
      <strong>Typical setups</strong>
      <ul class="mb-0 ps-3 mt-2">
        <li class="mb-2"><strong>Barbershop branch</strong> — Services, Service commissions, Reception, Staff.</li>
        <li class="mb-2"><strong>Shop</strong> — Products, Wholesale pricing, Cashier, Product commissions.</li>
        <li class="mb-2"><strong>Both</strong> — Enable everything you need; nothing is forced.</li>
      </ul>
      <hr>
      <a href="<?php echo public_path('super/staff/authorization.php'); ?>">User Access</a> — fine-tune permissions per staff member.
    </div>
  </div>
</div>

<?php elseif ($tab === 'receipts'): ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Receipt &amp; business details</h2>
        <p class="text-muted small">Shown on every receipt — business name, KRA PIN, location, footer.</p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="receipts">
          <div class="mb-3">
            <label class="form-label fw-semibold">Business name</label>
            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($__tenant['name'] ?? ''); ?>">
          </div>
          <div class="row g-2">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">KRA PIN</label>
              <input name="kra_pin" class="form-control" value="<?php echo htmlspecialchars($__tenant['kra_pin'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Phone</label>
              <input name="phone" class="form-control" value="<?php echo htmlspecialchars($__tenant['phone'] ?? ''); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Location</label>
            <input name="location" class="form-control" value="<?php echo htmlspecialchars($__tenant['location'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Address</label>
            <input name="address" class="form-control" value="<?php echo htmlspecialchars($__tenant['address'] ?? ''); ?>">
          </div>
          <div class="row g-2">
            <div class="col-md-4 mb-3">
              <label class="form-label fw-semibold">Currency</label>
              <input name="currency" class="form-control" maxlength="8" value="<?php echo htmlspecialchars($__tenant['currency'] ?? 'KES'); ?>">
            </div>
            <div class="col-md-8 mb-3">
              <label class="form-label fw-semibold">Receipt footer</label>
              <input name="receipt_footer" class="form-control" value="<?php echo htmlspecialchars($__tenant['receipt_footer'] ?? ''); ?>">
            </div>
          </div>
          <div class="mb-3 form-check <?php echo $schemaReady ? '' : 'opacity-50'; ?>">
            <input type="checkbox" class="form-check-input" name="credits_enabled" id="credits" value="1"
              <?php echo !empty($__tenant['credits_enabled']) ? 'checked' : ''; ?> <?php echo $schemaReady ? '' : 'disabled'; ?>>
            <label class="form-check-label" for="credits">Enable product credit sales</label>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Logo</label>
            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
          </div>
          <button class="btn btn-primary">Save receipt settings</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card border-0 shadow-sm text-center p-4" style="border-radius:12px;">
      <div class="text-muted small mb-2">Receipt preview logo</div>
      <img src="<?php echo htmlspecialchars(Branding::tenantLogo($__tenant)); ?>" alt="" style="max-height:80px;">
    </div>
  </div>
</div>

<?php elseif ($tab === 'customers'): ?>
<div class="row g-4">
  <div class="col-lg-5">
    <div class="card border-0 shadow-sm p-4" style="border-radius:12px;">
      <h2 class="h6 mb-3"><?php echo $editCustomerRow ? 'Edit customer' : 'Add customer'; ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="customer_save">
        <?php if ($editCustomerRow): ?><input type="hidden" name="customer_id" value="<?php echo (int)$editCustomerRow['id']; ?>"><?php endif; ?>
        <div class="mb-3"><label class="form-label">Name</label><input name="name" class="form-control" required value="<?php echo htmlspecialchars($editCustomerRow['name'] ?? ''); ?>"></div>
        <div class="mb-3"><label class="form-label">Phone</label><input name="phone" class="form-control" value="<?php echo htmlspecialchars($editCustomerRow['phone'] ?? ''); ?>"></div>
        <div class="mb-3"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($editCustomerRow['email'] ?? ''); ?>"></div>
        <button class="btn btn-primary"><?php echo $editCustomerRow ? 'Update' : 'Add'; ?></button>
        <?php if ($editCustomerRow): ?><a class="btn btn-link" href="?tab=customers">Cancel</a><?php endif; ?>
      </form>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm p-4" style="border-radius:12px;">
      <h2 class="h6 mb-3">Customers</h2>
      <?php if (!$customers): ?>
        <p class="text-muted mb-0">No customers yet.</p>
      <?php else: ?>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr class="text-muted small"><th>Name</th><th>Phone</th><th>Credit owed</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($customers as $c): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($c['name']); ?></td>
              <td><?php echo htmlspecialchars($c['phone'] ?? '—'); ?></td>
              <td>KES <?php echo number_format((float)$c['credit_balance'], 2); ?></td>
              <td class="text-end">
                <a class="btn btn-sm btn-outline-primary" href="?tab=customers&edit_customer=<?php echo (int)$c['id']; ?>">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete?');">
                  <input type="hidden" name="action" value="customer_delete">
                  <input type="hidden" name="customer_id" value="<?php echo (int)$c['id']; ?>">
                  <button class="btn btn-sm btn-outline-danger">Delete</button>
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

<?php else: ?>
<div class="row g-4">
  <?php if ($editStaffRow): ?>
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm p-4" style="border-radius:12px;">
      <h2 class="h6 mb-3">Edit staff — <?php echo htmlspecialchars($editStaffRow['username']); ?></h2>
      <form method="post">
        <input type="hidden" name="action" value="staff_update">
        <input type="hidden" name="staff_id" value="<?php echo (int)$editStaffRow['id']; ?>">
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" class="form-control" required value="<?php echo htmlspecialchars($editStaffRow['username']); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Role</label>
          <select name="staff_type" class="form-select">
            <?php foreach (StaffRoles::typeLabels() as $val => $label):
              if (!in_array($val, $staffTypes, true)) continue;
            ?>
            <option value="<?php echo htmlspecialchars($val); ?>" <?php echo ($editStaffRow['staff_type'] ?? '') === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Branch</label>
          <select name="branch_id" class="form-select">
            <option value="">All branches</option>
            <?php foreach ($locations as $loc): ?>
            <option value="<?php echo (int)$loc['id']; ?>" <?php echo (int)($editStaffRow['branch_id'] ?? 0) === (int)$loc['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($loc['title']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">New PIN <span class="text-muted">(optional)</span></label>
          <input name="pin" type="password" inputmode="numeric" maxlength="5" class="form-control" placeholder="Leave blank to keep current PIN" autocomplete="off">
        </div>
        <button class="btn btn-primary">Save changes</button>
        <a class="btn btn-link" href="?tab=staff">Cancel</a>
      </form>
    </div>
  </div>
  <?php endif; ?>
  <div class="col-12 <?php echo $editStaffRow ? 'col-lg-7' : ''; ?>">
<div class="card border-0 shadow-sm p-4" style="border-radius:12px;">
  <h2 class="h5 mb-1">Staff management</h2>
  <p class="text-muted small mb-3"><a href="<?php echo public_path('super/staff/'); ?>">Add staff</a> · <a href="<?php echo public_path('super/staff/authorization.php'); ?>">User Access</a></p>
  <?php if (!$staff): ?>
    <p class="text-muted">No staff yet.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Role</th><th>Status</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($staff as $s):
          $typeKey = $s['staff_type'] ?? 'general';
          $roleLabel = StaffRoles::typeLabels()[$typeKey] ?? ucfirst($s['role_name'] ?? 'Staff');
        ?>
        <tr>
          <td class="fw-semibold"><?php echo htmlspecialchars($s['username']); ?></td>
          <td><?php echo htmlspecialchars($roleLabel); ?></td>
          <td><?php echo (int)$s['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>'; ?></td>
          <td class="text-end text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="?tab=staff&edit_staff=<?php echo (int)$s['id']; ?>">Edit</a>
            <form method="post" class="d-inline">
              <input type="hidden" name="action" value="staff_toggle">
              <input type="hidden" name="staff_id" value="<?php echo (int)$s['id']; ?>">
              <input type="hidden" name="enable" value="<?php echo (int)$s['is_active'] ? '0' : '1'; ?>">
              <button class="btn btn-sm btn-outline-secondary"><?php echo (int)$s['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
            </form>
            <form method="post" class="d-inline-flex gap-1 align-items-center" onsubmit="return confirm('Delete ALL sales data for this person?');">
              <input type="hidden" name="action" value="purge_staff">
              <input type="hidden" name="staff_id" value="<?php echo (int)$s['id']; ?>">
              <input name="confirm_name" class="form-control form-control-sm" placeholder="Type name" required style="max-width:100px;">
              <button class="btn btn-sm btn-danger">Delete</button>
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
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
