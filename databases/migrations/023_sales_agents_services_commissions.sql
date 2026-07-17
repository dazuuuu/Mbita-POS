-- 023_sales_agents_services_commissions.sql
-- Sales agent role, tenant business services, commission tracking & payouts.

-- 1) Sales agent role
INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'sales_agent' AS role_name, 'tenant' AS scope,
    JSON_ARRAY('commission.record','commission.view')) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'sales_agent');

-- Grant owners the new management capabilities
UPDATE roles SET capabilities = JSON_ARRAY(
    'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
    'customers.manage','catalogue.send','reports.view','branches.manage','staff.manage',
    'settings.manage','billing.manage','services.manage','sales_agent.manage','commission.pay'
) WHERE role_name = 'tenant_owner';

-- 2) Tenant business services (distinct from CMS marketing services)
CREATE TABLE IF NOT EXISTS tenant_services (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_expenses (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    service_id INT NOT NULL,
    name       VARCHAR(160) NOT NULL,
    cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_se_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3) Product commission settings
ALTER TABLE products
    ADD COLUMN commission_type  ENUM('percent','fixed') NOT NULL DEFAULT 'percent' AFTER selling_price,
    ADD COLUMN commission_value DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER commission_type;

-- 4) Commission sale records (by sales agents or staff with commission.record)
CREATE TABLE IF NOT EXISTS commission_sales (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commission_sale_expenses (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    commission_sale_id INT NOT NULL,
    expense_name      VARCHAR(160) NOT NULL,
    cost              DECIMAL(12,2) NOT NULL DEFAULT 0,
    KEY idx_cse_sale (commission_sale_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5) Payouts — when super pays, linked sales are settled and agent balance resets
CREATE TABLE IF NOT EXISTS commission_payouts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
