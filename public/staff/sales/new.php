<?php
// public/staff/sales/new.php  — point-of-sale: record a sale (services + products)
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::SALES_RECORD);

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();

CommissionService::ensureSchema($pdo);

$stmt = $pdo->prepare('SELECT u.branch_id, b.title FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?');
$stmt->execute([$userId]);
$me = $stmt->fetch() ?: [];
$branchId   = !empty($me['branch_id']) ? (int) $me['branch_id'] : null;
$branchName = $me['title'] ?? '';

$__tenant   = (new Models\TenantModel($pdo))->find($tenantId);
$tenantSlug = $__tenant['slug'] ?? '';
$shopName   = $__tenant['name'] ?? 'Our Shop';
$catalogueUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
              . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
              . public_path('catalogue.php?shop=') . urlencode($tenantSlug);

$P = new Models\ProductModel($pdo);
$products = $P->sellable();
$services = (new OfferedServiceService($pdo))->activeForTenant($tenantId);

$serviceIndex = [];
foreach ($services as $s) {
    $serviceIndex[(int) $s['id']] = $s;
}

$error   = '';
$cartJson = '[]';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = json_decode($_POST['cart'] ?? '[]', true);
    $cartJson = $_POST['cart'] ?? '[]';
    if (!is_array($cart)) {
        $cart = [];
    }

    $productItems = [];
    $serviceItems = [];
    foreach ($cart as $c) {
        if (($c['type'] ?? 'product') === 'service') {
            $sid = (int) ($c['service_id'] ?? 0);
            $charged = (float) ($c['charged_amount'] ?? 0);
            if ($sid > 0 && $charged > 0) {
                $serviceItems[] = [
                    'item_type'      => 'service',
                    'service_id'     => $sid,
                    'charged_amount' => $charged,
                    'quantity'       => 1,
                ];
            }
        } else {
            $pid = (int) ($c['product_id'] ?? 0);
            $qty = (float) ($c['quantity'] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $productItems[] = ['product_id' => $pid, 'quantity' => $qty];
            }
        }
    }

    if (!$productItems && !$serviceItems) {
        $error = 'Add at least one service or product to the sale.';
    } else {
        $paymentMethod = in_array($_POST['payment_method'] ?? '', ['cash', 'mpesa'], true)
            ? $_POST['payment_method'] : '';
        $amountGiven = (float) ($_POST['amount_given'] ?? 0);

        $productTotal = 0.0;
        foreach ($productItems as $it) {
            foreach ($products as $p) {
                if ((int) $p['id'] === (int) $it['product_id']) {
                    $productTotal += (float) $p['selling_price'] * (float) $it['quantity'];
                    break;
                }
            }
        }
        $serviceTotal = array_sum(array_column($serviceItems, 'charged_amount'));
        $grandTotal = round($productTotal + $serviceTotal, 2);

        if (!$paymentMethod) {
            $error = 'Choose how the customer paid.';
        } elseif ($paymentMethod === 'cash' && $amountGiven + 0.0001 < $grandTotal) {
            $error = 'Cash given is less than the total (KES ' . number_format($grandTotal, 2) . ').';
        } else {
            $productRes = null;
            $serviceRes = null;
            $commSvc = new CommissionService($pdo);
            $mixedSale = $productItems && $serviceItems;
            $productCashGiven = $paymentMethod === 'cash'
                ? ($mixedSale ? $productTotal : $amountGiven)
                : $grandTotal;

            $pdo->beginTransaction();
            try {
                if ($productItems) {
                    $productRes = (new Models\SaleModel($pdo))->record([
                        'payment_method' => $paymentMethod,
                        'amount_given'   => $productCashGiven,
                        'staff_id'       => $userId,
                        'branch_id'      => $branchId,
                        'customer_name'  => $_POST['customer_name'] ?? '',
                        'customer_phone' => $_POST['customer_phone'] ?? '',
                        'customer_email' => $_POST['customer_email'] ?? '',
                        'items'          => $productItems,
                    ]);
                    if (!$productRes['ok']) {
                        $error = $productRes['errors']['_'] ?? ($productRes['errors']['payment_method'] ?? ($productRes['errors']['amount_given'] ?? 'Could not record product sale.'));
                    }
                }

                if (!$error && $serviceItems) {
                    $svcCommon = [
                        'payment_method' => $paymentMethod,
                        'customer_name'  => $_POST['customer_name'] ?? '',
                        'customer_phone' => $_POST['customer_phone'] ?? '',
                        'branch_id'      => $branchId,
                        'notes'          => '',
                    ];
                    $serviceRes = count($serviceItems) === 1
                        ? $commSvc->recordSale($tenantId, $userId, array_merge($svcCommon, $serviceItems[0]))
                        : $commSvc->recordSaleBatch($tenantId, $userId, $svcCommon, $serviceItems);
                    if (!$serviceRes['ok']) {
                        $error = $serviceRes['errors']['_'] ?? ($serviceRes['errors']['customer_name'] ?? 'Could not record service sale.');
                    }
                }

                if ($error) {
                    $pdo->rollBack();
                } else {
                    $pdo->commit();
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = 'Could not complete the sale. Please try again.';
            }

            if (!$error) {
                if ($productRes && $serviceRes) {
                    $svcNote = ($serviceRes['count'] ?? 1) > 1
                        ? count($serviceRes['receipt_numbers']) . ' service receipts'
                        : $serviceRes['receipt_number'];
                    $_SESSION['flash']['success'] = 'Sale recorded — products: ' . $productRes['receipt_number'] . ', services: ' . $svcNote . '.';
                    header('Location: ' . public_path('staff/sales/receipt.php') . '?id=' . (int) $productRes['sale_id']);
                    exit;
                }
                if ($productRes) {
                    $_SESSION['flash']['success'] = 'Sale recorded — ' . $productRes['receipt_number'] . '.';
                    header('Location: ' . public_path('staff/sales/receipt.php') . '?id=' . (int) $productRes['sale_id']);
                    exit;
                }
                if ($serviceRes) {
                    $_SESSION['flash']['success'] = 'Service sale recorded — ' . $serviceRes['receipt_number'] . '.';
                    header('Location: ' . public_path('commission/receipt.php') . '?id=' . (int) $serviceRes['id']);
                    exit;
                }
            }
        }
    }
}

