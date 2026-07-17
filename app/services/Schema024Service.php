<?php
// app/services/Schema024Service.php
// Applies migration 024 columns when missing. Safe to call repeatedly.

class Schema024Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (!SchemaHelper::tenantsTableReady($db)) {
            return ['ok' => false, 'log' => ['tenants table missing']];
        }

        foreach ([
            'kra_pin'         => "ALTER TABLE tenants ADD COLUMN kra_pin VARCHAR(20) NULL",
            'location'        => "ALTER TABLE tenants ADD COLUMN location VARCHAR(255) NULL",
            'credits_enabled' => "ALTER TABLE tenants ADD COLUMN credits_enabled TINYINT(1) NOT NULL DEFAULT 0",
        ] as $col => $sql) {
            if (!self::columnExists($db, 'tenants', $col)) {
                try {
                    $db->exec($sql);
                    $log[] = "Added tenants.{$col}";
                    SchemaHelper::clearCache();
                } catch (Throwable $e) {
                    $log[] = "Failed tenants.{$col}: " . $e->getMessage();
                }
            }
        }

        if (!self::tableExists($db, 'customers')) {
            try {
                $db->exec("CREATE TABLE customers (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id INT NOT NULL,
                    name VARCHAR(120) NOT NULL,
                    phone VARCHAR(30) NULL,
                    email VARCHAR(255) NULL,
                    credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
                    notes TEXT NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    KEY idx_cust_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $log[] = 'Created customers table';
            } catch (Throwable $e) {
                $log[] = 'Failed customers: ' . $e->getMessage();
            }
        }

        if (self::tableExists($db, 'products') && !self::columnExists($db, 'products', 'credit_allowed')) {
            try {
                $db->exec('ALTER TABLE products ADD COLUMN credit_allowed TINYINT(1) NOT NULL DEFAULT 0');
                $log[] = 'Added products.credit_allowed';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed products.credit_allowed: ' . $e->getMessage();
            }
        }

        if (self::tableExists($db, 'commission_sales')) {
            $cs = [
                'receipt_number' => "ALTER TABLE commission_sales ADD COLUMN receipt_number VARCHAR(32) NULL",
                'branch_id'      => "ALTER TABLE commission_sales ADD COLUMN branch_id INT NULL",
                'customer_id'    => "ALTER TABLE commission_sales ADD COLUMN customer_id INT NULL",
                'customer_name'  => "ALTER TABLE commission_sales ADD COLUMN customer_name VARCHAR(120) NULL",
                'customer_phone' => "ALTER TABLE commission_sales ADD COLUMN customer_phone VARCHAR(30) NULL",
                'quantity'       => "ALTER TABLE commission_sales ADD COLUMN quantity DECIMAL(12,2) NOT NULL DEFAULT 1",
                'payment_method' => "ALTER TABLE commission_sales ADD COLUMN payment_method ENUM('cash','mpesa','credit') NOT NULL DEFAULT 'cash'",
                'is_credit'      => "ALTER TABLE commission_sales ADD COLUMN is_credit TINYINT(1) NOT NULL DEFAULT 0",
            ];
            foreach ($cs as $col => $sql) {
                if (!self::columnExists($db, 'commission_sales', $col)) {
                    try {
                        $db->exec($sql);
                        $log[] = "Added commission_sales.{$col}";
                        SchemaHelper::clearCache();
                    } catch (Throwable $e) {
                        $log[] = "Failed commission_sales.{$col}: " . $e->getMessage();
                    }
                }
            }
            if (self::columnExists($db, 'commission_sales', 'receipt_number')) {
                try {
                    $db->exec("UPDATE commission_sales SET receipt_number = CONCAT('CRS-', LPAD(id, 6, '0'))
                               WHERE receipt_number IS NULL OR receipt_number = ''");
                } catch (Throwable $e) { /* ignore */ }
                // Unique index — skip if already exists (duplicate key name error is OK)
                try {
                    $idx = $db->query("SHOW INDEX FROM commission_sales WHERE Key_name = 'uq_cs_receipt'")->fetch();
                    if (!$idx) {
                        $db->exec('ALTER TABLE commission_sales ADD UNIQUE KEY uq_cs_receipt (tenant_id, receipt_number)');
                        $log[] = 'Added commission_sales.uq_cs_receipt index';
                    }
                } catch (Throwable $e) {
                    $log[] = 'Note commission_sales index: ' . $e->getMessage();
                }
            }
        }

        SchemaHelper::clearCache();
        return ['ok' => SchemaHelper::migration024Ready($db), 'log' => $log];
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        return SchemaHelper::tableExists($db, $table);
    }

    private static function columnExists(PDO $db, string $table, string $column): bool
    {
        return SchemaHelper::columnExists($db, $table, $column);
    }
}
