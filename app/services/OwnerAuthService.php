<?php
// app/services/OwnerAuthService.php
// Super owner login method and credential management (password / PIN).

class OwnerAuthService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        Schema025Service::ensureApplied($db);
        Schema028Service::ensureApplied($db);
    }

    public function loginMethod(int $tenantId): string
    {
        if (!SchemaHelper::columnExists($this->db, 'tenants', 'owner_login_method')) {
            return 'password';
        }
        $stmt = $this->db->prepare('SELECT owner_login_method FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $v = $stmt->fetchColumn();
        return ($v === 'pin') ? 'pin' : 'password';
    }

    public function ownerUser(int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT u.*, r.role_name
               FROM tenants t
               JOIN users u ON u.id = t.owner_user_id
               JOIN roles r ON r.id = u.role_id
              WHERE t.id = ? AND r.role_name = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, 'tenant_owner']);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array{ok:bool,errors:array<string,string>}
     */
    public function saveLoginSettings(int $tenantId, array $in): array
    {
        $method = ($in['owner_login_method'] ?? '') === 'pin' ? 'pin' : 'password';
        $owner = $this->ownerUser($tenantId);
        if (!$owner) {
            return ['ok' => false, 'errors' => ['_' => 'Owner account not found.']];
        }

        $errors = [];
        if ($method === 'pin') {
            $pin = trim($in['owner_pin'] ?? '');
            $confirm = trim($in['owner_pin_confirm'] ?? '');
            if ($pin === '' && empty($owner['login_pin_hash'])) {
                $errors['owner_pin'] = 'Set a 4–5 digit PIN for PIN login.';
            } elseif ($pin !== '') {
                if (!StaffService::validatePinFormat($pin)) {
                    $errors['owner_pin'] = 'PIN must be 4 or 5 digits.';
                } elseif ($pin !== $confirm) {
                    $errors['owner_pin_confirm'] = 'PINs do not match.';
                }
            }
        } else {
            $current = $in['current_password'] ?? '';
            $newPass = $in['new_password'] ?? '';
            $confirm = $in['new_password_confirm'] ?? '';
            if ($newPass !== '' || $confirm !== '' || $current !== '') {
                if ($current === '' || !password_verify($current, $owner['password_hash'] ?? '')) {
                    $errors['current_password'] = 'Current password is incorrect.';
                } elseif (strlen($newPass) < 8) {
                    $errors['new_password'] = 'New password must be at least 8 characters.';
                } elseif ($newPass !== $confirm) {
                    $errors['new_password_confirm'] = 'Passwords do not match.';
                }
            }
        }

        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        if (!SchemaHelper::columnExists($this->db, 'tenants', 'owner_login_method')) {
            return ['ok' => false, 'errors' => ['_' => 'Run fix-all-schema.php once to enable login settings.']];
        }

        $this->db->prepare('UPDATE tenants SET owner_login_method = ? WHERE id = ?')
            ->execute([$method, $tenantId]);

        if ($method === 'pin' && trim($in['owner_pin'] ?? '') !== '') {
            $pinRes = $this->setOwnerPin($tenantId, (int) $owner['id'], trim($in['owner_pin']));
            if (!$pinRes['ok']) {
                return ['ok' => false, 'errors' => ['owner_pin' => $pinRes['error'] ?? 'Could not save PIN.']];
            }
        }

        if ($method === 'password' && ($in['new_password'] ?? '') !== '') {
            $hash = password_hash($in['new_password'], PASSWORD_DEFAULT);
            $this->db->prepare('UPDATE users SET password_hash = ?, must_reset_password = 0 WHERE id = ? AND tenant_id = ?')
                ->execute([$hash, (int) $owner['id'], $tenantId]);
        }

        return ['ok' => true, 'errors' => []];
    }

    /** @return array{ok:bool,error:?string} */
    public function setOwnerPin(int $tenantId, int $ownerUserId, string $pin): array
    {
        if (!StaffService::validatePinFormat($pin)) {
            return ['ok' => false, 'error' => 'PIN must be 4 or 5 digits.'];
        }
        $staffSvc = new StaffService($this->db);
        if ($this->pinTakenByOther($tenantId, $pin, $ownerUserId)) {
            return ['ok' => false, 'error' => 'That PIN is already used by a staff member. Choose another.'];
        }
        $lookup = StaffService::pinLookup($tenantId, $pin);
        $stmt = $this->db->prepare(
            'UPDATE users SET login_pin_hash = ?, login_pin_lookup = ? WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([
            password_hash($pin, PASSWORD_DEFAULT),
            $lookup,
            $ownerUserId,
            $tenantId,
        ]);
        return ['ok' => true, 'error' => null];
    }

    private function pinTakenByOther(int $tenantId, string $pin, int $exceptUserId): bool
    {
        if (!SchemaHelper::columnExists($this->db, 'users', 'login_pin_lookup')) {
            return false;
        }
        $lookup = StaffService::pinLookup($tenantId, $pin);
        $stmt = $this->db->prepare(
            'SELECT id FROM users WHERE tenant_id = ? AND login_pin_lookup = ? AND id != ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $lookup, $exceptUserId]);
        return (bool) $stmt->fetchColumn();
    }
}
