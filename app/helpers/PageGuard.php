<?php
// app/helpers/PageGuard.php
// Per-request gate for protected pages.
// SINGLE-TENANT BUILD: subscription gating is disabled (no plans to pay for).
// Authentication (password + OTP) and role/capability checks still apply.

class PageGuard
{
    const LOGIN_URL = '/Curlz/public/auth/login.php';
    const STAFF_RESET_URL = '/Curlz/public/staff/reset-password.php';
    const AGENT_RESET_URL = '/Curlz/public/sales-agent/reset-password.php';

    /** Any fully-authenticated user (owner or staff). No role/subscription gate. */
    public static function auth(): void
    {
        self::requireFullAuth();
        self::enforcePasswordReset();
    }

    /** Require a fully-authenticated tenant OWNER. */
    public static function tenant(): void
    {
        self::requireFullAuth();
        if (TenantContext::role() !== 'tenant_owner') {
            self::deny();
        }
    }

    /** Require a fully-authenticated STAFF member. */
    public static function staff(): void
    {
        self::requireFullAuth();
        if (TenantContext::role() !== 'staff') {
            self::deny();
        }
        self::enforcePasswordReset();
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
        if ($role === 'staff' && TenantContext::can(Capabilities::COMMISSION_RECORD)) {
            self::enforcePasswordReset();
            CommissionService::ensureSchema(Database::pdo());
            return;
        }
        self::deny();
    }

    /** Require a fully-authenticated user (owner or staff) who holds a capability. */
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
        if (TenantContext::role() === 'staff' && !empty($_SESSION['must_reset'])) {
            header('Location: ' . self::STAFF_RESET_URL);
            exit;
        }
    }

    private static function enforceAgentPasswordReset(): void
    {
        if (TenantContext::role() === 'sales_agent' && !empty($_SESSION['must_reset'])) {
            header('Location: ' . self::AGENT_RESET_URL);
            exit;
        }
    }

    private static function requireFullAuth(): void
    {
        $authed = !empty($_SESSION['logged_in']) && !empty($_SESSION['otp_verified']) && TenantContext::check();
        if (!$authed) {
            header('Location: ' . self::LOGIN_URL);
            exit;
        }
    }

    /** Kept as a no-op so any remaining callers are harmless in the single-tenant build. */
    private static function requireActiveSubscription(): void
    {
        return;
    }

    private static function deny(): void
    {
        header('Location: ' . self::LOGIN_URL . '?denied=1');
        exit;
    }
}