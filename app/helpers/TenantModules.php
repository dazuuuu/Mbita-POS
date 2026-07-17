<?php
// app/helpers/TenantModules.php
// Feature modules per branch/shop — owner configures in Settings.

class TenantModules
{
    public const SERVICES            = 'services';
    public const PRODUCTS            = 'products';
    public const WHOLESALE           = 'wholesale';
    public const PRODUCT_COMMISSIONS = 'product_commissions';
    public const SERVICE_COMMISSIONS = 'service_commissions';
    public const CASHIER             = 'cashier';
    public const RECEPTION           = 'reception';
    public const STAFF               = 'staff';
    public const SALES_AGENTS        = 'sales_agents';
    public const STAFF_PROMOTION     = 'staff_promotion';
    public const PAYMENT_PROCESSING  = 'payment_processing';
    public const APPOINTMENTS        = 'appointments';

    /** All module keys (for forms). */
    public static function keys(): array
    {
        return [
            self::SERVICES,
            self::PRODUCTS,
            self::WHOLESALE,
            self::PRODUCT_COMMISSIONS,
            self::SERVICE_COMMISSIONS,
            self::CASHIER,
            self::RECEPTION,
            self::STAFF,
            self::SALES_AGENTS,
            self::STAFF_PROMOTION,
            self::PAYMENT_PROCESSING,
            self::APPOINTMENTS,
        ];
    }

    /** Branch types (barbershop / salon — NOT shop). */
    public static function branchTypeLabels(): array
    {
        return [
            'barbershop'       => 'Barbershop',
            'salon'            => 'Salon',
            'barbershop_salon' => 'Barbershop & Salon',
        ];
    }

    public static function isBranchType(string $type): bool
    {
        return isset(self::branchTypeLabels()[$type]);
    }

    public static function isShopType(string $type): bool
    {
        return $type === 'shop';
    }

    /** Default modules when a new branch or shop is created. */
    public static function defaultsForLocationType(string $locationType): array
    {
        if ($locationType === 'shop') {
            return [
                self::SERVICES            => false,
                self::PRODUCTS            => true,
                self::WHOLESALE           => true,
                self::PRODUCT_COMMISSIONS => true,
                self::SERVICE_COMMISSIONS => false,
                self::CASHIER             => true,
                self::RECEPTION           => false,
                self::STAFF               => true,
                self::SALES_AGENTS        => true,
                self::STAFF_PROMOTION     => true,
                self::PAYMENT_PROCESSING  => true,
                self::APPOINTMENTS        => false,
            ];
        }

        return [
            self::SERVICES            => true,
            self::PRODUCTS            => true,
            self::WHOLESALE           => false,
            self::PRODUCT_COMMISSIONS => false,
            self::SERVICE_COMMISSIONS => true,
            self::CASHIER             => true,
            self::RECEPTION           => true,
            self::STAFF               => true,
            self::SALES_AGENTS        => false,
            self::STAFF_PROMOTION     => true,
            self::PAYMENT_PROCESSING  => true,
            self::APPOINTMENTS        => true,
        ];
    }

    /** @deprecated use defaultsForLocationType */
    public static function defaults(?string $businessType = 'shop'): array
    {
        return self::defaultsForLocationType(
            ($businessType ?? 'shop') === 'barbershop_salon' ? 'barbershop_salon' : 'shop'
        );
    }

    public static function labels(): array
    {
        return [
            self::SERVICES            => 'Services',
            self::PRODUCTS            => 'Products & inventory',
            self::WHOLESALE           => 'Wholesale pricing',
            self::PRODUCT_COMMISSIONS => 'Product commissions',
            self::SERVICE_COMMISSIONS => 'Service commissions',
            self::CASHIER             => 'Cashier',
            self::RECEPTION           => 'Reception',
            self::STAFF               => 'Staff management',
            self::SALES_AGENTS        => 'Sales agents',
            self::STAFF_PROMOTION     => 'Promote staff (junior admin)',
            self::PAYMENT_PROCESSING  => 'Payment processing (cashier/reception)',
            self::APPOINTMENTS        => 'Appointments booking',
        ];
    }

    public static function descriptions(): array
    {
        return [
            self::SERVICES            => 'Barbershop/salon services, service sales, and appointments.',
            self::PRODUCTS            => 'Product catalogue, stock, categories, and till sales.',
            self::WHOLESALE           => 'Retail and wholesale prices on each product (for shops).',
            self::PRODUCT_COMMISSIONS => 'Commission when staff sell products.',
            self::SERVICE_COMMISSIONS => 'Commission when staff perform services.',
            self::CASHIER             => 'Cashier role — payments and till.',
            self::RECEPTION           => 'Reception role — check-in, appointments, invoices.',
            self::STAFF               => 'Add staff and assign them to this location.',
            self::SALES_AGENTS        => 'Dedicated sales-agent accounts.',
            self::STAFF_PROMOTION     => 'Junior admin — delegate inventory, sales, reports.',
            self::PAYMENT_PROCESSING  => 'Only cashier/reception process payments by default. Delegate via staff permissions.',
            self::APPOINTMENTS        => 'Let staff book appointments. Super controls who can book via Staff → Authorization.',
        ];
    }

    public static function locationLabel(array $location): string
    {
        $type = $location['branch_type'] ?? 'shop';
        if ($type === 'shop') {
            return 'Shop';
        }
        return self::branchTypeLabels()[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }

    /** @return array<string,bool> */
    public static function fromBranch(?array $branch): array
    {
        $type = $branch['branch_type'] ?? 'shop';
        $defaults = self::defaultsForLocationType($type);

        if (!$branch || empty($branch['modules'])) {
            return $defaults;
        }

        $raw = $branch['modules'];
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
        foreach (self::keys() as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = (bool) $raw[$key];
            }
        }
        return $out;
    }

    /** Union of modules across all locations (drives sidebar). */
    public static function effectiveForTenant(?array $tenant, array $locations): array
    {
        if (!$locations) {
            return self::fromTenant($tenant);
        }

        $merged = array_fill_keys(self::keys(), false);
        foreach ($locations as $loc) {
            foreach (self::fromBranch($loc) as $key => $on) {
                if ($on) {
                    $merged[$key] = true;
                }
            }
        }
        return $merged;
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
        foreach (self::keys() as $key) {
            if (array_key_exists($key, $raw)) {
                $out[$key] = (bool) $raw[$key];
            }
        }
        return $out;
    }

    public static function enabled(?array $tenant, string $key): bool
    {
        return !empty(self::fromTenant($tenant)[$key]);
    }

    /** @param array<string,mixed> $posted */
    public static function sanitizePosted(array $posted): array
    {
        $out = [];
        foreach (self::keys() as $key) {
            $out[$key] = !empty($posted[$key]);
        }
        return $out;
    }
}
