<?php
// app/services/Schema032Service.php
// Allow pending service invoices without a stylist assigned at check-in (assigned at till).

class Schema032Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (!SchemaHelper::tableExists($db, 'commission_sales')) {
            return ['ok' => true, 'log' => $log];
        }

        try {
            $col = $db->query("SHOW COLUMNS FROM commission_sales LIKE 'agent_user_id'")->fetch();
            if ($col && strtoupper($col['Null'] ?? '') === 'NO') {
                $db->exec('ALTER TABLE commission_sales MODIFY agent_user_id INT NULL');
                $log[] = 'commission_sales.agent_user_id is now nullable (assign at till)';
                SchemaHelper::clearCache();
            }
        } catch (Throwable $e) {
            $log[] = 'agent_user_id nullable: ' . $e->getMessage();
        }

        return ['ok' => true, 'log' => $log];
    }
}
