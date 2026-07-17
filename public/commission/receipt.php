<?php
// public/commission/receipt.php — redirects to unified receipt viewer
require_once __DIR__ . '/../../app/app.php';
$id = (int) ($_GET['id'] ?? 0);
header('Location: ' . ReceiptUrl::forCommission($id));
exit;
