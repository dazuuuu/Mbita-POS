<?php
// app/services/AuthService.php
// Credential checking for admin (email/password) and staff (shop + PIN).

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

    /** Staff PIN login: shop slug + 4–5 digit PIN. */
    public function findByPin(string $shopSlug, string $pin): ?array
    {
        if (!StaffService::validatePinFormat($pin)) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT id, name, slug FROM tenants WHERE slug = ? AND status = ? LIMIT 1');
        $stmt->execute([trim($shopSlug), 'active']);
        $tenant = $stmt->fetch();
        if (!$tenant) {
            return null;
        }
        $tenantId = (int) $tenant['id'];
        if (!SchemaHelper::columnExists($this->db, 'users', 'login_pin_lookup')) {
            return null;
        }
        $lookup = StaffService::pinLookup($tenantId, $pin);
        $stmt = $this->db->prepare(
            'SELECT u.*, r.role_name
               FROM users u
               JOIN roles r ON u.role_id = r.id
              WHERE u.tenant_id = ? AND u.login_pin_lookup = ? AND u.is_active = 1
              LIMIT 1'
        );
        $stmt->execute([$tenantId, $lookup]);
        $user = $stmt->fetch();
        if (!$user || empty($user['login_pin_hash'])) {
            return null;
        }
        if (!password_verify($pin, $user['login_pin_hash'])) {
            return null;
        }
        if (!StaffRoles::isEmployeeRole($user['role_name'] ?? null)) {
            return null;
        }
        $user['tenant_name'] = $tenant['name'];
        return $user;
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
        return $role === 'tenant_owner' || $role === 'platform_admin';
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
