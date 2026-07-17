<?php
// app/services/GeneralMigrationService.php
// One-shot schema repair — safe to run repeatedly. Use fix-all-schema.php in the browser.

class GeneralMigrationService
{
    /** @return array{ok:bool,log:array<int,string>,status:array<string,mixed>} */
    public static function ensureApplied(PDO $db): array
    {
        SchemaHelper::clearCache();
        $log = [];

        $steps = [
            '015 tenant branding' => fn () => self::ensure015TenantBranding($db, $log),
            '024 customers'       => fn () => self::ensureCustomers($db, $log),
            '020 inventory'       => fn () => Schema020Service::ensureApplied($db),
            '022 sales'           => fn () => self::ensure022Sales($db, $log),
            '023 commissions'   => fn () => Schema023Service::ensureApplied($db),
            '024 receipts'        => fn () => Schema024Service::ensureApplied($db),
            '025 pin auth'        => fn () => Schema025Service::ensureApplied($db),
            '026 modules'         => fn () => Schema026Service::ensureApplied($db),
            '027 wholesale'       => fn () => self::ensure027Wholesale($db, $log),
            '028 owner login'     => fn () => Schema028Service::ensureApplied($db),
            '029 branch scope'    => fn () => Schema029Service::ensureApplied($db),
            '030 payments'        => fn () => Schema030Service::ensureApplied($db),
        ];

        foreach ($steps as $label => $fn) {
            SchemaHelper::clearCache();
            $result = $fn();
            if (is_array($result) && isset($result['log'])) {
                foreach ($result['log'] as $line) {
                    $log[] = "[{$label}] {$line}";
                }
            }
        }

        SchemaHelper::clearCache();
        $status = self::statusReport($db);

        return [
            'ok'     => $status['all_ok'],
            'log'    => $log,
            'status' => $status,
        ];
    }

    /** @return array{all_ok:bool,checks:array<int,array{label:string,ok:bool}>} */
    public static function statusReport(PDO $db): array
    {
        SchemaHelper::clearCache();

        $checks = [
            ['label' => 'tenants.kra_pin', 'ok' => SchemaHelper::columnExists($db, 'tenants', 'kra_pin')],
            ['label' => 'tenants.credits_enabled', 'ok' => SchemaHelper::columnExists($db, 'tenants', 'credits_enabled')],
            ['label' => 'tenants.business_type', 'ok' => SchemaHelper::columnExists($db, 'tenants', 'business_type')],
            ['label' => 'tenants.modules', 'ok' => SchemaHelper::columnExists($db, 'tenants', 'modules')],
            ['label' => 'table customers', 'ok' => SchemaHelper::tableExists($db, 'customers') || SchemaHelper::columnExists($db, 'customers', 'id')],
            ['label' => 'table categories', 'ok' => SchemaHelper::tableExists($db, 'categories') || SchemaHelper::columnExists($db, 'categories', 'id')],
            ['label' => 'products.tenant_id', 'ok' => SchemaHelper::columnExists($db, 'products', 'tenant_id')],
            ['label' => 'products.selling_price', 'ok' => SchemaHelper::columnExists($db, 'products', 'selling_price')],
            ['label' => 'users.login_pin_hash', 'ok' => SchemaHelper::columnExists($db, 'users', 'login_pin_hash')],
            ['label' => 'branches.branch_type', 'ok' => !SchemaHelper::tableExists($db, 'branches') || SchemaHelper::columnExists($db, 'branches', 'branch_type')],
            ['label' => 'branches.modules', 'ok' => !SchemaHelper::tableExists($db, 'branches') || SchemaHelper::columnExists($db, 'branches', 'modules')],
            ['label' => 'tenants.owner_login_method', 'ok' => !SchemaHelper::tableExists($db, 'tenants') || SchemaHelper::columnExists($db, 'tenants', 'owner_login_method')],
            ['label' => 'products.branch_id', 'ok' => !SchemaHelper::tableExists($db, 'products') || SchemaHelper::columnExists($db, 'products', 'branch_id')],
            ['label' => 'tenant_services.branch_id', 'ok' => !SchemaHelper::tableExists($db, 'tenant_services') || SchemaHelper::columnExists($db, 'tenant_services', 'branch_id')],
        ];

        if (SchemaHelper::tableExists($db, 'commission_sales')) {
            $checks[] = ['label' => 'commission_sales.receipt_number', 'ok' => SchemaHelper::columnExists($db, 'commission_sales', 'receipt_number')];
            $checks[] = ['label' => 'commission_sales.payment_status', 'ok' => SchemaHelper::columnExists($db, 'commission_sales', 'payment_status')];
        }

        $allOk = true;
        foreach ($checks as $c) {
            if (!$c['ok']) {
                $allOk = false;
            }
        }

        return ['all_ok' => $allOk, 'checks' => $checks];
    }

