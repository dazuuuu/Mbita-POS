<?php
// public/staff/invoices/index.php
require_once __DIR__ . '/../../../app/app.php';
PageGuard::auth();

if (!TenantContext::can(Capabilities::INVOICES_MANAGE)) {
    header('Location: ' . public_path('auth/login.php?denied=1'));
    exit;
}

header('Location: ' . public_path('staff/invoices/new.php'));
exit;
