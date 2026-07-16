<?php
// public/devs/register-admin.php
// DEV / SETUP TOOL — bootstrap the first platform admin (or add more with admin code).
// Key-guarded. DELETE or restrict before production.
//
//   http://localhost{base_path}/public/devs/register-admin.php?key=curlz-dev

declare(strict_types=1);
require_once __DIR__ . '/../../app/app.php';

const DEV_KEY = 'curlz-dev';

if (!hash_equals(DEV_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — append ?key=curlz-dev to the URL.');
}

$appCfg = is_file(ROOT_PATH . '/app/config/app.php') ? require ROOT_PATH . '/app/config/app.php' : [];
$adminCode = (string) ($appCfg['admin_registration_code'] ?? 'ADMIN2024');

$pdo = Database::pdo();
$svc = new PlatformAdminService($pdo);
$existingCount = $svc->count();

$errors = [];
$success = null;
$old = ['name' => '', 'email' => '', 'admin_code' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'name'       => trim($_POST['name'] ?? ''),
        'email'      => trim($_POST['email'] ?? ''),
        'admin_code' => trim($_POST['admin_code'] ?? ''),
    ];
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($existingCount > 0 && !hash_equals($adminCode, $old['admin_code'])) {
        $errors['admin_code'] = 'Invalid admin registration code.';
    }

    if (!$errors) {
        $res = $svc->create([
            'name'             => $old['name'],
            'email'            => $old['email'],
            'password'         => $password,
            'confirm_password' => $confirm,
        ]);
        if ($res['ok']) {
            $success = 'Platform admin <strong>' . htmlspecialchars($old['name']) . '</strong> created. '
                     . 'They can log in at the Admin tab with <code>' . htmlspecialchars($old['email']) . '</code>.';
            $old = ['name' => '', 'email' => '', 'admin_code' => ''];
            $existingCount = $svc->count();
        } else {
            $errors = $res['errors'];
        }
    }
}

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
$loginUrl = public_path('auth/login.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register Platform Admin — Dev Tool</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: -apple-system,'Segoe UI',Roboto,Arial,sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 0; line-height: 1.5; }
  .wrap { max-width: 520px; margin: 0 auto; padding: 32px 18px; }
  h1 { font-size: 1.35rem; margin: 0 0 4px; }
  .lead { color: #64748b; font-size: .9rem; margin: 0 0 20px; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 18px; }
  label { display: block; font-size: .82rem; font-weight: 600; color: #475569; margin-bottom: 4px; }
  input { width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .92rem; margin-bottom: 14px; }
  input:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15); outline: none; }
  .btn { width: 100%; padding: 11px; background: #1e40af; color: #fff; border: none; border-radius: 8px; font-size: .95rem; font-weight: 600; cursor: pointer; }
  .btn:hover { background: #1d4ed8; }
  .alert { border-radius: 8px; padding: 12px 16px; font-size: .88rem; margin-bottom: 14px; }
  .alert.ok { background: #dcfce7; color: #166534; }
  .alert.err { background: #fee2e2; color: #991b1b; }
  .alert.warn { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; margin-left: 6px; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-size: .85em; }
  .text-danger { color: #dc2626; font-size: .8rem; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Register Platform Admin <span class="badge">DEV ONLY</span></h1>
  <p class="lead">Creates a system-wide admin who can manage tenants. Uses the <code>platform_admin</code> role from migration 014.</p>

  <div class="alert warn">
    This page has <strong>no login</strong>. Restrict by IP or delete before production. After the first admin exists, the registration code is required.
  </div>

  <?php if ($success): ?>
    <div class="alert ok"><?php echo $success; ?> <a href="<?php echo $h($loginUrl); ?>">Go to login →</a></div>
  <?php endif; ?>

  <?php if (!empty($errors['_'])): ?>
    <div class="alert err"><?php echo $h($errors['_']); ?></div>
  <?php endif; ?>

  <form method="post" action="?key=<?php echo $h(DEV_KEY); ?>">
    <div class="card">
      <label for="name">Full name</label>
      <input id="name" name="name" type="text" required value="<?php echo $h($old['name']); ?>">

      <label for="email">Email</label>
      <input id="email" name="email" type="email" required value="<?php echo $h($old['email']); ?>">
      <?php if (!empty($errors['email'])): ?><div class="text-danger"><?php echo $h($errors['email']); ?></div><?php endif; ?>

      <label for="password">Password</label>
      <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password">
      <?php if (!empty($errors['password'])): ?><div class="text-danger"><?php echo $h($errors['password']); ?></div><?php endif; ?>

      <label for="confirm_password">Confirm password</label>
      <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
      <?php if (!empty($errors['confirm_password'])): ?><div class="text-danger"><?php echo $h($errors['confirm_password']); ?></div><?php endif; ?>

      <?php if ($existingCount > 0): ?>
        <label for="admin_code">Admin registration code</label>
        <input id="admin_code" name="admin_code" type="password" required placeholder="Required when admins already exist">
        <?php if (!empty($errors['admin_code'])): ?><div class="text-danger"><?php echo $h($errors['admin_code']); ?></div><?php endif; ?>
      <?php else: ?>
        <p style="font-size:.85rem;color:#64748b;margin:0;">No platform admins yet — registration code is not required for the first account.</p>
      <?php endif; ?>
    </div>

    <button class="btn" type="submit">Create platform admin</button>
  </form>

  <p style="margin-top:18px;font-size:.85rem;color:#64748b;">
    Existing platform admins: <strong><?php echo $existingCount; ?></strong>.
    <?php if ($existingCount > 0): ?>
      You can also add admins from <a href="<?php echo $h(public_path('admins/')); ?>">the admins panel</a> after logging in.
    <?php endif; ?>
  </p>
</div>
</body>
</html>