    private static function ensureCustomers(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'customers')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS customers (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id      INT NOT NULL,
                name           VARCHAR(120) NOT NULL,
                phone          VARCHAR(30) NULL,
                email          VARCHAR(255) NULL,
                credit_balance DECIMAL(12,2) NOT NULL DEFAULT 0,
                notes          TEXT NULL,
                created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_cust_tenant (tenant_id),
                KEY idx_cust_phone (tenant_id, phone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created customers table';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'customers table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed customers: ' . $e->getMessage();
            }
        }
    }

    private static function ensure015TenantBranding(PDO $db, array &$log): void
    {
        if (!SchemaHelper::tenantsTableReady($db)) {
            $log[] = 'tenants table missing — run migration 013 first';
            return;
        }
        foreach ([
            'logo_path'      => 'ALTER TABLE tenants ADD COLUMN logo_path VARCHAR(255) NULL',
            'currency'       => "ALTER TABLE tenants ADD COLUMN currency VARCHAR(8) NOT NULL DEFAULT 'KES'",
            'phone'          => 'ALTER TABLE tenants ADD COLUMN phone VARCHAR(30) NULL',
            'email'          => 'ALTER TABLE tenants ADD COLUMN email VARCHAR(255) NULL',
            'address'        => 'ALTER TABLE tenants ADD COLUMN address VARCHAR(255) NULL',
            'receipt_footer' => 'ALTER TABLE tenants ADD COLUMN receipt_footer VARCHAR(255) NULL',
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

    private static function ensure022Sales(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'sales')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE sales (
                id              INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id       INT NOT NULL,
                branch_id       INT NULL,
                staff_id        INT NOT NULL,
                receipt_number  VARCHAR(32) NOT NULL,
                payment_method  ENUM('cash','mpesa','card','credit') NOT NULL DEFAULT 'cash',
                total           DECIMAL(12,2) NOT NULL DEFAULT 0,
                amount_given    DECIMAL(12,2) NULL,
                change_given    DECIMAL(12,2) NULL,
                customer_name   VARCHAR(120) NULL,
                customer_phone  VARCHAR(30) NULL,
                customer_email  VARCHAR(255) NULL,
                status          ENUM('completed','void') NOT NULL DEFAULT 'completed',
                created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_sales_tenant (tenant_id),
                KEY idx_sales_staff (staff_id),
                KEY idx_sales_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created sales table';
        } catch (Throwable $e) {
            $log[] = 'Failed sales: ' . $e->getMessage();
        }

        if (!SchemaHelper::tableExists($db, 'sale_items')) {
            try {
                $db->exec("CREATE TABLE sale_items (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    tenant_id    INT NOT NULL,
                    sale_id      INT NOT NULL,
                    product_id   INT NULL,
                    product_name VARCHAR(160) NOT NULL,
                    unit         VARCHAR(20) NOT NULL DEFAULT 'piece',
                    unit_price   DECIMAL(12,2) NOT NULL DEFAULT 0,
                    quantity     DECIMAL(12,2) NOT NULL DEFAULT 1,
                    line_total   DECIMAL(12,2) NOT NULL DEFAULT 0,
                    KEY idx_si_sale (sale_id),
                    KEY idx_si_tenant (tenant_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $log[] = 'Created sale_items table';
            } catch (Throwable $e) {
                $log[] = 'Failed sale_items: ' . $e->getMessage();
            }
        }
    }

    private static function ensure027Wholesale(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'products')
            && !SchemaHelper::columnExists($db, 'products', 'wholesale_price')) {
            try {
                $db->exec('ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(12,2) NULL');
                $log[] = 'Added products.wholesale_price';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed products.wholesale_price: ' . $e->getMessage();
            }
        }
    }
}
