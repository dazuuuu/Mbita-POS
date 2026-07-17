<?php
// public/index.php — business-branded login portal (installable PWA)
require_once __DIR__ . '/../app/app.php';

$LOGIN    = public_path('auth/login.php');
$portal   = Branding::portalBranding(Database::pdo());
$brandName = $portal['name'];
$brandLogo = $portal['logo_url'];
$brandHasLogo = !empty($portal['has_logo']);
$loggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']);
$role     = $_SESSION['role'] ?? '';
if ($role === 'tenant_owner') {
    $dashUrl = public_path('super/settings/?tab=locations');
} elseif ($role === 'sales_agent') {
    $dashUrl = public_path('sales-agent/dashboard/');
} elseif (StaffRoles::isEmployeeRole($role)) {
    $dashUrl = public_path('staff/dashboard/');
} else {
    $dashUrl = public_path('auth/login.php');
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo htmlspecialchars($brandName); ?> — Sign in</title>
<meta name="description" content="<?php echo htmlspecialchars($brandName); ?> — record sales, track stock, print receipts.">
<?php include __DIR__ . '/components/pwa_head.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  :root{
    --text:#222222;
    --muted:#888888;
    --gold:#b8956b;
    --gold-soft:rgba(184,149,107,.12);
    --line:#eeeeee;
  }
  *{ box-sizing:border-box; margin:0; padding:0; }
  body{
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
    min-height:100svh; color:var(--text);
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:24px; padding-top:max(24px,env(safe-area-inset-top)); padding-bottom:max(24px,env(safe-area-inset-bottom));
    background:#ffffff;
  }

  .wrap{ width:400px; max-width:100%; }
  .inner{ padding:8px 0; }

  .brand{ text-align:center; margin-bottom:28px; }
  .logo-box{ margin:0 auto 12px; max-width:120px; }
  .logo-box img{ max-height:56px; max-width:100%; object-fit:contain; }
  .brand h1{ font-size:1.3rem; font-weight:600; color:var(--text); margin-bottom:4px; }
  .brand p{ color:var(--muted); font-size:.85rem; }

  .lede{
    font-size:.72rem; text-transform:uppercase; letter-spacing:.12em; color:var(--muted);
    text-align:center; margin-bottom:14px;
  }
  .portal{
    display:flex; align-items:center; gap:14px; text-decoration:none; color:var(--text);
    padding:14px 0; margin-bottom:4px; border-bottom:1px solid var(--line);
    transition:color .2s;
  }
  .portal:last-of-type{ border-bottom:none; margin-bottom:0; }
  .portal:hover, .portal:focus-visible{ color:var(--gold); outline:none; }
  .portal .ic{
    width:36px; height:36px; display:flex; align-items:center; justify-content:center;
    font-size:1rem; flex-shrink:0; color:var(--gold);
  }
  .portal .tx{ flex:1; }
  .portal .tx b{ display:block; font-size:.95rem; font-weight:600; margin-bottom:2px; }
  .portal .tx span{ font-size:.8rem; color:var(--muted); }
  .portal:hover .tx span{ color:var(--muted); }
  .portal .go{ color:var(--line); font-size:.85rem; transition:color .2s; }
  .portal:hover .go{ color:var(--gold); }

  .install-btn{
    display:none; width:100%; margin-top:18px; align-items:center; justify-content:center;
    gap:8px; background:#fff; color:var(--gold);
    border:1px solid var(--gold); border-radius:8px; padding:12px;
    font-size:.9rem; font-weight:600; cursor:pointer;
  }
  .install-btn:hover{ background:var(--gold-soft); }

  .hint{ text-align:center; color:var(--muted); font-size:.76rem; margin-top:14px; line-height:1.5; }
  .foot{ text-align:center; color:#bbb; font-size:.72rem; margin-top:16px; }

  @media(max-width:480px){ .inner{ padding:4px 0; } }
</style>
</head>
<body>
  <div class="wrap">
        <div class="inner">
          <div class="brand">
            <?php if ($brandHasLogo && $brandLogo): ?>
            <div class="logo-box">
              <img src="<?php echo htmlspecialchars($brandLogo); ?>" alt="<?php echo htmlspecialchars($brandName); ?>">
            </div>
            <?php endif; ?>
            <h1><?php echo htmlspecialchars($brandName); ?></h1>
            <p>Run your shop from your phone</p>
          </div>

          <?php if ($loggedIn): ?>
            <div class="lede">You're signed in</div>
            <a class="portal owner" href="<?php echo $h($dashUrl); ?>">
              <span class="ic"><i class="fa-solid fa-gauge-high"></i></span>
              <span class="tx"><b>Open the POS</b><span>Continue to your dashboard</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
            <a class="portal staff" href="<?php echo public_path('auth/logout.php'); ?>">
              <span class="ic"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
              <span class="tx"><b>Switch account</b><span>Log out and sign in as someone else</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
          <?php else: ?>
            <div class="lede">Sign in to continue</div>
            <a class="portal owner" href="<?php echo $h($LOGIN); ?>?mode=admin">
              <span class="ic"><i class="fa-solid fa-user-shield"></i></span>
              <span class="tx"><b>Admin / Owner</b><span>PIN or email — manage shop, staff &amp; settings</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
            <a class="portal staff" href="<?php echo $h($LOGIN); ?>?mode=staff">
              <span class="ic"><i class="fa-solid fa-key"></i></span>
              <span class="tx"><b>Staff</b><span>PIN only — like unlocking your phone</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
          <?php endif; ?>

          <button class="install-btn" id="installBtn" type="button">
            <i class="fa-solid fa-circle-down"></i> Install app
          </button>
        </div>
  </div>

  <p class="hint" id="iosHint" style="display:none;">
    To install: tap <b>Share</b> <i class="fa-solid fa-arrow-up-from-bracket"></i> then <b>Add to Home Screen</b>.
  </p>
  <p class="foot"><?php echo htmlspecialchars($brandName); ?> &middot; works on phone &amp; desktop</p>

<script>
(function(){
  var deferred=null,btn=document.getElementById('installBtn');
  var standalone=window.matchMedia('(display-mode: standalone)').matches||window.navigator.standalone===true;
  window.addEventListener('beforeinstallprompt',function(e){e.preventDefault();deferred=e;if(btn&&!standalone)btn.style.display='flex';});
  if(btn)btn.addEventListener('click',function(){if(!deferred)return;deferred.prompt();deferred.userChoice.finally(function(){deferred=null;btn.style.display='none';});});
  window.addEventListener('appinstalled',function(){if(btn)btn.style.display='none';});

  var isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent);
  var isSafari=/^((?!chrome|crios|fxios).)*safari/i.test(navigator.userAgent);
  if(isIOS&&isSafari&&!standalone){var h=document.getElementById('iosHint');if(h)h.style.display='block';}
})();
</script>
</body>
</html>