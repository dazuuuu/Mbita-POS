-- =============================================================================
-- general_migration.sql — Mbita POS schema (safe to re-run)
-- =============================================================================
-- HOW TO USE (MySQL Workbench):
--   1. Select your database (e.g. dynamic_db)
--   2. Run this whole file OR section by section
--   3. "Duplicate column name" / "Duplicate key name" = already applied, SKIP
--
-- EASIER: open http://localhost:8000/devs/fix-all-schema.php in the browser
--         (checks each column before adding — no duplicate errors)
-- =============================================================================

-- -----------------------------------------------------------------------------
-- 015 — Tenant branding (needed before 024 credits_enabled)
-- -----------------------------------------------------------------------------
ALTER TABLE tenants ADD COLUMN logo_path VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN currency VARCHAR(8) NOT NULL DEFAULT 'KES';
ALTER TABLE tenants ADD COLUMN phone VARCHAR(30) NULL;
ALTER TABLE tenants ADD COLUMN email VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN address VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN receipt_footer VARCHAR(255) NULL;

-- -----------------------------------------------------------------------------
-- 024 — KRA, credits, customers (ONE COLUMN PER LINE — safe on re-run)
-- -----------------------------------------------------------------------------
ALTER TABLE tenants ADD COLUMN kra_pin VARCHAR(20) NULL;
ALTER TABLE tenants ADD COLUMN location VARCHAR(255) NULL;
ALTER TABLE tenants ADD COLUMN credits_enabled TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS customers (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE products ADD COLUMN credit_allowed TINYINT(1) NOT NULL DEFAULT 0;

-- commission_sales (only if table exists — skip errors if table missing)
ALTER TABLE commission_sales ADD COLUMN receipt_number VARCHAR(32) NULL;
ALTER TABLE commission_sales ADD COLUMN branch_id INT NULL;
ALTER TABLE commission_sales ADD COLUMN customer_id INT NULL;
ALTER TABLE commission_sales ADD COLUMN customer_name VARCHAR(120) NULL;
ALTER TABLE commission_sales ADD COLUMN customer_phone VARCHAR(30) NULL;
ALTER TABLE commission_sales ADD COLUMN quantity DECIMAL(12,2) NOT NULL DEFAULT 1;
ALTER TABLE commission_sales ADD COLUMN payment_method ENUM('cash','mpesa','credit') NOT NULL DEFAULT 'cash';
ALTER TABLE commission_sales ADD COLUMN is_credit TINYINT(1) NOT NULL DEFAULT 0;

UPDATE commission_sales SET receipt_number = CONCAT('CRS-', LPAD(id, 6, '0'))
 WHERE receipt_number IS NULL OR receipt_number = '';

-- Skip if index already exists:
-- ALTER TABLE commission_sales ADD UNIQUE KEY uq_cs_receipt (tenant_id, receipt_number);

-- -----------------------------------------------------------------------------
-- 020 — Inventory (categories, subcategories, products with tenant_id)
-- Run fix-schema-020.php if products table is the OLD CMS version
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS categories (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id  INT NOT NULL,
    name       VARCHAR(120) NOT NULL,
    status     ENUM('active','draft') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_cat_tenant_name (tenant_id, name),
    KEY idx_cat_tenant (tenant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subcategories (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If products lacks tenant_id, add it (ignore duplicate error):
ALTER TABLE products ADD COLUMN tenant_id INT NOT NULL DEFAULT 1;
ALTER TABLE products ADD COLUMN selling_price DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN buying_price DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN quantity DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN unit VARCHAR(20) NOT NULL DEFAULT 'piece';
ALTER TABLE products ADD COLUMN commission_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent';
ALTER TABLE products ADD COLUMN commission_value DECIMAL(12,2) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(12,2) NULL;

-- -----------------------------------------------------------------------------
-- 025 — Business types + PIN login
-- -----------------------------------------------------------------------------
ALTER TABLE tenants ADD COLUMN business_type ENUM('barbershop_salon','shop') NOT NULL DEFAULT 'shop';

ALTER TABLE users ADD COLUMN staff_type ENUM('cashier','reception','sales','barber','junior_admin','general') NULL;
ALTER TABLE users ADD COLUMN login_pin_hash VARCHAR(255) NULL;
ALTER TABLE users ADD COLUMN login_pin_lookup CHAR(64) NULL;

-- -----------------------------------------------------------------------------
-- 026 / 027 — Modules + branch types + wholesale
-- -----------------------------------------------------------------------------
ALTER TABLE tenants ADD COLUMN modules JSON NULL;
ALTER TABLE branches ADD COLUMN branch_type ENUM('shop','barbershop','salon','barbershop_salon') NOT NULL DEFAULT 'shop';
ALTER TABLE branches ADD COLUMN modules JSON NULL;

-- -----------------------------------------------------------------------------
-- VERIFY (run these SELECTs — all should return 1 row each)
-- -----------------------------------------------------------------------------
-- SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='kra_pin';
-- SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenants' AND COLUMN_NAME='credits_enabled';
-- SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='customers';
