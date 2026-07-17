<?php
// public/sw.php — service worker with BASE from app/config/paths.php
require_once __DIR__ . '/../app/app.php';

header('Content-Type: application/javascript; charset=utf-8');
header('Service-Worker-Allowed: /');

$base = rtrim(public_path(''), '/');
$scope = AppUrl::publicScope();
?>
/* POS service worker — paths from app/config/paths.php */
const CACHE = 'curlz-pos-v2';
const BASE  = <?php echo json_encode($base); ?>;
const SCOPE = <?php echo json_encode($scope); ?>;
const SHELL = [
  BASE + '/',
  <?php echo json_encode(public_path('manifest.php')); ?>,
  <?php echo json_encode(asset_path('icons/icon-192.png')); ?>,
  <?php echo json_encode(asset_path('icons/icon-512.png')); ?>
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE).then((c) => c.addAll(SHELL)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) return;

  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(BASE + '/')));
    return;
  }

  if (/\.(png|jpe?g|svg|gif|webp|ico|css|js|woff2?|ttf)$/i.test(url.pathname)) {
    event.respondWith(
      caches.match(req).then((hit) => {
        if (hit) return hit;
        return fetch(req).then((res) => {
          const copy = res.clone();
          caches.open(CACHE).then((c) => c.put(req, copy));
          return res;
        });
      })
    );
    return;
  }

  event.respondWith(fetch(req).catch(() => caches.match(req)));
});
