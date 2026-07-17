<?php
// public/receipt/view.php — receipt viewer with layout based on logged-in role
require_once __DIR__ . '/../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
GeneralMigrationService::ensureApplied($pdo);

$type = strtolower(trim($_GET['type'] ?? ''));
$id   = (int) ($_GET['id'] ?? 0);
$tenantId = (int) TenantContext::tenantId();
$role = TenantContext::role() ?? '';
$isOwner = $role === 'tenant_owner';

if ($id <= 0) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}

if ($type === '') {
    $probe = new CommissionService($pdo);
    $type = $probe->find($tenantId, $id) ? 'commission' : 'pos';
}

$tenant = (new Models\TenantModel($pdo))->find($tenantId);
$shop   = $tenant['name'] ?? 'My Shop';
$logoUrl = Branding::tenantLogo($tenant);
if (strpos($logoUrl, '/public/') === 0) {
    $logoUrl = '/Curlz' . $logoUrl;
}

$backUrl = $isOwner ? public_path('super/sales/') : public_path('staff/sales/');
$newUrl = $isOwner ? public_path('super/sales/') : public_path('staff/sales/new.php');

if ($type === 'commission') {
    $commSvc = new CommissionService($pdo);
    $sale = $commSvc->find($tenantId, $id);
    if (!$sale) {
        http_response_code(404);
        echo 'Receipt not found.';
        exit;
    }
    $uid = (int) TenantContext::userId();
    $allowed = $isOwner
        || (int) $sale['agent_user_id'] === $uid
        || (StaffRoles::isEmployeeRole($role) && TenantContext::can(Capabilities::COMMISSION_VIEW))
        || TenantContext::can(Capabilities::SALES_VIEW)
        || TenantContext::can(Capabilities::INVOICES_MANAGE)
        || TenantContext::can(Capabilities::PAYMENTS_RECEIVE);
    if (!$allowed) {
        header('Location: ' . public_path('auth/login.php?denied=1'));
        exit;
    }

    $expenses = $commSvc->expenses($tenantId, $id);
    $branch = '';
    if (!empty($sale['branch_id'])) {
        $b = $pdo->prepare('SELECT title FROM branches WHERE id = ? AND tenant_id = ?');
        $b->execute([$sale['branch_id'], $tenantId]);
        $branch = (string) ($b->fetchColumn() ?: '');
    }
    $st = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $st->execute([$sale['agent_user_id']]);
    $agent = (string) ($st->fetchColumn() ?: 'Staff');

    $receiptHtml = ReceiptService::commissionReceiptHtml($sale, $expenses, $tenant, $branch, $agent, $logoUrl);
    $receiptNumber = $sale['receipt_number'];
    $saleMeta = $sale;

    if (!$isOwner) {
        $backUrl = $role === 'sales_agent'
            ? public_path('sales-agent/sales/')
            : (TenantContext::can(Capabilities::PAYMENTS_RECEIVE)
                ? public_path('staff/payments/')
                : public_path('staff/commissions/'));
        $newUrl = $role === 'sales_agent'
            ? public_path('sales-agent/sales/new.php')
            : public_path('staff/invoices/new.php');
    }
} else {
    $SA = new Models\SaleModel($pdo);
    $sale = $SA->find($id);
    if (!$sale) {
        http_response_code(404);
        echo 'Receipt not found.';
        exit;
    }
    $items = $SA->items($id);
    $branch = '';
    if (!empty($sale['branch_id'])) {
        $b = $pdo->prepare('SELECT title FROM branches WHERE id = ? AND tenant_id = ?');
        $b->execute([$sale['branch_id'], $tenantId]);
        $branch = (string) ($b->fetchColumn() ?: '');
    }
    $st = $pdo->prepare('SELECT username FROM users WHERE id = ?');
    $st->execute([$sale['staff_id']]);
    $staff = (string) ($st->fetchColumn() ?: 'Staff');

    $receiptHtml = ReceiptService::posReceiptHtml($sale, $items, $tenant, $branch, $staff, $logoUrl);
    $receiptNumber = $sale['receipt_number'];
    $saleMeta = $sale;
}

