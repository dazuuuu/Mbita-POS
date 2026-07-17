<?php
// public/devs/register-admin.php
// DEV / SETUP — Register a Super (store owner) with shop + email login.
// Super owners manage everything under /super/ (products, staff, sales, settings).
//
//   http://localhost{base_path}/public/devs/register-admin.php?key=curlz-dev

declare(strict_types=1);
require_once __DIR__ . '/../../app/app.php';

const DEV_KEY = 'curlz-dev';

if (!hash_equals(DEV_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — append ?key=curlz-dev to the URL.');
}

$pdo = Database::pdo();
$svc = new SuperOwnerService($pdo);

$errors = [];
$success = null;
$old = [
    'shop_name'     => '',
    'slug'          => '',
    'business_type' => 'shop',
    'status'        => 'active',
    'owner_name'    => '',
    'owner_email'   => '',
    'owner_phone'   => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = [
        'shop_name'     => trim($_POST['shop_name'] ?? ''),
        'slug'          => trim($_POST['slug'] ?? ''),
        'business_type' => $_POST['business_type'] ?? 'shop',
        'status'        => $_POST['status'] ?? 'active',
        'owner_name'    => trim($_POST['owner_name'] ?? ''),
        'owner_email'   => trim($_POST['owner_email'] ?? ''),
        'owner_phone'   => trim($_POST['owner_phone'] ?? ''),
    ];
    $password = $_POST['owner_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $errors['owner_password'] = 'Password must be at least 8 characters.';
    }

    if (!$errors) {
        $res = $svc->create(array_merge($old, ['owner_password' => $password]));
        if ($res['ok']) {
            $loginUrl = public_path('auth/login.php');
            $success = 'Super owner <strong>' . htmlspecialchars($old['owner_name']) . '</strong> created for shop '
                     . '<strong>' . htmlspecialchars($old['shop_name']) . '</strong> (code: <code>'
                     . htmlspecialchars($old['slug'] ?: '') . '</code>). '
                     . 'They can log in on the <em>Admin</em> tab at <a href="' . htmlspecialchars($loginUrl) . '">login</a>.';
            $old = [
                'shop_name' => '', 'slug' => '', 'business_type' => 'shop', 'status' => 'active',
                'owner_name' => '', 'owner_email' => '', 'owner_phone' => '',
            ];
        } else {
            $errors = $res['errors'];
        }
    }
}

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register Super Owner — Dev Tool</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body { font-family: -apple-system,'Segoe UI',Roboto,Arial,sans-serif; background: #f1f5f9; color: #0f172a; margin: 0; padding: 0; line-height: 1.5; }
  .wrap { max-width: 580px; margin: 0 auto; padding: 32px 18px; }
  h1 { font-size: 1.35rem; margin: 0 0 4px; }
  .lead { color: #64748b; font-size: .9rem; margin: 0 0 20px; }
  .card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 22px 24px; margin-bottom: 18px; }
  .card h2 { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin: 0 0 16px; }
  label { display: block; font-size: .82rem; font-weight: 600; color: #475569; margin-bottom: 4px; }
  input, select { width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: .92rem; margin-bottom: 14px; }
  .btn { width: 100%; padding: 11px; background: #1e40af; color: #fff; border: none; border-radius: 8px; font-size: .95rem; font-weight: 600; cursor: pointer; }
  .alert { border-radius: 8px; padding: 12px 16px; font-size: .88rem; margin-bottom: 14px; }
  .alert.ok { background: #dcfce7; color: #166534; }
  .alert.err { background: #fee2e2; color: #991b1b; }
  .alert.warn { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e; font-size: .68rem; font-weight: 700; padding: 2px 7px; border-radius: 999px; margin-left: 6px; }
  .text-danger { color: #dc2626; font-size: .8rem; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; }
</style>
</head>
<body>
<div class="wrap">
  <h1>Register Super Owner <span class="badge">DEV ONLY</span></h1>
  <p class="lead">Creates a shop and its <strong>Super</strong> account (<code>tenant_owner</code>). Super manages everything under <code>/super/</code> — staff, products, sales, settings.</p>

  <div class="alert warn">No authentication on this page. Restrict by IP or delete before production.</div>

  <?php if ($success): ?><div class="alert ok"><?php echo $success; ?></div><?php endif; ?>
  <?php if (!empty($errors['_'])): ?><div class="alert err"><?php echo $h($errors['_']); ?></div><?php endif; ?>

  <form method="post" action="?key=<?php echo $h(DEV_KEY); ?>">
    <div class="card">
      <h2>Shop</h2>
      <label for="shop_name">Shop name</label>
      <input id="shop_name" name="shop_name" required value="<?php echo $h($old['shop_name']); ?>" placeholder="e.g. Mbita General Store">

      <label for="slug">Shop code (slug)</label>
      <input id="slug" name="slug" pattern="[a-z0-9\-]*" value="<?php echo $h($old['slug']); ?>" placeholder="auto-generated if empty — staff use this as PIN shop code">
      <?php if (!empty($errors['slug'])): ?><div class="text-danger"><?php echo $h($errors['slug']); ?></div><?php endif; ?>

      <label for="business_type">Business type</label>
      <select id="business_type" name="business_type">
        <option value="shop" <?php echo $old['business_type'] === 'shop' ? 'selected' : ''; ?>>Retail shop</option>
        <option value="barbershop_salon" <?php echo $old['business_type'] === 'barbershop_salon' ? 'selected' : ''; ?>>Barbershop &amp; salon</option>
      </select>

      <label for="status">Status</label>
      <select id="status" name="status">
        <option value="active" <?php echo $old['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
        <option value="suspended" <?php echo $old['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
      </select>
    </div>

    <div class="card">
      <h2>Super owner account</h2>
      <label for="owner_name">Full name</label>
      <input id="owner_name" name="owner_name" required value="<?php echo $h($old['owner_name']); ?>">

      <label for="owner_email">Email (Admin tab login)</label>
      <input id="owner_email" name="owner_email" type="email" required value="<?php echo $h($old['owner_email']); ?>">
      <?php if (!empty($errors['owner_email'])): ?><div class="text-danger"><?php echo $h($errors['owner_email']); ?></div><?php endif; ?>

      <label for="owner_phone">Phone (optional)</label>
      <input id="owner_phone" name="owner_phone" type="tel" value="<?php echo $h($old['owner_phone']); ?>">

      <label for="owner_password">Password</label>
      <input id="owner_password" name="owner_password" type="password" required minlength="8" autocomplete="new-password">
      <?php if (!empty($errors['owner_password'])): ?><div class="text-danger"><?php echo $h($errors['owner_password']); ?></div><?php endif; ?>

      <label for="confirm_password">Confirm password</label>
      <input id="confirm_password" name="confirm_password" type="password" required autocomplete="new-password">
      <?php if (!empty($errors['confirm_password'])): ?><div class="text-danger"><?php echo $h($errors['confirm_password']); ?></div><?php endif; ?>
    </div>

    <button class="btn" type="submit">Create Super owner</button>
  </form>
</div>
</body>
</html>
