<?php
// app/config/paths.php
// ═══════════════════════════════════════════════════════════════════════════════
// EDIT THIS FILE ONCE when you deploy — every page reads paths from here.
//
// base_path = folder this project lives under on your web server (leading slash).
//
// Examples:
//   Local XAMPP/AMPPS:  '/Curlz'   or  '/Mbita'   or  '/pos'
//   Domain root:        ''           (empty string — project at http://example.com/)
//   Subfolder:          '/my-shop'
//
// public_segment = URL folder that maps to the /public directory.
//   Keep 'public' for normal installs (URLs like /Curlz/public/auth/login.php).
//   Set to '' ONLY if your web server's document root already points at /public
//   (URLs like /auth/login.php with no /public/ in the path).
// ═══════════════════════════════════════════════════════════════════════════════

return [
    'base_path'       => '/Mbita',
    'public_segment'  => 'public',
];
