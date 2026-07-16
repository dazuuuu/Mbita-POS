<?php
// app/helpers/TenantModules.php
// Which features a tenant has enabled — owner toggles these in Settings → Modules.

class TenantModules
{
    public const SERVICES            = 'services';
    public const PRODUCTS            = 'products';
    public const PRODUCT_COMMISSIONS = 'product_commissions';
    public const SERVICE_COMMISSIONS = 'service_commissions';
    public const CASHIER             = 'cashier';
    public const SALES_AGENTS        = 'sales_agents';
    public const STAFF_PROMOTION     = 'staff_promotion';

    /** @return array<string,bool> */
    public static function defaults(?string $businessType = 'shop'): array
    {
        $isSalon = $businessType === 'barbershop_salon';
        return [
            self::SERVICES            => $isSalon,
            self::PRODUCTS            => true,
            self::PRODUCT_COMMISSIONS => true,
            self::SERVICE_COMMISSIONS => $isSalon,
            self::CASHIER             => true,
            self::SALES_AGENTS        => true,
            self::STAFF_PROMOTION     => true,
        ];
    }

    /** Human labels for the settings UI. */
    public static function labels(): array
    {
        return [
            self::SERVICES            => 'Services (barbershop / salon)',
            self::PRODUCTS            => 'Products & inventory',
            self::PRODUCT_COMMISSIONS => 'Product commissions',
            self::SERVICE_COMMISSIONS => 'Service commissions',
            self::CASHIER             => 'Cashier role',
            self::SALES_AGENTS        => 'Sales agents',
            self::STAFF_PROMOTION     => 'Promote staff (junior admin)',
        ];
    }

    public static function descriptions(): array
    {
        return [
            self::SERVICES            => 'Manage services, record service sales, and service-based commission.',
            self::PRODUCTS            => 'Product catalogue, stock, categories, till sales, and credits.',
            self::PRODUCT_COMMISSIONS => 'Commission on product sales (per-product rates).',
            self::SERVICE_COMMISSIONS => 'Commission on service sales (per-service rates).',
            self::CASHIER             => 'Allow cashier staff role for payments and till.',
            self::SALES_AGENTS        => 'Dedicated sales-agent accounts with commission tracking.',
            self::STAFF_PROMOTION     => 'Junior admin role — delegate inventory, sales, and reports to staff.',
        ];
    }

    /** @return array<string,bool> */
    public static function fromTenant(?array $tenant): array
    {
        $businessType = $tenant['business_type'] ?? 'shop';
        $defaults = self::defaults($businessType);

        if (!$tenant || empty($tenant['modules'])) {
            return $defaults;
        }

        $raw = $tenant['modules'];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return $defaults;
            }
            $raw = $decoded;
        }
        if (!is_array($raw)) {
            return $defaults;
        }

        $out = $defaults;
        foreach (array_keys($defaults) as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = (bool) $raw[$key];
            }
        }
        return $out;
    }

    public static function enabled(?array $tenant, string $key): bool
    {
        $mods = self::fromTenant($tenant);
        return !empty($mods[$key]);
    }

    /** @param array<string,bool> $posted */
    public static function sanitizePosted(array $posted): array
    {
        $defaults = self::defaults();
        $out = [];
        foreach (array_keys($defaults) as $key) {
            $out[$key] = !empty($posted[$key]);
        }
        return $out;
    }

    public static function branchTypeLabels(): array
    {
        return [
            'shop'             => 'Retail shop',
            'barbershop'       => 'Barbershop',
            'salon'            => 'Salon',
            'barbershop_salon' => 'Barbershop & salon',
        ];
    }
}