$hasServices = (bool) $services;
$hasProducts = (bool) $products;
$defaultTab = $hasServices ? 'services' : 'products';

$page_title = 'Make a sale';
ob_start();
?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

<?php if (!$hasServices && !$hasProducts): ?>
  <div class="alert alert-warning">
    Nothing to sell yet. Ask the owner to add <strong>services</strong> (Super → Services) and/or <strong>products</strong> with stock.
  </div>
<?php else: ?>
<form method="post" id="saleForm">
<input type="hidden" name="cart" id="cartInput" value="">
<div class="row g-4">

  <div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm" style="border-radius:14px;overflow:hidden;">
      <div style="background:linear-gradient(135deg,#0f172a,#1e3a5f);padding:18px 20px;">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
          <div>
            <div class="btn-group btn-group-sm mb-2" role="group" id="saleTabs">
              <?php if ($hasServices): ?>
              <button type="button" class="btn <?php echo $defaultTab === 'services' ? 'btn-light' : 'btn-outline-light'; ?>" data-pane="services">
                <i class="fas fa-scissors me-1"></i>Services
              </button>
              <?php endif; ?>
              <?php if ($hasProducts): ?>
              <button type="button" class="btn <?php echo $defaultTab === 'products' ? 'btn-light' : 'btn-outline-light'; ?>" data-pane="products">
                <i class="fas fa-box me-1"></i>Products
              </button>
              <?php endif; ?>
            </div>
            <h2 class="h6 mb-0 text-white fw-bold" id="paneTitle">
              <?php echo $defaultTab === 'services' ? 'Services' : 'Products'; ?>
            </h2>
            <?php if ($branchName): ?><span class="badge mt-1" style="background:rgba(255,255,255,.15);color:rgba(255,255,255,.85);font-size:.7rem;"><?php echo htmlspecialchars($branchName); ?></span><?php endif; ?>
          </div>
          <?php if ($hasProducts): ?>
          <button type="button" class="btn btn-sm fw-semibold"
                  data-bs-toggle="modal" data-bs-target="#shareCatalogueModal"
                  style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:9px;font-size:.8rem;">
            <i class="fas fa-share-nodes me-1" style="color:#a5b4fc;"></i>Share Catalogue
          </button>
          <?php endif; ?>
        </div>
        <div class="position-relative">
          <i class="fas fa-search position-absolute" style="left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.5);font-size:.85rem;"></i>
          <input type="text" id="search" class="form-control"
                 placeholder="Search…" autocomplete="off"
                 style="padding-left:36px;border-radius:10px;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.2);color:#fff;font-size:.9rem;">
        </div>
        <div id="searchHint" class="mt-2" style="font-size:.75rem;color:rgba(255,255,255,.5);"></div>
      </div>

      <div class="card-body p-3" style="background:#fff;">
        <?php if ($hasServices): ?>
        <div id="servicesPane" class="picker-pane" style="<?php echo $defaultTab === 'products' ? 'display:none;' : ''; ?>">
          <div id="serviceList" style="max-height:420px;overflow-y:auto;">
            <?php foreach ($services as $idx => $s): ?>
            <button type="button"
                    class="svc pick-item btn w-100 text-start border rounded mb-2 p-0 overflow-hidden"
                    data-type="service"
                    data-id="<?php echo (int)$s['id']; ?>"
                    data-name="<?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?>"
                    data-price="<?php echo (float)$s['charge_amount']; ?>"
                    data-idx="<?php echo $idx; ?>"
                    style="transition:all .15s;border-color:#e2e8f0!important;">
              <div class="d-flex align-items-center gap-0">
                <div class="flex-shrink-0 d-flex align-items-center justify-content-center"
                     style="width:60px;height:60px;background:#eff6ff;color:#2563eb;">
                  <i class="fas fa-scissors" style="font-size:1.2rem;"></i>
                </div>
                <div class="px-3 flex-grow-1 text-start">
                  <div class="fw-semibold" style="font-size:.88rem;color:#0f172a;"><?php echo htmlspecialchars($s['name']); ?></div>
                  <small class="text-muted">Service · tap to add</small>
                </div>
                <div class="px-3 text-nowrap fw-bold" style="font-size:.88rem;color:#16a34a;">
                  KES <?php echo number_format((float)$s['charge_amount'], 0); ?>
                </div>
              </div>
            </button>
            <?php endforeach; ?>
            <div id="noServiceMatch" class="text-muted small text-center py-3" style="display:none;">
              <i class="fas fa-search me-1"></i>No services match.
            </div>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($hasProducts): ?>
        <div id="productsPane" class="picker-pane" style="<?php echo $defaultTab === 'services' ? 'display:none;' : ''; ?>">
          <div id="productList" style="max-height:420px;overflow-y:auto;">
            <?php foreach ($products as $idx => $p): ?>
            <button type="button"
                    class="prod pick-item btn w-100 text-start border rounded mb-2 p-0 overflow-hidden"
                    data-type="product"
                    data-id="<?php echo (int)$p['id']; ?>"
                    data-name="<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>"
                    data-price="<?php echo (float)$p['selling_price']; ?>"
                    data-stock="<?php echo (float)$p['quantity']; ?>"
                    data-unit="<?php echo htmlspecialchars($p['unit'], ENT_QUOTES); ?>"
                    data-idx="<?php echo $idx; ?>"
                    style="transition:all .15s;border-color:#e2e8f0!important;">
              <div class="d-flex align-items-center gap-0">
                <div class="flex-shrink-0" style="width:60px;height:60px;background:#f1f5f9;overflow:hidden;">
                  <?php if (!empty($p['image_path'])): ?>
                    <img src="<?php echo htmlspecialchars($p['image_path']); ?>"
                         alt="" style="width:60px;height:60px;object-fit:cover;">
                  <?php else: ?>
                    <div class="d-flex align-items-center justify-content-center h-100" style="color:#94a3b8;">
                      <i class="fas fa-box" style="font-size:1.3rem;"></i>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="px-3 flex-grow-1 text-start">
                  <div class="fw-semibold" style="font-size:.88rem;color:#0f172a;"><?php echo htmlspecialchars($p['name']); ?></div>
                  <small class="text-muted"><?php echo rtrim(rtrim(number_format((float)$p['quantity'],2),'0'),'.'); ?> <?php echo htmlspecialchars($p['unit']); ?> in stock</small>
                </div>
                <div class="px-3 text-nowrap fw-bold" style="font-size:.88rem;color:#1d4ed8;">
                  KES <?php echo number_format((float)$p['selling_price'], 0); ?>
                </div>
              </div>
            </button>
            <?php endforeach; ?>
            <div id="noProductMatch" class="text-muted small text-center py-3" style="display:none;">
              <i class="fas fa-search me-1"></i>No products match.
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-6">
    <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
      <div class="card-body p-3 p-md-4">
        <h2 class="h6 mb-3">This sale</h2>
        <div id="cartEmpty" class="text-muted small mb-2">Tap a service or product to add it.</div>
        <table class="table table-sm align-middle mb-2" id="cartTable" style="display:none;">
          <tbody id="cartRows"></tbody>
        </table>
        <div class="d-flex justify-content-between border-top pt-2">
          <span class="fw-semibold">Total</span>
          <span class="fw-bold fs-5">KES <span id="totalOut">0</span></span>
        </div>
      </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:14px;">
      <div class="card-body p-3 p-md-4">
        <h2 class="h6 mb-3">Payment</h2>
        <div class="btn-group w-100 mb-3" role="group">
          <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked>
          <label class="btn btn-outline-primary" for="payCash"><i class="fas fa-money-bill-wave me-1"></i>Cash</label>
          <input type="radio" class="btn-check" name="payment_method" id="payMpesa" value="mpesa">
          <label class="btn btn-outline-success" for="payMpesa"><i class="fas fa-mobile-screen me-1"></i>M-Pesa</label>
        </div>

        <div id="cashBox">
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small">Cash given</label>
              <input type="number" step="0.01" min="0" name="amount_given" id="amountGiven" class="form-control" placeholder="0">
            </div>
            <div class="col-6">
              <label class="form-label small">Change</label>
              <div class="form-control bg-light" id="changeOut">KES 0</div>
            </div>
          </div>
        </div>

        <hr>
        <p class="small text-muted mb-2">Customer (optional — needed to send a receipt)</p>
        <div class="mb-2"><input name="customer_name" class="form-control" placeholder="Customer name"></div>
        <div class="row g-2 mb-3">
          <div class="col-6"><input name="customer_phone" class="form-control" placeholder="Phone (2547…)"></div>
          <div class="col-6"><input name="customer_email" type="email" class="form-control" placeholder="Email"></div>
        </div>

        <button type="submit" class="btn btn-primary w-100 btn-lg" id="completeBtn" disabled>Complete sale</button>
      </div>
    </div>
  </div>

