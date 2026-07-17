<?php /* PWA head tags + service-worker registration. Include inside <head>. */ ?>
<link rel="manifest" href="/Curlz/public/manifest.webmanifest">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Curlz POS">
<link rel="apple-touch-icon" href="/Curlz/public/assets/icons/apple-touch-icon.png">
<link rel="icon" type="image/png" sizes="32x32" href="/Curlz/public/assets/icons/favicon-32.png">
<script>
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('/Curlz/public/sw.js', { scope: '/Curlz/public/' })
      .catch(function (e) { console.warn('SW registration failed', e); });
  });
}
</script>
