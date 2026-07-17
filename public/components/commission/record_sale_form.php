<?php
// public/components/commission/record_sale_form.php
// Expects: $services, $products, $servicesJson, $productsJson, $errors, $preview, $creditsEnabled
// Optional: $prefillServiceId, $prefillProductId, $backUrl
$prefillServiceId = (int) ($prefillServiceId ?? 0);
$prefillProductId = (int) ($prefillProductId ?? 0);
$backUrl = $backUrl ?? '';
?>
<div class="row g-4">
  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-1">What are you selling?</h2>
        <p class="text-muted small mb-3">Tap services or products to add them to the sale. You can mix both in one visit — e.g. a haircut plus hair product.</p>
        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>

        <ul class="nav nav-pills mb-3" id="saleTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-services" data-bs-toggle="pill" data-bs-target="#pane-services" type="button" role="tab">
              <i class="fas fa-scissors me-1"></i> Services <?php if ($services): ?><span class="badge bg-primary ms-1"><?php echo count($services); ?></span><?php endif; ?>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-products" data-bs-toggle="pill" data-bs-target="#pane-products" type="button" role="tab">
              <i class="fas fa-box me-1"></i> Products <?php if ($products): ?><span class="badge bg-secondary ms-1"><?php echo count($products); ?></span><?php endif; ?>
            </button>
          </li>
        </ul>

        <div class="tab-content">
          <div class="tab-pane fade show active" id="pane-services" role="tabpanel">
            <?php if (!$services): ?>
              <div class="alert alert-warning mb-0">
                <strong>No services yet.</strong> Ask your manager to create services under
                <em>Super → Services</em> (e.g. Haircut, Shave, Styling).
              </div>
            <?php else: ?>
              <div class="row g-2" id="serviceGrid">
                <?php foreach ($services as $s):
                  $commLabel = $s['commission_type'] === 'percent'
                      ? $s['commission_value'] . '% commission'
                      : 'KES ' . number_format((float)$s['commission_value'], 0) . ' commission';
                ?>
                <div class="col-6 col-md-4">
                  <button type="button" class="btn btn-light w-100 text-start p-3 h-100 border sale-pick"
                          data-type="service" data-id="<?php echo (int)$s['id']; ?>"
                          style="border-radius:10px;">
                    <div class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></div>
                    <div class="text-success small fw-semibold mt-1">KES <?php echo number_format((float)$s['charge_amount'], 2); ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($commLabel); ?></div>
                  </button>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <div class="tab-pane fade" id="pane-products" role="tabpanel">
            <?php if (!$products): ?>
              <div class="alert alert-warning mb-0">No products in stock. Ask your manager to add products.</div>
            <?php else: ?>
              <div class="row g-2" id="productGrid">
                <?php foreach ($products as $p):
                  $commLabel = ($p['commission_type'] ?? 'percent') === 'percent'
                      ? ($p['commission_value'] ?? 0) . '% commission'
                      : 'KES ' . number_format((float)($p['commission_value'] ?? 0), 0) . ' commission';
                ?>
                <div class="col-6 col-md-4">
                  <button type="button" class="btn btn-light w-100 text-start p-3 h-100 border sale-pick"
                          data-type="product" data-id="<?php echo (int)$p['id']; ?>"
                          style="border-radius:10px;">
                    <div class="fw-semibold"><?php echo htmlspecialchars($p['name']); ?></div>
                    <div class="text-success small fw-semibold mt-1">KES <?php echo number_format((float)$p['selling_price'], 2); ?></div>
                    <div class="text-muted" style="font-size:.72rem;"><?php echo htmlspecialchars($commLabel); ?></div>
                  </button>
                </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12 col-lg-5">
    <form method="post" id="saleForm">
      <input type="hidden" name="cart_json" id="cartJson" value="">
      <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
        <div class="card-body p-4">
          <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h6 mb-0">This sale</h2>
            <span class="badge bg-light text-dark" id="cartCount">0 items</span>
          </div>
          <div id="cartEmpty" class="text-muted small py-3 text-center border rounded bg-light">
            Tap a service or product to start.
          </div>
          <div id="cartItems" class="d-none"></div>
          <?php if (!empty($errors['charged_amount'])): ?><small class="text-danger d-block mt-2"><?php echo htmlspecialchars($errors['charged_amount']); ?></small><?php endif; ?>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
        <div class="card-body p-4">
          <div class="mb-3">
            <label class="form-label">Payment</label>
            <select name="payment_method" class="form-select">
              <option value="cash" <?php echo ($_POST['payment_method'] ?? 'cash') === 'cash' ? 'selected' : ''; ?>>Cash</option>
              <option value="mpesa" <?php echo ($_POST['payment_method'] ?? '') === 'mpesa' ? 'selected' : ''; ?>>M-Pesa</option>
              <?php if (!empty($creditsEnabled)): ?>
              <option value="credit" <?php echo ($_POST['payment_method'] ?? '') === 'credit' ? 'selected' : ''; ?>>Credit</option>
              <?php endif; ?>
            </select>
          </div>
          <div class="mb-3 border rounded p-3 bg-light">
            <label class="form-label mb-2">Customer</label>
            <input name="customer_name" class="form-control mb-2" placeholder="Name" value="<?php echo htmlspecialchars($_POST['customer_name'] ?? ''); ?>">
            <?php if (!empty($errors['customer_name'])): ?><small class="text-danger d-block mb-1"><?php echo htmlspecialchars($errors['customer_name']); ?></small><?php endif; ?>
            <input name="customer_phone" class="form-control" placeholder="Phone (optional)" value="<?php echo htmlspecialchars($_POST['customer_phone'] ?? ''); ?>">
          </div>
          <div class="mb-0">
            <label class="form-label">Notes <span class="text-muted">(optional)</span></label>
            <textarea name="notes" class="form-control" rows="2"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
          </div>
        </div>
      </div>

      <div class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
        <div class="card-body p-4">
          <h2 class="h6 mb-3">Commission breakdown</h2>
          <?php if ($preview): ?>
          <dl class="mb-0">
            <?php if (!empty($preview['lines'])): foreach ($preview['lines'] as $line): ?>
            <dt class="text-muted small"><?php echo htmlspecialchars($line['name']); ?></dt>
            <dd class="mb-2">KES <?php echo number_format($line['commission'], 2); ?></dd>
            <?php endforeach; endif; ?>
            <dt class="text-muted small fw-bold">Total commission</dt>
            <dd class="h4 text-success mb-0">KES <?php echo number_format($preview['total_commission'], 2); ?></dd>
          </dl>
          <?php else: ?>
          <p class="text-muted small mb-0" id="commissionHint">Add items to see your estimated commission.</p>
          <div id="liveCommission" class="d-none">
            <div class="h4 text-success mb-0">KES <span id="liveCommissionAmt">0.00</span></div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="d-flex gap-2">
        <button type="submit" name="step" value="preview" class="btn btn-outline-secondary flex-fill">Preview</button>
        <button type="submit" name="step" value="save" class="btn btn-primary flex-fill" id="saveBtn" disabled>Save sale</button>
      </div>
      <?php if ($backUrl): ?><a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-link w-100 mt-2">Back to dashboard</a><?php endif; ?>
    </form>
  </div>
