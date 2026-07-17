<?php
// public/components/sales-agent/sidebar.php
$__tenant   = $__tenant ?? null;
$pdoSidebar = Database::pdo();
if ($__tenant === null && TenantContext::tenantId()) {
    $__tenant = (new Models\TenantModel($pdoSidebar))->find(TenantContext::tenantId());
}
$__tenant = Branding::tenantOrPortal($__tenant, $pdoSidebar);
$username   = $_SESSION['username'] ?? 'User';
$uri        = $_SERVER['REQUEST_URI'] ?? '';
$isOn = function (string $needle) use ($uri): string {
    return strpos($uri, $needle) !== false ? 'active' : '';
};
?>
<button class="t-sidebar-toggle" id="tSidebarToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
<div class="t-sidebar-overlay" id="tSidebarOverlay"></div>
<aside class="t-sidebar" id="tSidebar">
    <div class="t-brand">
        <button class="t-close" id="tSidebarClose" aria-label="Close"><i class="fas fa-times"></i></button>
        <?php include __DIR__ . '/../branding/logo_block.php'; ?>
        <div class="t-user">
            <?php echo htmlspecialchars($username); ?>
            <span class="t-role">Sales agent</span>
        </div>
    </div>
    <nav class="t-nav">
        <a class="t-link <?php echo $isOn('/sales-agent/dashboard'); ?>" href="<?php echo public_path('sales-agent/dashboard/'); ?>">
            <i class="fas fa-gauge-high"></i><span>Dashboard</span>
        </a>
        <a class="t-link <?php echo $isOn('/sales-agent/sales/new'); ?>" href="<?php echo public_path('sales-agent/sales/new.php'); ?>">
            <i class="fas fa-plus-circle"></i><span>Record sale</span>
        </a>
        <a class="t-link <?php echo $isOn('/sales-agent/sales/index'); ?>" href="<?php echo public_path('sales-agent/sales/'); ?>">
            <i class="fas fa-receipt"></i><span>My sales</span>
        </a>
        <hr>
        <a class="t-link t-danger" href="<?php echo public_path('auth/logout.php'); ?>">
            <i class="fas fa-arrow-right-from-bracket"></i><span>Logout</span>
        </a>
    </nav>
</aside>
<style>
:root { --t-bg:#0f172a; --t-bg2:#1e293b; --t-line:#334155; --t-accent:#2563eb; --t-text:#cbd5e1; }
.t-sidebar { width:264px; background:var(--t-bg); color:var(--t-text); position:fixed; left:0; top:0; height:100vh; overflow-y:auto; z-index:1001; transition:transform .3s ease; }
.t-brand { padding:24px 20px; border-bottom:1px solid var(--t-line); text-align:center; position:relative; }
.t-logo { height:44px; max-width:160px; object-fit:contain; background:#fff; border-radius:8px; padding:6px; }
.t-shop { margin-top:12px; font-weight:700; color:#fff; font-size:1.05rem; }
.t-user { margin-top:6px; font-size:.8rem; color:#94a3b8; }
.t-role { display:inline-block; margin-left:6px; padding:1px 8px; border-radius:999px; background:var(--t-bg2); color:#cbd5e1; font-size:.7rem; }
.t-nav { padding:14px 12px; }
.t-link { display:flex; align-items:center; gap:12px; padding:11px 14px; border-radius:8px; color:var(--t-text); text-decoration:none; font-size:.9rem; margin-bottom:3px; }
.t-link i { width:20px; color:#94a3b8; }
.t-link:hover, .t-link.active { background:var(--t-bg2); color:#fff; }
.t-link:hover i, .t-link.active i { color:#fff; }
.t-link.active { box-shadow:inset 3px 0 0 var(--t-accent); }
.t-danger { color:#f87171; }
.t-danger:hover { background:#7f1d1d; color:#fff; }
.t-nav hr { border:0; border-top:1px solid var(--t-line); margin:12px 0; }
.t-sidebar-toggle, .t-close { display:none; }
.t-sidebar-overlay { position:fixed; inset:0; background:rgba(0,0,0,.5); z-index:1000; opacity:0; display:none; transition:opacity .3s; }
.t-sidebar-overlay.active { display:block; opacity:1; }
@media (max-width:992px){
  .t-sidebar { transform:translateX(-100%); }
  .t-sidebar.active { transform:translateX(0); }
  .t-sidebar-toggle { display:flex; align-items:center; justify-content:center; position:fixed; top:12px; left:12px; z-index:1002; width:44px; height:44px; border:0; border-radius:8px; background:var(--t-bg); color:#fff; font-size:20px; box-shadow:0 2px 10px rgba(0,0,0,.2); }
  .t-close { display:flex; align-items:center; justify-content:center; position:absolute; top:12px; right:12px; width:30px; height:30px; border:0; border-radius:6px; background:rgba(255,255,255,.1); color:#fff; }
}
</style>
<script>
(function(){
  var tg=document.getElementById('tSidebarToggle'),sb=document.getElementById('tSidebar'),
      ov=document.getElementById('tSidebarOverlay'),cl=document.getElementById('tSidebarClose');
  function open(){sb&&sb.classList.add('active');ov&&ov.classList.add('active');document.body.style.overflow='hidden';}
  function close(){sb&&sb.classList.remove('active');ov&&ov.classList.remove('active');document.body.style.overflow='';}
  tg&&tg.addEventListener('click',open); cl&&cl.addEventListener('click',close); ov&&ov.addEventListener('click',close);
})();
</script>
