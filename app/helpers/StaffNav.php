<?php
// app/helpers/StaffNav.php — shared staff menu / workflow rules (sidebar, dashboard, pages).

class StaffNav
{
    /** Branch-scoped modules for the logged-in staff member (union with tenant modules). */
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
                $branchMods = TenantModules::fromBranch($branch);
                foreach ($branchMods as $key => $on) {
                    $modules[$key] = !empty($modules[$key]) || $on;
                }
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

    /** Capabilities that unlock the service check-in workflow. */
    public static function checkInCapabilities(): array
    {
        return [
            Capabilities::CUSTOMERS_CHECKIN,
            Capabilities::INVOICES_MANAGE,
            Capabilities::COMMISSION_RECORD,
        ];
    }

    /** Official service check-in (reception / delegated service staff). */
    public static function canCheckIn(?array $modules = null): bool
    {
        return TenantContext::canAny(self::checkInCapabilities());
    }

    /** Product till only — never services (services = check-in, pay later). */
    public static function canSellProducts(?array $modules = null): bool
    {
        $modules = $modules ?? self::staffModules();
        if (!self::hasProducts($modules)) {
            return false;
        }
        return TenantContext::can(Capabilities::SALES_RECORD);
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
        return TenantContext::canAny([
            Capabilities::COMMISSION_VIEW,
            Capabilities::COMMISSION_RECORD,
        ]);
    }

    public static function paymentCapabilities(): array
    {
        return [Capabilities::PAYMENTS_RECEIVE, Capabilities::PAYMENTS_UPDATE];
    }

    public static function canProcessPayments(): bool
    {
        return TenantContext::canAny(self::paymentCapabilities());
    }

    public static function canManageAppointments(?array $modules = null): bool
    {
        return TenantContext::can(Capabilities::APPOINTMENTS_MANAGE);
    }

    public static function canViewSalesHistory(): bool
    {
        return TenantContext::can(Capabilities::SALES_VIEW);
    }

    /**
     * Reception / general staff: record customer + services; stylist assigned when payment is taken.
     */
    public static function defersStaffAssignmentAtCheckIn(): bool
    {
        return TenantContext::can(Capabilities::CUSTOMERS_CHECKIN)
            || TenantContext::can(Capabilities::INVOICES_MANAGE);
    }

    /** Barber/stylist walk-in — commission goes to themselves; no picker shown. */
    public static function usesSelfAssignmentAtCheckIn(): bool
    {
        if (self::defersStaffAssignmentAtCheckIn()) {
            return false;
        }
        if (!TenantContext::can(Capabilities::COMMISSION_RECORD)) {
            return false;
        }
        $type = $_SESSION['staff_type'] ?? '';
        return $type === 'barber';
    }

    /** Who receives commission for this check-in (null = assign at till). */
    public static function resolveCheckInAgentId(int $userId): ?int
    {
        if (self::usesSelfAssignmentAtCheckIn()) {
            return $userId;
        }
        if (self::defersStaffAssignmentAtCheckIn()) {
            return null;
        }
        if (TenantContext::can(Capabilities::COMMISSION_RECORD)) {
            return $userId;
        }
        return null;
    }

    /** Staff types that may be assigned when processing payment. */
    public static function assignableServiceStaffTypes(): array
    {
        return ['barber'];
    }
}
