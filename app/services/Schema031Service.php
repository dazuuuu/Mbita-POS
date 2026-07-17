<?php
// app/services/Schema031Service.php
// Appointments, service credits, customer credit limits.

class Schema031Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (SchemaHelper::tableExists($db, 'tenants')) {
            foreach ([
                'service_credits_enabled' => "ALTER TABLE tenants ADD COLUMN service_credits_enabled TINYINT(1) NOT NULL DEFAULT 0",
                'default_credit_days'     => "ALTER TABLE tenants ADD COLUMN default_credit_days INT NOT NULL DEFAULT 30",
                'default_credit_limit'    => "ALTER TABLE tenants ADD COLUMN default_credit_limit DECIMAL(12,2) NOT NULL DEFAULT 5000",
            ] as $col => $sql) {
                if (!SchemaHelper::columnExists($db, 'tenants', $col)) {
                    try {
                        $db->exec($sql);
                        $log[] = "Added tenants.{$col}";
                        SchemaHelper::clearCache();
                    } catch (Throwable $e) {
                        $log[] = "Failed tenants.{$col}: " . $e->getMessage();
                    }
                }
            }
        }

        if (SchemaHelper::tableExists($db, 'customers')) {
            foreach ([
                'credit_limit' => 'ALTER TABLE customers ADD COLUMN credit_limit DECIMAL(12,2) NULL',
            ] as $col => $sql) {
                if (!SchemaHelper::columnExists($db, 'customers', $col)) {
                    try {
                        $db->exec($sql);
                        $log[] = "Added customers.{$col}";
                        SchemaHelper::clearCache();
                    } catch (Throwable $e) {
                        $log[] = "Failed customers.{$col}: " . $e->getMessage();
                    }
                }
            }
        }

        if (SchemaHelper::tableExists($db, 'commission_sales')
            && !SchemaHelper::columnExists($db, 'commission_sales', 'credit_due_at')) {
            try {
                $db->exec('ALTER TABLE commission_sales ADD COLUMN credit_due_at DATETIME NULL');
                $log[] = 'Added commission_sales.credit_due_at';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed commission_sales.credit_due_at: ' . $e->getMessage();
            }
        }

        if (!SchemaHelper::tableExists($db, 'appointments')) {
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS appointments (
                    id              INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id       INT NOT NULL,
                    branch_id       INT NULL,
                    customer_id     INT NULL,
                    customer_name   VARCHAR(120) NOT NULL,
                    customer_phone  VARCHAR(30) NULL,
                    service_id      INT NULL,
                    service_name    VARCHAR(160) NULL,
                    agent_user_id   INT NULL,
                    scheduled_at    DATETIME NOT NULL,
                    status          ENUM('scheduled','checked_in','cancelled','no_show') NOT NULL DEFAULT 'scheduled',
                    notes           TEXT NULL,
                    created_by      INT NULL,
                    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_appt_tenant (tenant_id),
                    KEY idx_appt_sched (tenant_id, scheduled_at),
                    KEY idx_appt_status (tenant_id, status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $log[] = 'Created appointments table';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed appointments: ' . $e->getMessage();
            }
        }

        return ['ok' => true, 'log' => $log];
    }
}
