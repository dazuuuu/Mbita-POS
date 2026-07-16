<?php
// public/staff/sales/receipt.php?id=N  — view / print / send a receipt
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

$pdo = Database::pdo();
$SA  = new Models\SaleModel($pdo);

$id   = (int) ($_GET['id'] ?? 0);
$sale = $id > 0 ? $SA->find($id) : null;
if (!$sale) {
    http_response_code(404);
    echo 'Receipt not found.';
    exit;
}
$items = $SA->items($id);

$tenant = (new Models\TenantModel($pdo))->find(TenantContext::tenantId());
$shop   = $tenant['name'] ?? 'My Shop';
$branch = '';
if (!empty($sale['branch_id'])) {
    $b = $pdo->prepare('SELECT title FROM branches WHERE id = ? AND tenant_id = ?');
    $b->execute([$sale['branch_id'], TenantContext::tenantId()]);
    $branch = (string) ($b->fetchColumn() ?: '');
}
$st = $pdo->prepare('SELECT username FROM users WHERE id = ?');
$st->execute([$sale['staff_id']]);
$staff = (string) ($st->fetchColumn() ?: 'Staff');

$logoUrl = Branding::tenantLogo($tenant);
if (strpos($logoUrl, '/public/') === 0) {
    $logoUrl = '/Curlz' . $logoUrl;
}

function money($n, $currency = 'KES') { return ReceiptService::money((float)$n, $currency); }

$receiptHtml = ReceiptService::posReceiptHtml($sale, $items, $tenant, $branch, $staff, $logoUrl);

// --- email delivery ---
$flash = '';
$flashOk = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'email') {
    $to = trim($_POST['email'] ?? '');
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $flash = 'Enter a valid email address.';
    } else {
        $html = '<div style="background:#f8fafc;padding:20px;">' . $receiptHtml . '</div>';
        $sent = (new MailService())->send($to, 'Receipt ' . $sale['receipt_number'] . ' — ' . $shop, $html, 'Receipt ' . $sale['receipt_number'] . ' from ' . $shop);
        if ($sent) { $flash = 'Receipt sent to ' . $to . '.'; $flashOk = true; }
        else { $flash = 'Could not send the email. Check the mail settings and try again.'; }
    }
}

$waNum = '';
if (!empty($sale['customer_phone'])) {
    $d = preg_replace('/\D+/', '', $sale['customer_phone']);
    if ($d !== '') {
        if (strpos($d, '0') === 0) { $d = '254' . substr($d, 1); }
        elseif (strpos($d, '254') !== 0) { $d = '254' . $d; }
        $waNum = $d;
    }
}
$currency = $tenant['currency'] ?? 'KES';
$waText = rawurlencode("Receipt {$sale['receipt_number']} from {$shop}\nTotal: " . money($sale['total'], $currency));
$waLink = 'https://wa.me/' . $waNum . '?text=' . $waText;

$defaultEmail = htmlspecialchars($sale['customer_email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Receipt <?php echo htmlspecialchars($sale['receipt_number']); ?> — <?php echo htmlspecialchars($shop); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
  body{background:#f1f5f9;margin:0;padding:24px;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;}
  .sheet{background:#fff;max-width:420px;margin:0 auto 18px;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.1);padding:24px;}
  .actions{max-width:420px;margin:0 auto;}
  @media print { body{background:#fff;padding:0;} .actions,.noprint{display:none !important;} .sheet{box-shadow:none;border-radius:0;margin:0;} }
</style>
</head>
<body>
  <?php if ($flash): ?>
    <div class="actions"><div class="alert <?php echo $flashOk ? 'alert-success' : 'alert-danger'; ?> py-2"><?php echo htmlspecialchars($flash); ?></div></div>
  <?php endif; ?>

  <div class="sheet"><?php echo $receiptHtml; ?></div>

  <div class="actions noprint">
    <div class="d-flex gap-2 mb-2">
      <button onclick="window.print()" class="btn btn-primary flex-fill"><i class="fas fa-print me-1"></i> Print / Save PDF</button>
      <a href="<?php echo htmlspecialchars($waLink); ?>" target="_blank" rel="noopener" class="btn btn-success flex-fill"><i class="fab fa-whatsapp me-1"></i> WhatsApp</a>
    </div>
    <form method="post" class="card border-0 shadow-sm" style="border-radius:12px;">
      <div class="card-body p-3">
        <label class="form-label small mb-1">Email the receipt</label>
        <div class="input-group">
          <input type="email" name="email" class="form-control" placeholder="customer@email.com" value="<?php echo $defaultEmail; ?>" required>
          <input type="hidden" name="action" value="email">
          <button class="btn btn-outline-primary"><i class="fas fa-paper-plane me-1"></i> Send</button>
        </div>
      </div>
    </form>
    <div class="d-flex gap-2 mt-3">
      <a href="<?php echo public_path('staff/sales/new.php'); ?>" class="btn btn-link flex-fill">New sale</a>
      <a href="<?php echo public_path('staff/sales/'); ?>" class="btn btn-link flex-fill">My sales</a>
    </div>
  </div>
</body>
</html>
