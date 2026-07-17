<?php
// app/services/StaffAttendanceService.php
// Track staff PIN login/logout. Late = after 8:00 AM. Early leave = before 3:00 PM.

class StaffAttendanceService
{
    public const LATE_AFTER   = '08:00:00';
    public const EARLY_BEFORE = '15:00:00';

    public static function ensureSchema(PDO $db): void
    {
        Schema033Service::ensureApplied($db);
    }

    public static function shouldTrack(?string $role): bool
    {
        if ($role === 'sales_agent') {
            return true;
        }
        return StaffRoles::isEmployeeRole($role);
    }

    public static function recordLogin(PDO $db, int $tenantId, int $userId): void
    {
        if (!SchemaHelper::tableExists($db, 'staff_attendance')) {
            return;
        }

        $now = new DateTime();
        $time = $now->format('H:i:s');
        $loginStatus = ($time > self::LATE_AFTER) ? 'late' : 'on_time';

        $stmt = $db->prepare(
            'INSERT INTO staff_attendance (tenant_id, user_id, session_date, login_at, login_status, logout_status)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $tenantId,
            $userId,
            $now->format('Y-m-d'),
            $now->format('Y-m-d H:i:s'),
            $loginStatus,
            'open',
        ]);
    }

    public static function recordLogout(PDO $db, int $tenantId, int $userId): void
    {
        if (!SchemaHelper::tableExists($db, 'staff_attendance')) {
            return;
        }

        $stmt = $db->prepare(
            'SELECT id, login_at FROM staff_attendance
              WHERE tenant_id = ? AND user_id = ? AND logout_at IS NULL
              ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return;
        }

        $now = new DateTime();
        $time = $now->format('H:i:s');
        $logoutStatus = ($time < self::EARLY_BEFORE) ? 'early' : 'on_time';

        $upd = $db->prepare(
            'UPDATE staff_attendance SET logout_at = ?, logout_status = ? WHERE id = ? AND tenant_id = ?'
        );
        $upd->execute([$now->format('Y-m-d H:i:s'), $logoutStatus, (int) $row['id'], $tenantId]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listForTenant(int $tenantId, ?string $fromDate = null, ?string $toDate = null, ?int $userId = null): array
    {
        self::ensureSchema($this->db);

        $sql = "SELECT a.*, u.username, u.staff_type, b.title AS branch_title
                  FROM staff_attendance a
                  JOIN users u ON u.id = a.user_id
             LEFT JOIN branches b ON b.id = u.branch_id
                 WHERE a.tenant_id = ?";
        $params = [$tenantId];

        if ($fromDate) {
            $sql .= ' AND a.session_date >= ?';
            $params[] = $fromDate;
        }
        if ($toDate) {
            $sql .= ' AND a.session_date <= ?';
            $params[] = $toDate;
        }
        if ($userId) {
            $sql .= ' AND a.user_id = ?';
            $params[] = $userId;
        }

        $sql .= ' ORDER BY a.login_at DESC LIMIT 500';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function __construct(private PDO $db) {}
}
