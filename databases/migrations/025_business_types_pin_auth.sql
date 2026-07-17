-- 025_business_types_pin_auth.sql
-- Business types (barbershop/salon vs retail shop), staff PIN login, and role templates.

ALTER TABLE tenants
    ADD COLUMN business_type ENUM('barbershop_salon','shop') NOT NULL DEFAULT 'shop' AFTER slug;

ALTER TABLE users
    ADD COLUMN staff_type ENUM('cashier','reception','sales','barber','junior_admin','general') NULL AFTER branch_id,
    ADD COLUMN login_pin_hash VARCHAR(255) NULL AFTER must_reset_password,
    ADD COLUMN login_pin_lookup CHAR(64) NULL AFTER login_pin_hash;

-- Deterministic lookup hash so PINs are unique within a tenant.
ALTER TABLE users
    ADD UNIQUE KEY uq_users_tenant_pin (tenant_id, login_pin_lookup);

-- New staff role templates with default capabilities.
INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'cashier' AS role_name, 'tenant' AS scope,
    JSON_ARRAY('payments.receive','payments.update','sales.view') AS capabilities) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'cashier');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'reception', 'tenant',
    JSON_ARRAY('appointments.manage','invoices.manage','customers.manage','customers.checkin') AS capabilities) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'reception');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'sales', 'tenant',
    JSON_ARRAY('sales.record','inventory.view','commission.view') AS capabilities) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'sales');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'barber', 'tenant',
    JSON_ARRAY('commission.record','commission.view') AS capabilities) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'barber');

INSERT INTO roles (role_name, scope, capabilities)
SELECT * FROM (SELECT 'junior_admin', 'tenant',
    JSON_ARRAY('inventory.view','inventory.edit','stock.enter','sales.view','reports.view','payments.receive') AS capabilities) AS t
WHERE NOT EXISTS (SELECT 1 FROM roles r WHERE r.role_name = 'junior_admin');

-- Owner gets services + discounts management for barbershop/salon setups.
UPDATE roles SET capabilities = JSON_ARRAY(
    'inventory.view','inventory.edit','stock.enter','sales.record','sales.view',
    'customers.manage','catalogue.send','reports.view',
    'branches.manage','staff.manage','settings.manage','billing.manage',
    'services.manage','sales_agent.manage','commission.record','commission.view','commission.pay',
    'payments.receive','payments.update','appointments.manage','invoices.manage',
    'customers.checkin','discounts.manage','credits.manage'
) WHERE role_name = 'tenant_owner';
