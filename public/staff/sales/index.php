<?php
// public/staff/sales/index.php — logged-in staff's own sales
// Defaults to TODAY only. Pass ?period=all to see full history.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_VIEW);

$pdo = Database::pdo();
$SA  = new Models\SaleModel($pdo);
$modules = StaffNav::staffModules($pdo);
$canSellProducts = StaffNav::canSellProducts($modules);
$canCheckIn = StaffNav::canCheckIn($modules);

$viewAll = ($_GET['period'] ?? '') === 'all';
$today   = date('Y-m-d');

$todaySales = $SA->unifiedForStaff(TenantContext::userId(), 500, $today);
$todaySum   = selfSummarize($todaySales);

$sales = $viewAll ? $SA->unifiedForStaff(TenantContext::userId()) : $todaySales;
$sum   = $viewAll ? selfSummarize($sales) : $todaySum;

function selfSummarize(array $rows): array
{
    $sum = ['count' => 0, 'revenue' => 0.0, 'cash' => 0.0, 'mpesa' => 0.0];
    foreach ($rows as $r) {
        $sum['count']++;
        $sum['revenue'] += (float) $r['total'];
        $method = $r['payment_method'] ?? 'cash';
        if ($method === 'cash' || $method === 'mpesa') {
            $sum[$method] = ($sum[$method] ?? 0) + (float) $r['total'];
        }
    }
    $sum['revenue'] = round($sum['revenue'], 2);
    return $sum;
}

function groupSalesByBranch(array $rows): array
{
    $groups = [];
    foreach ($rows as $r) {
        $key = ($r['branch_name'] ?? '') !== '' ? $r['branch_name'] : 'No branch';
        $groups[$key][] = $r;
    }
    return $groups;
}

$salesByBranch = groupSalesByBranch($sales);
$page_title = 'My sales';
ob_start();
?>
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
    <?php if ($canSellProducts): ?>
    <a href="<?php echo public_path('staff/sales/new.php'); ?>" class="btn btn-primary">
      <i class="fas fa-cash-register me-1"></i>Sell products
    </a>
    <?php endif; ?>
    <?php if ($canCheckIn): ?>
    <a href="<?php echo public_path('staff/checkin/'); ?>" class="btn btn-outline-primary">
      <i class="fas fa-user-check me-1"></i>Customer check-in
    </a>
    <?php endif; ?>
  </div>
</div>

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
        <?php echo $viewAll ? 'No sales recorded yet.' : 'No sales recorded today.'; ?>
      </div>
    <?php else: ?>
      <?php foreach ($salesByBranch as $branchName => $branchRows): ?>
      <div class="mb-4">
        <h3 class="h6 fw-bold mb-2"><i class="fas fa-code-branch me-1 text-primary"></i><?php echo htmlspecialchars($branchName); ?>
          <span class="badge bg-light text-dark ms-1"><?php echo count($branchRows); ?></span>
        </h3>
        <div class="table-responsive">
          <table class="table align-middle mb-0">
            <thead><tr class="text-muted small text-uppercase">
              <th>Receipt</th><th>When</th><th class="text-center">Items</th><th>Pay</th><th class="text-end">Total</th><th></th>
            </tr></thead>
            <tbody>
              <?php foreach ($branchRows as $s): ?>
              <tr>
                <td class="fw-semibold">
                  <?php echo htmlspecialchars($s['receipt_number']); ?>
                  <?php if ($s['customer_name']): ?>
                    <div class="text-muted small"><?php echo htmlspecialchars($s['customer_name']); ?></div>
                  <?php endif; ?>
                </td>
                <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($s['created_at'])); ?></td>
                <td class="text-center">
                  <span class="badge bg-light text-dark">
                    <?php echo ($s['sale_type'] ?? 'pos') === 'commission' ? htmlspecialchars($s['item_label'] ?? 'Service') : (int) $s['item_count']; ?>
                  </span>
                </td>
                <td><?php
                  $pay = $s['payment_method'] ?? 'cash';
                  echo $pay === 'cash' ? '<span class="badge bg-light text-dark">Cash</span>'
                      : ($pay === 'mpesa' ? '<span class="badge bg-success text-white">M-Pesa</span>'
                      : '<span class="badge bg-warning text-dark">Credit</span>');
                ?></td>
                <td class="text-end fw-semibold">KES <?php echo number_format((float)$s['total'],0); ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($s['receipt_url'] ?? ReceiptUrl::forUnifiedRow($s)); ?>">Receipt</a></td>
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
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
