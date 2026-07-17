<?php
// app/helpers/StaffNav.php — shared staff menu / workflow rules (sidebar, dashboard, pages).

class StaffNav
{
    /** Branch-scoped modules for the logged-in staff member. */
    public static function staffModules(?PDO $db = null, ?array $tenant = null, ?int $userId = null): array
    {
        $db = $db ?? Database::pdo();
        $tenantId = TenantContext::tenantId();
        if ($tenant === null && $tenantId) {
            $tenant = (new Models\TenantModel($db))->find($tenantId);
        }
        $modules = TenantModules::fromTenant($tenant);
        $uid = $userId ?? TenantContext::userId();
        if ($uid) {
            $stmt = $db->prepare('SELECT b.* FROM users u LEFT JOIN branches b ON b.id = u.branch_id WHERE u.id = ?');
            $stmt->execute([$uid]);
            $branch = $stmt->fetch();
            if ($branch && !empty($branch['id'])) {
                $modules = TenantModules::fromBranch($branch);
            }
        }
        return $modules;
    }

    public static function hasServices(array $modules): bool
    {
        return !empty($modules[TenantModules::SERVICES]);
    }

    public static function hasProducts(array $modules): bool
    {
        return !empty($modules[TenantModules::PRODUCTS]);
    }

    public static function hasAppointments(array $modules): bool
    {
        return !empty($modules[TenantModules::APPOINTMENTS]);
    }

    /** Official service check-in (reception / delegated service staff). */
    public static function canCheckIn(?array $modules = null): bool
    {
        $modules = $modules ?? self::staffModules();
        if (!self::hasServices($modules)) {
            return false;
        }
        return TenantContext::can(Capabilities::CUSTOMERS_CHECKIN)
            || TenantContext::can(Capabilities::INVOICES_MANAGE)
            || TenantContext::can(Capabilities::COMMISSION_RECORD);
    }

    /** Product till only — never services (services = check-in, pay later). */
    public static function canSellProducts(?array $modules = null): bool
    {
        $modules = $modules ?? self::staffModules();
        if (!self::hasProducts($modules)) {
            return false;
        }
        if (!TenantContext::can(Capabilities::SALES_RECORD)) {
            return false;
        }
        // On service branches, product till is separate — only if products module on
        return true;
    }

    /** Services are NEVER sold at till — check-in records customer, payment is later. */
    public static function canSellServices(): bool
    {
        return false;
    }

    /**
     * Legacy “record commission sale” (immediate pay). Disabled when services module uses check-in workflow.
     */
    public static function canRecordCommissionSale(?array $modules = null): bool
    {
        $modules = $modules ?? self::staffModules();
        if (self::hasServices($modules)) {
            return false;
        }
        return TenantContext::can(Capabilities::COMMISSION_RECORD);
    }

    public static function canViewCommission(): bool
    {
        return TenantContext::can(Capabilities::COMMISSION_VIEW);
    }

    public static function canProcessPayments(): bool
    {
        return TenantContext::can(Capabilities::PAYMENTS_RECEIVE)
            || TenantContext::can(Capabilities::PAYMENTS_UPDATE);
    }

    public static function canManageAppointments(?array $modules = null): bool
    {
        $modules = $modules ?? self::staffModules();
        return self::hasAppointments($modules) && TenantContext::can(Capabilities::APPOINTMENTS_MANAGE);
    }

    public static function canViewSalesHistory(): bool
    {
        return TenantContext::can(Capabilities::SALES_VIEW);
    }
}
