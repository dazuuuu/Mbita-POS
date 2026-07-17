<?php
// public/manifest.php — PWA manifest with paths from app/config/paths.php
require_once __DIR__ . '/../app/app.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$brand = Branding::portalBranding();
$start = public_path('');
$scope = AppUrl::publicScope();
$manifest = [
    'name'             => $brand['name'],
    'short_name'       => $brand['name'],
    'description'      => $brand['name'] . ' — point of sale',
    'id'               => $scope,
    'start_url'        => $start,
    'scope'            => $scope,
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#ffffff',
    'theme_color'      => '#111111',
    'icons'            => [
        ['src' => asset_path('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset_path('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset_path('icons/icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
