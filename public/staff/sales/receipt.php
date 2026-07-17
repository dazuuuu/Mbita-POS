<?php
// public/staff/sales/receipt.php — redirects to unified receipt viewer
require_once __DIR__ . '/../../../app/app.php';
$id = (int) ($_GET['id'] ?? 0);
header('Location: ' . ReceiptUrl::forPos($id));
exit;
