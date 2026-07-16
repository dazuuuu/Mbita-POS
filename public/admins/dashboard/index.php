<?php
// public/admins/dashboard/index.php — platform admin overview
require_once __DIR__ . '/../../../app/app.php';
PageGuard::platform();

$pdo = Database::pdo();
$adminSvc = new PlatformAdminService($pdo);

$tenantCount = (int) $pdo->query("SELECT COUNT(*) FROM tenants")->fetchColumn();
$activeTenants = (int) $pdo->query("SELECT COUNT(*) FROM tenants WHERE status = 'active'")->fetchColumn();
$adminCount = $adminSvc->count();

$page_title = 'Dashboard';
ob_start();
?>
<div class="cd-stat-row">
  <div class="cd-stat-card">
    <div class="cd-stat-icon" style="background:#1e40af;">
      <i class="fas fa-store"></i>
    </div>
    <div class="cd-stat-body">
      <div class="cd-stat-value"><?php echo $tenantCount; ?></div>
      <div class="cd-stat-label">Total tenants</div>
    </div>
  </div>
  <div class="cd-stat-card">
    <div class="cd-stat-icon" style="background:#059669;">
      <i class="fas fa-circle-check"></i>
    </div>
    <div class="cd-stat-body">
      <div class="cd-stat-value"><?php echo $activeTenants; ?></div>
      <div class="cd-stat-label">Active tenants</div>
    </div>
  </div>
  <div class="cd-stat-card">
    <div class="cd-stat-icon" style="background:#7c3aed;">
      <i class="fas fa-user-shield"></i>
    </div>
    <div class="cd-stat-body">
      <div class="cd-stat-value"><?php echo $adminCount; ?></div>
      <div class="cd-stat-label">Platform admins</div>
    </div>
  </div>
</div>

<div class="row g-4 mt-1">
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-3">Quick actions</h2>
        <div class="d-grid gap-2">
          <a class="btn btn-outline-primary" href="<?php echo public_path('admins/'); ?>">Manage platform admins</a>
          <a class="btn btn-outline-secondary" href="<?php echo public_path('devs/register-tenant.php'); ?>?key=curlz-dev">Register new tenant (dev tool)</a>
        </div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-3">Recent tenants</h2>
        <?php
        $recent = $pdo->query('SELECT id, name, slug, status, business_type FROM tenants ORDER BY id DESC LIMIT 8')->fetchAll();
        if (!$recent):
        ?>
          <p class="text-muted mb-0">No tenants registered yet.</p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
              <thead><tr class="text-muted small"><th>Shop</th><th>Code</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($recent as $t): ?>
                <tr>
                  <td><?php echo htmlspecialchars($t['name']); ?></td>
                  <td><code><?php echo htmlspecialchars($t['slug']); ?></code></td>
                  <td><span class="badge bg-light text-dark"><?php echo htmlspecialchars($t['status']); ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/admins/layout.php';
