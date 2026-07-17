<?php
// public/super/settings/index.php — shop, receipt, customers, staff management
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
// Auto-apply migration 024 columns if missing (fixes credits_enabled etc. on AMPPS).
Schema024Service::ensureApplied($pdo);

$tenantId = (int) TenantContext::tenantId();
$tenantModel = new Models\TenantModel($pdo);
$staffSvc = new StaffService($pdo);
$custSvc = new CustomerService($pdo);
$tab = $_GET['tab'] ?? 'shop';

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

    if ($action === 'shop') {
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
                if (!$tenantModel->hasExtendedSettings()) {
                    $_SESSION['flash']['error'] = 'Basic settings saved. Run the database update for KRA PIN, credits & receipts: /Curlz/public/devs/fix-schema-024.php';
                } else {
                    $_SESSION['flash']['success'] = 'Shop settings saved.';
                }
            }
        }
        header('Location: /Curlz/public/super/settings/?tab=shop'); exit;
    }

    if ($action === 'customer_save') {
        $in = ['name' => $_POST['name'] ?? '', 'phone' => $_POST['phone'] ?? '', 'email' => $_POST['email'] ?? '', 'notes' => $_POST['notes'] ?? ''];
        $cid = (int) ($_POST['customer_id'] ?? 0);
        $res = $cid ? $custSvc->update($tenantId, $cid, $in) : $custSvc->create($tenantId, $in);
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok'] ? 'Customer saved.' : ($res['errors']['name'] ?? $res['errors']['_'] ?? 'Could not save.');
        header('Location: /Curlz/public/super/settings/?tab=customers'); exit;
    }

    if ($action === 'customer_delete') {
        $custSvc->delete($tenantId, (int) ($_POST['customer_id'] ?? 0));
        $_SESSION['flash']['success'] = 'Customer removed.';
        header('Location: /Curlz/public/super/settings/?tab=customers'); exit;
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
        header('Location: /Curlz/public/super/settings/?tab=staff'); exit;
    }
}

$__tenant = $tenantModel->find($tenantId);
$staff = $staffSvc->listForTenant($tenantId);
$schemaReady = SchemaHelper::migration024Ready($pdo);
$customers = $schemaReady ? $custSvc->listForTenant($tenantId) : [];
$editCustomer = (int) ($_GET['edit_customer'] ?? 0);
$editCustomerRow = $editCustomer ? $custSvc->find($tenantId, $editCustomer) : null;

$page_title = 'Settings';
ob_start();
?>
<?php if (!$schemaReady): ?>
<div class="alert alert-warning">
  <strong>Database update needed.</strong> KRA PIN, receipts, customers and credits require migration 024.
  Open <a href="/Curlz/public/devs/fix-schema-024.php" class="alert-link">fix-schema-024.php</a> once, then refresh this page.
</div>
<?php endif; ?>
<ul class="nav nav-tabs mb-4">
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'shop' ? 'active' : ''; ?>" href="?tab=shop">Shop &amp; receipts</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'customers' ? 'active' : ''; ?>" href="?tab=customers">Customers</a></li>
  <li class="nav-item"><a class="nav-link <?php echo $tab === 'staff' ? 'active' : ''; ?>" href="?tab=staff">Staff</a></li>
</ul>

<?php if ($tab === 'shop'): ?>
<div class="row g-4">
  <div class="col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Shop &amp; receipt details</h2>
        <p class="text-muted small">Shown on every receipt — shop name, KRA PIN, location, branch, and footer.</p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="shop">
          <div class="mb-3">
            <label class="form-label fw-semibold">Business name</label>
            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($__tenant['name'] ?? ''); ?>">
          </div>
          <div class="row g-2">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">KRA PIN</label>
              <input name="kra_pin" class="form-control" placeholder="e.g. P051234567X"
                value="<?php echo htmlspecialchars($__tenant['kra_pin'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Phone</label>
              <input name="phone" class="form-control" value="<?php echo htmlspecialchars($__tenant['phone'] ?? ''); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Location <span class="text-muted">(shown on receipt)</span></label>
            <input name="location" class="form-control" placeholder="e.g. Kitale Town, Trans-Nzoia"
              value="<?php echo htmlspecialchars($__tenant['location'] ?? ''); ?>">
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
              <?php echo !empty($__tenant['credits_enabled']) ? 'checked' : ''; ?>
              <?php echo $schemaReady ? '' : 'disabled'; ?>>
            <label class="form-check-label" for="credits">Enable product credit sales</label>
            <div class="form-text">When on, agents can sell credit-eligible products on credit. Mark products as credit-allowed on the Products page.</div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Logo</label>
            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
          </div>
          <button class="btn btn-primary">Save settings</button>
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
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input name="name" class="form-control" required value="<?php echo htmlspecialchars($editCustomerRow['name'] ?? ''); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Phone <span class="text-muted">(optional)</span></label>
          <input name="phone" class="form-control" value="<?php echo htmlspecialchars($editCustomerRow['phone'] ?? ''); ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Email <span class="text-muted">(optional)</span></label>
          <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($editCustomerRow['email'] ?? ''); ?>">
        </div>
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
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="?tab=customers&edit_customer=<?php echo (int)$c['id']; ?>">Edit</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Delete this customer?');">
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

<?php else: /* staff */ ?>
<div class="card border-0 shadow-sm p-4" style="border-radius:12px;">
  <h2 class="h5 mb-1">Staff management</h2>
  <p class="text-muted small mb-3">
    <strong>Permanent delete</strong> removes the staff account and <em>all</em> their POS sales, commission sales, and payouts.
    Type their exact name to confirm. <a href="/Curlz/public/super/staff/">Add staff here</a>.
  </p>
  <?php if (!$staff): ?>
    <p class="text-muted">No staff members.</p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Email</th><th>Status</th><th>Permanent delete</th></tr></thead>
      <tbody>
        <?php foreach ($staff as $s): ?>
        <tr>
          <td class="fw-semibold"><?php echo htmlspecialchars($s['username']); ?></td>
          <td class="text-muted"><?php echo htmlspecialchars($s['email']); ?></td>
          <td><?php echo (int)$s['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>'; ?></td>
          <td>
            <form method="post" class="d-flex gap-2 align-items-center" onsubmit="return confirm('This permanently deletes ALL sales data for this person. Continue?');">
              <input type="hidden" name="action" value="purge_staff">
              <input type="hidden" name="staff_id" value="<?php echo (int)$s['id']; ?>">
              <input name="confirm_name" class="form-control form-control-sm" placeholder="Type <?php echo htmlspecialchars($s['username']); ?>" required style="max-width:180px;">
              <button class="btn btn-sm btn-danger text-nowrap">Delete forever</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
