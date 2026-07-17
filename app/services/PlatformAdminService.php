<?php
// app/services/PlatformAdminService.php
// CRUD for platform-level admins (tenant_id IS NULL, role platform_admin).

class PlatformAdminService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return list<array> */
    public function list(): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.email, u.is_active, u.created_at
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.tenant_id IS NULL AND r.role_name = ?
              ORDER BY u.username ASC'
        );
        $stmt->execute(['platform_admin']);
        return $stmt->fetchAll() ?: [];
    }

    public function count(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.tenant_id IS NULL AND r.role_name = ?'
        );
        $stmt->execute(['platform_admin']);
        return (int) $stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.username, u.email, u.is_active, u.created_at
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = ? AND u.tenant_id IS NULL AND r.role_name = ?
              LIMIT 1'
        );
        $stmt->execute([$id, 'platform_admin']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function emailTaken(string $email, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM users WHERE email = ? AND tenant_id IS NULL';
        $params = [$email];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * @param array{name:string,email:string,password:string,confirm_password?:string} $in
     * @return array{ok:bool, user_id:?int, errors:array<string,string>}
     */
    public function create(array $in): array
    {
        $name = trim($in['name'] ?? '');
        $email = strtolower(trim($in['email'] ?? ''));
        $password = $in['password'] ?? '';
        $confirm = $in['confirm_password'] ?? $password;
        $errors = $this->validateAccount($name, $email, $password, $confirm, true);

        if (!$errors && $this->emailTaken($email)) {
            $errors['email'] = 'That email is already registered.';
        }

        if ($errors) {
            return ['ok' => false, 'user_id' => null, 'errors' => $errors];
        }

        $roleId = $this->roleId();
        if ($roleId === null) {
            return ['ok' => false, 'user_id' => null, 'errors' => ['_' => 'platform_admin role missing — run migration 014.']];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO users (tenant_id, username, email, password_hash, role_id, is_active, email_verified)
             VALUES (NULL, ?, ?, ?, ?, 1, 1)'
        );
        $stmt->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $roleId,
        ]);

        return ['ok' => true, 'user_id' => (int) $this->db->lastInsertId(), 'errors' => []];
    }

    /**
     * @param array{name?:string,email?:string,password?:string,confirm_password?:string,is_active?:int} $in
     * @return array{ok:bool, errors:array<string,string>}
     */
    public function update(int $id, array $in): array
    {
        $admin = $this->find($id);
        if (!$admin) {
            return ['ok' => false, 'errors' => ['_' => 'Admin account not found.']];
        }

        $name = trim($in['name'] ?? $admin['username']);
        $email = strtolower(trim($in['email'] ?? $admin['email']));
        $password = $in['password'] ?? '';
        $confirm = $in['confirm_password'] ?? $password;
        $isActive = isset($in['is_active']) ? (int) $in['is_active'] : (int) $admin['is_active'];

        $requirePassword = $password !== '';
        $errors = $this->validateAccount($name, $email, $password, $confirm, $requirePassword);

        if (!$errors && $this->emailTaken($email, $id)) {
            $errors['email'] = 'That email is already registered.';
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $sql = 'UPDATE users SET username = ?, email = ?, is_active = ?';
        $params = [$name, $email, $isActive ? 1 : 0];

        if ($requirePassword) {
            $sql .= ', password_hash = ?';
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }

        $sql .= ' WHERE id = ? AND tenant_id IS NULL';
        $params[] = $id;

        $this->db->prepare($sql)->execute($params);
        return ['ok' => true, 'errors' => []];
    }

    /** @return array{ok:bool, error:?string} */
    public function delete(int $id, int $currentUserId): array
    {
        if ($id === $currentUserId) {
            return ['ok' => false, 'error' => 'You cannot remove your own account while logged in.'];
        }

        if (!$this->find($id)) {
            return ['ok' => false, 'error' => 'Admin account not found.'];
        }

        if ($this->count() <= 1) {
            return ['ok' => false, 'error' => 'Cannot remove the last platform admin.'];
        }

        $stmt = $this->db->prepare(
            'DELETE u FROM users u
              JOIN roles r ON r.id = u.role_id
             WHERE u.id = ? AND u.tenant_id IS NULL AND r.role_name = ?'
        );
        $stmt->execute([$id, 'platform_admin']);

        return ['ok' => true, 'error' => null];
    }

    /** @return array<string,string> */
    private function validateAccount(string $name, string $email, string $password, string $confirm, bool $requirePassword): array
    {
        $errors = [];
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }
        if ($requirePassword) {
            if (strlen($password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters.';
            } elseif ($password !== $confirm) {
                $errors['confirm_password'] = 'Passwords do not match.';
            }
        }
        return $errors;
    }

    private function roleId(): ?int
    {
        $id = $this->db->query("SELECT id FROM roles WHERE role_name = 'platform_admin' LIMIT 1")->fetchColumn();
        return $id ? (int) $id : null;
    }
}
