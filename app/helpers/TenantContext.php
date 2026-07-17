<?php
// app/helpers/TenantContext.php
// Holds the current request's identity: which tenant, which user, which
// capabilities. Populated once at login (into the session) and rehydrated from
// the session on every request via boot().
// Subscription-gating has been removed for single-tenant POS use.

class TenantContext
{
    private static bool $booted        = false;
    private static bool $capsRefreshed = false;
    private static ?int $userId        = null;
    private static ?int $tenantId      = null;
    private static ?string $role        = null;
    private static array $caps          = [];

    /** Rehydrate context from the session. Call once early in each request. */
    public static function boot(): void
    {
        self::$userId   = $_SESSION['user_id']      ?? null;
        self::$tenantId = $_SESSION['tenant_id']    ?? null;
        self::$role     = $_SESSION['role']         ?? null;
        self::$caps     = $_SESSION['capabilities'] ?? [];
        self::$booted   = true;
    }

    /**
     * Populate context at login time and persist it to the session.
     * Computes the effective capability set from the DB.
     */
    public static function establish(PDO $db, array $user): void
    {
        $userId = (int) $user['id'];
        $roleId = (int) $user['role_id'];
        $caps = Capabilities::effective($db, $userId, $roleId);

        $_SESSION['user_id']      = $userId;
        $_SESSION['role_id']      = $roleId;
        $_SESSION['tenant_id']    = isset($user['tenant_id']) ? ($user['tenant_id'] !== null ? (int) $user['tenant_id'] : null) : null;
        $_SESSION['role']         = $user['role_name'] ?? null;
        $_SESSION['staff_type']   = $user['staff_type'] ?? null;
        $_SESSION['capabilities'] = $caps;

        self::$capsRefreshed = true;
        self::boot();
    }

    /** Reload delegated permissions from DB (role defaults + user_permissions). */
    public static function refreshCapabilities(?PDO $db = null): void
    {
        if (!self::$userId) {
            return;
        }

        try {
            $db = $db ?? Database::pdo();
            $roleId = (int) ($_SESSION['role_id'] ?? 0);
            if ($roleId <= 0) {
                $stmt = $db->prepare('SELECT role_id FROM users WHERE id = ? LIMIT 1');
                $stmt->execute([self::$userId]);
                $roleId = (int) ($stmt->fetchColumn() ?: 0);
                if ($roleId > 0) {
                    $_SESSION['role_id'] = $roleId;
                }
            }
            if ($roleId <= 0) {
                return;
            }

            $caps = Capabilities::effective($db, self::$userId, $roleId);
            $_SESSION['capabilities'] = $caps;
            self::$caps = $caps;
        } catch (\Throwable $e) {
            // Keep session capabilities when DB is unavailable.
        }
    }

    public static function check(): bool    { self::ensure(); return self::$userId !== null; }
    public static function userId(): ?int   { self::ensure(); return self::$userId; }
    public static function tenantId(): ?int { self::ensure(); return self::$tenantId; }
    public static function role(): ?string  { self::ensure(); return self::$role; }

    public static function isPlatformAdmin(): bool
    {
        self::ensure();
        return in_array(Capabilities::ALL, self::$caps, true);
    }

    /** Does the current user hold a capability? Platform admins hold all. */
    public static function can(string $cap): bool
    {
        self::ensure();
        return in_array(Capabilities::ALL, self::$caps, true)
            || in_array($cap, self::$caps, true);
    }

    /** True when the user holds at least one of the given capabilities. */
    public static function canAny(array $caps): bool
    {
        self::ensure();
        if (in_array(Capabilities::ALL, self::$caps, true)) {
            return true;
        }
        foreach ($caps as $cap) {
            if (in_array($cap, self::$caps, true)) {
                return true;
            }
        }
        return false;
    }

    private static function ensure(): void
    {
        if (!self::$booted) {
            self::boot();
        }
        if (!self::$capsRefreshed && self::$userId) {
            self::$capsRefreshed = true;
            self::refreshCapabilities();
        }
    }

    /** Test/maintenance helper — reset between requests in a long-running process. */
    public static function reset(): void
    {
        self::$booted        = false;
        self::$capsRefreshed = false;
        self::$userId        = self::$tenantId = null;
        self::$role          = null;
        self::$caps          = [];
    }
}