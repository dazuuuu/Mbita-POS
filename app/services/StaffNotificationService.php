<?php
// app/services/StaffNotificationService.php
// Owner broadcast notifications to all staff (Late, Memo, Notice templates).

class StaffNotificationService
{
    public static function templates(): array
    {
        return [
            'late' => [
                'label' => 'Late',
                'title' => 'Late arrival',
                'body'  => "You checked in after 8:00 AM today.\n\nPlease see reception or your manager before starting work.",
            ],
            'memo' => [
                'label' => 'Memo',
                'title' => 'Team memo',
                'body'  => "Please read the following memo from management:\n\n[Write your message here]",
            ],
            'notice' => [
                'label' => 'Notice',
                'title' => 'Important notice',
                'body'  => "Notice to all staff:\n\n[Write your notice here]",
            ],
        ];
    }

    public static function ensureSchema(PDO $db): void
    {
        Schema033Service::ensureApplied($db);
    }

    public function create(int $tenantId, int $ownerUserId, string $templateType, string $title, string $body): array
    {
        self::ensureSchema($this->db);

        $templates = self::templates();
        if (!isset($templates[$templateType])) {
            return ['ok' => false, 'error' => 'Choose a valid template.'];
        }
        $title = trim($title);
        $body = trim($body);
        if ($title === '' || $body === '') {
            return ['ok' => false, 'error' => 'Title and message are required.'];
        }

        $stmt = $this->db->prepare(
            'INSERT INTO staff_notifications (tenant_id, created_by_user_id, template_type, title, body)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $ownerUserId, $templateType, $title, $body]);

        return ['ok' => true, 'id' => (int) $this->db->lastInsertId()];
    }

    /** Latest notification this staff member has not dismissed. */
    public function pendingForUser(int $tenantId, int $userId): ?array
    {
        self::ensureSchema($this->db);

        $stmt = $this->db->prepare(
            'SELECT n.*
               FROM staff_notifications n
              WHERE n.tenant_id = ?
                AND NOT EXISTS (
                    SELECT 1 FROM staff_notification_reads r
                     WHERE r.notification_id = n.id AND r.user_id = ?
                )
              ORDER BY n.created_at DESC
              LIMIT 1'
        );
        $stmt->execute([$tenantId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        self::ensureSchema($this->db);

        $stmt = $this->db->prepare(
            'INSERT INTO staff_notification_reads (notification_id, user_id)
             VALUES (?, ?)
             ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP'
        );
        $stmt->execute([$notificationId, $userId]);
        return true;
    }

    /** @return array<int,array<string,mixed>> */
    public function listForTenant(int $tenantId, int $limit = 50): array
    {
        self::ensureSchema($this->db);

        $stmt = $this->db->prepare(
            'SELECT n.*, u.username AS sent_by,
                    (SELECT COUNT(*) FROM staff_notification_reads r WHERE r.notification_id = n.id) AS read_count
               FROM staff_notifications n
               JOIN users u ON u.id = n.created_by_user_id
              WHERE n.tenant_id = ?
              ORDER BY n.created_at DESC
              LIMIT ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function activeStaffCount(int $tenantId): int
    {
        $roles = StaffRoles::employeeRoleNames();
        $roles[] = 'sales_agent';
        $ph = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.tenant_id = ? AND u.is_active = 1 AND r.role_name IN ({$ph})"
        );
        $stmt->execute(array_merge([$tenantId], $roles));
        return (int) $stmt->fetchColumn();
    }

    public function __construct(private PDO $db) {}
}
