<?php
// public/admins/index.php — register, update, and remove platform admins
require_once __DIR__ . '/../../app/app.php';
PageGuard::platform();

$pdo = Database::pdo();
$svc = new PlatformAdminService($pdo);
$currentUserId = (int) TenantContext::userId();

$editId = (int) ($_GET['edit'] ?? 0);
$editing = $editId ? $svc->find($editId) : null;

$errors = [];
$old = ['name' => '', 'email' => '', 'password' => '', 'confirm_password' => '', 'is_active' => 1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    if ($action === 'delete') {
        $res = $svc->delete((int) ($_POST['admin_id'] ?? 0), $currentUserId);
        $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
            ? 'Platform admin removed.'
            : ($res['error'] ?? 'Could not remove admin.');
        header('Location: ' . public_path('admins/'));
        exit;
    }

    if ($action === 'update') {
        $editId = (int) ($_POST['admin_id'] ?? 0);
        $old = [
            'name'             => trim($_POST['name'] ?? ''),
            'email'            => trim($_POST['email'] ?? ''),
            'password'         => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'is_active'        => !empty($_POST['is_active']) ? 1 : 0,
        ];
        $res = $svc->update($editId, $old);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Platform admin updated.';
            header('Location: ' . public_path('admins/'));
            exit;
        }
        $errors = $res['errors'];
        $editing = $svc->find($editId);
    }

    if ($action === 'create') {
        $old = [
            'name'             => trim($_POST['name'] ?? ''),
            'email'            => trim($_POST['email'] ?? ''),
            'password'         => $_POST['password'] ?? '',
            'confirm_password' => $_POST['confirm_password'] ?? '',
            'is_active'        => 1,
        ];
        $res = $svc->create($old);
        if ($res['ok']) {
            $_SESSION['flash']['success'] = 'Platform admin registered. They can log in with email and password.';
            header('Location: ' . public_path('admins/'));
            exit;
        }
        $errors = $res['errors'];
    }
}

if ($editing && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $old = [
        'name'             => $editing['username'],
        'email'            => $editing['email'],
        'password'         => '',
        'confirm_password' => '',
        'is_active'        => (int) $editing['is_active'],
    ];
}

$admins = $svc->list();
$page_title = $editing ? 'Update admin' : 'Platform admins';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1"><?php echo $editing ? 'Update admin' : 'Register admin'; ?></h2>
        <p class="text-muted small mb-3">
          Platform admins manage tenants and system settings. They log in with email and password on the Admin tab.
        </p>
        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
        <form method="post" novalidate>
          <input type="hidden" name="action" value="<?php echo $editing ? 'update' : 'create'; ?>">
          <?php if ($editing): ?><input type="hidden" name="admin_id" value="<?php echo (int) $editId; ?>"><?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Full name</label>
            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($old['name']); ?>">
            <?php if (!empty($errors['name'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" required value="<?php echo htmlspecialchars($old['email']); ?>">
            <?php if (!empty($errors['email'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['email']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Password<?php echo $editing ? ' <span class="text-muted fw-normal">(leave blank to keep current)</span>' : ''; ?></label>
            <input name="password" type="password" class="form-control" <?php echo $editing ? '' : 'required minlength="8"'; ?> autocomplete="new-password">
            <?php if (!empty($errors['password'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['password']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm password</label>
            <input name="confirm_password" type="password" class="form-control" <?php echo $editing ? '' : 'required'; ?> autocomplete="new-password">
            <?php if (!empty($errors['confirm_password'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['confirm_password']); ?></small><?php endif; ?>
          </div>
          <?php if ($editing): ?>
          <div class="mb-3 form-check">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?php echo $old['is_active'] ? 'checked' : ''; ?>>
            <label class="form-check-label" for="is_active">Account active</label>
          </div>
          <?php endif; ?>
          <div class="d-flex gap-2">
            <button class="btn btn-primary"><?php echo $editing ? 'Save changes' : 'Register admin'; ?></button>
            <?php if ($editing): ?>
              <a class="btn btn-outline-secondary" href="<?php echo public_path('admins/'); ?>">Cancel</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Platform admins <span class="badge bg-light text-dark"><?php echo count($admins); ?></span></h2>
        <?php if (!$admins): ?>
          <div class="text-muted">No platform admins yet. Register the first account on the left, or use the dev bootstrap tool.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr class="text-muted small text-uppercase"><th>Name</th><th>Email</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($admins as $a): ?>
                <tr>
                  <td class="fw-semibold">
                    <?php echo htmlspecialchars($a['username']); ?>
                    <?php if ((int) $a['id'] === $currentUserId): ?><span class="badge bg-primary ms-1">You</span><?php endif; ?>
                  </td>
                  <td><?php echo htmlspecialchars($a['email']); ?></td>
                  <td>
                    <?php if ((int) $a['is_active'] === 1): ?>
                      <span class="badge bg-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" href="<?php echo public_path('admins/'); ?>?edit=<?php echo (int) $a['id']; ?>">Update</a>
                    <?php if ((int) $a['id'] !== $currentUserId): ?>
                    <form method="post" class="d-inline" onsubmit="return confirm('Remove this platform admin permanently?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="admin_id" value="<?php echo (int) $a['id']; ?>">
                      <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                    </form>
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
include __DIR__ . '/../templates/admins/layout.php';
