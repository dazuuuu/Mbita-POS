-- 024_receipts_customers_credits.sql
-- Receipt fields on commission sales, tenant KRA/location, customers, product credits.

ALTER TABLE tenants
    ADD COLUMN kra_pin         VARCHAR(20)  NULL AFTER address,
    ADD COLUMN location        VARCHAR(255) NULL AFTER kra_pin,
    ADD COLUMN credits_enabled TINYINT(1)   NOT NULL DEFAULT 0 AFTER receipt_footer;

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

ALTER TABLE products
    ADD COLUMN credit_allowed TINYINT(1) NOT NULL DEFAULT 0 AFTER commission_value;

ALTER TABLE commission_sales
    ADD COLUMN receipt_number   VARCHAR(32) NULL AFTER tenant_id,
    ADD COLUMN branch_id        INT NULL AFTER agent_user_id,
    ADD COLUMN customer_id      INT NULL AFTER branch_id,
    ADD COLUMN customer_name    VARCHAR(120) NULL AFTER customer_id,
    ADD COLUMN customer_phone   VARCHAR(30) NULL AFTER customer_name,
    ADD COLUMN quantity         DECIMAL(12,2) NOT NULL DEFAULT 1 AFTER item_name,
    ADD COLUMN payment_method   ENUM('cash','mpesa','credit') NOT NULL DEFAULT 'cash' AFTER charged_amount,
    ADD COLUMN is_credit        TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_method;

ALTER TABLE commission_sales
    ADD UNIQUE KEY uq_cs_receipt (tenant_id, receipt_number);

-- Back-fill receipt numbers (run ONLY after columns above exist)
UPDATE commission_sales SET receipt_number = CONCAT('CRS-', LPAD(id, 6, '0')) WHERE receipt_number IS NULL;
