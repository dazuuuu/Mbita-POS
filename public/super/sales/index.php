<?php
// public/super/sales/index.php — enhanced owner view of all sales (POS + commission)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo  = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
CommissionService::ensureSchema($pdo);
$SA   = new Models\SaleModel($pdo);
$commSvc = new CommissionService($pdo);
$tenantId = (int) TenantContext::tenantId();
$__locations = (new Models\BranchModel($pdo))->listWithCounts();
$branchFilter = (int) ($_GET['branch'] ?? 0);

$redirectSales = function (string $period, int $branchId): void {
    $url = public_path('super/sales/') . '?period=' . urlencode($period);
    if ($branchId > 0) {
        $url .= '&branch=' . $branchId;
    }
    header('Location: ' . $url);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'void_sale') {
    $res = $SA->voidSale((int) ($_POST['sale_id'] ?? 0));
    $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
        ? 'Sale voided and stock restored.'
        : ($res['error'] ?? 'Could not void sale.');
    $redirectPeriod = in_array($_POST['period'] ?? '', ['today', 'week', 'month', 'all'], true) ? $_POST['period'] : 'today';
    $redirectSales($redirectPeriod, (int) ($_POST['branch'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_commission_sale') {
    $res = $commSvc->deleteSale($tenantId, (int) ($_POST['sale_id'] ?? 0));
    $_SESSION['flash'][$res['ok'] ? 'success' : 'error'] = $res['ok']
        ? 'Sale removed.'
        : ($res['error'] ?? 'Could not delete sale.');
    $redirectPeriod = in_array($_POST['period'] ?? '', ['today', 'week', 'month', 'all'], true) ? $_POST['period'] : 'today';
    $redirectSales($redirectPeriod, (int) ($_POST['branch'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_manual_sale') {
    $recordType = ($_POST['record_type'] ?? '') === 'pos' ? 'pos' : 'commission';
    $payload = [
        'staff_id'        => (int) ($_POST['staff_id'] ?? 0),
        'item_name'       => trim($_POST['item_name'] ?? ''),
        'amount'          => (float) ($_POST['amount'] ?? 0),
        'sale_date'       => trim($_POST['sale_date'] ?? ''),
        'payment_method'  => $_POST['payment_method'] ?? 'cash',
        'branch_id'       => (int) ($_POST['branch_id'] ?? 0),
        'customer_name'   => trim($_POST['customer_name'] ?? ''),
        'item_type'       => ($_POST['item_type'] ?? '') === 'product' ? 'product' : 'service',
    ];
    $res = $recordType === 'pos'
        ? $SA->recordManual($payload)
        : $commSvc->recordManual($tenantId, $payload);
    if ($res['ok']) {
        $num = $res['receipt_number'] ?? '';
        $_SESSION['flash']['success'] = 'Past sale added' . ($num ? " ({$num})." : '.');
    } else {
        $err = $res['errors']['_'] ?? $res['errors']['item_name'] ?? $res['errors']['amount'] ?? $res['error'] ?? 'Could not add sale.';
        $_SESSION['flash']['error'] = $err;
    }
    $redirectPeriod = in_array($_POST['period'] ?? '', ['today', 'week', 'month', 'all'], true) ? $_POST['period'] : 'today';
    $redirectSales($redirectPeriod, (int) ($_POST['branch'] ?? 0));
}

$assigneeStmt = $pdo->prepare(
    "SELECT u.id, u.username, r.role_name
       FROM users u
       JOIN roles r ON r.id = u.role_id
      WHERE u.tenant_id = ? AND u.is_active = 1
        AND r.role_name IN ('tenant_owner','cashier','reception','sales','barber','stylist','general','junior_admin','sales_agent')
   ORDER BY u.username ASC"
);
$assigneeStmt->execute([$tenantId]);
$saleAssignees = $assigneeStmt->fetchAll() ?: [];

$allowed = ['today', 'week', 'month', 'all'];
$period  = in_array($_GET['period'] ?? '', $allowed, true) ? $_GET['period'] : 'today';

$unified    = $SA->unifiedForTenant(1000, $period, $branchFilter ?: null, true);
$sum        = Models\SaleModel::summarize($unified);
$staffBd    = Models\SaleModel::unifiedStaffBreakdown($unified);
$branchBd   = Models\SaleModel::branchBreakdown(array_filter($unified, fn($r) => ($r['sale_type'] ?? '') === 'pos'));
$commBranchBd = Models\SaleModel::branchBreakdown(array_filter($unified, fn($r) => ($r['sale_type'] ?? '') === 'commission'));

$salesByBranch = [];
foreach ($unified as $s) {
    $key = ($s['branch_name'] ?? '') !== '' ? $s['branch_name'] : 'No branch / shop';
    $salesByBranch[$key][] = $s;
}

$todayUnified = ($period === 'today')
    ? $unified
    : $SA->unifiedForTenant(1000, 'today', $branchFilter ?: null, true);
$todaySum = ($period === 'today') ? $sum : Models\SaleModel::summarize($todayUnified);

$periodLabel = match ($period) {
    'today' => 'Today',
    'week'  => 'Last 7 days',
    'month' => 'Last 30 days',
    default => 'All time',
};

$page_title = 'Sales';
ob_start();
?>
<?php if (!empty($_SESSION['flash']['success'])): ?>
  <div class="alert alert-success py-2"><?php echo htmlspecialchars($_SESSION['flash']['success']); unset($_SESSION['flash']['success']); ?></div>
<?php endif; ?>
<?php if (!empty($_SESSION['flash']['error'])): ?>
  <div class="alert alert-danger py-2"><?php echo htmlspecialchars($_SESSION['flash']['error']); unset($_SESSION['flash']['error']); ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <h2 class="h6 fw-bold mb-1"><i class="fas fa-clock-rotate-left me-2 text-secondary"></i>Add a past sale manually</h2>
    <p class="text-muted small mb-3">Record a sale that happened earlier — assign staff, item, amount, and date. Stock is not changed.</p>
    <form method="post" class="row g-3">
      <input type="hidden" name="action" value="add_manual_sale">
      <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
      <input type="hidden" name="branch" value="<?php echo (int)$branchFilter; ?>">
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Record as</label>
        <select name="record_type" class="form-select form-select-sm" id="manualRecordType">
          <option value="commission">Service / product sale</option>
          <option value="pos">POS (retail) sale</option>
        </select>
      </div>
      <div class="col-md-3" id="manualItemTypeWrap">
        <label class="form-label small fw-semibold">Type</label>
        <select name="item_type" class="form-select form-select-sm">
          <option value="service">Service</option>
          <option value="product">Product</option>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Staff member</label>
        <select name="staff_id" class="form-select form-select-sm" required>
          <option value="">— Choose staff —</option>
          <?php foreach ($saleAssignees as $u): ?>
          <option value="<?php echo (int)$u['id']; ?>">
            <?php echo htmlspecialchars($u['username']); ?>
            <?php if (($u['role_name'] ?? '') === 'tenant_owner'): ?> (Owner)<?php endif; ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ($__locations): ?>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Branch / shop</label>
        <select name="branch_id" class="form-select form-select-sm">
          <option value="">— Optional —</option>
          <?php foreach ($__locations as $loc): ?>
          <option value="<?php echo (int)$loc['id']; ?>"><?php echo htmlspecialchars($loc['title']); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="col-md-<?php echo $__locations ? '4' : '6'; ?>">
        <label class="form-label small fw-semibold">What was sold</label>
        <input type="text" name="item_name" class="form-control form-control-sm" placeholder="e.g. Haircut, Shampoo, etc." required>
      </div>
      <div class="col-md-<?php echo $__locations ? '4' : '6'; ?>">
        <label class="form-label small fw-semibold">Amount (KES)</label>
        <input type="number" name="amount" class="form-control form-control-sm" min="0.01" step="0.01" required>
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Date &amp; time</label>
        <input type="datetime-local" name="sale_date" class="form-control form-control-sm"
               value="<?php echo date('Y-m-d\TH:i'); ?>" required>
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Payment</label>
        <select name="payment_method" class="form-select form-select-sm">
          <option value="cash">Cash</option>
          <option value="mpesa">M-Pesa</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Customer <span class="text-muted fw-normal">(optional)</span></label>
        <input type="text" name="customer_name" class="form-control form-control-sm" placeholder="Customer name">
      </div>
      <div class="col-12">
        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-plus me-1"></i>Add past sale</button>
      </div>
    </form>
  </div>
</div>

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

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="height:4px;background:linear-gradient(90deg,#2563eb,#7c3aed);"></div>
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold mb-1">Today's Revenue</div>
        <div class="h4 mb-0 fw-bold">KES <?php echo number_format($todaySum['revenue'],0); ?></div>
        <div class="text-muted small"><?php echo $todaySum['count']; ?> paid sale<?php echo $todaySum['count']!==1?'s':''; ?></div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="height:4px;background:linear-gradient(90deg,#059669,#10b981);"></div>
      <div class="card-body p-3">
        <div class="text-muted small text-uppercase fw-semibold mb-1"><?php echo htmlspecialchars($periodLabel); ?></div>
        <div class="h4 mb-0 fw-bold">KES <?php echo number_format($sum['revenue'],0); ?></div>
        <div class="text-muted small"><?php echo $sum['count']; ?> paid<?php if ($sum['pending']): ?> · <?php echo $sum['pending']; ?> pending<?php endif; ?></div>
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

<?php if ($staffBd): ?>
<div class="row g-3 mb-4">
  <div class="col-12">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 fw-bold mb-3"><i class="fas fa-users me-2 text-primary"></i>By Staff Member</h2>
        <table class="table table-sm align-middle mb-0">
          <thead><tr class="text-muted small text-uppercase"><th>Staff</th><th class="text-center">Sales</th><th class="text-end">Revenue</th><th class="text-end">Commission</th></tr></thead>
          <tbody>
            <?php foreach ($staffBd as $name => $d): ?>
            <tr>
              <td class="fw-semibold"><?php echo htmlspecialchars($name); ?></td>
              <td class="text-center"><span class="badge bg-light text-dark"><?php echo $d['count']; ?></span></td>
              <td class="text-end fw-semibold text-primary">KES <?php echo number_format($d['revenue'],0); ?></td>
              <td class="text-end text-success">KES <?php echo number_format($d['commission'],0); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
  <div class="card-body p-4">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
      <h2 class="h6 fw-bold mb-0">
        All sales — <?php echo htmlspecialchars($periodLabel); ?>
        <span class="badge bg-light text-dark ms-1"><?php echo count($unified); ?></span>
      </h2>
      <div class="position-relative" style="max-width:220px;width:100%;">
        <i class="fas fa-search position-absolute" style="left:10px;top:50%;transform:translateY(-50%);color:#94a3b8;font-size:.8rem;pointer-events:none;"></i>
        <input type="text" id="saleSearch" class="form-control form-control-sm" placeholder="Filter sales…" style="padding-left:30px;">
      </div>
    </div>
    <?php if (!$unified): ?>
      <div class="text-muted py-4 text-center">
        <i class="fas fa-receipt fa-2x mb-2 d-block text-muted" style="opacity:.3;"></i>
        No sales recorded for this period yet.
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
              <th>Receipt</th><th>Type</th><th>When</th><th>Staff</th><th>Customer</th><th>Pay</th><th class="text-end">Total</th><th></th>
            </tr></thead>
            <tbody>
              <?php foreach ($branchRows as $s):
                $isPending = ($s['payment_status'] ?? 'paid') === 'pending';
                $typeLabel = ($s['sale_type'] ?? 'pos') === 'commission'
                    ? (($s['item_label'] ?? 'Commission'))
                    : 'POS (' . (int)($s['item_count'] ?? 0) . ' items)';
              ?>
              <tr data-search="<?php echo strtolower(htmlspecialchars(($s['receipt_number'] ?? '').' '.($s['staff_name'] ?? '').' '.($s['branch_name'] ?? '').' '.($s['customer_name'] ?? '').' '.$typeLabel)); ?>">
                <td class="fw-semibold small"><?php echo htmlspecialchars($s['receipt_number']); ?></td>
                <td class="small">
                  <?php if (($s['sale_type'] ?? '') === 'commission'): ?>
                    <span class="badge bg-warning text-dark">Service/Product</span>
                  <?php else: ?>
                    <span class="badge bg-primary">POS</span>
                  <?php endif; ?>
                </td>
                <td class="small text-nowrap"><?php echo date('j M, g:i a', strtotime($s['created_at'])); ?></td>
                <td class="small"><?php echo htmlspecialchars($s['staff_name'] ?: '—'); ?></td>
                <td class="small"><?php echo htmlspecialchars($s['customer_name'] ?: '—'); ?></td>
                <td>
                  <?php if ($isPending): ?>
                    <span class="badge bg-warning text-dark">Pending</span>
                  <?php elseif (($s['payment_method'] ?? '') === 'cash'): ?>
                    <span class="badge bg-light text-dark">Cash</span>
                  <?php else: ?>
                    <span class="badge bg-success text-white">M-Pesa</span>
                  <?php endif; ?>
                </td>
                <td class="text-end fw-semibold">KES <?php echo number_format((float)$s['total'],0); ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-secondary" href="<?php echo htmlspecialchars($s['receipt_url'] ?? ReceiptUrl::forUnifiedRow($s)); ?>">Receipt</a>
                  <?php if (($s['sale_type'] ?? '') === 'pos' && !$isPending): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Void this sale? Stock will be restored if it was a live POS sale.');">
                    <input type="hidden" name="action" value="void_sale">
                    <input type="hidden" name="sale_id" value="<?php echo (int)$s['id']; ?>">
                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                    <input type="hidden" name="branch" value="<?php echo (int)$branchFilter; ?>">
                    <button class="btn btn-sm btn-outline-danger">Void</button>
                  </form>
                  <?php elseif (($s['sale_type'] ?? '') === 'commission'): ?>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this sale permanently?');">
                    <input type="hidden" name="action" value="delete_commission_sale">
                    <input type="hidden" name="sale_id" value="<?php echo (int)$s['id']; ?>">
                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                    <input type="hidden" name="branch" value="<?php echo (int)$branchFilter; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                  <?php endif; ?>
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

<script>
(function(){
  var inp = document.getElementById('saleSearch');
  if (inp) {
    inp.addEventListener('input', function(){
      var q = this.value.toLowerCase().trim();
      document.querySelectorAll('.saleTable tbody tr').forEach(function(tr){
        tr.style.display = !q || tr.dataset.search.indexOf(q) !== -1 ? '' : 'none';
      });
    });
  }

  var recordType = document.getElementById('manualRecordType');
  var itemTypeWrap = document.getElementById('manualItemTypeWrap');
  if (recordType && itemTypeWrap) {
    var sync = function(){
      itemTypeWrap.style.display = recordType.value === 'commission' ? '' : 'none';
    };
    recordType.addEventListener('change', sync);
    sync();
  }
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
