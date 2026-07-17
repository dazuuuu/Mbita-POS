<?php
// public/auth/login.php
// Staff: PIN only (phone lockscreen). Super: email+password OR PIN (set in Settings → Login).
require_once __DIR__ . '/../../app/app.php';

function auth_employee_dashboard(?string $role): string
{
    if ($role === 'sales_agent') {
        return public_path('sales-agent/dashboard/');
    }
    if (StaffRoles::isEmployeeRole($role)) {
        return public_path('staff/dashboard/');
    }
    return public_path('staff/dashboard/');
}

$pdo = null;
$dbError = '';
$auth = null;
$ownerLoginMethod = 'password';

try {
    $pdo = Database::pdo();
    Schema028Service::ensureApplied($pdo);
    $auth = new AuthService($pdo);
    $ownerLoginMethod = $auth->defaultOwnerLoginMethod();
} catch (Throwable $e) {
    $dbError = 'Database connection failed. Start MySQL and check app/config/database.php.';
}

$error = '';
$notice = '';
$mode = ($_GET['mode'] ?? $_POST['mode'] ?? 'admin') === 'staff' ? 'staff' : 'admin';
$adminUsesPin = ($ownerLoginMethod === 'pin') || ($mode === 'admin' && ($_GET['method'] ?? $_POST['method'] ?? '') === 'pin');

if (($_GET['reset'] ?? '') === '1') {
    $notice = 'Your password has been set. Please sign in with your new password.';
} elseif (($_GET['denied'] ?? '') === '1') {
    if (!empty($_SESSION['logged_in']) && StaffRoles::isEmployeeRole($_SESSION['role'] ?? '')) {
        $_SESSION['flash']['error'] = 'You don\'t have access to that page.';
        header('Location: ' . public_path('staff/dashboard/'));
        exit;
    }
    if (!empty($_SESSION['logged_in']) && ($_SESSION['role'] ?? '') === 'sales_agent') {
        $_SESSION['flash']['error'] = 'You don\'t have access to that page.';
        header('Location: ' . public_path('sales-agent/dashboard/'));
        exit;
    }
    $error = 'You don\'t have access to that page. Please sign in with the right account.';
    unset(
        $_SESSION['logged_in'], $_SESSION['otp_verified'], $_SESSION['user_id'],
        $_SESSION['tenant_id'], $_SESSION['role'], $_SESSION['role_id'], $_SESSION['staff_type'],
        $_SESSION['capabilities'], $_SESSION['username'], $_SESSION['must_reset']
    );
    TenantContext::reset();
} elseif (($_GET['locked'] ?? '') === '1') {
    $error = 'Your account needs attention before you can sign in.';
}

$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$pdo || !$auth) {
        $error = $dbError ?: 'System unavailable. Try again later.';
    } else {
        $mode = ($_POST['mode'] ?? 'admin') === 'staff' ? 'staff' : 'admin';
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($mode === 'staff') {
            $pin  = trim($_POST['pin'] ?? '');
            $user = $auth->findStaffByPin($pin);
            if (!$user) {
                $auth->logAttempt('staff-pin', $ip);
                $error = 'Incorrect PIN. Try again.';
            } else {
                session_regenerate_id(true);
                TenantContext::establish($pdo, $user);
                $_SESSION['username']     = $user['username'];
                $_SESSION['logged_in']    = true;
                $_SESSION['otp_verified'] = true;
                $_SESSION['must_reset']   = false;
                if (StaffAttendanceService::shouldTrack($user['role_name'] ?? '')) {
                    StaffAttendanceService::recordLogin($pdo, (int) $user['tenant_id'], (int) $user['id']);
                }
                header('Location: ' . auth_employee_dashboard($user['role_name'] ?? ''));
                exit;
            }
        } elseif (($_POST['login_type'] ?? '') === 'pin' || $adminUsesPin) {
            $pin  = trim($_POST['pin'] ?? '');
            $user = $auth->findOwnerByPin($pin);
            if (!$user) {
                $auth->logAttempt('owner-pin', $ip);
                $error = 'Incorrect PIN. Try again.';
            } else {
                session_regenerate_id(true);
                TenantContext::establish($pdo, $user);
                $_SESSION['username']     = $user['username'];
                $_SESSION['logged_in']    = true;
                $_SESSION['otp_verified'] = true;
                $_SESSION['first_login']  = true;
                $_SESSION['must_reset']   = false;
                header('Location: ' . public_path('super/settings/?tab=locations'));
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
                $error = 'Staff sign in with PIN only. Use the Staff option on the home screen.';
            } else {
                $verdict = AccountGuard::evaluate($user);
                if (!$verdict['ok']) {
                    $error = AccountGuard::message($verdict['reason']);
                } elseif (($user['role_name'] ?? '') !== 'tenant_owner') {
                    $error = 'Only the store owner can sign in here.';
                } else {
                    session_regenerate_id(true);
                    TenantContext::establish($pdo, $user);
                    $_SESSION['username']     = $user['username'];
                    $_SESSION['logged_in']    = true;
                    $_SESSION['otp_verified'] = true;
                    $_SESSION['first_login']  = true;
                    $_SESSION['must_reset']   = !empty($user['must_reset_password']);
                    header('Location: ' . public_path('super/settings/?tab=locations'));
                    exit;
                }
            }
        }
    }
}

