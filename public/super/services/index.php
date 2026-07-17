<?php
// public/super/services/index.php — tenant business services with expenses & commission
require_once __DIR__ . '/../../../app/app.php';
PageGuard::tenant();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$__locations = (new Models\BranchModel($pdo))->listWithCounts();
$modules = TenantModules::effectiveForTenant($__tenant, $__locations);
if (empty($modules[TenantModules::SERVICES])) {
    $_SESSION['flash']['error'] = 'Services are not enabled for any of your locations. Turn on Services in Settings → Modules.';
    header('Location: ' . public_path('super/settings/?tab=modules'));
    exit;
}

CommissionService::ensureSchema($pdo);
$svc = new OfferedServiceService($pdo);
$base = public_path('super/services/');

$editId = (int) ($_GET['edit'] ?? $_POST['id'] ?? 0);
$editRow = $editId ? $svc->find($tenantId, $editId) : null;
$errors = [];
$old = $editRow ?: ['name' => '', 'description' => '', 'charge_amount' => '', 'commission_type' => 'percent', 'commission_value' => '', 'expenses' => [['name' => '', 'cost' => '']]];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save';
    if ($action === 'delete') {
        if ($svc->delete($tenantId, (int) ($_POST['id'] ?? 0))) {
            $_SESSION['flash']['success'] = 'Service deleted.';
        } else {
            $_SESSION['flash']['error'] = 'Could not delete — this service has sales on record.';
        }
        header('Location: ' . $base); exit;
    }

    $expenses = [];
    foreach ($_POST['exp_name'] ?? [] as $i => $name) {
        $expenses[] = ['name' => $name, 'cost' => $_POST['exp_cost'][$i] ?? 0];
    }
    $in = [
        'name' => trim($_POST['name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'charge_amount' => $_POST['charge_amount'] ?? 0,
        'commission_type' => $_POST['commission_type'] ?? 'percent',
        'commission_value' => $_POST['commission_value'] ?? 0,
        'status' => $_POST['status'] ?? 'active',
        'expenses' => $expenses,
    ];
    $old = array_merge($old, $in);
    $old['expenses'] = $expenses;

    if ($editId) {
        $res = $svc->update($tenantId, $editId, $in);
    } else {
        $res = $svc->create($tenantId, $in);
    }
    if ($res['ok']) {
        $_SESSION['flash']['success'] = $editId ? 'Service updated.' : 'Service created.';
        header('Location: ' . $base); exit;
    }
    $errors = $res['errors'];
}

$services = $svc->listForTenant($tenantId);
$page_title = 'Services';
ob_start();
?>
<div class="row g-4">
  <div class="col-12 col-lg-5">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3"><?php echo $editId ? 'Edit service' : 'Add service'; ?></h2>
        <?php if (!empty($errors['_'])): ?><div class="alert alert-danger py-2"><?php echo htmlspecialchars($errors['_']); ?></div><?php endif; ?>
        <form method="post">
          <?php if ($editId): ?><input type="hidden" name="id" value="<?php echo $editId; ?>"><?php endif; ?>
          <div class="mb-3">
            <label class="form-label">Service name</label>
            <input name="name" class="form-control" required value="<?php echo htmlspecialchars($old['name']); ?>">
            <?php if (!empty($errors['name'])): ?><small class="text-danger"><?php echo htmlspecialchars($errors['name']); ?></small><?php endif; ?>
          </div>
          <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($old['description'] ?? ''); ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label">Standard charge (KES)</label>
            <input name="charge_amount" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($old['charge_amount']); ?>">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label">Commission type</label>
              <select name="commission_type" class="form-select">
                <option value="percent" <?php echo ($old['commission_type'] ?? '') === 'percent' ? 'selected' : ''; ?>>Percentage</option>
                <option value="fixed" <?php echo ($old['commission_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed amount</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label">Commission value</label>
              <input name="commission_value" type="number" step="0.01" min="0" class="form-control" value="<?php echo htmlspecialchars($old['commission_value']); ?>">
              <small class="text-muted">% of charged amount or fixed KES</small>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Incurred expenses during service</label>
            <div id="expense-rows">
              <?php foreach ($old['expenses'] as $i => $exp): ?>
              <div class="input-group mb-2 expense-row">
                <input name="exp_name[]" class="form-control" placeholder="Expense name" value="<?php echo htmlspecialchars($exp['name'] ?? ''); ?>">
                <input name="exp_cost[]" type="number" step="0.01" min="0" class="form-control" placeholder="Cost" value="<?php echo htmlspecialchars($exp['cost'] ?? ''); ?>">
                <button type="button" class="btn btn-outline-secondary btn-remove-exp" tabindex="-1">&times;</button>
              </div>
              <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="add-expense">+ Add expense</button>
          </div>
          <div class="d-flex gap-2">
            <button class="btn btn-primary"><?php echo $editId ? 'Save changes' : 'Create service'; ?></button>
            <?php if ($editId): ?><a class="btn btn-outline-secondary" href="<?php echo $base; ?>">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-12 col-lg-7">
    <div class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-4">
        <h2 class="h5 mb-3">Your services</h2>
        <?php if (!$services): ?>
          <p class="text-muted mb-0">No services yet. Define what you offer and how agents earn commission.</p>
        <?php else: ?>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead><tr class="text-muted small text-uppercase"><th>Service</th><th>Charge</th><th>Commission</th><th>Expenses</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($services as $s):
                $expSum = array_sum(array_column($s['expenses'], 'cost'));
                $commLabel = $s['commission_type'] === 'percent'
                    ? $s['commission_value'] . '%'
                    : 'KES ' . number_format((float)$s['commission_value'], 2);
              ?>
              <tr>
                <td>
                  <div class="fw-semibold"><?php echo htmlspecialchars($s['name']); ?></div>
                  <?php if ($s['description']): ?><small class="text-muted"><?php echo htmlspecialchars($s['description']); ?></small><?php endif; ?>
                </td>
                <td>KES <?php echo number_format((float)$s['charge_amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($commLabel); ?></td>
                <td><?php echo $expSum > 0 ? 'KES ' . number_format($expSum, 2) : '—'; ?></td>
                <td class="text-end text-nowrap">
                  <a class="btn btn-sm btn-outline-primary" href="?edit=<?php echo (int)$s['id']; ?>">Edit</a>
                  <form method="post" class="d-inline" onsubmit="return confirm('Delete this service?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                  </form>
                </td>
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
<script>
document.getElementById('add-expense').addEventListener('click', function () {
  var row = document.createElement('div');
  row.className = 'input-group mb-2 expense-row';
  row.innerHTML = '<input name="exp_name[]" class="form-control" placeholder="Expense name">' +
    '<input name="exp_cost[]" type="number" step="0.01" min="0" class="form-control" placeholder="Cost">' +
    '<button type="button" class="btn btn-outline-secondary btn-remove-exp" tabindex="-1">&times;</button>';
  document.getElementById('expense-rows').appendChild(row);
});
document.getElementById('expense-rows').addEventListener('click', function (e) {
  if (e.target.classList.contains('btn-remove-exp')) {
    var rows = document.querySelectorAll('.expense-row');
    if (rows.length > 1) e.target.closest('.expense-row').remove();
  }
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/tenants/layout.php';
