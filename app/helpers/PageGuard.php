<?php
// app/helpers/PageGuard.php
// Per-request gate for protected pages.

class PageGuard
{
    public static function loginUrl(): string
    {
        return public_path('auth/login.php');
    }

    public static function staffResetUrl(): string
    {
        return public_path('staff/reset-password.php');
    }

    public static function agentResetUrl(): string
    {
        return public_path('sales-agent/reset-password.php');
    }

    /** Any fully-authenticated user (owner or staff). */
    public static function auth(): void
    {
        self::requireFullAuth();
        self::enforcePasswordReset();
    }

    /** Require a fully-authenticated tenant OWNER (email login). */
    public static function tenant(): void
    {
        self::requireFullAuth();
        if (TenantContext::role() !== 'tenant_owner') {
            self::deny();
        }
    }

    /** Require any employee role (PIN login: cashier, reception, sales, barber, etc.). */
    public static function staff(): void
    {
        self::requireFullAuth();
        if (!StaffRoles::isEmployeeRole(TenantContext::role())) {
            self::deny();
        }
        self::enforcePasswordReset();
    }

    /** Alias for staff — any non-owner employee. */
    public static function employee(): void
    {
        self::staff();
    }

    /** Junior admin may access limited owner pages (inventory, reports). */
    public static function juniorAdminOrOwner(): void
    {
        self::requireFullAuth();
        $role = TenantContext::role();
        if ($role === 'tenant_owner') {
            return;
        }
        if ($role === 'junior_admin') {
            return;
        }
        self::deny();
    }

    /** Require a fully-authenticated SALES AGENT. */
    public static function salesAgent(): void
    {
        self::requireFullAuth();
        if (TenantContext::role() !== 'sales_agent') {
            self::deny();
        }
        self::enforceAgentPasswordReset();
        CommissionService::ensureSchema(Database::pdo());
    }

    /** Staff or sales agent who can record commissioned sales. */
    public static function commissionAgent(): void
    {
        self::requireFullAuth();
        $role = TenantContext::role();
        if ($role === 'sales_agent') {
            self::enforceAgentPasswordReset();
            CommissionService::ensureSchema(Database::pdo());
            return;
        }
        if (StaffRoles::isEmployeeRole($role) && TenantContext::can(Capabilities::COMMISSION_RECORD)) {
            self::enforcePasswordReset();
            CommissionService::ensureSchema(Database::pdo());
            return;
        }
        self::deny();
    }

    /** Require a fully-authenticated user who holds a capability. */
    public static function capability(string $cap): void
    {
        self::requireFullAuth();
        if (!TenantContext::can($cap)) {
            self::deny();
        }
        self::enforcePasswordReset();
    }

    private static function enforcePasswordReset(): void
    {
        if (StaffRoles::isEmployeeRole(TenantContext::role()) && !empty($_SESSION['must_reset'])) {
            header('Location: ' . self::staffResetUrl());
            exit;
        }
    }

    private static function enforceAgentPasswordReset(): void
    {
        if (TenantContext::role() === 'sales_agent' && !empty($_SESSION['must_reset'])) {
            header('Location: ' . self::agentResetUrl());
            exit;
        }
    }

    private static function requireFullAuth(): void
    {
        $authed = !empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']) && TenantContext::check();
        if (!$authed) {
            header('Location: ' . self::loginUrl());
            exit;
        }
    }

    private static function requireActiveSubscription(): void
    {
        return;
    }

    private static function deny(): void
    {
        header('Location: ' . self::loginUrl() . '?denied=1');
        exit;
    }
}