</div>
</form>

<style>
.pick-item:hover { background:#f0f7ff!important; border-color:#bfdbfe!important; }
.pick-item:active { background:#dbeafe!important; }
#search::placeholder { color:rgba(255,255,255,.45)!important; }
#search:focus { outline:none; box-shadow:none; background:rgba(255,255,255,.18)!important; border-color:rgba(255,255,255,.4)!important; }
</style>

<script>
var PRODUCTS = {};
var SERVICES = {};
var IDLE_LIMIT = 3;
var totalProducts = <?php echo count($products); ?>;
var totalServices = <?php echo count($services); ?>;
var activePane = <?php echo json_encode($defaultTab); ?>;

document.querySelectorAll('.prod').forEach(function (b) {
    PRODUCTS[b.dataset.id] = {
        id: b.dataset.id, name: b.dataset.name,
        price: parseFloat(b.dataset.price),
        stock: parseFloat(b.dataset.stock),
        unit:  b.dataset.unit
    };
});
document.querySelectorAll('.svc').forEach(function (b) {
    SERVICES[b.dataset.id] = {
        id: b.dataset.id, name: b.dataset.name,
        price: parseFloat(b.dataset.price)
    };
});

var cart = [];
var cartKey = 0;
try {
    (JSON.parse(<?php echo json_encode($cartJson); ?>) || []).forEach(function (c) {
        if (c.type === 'service' || c.service_id) {
            var sid = String(c.service_id || c.id);
            if (SERVICES[sid]) {
                cart.push({ key: ++cartKey, type: 'service', id: sid, name: SERVICES[sid].name, price: parseFloat(c.charged_amount) || SERVICES[sid].price, quantity: 1 });
            }
        } else {
            var pid = String(c.product_id || c.id);
            if (PRODUCTS[pid]) {
                cart.push({ key: ++cartKey, type: 'product', id: pid, name: PRODUCTS[pid].name, price: PRODUCTS[pid].price, quantity: parseFloat(c.quantity) || 1 });
            }
        }
    });
} catch (e) {}

var rows        = document.getElementById('cartRows');
var table       = document.getElementById('cartTable');
var empty       = document.getElementById('cartEmpty');
var totalOut    = document.getElementById('totalOut');
var changeOut   = document.getElementById('changeOut');
var amountGiven = document.getElementById('amountGiven');
var completeBtn = document.getElementById('completeBtn');
var cartInput   = document.getElementById('cartInput');
var searchInput = document.getElementById('search');
var searchHint  = document.getElementById('searchHint');

function money(n) { return n.toLocaleString('en-KE', {maximumFractionDigits:0}); }
function lineTotal(row) { return row.type === 'service' ? row.price : row.price * row.quantity; }
function total() { var t = 0; cart.forEach(function (r) { t += lineTotal(r); }); return t; }

function syncCartInput() {
    cartInput.value = JSON.stringify(cart.map(function (r) {
        if (r.type === 'service') {
            return { type: 'service', service_id: parseInt(r.id, 10), charged_amount: r.price };
        }
        return { type: 'product', product_id: parseInt(r.id, 10), quantity: r.quantity };
    }));
}

function render() {
    rows.innerHTML = '';
    table.style.display = cart.length ? 'table' : 'none';
    empty.style.display  = cart.length ? 'none'  : 'block';
    cart.forEach(function (row) {
        var tr = document.createElement('tr');
        if (row.type === 'service') {
            tr.innerHTML =
                '<td><div class="fw-semibold"><span class="badge bg-primary me-1">Service</span>'+row.name+'</div>'+
                '<small class="text-muted">KES '+money(row.price)+'</small></td>'+
                '<td class="text-end"><button type="button" class="btn btn-sm btn-link text-danger" data-del="'+row.key+'">remove</button></td>'+
                '<td class="text-end fw-semibold">KES '+money(row.price)+'</td>';
        } else {
            tr.innerHTML =
                '<td><div class="fw-semibold"><span class="badge bg-secondary me-1">Product</span>'+row.name+'</div>'+
                '<small class="text-muted">KES '+money(row.price)+' × '+row.quantity+'</small></td>'+
                '<td class="text-end" style="white-space:nowrap;">'+
                  '<button type="button" class="btn btn-sm btn-outline-secondary" data-dec="'+row.key+'">−</button>'+
                  '<span class="mx-2">'+row.quantity+'</span>'+
                  '<button type="button" class="btn btn-sm btn-outline-secondary" data-inc="'+row.key+'">+</button>'+
                  '<button type="button" class="btn btn-sm btn-link text-danger" data-del="'+row.key+'">remove</button>'+
                '</td>'+
                '<td class="text-end fw-semibold">KES '+money(lineTotal(row))+'</td>';
        }
        rows.appendChild(tr);
    });
    totalOut.textContent = money(total());
    completeBtn.disabled = cart.length === 0;
    updateChange();
    syncCartInput();
}

function addService(id) {
    var s = SERVICES[id];
    if (!s) return;
    cart.push({ key: ++cartKey, type: 'service', id: id, name: s.name, price: s.price, quantity: 1 });
    render();
}

function addProduct(id) {
    var p = PRODUCTS[id];
    if (!p) return;
    var existing = cart.find(function (r) { return r.type === 'product' && r.id === id; });
    if (existing) {
        if (existing.quantity + 1 > p.stock) {
            alert('Only '+p.stock+' '+p.unit+' of '+p.name+' in stock.');
            return;
        }
        existing.quantity++;
    } else {
        if (p.stock < 1) { alert('Out of stock.'); return; }
        cart.push({ key: ++cartKey, type: 'product', id: id, name: p.name, price: p.price, quantity: 1 });
    }
    render();
}

function decProduct(key) {
    var row = cart.find(function (r) { return r.key === key; });
    if (!row || row.type !== 'product') return;
    row.quantity--;
    if (row.quantity <= 0) cart = cart.filter(function (r) { return r.key !== key; });
    render();
}

function incProduct(key) {
    var row = cart.find(function (r) { return r.key === key; });
    if (!row || row.type !== 'product') return;
    var p = PRODUCTS[row.id];
    if (row.quantity + 1 > p.stock) { alert('Only '+p.stock+' '+p.unit+' in stock.'); return; }
    row.quantity++;
    render();
}

document.querySelectorAll('.svc').forEach(function (b) {
    b.addEventListener('click', function () { addService(b.dataset.id); });
});
document.querySelectorAll('.prod').forEach(function (b) {
    b.addEventListener('click', function () { addProduct(b.dataset.id); });
});
rows.addEventListener('click', function (e) {
    var t = e.target;
    if (t.dataset.inc) incProduct(parseInt(t.dataset.inc, 10));
    else if (t.dataset.dec) decProduct(parseInt(t.dataset.dec, 10));
    else if (t.dataset.del) { cart = cart.filter(function (r) { return r.key !== parseInt(t.dataset.del, 10); }); render(); }
});

// Tab switching
document.querySelectorAll('#saleTabs [data-pane]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        activePane = btn.getAttribute('data-pane');
        document.querySelectorAll('#saleTabs .btn').forEach(function (b) {
            b.classList.toggle('btn-light', b.getAttribute('data-pane') === activePane);
            b.classList.toggle('btn-outline-light', b.getAttribute('data-pane') !== activePane);
        });
        document.querySelectorAll('.picker-pane').forEach(function (p) { p.style.display = 'none'; });
        var pane = document.getElementById(activePane + 'Pane');
        if (pane) pane.style.display = '';
        document.getElementById('paneTitle').textContent = activePane === 'services' ? 'Services' : 'Products';
        searchInput.value = '';
        applyFilter();
    });
});

