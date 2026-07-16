<?php
// Redirect legacy permissions URL to authorization page.
require_once __DIR__ . '/../../../app/app.php';
$q = $_SERVER['QUERY_STRING'] ?? '';
header('Location: /Curlz/public/super/staff/authorization.php' . ($q ? '?' . $q : ''));
exit;
