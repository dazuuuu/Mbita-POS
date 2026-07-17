<?php
// app/services/AuthService.php
// Credential checking: owner (email/password or PIN) and staff (PIN only).

use Models\SubscriptionModel;

class AuthService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Look up a user by email with their role name. */
    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.role_name
             FROM users u JOIN roles r ON u.role_id = r.id
             WHERE u.email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    /** Staff PIN login — no shop code; PIN only (phone lockscreen). */
    public function findStaffByPin(string $pin): ?array
    {
        return $this->findUserByPin($pin, 'employee');
    }

    /** Super owner PIN login — no shop code. */
    public function findOwnerByPin(string $pin): ?array
    {
        return $this->findUserByPin($pin, 'owner');
    }

    /**
     * @deprecated Use findStaffByPin() — shop slug is no longer required.
     */
    public function findByPin(string $shopSlug, string $pin): ?array
    {
        return $this->findStaffByPin($pin);
    }

    /**
     * Resolve a PIN across active tenants.
     * @param 'employee'|'owner' $roleFilter
     */
    private function findUserByPin(string $pin, string $roleFilter): ?array
    {
        if (!StaffService::validatePinFormat($pin)) {
            return null;
        }
        if (!SchemaHelper::columnExists($this->db, 'users', 'login_pin_lookup')) {
            return null;
        }

        $stmt = $this->db->query("SELECT id, name, slug FROM tenants WHERE status = 'active'");
        $tenants = $stmt->fetchAll() ?: [];
        $matches = [];

        $userStmt = $this->db->prepare(
            'SELECT u.*, r.role_name
               FROM users u
               JOIN roles r ON u.role_id = r.id
              WHERE u.tenant_id = ? AND u.login_pin_lookup = ? AND u.is_active = 1
              LIMIT 1'
        );

        foreach ($tenants as $tenant) {
            $tenantId = (int) $tenant['id'];
            $lookup = StaffService::pinLookup($tenantId, $pin);
            $userStmt->execute([$tenantId, $lookup]);
            $user = $userStmt->fetch();
            if (!$user || empty($user['login_pin_hash'])) {
                continue;
            }
            if (!password_verify($pin, $user['login_pin_hash'])) {
                continue;
            }

            $role = $user['role_name'] ?? '';
            if ($roleFilter === 'owner') {
                if ($role !== 'tenant_owner') {
                    continue;
                }
            } else {
                if (!StaffRoles::isEmployeeRole($role)) {
                    continue;
                }
            }

            $user['tenant_name'] = $tenant['name'];
            $matches[] = $user;
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    /** How the Super owner signs in for this tenant (password or pin). */
    public function ownerLoginMethodForTenant(?int $tenantId): string
    {
        if ($tenantId === null || !SchemaHelper::columnExists($this->db, 'tenants', 'owner_login_method')) {
            return 'password';
        }
        $stmt = $this->db->prepare('SELECT owner_login_method FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $v = $stmt->fetchColumn();
        return ($v === 'pin') ? 'pin' : 'password';
    }

    /** When only one active shop exists, return its owner login method for the login screen. */
    public function defaultOwnerLoginMethod(): string
    {
        if (!SchemaHelper::tableExists($this->db, 'tenants')) {
            return 'password';
        }
        $stmt = $this->db->query(
            "SELECT owner_login_method FROM tenants WHERE status = 'active' LIMIT 2"
        );
        $rows = $stmt->fetchAll() ?: [];
        if (count($rows) !== 1) {
            return 'password';
        }
        return ($rows[0]['owner_login_method'] ?? '') === 'pin' ? 'pin' : 'password';
    }

    public function verifyPassword(array $user, string $password): bool
    {
        return !empty($user['password_hash']) && password_verify($password, $user['password_hash']);
    }

    /** Admin/owner accounts only — blocks staff internal emails from email login. */
    public function isAdminLoginEligible(array $user): bool
    {
        $role = $user['role_name'] ?? '';
        if (StaffRoles::isEmployeeRole($role)) {
            return false;
        }
        if (str_ends_with(strtolower($user['email'] ?? ''), '@staff.internal')) {
            return false;
        }
        return $role === 'tenant_owner';
    }

    public function subscriptionFor(?int $tenantId): ?array
    {
        if ($tenantId === null) {
            return null;
        }
        try {
            return (new SubscriptionModel($this->db))->forTenant($tenantId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function logAttempt(string $email, ?string $ip): void
    {
        try {
            $this->db->prepare('INSERT INTO login_attempts (email, ip_address) VALUES (?, ?)')
                ->execute([$email, $ip]);
        } catch (\Throwable $e) {
            // best-effort
        }
    }
}
