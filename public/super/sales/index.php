<?php
// public/super/sales/index.php — enhanced owner view of all sales
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo  = Database::pdo();
$SA   = new Models\SaleModel($pdo);

// Period filter
$allowed = ['today', 'week', 'month', 'all'];
$period  = in_array($_GET['period'] ?? '', $allowed, true) ? $_GET['period'] : 'today';

$sales      = $SA->forTenant(1000, $period);
$sum        = Models\SaleModel::summarize($sales);
$staffBd    = Models\SaleModel::staffBreakdown($sales);
$branchBd   = Models\SaleModel::branchBreakdown($sales);

// Always compute today stats for the header card
$todaySales = ($period === 'today') ? $sales : $SA->forTenant(1000, 'today');
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
  <h1 class="h5 mb-0 fw-bold">Sales Overview</h1>
  <div class="btn-group">
    <?php foreach (['today'=>'Today','week'=>'7 days','month'=>'30 days','all'=>'All time'] as $p=>$lbl): ?>
    <a href="?period=<?php echo $p; ?>"
       class="btn btn-sm <?php echo $period===$p ? 'btn-primary' : 'btn-outline-secondary'; ?>">
      <?php echo $lbl; ?>
    </a>
    <?php endforeach; ?>
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
<?php if ($staffBd || $branchBd): ?>
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
  <?php if (count($branchBd) > 1): ?>
  <div class="col-12 col-md-6">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-code-branch me-2 text-success"></i>By Branch</h2>
        <table class="table table-sm align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Branch</th><th class="text-center">Sales</th><th class="text-end">Revenue</th></tr></thead>
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
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ===== Full sales table ===== -->
<div class="card border-0 shadow-sm" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h2 class="h6 fw-bold mb-0">
        Sales — <?php echo htmlspecialchars($periodLabel); ?>
        <span class="badge bg-light text-dark ms-1"><?php echo count($sales); ?></span>
      </h2>
      <div class="position-relative" style="max-width:220px;width:100%;">
        <i class="fas fa-search position-absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.8rem;pointer-events:none;"></i>
        <input type="text" id="saleSearch" class="form-control form-control-sm" placeholder="Filter table…" style="padding-left:30px;">
      </div>
    </div>
    <?php if (!$sales): ?>
      <div class="text-muted py-4 text-center">
        <i class="fas fa-receipt fa-2x mb-2 d-block text-muted" style="opacity:.3;"></i>
        No sales recorded for this period yet.
      </div>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle mb-0" id="saleTable">
          <thead><tr class="text-muted small text-uppercase">
            <th>Receipt</th><th>When</th><th>Branch</th><th>Staff</th><th>Customer</th><th>Pay</th><th class="text-end">Total</th><th></th>
          </tr></thead>
          <tbody>
            <?php foreach ($sales as $s): ?>
            <tr data-search="<?php echo strtolower(htmlspecialchars($s['receipt_number'].' '.$s['staff_name'].' '.$s['branch_name'].' '.($s['customer_name']??''))); ?>">
              <td class="fw-semibold small"><?php echo htmlspecialchars($s['receipt_number']); ?></td>
              <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($s['created_at'])); ?></td>
              <td class="small"><?php echo htmlspecialchars($s['branch_name'] ?: '—'); ?></td>
              <td class="small"><?php echo htmlspecialchars($s['staff_name'] ?: '—'); ?></td>
              <td class="small"><?php echo htmlspecialchars($s['customer_name'] ?: '—'); ?></td>
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

<script>
(function(){
  var inp = document.getElementById('saleSearch');
  if (!inp) return;
  inp.addEventListener('input', function(){
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll('#saleTable tbody tr').forEach(function(tr){
      tr.style.display = !q || tr.dataset.search.indexOf(q) !== -1 ? '' : 'none';
    });
  });
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';