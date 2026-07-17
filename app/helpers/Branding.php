<?php
// app/helpers/Branding.php
// Tenant logo + business name for menus, auth, receipts, and PWA.

class Branding
{
    const DEFAULT_LOGO = '/public/assets/images/logo/logo.png';

    private static ?array $portalCache = null;

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

    /** Stored path (DB value or default). */
    public static function tenantLogo(?array $tenant): string
    {
        if ($tenant && !empty($tenant['logo_path'])) {
            return (string) $tenant['logo_path'];
        }
        return self::DEFAULT_LOGO;
    }

    /** Web URL for img src — works on subfolder and built-in server. */
    public static function resolveLogoUrl(?string $path): string
    {
        if ($path === null || $path === '') {
            return asset_path('images/logo/logo.png');
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        if (str_starts_with($path, '/public/')) {
            return AppUrl::public(ltrim(substr($path, 8), '/'));
        }
        if (str_starts_with($path, '/assets/')) {
            return AppUrl::public(ltrim($path, '/'));
        }
        return asset_path(ltrim($path, '/'));
    }

    public static function tenantLogoUrl(?array $tenant): string
    {
        return self::resolveLogoUrl(self::tenantLogo($tenant));
    }

    public static function shopName(?array $tenant): string
    {
        $name = trim((string) ($tenant['name'] ?? ''));
        return $name !== '' ? $name : 'My Shop';
    }

    /**
     * Branding for login portal / auth screens.
     * Uses logged-in tenant, else the only active tenant, else app config.
     *
     * @return array{name:string,logo_url:string,tenant:?array}
     */
    public static function portalBranding(?PDO $db = null): array
    {
        if (self::$portalCache !== null) {
            return self::$portalCache;
        }

        $tenant = null;
        $tenantId = TenantContext::tenantId();
        if ($tenantId) {
            try {
                $db = $db ?? Database::pdo();
                $tenant = (new Models\TenantModel($db))->find((int) $tenantId);
            } catch (Throwable $e) {
                $tenant = null;
            }
        }

        if (!$tenant) {
            $tenant = self::singleActiveTenant($db);
        }

        if ($tenant) {
            self::$portalCache = [
                'name'     => self::shopName($tenant),
                'logo_url' => self::tenantLogoUrl($tenant),
                'tenant'   => $tenant,
            ];
            return self::$portalCache;
        }

        self::$portalCache = [
            'name'     => self::appName(),
            'logo_url' => self::resolveLogoUrl(self::DEFAULT_LOGO),
            'tenant'   => null,
        ];
        return self::$portalCache;
    }

    /** @deprecated Use tenantLogoUrl() on auth when tenant is known. */
    public static function loginLogo(): string
    {
        return self::DEFAULT_LOGO;
    }

    private static function singleActiveTenant(?PDO $db): ?array
    {
        try {
            $db = $db ?? Database::pdo();
            if (!SchemaHelper::tableExists($db, 'tenants')) {
                return null;
            }
            $rows = $db->query("SELECT * FROM tenants WHERE status = 'active' ORDER BY id ASC LIMIT 2")->fetchAll();
            if (count($rows) === 1) {
                return $rows[0];
            }
        } catch (Throwable $e) {
            return null;
        }
        return null;
    }
}
