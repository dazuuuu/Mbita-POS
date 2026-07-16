<?php
// public/sales-agent/sales/new.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::salesAgent();

$pdo = Database::pdo();
$tenantId = (int) TenantContext::tenantId();
$userId = (int) TenantContext::userId();
$commSvc = new CommissionService($pdo);
$svcSvc = new OfferedServiceService($pdo);
$__tenant = (new Models\TenantModel($pdo))->find($tenantId);
$creditsEnabled = !empty($__tenant['credits_enabled']);

$stmt = $pdo->prepare('SELECT branch_id FROM users WHERE id = ?');
$stmt->execute([$userId]);
$branchId = (int) ($stmt->fetchColumn() ?: 0) ?: null;

$services = $svcSvc->activeForTenant($tenantId);
try {
    $stmt = $pdo->prepare("SELECT id, name, selling_price, commission_type, commission_value, credit_allowed FROM products WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenantId]);
    $products = $stmt->fetchAll();
} catch (Throwable $e) {
    $stmt = $pdo->prepare("SELECT id, name, selling_price, commission_type, commission_value FROM products WHERE tenant_id = ? AND status = 'active' ORDER BY name");
    $stmt->execute([$tenantId]);
    $products = $stmt->fetchAll();
}

$errors = [];
$preview = null;
$prefillServiceId = (int) ($_GET['service_id'] ?? 0);
$prefillProductId = (int) ($_GET['product_id'] ?? 0);
$backUrl = public_path('sales-agent/dashboard/');

$serviceIndex = [];
foreach ($services as $s) {
    $serviceIndex[(int) $s['id']] = $s;
}
$productIndex = [];
foreach ($products as $p) {
    $productIndex[(int) $p['id']] = $p;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cart = json_decode($_POST['cart_json'] ?? '[]', true);
    $items = CommissionService::parseCartItems($cart);
    $common = [
        'payment_method'  => $_POST['payment_method'] ?? 'cash',
        'customer_name'   => $_POST['customer_name'] ?? '',
        'customer_phone'  => $_POST['customer_phone'] ?? '',
        'branch_id'       => $branchId,
        'notes'           => $_POST['notes'] ?? '',
    ];

    if (($_POST['step'] ?? '') === 'preview') {
        $lines = [];
        $total = 0.0;
        foreach ($items as $item) {
            if ($item['item_type'] === 'service') {
                $src = $serviceIndex[(int) ($item['service_id'] ?? 0)] ?? null;
                if (!$src) {
                    continue;
                }
                $calc = CommissionService::calculateCommission(
                    $src['commission_type'],
                    (float) $src['commission_value'],
                    (float) $src['charge_amount'],
                    (float) $item['charged_amount']
                );
                $name = $src['name'];
            } else {
                $src = $productIndex[(int) ($item['product_id'] ?? 0)] ?? null;
                if (!$src) {
                    continue;
                }
                $calc = CommissionService::calculateCommission(
                    $src['commission_type'] ?? 'percent',
                    (float) ($src['commission_value'] ?? 0),
                    (float) $src['selling_price'],
                    (float) $item['charged_amount']
                );
                $name = $src['name'];
            }
            $lines[] = ['name' => $name, 'commission' => $calc['total_commission']];
            $total += $calc['total_commission'];
        }
        $preview = ['lines' => $lines, 'total_commission' => round($total, 2)];
    } elseif ($items) {
        $res = count($items) === 1
            ? $commSvc->recordSale($tenantId, $userId, array_merge($common, $items[0]))
            : $commSvc->recordSaleBatch($tenantId, $userId, $common, $items);
        if ($res['ok']) {
            header('Location: ' . public_path('commission/receipt.php')?id=);
            exit;
        }
        $errors = $res['errors'];
    } else {
        $errors = ['_' => 'Add at least one service or product to the sale.'];
    }
}

$servicesJson = json_encode(array_map(fn($s) => [
    'id' => (int)$s['id'], 'name' => $s['name'], 'price' => (float)$s['charge_amount'],
    'commission_type' => $s['commission_type'], 'commission_value' => (float)$s['commission_value'],
    'expenses' => array_map(fn($e) => ['id' => (int)$e['id'], 'name' => $e['name'], 'cost' => (float)$e['cost']], $s['expenses']),
], $services));
$productsJson = json_encode(array_map(fn($p) => [
    'id' => (int)$p['id'], 'name' => $p['name'], 'price' => (float)$p['selling_price'],
    'commission_type' => $p['commission_type'] ?? 'percent', 'commission_value' => (float)($p['commission_value'] ?? 0),
], $products));

$page_title = 'Record sale';
ob_start();
include __DIR__ . '/../../components/commission/record_sale_form.php';
$content = ob_get_clean();
include __DIR__ . '/../../templates/sales-agent/layout.php';
