<?php
// public/super/sales/index.php — enhanced owner view of all sales
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo  = Database::pdo();
$SA   = new Models\SaleModel($pdo);
$commSvc = new CommissionService($pdo);
$tenantId = (int) TenantContext::tenantId();
$__locations = (new Models\BranchModel($pdo))->listWithCounts();
$branchFilter = (int) ($_GET['branch'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void_sale') {
    $res = $SA->voidSale((int) ($_POST['sale_id'] ?? 0));
    $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
        ? 'Sale voided and stock restored.'
        : ($res['error'] ?? 'Could not void sale.');
    $redirectPeriod = in_array($_POST['period'] ?? '', ['today', 'week', 'month', 'all'], true) ? $_POST['period'] : 'today';
    $redirectBranch = (int) ($_POST['branch'] ?? 0);
    $url = public_path('super/sales/') . '?period=' . urlencode($redirectPeriod);
    if ($redirectBranch > 0) {
        $url .= '&branch=' . $redirectBranch;
    }
    header('Location: ' . $url);
    exit;
}

// Period filter
$allowed = ['today', 'week', 'month', 'all'];
$period  = in_array($_GET['period'] ?? '', $allowed, true) ? $_GET['period'] : 'today';

$sales      = $SA->forTenant(1000, $period, $branchFilter ?: null);
$commSales  = $commSvc->commissionSalesForTenant($tenantId, $period, $branchFilter ?: null);
$sum        = Models\SaleModel::summarize($sales);
$staffBd    = Models\SaleModel::staffBreakdown($sales);
$branchBd   = Models\SaleModel::branchBreakdown($sales);
$commBranchBd = CommissionService::branchBreakdown($commSales);

$salesByBranch = [];
foreach ($sales as $s) {
    $key = ($s['branch_name'] ?? '') !== '' ? $s['branch_name'] : 'No branch / shop';
    $salesByBranch[$key][] = $s;
}
$commByBranch = [];
foreach ($commSales as $s) {
    $key = ($s['branch_name'] ?? '') !== '' ? $s['branch_name'] : 'No branch / shop';
    $commByBranch[$key][] = $s;
}

// Always compute today stats for the header card
$todaySales = ($period === 'today') ? $sales : $SA->forTenant(1000, 'today', $branchFilter ?: null);
$todaySum   = ($period === 'today') ? $sum    : Models\SaleModel::summarize($todaySales);

$periodLabel = match ($period) {
    'today' => 'Today',
    'week'  => 'Last 7 days',
    'month' => 'Last 30 days',
    default => 'All time',
};

$page_title = 'Sales';
ob_start();
?>
<!-- ===== Period tabs ===== -->
<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
  <h1 class="h5 mb-0 fw-bold">Sales by branch / shop</h1>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <?php if ($__locations): ?>
    <form method="get" class="d-flex gap-2 align-items-center">
      <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
      <select name="branch" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="">All locations</option>
        <?php foreach ($__locations as $loc): ?>
        <option value="<?php echo (int)$loc['id']; ?>" <?php echo $branchFilter === (int)$loc['id'] ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($loc['title']); ?>
        </option>
        <?php endforeach; ?>
      </select>
    </form>
    <?php endif; ?>
  <div class="btn-group">
    <?php foreach (['today'=>'Today','week'=>'7 days','month'=>'30 days','all'=>'All time'] as $p=>$lbl): ?>
    <a href="?period=<?php echo $p; ?><?php echo $branchFilter ? '&branch='.$branchFilter : ''; ?>"
       class="btn btn-sm <?php echo $period===$p ? 'btn-primary' : 'btn-outline-secondary'; ?>">
      <?php echo $lbl; ?>
    </a>
    <?php endforeach; ?>
  </div>
  </div>
</div>

<!-- ===== Stat cards ===== -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="height:4px;background:linear-gradient(90deg,#2563eb,#7c3aed);"></div>
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold mb-1">Today's Revenue</div>
        <div class="h4 mb-0 fw-bold">KES <?php echo number_format($todaySum['revenue'],0); ?></div>
        <div class="text-muted small"><?php echo $todaySum['count']; ?> sale<?php echo $todaySum['count']!==1?'s':''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="height:4px;background:linear-gradient(90deg,#059669,#10b981);"></div>
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold mb-1"><?php echo htmlspecialchars($periodLabel); ?></div>
        <div class="h4 mb-0 fw-bold">KES <?php echo number_format($sum['revenue'],0); ?></div>
        <div class="text-muted small"><?php echo $sum['count']; ?> sale<?php echo $sum['count']!==1?'s':''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="height:4px;background:#f59e0b;"></div>
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold mb-1">Cash</div>
        <div class="h5 mb-0 fw-bold">KES <?php echo number_format($sum['cash'],0); ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="height:4px;background:#10b981;"></div>
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold mb-1">M-Pesa</div>
        <div class="h5 mb-0 fw-bold">KES <?php echo number_format($sum['mpesa'],0); ?></div>
      </div>
    </div>
  </div>
</div>

<!-- ===== Breakdown row ===== -->
<?php if ($staffBd || $branchBd || $commBranchBd): ?>
<div class="row g-3 mb-4">
  <?php if ($staffBd): ?>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-users me-2 text-primary"></i>By Staff Member</h2>
        <table class="table table-sm align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Staff</th><th class="text-center">Sales</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($staffBd as $name => $d): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($name); ?></td>
              <td class="text-center"><span class="badge bg-light text-dark"><?php echo $d['count']; ?></span></td>
              <td class="text-end fw-semibold text-primary">KES <?php echo number_format($d['revenue'],0); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($branchBd || $commBranchBd): ?>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-code-branch me-2 text-success"></i>POS sales by location</h2>
        <?php if (!$branchBd): ?>
          <p class="text-muted small mb-0">No POS sales for this period.</p>
        <?php else: ?>
        <table class="table table-sm align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Branch / shop</th><th class="text-center">Sales</th><th class="text-end">Revenue</th></tr></thead>
          <tbody>
            <?php foreach ($branchBd as $name => $d): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($name); ?></td>
              <td class="text-center"><span class="badge bg-light text-dark"><?php echo $d['count']; ?></span></td>
              <td class="text-end fw-semibold text-success">KES <?php echo number_format($d['revenue'],0); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-coins me-2 text-warning"></i>Commission sales by location</h2>
        <?php if (!$commBranchBd): ?>
          <p class="text-muted small mb-0">No commission sales for this period.</p>
        <?php else: ?>
        <table class="table table-sm align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Branch / shop</th><th class="text-center">Sales</th><th class="text-end">Charged</th></tr></thead>
          <tbody>
            <?php foreach ($commBranchBd as $name => $d): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($name); ?></td>
              <td class="text-center"><span class="badge bg-light text-dark"><?php echo $d['count']; ?></span></td>
              <td class="text-end fw-semibold">KES <?php echo number_format($d['revenue'],0); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== POS sales by branch ===== -->
<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h2 class="h6 fw-bold mb-0">
        POS sales — <?php echo htmlspecialchars($periodLabel); ?>
        <span class="badge bg-light text-dark ms-1"><?php echo count($sales); ?></span>
      </h2>
      <div class="position-relative" style="max-width:220px;width:100%;">
        <i class="fas fa-search position-absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.8rem;pointer-events:none;"></i>
        <input type="text" id="saleSearch" class="form-control form-control-sm" placeholder="Filter POS sales…" style="padding-left:30px;">
      </div>
    </div>
    <?php if (!$sales): ?>
      <div class="text-muted py-4 text-center">
        <i class="fas fa-receipt fa-2x mb-2 d-block text-muted" style="opacity:.3;"></i>
        No POS sales recorded for this period yet.
      </div>
    <?php else: ?>
      <?php foreach ($salesByBranch as $branchName => $branchRows): ?>
      <div class="mb-4">
        <h3 class="h6 fw-bold mb-2"><i class="fas fa-store me-1 text-primary"></i><?php echo htmlspecialchars($branchName); ?>
          <span class="badge bg-light text-dark ms-1"><?php echo count($branchRows); ?></span>
        </h3>
      <div class="table-responsive">
        <table class="table align-middle mb-0 saleTable">
          <thead><tr class="text-muted small text-uppercase">
            <th>Receipt</th><th>When</th><th>Staff</th><th>Customer</th><th>Pay</th><th class="text-end">Total</th><th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($branchRows as $s): ?>
            <tr data-search="<?php echo strtolower(htmlspecialchars($s['receipt_number'].' '.$s['staff_name'].' '.$s['branch_name'].' '.($s['customer_name']??''))); ?>">
              <td class="fw-semibold small"><?php echo htmlspecialchars($s['receipt_number']); ?></td>
              <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($s['created_at'])); ?></td>
              <td class="small"><?php echo htmlspecialchars($s['staff_name'] ?: '—'); ?></td>
              <td class="small"><?php echo htmlspecialchars($s['customer_name'] ?: '—'); ?></td>
              <td><?php echo $s['payment_method']==='cash' ? '<span class="badge bg-light text-dark">Cash</span>' : '<span class="badge bg-success text-white">M-Pesa</span>'; ?></td>
              <td class="text-end fw-semibold">KES <?php echo number_format((float)$s['total'],0); ?></td>
              <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-secondary" href="<?php echo public_path('staff/sales/receipt.php'); ?>?id=<?php echo (int)$s['id']; ?>">Receipt</a>
                <form method="post" class="d-inline" onsubmit="return confirm('Void this sale? Stock will be restored.');">
                  <input type="hidden" name="action" value="void_sale">
                  <input type="hidden" name="sale_id" value="<?php echo (int)$s['id']; ?>">
                  <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                  <input type="hidden" name="branch" value="<?php echo (int)$branchFilter; ?>">
                  <button class="btn btn-sm btn-outline-danger">Void</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- ===== Commission sales by branch ===== -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-4">
    <h2 class="h6 fw-bold mb-3">Commission sales — <?php echo htmlspecialchars($periodLabel); ?>
      <span class="badge bg-light text-dark ms-1"><?php echo count($commSales); ?></span>
    </h2>
    <?php if (!$commSales): ?>
      <p class="text-muted mb-0">No commission sales for this period.</p>
    <?php else: ?>
      <?php foreach ($commByBranch as $branchName => $branchRows): ?>
      <div class="mb-4">
        <h3 class="h6 fw-bold mb-2"><i class="fas fa-code-branch me-1 text-warning"></i><?php echo htmlspecialchars($branchName); ?>
          <span class="badge bg-light text-dark ms-1"><?php echo count($branchRows); ?></span>
        </h3>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr class="text-muted small text-uppercase"><th>Receipt</th><th>When</th><th>Agent</th><th>Item</th><th class="text-end">Charged</th><th class="text-end">Commission</th></tr></thead>
            <tbody>
              <?php foreach ($branchRows as $s): ?>
              <tr>
                <td class="small fw-semibold"><?php echo htmlspecialchars($s['receipt_number'] ?? '—'); ?></td>
                <td class="small"><?php echo date('j M, g:i a', strtotime($s['created_at'])); ?></td>
                <td class="small"><?php echo htmlspecialchars($s['agent_name'] ?? '—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($s['item_name']); ?></td>
                <td class="text-end">KES <?php echo number_format((float)$s['charged_amount'], 0); ?></td>
                <td class="text-end text-success fw-semibold">KES <?php echo number_format((float)$s['total_commission'], 0); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
(function(){
  var inp = document.getElementById('saleSearch');
  if (!inp) return;
  inp.addEventListener('input', function(){
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll('.saleTable tbody tr').forEach(function(tr){
      tr.style.display = !q || tr.dataset.search.indexOf(q) !== -1 ? '' : 'none';
    });
  });
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';