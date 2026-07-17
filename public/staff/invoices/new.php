<?php
// public/staff/invoices/new.php — redirect to official check-in
require_once __DIR__ . '/../../../app/app.php';
header('Location: ' . public_path('staff/checkin/'));
exit;