</div>
<script>
var SERVICES = <?php echo $servicesJson; ?>;
var PRODUCTS = <?php echo $productsJson; ?>;
var PREFILL_SERVICE = <?php echo $prefillServiceId; ?>;
var PREFILL_PRODUCT = <?php echo $prefillProductId; ?>;
var cart = [];
var cartKey = 0;

function findItem(type, id) {
  var list = type === 'service' ? SERVICES : PRODUCTS;
  return list.find(function (x) { return x.id === id; }) || null;
}

function commissionFor(type, item, charged) {
  var std = item.price;
  var base = item.commission_type === 'percent'
    ? charged * (item.commission_value / 100)
    : item.commission_value;
  var overage = Math.max(0, charged - std);
  return Math.round((base + overage) * 100) / 100;
}

function addToCart(type, id) {
  var item = findItem(type, id);
  if (!item) return;
  var row = {
    key: ++cartKey,
    type: type,
    id: id,
    name: item.name,
    price: item.price,
    charged: item.price,
    quantity: 1,
    expenses: []
  };
  if (type === 'service' && item.expenses && item.expenses.length) {
    row.expenses = item.expenses.map(function (e) {
      return { id: e.id, name: e.name, cost: e.cost, selected: true };
    });
  }
  cart.push(row);
  renderCart();
}

function removeFromCart(key) {
  cart = cart.filter(function (r) { return r.key !== key; });
  renderCart();
}

