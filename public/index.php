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
    --lux-black:#0a0a0a;
    --lux-black-soft:#141414;
    --lux-white:#ffffff;
    --lux-off:#f5f5f5;
    --lux-gold:#c9a227;
    --lux-gold-light:#e8c547;
    --lux-gold-dark:#9a7b1a;
    --lux-muted:#666666;
    --lux-border:#e5e5e5;
  }
  *{ box-sizing:border-box; margin:0; padding:0; }
  body{
    font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
    min-height:100svh; color:var(--lux-black);
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:24px; padding-top:max(24px,env(safe-area-inset-top)); padding-bottom:max(24px,env(safe-area-inset-bottom));
    background:var(--lux-black);
    overflow-x:hidden;
  }
  body::before{
    content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
    background:
      radial-gradient(ellipse 80% 50% at 50% -10%, rgba(201,162,39,.12), transparent 55%),
      linear-gradient(180deg, var(--lux-black-soft) 0%, var(--lux-black) 100%);
  }

  .wrap{ width:440px; max-width:100%; position:relative; z-index:1; }
  .card{
    background:var(--lux-white); border:1px solid var(--lux-border); border-radius:16px;
    overflow:hidden; box-shadow:0 24px 60px rgba(0,0,0,.45); position:relative;
  }
  .card::before{
    content:''; position:absolute; top:0; left:0; right:0; height:4px;
    background:linear-gradient(90deg, var(--lux-gold-dark), var(--lux-gold), var(--lux-gold-light));
  }
  .inner{ position:relative; padding:32px; }

  .brand{ text-align:center; margin-bottom:24px; }
  .logo-box{
    width:72px; height:72px; border-radius:14px; margin:0 auto 14px;
    background:var(--lux-off); border:2px solid var(--lux-gold);
    display:flex; align-items:center; justify-content:center; overflow:hidden;
  }
  .logo-box img{ width:100%; height:100%; object-fit:contain; padding:10px; }
  .logo-box .logo-fallback{ color:var(--lux-gold); font-size:1.6rem; }
  .brand h1{ font-size:1.4rem; font-weight:800; color:var(--lux-black); letter-spacing:-.02em; margin-bottom:4px; }
  .brand p{ color:var(--lux-muted); font-size:.85rem; }

  .lede{
    font-size:.7rem; text-transform:uppercase; letter-spacing:.14em; color:var(--lux-gold-dark);
    text-align:center; margin-bottom:16px; font-weight:700;
  }
  .portal{
    display:flex; align-items:center; gap:14px; text-decoration:none; color:var(--lux-black);
    background:var(--lux-off); border:1px solid var(--lux-border);
    border-radius:12px; padding:16px 18px; margin-bottom:10px; transition:all .2s;
  }
  .portal:last-of-type{ margin-bottom:0; }
  .portal:hover, .portal:focus-visible{
    transform:translateY(-2px); outline:none;
    border-color:var(--lux-gold); box-shadow:0 8px 24px rgba(201,162,39,.2);
  }
  .portal .ic{
    width:46px; height:46px; border-radius:10px; display:flex; align-items:center;
    justify-content:center; font-size:1.2rem; flex-shrink:0;
    background:var(--lux-black); color:var(--lux-gold); border:1px solid var(--lux-gold-dark);
  }
  .portal .tx{ flex:1; }
  .portal .tx b{ display:block; font-size:.98rem; font-weight:700; margin-bottom:2px; }
  .portal .tx span{ font-size:.8rem; color:var(--lux-muted); }
  .portal .go{ color:var(--lux-gold-dark); font-size:.9rem; transition:transform .2s,color .2s; }
  .portal:hover .go{ transform:translateX(3px); color:var(--lux-gold); }

  .install-btn{
    display:none; width:100%; margin-top:14px; align-items:center; justify-content:center;
    gap:10px; background:var(--lux-gold); color:var(--lux-black);
    border:1px solid var(--lux-gold-dark); border-radius:12px; padding:14px;
    font-size:.95rem; font-weight:800; cursor:pointer; transition:all .2s;
  }
  .install-btn:hover{ background:var(--lux-gold-light); box-shadow:0 8px 24px rgba(201,162,39,.35); }

  .hint{ text-align:center; color:rgba(255,255,255,.55); font-size:.76rem; margin-top:14px; line-height:1.5; }
  .hint b{ color:var(--lux-gold-light); font-weight:600; }
  .foot{ text-align:center; color:rgba(255,255,255,.35); font-size:.72rem; margin-top:18px; }

  @media(max-width:480px){ .inner{ padding:24px; } }
</style>
</head>
<body>
  <div class="wrap">
      <div class="card">
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