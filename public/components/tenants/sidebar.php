<?php
// public/components/tenants/sidebar.php — owner sidebar (business-type aware)
$__tenant   = $__tenant ?? null;
$shopName   = $__tenant['name'] ?? 'My Shop';
$businessType = $__tenant['business_type'] ?? 'shop';
$username   = $_SESSION['username'] ?? 'User';
$uri        = $_SERVER['REQUEST_URI'] ?? '';
$isOwner    = TenantContext::role() === 'tenant_owner';
$isJunior   = TenantContext::role() === 'junior_admin';
$dashUrl    = $isOwner || $isJunior ? '/Curlz/public/super/dashboard/' : '/Curlz/public/staff/dashboard/';

$isOn = function (string $needle) use ($uri): string {
    return strpos($uri, $needle) !== false ? 'active' : '';
};

$nav = [
    ['href' => $dashUrl, 'icon' => 'fa-chart-line', 'label' => 'Dashboard', 'active' => $isOn('/dashboard')],
];

if ($isOwner || TenantContext::can(Capabilities::SALES_VIEW)) {
    $nav[] = ['href' => '/Curlz/public/super/sales/', 'icon' => 'fa-receipt', 'label' => 'Sales', 'active' => $isOn('/super/sales')];
}

if ($businessType === 'shop' && ($isOwner || TenantContext::can(Capabilities::INVENTORY_EDIT))) {
    $nav[] = ['href' => '/Curlz/public/super/products/', 'icon' => 'fa-box', 'label' => 'Products', 'active' => $isOn('/super/products')];
    $nav[] = ['href' => '/Curlz/public/super/categories/', 'icon' => 'fa-tags', 'label' => 'Categories', 'active' => $isOn('/super/categories') || $isOn('/super/subcategories')];
}

if ($businessType === 'barbershop_salon' && $isOwner) {
    $nav[] = ['href' => '/Curlz/public/super/services/', 'icon' => 'fa-scissors', 'label' => 'Services', 'active' => $isOn('/super/services')];
    $nav[] = ['href' => '/Curlz/public/super/commissions/', 'icon' => 'fa-coins', 'label' => 'Commissions', 'active' => $isOn('/super/commissions')];
}

if ($businessType === 'shop' && $isOwner) {
    $nav[] = ['href' => '/Curlz/public/super/commissions/', 'icon' => 'fa-coins', 'label' => 'Commissions', 'active' => $isOn('/super/commissions')];
}

if ($isOwner) {
    $nav[] = ['href' => '/Curlz/public/super/staff/', 'icon' => 'fa-user-gear', 'label' => 'Staff', 'active' => $isOn('/super/staff')];
    if ($businessType === 'shop') {
        $nav[] = ['href' => '/Curlz/public/super/sales-agents/', 'icon' => 'fa-user-tie', 'label' => 'Sales Agents', 'active' => $isOn('/super/sales-agents')];
    }
}

if (TenantContext::can(Capabilities::REPORTS_VIEW)) {
    $nav[] = ['href' => '/Curlz/public/super/reports/', 'icon' => 'fa-chart-bar', 'label' => 'Reports', 'active' => $isOn('/super/reports')];
}

if ($isOwner && TenantContext::can(Capabilities::BRANCHES_MANAGE)) {
    $nav[] = ['href' => '/Curlz/public/super/branches/', 'icon' => 'fa-code-branch', 'label' => 'Branches', 'active' => $isOn('/super/branches')];
}

if ($isOwner) {
    $nav[] = ['href' => '/Curlz/public/super/settings/', 'icon' => 'fa-gear', 'label' => 'Settings', 'active' => $isOn('/super/settings')];
}
?>
<div class="cd-overlay" id="cdOverlay"></div>
<aside class="cd-sidebar" id="cdSidebar">
  <div class="cd-sidebar-brand">
    <i class="fas fa-chart-bar"></i>
    <span>CURLZ</span>
    <?php if ($businessType === 'barbershop_salon'): ?>
      <small class="d-block text-muted" style="font-size:.65rem;font-weight:500;">Barbershop &amp; Salon</small>
    <?php else: ?>
      <small class="d-block text-muted" style="font-size:.65rem;font-weight:500;">Retail Shop</small>
    <?php endif; ?>
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
        <a href="/Curlz/public/auth/logout.php" style="color:#e74c3c;margin-top:8px;">
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