$page_title = 'Log in';
ob_start();
?>
<style>
  .auth-switch { display:flex; gap:24px; justify-content:center; margin-bottom:22px; }
  .auth-switch a {
    padding:4px 2px; font-size:.9rem; font-weight:500;
    text-decoration:none; color:var(--muted, #888);
    border-bottom:2px solid transparent;
  }
  .auth-switch a.active { color:var(--text, #222); border-bottom-color:var(--gold, #b8956b); }
  .lock-time { text-align:center; font-size:2.1rem; font-weight:300; color:var(--text, #222); letter-spacing:-.02em; margin:0 0 2px; line-height:1.1; }
  .lock-date { text-align:center; color:var(--muted, #888); font-size:.82rem; margin-bottom:16px; }
  .lock-hint { text-align:center; color:var(--muted, #888); font-size:.85rem; margin-bottom:4px; }
  .auth-foot { text-align:center; margin-top:18px; font-size:.82rem; }
  .auth-foot a { color:var(--gold, #b8956b); text-decoration:none; }
  .auth-foot a:hover { text-decoration:underline; }
</style>

<div class="auth-switch">
  <a class="<?php echo $mode === 'admin' ? 'active' : ''; ?>" href="?mode=admin">Owner</a>
  <a class="<?php echo $mode === 'staff' ? 'active' : ''; ?>" href="?mode=staff">Staff</a>
</div>

<?php if ($mode === 'staff' || ($mode === 'admin' && $adminUsesPin)): ?>
<div class="lock-time" id="lockTime"></div>
<div class="lock-date" id="lockDate"></div>
<div class="auth-title" style="font-size:1.1rem;margin-bottom:4px;">
  <?php echo $mode === 'staff' ? 'Enter staff PIN' : 'Enter your PIN'; ?>
</div>
<div class="lock-hint"><?php echo $mode === 'staff' ? 'Tap your PIN to unlock the POS' : 'Owner PIN — set in Settings → Login'; ?></div>
<?php else: ?>
<div class="auth-title">Owner sign in</div>
<div class="auth-sub">Email and password for your Super account.</div>
<?php endif; ?>

<?php if ($error): ?><div class="auth-alert err"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
<?php if ($dbError && !$error): ?><div class="auth-alert err"><?php echo htmlspecialchars($dbError); ?></div><?php endif; ?>
<?php if ($notice): ?><div class="auth-alert ok"><?php echo htmlspecialchars($notice); ?></div><?php endif; ?>

<form method="post" novalidate id="loginForm">
  <input type="hidden" name="mode" value="<?php echo htmlspecialchars($mode); ?>">
  <?php if ($mode === 'admin' && $adminUsesPin): ?>
    <input type="hidden" name="login_type" value="pin">
    <input type="hidden" name="method" value="pin">
  <?php endif; ?>
  <?php if ($mode === 'staff' || ($mode === 'admin' && $adminUsesPin)): ?>
    <?php include ROOT_PATH . '/public/components/auth/pin_keypad.php'; ?>
  <?php else: ?>
    <div class="mb-3">
      <label class="form-label">Email</label>
      <input name="email" type="email" class="form-control" value="<?php echo htmlspecialchars($email); ?>" autofocus>
    </div>
    <div class="mb-4">
      <label class="form-label">Password</label>
      <input name="password" type="password" class="form-control">
    </div>
    <button class="btn-auth">Sign in</button>
  <?php endif; ?>
</form>

<div class="auth-foot">
  <?php if ($mode === 'admin' && !$adminUsesPin): ?>
    <a href="<?php echo public_path('auth/forgot-password.php'); ?>">Forgot password?</a> ·
  <?php endif; ?>
  <a href="<?php echo public_path('/'); ?>">Back to home</a> ·
  <a href="<?php echo public_path('auth/logout.php'); ?>">Log out</a>
</div>

<?php if ($mode === 'staff' || ($mode === 'admin' && $adminUsesPin)): ?>
<script>
(function(){
  function pad(n){ return n < 10 ? '0' + n : n; }
  function tick(){
    var d = new Date();
    var el = document.getElementById('lockTime');
    var dt = document.getElementById('lockDate');
    if (el) el.textContent = pad(d.getHours()) + ':' + pad(d.getMinutes());
    if (dt) {
      dt.textContent = d.toLocaleDateString(undefined, { weekday:'long', month:'long', day:'numeric' });
    }
  }
  tick();
  setInterval(tick, 10000);
  <?php if ($error): ?>
  document.querySelectorAll('.phone-lock').forEach(function(p){ if (p.shakePin) p.shakePin(); });
  <?php endif; ?>
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include ROOT_PATH . '/public/templates/auth/layout.php';
