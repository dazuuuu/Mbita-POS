<?php
// app/services/Schema023Service.php
// Applies migration 023 tables/columns when missing. Safe to call repeatedly.

class Schema023Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        self::ensureSalesAgentRole($db, $log);
        self::ensureTenantServices($db, $log);
        self::ensureProductCommissionColumns($db, $log);
        self::ensureCommissionSales($db, $log);
        self::ensureCommissionSaleExpenses($db, $log);
        self::ensureCommissionPayouts($db, $log);

        return ['ok' => SchemaHelper::migration023Ready($db), 'log' => $log];
    }

    private static function ensureSalesAgentRole(PDO $db, array &$log): void
    {
        if (!SchemaHelper::tableExists($db, 'roles')) {
            return;
        }
        try {
            $stmt = $db->query("SELECT 1 FROM roles WHERE role_name = 'sales_agent' LIMIT 1");
            if (!$stmt->fetchColumn()) {
                $db->exec(
                    "INSERT INTO roles (role_name, scope, capabilities)
                     VALUES ('sales_agent', 'tenant', JSON_ARRAY('commission.record','commission.view'))"
                );
                $log[] = 'Added sales_agent role';
            }
        } catch (Throwable $e) {
            $log[] = 'Failed sales_agent role: ' . $e->getMessage();
        }
    }

    private static function ensureTenantServices(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'tenant_services')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS tenant_services (
                id               INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id        INT NOT NULL,
                name             VARCHAR(160) NOT NULL,
                description      TEXT NULL,
                charge_amount    DECIMAL(12,2) NOT NULL DEFAULT 0,
                commission_type  ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
                commission_value DECIMAL(12,2) NOT NULL DEFAULT 0,
                status           ENUM('active','draft') NOT NULL DEFAULT 'active',
                created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_ts_tenant (tenant_id),
                UNIQUE KEY uq_ts_tenant_name (tenant_id, name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created tenant_services table (if not exists)';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'tenant_services table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed tenant_services: ' . $e->getMessage();
            }
        }

        if (!SchemaHelper::tableExists($db, 'service_expenses')) {
            try {
                $db->exec("CREATE TABLE IF NOT EXISTS service_expenses (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    service_id INT NOT NULL,
                    name       VARCHAR(160) NOT NULL,
                    cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_se_service (service_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $log[] = 'Created service_expenses table (if not exists)';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                if (SchemaHelper::isDuplicateSchemaError($e)) {
                    $log[] = 'service_expenses table already exists';
                    SchemaHelper::clearCache();
                } else {
                    $log[] = 'Failed service_expenses: ' . $e->getMessage();
                }
            }
        }
    }

    private static function ensureProductCommissionColumns(PDO $db, array &$log): void
    {
        if (!SchemaHelper::tableExists($db, 'products') && !SchemaHelper::columnExists($db, 'products', 'id')) {
            return;
        }
        foreach ([
            'commission_type'  => "ALTER TABLE products ADD COLUMN commission_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent'",
            'commission_value' => 'ALTER TABLE products ADD COLUMN commission_value DECIMAL(12,2) NOT NULL DEFAULT 0',
        ] as $col => $sql) {
            if (!SchemaHelper::columnExists($db, 'products', $col)) {
                try {
                    $db->exec($sql);
                    $log[] = "Added products.{$col}";
                    SchemaHelper::clearCache();
                } catch (Throwable $e) {
                    if (SchemaHelper::isDuplicateSchemaError($e)) {
                        $log[] = "products.{$col} already exists";
                        SchemaHelper::clearCache();
                    } else {
                        $log[] = "Failed products.{$col}: " . $e->getMessage();
                    }
                }
            }
        }
    }

    private static function ensureCommissionSales(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'commission_sales')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS commission_sales (
                id                  INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id           INT NOT NULL,
                agent_user_id       INT NOT NULL,
                item_type           ENUM('service','product') NOT NULL,
                service_id          INT NULL,
                product_id          INT NULL,
                item_name           VARCHAR(160) NOT NULL,
                standard_price      DECIMAL(12,2) NOT NULL DEFAULT 0,
                charged_amount      DECIMAL(12,2) NOT NULL DEFAULT 0,
                expense_total       DECIMAL(12,2) NOT NULL DEFAULT 0,
                base_commission     DECIMAL(12,2) NOT NULL DEFAULT 0,
                overage_commission  DECIMAL(12,2) NOT NULL DEFAULT 0,
                total_commission    DECIMAL(12,2) NOT NULL DEFAULT 0,
                notes               TEXT NULL,
                payout_id           INT NULL,
                created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cs_tenant (tenant_id),
                KEY idx_cs_agent (agent_user_id),
                KEY idx_cs_payout (payout_id),
                KEY idx_cs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created commission_sales table (if not exists)';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'commission_sales table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed commission_sales: ' . $e->getMessage();
            }
        }
    }

    private static function ensureCommissionSaleExpenses(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'commission_sale_expenses')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS commission_sale_expenses (
                id                 INT AUTO_INCREMENT PRIMARY KEY,
                commission_sale_id INT NOT NULL,
                expense_name       VARCHAR(160) NOT NULL,
                cost               DECIMAL(12,2) NOT NULL DEFAULT 0,
                KEY idx_cse_sale (commission_sale_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created commission_sale_expenses table (if not exists)';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'commission_sale_expenses table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed commission_sale_expenses: ' . $e->getMessage();
            }
        }
    }

    private static function ensureCommissionPayouts(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'commission_payouts')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS commission_payouts (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id     INT NOT NULL,
                agent_user_id INT NOT NULL,
                amount_paid   DECIMAL(12,2) NOT NULL DEFAULT 0,
                sales_count   INT NOT NULL DEFAULT 0,
                paid_by       INT NOT NULL,
                paid_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes         TEXT NULL,
                KEY idx_cp_tenant (tenant_id),
                KEY idx_cp_agent (agent_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created commission_payouts table (if not exists)';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'commission_payouts table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed commission_payouts: ' . $e->getMessage();
            }
        }
    }
}