function applyFilter() {
    var q = searchInput.value.toLowerCase().trim();
    var selector = activePane === 'services' ? '.svc' : '.prod';
    var noMatch = document.getElementById(activePane === 'services' ? 'noServiceMatch' : 'noProductMatch');
    var allBtns = document.querySelectorAll(selector);
    var any = false, shown = 0, total = activePane === 'services' ? totalServices : totalProducts;

    allBtns.forEach(function (b) {
        var nameMatch = b.dataset.name.toLowerCase().indexOf(q) !== -1;
        var show = q === '' ? parseInt(b.dataset.idx, 10) < IDLE_LIMIT : nameMatch;
        b.style.display = show ? '' : 'none';
        if (show) { any = true; shown++; }
    });

    if (noMatch) noMatch.style.display = (q !== '' && !any) ? 'block' : 'none';

    if (searchHint) {
        if (q === '') {
            searchHint.textContent = 'Showing ' + Math.min(IDLE_LIMIT, total) + ' ' + activePane + ' — type to search all ' + total;
            searchHint.style.display = total > IDLE_LIMIT ? 'block' : 'none';
        } else {
            searchHint.textContent = shown + ' result' + (shown !== 1 ? 's' : '');
            searchHint.style.display = 'block';
        }
    }
}

searchInput.addEventListener('input', applyFilter);
applyFilter();

var cashBox = document.getElementById('cashBox');
function isCash(){ return document.getElementById('payCash').checked; }
function updateChange(){
    if (!isCash()){ changeOut.textContent='—'; return; }
    var given=parseFloat(amountGiven.value)||0, ch=given-total();
    changeOut.textContent = ch>=0 ? ('KES '+money(ch)) : 'short';
}
document.querySelectorAll('input[name=payment_method]').forEach(function(r){
    r.addEventListener('change', function(){ cashBox.style.display = isCash()?'block':'none'; updateChange(); });
});
amountGiven.addEventListener('input', updateChange);

document.getElementById('saleForm').addEventListener('submit', function(e){
    if (!cart.length){ e.preventDefault(); alert('Add at least one service or product.'); return; }
    if (isCash()){ var given=parseFloat(amountGiven.value)||0; if (given < total()){ e.preventDefault(); alert('Cash given is less than the total.'); } }
});

render();
</script>

<?php if ($hasProducts): include __DIR__ . '/../../components/tenants/share_modal.php'; endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/staff/layout.php';
