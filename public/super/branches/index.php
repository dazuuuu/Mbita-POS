<?php
// public/super/branches/index.php — moved into Settings
require_once __DIR__ . '/../../../app/app.php';
header('Location: ' . public_path('super/settings/?tab=locations'));
exit;
