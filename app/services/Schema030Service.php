<?php
// app/services/Schema030Service.php
// Pending payment workflow for service invoices + role capability updates.

class Schema030Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (SchemaHelper::tableExists($db, 'commission_sales')) {
            foreach ([
                'payment_status' => "ALTER TABLE commission_sales ADD COLUMN payment_status ENUM('pending','paid') NOT NULL DEFAULT 'paid' AFTER payment_method",
                'recorded_by_user_id' => 'ALTER TABLE commission_sales ADD COLUMN recorded_by_user_id INT NULL AFTER agent_user_id',
                'paid_at' => 'ALTER TABLE commission_sales ADD COLUMN paid_at DATETIME NULL AFTER payment_status',
            ] as $col => $sql) {
                if (!SchemaHelper::columnExists($db, 'commission_sales', $col)) {
                    try {
                        $db->exec($sql);
                        $log[] = "Added commission_sales.{$col}";
                        SchemaHelper::clearCache();
                    } catch (Throwable $e) {
                        $log[] = "Failed commission_sales.{$col}: " . $e->getMessage();
                    }
                }
            }
        }

        self::updateRoleCaps($db, $log);

        return ['ok' => true, 'log' => $log];
    }

    /** Merge capability updates into existing tenant roles. */
    private static function updateRoleCaps(PDO $db, array &$log): void
    {
        if (!SchemaHelper::tableExists($db, 'roles')) {
            return;
        }

        $updates = [
            'reception' => [
                'appointments.manage', 'invoices.manage', 'customers.manage', 'customers.checkin',
                'commission.record', 'sales.view',
            ],
            'cashier' => ['payments.receive', 'payments.update', 'sales.view'],
            'barber' => ['commission.view'],
        ];

        foreach ($updates as $roleName => $desired) {
            try {
                $stmt = $db->prepare('SELECT id, capabilities FROM roles WHERE role_name = ? LIMIT 1');
                $stmt->execute([$roleName]);
                $row = $stmt->fetch();
                if (!$row) {
                    continue;
                }
                $current = json_decode($row['capabilities'] ?? '[]', true) ?: [];
                $merged = array_values(array_unique(array_merge($current, $desired)));
                if ($merged === $current) {
                    continue;
                }
                $upd = $db->prepare('UPDATE roles SET capabilities = ? WHERE id = ?');
                $upd->execute([json_encode($merged), (int) $row['id']]);
                $log[] = "Updated role {$roleName} capabilities";
            } catch (Throwable $e) {
                $log[] = "Failed role {$roleName}: " . $e->getMessage();
            }
        }
    }
}
