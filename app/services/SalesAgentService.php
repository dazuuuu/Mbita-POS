<?php
// app/services/SalesAgentService.php
// Owner-driven sales agent management. Mirrors StaffService for the sales_agent role.

class SalesAgentService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(int $tenantId, array $in, callable $notify): array
    {
        $email = strtolower(trim($in['email'] ?? ''));
        $name  = trim($in['name'] ?? '');
        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if (!$errors && $this->emailExists($email)) {
            $errors['email'] = 'That email is already in use.';
        }
        if ($errors) {
            return ['ok' => false, 'user_id' => null, 'temp_password' => null, 'errors' => $errors];
        }

        $roleId = $this->roleId('sales_agent');
        if ($roleId === null) {
            return ['ok' => false, 'user_id' => null, 'temp_password' => null, 'errors' => ['_' => 'Sales agent role missing. Run migration 023.']];
        }

        $temp = StaffService::generateTempPassword();
        $username = $name !== '' ? $name : strstr($email, '@', true);

        $stmt = $this->db->prepare(
            'INSERT INTO users (tenant_id, username, email, password_hash, must_reset_password, role_id, is_active, email_verified)
             VALUES (:t, :u, :e, :p, 1, :r, 1, 1)'
        );
        $stmt->execute([
            ':t' => $tenantId, ':u' => $username, ':e' => $email,
            ':p' => password_hash($temp, PASSWORD_DEFAULT), ':r' => $roleId,
        ]);
        $userId = (int) $this->db->lastInsertId();

        try {
            $shop = $this->shopName($tenantId);
            $notify(['email' => $email, 'name' => $username, 'temp_password' => $temp, 'shop' => $shop]);
        } catch (\Throwable $e) {
            error_log('SalesAgentService notify failed: ' . $e->getMessage());
        }

        return ['ok' => true, 'user_id' => $userId, 'temp_password' => $temp, 'errors' => []];
    }

    public function listForTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.email, u.is_active, u.must_reset_password
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.tenant_id = :t AND r.role_name = 'sales_agent'
           ORDER BY u.username ASC"
        );
        $stmt->execute([':t' => $tenantId]);
        return $stmt->fetchAll();
    }

    public function findAgent(int $tenantId, int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.id, u.username, u.email, u.is_active
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.tenant_id = ? AND r.role_name = 'sales_agent' LIMIT 1"
        );
        $stmt->execute([$userId, $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function delete(int $tenantId, int $userId): array
    {
        $agent = $this->findAgent($tenantId, $userId);
        if (!$agent) {
            return ['ok' => false, 'error' => 'Sales agent not found.'];
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM commission_sales WHERE agent_user_id = ? AND tenant_id = ?"
        );
        $stmt->execute([$userId, $tenantId]);
        if ((int) $stmt->fetchColumn() > 0) {
            return ['ok' => false, 'error' => 'Cannot delete — this agent has commission sales on record.'];
        }
        $this->db->beginTransaction();
        try {
            $this->db->prepare('DELETE FROM user_permissions WHERE user_id = ?')->execute([$userId]);
            $this->db->prepare(
                "DELETE u FROM users u JOIN roles r ON r.id = u.role_id
                  WHERE u.id = ? AND u.tenant_id = ? AND r.role_name = 'sales_agent'"
            )->execute([$userId, $tenantId]);
            $this->db->commit();
            return ['ok' => true, 'error' => null];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            return ['ok' => false, 'error' => 'Could not delete sales agent.'];
        }
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

    private function shopName(int $tenantId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return (string) ($stmt->fetchColumn() ?: 'your shop');
    }
}
