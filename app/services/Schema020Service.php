<?php
// app/services/Schema020Service.php
// Ensures inventory tables (categories, subcategories, products) exist with tenant_id.
// Safe to call repeatedly — fixes old CMS products table from migration 007.

class Schema020Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        self::ensureCategories($db, $log);
        self::ensureSubcategories($db, $log);
        self::ensureProducts($db, $log);

        $ok = SchemaHelper::tableExists($db, 'products')
            && SchemaHelper::columnExists($db, 'products', 'tenant_id')
            && SchemaHelper::columnExists($db, 'products', 'selling_price');

        return ['ok' => $ok, 'log' => $log];
    }

    private static function ensureCategories(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'categories')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS categories (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id  INT NOT NULL,
                name       VARCHAR(120) NOT NULL,
                status     ENUM('active','draft') NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cat_tenant_name (tenant_id, name),
                KEY idx_cat_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created categories table (if not exists)';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'categories table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed categories: ' . $e->getMessage();
            }
        }
    }

    private static function ensureSubcategories(PDO $db, array &$log): void
    {
        if (SchemaHelper::tableExists($db, 'subcategories')) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS subcategories (
                id          INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id   INT NOT NULL,
                category_id INT NOT NULL,
                name        VARCHAR(120) NOT NULL,
                status      ENUM('active','draft') NOT NULL DEFAULT 'active',
                created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_subcat_tenant_cat_name (tenant_id, category_id, name),
                KEY idx_subcat_tenant (tenant_id),
                KEY idx_subcat_cat (category_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created subcategories table (if not exists)';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'subcategories table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed subcategories: ' . $e->getMessage();
            }
        }
    }

    private static function ensureProducts(PDO $db, array &$log): void
    {
        if (!SchemaHelper::tableExists($db, 'products')) {
            self::createInventoryProductsTable($db, $log);
            return;
        }

        if (SchemaHelper::columnExists($db, 'products', 'tenant_id')
            && SchemaHelper::columnExists($db, 'products', 'selling_price')) {
            self::addOptionalProductColumns($db, $log);
            return;
        }

        // Old CMS products table (migration 007) blocks migration 020 via IF NOT EXISTS.
        if (SchemaHelper::columnExists($db, 'products', 'price')
            && !SchemaHelper::columnExists($db, 'products', 'selling_price')) {
            try {
                if (!SchemaHelper::tableExists($db, 'legacy_cms_products')) {
                    $db->exec('RENAME TABLE products TO legacy_cms_products');
                    $log[] = 'Renamed old CMS products → legacy_cms_products';
                }
                self::createInventoryProductsTable($db, $log);
            } catch (Throwable $e) {
                $log[] = 'Failed to replace old products table: ' . $e->getMessage();
            }
            return;
        }

        if (!SchemaHelper::columnExists($db, 'products', 'tenant_id')) {
            try {
                $defaultTenant = (int) ($db->query('SELECT id FROM tenants ORDER BY id ASC LIMIT 1')->fetchColumn() ?: 1);
                $db->exec("ALTER TABLE products ADD COLUMN tenant_id INT NOT NULL DEFAULT {$defaultTenant} AFTER id");
                $log[] = 'Added products.tenant_id';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed products.tenant_id: ' . $e->getMessage();
            }
        }

        self::addOptionalProductColumns($db, $log);
    }

    private static function createInventoryProductsTable(PDO $db, array &$log): void
    {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS products (
                id                    INT AUTO_INCREMENT PRIMARY KEY,
                tenant_id             INT NOT NULL,
                category_id           INT NULL,
                subcategory_id        INT NULL,
                name                  VARCHAR(160) NOT NULL,
                description           TEXT NULL,
                quantity              DECIMAL(12,2) NOT NULL DEFAULT 0,
                unit                  VARCHAR(20) NOT NULL DEFAULT 'piece',
                buying_price          DECIMAL(12,2) NOT NULL DEFAULT 0,
                selling_price         DECIMAL(12,2) NOT NULL DEFAULT 0,
                commission_type       ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
                commission_value      DECIMAL(12,2) NOT NULL DEFAULT 0,
                credit_allowed        TINYINT(1) NOT NULL DEFAULT 0,
                colors                JSON NULL,
                sizes                 JSON NULL,
                image_path            VARCHAR(255) NULL,
                low_stock_threshold   INT NOT NULL DEFAULT 10,
                low_stock_notified_at DATETIME NULL,
                status                ENUM('active','draft') NOT NULL DEFAULT 'active',
                created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_prod_tenant (tenant_id),
                KEY idx_prod_cat (category_id),
                KEY idx_prod_subcat (subcategory_id),
                KEY idx_prod_status (status),
                KEY idx_prod_lowstock (tenant_id, quantity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $log[] = 'Created inventory products table (if not exists)';
            SchemaHelper::clearCache();
        } catch (Throwable $e) {
            if (SchemaHelper::isDuplicateSchemaError($e)) {
                $log[] = 'products table already exists';
                SchemaHelper::clearCache();
            } else {
                $log[] = 'Failed create products: ' . $e->getMessage();
            }
        }
    }

    private static function addOptionalProductColumns(PDO $db, array &$log): void
    {
        $cols = [
            'commission_type'  => "ALTER TABLE products ADD COLUMN commission_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent' AFTER selling_price",
            'commission_value' => 'ALTER TABLE products ADD COLUMN commission_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER commission_type',
            'credit_allowed'   => 'ALTER TABLE products ADD COLUMN credit_allowed TINYINT(1) NOT NULL DEFAULT 0',
            'colors'           => 'ALTER TABLE products ADD COLUMN colors JSON NULL',
            'sizes'            => 'ALTER TABLE products ADD COLUMN sizes JSON NULL',
            'image_path'       => 'ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL',
            'low_stock_threshold' => 'ALTER TABLE products ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 10',
            'unit'             => "ALTER TABLE products ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'piece'",
            'buying_price'     => 'ALTER TABLE products ADD COLUMN buying_price DECIMAL(12,2) NOT NULL DEFAULT 0',
            'selling_price'    => 'ALTER TABLE products ADD COLUMN selling_price DECIMAL(12,2) NOT NULL DEFAULT 0',
            'wholesale_price'  => 'ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(12,2) NULL',
            'quantity'         => 'ALTER TABLE products ADD COLUMN quantity DECIMAL(12,2) NOT NULL DEFAULT 0',
        ];
        foreach ($cols as $col => $sql) {
            if (!SchemaHelper::columnExists($db, 'products', $col)) {
                try {
                    $db->exec($sql);
                    $log[] = "Added products.{$col}";
                    SchemaHelper::clearCache();
                } catch (Throwable $e) {
                    $log[] = "Failed products.{$col}: " . $e->getMessage();
                }
            }
        }
        if (SchemaHelper::columnExists($db, 'products', 'category_id')) {
            try {
                $db->exec('ALTER TABLE products MODIFY category_id INT NULL');
            } catch (Throwable $e) {
                // optional
            }
        }
        if (SchemaHelper::columnExists($db, 'products', 'price')
            && SchemaHelper::columnExists($db, 'products', 'selling_price')) {
            try {
                $db->exec('UPDATE products SET selling_price = price WHERE selling_price = 0 AND price > 0');
                $log[] = 'Copied legacy price → selling_price where needed';
            } catch (Throwable $e) {
                // optional
            }
        }
    }
}
