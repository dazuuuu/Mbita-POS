<?php
// public/super/profile/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$tenantId = TenantContext::tenantId();
$tenantModel = new Models\TenantModel($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'name'           => trim($_POST['name'] ?? ''),
        'phone'          => trim($_POST['phone'] ?? ''),
        'address'        => trim($_POST['address'] ?? ''),
        'currency'       => trim($_POST['currency'] ?? 'KES'),
        'receipt_footer' => trim($_POST['receipt_footer'] ?? ''),
    ];

    // Logo upload (optional). Stored under public/uploads/branding/.
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $logo = save_tenant_logo($_FILES['logo'], $tenantId);
        if ($logo['ok']) {
            $data['logo_path'] = $logo['path'];
        } else {
            $_SESSION['flash']['error'] = $logo['error'];
        }
    }

    if ($data['name'] === '') {
        $_SESSION['flash']['error'] = 'Business name is required.';
    } else {
        $tenantModel->updateSettings($tenantId, $data);
        if (empty($_SESSION['flash']['error'])) {
            $_SESSION['flash']['success'] = 'Business profile updated.';
        }
    }
    header('Location: /Curlz/public/super/profile/');
    exit;
}

/** Minimal, safe image saver (swap for app/helpers/uploads.php if you prefer). */
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

$__tenant = $tenantModel->find($tenantId);
$page_title = 'Business Profile';

ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-8">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <form method="post" enctype="multipart/form-data">
          <div class="mb-3">
            <label class="form-label fw-semibold">Business name</label>
            <input type="text" name="name" class="form-control" required
                   value="<?php echo htmlspecialchars($__tenant['name'] ?? ''); ?>">
          </div>
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" name="phone" class="form-control"
                     value="<?php echo htmlspecialchars($__tenant['phone'] ?? ''); ?>">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label fw-semibold">Currency</label>
              <input type="text" name="currency" class="form-control" maxlength="8"
                     value="<?php echo htmlspecialchars($__tenant['currency'] ?? 'KES'); ?>">
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Address</label>
            <input type="text" name="address" class="form-control"
                   value="<?php echo htmlspecialchars($__tenant['address'] ?? ''); ?>">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold">Receipt footer</label>
            <input type="text" name="receipt_footer" class="form-control"
                   placeholder="e.g. Thank you for shopping with us!"
                   value="<?php echo htmlspecialchars($__tenant['receipt_footer'] ?? ''); ?>">
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold">Logo</label>
            <input type="file" name="logo" class="form-control" accept="image/png,image/jpeg,image/webp">
            <div class="form-text">Shown on your dashboard, your staff's dashboard, and receipts. PNG/JPG/WEBP, under 2MB.</div>
          </div>
          <button class="btn btn-primary">Save changes</button>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-4">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body text-center p-4">
        <div class="text-muted small text-uppercase mb-2">Current logo</div>
        <img src="<?php echo htmlspecialchars(Branding::tenantLogo($__tenant)); ?>"
             alt="Logo" style="max-height:90px;max-width:100%;object-fit:contain;">
        <div class="text-muted small mt-3">The login screen always shows the default Modern logo, not your business logo.</div>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';