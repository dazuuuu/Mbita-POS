<?php
// public/index.php — Curlz POS · login portal (installable PWA)
if (session_status() === PHP_SESSION_NONE) { session_start(); }

$LOGIN    = '/Curlz/public/auth/login.php';
$loggedIn = !empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']);
$role     = $_SESSION['role'] ?? '';
if ($role === 'staff') {
    $dashUrl = '/Curlz/public/staff/dashboard/';
} elseif ($role === 'sales_agent') {
    $dashUrl = '/Curlz/public/sales-agent/dashboard/';
} else {
    $dashUrl = '/Curlz/public/super/dashboard/';
}
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title>Curlz POS — Sign in</title>
<meta name="description" content="Curlz POS — record sales, track stock, print receipts.">
<?php include __DIR__ . '/components/pwa_head.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  *{ box-sizing:border-box; margin:0; padding:0; }
  body{ font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;
        min-height:100svh; color:#e2e8f0; display:flex; flex-direction:column;
        align-items:center; justify-content:center;
        padding:24px; padding-top:max(24px,env(safe-area-inset-top)); padding-bottom:max(24px,env(safe-area-inset-bottom));
        background:radial-gradient(ellipse at 30% 20%,#0d1b3e,#0a0f1e 60%,#000510);
        perspective:1200px; overflow-x:hidden; }

  /* Stars */
  .stars{ position:fixed; inset:0; overflow:hidden; pointer-events:none; z-index:0; }
  .star{ position:absolute; border-radius:50%; background:#fff; animation:twinkle var(--d,3s) infinite alternate; }
  @keyframes twinkle{ 0%{ opacity:.1; transform:scale(.8); } 100%{ opacity:.9; transform:scale(1.2); } }

  /* Grid floor */
  .grid-plane{ position:fixed; bottom:-80px; left:50%; transform:translateX(-50%) rotateX(75deg);
               width:900px; height:600px; z-index:0; pointer-events:none;
               background:linear-gradient(rgba(15,118,110,.2) 1px,transparent 1px),
                           linear-gradient(90deg,rgba(15,118,110,.2) 1px,transparent 1px);
               background-size:40px 40px; }

  /* Ambient orbs */
  .orb{ position:fixed; border-radius:50%; filter:blur(60px); pointer-events:none; z-index:0;
        animation:drift var(--dt,8s) ease-in-out infinite alternate; }
  @keyframes drift{ 0%{ transform:translate(0,0); } 100%{ transform:translate(var(--tx,20px),var(--ty,-20px)); } }

  /* Floating chips */
  .floating-chip{ position:fixed; background:rgba(13,20,40,.9); border:1px solid rgba(255,255,255,.12);
                  border-radius:12px; padding:8px 14px; display:flex; align-items:center; gap:8px;
                  backdrop-filter:blur(20px); pointer-events:none; z-index:1;
                  animation:chipfloat var(--cf,5s) ease-in-out infinite alternate; }
  @keyframes chipfloat{ 0%{ transform:translateY(0) rotate(var(--cr,-2deg)); }
                        100%{ transform:translateY(-10px) rotate(var(--cr2,2deg)); } }
  .chip-icon{ width:28px; height:28px; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:14px; }
  .chip-label{ font-size:.68rem; font-weight:600; color:#94a3b8; line-height:1.3; }
  .chip-val{ color:#e2e8f0; font-size:.76rem; font-weight:700; }

  /* 3-D stage */
  .stage{ transform-style:preserve-3d; animation:bob 6s ease-in-out infinite; z-index:2; position:relative; }
  @keyframes bob{ 0%,100%{ transform:translateY(0) rotateX(0) rotateY(0); }
                  33%{ transform:translateY(-8px) rotateX(2deg) rotateY(-1deg); }
                  66%{ transform:translateY(-4px) rotateX(-1deg) rotateY(2deg); } }
  .wrap{ width:440px; max-width:100%; transform-style:preserve-3d; position:relative; transition:transform .1s ease; }

  /* Card layers */
  .card-shadow{ position:absolute; inset:0; transform:translateZ(-60px) translateY(60px) scale(.88);
                background:rgba(15,118,110,.3); filter:blur(40px); border-radius:24px; pointer-events:none; }
  .card-glow{ position:absolute; inset:-2px;
              background:linear-gradient(135deg,rgba(15,118,110,.5),rgba(45,212,191,.4),rgba(37,99,235,.3));
              border-radius:26px; transform:translateZ(-4px); filter:blur(1px); }
  .card-mid{ position:absolute; inset:-1px; background:rgba(255,255,255,.06); border-radius:25px;
             transform:translateZ(-2px); border:1px solid rgba(255,255,255,.12); }
  .card{ background:rgba(13,20,40,.92); border:1px solid rgba(255,255,255,.1); border-radius:24px;
         overflow:hidden; backdrop-filter:blur(40px); -webkit-backdrop-filter:blur(40px); position:relative; }
  .card::before{ content:''; position:absolute; inset:0;
                 background:linear-gradient(135deg,rgba(255,255,255,.05) 0%,transparent 50%,rgba(45,212,191,.04) 100%);
                 pointer-events:none; z-index:1; }
  .sheen{ position:absolute; inset:0;
          background:radial-gradient(circle at 50% 20%,rgba(255,255,255,.06) 0%,transparent 60%);
          pointer-events:none; z-index:3; transition:background .05s; }
  .inner{ position:relative; z-index:2; padding:32px; }

  /* Brand */
  .brand{ text-align:center; margin-bottom:24px; }
  .logo-box{ width:72px; height:72px; border-radius:18px; margin:0 auto 14px;
             background:linear-gradient(135deg,#0f766e,rgba(45,212,191,.1));
             border:1px solid rgba(45,212,191,.3);
             box-shadow:0 0 30px rgba(15,118,110,.4),inset 0 1px 0 rgba(255,255,255,.15);
             display:flex; align-items:center; justify-content:center; overflow:hidden; }
  .logo-box img{ width:100%; height:100%; object-fit:contain; padding:10px;
                 filter:brightness(0) invert(1) drop-shadow(0 0 6px rgba(45,212,191,.4)); }
  .logo-box .logo-fallback{ color:#2dd4bf; font-size:1.6rem; }
  .brand h1{ font-size:1.4rem; font-weight:800; color:#fff; letter-spacing:-.02em; margin-bottom:4px; }
  .brand p{ color:#64748b; font-size:.85rem; }

  /* Portal items */
  .lede{ font-size:.7rem; text-transform:uppercase; letter-spacing:.14em; color:#475569;
         text-align:center; margin-bottom:16px; }
  .portal{ display:flex; align-items:center; gap:14px; text-decoration:none; color:#fff;
           background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.1);
           border-radius:14px; padding:16px 18px; margin-bottom:10px; transition:all .2s; }
  .portal:last-of-type{ margin-bottom:0; }
  .portal:hover, .portal:focus-visible{
    transform:translateY(-2px); outline:none;
    box-shadow:0 8px 24px rgba(15,118,110,.25); }
  .portal.owner:hover{ background:rgba(45,212,191,.08); border-color:rgba(45,212,191,.5); }
  .portal.staff:hover{ background:rgba(96,165,250,.08); border-color:rgba(96,165,250,.5);
                       box-shadow:0 8px 24px rgba(37,99,235,.2); }
  .portal .ic{ width:46px; height:46px; border-radius:12px; display:flex; align-items:center;
               justify-content:center; font-size:1.2rem; flex-shrink:0; }
  .portal.owner .ic{ background:rgba(45,212,191,.15); color:#2dd4bf; }
  .portal.staff .ic{ background:rgba(96,165,250,.15); color:#93c5fd; }
  .portal .tx{ flex:1; }
  .portal .tx b{ display:block; font-size:.98rem; font-weight:700; margin-bottom:2px; }
  .portal .tx span{ font-size:.8rem; color:#64748b; }
  .portal .go{ color:#334155; font-size:.9rem; transition:transform .2s; }
  .portal:hover .go{ transform:translateX(3px); color:#94a3b8; }

  /* Install button */
  .install-btn{ display:none; width:100%; margin-top:14px; align-items:center; justify-content:center;
                gap:10px; background:linear-gradient(135deg,#0f766e,#2dd4bf); color:#04201d;
                border:0; border-radius:13px; padding:14px; font-size:.95rem; font-weight:800;
                cursor:pointer; transition:all .2s; }
  .install-btn:hover{ transform:translateY(-1px); box-shadow:0 8px 24px rgba(15,118,110,.4); }

  /* Misc */
  .hint{ text-align:center; color:#475569; font-size:.76rem; margin-top:14px; line-height:1.5; }
  .hint b{ color:#64748b; font-weight:600; }
  .foot{ text-align:center; color:#1e293b; font-size:.72rem; margin-top:18px; }

  @media(max-width:480px){
    .floating-chip{ display:none; }
    .inner{ padding:24px; }
  }
</style>
</head>
<body>
  <div class="stars" id="stars"></div>
  <div class="grid-plane"></div>
  <div class="orb" style="width:280px;height:280px;background:rgba(15,118,110,.22);top:0%;left:-8%;--dt:9s;--tx:25px;--ty:15px"></div>
  <div class="orb" style="width:220px;height:220px;background:rgba(37,99,235,.15);bottom:5%;right:-6%;--dt:11s;--tx:-20px;--ty:-25px"></div>
  <div class="orb" style="width:160px;height:160px;background:rgba(45,212,191,.1);top:55%;left:8%;--dt:7s;--tx:10px;--ty:20px"></div>

  <div class="floating-chip" style="top:12%;left:3%;--cf:6s;--cr:-3deg;--cr2:1deg">
    <div class="chip-icon" style="background:rgba(45,212,191,.15)"><i class="fa-solid fa-cart-shopping" style="color:#2dd4bf;font-size:12px"></i></div>
    <div><div class="chip-val">Live POS</div><div class="chip-label">Sales tracking</div></div>
  </div>
  <div class="floating-chip" style="bottom:18%;right:3%;--cf:7.5s;--cr:2deg;--cr2:-2deg">
    <div class="chip-icon" style="background:rgba(16,185,129,.15)"><i class="fa-solid fa-boxes-stacked" style="color:#10b981;font-size:12px"></i></div>
    <div><div class="chip-val">Stock</div><div class="chip-label">Managed</div></div>
  </div>
  <div class="floating-chip" style="top:45%;right:2%;--cf:5s;--cr:-1deg;--cr2:3deg">
    <div class="chip-icon" style="background:rgba(96,165,250,.15)"><i class="fa-solid fa-receipt" style="color:#93c5fd;font-size:12px"></i></div>
    <div><div class="chip-val">Receipts</div><div class="chip-label">Instant print</div></div>
  </div>

  <div class="stage" id="stage">
    <div class="wrap" id="wrap">
      <div class="card-shadow"></div>
      <div class="card-glow"></div>
      <div class="card-mid"></div>
      <div class="card">
        <div class="sheen" id="sheen"></div>
        <div class="inner">
          <div class="brand">
            <div class="logo-box">
              <img src="/Curlz/public/assets/images/logo/logo.png" alt="Curlz POS"
                   onerror="this.style.display='none';this.parentNode.innerHTML+='<i class=\'fa-solid fa-layer-group logo-fallback\'></i>'">
            </div>
            <h1>Curlz POS</h1>
            <p>Run your shop from your phone</p>
          </div>

          <?php if ($loggedIn): ?>
            <div class="lede">You're signed in</div>
            <a class="portal owner" href="<?php echo $h($dashUrl); ?>">
              <span class="ic"><i class="fa-solid fa-gauge-high"></i></span>
              <span class="tx"><b>Open the POS</b><span>Continue to your dashboard</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
            <a class="portal staff" href="/Curlz/public/auth/logout.php">
              <span class="ic"><i class="fa-solid fa-arrow-right-from-bracket"></i></span>
              <span class="tx"><b>Switch account</b><span>Log out and sign in as someone else</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
          <?php else: ?>
            <div class="lede">Sign in to continue</div>
            <a class="portal owner" href="<?php echo $h($LOGIN); ?>?as=owner">
              <span class="ic"><i class="fa-solid fa-user-shield"></i></span>
              <span class="tx"><b>Owner / Manager</b><span>Sales, stock, staff &amp; reports</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
            <a class="portal staff" href="<?php echo $h($LOGIN); ?>?as=staff">
              <span class="ic"><i class="fa-solid fa-cash-register"></i></span>
              <span class="tx"><b>Staff / Cashier</b><span>Make sales &amp; print receipts</span></span>
              <span class="go"><i class="fa-solid fa-arrow-right"></i></span>
            </a>
          <?php endif; ?>

          <button class="install-btn" id="installBtn" type="button">
            <i class="fa-solid fa-circle-down"></i> Install app
          </button>
        </div>
      </div>
    </div>
  </div>

  <p class="hint" id="iosHint" style="display:none;">
    To install: tap <b>Share</b> <i class="fa-solid fa-arrow-up-from-bracket"></i> then <b>Add to Home Screen</b>.
  </p>
  <p class="foot">Curlz POS &middot; works on phone &amp; desktop</p>

<script>
(function(){
  var s=document.getElementById('stars');
  for(var i=0;i<110;i++){
    var el=document.createElement('div');el.className='star';
    var sz=Math.random()*2.4+0.4;
    el.style.cssText='width:'+sz+'px;height:'+sz+'px;top:'+Math.random()*100+'%;left:'+Math.random()*100+'%;--d:'+(Math.random()*4+2)+'s;animation-delay:'+(Math.random()*4)+'s';
    s.appendChild(el);
  }

  var wrap=document.getElementById('wrap'),sheen=document.getElementById('sheen'),
      stage=document.getElementById('stage');

  document.addEventListener('mousemove',function(e){
    if(!wrap)return;
    var r=wrap.getBoundingClientRect();
    var dx=(e.clientX-(r.left+r.width/2))/r.width;
    var dy=(e.clientY-(r.top+r.height/2))/r.height;
    wrap.style.transform='rotateX('+(dy*14)+'deg) rotateY('+(-dx*14)+'deg)';
    stage.style.animation='none';
    sheen.style.background='radial-gradient(circle at '+((e.clientX-r.left)/r.width*100).toFixed(1)+'% '+((e.clientY-r.top)/r.height*100).toFixed(1)+'%,rgba(255,255,255,.09) 0%,transparent 55%)';
  });
  document.addEventListener('mouseleave',function(){
    if(wrap)wrap.style.transform='';
    if(stage)stage.style.animation='bob 6s ease-in-out infinite';
    if(sheen)sheen.style.background='';
  });

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