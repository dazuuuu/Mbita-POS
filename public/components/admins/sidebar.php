<?php
// public/components/admins/sidebar.php — platform admin navigation
$username = $_SESSION['username'] ?? 'Admin';
$uri      = $_SERVER['REQUEST_URI'] ?? '';

$isOn = function (string $needle) use ($uri): string {
    return strpos($uri, $needle) !== false ? 'active' : '';
};

$nav = [
    ['href' => public_path('admins/dashboard/'), 'icon' => 'fa-gauge-high', 'label' => 'Dashboard', 'active' => $isOn('/admins/dashboard')],
    ['href' => public_path('admins/'), 'icon' => 'fa-user-shield', 'label' => 'Platform admins', 'active' => $isOn('/admins/index') || (strpos($uri, '/admins/') !== false && strpos($uri, '/admins/dashboard') === false && strpos($uri, '/admins/tenants') === false)],
    ['href' => public_path('devs/register-tenant.php') . '?key=curlz-dev', 'icon' => 'fa-store', 'label' => 'Register tenant', 'active' => $isOn('/register-tenant')],
];
?>
<div class="cd-overlay" id="cdOverlay"></div>
<aside class="cd-sidebar" id="cdSidebar">
  <div class="cd-sidebar-brand">
    <i class="fas fa-shield-halved"></i>
    <span>Platform</span>
    <small class="d-block text-muted" style="font-size:.65rem;font-weight:500;">System admin</small>
  </div>
  <nav>
    <ul class="cd-nav">
      <?php foreach ($nav as $item): ?>
      <li>
        <a href="<?php echo htmlspecialchars($item['href']); ?>" class="<?php echo $item['active']; ?>">
          <i class="fas <?php echo $item['icon']; ?>"></i>
          <span><?php echo htmlspecialchars($item['label']); ?></span>
        </a>
      </li>
      <?php endforeach; ?>
      <li>
        <a href="<?php echo public_path('auth/logout.php'); ?>" style="color:#e74c3c;margin-top:8px;">
          <i class="fas fa-arrow-right-from-bracket"></i>
          <span>Logout</span>
        </a>
      </li>
    </ul>
  </nav>
</aside>
<script>
(function(){
  var sb=document.getElementById('cdSidebar'),ov=document.getElementById('cdOverlay');
  window.cdToggleSidebar=function(){sb&&sb.classList.toggle('open');ov&&ov.classList.toggle('show');};
  ov&&ov.addEventListener('click',function(){sb.classList.remove('open');ov.classList.remove('show');});
})();
</script>
