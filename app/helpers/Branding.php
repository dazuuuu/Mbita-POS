<?php
// app/helpers/Branding.php
// Tenant logo + business name for menus, auth, receipts, and PWA.

class Branding
{
    const DEFAULT_LOGO = '/public/assets/images/logo/logo.png';

    private static ?array $portalCache = null;

    public static function resetCache(): void
    {
        self::$portalCache = null;
    }

    public static function appName(): string
    {
        $file = ROOT_PATH . '/app/config/app.php';
        if (is_file($file)) {
            $cfg = require $file;
            $name = trim((string) ($cfg['app_name'] ?? ''));
            if ($name !== '') {
                return $name;
            }
        }
        return 'POS';
    }

    /** Stored path in DB (may be /public/uploads/... or uploads/...). */
    public static function tenantLogo(?array $tenant): ?string
    {
        if ($tenant && !empty($tenant['logo_path'])) {
            return trim((string) $tenant['logo_path']);
        }
        return null;
    }

    /** Relative path under public/ when the file exists on disk. */
    public static function publicRelativePath(?string $storedPath): ?string
    {
        if ($storedPath === null || $storedPath === '') {
            return null;
        }

        $candidates = [];
        $storedPath = str_replace('\\', '/', $storedPath);

        if (str_starts_with($storedPath, '/public/')) {
            $candidates[] = ltrim(substr($storedPath, 8), '/');
        }
        $candidates[] = ltrim($storedPath, '/');
        if (str_starts_with($storedPath, 'public/')) {
            $candidates[] = ltrim(substr($storedPath, 7), '/');
        }

        foreach (array_unique($candidates) as $rel) {
            if ($rel === '') {
                continue;
            }
            $disk = ROOT_PATH . '/public/' . $rel;
            if (is_file($disk)) {
                return $rel;
            }
        }

        return null;
    }

    public static function hasCustomLogo(?array $tenant): bool
    {
        return self::publicRelativePath(self::tenantLogo($tenant)) !== null;
    }

    /** Web URL for img src. Returns null when no custom logo uploaded (never show platform default on tenant UI). */
    public static function tenantLogoUrl(?array $tenant, bool $absolute = false): ?string
    {
        $rel = self::publicRelativePath(self::tenantLogo($tenant));
        if ($rel === null) {
            return null;
        }

        $url = AppUrl::public($rel);
        if ($absolute) {
            return AppUrl::url($rel);
        }

        $disk = ROOT_PATH . '/public/' . $rel;
        if (is_file($disk)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . filemtime($disk);
        }

        return $url;
    }

    public static function shopName(?array $tenant): string
    {
        $name = trim((string) ($tenant['name'] ?? ''));
        return $name !== '' ? $name : 'My Shop';
    }

    /** Active tenant for this install (logged-in tenant, else first active row). */
    public static function activeTenant(?PDO $db = null): ?array
    {
        try {
            $db = $db ?? Database::pdo();
            if (!SchemaHelper::tableExists($db, 'tenants')) {
                return null;
            }

            $tenantId = TenantContext::tenantId();
            if ($tenantId) {
                $stmt = $db->prepare('SELECT * FROM tenants WHERE id = ? AND status = ? LIMIT 1');
                $stmt->execute([(int) $tenantId, 'active']);
                $row = $stmt->fetch();
                if ($row) {
                    return $row;
                }
            }

            $row = $db->query("SELECT * FROM tenants WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Branding for login portal / auth screens.
     *
     * @return array{name:string,logo_url:?string,tenant:?array,has_logo:bool}
     */
    public static function portalBranding(?PDO $db = null): array
    {
        if (self::$portalCache !== null) {
            return self::$portalCache;
        }

        $tenant = self::activeTenant($db);
        if ($tenant) {
            self::$portalCache = [
                'name'     => self::shopName($tenant),
                'logo_url' => self::tenantLogoUrl($tenant),
                'tenant'   => $tenant,
                'has_logo' => self::hasCustomLogo($tenant),
            ];
            return self::$portalCache;
        }

        self::$portalCache = [
            'name'     => self::appName(),
            'logo_url' => null,
            'tenant'   => null,
            'has_logo' => false,
        ];
        return self::$portalCache;
    }

    /** Ensure layout/sidebar always has tenant row when possible. */
    public static function tenantOrPortal(?array $tenant, ?PDO $db = null): ?array
    {
        if ($tenant && !empty($tenant['id'])) {
            return $tenant;
        }
        return self::portalBranding($db)['tenant'];
    }
}
