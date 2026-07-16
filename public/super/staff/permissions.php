<?php
// Redirect legacy permissions URL to authorization page.
require_once __DIR__ . '/../../../app/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
$dest = public_path('super/staff/authorization.php');
if ($q !== '') {
    $dest .= (str_contains($dest, '?') ? '&' : '?') . $q;
}
header('Location: ' . $dest);
exit;
