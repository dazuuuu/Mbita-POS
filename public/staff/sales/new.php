<?php
// public/staff/sales/new.php — PRODUCT sales only. Services use Customer check-in → pay at till.
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$modules = StaffNav::staffModules($pdo);

if (!StaffNav::canSellProducts($modules)) {
    if (StaffNav::canCheckIn($modules)) {
        header('Location: ' . public_path('staff/checkin/'));
        exit;
    }
    header('Location: ' . public_path('auth/login.php?denied=1'));
    exit;
}

$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();

$stmt = $pdo->prepare('SELECT u.branch_id, b.title FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?');
$stmt->execute([$userId]);
$me = $stmt->fetch() ?: [];
$branchId   = !empty($me['branch_id']) ? (int) $me['branch_id'] : null;
$branchName = $me['title'] ?? '';

$products = (new Models\ProductModel($pdo))->sellable($branchId);

$error = '';
$cartJson = '[]';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = json_decode($_POST['cart'] ?? '[]', true);
    $cartJson = $_POST['cart'] ?? '[]';
    if (!is_array($cart)) {
        $cart = [];
    }

    $items = [];
    foreach ($cart as $c) {
        $pid = (int) ($c['product_id'] ?? $c['id'] ?? 0);
        $qty = (float) ($c['quantity'] ?? 0);
        if ($pid > 0 && $qty > 0) {
            $items[] = ['product_id' => $pid, 'quantity' => $qty];
        }
    }

    if (!$items) {
        $error = 'Add at least one product.';
    } else {
        $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cash', 'mpesa'], true)
            ? $_POST['payment_method'] : '';
        $amountGiven = (float) ($_POST['amount_given'] ?? 0);

        if (!$paymentMethod) {
            $error = 'Choose how the customer paid.';
        } else {
            $res = (new Models\SaleModel($pdo))->record([
                'payment_method' => $paymentMethod,
                'amount_given'   => $amountGiven,
                'staff_id'       => $userId,
                'branch_id'      => $branchId,
                'customer_name'  => $_POST['customer_name'] ?? '',
                'customer_phone' => $_POST['customer_phone'] ?? '',
                'customer_email' => $_POST['customer_email'] ?? '',
                'items'          => $items,
            ]);
            if ($res['ok']) {
                $_SESSION['flash']['success'] = 'Product sale recorded — ' . $res['receipt_number'] . '.';
                header('Location: ' . ReceiptUrl::forPos((int) $res['sale_id']));
                exit;
            }
            $error = $res['errors']['_'] ?? ($res['errors']['payment_method'] ?? ($res['errors']['amount_given'] ?? 'Could not complete sale.'));
        }
    }
}

$page_title = 'Sell products';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$products): ?>
  <div class="alert alert-warning mb-0">No products in stock at this location.</div>
<?php else: ?>
<form method="post" id="saleForm">
<input type="hidden" name="cart" id="cartInput" value="">
<div class="row g-4">
  <div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:18px 20px;">
        <h2 class="h6 mb-1 text-white fw-bold">Products</h2>
        <?php if ($branchName): ?><span class="badge" style="background:rgba(255,255,255,.15);color:rgba(255,255,255,.85);font-size:.7rem;"><?php echo htmlspecialchars($branchName); ?></span><?php endif; ?>
        <div class="position-relative mt-3">
          <i class="fas fa-search position-absolute" style="left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.5);"></i>
          <input type="text" id="search" class="form-control" placeholder="Search products…" autocomplete="off"
                 style="padding-left:36px;border-radius:10px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;">
        </div>
      </div>
      <div class="card-body p-3">
        <div id="productList" style="max-height:420px;overflow-y:auto;">
          <?php foreach ($products as $idx => $p): ?>
          <button type="button" class="prod btn w-100 text-start border rounded mb-2 p-0 overflow-hidden pick-item"
                  data-id="<?php echo (int)$p['id']; ?>"
                  data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                  data-price="<?php echo (float)$p['selling_price']; ?>"
                  data-stock="<?php echo (float)$p['quantity']; ?>"
                  data-unit="<?php echo htmlspecialchars($p['unit'], ENT_QUOTES); ?>"
                  data-idx="<?php echo $idx; ?>">
            <div class="d-flex align-items-center">
              <div class="flex-shrink-0" style="width:56px;height:56px;background:#f1f5f9;">
                <?php if (!empty($p['image_path'])): ?>
                  <img src="<?php echo htmlspecialchars($p['image_path']); ?>" alt="" style="width:56px;height:56px;object-fit:cover;">
                <?php else: ?>
                  <div class="d-flex align-items-center justify-content-center h-100 text-muted"><i class="fas fa-box"></i></div>
                <?php endif; ?>
              </div>
              <div class="px-3 flex-grow-1">
                <div class="fw-semibold small"><?php echo htmlspecialchars($p['name']); ?></div>
                <small class="text-muted"><?php echo rtrim(rtrim(number_format((float)$p['quantity'], 2), '0'), '.'); ?> <?php echo htmlspecialchars($p['unit']); ?> in stock</small>
              </div>
              <div class="px-3 fw-bold text-primary small">KES <?php echo number_format((float)$p['selling_price'], 0); ?></div>
            </div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-3">Cart</h2>
        <div id="cartEmpty" class="text-muted small">Tap a product to add it.</div>
        <table class="table table-sm mb-2" id="cartTable" style="display:none;"><tbody id="cartRows"></tbody></table>
        <div class="d-flex justify-content-between border-top pt-2">
          <span class="fw-semibold">Total</span>
          <span class="fw-bold fs-5">KES <span id="totalOut">0</span></span>
        </div>
      </div>
    </div>
    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-4">
        <h2 class="h6 mb-3">Payment</h2>
        <div class="btn-group w-100 mb-3">
          <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked>
          <label class="btn btn-outline-primary" for="payCash"><i class="fas fa-money-bill-wave me-1"></i>Cash</label>
          <input type="radio" class="btn-check" name="payment_method" id="payMpesa" value="mpesa">
          <label class="btn btn-outline-success" for="payMpesa"><i class="fas fa-mobile-screen me-1"></i>M-Pesa</label>
        </div>
        <div id="cashBox" class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small">Cash given</label>
            <input type="number" step="0.01" min="0" name="amount_given" id="amountGiven" class="form-control">
          </div>
          <div class="col-6">
            <label class="form-label small">Change</label>
            <div class="form-control bg-light" id="changeOut">KES 0</div>
          </div>
        </div>
        <p class="small text-muted mb-2">Customer (optional)</p>
        <input name="customer_name" class="form-control mb-2" placeholder="Name">
        <div class="row g-2 mb-3">
          <div class="col-6"><input name="customer_phone" class="form-control" placeholder="Phone"></div>
          <div class="col-6"><input name="customer_email" type="email" class="form-control" placeholder="Email"></div>
        </div>
        <button type="submit" class="btn btn-primary w-100 btn-lg" id="completeBtn" disabled>Complete product sale</button>
      </div>
    </div>
  </div>
