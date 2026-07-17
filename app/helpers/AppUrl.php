<?php
// app/helpers/AppUrl.php
// Central path builder — reads app/config/paths.php (edit that file when hosting).

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__, 2));
}

class AppUrl
{
    private static ?array $paths = null;

    public static function pathsConfig(): array
    {
        if (self::$paths === null) {
            $file = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/app/config/paths.php';
            self::$paths = is_file($file) ? (require $file) : ['base_path' => '', 'public_segment' => 'public'];

            // php -S localhost:8000 -t public — docroot is already /public, no URL prefix needed
            if (php_sapi_name() === 'cli-server') {
                self::$paths['base_path'] = '';
                self::$paths['public_segment'] = '';
            }
        }
        return self::$paths;
    }

    /** Project folder under the web server, e.g. /Curlz or empty string. */
    public static function basePath(): string
    {
        $p = (string) (self::pathsConfig()['base_path'] ?? '');
        $p = rtrim($p, '/');
        return $p === '' ? '' : $p;
    }

    /** Web path to a file under /public (or docroot if public_segment is empty). */
    public static function public(string $path = ''): string
    {
        $query = '';
        if (($qpos = strpos($path, '?')) !== false) {
            $query = substr($path, $qpos);
            $path = substr($path, 0, $qpos);
        }
        $path = ltrim($path, '/');
        $segments = [];
        if ($bp = self::basePath()) {
            $segments[] = ltrim($bp, '/');
        }
        $seg = trim((string) (self::pathsConfig()['public_segment'] ?? 'public'), '/');
        if ($seg !== '') {
            $segments[] = $seg;
        }
        if ($path !== '') {
            $segments[] = $path;
        }
        $out = '/' . implode('/', $segments);
        if ($path === '' || str_ends_with($path, '/')) {
            $out = rtrim($out, '/') . '/';
        }
        return $out . $query;
    }

    /** Path to a static asset inside /public/assets/ */
    public static function asset(string $path): string
    {
        return self::public('assets/' . ltrim($path, '/'));
    }

    /** Path to an uploaded file under /public/ (e.g. uploads/...). */
    public static function upload(string $path): string
    {
        return self::public(ltrim($path, '/'));
    }

    /** Full absolute URL (uses app_url from app/config/app.php when set). */
    public static function url(string $publicPath = ''): string
    {
        $rel = self::public($publicPath);
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // Built-in server: always use the request host (e.g. localhost:8000)
        if (php_sapi_name() === 'cli-server') {
            return $scheme . '://' . $host . $rel;
        }

        $appFile = (defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__, 2)) . '/app/config/app.php';
        if (is_file($appFile)) {
            $app = require $appFile;
            $origin = rtrim((string) ($app['app_url'] ?? ''), '/');
            if ($origin !== '' && !str_starts_with($rel, 'http')) {
                return $origin . $rel;
            }
        }

        return $scheme . '://' . $host . $rel;
    }

    /** Service worker scope (trailing slash). */
    public static function publicScope(): string
    {
        $p = self::public('');
        return rtrim($p, '/') . '/';
    }
}

/** Shorthand for templates and redirects — path under /public. */
function public_path(string $path = ''): string
{
    return AppUrl::public($path);
}

/** Shorthand for /public/assets/... */
function asset_path(string $path): string
{
    return AppUrl::asset($path);
}

/** Shorthand for full URL. */
function app_url(string $publicPath = ''): string
{
    return AppUrl::url($publicPath);
}
