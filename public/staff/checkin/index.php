<?php
// public/staff/checkin/index.php — official customer check-in for services
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

if (!TenantContext::can(Capabilities::CUSTOMERS_CHECKIN)
    && !TenantContext::can(Capabilities::INVOICES_MANAGE)
    && !TenantContext::can(Capabilities::COMMISSION_RECORD)) {
    header('Location: ' . public_path('auth/login.php?denied=1'));
    exit;
}

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);
CommissionService::ensureSchema($pdo);

$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$commSvc = new CommissionService($pdo);
$svcSvc = new OfferedServiceService($pdo);
$custSvc = new CustomerService($pdo);
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);

$stmt = $pdo->prepare('SELECT branch_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$branchId = (int) ($stmt->fetchColumn() ?: 0) ?: null;

$services = $svcSvc->activeForTenant($tenantId, $branchId);
$customers = $custSvc->listForTenant($tenantId);

$staffRoles = StaffRoles::employeeRoleNames();
$placeholders = implode(',', array_fill(0, count($staffRoles), '?'));
$staffSql = "SELECT u.id, u.username, u.staff_type FROM users u
              JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND u.is_active = 1 AND r.role_name IN ({$placeholders})";
$staffParams = array_merge([$tenantId], $staffRoles);
if ($branchId) {
    $staffSql .= ' AND (u.branch_id = ? OR u.branch_id IS NULL)';
    $staffParams[] = $branchId;
}
$staffSql .= ' ORDER BY u.username ASC';
$st = $pdo->prepare($staffSql);
$st->execute($staffParams);
$branchStaff = $st->fetchAll() ?: [];

$checkInDate = date('l, j F Y');
$checkInTime = date('g:i A');
$defaultCreditDays = (int) ($__tenant['default_credit_days'] ?? 30);
$serviceCreditsOn = !empty($__tenant['service_credits_enabled']) || !empty($__tenant['credits_enabled']);

$errors = [];
$prefillAppt = (int) ($_GET['appointment'] ?? 0);
$prefillName = '';
$prefillPhone = '';
$prefillServiceId = 0;
$prefillAgent = 0;

if ($prefillAppt > 0) {
    AppointmentService::ensureSchema($pdo);
    $appt = (new AppointmentService($pdo))->find($tenantId, $prefillAppt);
    if ($appt) {
        $prefillName = $appt['customer_name'] ?? '';
        $prefillPhone = $appt['customer_phone'] ?? '';
        $prefillServiceId = (int) ($appt['service_id'] ?? 0);
        $prefillAgent = (int) ($appt['agent_user_id'] ?? 0);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = json_decode($_POST['cart_json'] ?? '[]', true);
    $items = CommissionService::parseCartItems(is_array($cart) ? $cart : []);
    $items = array_values(array_filter($items, fn($i) => ($i['item_type'] ?? '') === 'service'));

    $common = [
        'customer_name'  => trim($_POST['customer_name'] ?? ''),
        'customer_phone' => trim($_POST['customer_phone'] ?? ''),
        'agent_user_id'  => (int) ($_POST['agent_user_id'] ?? 0),
        'branch_id'      => $branchId,
        'notes'          => trim($_POST['notes'] ?? ''),
    ];

    $res = $commSvc->recordCheckIn($tenantId, $userId, $common, $items);
    if ($res['ok']) {
        $apptId = (int) ($_POST['appointment_id'] ?? 0);
        if ($apptId > 0) {
            (new AppointmentService($pdo))->markCheckedIn($tenantId, $apptId);
        }
        $_SESSION['flash'] = ['success' => 'Customer checked in. Unpaid receipt sent to till (' . ($res['count'] ?? 1) . ' service(s)).'];
        header('Location: ' . ReceiptUrl::forCommission((int) $res['id']));
        exit;
    }
    $errors = $res['errors'];
}

$servicesJson = json_encode(array_map(fn($s) => [
    'id' => (int)$s['id'], 'name' => $s['name'], 'price' => (float)$s['charge_amount'],
], $services));

$customersJson = json_encode(array_map(fn($c) => [
    'id' => (int)$c['id'], 'name' => $c['name'], 'phone' => $c['phone'] ?? '',
    'credit_limit' => $c['credit_limit'] !== null ? (float)$c['credit_limit'] : null,
    'credit_balance' => (float)($c['credit_balance'] ?? 0),
], $customers));

$page_title = 'Customer check-in';
$extra_css = <<<'CSS'
<style>
.svc-pick{border-radius:10px;text-align:left;padding:14px;}
.cart-line{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #e2e8f0;}
.checkin-badge{background:#eff6ff;color:#1d4ed8;font-size:.85rem;padding:8px 14px;border-radius:8px;display:inline-block;}
.credit-banner{background:#fef3c7;border:1px solid #fcd34d;border-radius:10px;padding:12px 16px;font-size:.9rem;}
</style>
CSS;

ob_start();
?>
<div class="row g-4">
  <div class="col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
          <div>
            <h2 class="h5 fw-bold mb-1">Customer check-in</h2>
            <p class="text-muted small mb-0">
              <strong>Step 1 — Check in:</strong> record customer details and services here. No payment yet.<br>
              <strong>Step 2 — Pay at till:</strong> reception/cashier processes payment under Process payments.
            </p>
          </div>
          <span class="checkin-badge"><i class="fas fa-calendar-day me-1"></i><?php echo htmlspecialchars($checkInDate); ?> · <?php echo htmlspecialchars($checkInTime); ?></span>
        </div>

        <?php if (!$services): ?>
          <div class="alert alert-warning mb-0">No active services. Ask your manager to add services under Super → Services.</div>
        <?php elseif (!$branchStaff): ?>
          <div class="alert alert-warning mb-0">No staff to assign. Add staff at this branch first.</div>
        <?php else: ?>
        <form method="post" id="checkinForm">
          <input type="hidden" name="cart_json" id="cartJson" value="[]">
          <input type="hidden" name="appointment_id" value="<?php echo (int)$prefillAppt; ?>">

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label fw-semibold">Customer name <span class="text-danger">*</span></label>
              <input type="text" name="customer_name" id="customerName" class="form-control" required
                     value="<?php echo htmlspecialchars($_POST['customer_name'] ?? $prefillName); ?>">
              <?php if (!empty($errors['customer_name'])): ?><div class="text-danger small"><?php echo htmlspecialchars($errors['customer_name']); ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
              <label class="form-label fw-semibold">Phone</label>
              <input type="text" name="customer_phone" id="customerPhone" class="form-control"
                     value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? $prefillPhone); ?>">
            </div>
          </div>

          <div id="creditBanner" class="credit-banner mb-3 d-none">
            <strong><i class="fas fa-credit-card me-1"></i> Credit available:</strong>
            <span id="creditAvailableText">—</span>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Assign staff <span class="text-danger">*</span></label>
            <select name="agent_user_id" class="form-select" required>
              <option value="">— Stylist / barber for this visit —</option>
              <?php foreach ($branchStaff as $s): ?>
              <option value="<?php echo (int)$s['id']; ?>" <?php echo (int)($_POST['agent_user_id'] ?? $prefillAgent) === (int)$s['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($s['username']); ?>
                (<?php echo htmlspecialchars(StaffRoles::typeLabels()[$s['staff_type'] ?? 'general'] ?? 'Staff'); ?>)
              </option>
              <?php endforeach; ?>
            </select>
            <?php if (!empty($errors['agent_user_id'])): ?><div class="text-danger small"><?php echo htmlspecialchars($errors['agent_user_id']); ?></div><?php endif; ?>
          </div>

          <label class="form-label fw-semibold">Services for this visit</label>
          <div class="row g-2 mb-3">
            <?php foreach ($services as $svc): ?>
            <div class="col-6 col-md-4">
              <button type="button" class="btn btn-light w-100 border svc-pick" data-id="<?php echo (int)$svc['id']; ?>">
                <div class="fw-semibold"><?php echo htmlspecialchars($svc['name']); ?></div>
                <div class="text-success small">KES <?php echo number_format((float)$svc['charge_amount'], 0); ?></div>
              </button>
            </div>
            <?php endforeach; ?>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Notes</label>
            <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
          </div>

          <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>

          <button type="submit" class="btn btn-primary btn-lg w-100">
            <i class="fas fa-user-check me-1"></i> Check in &amp; generate receipt
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card border-0 shadow-sm sticky-top" style="border-radius:14px;top:20px;">
      <div class="card-body p-4">
        <h3 class="h6 fw-bold mb-3">Visit summary</h3>
        <div id="cartEmpty" class="text-muted small">Tap services to add them.</div>
        <div id="cartLines"></div>
        <div class="d-flex justify-content-between fw-bold mt-3 pt-3 border-top d-none" id="cartTotalRow">
          <span>Total (unpaid)</span>
          <span id="cartTotal">KES 0</span>
        </div>
        <p class="text-muted small mt-3 mb-0">
          Receipt goes to <strong>Payments</strong> at till. Reception processes cash, M-Pesa<?php echo $serviceCreditsOn ? ', or credit' : ''; ?>.
        </p>
        <?php if (TenantContext::can(Capabilities::APPOINTMENTS_MANAGE) && TenantModules::enabled($__tenant, TenantModules::APPOINTMENTS)): ?>
        <a href="<?php echo public_path('staff/appointments/'); ?>" class="btn btn-outline-secondary btn-sm w-100 mt-3">
          <i class="fas fa-calendar-plus me-1"></i> Book appointment instead
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  var services = <?php echo $servicesJson; ?>;
  var customers = <?php echo $customersJson; ?>;
  var defaultLimit = <?php echo (float)($__tenant['default_credit_limit'] ?? 5000); ?>;
  var svcMap = {};
  services.forEach(function(s){ svcMap[s.id] = s; });

  var cart = [];
  var prefillService = <?php echo (int)$prefillServiceId; ?>;
  if (prefillService && svcMap[prefillService]) {
    cart.push({type:'service', id:prefillService, name:svcMap[prefillService].name, charged:svcMap[prefillService].price, quantity:1});
  }

  function renderCart(){
    var lines = document.getElementById('cartLines');
    var empty = document.getElementById('cartEmpty');
    var totalRow = document.getElementById('cartTotalRow');
    var totalEl = document.getElementById('cartTotal');
    var json = document.getElementById('cartJson');
    if (!lines) return;
    if (!cart.length) {
      lines.innerHTML = '';
      empty.style.display = '';
      totalRow.classList.add('d-none');
      json.value = '[]';
      return;
    }
    empty.style.display = 'none';
    totalRow.classList.remove('d-none');
    var total = 0, html = '';
    cart.forEach(function(item, idx){
      total += item.charged;
      html += '<div class="cart-line"><div><strong>'+item.name+'</strong><br><span class="text-muted small">KES '+item.charged.toLocaleString()+'</span></div>';
      html += '<button type="button" class="btn btn-sm btn-outline-danger" data-idx="'+idx+'">&times;</button></div>';
    });
    lines.innerHTML = html;
    totalEl.textContent = 'KES ' + total.toLocaleString();
    json.value = JSON.stringify(cart.map(function(c){
      return {type:c.type, id:c.id, name:c.name, charged:c.charged, quantity:1};
    }));
    lines.querySelectorAll('button[data-idx]').forEach(function(btn){
      btn.addEventListener('click', function(){
        cart.splice(parseInt(btn.dataset.idx,10),1);
        renderCart();
      });
    });
  }

  document.querySelectorAll('.svc-pick').forEach(function(btn){
    btn.addEventListener('click', function(){
      var id = parseInt(btn.dataset.id,10);
      var s = svcMap[id];
      if (!s) return;
      cart.push({type:'service', id:id, name:s.name, charged:s.price, quantity:1});
      renderCart();
    });
  });

  function updateCredit(){
    var phone = (document.getElementById('customerPhone')||{}).value||'';
    var name = (document.getElementById('customerName')||{}).value||'';
    var banner = document.getElementById('creditBanner');
    var text = document.getElementById('creditAvailableText');
    var found = null;
    phone = phone.replace(/\s/g,'');
    customers.forEach(function(c){
      if (phone && c.phone && c.phone.replace(/\s/g,'') === phone) found = c;
      else if (!phone && name && c.name.toLowerCase() === name.toLowerCase()) found = c;
    });
    if (!found) { banner.classList.add('d-none'); return; }
    var limit = found.credit_limit != null ? found.credit_limit : defaultLimit;
    var used = found.credit_balance || 0;
    var avail = Math.max(0, limit - used);
    text.textContent = 'KES ' + avail.toLocaleString() + ' of KES ' + limit.toLocaleString() + ' limit (used KES ' + used.toLocaleString() + ')';
    banner.classList.remove('d-none');
  }

  ['customerPhone','customerName'].forEach(function(id){
    var el = document.getElementById(id);
    if (el) { el.addEventListener('input', updateCredit); el.addEventListener('blur', updateCredit); }
  });

  renderCart();
  updateCredit();
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