</div>
</form>

<script>
(function(){
  var PRODUCTS = {};
  document.querySelectorAll('.prod').forEach(function(b){
    PRODUCTS[b.dataset.id] = { id:b.dataset.id, name:b.dataset.name, price:parseFloat(b.dataset.price), stock:parseFloat(b.dataset.stock), unit:b.dataset.unit };
  });
  var cart = [], cartKey = 0;
  var rows = document.getElementById('cartRows'), table = document.getElementById('cartTable'), empty = document.getElementById('cartEmpty');
  var totalOut = document.getElementById('totalOut'), changeOut = document.getElementById('changeOut'), amountGiven = document.getElementById('amountGiven');
  var completeBtn = document.getElementById('completeBtn'), cartInput = document.getElementById('cartInput');

  function money(n){ return n.toLocaleString('en-KE',{maximumFractionDigits:0}); }
  function total(){ var t=0; cart.forEach(function(r){ t += r.price * r.quantity; }); return t; }
  function sync(){ cartInput.value = JSON.stringify(cart.map(function(r){ return { product_id: parseInt(r.id,10), quantity: r.quantity }; })); }
  function render(){
    rows.innerHTML = '';
    table.style.display = cart.length ? 'table' : 'none';
    empty.style.display = cart.length ? 'none' : 'block';
    cart.forEach(function(row){
      var tr = document.createElement('tr');
      tr.innerHTML = '<td><div class="fw-semibold">'+row.name+'</div><small class="text-muted">KES '+money(row.price)+' × '+row.quantity+'</small></td>'+
        '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-dec="'+row.key+'">−</button> <button type="button" class="btn btn-sm btn-outline-secondary" data-inc="'+row.key+'">+</button> <button type="button" class="btn btn-sm btn-link text-danger" data-del="'+row.key+'">×</button></td>'+
        '<td class="text-end fw-semibold">KES '+money(row.price*row.quantity)+'</td>';
      rows.appendChild(tr);
    });
    totalOut.textContent = money(total());
    completeBtn.disabled = !cart.length;
    sync();
    updateChange();
  }
  function addProduct(id){
    var p = PRODUCTS[id]; if (!p) return;
    var ex = cart.find(function(r){ return r.id === id; });
    if (ex) { if (ex.quantity+1 > p.stock) { alert('Not enough stock.'); return; } ex.quantity++; }
    else { if (p.stock < 1) { alert('Out of stock.'); return; } cart.push({ key:++cartKey, id:id, name:p.name, price:p.price, quantity:1 }); }
    render();
  }
  document.querySelectorAll('.prod').forEach(function(b){ b.addEventListener('click', function(){ addProduct(b.dataset.id); }); });
  rows.addEventListener('click', function(e){
    var t = e.target;
    if (t.dataset.inc){ var r=cart.find(function(x){ return x.key===parseInt(t.dataset.inc,10); }); if(r){ var p=PRODUCTS[r.id]; if(r.quantity+1<=p.stock) r.quantity++; render(); } }
    else if (t.dataset.dec){ var r2=cart.find(function(x){ return x.key===parseInt(t.dataset.dec,10); }); if(r2){ r2.quantity--; if(r2.quantity<=0) cart=cart.filter(function(x){ return x.key!==r2.key; }); render(); } }
    else if (t.dataset.del){ cart=cart.filter(function(x){ return x.key!==parseInt(t.dataset.del,10); }); render(); }
  });
  function isCash(){ return document.getElementById('payCash').checked; }
  function updateChange(){ if(!isCash()){ changeOut.textContent='—'; return; } var g=parseFloat(amountGiven.value)||0, ch=g-total(); changeOut.textContent = ch>=0 ? ('KES '+money(ch)) : 'short'; }
  document.querySelectorAll('input[name=payment_method]').forEach(function(r){ r.addEventListener('change', function(){ document.getElementById('cashBox').style.display = isCash()?'flex':'none'; updateChange(); }); });
  amountGiven.addEventListener('input', updateChange);
  document.getElementById('search').addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('.prod').forEach(function(b){ b.style.display = !q || b.dataset.name.toLowerCase().indexOf(q)!==-1 ? '' : 'none'; });
  });
  document.getElementById('saleForm').addEventListener('submit', function(e){
    if (!cart.length){ e.preventDefault(); alert('Add at least one product.'); return; }
    if (isCash() && (parseFloat(amountGiven.value)||0) < total()){ e.preventDefault(); alert('Cash given is less than total.'); }
  });
  render();
})();
</script>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
