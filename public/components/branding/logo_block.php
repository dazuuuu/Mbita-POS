<?php
// public/components/branding/logo_block.php
// Reusable logo + business name (menus, auth). Expects $__brandTenant or uses portal branding.
$__brandTenant = Branding::tenantOrPortal($__brandTenant ?? ($__tenant ?? null));
$__brandName   = Branding::shopName($__brandTenant);
$__brandLogo   = Branding::tenantLogoUrl($__brandTenant);
$__brandVariant = $__brandVariant ?? 'sidebar'; // sidebar | auth
?>
<?php if ($__brandVariant === 'auth'): ?>
<div class="brand-block brand-block--auth text-center mb-2">
  <?php if ($__brandLogo): ?>
  <img src="<?php echo htmlspecialchars($__brandLogo); ?>" alt="<?php echo htmlspecialchars($__brandName); ?>"
       class="brand-logo brand-logo--auth">
  <?php endif; ?>
  <div class="brand-name brand-name--auth"><?php echo htmlspecialchars($__brandName); ?></div>
</div>
<?php else: ?>
<div class="brand-block brand-block--sidebar">
  <?php if ($__brandLogo): ?>
  <img src="<?php echo htmlspecialchars($__brandLogo); ?>" alt="<?php echo htmlspecialchars($__brandName); ?>"
       class="brand-logo brand-logo--sidebar">
  <?php endif; ?>
  <div class="brand-name brand-name--sidebar"><?php echo htmlspecialchars($__brandName); ?></div>
  <?php if (!empty($__brandSubline)): ?>
  <small class="brand-subline"><?php echo htmlspecialchars($__brandSubline); ?></small>
  <?php endif; ?>
</div>
<?php endif; ?>
