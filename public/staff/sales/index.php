<?php
// public/staff/sales/index.php — logged-in staff's own sales
// Defaults to TODAY only. Pass ?period=all to see full history.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth(Capabilities::SALES_VIEW);

$pdo = Database::pdo();
$SA  = new Models\SaleModel($pdo);

$viewAll = ($_GET['period'] ?? '') === 'all';
$today   = date('Y-m-d');

// Today's sales (always computed for the stat card)
$todaySales = $SA->forStaff(TenantContext::userId(), 500, $today);
$todaySum   = Models\SaleModel::summarize($todaySales);

// Displayed list
$sales      = $viewAll ? $SA->forStaff(TenantContext::userId()) : $todaySales;
$sum        = $viewAll ? Models\SaleModel::summarize($sales) : $todaySum;

// Tenant info for catalogue share link
$__tenant     = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$tenantSlug   = $__tenant['slug'] ?? '';
$shopName     = $__tenant['name'] ?? 'Our Shop';
$catalogueUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . '/Curlz/public/catalogue.php?shop=' . urlencode($tenantSlug);

$page_title = 'My sales';
ob_start();
?>
<!-- ===== Actions row ===== -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold">Today</div>
        <div class="h5 mb-0 mt-1 fw-bold">KES <?php echo number_format($todaySum['revenue'],0); ?></div>
        <div class="text-muted small"><?php echo $todaySum['count']; ?> sale<?php echo $todaySum['count']!==1?'s':''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold"><?php echo $viewAll ? 'All time' : 'Shown'; ?></div>
        <div class="h5 mb-0 mt-1 fw-bold">KES <?php echo number_format($sum['revenue'],0); ?></div>
        <div class="text-muted small"><?php echo $sum['count']; ?> sale<?php echo $sum['count']!==1?'s':''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6 d-flex align-items-center gap-2 flex-wrap">
    <a href="/Curlz/public/staff/sales/new.php" class="btn btn-primary">
      <i class="fas fa-cash-register me-1"></i>Make a sale
    </a>
    <button type="button" class="btn btn-outline-secondary"
            data-bs-toggle="modal" data-bs-target="#shareCatalogueModal">
      <i class="fas fa-share-nodes me-1"></i>Share Catalogue
    </button>
  </div>
</div>

<!-- ===== Share Catalogue Banner ===== -->
<div class="card border-0 mb-4 overflow-hidden" style="border-radius:14px;">
  <div style="background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 60%,#1d4ed8 100%);padding:20px 24px;position:relative;">
    <div style="position:absolute;inset:0;background:radial-gradient(ellipse 50% 70% at 90% 10%,rgba(251,191,36,.14) 0%,transparent 55%);pointer-events:none;"></div>
    <div class="d-flex align-items-center gap-4" style="position:relative;">
      <div style="width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,.1);display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(255,255,255,.15);">
        <i class="fas fa-store" style="font-size:1.3rem;color:#93c5fd;"></i>
      </div>
      <div class="flex-grow-1">
        <div class="fw-bold text-white mb-1">Share Your Product Catalogue</div>
        <div style="color:rgba(255,255,255,.65);font-size:.83rem;">
          Let customers browse all products — share a link, WhatsApp or email. No login needed.
        </div>
      </div>
      <button type="button" class="btn btn-sm flex-shrink-0"
              data-bs-toggle="modal" data-bs-target="#shareCatalogueModal"
              style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:10px;padding:9px 16px;backdrop-filter:blur(6px);font-size:.82rem;">
        <i class="fas fa-share-nodes me-1" style="color:#a5b4fc;"></i>Share Now
      </button>
    </div>
  </div>
</div>

<!-- ===== Sales table ===== -->
<div class="card border-0 shadow-sm" style="border-radius:12px;">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h2 class="h6 fw-bold mb-0">
        <?php echo $viewAll ? 'All My Sales' : "Today's Sales — " . date('j M Y'); ?>
        <span class="badge bg-light text-dark ms-1"><?php echo count($sales); ?></span>
      </h2>
      <a href="?period=<?php echo $viewAll ? 'today' : 'all'; ?>" class="btn btn-sm btn-outline-secondary">
        <i class="fas fa-<?php echo $viewAll ? 'calendar-day' : 'history'; ?> me-1"></i>
        <?php echo $viewAll ? "Show today only" : "View all time"; ?>
      </a>
    </div>

    <?php if (!$sales): ?>
      <div class="text-muted py-4 text-center">
        <i class="fas fa-receipt fa-2x d-block mb-2" style="opacity:.25;"></i>
        <?php echo $viewAll ? 'No sales recorded yet.' : 'No sales recorded today. Tap "Make a sale" to start.'; ?>
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase">
            <th>Receipt</th><th>When</th><th class="text-center">Items</th><th>Pay</th><th class="text-end">Total</th><th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($sales as $s): ?>
            <tr>
              <td class="fw-semibold">
                <?php echo htmlspecialchars($s['receipt_number']); ?>
                <?php if ($s['customer_name']): ?>
                  <div class="text-muted small"><?php echo htmlspecialchars($s['customer_name']); ?></div>
                <?php endif; ?>
              </td>
              <td class="small text-nowrap"><?php echo date('g:i a', strtotime($s['created_at'])); ?></td>
              <td class="text-center"><span class="badge bg-light text-dark"><?php echo (int)$s['item_count']; ?></span></td>
              <td><?php echo $s['payment_method']==='cash' ? '<span class="badge bg-light text-dark">Cash</span>' : '<span class="badge bg-success text-white">M-Pesa</span>'; ?></td>
              <td class="text-end fw-semibold">KES <?php echo number_format((float)$s['total'],0); ?></td>
              <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/Curlz/public/staff/sales/receipt.php?id=<?php echo (int)$s['id']; ?>">Receipt</a></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
// Share modal (expects $catalogueUrl, $shopName)
include __DIR__ . '/../../components/tenants/share_modal.php';
?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';