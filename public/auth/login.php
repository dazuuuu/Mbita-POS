<?php
// public/auth/login.php
// Admin: email + password. Staff: shop code + 4–5 digit PIN.
require_once __DIR__ . '/../../app/app.php';

function auth_employee_dashboard(?string $role): string
{
    if ($role === 'sales_agent') {
        return public_path('sales-agent/dashboard/');
    }
    if (StaffRoles::isEmployeeRole($role)) {
        return public_path('staff/dashboard/');
    }
    return public_path('super/dashboard/');
}

if (!empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']) && ($_GET['denied'] ?? '') !== '1') {
    $sessionRole = $_SESSION['role'] ?? null;
    if ($sessionRole === 'tenant_owner') {
        header('Location: ' . public_path('super/dashboard/'));
        exit;
    }
    if (StaffRoles::isEmployeeRole($sessionRole) || $sessionRole === 'sales_agent') {
        header('Location: ' . auth_employee_dashboard($sessionRole));
        exit;
    }
    // Unsupported role (e.g. legacy admin) — clear session to stop login ↔ dashboard loops.
    unset(
        $_SESSION['logged_in'], $_SESSION['otp_verified'], $_SESSION['user_id'],
        $_SESSION['tenant_id'], $_SESSION['role'], $_SESSION['staff_type'], $_SESSION['capabilities'],
        $_SESSION['username'], $_SESSION['must_reset']
    );
    TenantContext::reset();
}

$pdo  = null;
$dbError = '';
try {
    $pdo = Database::pdo();
} catch (Throwable $e) {
    $dbError = 'Database connection failed. Check app/config/database.php and that MySQL is running.';
}
$auth = $pdo ? new AuthService($pdo) : null;
$error = '';
$notice = '';
$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'admin') === 'staff' ? 'staff' : 'admin';

if (($_GET['reset'] ?? '') === '1') {
    $notice = 'Your password has been set. Please sign in with your new password.';
} elseif (($_GET['denied'] ?? '') === '1') {
    $error = 'You don\'t have access to that page. Please sign in with the right account.';
} elseif (($_GET['locked'] ?? '') === '1') {
    $error = 'Your account needs attention before you can sign in.';
}

$email    = '';
$shopSlug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo || !$auth) {
        $error = $dbError ?: 'System unavailable. Try again later.';
    } else {
    $mode = ($_POST['mode'] ?? 'admin') === 'staff' ? 'staff' : 'admin';
    $ip   = $_SERVER['REMOTE_ADDR'] ?? null;

    if ($mode === 'staff') {
        $shopSlug = trim($_POST['shop_slug'] ?? '');
        $pin      = trim($_POST['pin'] ?? '');

        $user = $auth->findByPin($shopSlug, $pin);
        if (!$user) {
            $auth->logAttempt('pin:' . $shopSlug, $ip);
            $error = 'Invalid shop code or PIN.';
        } else {
            session_regenerate_id(true);
            TenantContext::establish($pdo, $user);
            $_SESSION['username']     = $user['username'];
            $_SESSION['logged_in']    = true;
            $_SESSION['otp_verified'] = true;
            $_SESSION['must_reset']   = false;
            header('Location: ' . auth_employee_dashboard($user['role_name'] ?? ''));
            exit;
        }
    } else {
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $user = $auth->findByEmail($email);
        if (!$user || !$auth->verifyPassword($user, $password)) {
            $auth->logAttempt($email, $ip);
            $error = 'Invalid email or password.';
        } elseif (!$auth->isAdminLoginEligible($user)) {
            $error = 'Staff accounts use PIN login. Switch to the Staff tab below.';
        } else {
            $verdict = AccountGuard::evaluate($user);
            if (!$verdict['ok']) {
                $error = AccountGuard::message($verdict['reason']);
            } else {
                if (($user['role_name'] ?? '') !== 'tenant_owner') {
                    $error = 'Only store owners (Super) can use email login here. Staff must use the PIN tab.';
                } else {
                    session_regenerate_id(true);
                    TenantContext::establish($pdo, $user);
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['logged_in']    = true;
                    $_SESSION['otp_verified'] = true;
                    $_SESSION['first_login']  = true;
                    $_SESSION['must_reset']   = !empty($user['must_reset_password']);
                    header('Location: ' . public_path('super/dashboard/'));
                    exit;
                }
            }
        }
    }
    }
}

$page_title = 'Log in';
ob_start();
?>
<style>
  .auth-tabs { display:flex; gap:8px; margin-bottom:20px; }
  .auth-tab { flex:1; text-align:center; padding:10px; border-radius:10px; font-size:.85rem; font-weight:600;
              text-decoration:none; color:#94a3b8; background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); }
  .auth-tab.active { color:#fff; background:rgba(37,99,235,.25); border-color:rgba(37,99,235,.5); }
  .pin-input { letter-spacing:.35em; text-align:center; font-size:1.2rem; font-weight:700; }
</style>

<div class="auth-tabs">
  <a class="auth-tab <?php echo $mode === 'admin' ? 'active' : ''; ?>" href="?mode=admin">Admin login</a>
  <a class="auth-tab <?php echo $mode === 'staff' ? 'active' : ''; ?>" href="?mode=staff">Staff PIN</a>
</div>

<?php if ($mode === 'admin'): ?>
<div class="auth-title">Admin sign in</div>
<div class="auth-sub">Log in with your email and password to manage your business.</div>
<?php else: ?>
<div class="auth-title">Staff sign in</div>
<div class="auth-sub">Enter your shop code and the PIN your manager gave you.</div>
<?php endif; ?>

<?php if ($error): ?><div class="auth-alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($dbError && !$error): ?><div class="auth-alert err"><?php echo htmlspecialchars($dbError); ?></div><?php endif; ?>
<?php if ($notice): ?><div class="auth-alert ok"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

<form method="post" novalidate>
  <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
  <?php if ($mode === 'admin'): ?>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label">Password</label>
      <input name="password" type="password" class="form-control">
    </div>
    <button class="btn-auth">Log in as admin</button>
  <?php else: ?>
    <div class="mb-3">
      <label class="form-label">Shop code</label>
      <input name="shop_slug" class="form-control" placeholder="e.g. curlz-salon" value="<?php echo htmlspecialchars($shopSlug); ?>" autofocus required>
      <small class="text-muted" style="color:#64748b!important;font-size:.78rem;">Ask your manager for your shop code (same as the shop URL slug).</small>
    </div>
    <div class="mb-4">
      <label class="form-label">PIN (4–5 digits)</label>
      <input name="pin" type="password" inputmode="numeric" pattern="\d{4,5}" maxlength="5"
             class="form-control pin-input" placeholder="••••" autocomplete="off" required>
    </div>
    <button class="btn-auth">Log in with PIN</button>
  <?php endif; ?>
</form>

<?php if ($mode === 'admin'): ?>
<div class="auth-foot">
  <a href="<?php echo public_path('auth/forgot-password.php'); ?>">Forgot password?</a>
</div>
<?php endif; ?>
<?php
$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';
