<?php
// public/manifest.php — PWA manifest with paths from app/config/paths.php
require_once __DIR__ . '/../app/app.php';

header('Content-Type: application/manifest+json; charset=utf-8');

$start = public_path('');
$scope = AppUrl::publicScope();
$manifest = [
    'name'             => 'Curlz POS',
    'short_name'       => 'Curlz POS',
    'description'      => 'Point of sale — record sales, track stock, print receipts.',
    'id'               => $scope,
    'start_url'        => $start,
    'scope'            => $scope,
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#0f172a',
    'theme_color'      => '#0f172a',
    'icons'            => [
        ['src' => asset_path('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset_path('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
        ['src' => asset_path('icons/icon-512-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
