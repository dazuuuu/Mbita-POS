<?php
// app/services/StaffService.php
// Owner-driven staff management. Creates a 'staff' user pinned to a branch with
// an auto-generated temporary password (emailed via the $notify callback), and
// flags the account to force a password reset on first login.

class StaffService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param int   $tenantId  the owner's tenant
     * @param array $in        email, name (optional), branch_id (optional — NULL = all branches)
     * @param callable $notify fn(array $info): void  // info: email,name,temp_password,shop
     * @return array ['ok'=>bool, 'user_id'=>?int, 'temp_password'=>?string, 'errors'=>array]
     */
    public function create(int $tenantId, array $in, callable $notify): array
    {
        $email = strtolower(trim($in['email'] ?? ''));
        $name  = trim($in['name'] ?? '');
        $branchId = isset($in['branch_id']) && (int) $in['branch_id'] > 0 ? (int) $in['branch_id'] : null;
        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($branchId !== null && !$this->branchBelongsToTenant($branchId, $tenantId)) {
            $errors['branch_id'] = 'Choose a valid branch.';
        }
        // Login is by email alone, so emails must be unique across ALL users.
        if (!$errors && $this->emailExists($email)) {
            $errors['email'] = 'That email is already in use.';
        }
        // Respect the plan's staff limit.
        if (!$errors) {
            $limit = $this->staffLimit($tenantId);
            if ($limit !== null && $this->staffCount($tenantId) >= $limit) {
                $errors['_'] = "Your plan allows up to {$limit} staff. Upgrade to add more.";
            }
        }
        if ($errors) {
            return ['ok' => false, 'user_id' => null, 'temp_password' => null, 'errors' => $errors];
        }

        $staffRoleId = $this->roleId('staff');
        if ($staffRoleId === null) {
            return ['ok' => false, 'user_id' => null, 'temp_password' => null, 'errors' => ['_' => 'Staff role missing. Run migration 014.']];
        }

        $temp = self::generateTempPassword();
        $username = $name !== '' ? $name : strstr($email, '@', true);

        $stmt = $this->db->prepare(
            'INSERT INTO users (tenant_id, branch_id, username, email, password_hash, must_reset_password, role_id, is_active, email_verified)
             VALUES (:t, :b, :u, :e, :p, 1, :r, 1, 1)'
        );
        $stmt->execute([
            ':t' => $tenantId, ':b' => $branchId, ':u' => $username, ':e' => $email,
            ':p' => password_hash($temp, PASSWORD_DEFAULT), ':r' => $staffRoleId,
        ]);
        $userId = (int) $this->db->lastInsertId();

        // Email the temp password (best-effort; never fail creation on mail error).
        try {
            $shop = $this->shopName($tenantId);
            $notify(['email' => $email, 'name' => $username, 'temp_password' => $temp, 'shop' => $shop]);
        } catch (\Throwable $e) {
            error_log('StaffService notify failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'user_id' => $userId, 'temp_password' => $temp, 'errors' => []];
    }

    /** Staff for a tenant (optionally one branch), with branch title. */
    public function listForTenant(int $tenantId, ?int $branchId = null): array
    {
        $sql = "SELECT u.id, u.username, u.email, u.is_active, u.must_reset_password, u.branch_id, b.title AS branch_title
                  FROM users u
                  JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
                 WHERE u.tenant_id = :t AND r.role_name = 'staff'";
        $params = [':t' => $tenantId];
        if ($branchId !== null) { $sql .= ' AND u.branch_id = :b'; $params[':b'] = $branchId; }
        $sql .= ' ORDER BY u.username ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function generateTempPassword(int $len = 10): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789abcdefghijkmnpqrstuvwxyz'; // no 0/O/1/I/l
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $len; $i++) { $out .= $alphabet[random_int(0, $max)]; }
        return $out;
    }

    private function branchBelongsToTenant(int $branchId, int $tenantId): bool
    {
        if ($branchId <= 0) { return false; }
        $stmt = $this->db->prepare('SELECT 1 FROM branches WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$branchId, $tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    private function emailExists(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }

    private function roleId(string $name): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE role_name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    private function staffCount(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id WHERE u.tenant_id=? AND r.role_name='staff'"
        );
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /** Active plan's max_staff, or null for unlimited / no active plan. */
    private function staffLimit(int $tenantId): ?int
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT p.max_staff FROM subscriptions s JOIN subscription_plans p ON p.id = s.plan_id
                  WHERE s.tenant_id = ? ORDER BY s.id DESC LIMIT 1'
            );
            $stmt->execute([$tenantId]);
            $v = $stmt->fetchColumn();
            return ($v === false || $v === null) ? null : (int) $v;
        } catch (\Throwable $e) {
            return null; // no subscription tables → unlimited for single-tenant
        }
    }

    private function shopName(int $tenantId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return (string) ($stmt->fetchColumn() ?: 'your shop');
    }

    // ===== per-staff capability management ==============================

    /** roles.id for the 'staff' role. */
    public function staffRoleId(): ?int
    {
        $id = $this->db->query("SELECT id FROM roles WHERE role_name = 'staff' LIMIT 1")->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    /** The capabilities a role grants by default (source of truth = roles table). */
    public function roleDefaultCaps(string $role = 'staff'): array
    {
        $stmt = $this->db->prepare("SELECT capabilities FROM roles WHERE role_name = ? LIMIT 1");
        $stmt->execute([$role]);
        $json = $stmt->fetchColumn();
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    /** One staff member that belongs to this tenant (or null). */
    public function findStaff(int $tenantId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.email, u.is_active, u.branch_id, b.title AS branch_title
               FROM users u
               JOIN roles r ON r.id = u.role_id
          LEFT JOIN branches b ON b.id = u.branch_id
              WHERE u.id = ? AND u.tenant_id = ? AND r.role_name = 'staff' LIMIT 1"
        );
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** Effective capabilities for a staff member (role defaults + grants − revokes). */
    public function effectiveCaps(int $userId, int $roleId): array
    {
        return Capabilities::effective($this->db, $userId, $roleId);
    }

    /**
     * Persist desired capabilities for a staff member. Only capabilities in
     * $manageable are touched (never owner-only powers). Overrides are stored
     * only where the desired state differs from the role default, so the table
     * stays minimal and correct.
     */
    public function setCapabilities(int $tenantId, int $userId, array $desired, array $manageable, array $roleDefaults): void
    {
        $del = $this->db->prepare("DELETE FROM user_permissions WHERE user_id = ? AND capability = ?");
        $up  = $this->db->prepare(
            "INSERT INTO user_permissions (tenant_id, user_id, capability, effect)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE effect = VALUES(effect)"
        );
        $this->db->beginTransaction();
        try {
            foreach ($manageable as $cap) {
                $want = in_array($cap, $desired, true);
                $def  = in_array($cap, $roleDefaults, true);
                if ($want === $def) {
                    $del->execute([$userId, $cap]);          // back to default → no override
                } else {
                    $up->execute([$tenantId, $userId, $cap, $want ? 'grant' : 'revoke']);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            throw $e;
        }
    }

    /** Permanently remove staff and ALL associated records (sales, commissions, permissions). */
    public function purge(int $tenantId, int $userId): array
    {
        $staff = $this->findStaff($tenantId, $userId);
        if (!$staff) {
            return ['ok' => false, 'error' => 'Staff member not found.'];
        }

        $this->db->beginTransaction();
        try {
            // Commission sale expenses
            $this->db->prepare(
                'DELETE cse FROM commission_sale_expenses cse
                  JOIN commission_sales cs ON cs.id = cse.commission_sale_id
                 WHERE cs.agent_user_id = ? AND cs.tenant_id = ?'
            )->execute([$userId, $tenantId]);

            // Commission sales
            $this->db->prepare(
                'DELETE FROM commission_sales WHERE agent_user_id = ? AND tenant_id = ?'
            )->execute([$userId, $tenantId]);

            // Commission payouts for this user
            $this->db->prepare(
                'DELETE FROM commission_payouts WHERE agent_user_id = ? AND tenant_id = ?'
            )->execute([$userId, $tenantId]);

            // POS sale items then sales
            $this->db->prepare(
                'DELETE si FROM sale_items si
                  JOIN sales s ON s.id = si.sale_id
                 WHERE s.staff_id = ? AND s.tenant_id = ?'
            )->execute([$userId, $tenantId]);
            $this->db->prepare(
                'DELETE FROM sales WHERE staff_id = ? AND tenant_id = ?'
            )->execute([$userId, $tenantId]);

            $this->db->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
            $this->db->prepare(
                "DELETE u FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE u.id = ? AND u.tenant_id = ? AND r.role_name = 'staff'"
            )->execute([$userId, $tenantId]);

            $this->db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            return ['ok' => false, 'error' => 'Could not remove staff member and their data.'];
        }
    }

    /** @deprecated Use purge() for full removal. Kept for backward compatibility. */
    public function delete(int $tenantId, int $userId): array
    {
        return $this->purge($tenantId, $userId);
    }

    public function deactivate(int $tenantId, int $userId): bool
    {
        $staff = $this->findStaff($tenantId, $userId);
        if (!$staff) { return false; }
        $stmt = $this->db->prepare(
            "UPDATE users u JOIN roles r ON r.id = u.role_id
                SET u.is_active = 0 WHERE u.id = ? AND u.tenant_id = ? AND r.role_name = 'staff'"
        );
        $stmt->execute([$userId, $tenantId]);
        return $stmt->rowCount() > 0;
    }
}