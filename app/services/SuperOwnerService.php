<?php
// app/services/SuperOwnerService.php
// Creates a shop (tenant) and its Super owner (tenant_owner role).

class SuperOwnerService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * @param array{
     *   shop_name:string,slug?:string,business_type?:string,status?:string,
     *   owner_name:string,owner_email:string,owner_password:string,
     *   owner_phone?:string
     * } $in
     * @return array{ok:bool,tenant_id:?int,user_id:?int,errors:array<string,string>}
     */
    public function create(array $in): array
    {
        $shopName = trim($in['shop_name'] ?? '');
        $slug = trim($in['slug'] ?? '');
        $status = $in['status'] ?? 'active';
        $ownerName = trim($in['owner_name'] ?? '');
        $ownerEmail = strtolower(trim($in['owner_email'] ?? ''));
        $ownerPassword = $in['owner_password'] ?? '';
        $ownerPhone = trim($in['owner_phone'] ?? '');
        $businessType = $in['business_type'] ?? 'shop';

        $errors = [];
        if ($shopName === '') {
            $errors['shop_name'] = 'Shop name is required.';
        }
        if ($slug === '') {
            $slug = $this->slugFromName($shopName);
        }
        if (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
            $errors['slug'] = 'Slug may only contain lowercase letters, numbers, and hyphens.';
        }
        if (!in_array($status, ['active', 'suspended', 'cancelled'], true)) {
            $errors['status'] = 'Invalid status.';
        }
        if ($ownerName === '') {
            $errors['owner_name'] = 'Owner name is required.';
        }
        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['owner_email'] = 'A valid owner email is required.';
        }
        if (strlen($ownerPassword) < 8) {
            $errors['owner_password'] = 'Password must be at least 8 characters.';
        }
        if (!in_array($businessType, ['shop', 'barbershop_salon'], true)) {
            $errors['business_type'] = 'Choose a valid business type.';
        }
        if ($errors) {
            return ['ok' => false, 'tenant_id' => null, 'user_id' => null, 'errors' => $errors];
        }

        Schema025Service::ensureApplied($this->db);

        $roleId = (int) $this->db->query("SELECT id FROM roles WHERE role_name = 'tenant_owner' LIMIT 1")->fetchColumn();
        if (!$roleId) {
            return ['ok' => false, 'tenant_id' => null, 'user_id' => null, 'errors' => ['_' => 'tenant_owner role missing — run migration 014.']];
        }

        try {
            $this->db->beginTransaction();

            $chk = $this->db->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
            $chk->execute([$slug]);
            if ($chk->fetchColumn()) {
                $this->db->rollBack();
                return ['ok' => false, 'tenant_id' => null, 'user_id' => null, 'errors' => ['slug' => 'That shop code is already taken.']];
            }

            $emailAny = $this->db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $emailAny->execute([$ownerEmail]);
            if ($emailAny->fetchColumn()) {
                $this->db->rollBack();
                return ['ok' => false, 'tenant_id' => null, 'user_id' => null, 'errors' => ['owner_email' => 'That email is already registered.']];
            }

            $tenantCols = 'name, slug, status';
            $tenantVals = ':name, :slug, :status';
            $tenantParams = [':name' => $shopName, ':slug' => $slug, ':status' => $status];
            if (SchemaHelper::columnExists($this->db, 'tenants', 'business_type')) {
                $tenantCols .= ', business_type';
                $tenantVals .= ', :business_type';
                $tenantParams[':business_type'] = $businessType;
            }
            $this->db->prepare("INSERT INTO tenants ({$tenantCols}) VALUES ({$tenantVals})")->execute($tenantParams);
            $tenantId = (int) $this->db->lastInsertId();

            $hash = password_hash($ownerPassword, PASSWORD_DEFAULT);
            $this->db->prepare(
                'INSERT INTO users (tenant_id, username, email, password_hash, role_id, is_active, email_verified)
                 VALUES (:tenant_id, :username, :email, :password_hash, :role_id, 1, 1)'
            )->execute([
                ':tenant_id'     => $tenantId,
                ':username'      => $ownerName,
                ':email'         => $ownerEmail,
                ':password_hash' => $hash,
                ':role_id'       => $roleId,
            ]);
            $ownerId = (int) $this->db->lastInsertId();

            if ($ownerPhone !== '') {
                $this->db->prepare(
                    'INSERT INTO user_profiles (user_id, phone) VALUES (?, ?)
                     ON DUPLICATE KEY UPDATE phone = VALUES(phone)'
                )->execute([$ownerId, $ownerPhone]);
            }

            $this->db->prepare('UPDATE tenants SET owner_user_id = ? WHERE id = ?')->execute([$ownerId, $tenantId]);

            $this->db->commit();
            return ['ok' => true, 'tenant_id' => $tenantId, 'user_id' => $ownerId, 'errors' => []];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'tenant_id' => null, 'user_id' => null, 'errors' => ['_' => 'Could not create Super owner: ' . $e->getMessage()]];
        }
    }

    private function slugFromName(string $name): string
    {
        $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)), '-');
        return $base !== '' ? $base : 'shop';
    }
}
