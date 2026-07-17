<?php
// public/staff/invoices/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::capability(Capabilities::INVOICES_MANAGE);

header('Location: ' . public_path('staff/invoices/new.php'));
exit;