$flash = '';
$flashOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    $to = trim($_POST['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $flash = 'Enter a valid email address.';
    } else {
        $html = '<div style="background:#f8fafc;padding:20px;">' . $receiptHtml . '</div>';
        $sent = (new MailService())->send($to, 'Receipt ' . $receiptNumber . ' — ' . $shop, $html, 'Receipt ' . $receiptNumber . ' from ' . $shop);
        $flash = $sent ? 'Receipt sent to ' . $to . '.' : 'Could not send the email.';
        $flashOk = $sent;
    }
}

$waNum = '';
$customerPhone = $saleMeta['customer_phone'] ?? '';
if ($customerPhone !== '') {
    $d = preg_replace('/\D+/', '', $customerPhone);
    if ($d !== '') {
        if (strpos($d, '0') === 0) { $d = '254' . substr($d, 1); }
        elseif (strpos($d, '254') !== 0) { $d = '254' . $d; }
        $waNum = $d;
    }
}
$currency = $tenant['currency'] ?? 'KES';
$totalAmount = $type === 'commission'
    ? (float) ($saleMeta['charged_amount'] ?? 0)
    : (float) ($saleMeta['total'] ?? 0);
$waText = rawurlencode("Receipt {$receiptNumber} from {$shop}\nTotal: {$currency} " . number_format($totalAmount, 2));
$waLink = $waNum ? 'https://wa.me/' . $waNum . '?text=' . $waText : 'https://wa.me/?text=' . $waText;
$defaultEmail = htmlspecialchars($saleMeta['customer_email'] ?? '');
$pending = ($saleMeta['payment_status'] ?? 'paid') === 'pending';

$page_title = 'Receipt ' . $receiptNumber;
$extra_css = <<<'CSS'
<style>
  .rc-wrap { max-width: 480px; margin: 0 auto; }
  .rc-sheet { background:#fff; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,.08); padding:24px; margin-bottom:18px; }
  .rc-pending { border:2px dashed #f59e0b; }
  @media print { .rc-actions, .t-sidebar, .t-sidebar-toggle, .cd-sidebar, .cd-header, .noprint { display:none !important; } .rc-sheet { box-shadow:none; } body { background:#fff; } }
</style>
CSS;

ob_start();
?>
<div class="rc-wrap">
  <?php if ($flash): ?>
    <div class="alert <?php echo $flashOk ? 'alert-success' : 'alert-danger'; ?> py-2 mb-3"><?php echo htmlspecialchars($flash); ?></div>
  <?php endif; ?>
  <?php if ($pending): ?>
    <div class="alert alert-warning py-2 mb-3"><i class="fas fa-clock me-1"></i> Awaiting payment at till</div>
  <?php endif; ?>
  <div class="rc-sheet <?php echo $pending ? 'rc-pending' : ''; ?>"><?php echo $receiptHtml; ?></div>

  <div class="rc-actions noprint">
    <div class="d-flex gap-2 mb-2 flex-wrap">
      <button type="button" onclick="window.print()" class="btn btn-primary flex-fill"><i class="fas fa-print me-1"></i> Print / PDF</button>
      <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" rel="noopener" class="btn btn-success flex-fill"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
    </div>
    <form method="post" class="card border-0 shadow-sm mb-3" style="border-radius:12px;">
      <div class="card-body p-3">
        <label class="form-label small mb-1">Email receipt</label>
        <div class="input-group">
          <input type="email" name="email" class="form-control" placeholder="customer@email.com" value="<?php echo $defaultEmail; ?>" required>
          <input type="hidden" name="action" value="email">
          <button class="btn btn-outline-primary"><i class="fas fa-paper-plane"></i></button>
        </div>
      </div>
    </form>
    <div class="d-flex gap-2">
      <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-link flex-fill">&larr; Back to sales</a>
      <?php if (!$isOwner && !$pending): ?>
      <a href="<?php echo htmlspecialchars($newUrl); ?>" class="btn btn-link flex-fill">New sale</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
$content = ob_get_clean();

if ($isOwner) {
    include __DIR__ . '/../templates/tenants/layout.php';
} else {
    include __DIR__ . '/../templates/staff/layout.php';
}
