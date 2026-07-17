<?php
// app/services/StaffService.php
// Owner-driven staff management. Creates staff with a PIN (no email login).
// Admin assigns a role template (cashier, reception, sales, barber, junior admin)
// and delegates features on the authorization page.

class StaffService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        Schema025Service::ensureApplied($db);
    }

    /**
     * @param int   $tenantId
     * @param array $in  name, pin (4-5 digits), staff_type, branch_id (optional)
     * @return array ['ok'=>bool, 'user_id'=>?int, 'errors'=>array]
     */
    public function create(int $tenantId, array $in): array
    {
        $name     = trim($in['name'] ?? '');
        $pin      = trim($in['pin'] ?? '');
        $staffType = $in['staff_type'] ?? 'general';
        $branchId = isset($in['branch_id']) && (int) $in['branch_id'] > 0 ? (int) $in['branch_id'] : null;
        $errors = [];

        if ($name === '') {
            $errors['name'] = 'Staff name is required.';
        }
        if (!preg_match('/^\d{4,5}$/', $pin)) {
            $errors['pin'] = 'PIN must be 4 or 5 digits.';
        }
        if (!isset(StaffRoles::typeLabels()[$staffType])) {
            $errors['staff_type'] = 'Choose a valid staff role.';
        }
        if ($branchId !== null && !$this->branchBelongsToTenant($branchId, $tenantId)) {
            $errors['branch_id'] = 'Choose a valid branch.';
        }
        if (!$errors && $branchId === null && $this->branchCount($tenantId) > 0) {
            $errors['branch_id'] = 'Select which branch or shop this staff member works at.';
        }
        if (!$errors && $this->pinExists($tenantId, $pin)) {
            $errors['pin'] = 'That PIN is already in use by another staff member.';
        }
        if (!$errors) {
            $limit = $this->staffLimit($tenantId);
            if ($limit !== null && $this->staffCount($tenantId) >= $limit) {
                $errors['_'] = "Your plan allows up to {$limit} staff. Upgrade to add more.";
            }
        }
        if ($errors) {
            return ['ok' => false, 'user_id' => null, 'errors' => $errors];
        }

        $roleName = StaffRoles::roleForType($staffType);
        $roleId = $this->roleId($roleName);
        if ($roleId === null) {
            return ['ok' => false, 'user_id' => null, 'errors' => ['_' => 'Staff role missing. Run migration 025.']];
        }

        $internalEmail = $this->internalEmail($tenantId, $name);
        $pinLookup = self::pinLookup($tenantId, $pin);

        $stmt = $this->db->prepare(
            'INSERT INTO users (tenant_id, branch_id, staff_type, username, email, password_hash, login_pin_hash, login_pin_lookup,
                                must_reset_password, role_id, is_active, email_verified)
             VALUES (:t, :b, :st, :u, :e, :p, :ph, :pl, 0, :r, 1, 1)'
        );
        $stmt->execute([
            ':t'  => $tenantId,
            ':b'  => $branchId,
            ':st' => $staffType,
            ':u'  => $name,
            ':e'  => $internalEmail,
            ':p'  => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            ':ph' => password_hash($pin, PASSWORD_DEFAULT),
            ':pl' => $pinLookup,
            ':r'  => $roleId,
        ]);
        $userId = (int) $this->db->lastInsertId();

        return ['ok' => true, 'user_id' => $userId, 'errors' => []];
    }

    /** All non-owner employees for a tenant. */
    public function listForTenant(int $tenantId, ?int $branchId = null): array
    {
        $roles = StaffRoles::employeeRoleNames();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $sql = "SELECT u.id, u.username, u.email, u.is_active, u.must_reset_password, u.branch_id,
                       u.staff_type, u.role_id, r.role_name, b.title AS branch_title
                  FROM users u
                  JOIN roles r ON r.id = u.role_id
             LEFT JOIN branches b ON b.id = u.branch_id
                 WHERE u.tenant_id = ? AND r.role_name IN ({$placeholders})";
        $params = array_merge([$tenantId], $roles);
        if ($branchId !== null) {
            $sql .= ' AND u.branch_id = ?';
            $params[] = $branchId;
        }
        $sql .= ' ORDER BY u.username ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function pinLookup(int $tenantId, string $pin): string
    {
        return hash('sha256', $tenantId . ':' . $pin);
    }

    public static function validatePinFormat(string $pin): bool
    {
        return (bool) preg_match('/^\d{4,5}$/', $pin);
    }

    private function internalEmail(int $tenantId, string $name): string
    {
        $base = preg_replace('/[^a-z0-9]+/', '', strtolower($name)) ?: 'staff';
        $email = "{$base}.{$tenantId}@staff.internal";
        $i = 0;
        while ($this->emailExists($email)) {
            $email = "{$base}.{$tenantId}." . (++$i) . '@staff.internal';
        }
        return $email;
    }

    private function pinExists(int $tenantId, string $pin, ?int $exceptUserId = null): bool
    {
        if (!SchemaHelper::columnExists($this->db, 'users', 'login_pin_lookup')) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT id FROM users WHERE tenant_id = ? AND login_pin_lookup = ? LIMIT 1');
        $stmt->execute([$tenantId, self::pinLookup($tenantId, $pin)]);
        $id = $stmt->fetchColumn();
        if ($id === false) {
            return false;
        }
        return $exceptUserId === null || (int) $id !== $exceptUserId;
    }

    private function branchBelongsToTenant(int $branchId, int $tenantId): bool
    {
        if ($branchId <= 0) {
            return false;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM branches WHERE id = ? AND tenant_id = ? LIMIT 1');
        $stmt->execute([$branchId, $tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    private function branchCount(int $tenantId): int
    {
        if (!SchemaHelper::tableExists($this->db, 'branches')) {
            return 0;
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM branches WHERE tenant_id = ?');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
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
        $roles = StaffRoles::employeeRoleNames();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id=u.role_id
              WHERE u.tenant_id=? AND r.role_name IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$tenantId], $roles));
        return (int) $stmt->fetchColumn();
    }

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
            return null;
        }
    }

    /** Role id for a staff template (defaults to general staff role). */
    public function staffRoleId(?string $staffType = null): ?int
    {
        $roleName = $staffType ? StaffRoles::roleForType($staffType) : 'staff';
        return $this->roleId($roleName);
    }

    public function roleDefaultCaps(string $role = 'staff'): array
    {
        $stmt = $this->db->prepare('SELECT capabilities FROM roles WHERE role_name = ? LIMIT 1');
        $stmt->execute([$role]);
        $json = $stmt->fetchColumn();
        return $json ? (json_decode($json, true) ?: []) : [];
    }

    public function findStaff(int $tenantId, int $userId): ?array
    {
        $roles = StaffRoles::employeeRoleNames();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.email, u.is_active, u.branch_id, u.staff_type, u.role_id, r.role_name, b.title AS branch_title
               FROM users u
               JOIN roles r ON r.id = u.role_id
          LEFT JOIN branches b ON b.id = u.branch_id
              WHERE u.id = ? AND u.tenant_id = ? AND r.role_name IN ({$placeholders}) LIMIT 1"
        );
        $stmt->execute(array_merge([$userId, $tenantId], $roles));
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function effectiveCaps(int $userId, int $roleId): array
    {
        return Capabilities::effective($this->db, $userId, $roleId);
    }

    public function setCapabilities(int $tenantId, int $userId, array $desired, array $manageable, array $roleDefaults): void
    {
        $del = $this->db->prepare('DELETE FROM user_permissions WHERE user_id = ? AND capability = ?');
        $up  = $this->db->prepare(
            'INSERT INTO user_permissions (tenant_id, user_id, capability, effect)
             VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE effect = VALUES(effect)'
        );
        $this->db->beginTransaction();
        try {
            foreach ($manageable as $cap) {
                $want = in_array($cap, $desired, true);
                $def  = in_array($cap, $roleDefaults, true);
                if ($want === $def) {
                    $del->execute([$userId, $cap]);
                } else {
                    $up->execute([$tenantId, $userId, $cap, $want ? 'grant' : 'revoke']);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Update staff profile (name, role, branch). PIN is optional.
     * @return array ['ok'=>bool, 'errors'=>array]
     */
    public function update(int $tenantId, int $userId, array $in): array
    {
        $staff = $this->findStaff($tenantId, $userId);
        if (!$staff) {
            return ['ok' => false, 'errors' => ['_' => 'Staff member not found.']];
        }

        $name      = trim($in['name'] ?? $staff['username']);
        $staffType = $in['staff_type'] ?? ($staff['staff_type'] ?? 'general');
        $branchId  = isset($in['branch_id']) && (int) $in['branch_id'] > 0 ? (int) $in['branch_id'] : null;
        $pin       = trim($in['pin'] ?? '');
        $errors    = [];

        if ($name === '') {
            $errors['name'] = 'Staff name is required.';
        }
        if (!isset(StaffRoles::typeLabels()[$staffType])) {
            $errors['staff_type'] = 'Choose a valid staff role.';
        }
        if ($branchId !== null && !$this->branchBelongsToTenant($branchId, $tenantId)) {
            $errors['branch_id'] = 'Choose a valid branch.';
        }
        if (!$errors && $branchId === null) {
            $branchCount = $this->branchCount($tenantId);
            if ($branchCount > 0) {
                $errors['branch_id'] = 'Select which branch or shop this staff member works at.';
            }
        }
        if ($pin !== '' && !preg_match('/^\d{4,5}$/', $pin)) {
            $errors['pin'] = 'PIN must be 4 or 5 digits.';
        }
        if (!$errors && $pin !== '' && $this->pinExists($tenantId, $pin, $userId)) {
            $errors['pin'] = 'That PIN is already in use by another staff member.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $roleName = StaffRoles::roleForType($staffType);
        $roleId = $this->roleId($roleName);
        if ($roleId === null) {
            return ['ok' => false, 'errors' => ['_' => 'Staff role missing. Run migration 025.']];
        }

        $row = [
            'username'   => $name,
            'staff_type' => $staffType,
            'branch_id'  => $branchId,
            'role_id'    => $roleId,
        ];
        if ($pin !== '') {
            $row['login_pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
            $row['login_pin_lookup'] = self::pinLookup($tenantId, $pin);
        }
        $row = SchemaHelper::filterColumns($this->db, 'users', $row);
        $sets = [];
        $params = [];
        foreach ($row as $col => $val) {
            $sets[] = "{$col} = ?";
            $params[] = $val;
        }
        $params[] = $userId;
        $params[] = $tenantId;
        $stmt = $this->db->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = ? AND tenant_id = ?');
        $stmt->execute($params);

        return ['ok' => true, 'errors' => []];
    }

    /** Update staff PIN (owner action). */
    public function updatePin(int $tenantId, int $userId, string $pin): array
    {
        if (!self::validatePinFormat($pin)) {
            return ['ok' => false, 'error' => 'PIN must be 4 or 5 digits.'];
        }
        $staff = $this->findStaff($tenantId, $userId);
        if (!$staff) {
            return ['ok' => false, 'error' => 'Staff member not found.'];
        }
        if ($this->pinExists($tenantId, $pin)) {
            $lookup = self::pinLookup($tenantId, $pin);
            $chk = $this->db->prepare('SELECT id FROM users WHERE tenant_id = ? AND login_pin_lookup = ? LIMIT 1');
            $chk->execute([$tenantId, $lookup]);
            if ((int) $chk->fetchColumn() !== $userId) {
                return ['ok' => false, 'error' => 'That PIN is already in use.'];
            }
        }
        $stmt = $this->db->prepare(
            'UPDATE users SET login_pin_hash = ?, login_pin_lookup = ? WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            password_hash($pin, PASSWORD_DEFAULT),
            self::pinLookup($tenantId, $pin),
            $userId,
            $tenantId,
        ]);
        return ['ok' => true, 'error' => null];
    }

    public function purge(int $tenantId, int $userId): array
    {
        $staff = $this->findStaff($tenantId, $userId);
        if (!$staff) {
            return ['ok' => false, 'error' => 'Staff member not found.'];
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'DELETE cse FROM commission_sale_expenses cse
                  JOIN commission_sales cs ON cs.id = cse.commission_sale_id
                 WHERE cs.agent_user_id = ? AND cs.tenant_id = ?'
            )->execute([$userId, $tenantId]);

            $this->db->prepare(
                'DELETE FROM commission_sales WHERE agent_user_id = ? AND tenant_id = ?'
            )->execute([$userId, $tenantId]);

            $this->db->prepare(
                'DELETE FROM commission_payouts WHERE agent_user_id = ? AND tenant_id = ?'
            )->execute([$userId, $tenantId]);

            $this->db->prepare(
                'DELETE si FROM sale_items si
                  JOIN sales s ON s.id = si.sale_id
                 WHERE s.staff_id = ? AND s.tenant_id = ?'
            )->execute([$userId, $tenantId]);
            $this->db->prepare(
                'DELETE FROM sales WHERE staff_id = ? AND tenant_id = ?'
            )->execute([$userId, $tenantId]);

            $this->db->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);

            $roles = StaffRoles::employeeRoleNames();
            $placeholders = implode(',', array_fill(0, count($roles), '?'));
            $this->db->prepare(
                "DELETE u FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE u.id = ? AND u.tenant_id = ? AND r.role_name IN ({$placeholders})"
            )->execute(array_merge([$userId, $tenantId], $roles));

            $this->db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'error' => 'Could not remove staff member and their data.'];
        }
    }

    public function delete(int $tenantId, int $userId): array
    {
        return $this->purge($tenantId, $userId);
    }

    public function deactivate(int $tenantId, int $userId): bool
    {
        return $this->setActive($tenantId, $userId, false);
    }

    public function activate(int $tenantId, int $userId): bool
    {
        return $this->setActive($tenantId, $userId, true);
    }

    private function setActive(int $tenantId, int $userId, bool $active): bool
    {
        $staff = $this->findStaff($tenantId, $userId);
        if (!$staff) {
            return false;
        }
        $roles = StaffRoles::employeeRoleNames();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->db->prepare(
            "UPDATE users u JOIN roles r ON r.id = u.role_id
                SET u.is_active = ? WHERE u.id = ? AND u.tenant_id = ? AND r.role_name IN ({$placeholders})"
        );
        $stmt->execute(array_merge([$active ? 1 : 0, $userId, $tenantId], $roles));
        return $stmt->rowCount() > 0;
    }
}
