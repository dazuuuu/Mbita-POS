<?php
// Redirect legacy permissions URL to authorization page.
require_once __DIR__ . '/../../../app/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: ' . public_path('super/staff/authorization.php')None));
exit;