function renderCart() {
  var empty = document.getElementById('cartEmpty');
  var wrap = document.getElementById('cartItems');
  var count = document.getElementById('cartCount');
  var saveBtn = document.getElementById('saveBtn');
  var live = document.getElementById('liveCommission');
  var liveAmt = document.getElementById('liveCommissionAmt');
  var hint = document.getElementById('commissionHint');

  count.textContent = cart.length + ' item' + (cart.length === 1 ? '' : 's');
  saveBtn.disabled = cart.length === 0;

  if (!cart.length) {
    empty.classList.remove('d-none');
    wrap.classList.add('d-none');
    wrap.innerHTML = '';
    live.classList.add('d-none');
    hint.classList.remove('d-none');
    document.getElementById('cartJson').value = '[]';
    return;
  }

  empty.classList.add('d-none');
  wrap.classList.remove('d-none');
  var totalComm = 0;
  wrap.innerHTML = cart.map(function (row) {
    var comm = commissionFor(row.type, findItem(row.type, row.id), parseFloat(row.charged) || 0);
    totalComm += comm;
    var qtyField = row.type === 'product'
      ? '<div class="mt-2"><label class="form-label small mb-0">Qty</label><input type="number" step="0.01" min="0.01" class="form-control form-control-sm qty-input" data-key="' + row.key + '" value="' + row.quantity + '"></div>'
      : '';
    var expHtml = '';
    if (row.type === 'service' && row.expenses.length) {
      expHtml = '<div class="mt-2 small">' + row.expenses.map(function (e) {
        return '<div class="form-check"><input class="form-check-input exp-input" type="checkbox" data-key="' + row.key + '" data-exp="' + e.id + '" ' + (e.selected ? 'checked' : '') + '><label class="form-check-label">' + e.name + ' (KES ' + e.cost.toFixed(2) + ')</label></div>';
      }).join('') + '</div>';
    }
    return '<div class="border rounded p-3 mb-2 bg-white" data-key="' + row.key + '">' +
      '<div class="d-flex justify-content-between align-items-start gap-2">' +
        '<div><span class="badge ' + (row.type === 'service' ? 'bg-primary' : 'bg-secondary') + ' me-1">' + (row.type === 'service' ? 'Service' : 'Product') + '</span>' +
        '<span class="fw-semibold">' + row.name + '</span></div>' +
        '<button type="button" class="btn btn-sm btn-outline-danger btn-remove" data-key="' + row.key + '">&times;</button>' +
      '</div>' +
      '<div class="mt-2"><label class="form-label small mb-0">Amount charged (KES)</label>' +
      '<input type="number" step="0.01" min="0" class="form-control form-control-sm charged-input" data-key="' + row.key + '" value="' + row.charged + '"></div>' +
      qtyField + expHtml +
      '<div class="text-success small mt-2">Est. commission: KES ' + comm.toFixed(2) + '</div>' +
    '</div>';
  }).join('');

  live.classList.remove('d-none');
  hint.classList.add('d-none');
  liveAmt.textContent = totalComm.toFixed(2);
  syncCartJson();
}

function syncCartJson() {
  var payload = cart.map(function (row) {
    var out = {
      type: row.type,
      id: row.id,
      name: row.name,
      charged: parseFloat(row.charged) || 0,
      quantity: parseFloat(row.quantity) || 1
    };
    if (row.type === 'service' && row.expenses.length) {
      out.expenses = row.expenses.filter(function (e) { return e.selected; }).map(function (e) {
        return { name: e.name, cost: e.cost };
      });
    }
    return out;
  });
  document.getElementById('cartJson').value = JSON.stringify(payload);
}

document.querySelectorAll('.sale-pick').forEach(function (btn) {
  btn.addEventListener('click', function () {
    addToCart(btn.getAttribute('data-type'), parseInt(btn.getAttribute('data-id'), 10));
  });
});

document.getElementById('cartItems').addEventListener('click', function (e) {
  if (e.target.classList.contains('btn-remove')) {
    removeFromCart(parseInt(e.target.getAttribute('data-key'), 10));
  }
});

document.getElementById('cartItems').addEventListener('input', function (e) {
  var key = parseInt(e.target.getAttribute('data-key'), 10);
  var row = cart.find(function (r) { return r.key === key; });
  if (!row) return;
  if (e.target.classList.contains('charged-input')) {
    row.charged = e.target.value;
  }
  if (e.target.classList.contains('qty-input')) {
    row.quantity = e.target.value;
  }
  renderCart();
});

document.getElementById('cartItems').addEventListener('change', function (e) {
  if (!e.target.classList.contains('exp-input')) return;
  var key = parseInt(e.target.getAttribute('data-key'), 10);
  var expId = parseInt(e.target.getAttribute('data-exp'), 10);
  var row = cart.find(function (r) { return r.key === key; });
  if (!row) return;
  var exp = row.expenses.find(function (x) { return x.id === expId; });
  if (exp) exp.selected = e.target.checked;
  syncCartJson();
});

document.getElementById('saleForm').addEventListener('submit', function () {
  syncCartJson();
});

if (PREFILL_SERVICE) addToCart('service', PREFILL_SERVICE);
else if (PREFILL_PRODUCT) addToCart('product', PREFILL_PRODUCT);
else renderCart();
</script>
