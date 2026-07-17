-- 026_tenant_modules_branch_types.sql
-- Modular feature toggles per tenant + branch business types (shop, barbershop, salon, both).

ALTER TABLE tenants
    ADD COLUMN modules JSON NULL AFTER business_type;

ALTER TABLE branches
    ADD COLUMN branch_type ENUM('shop','barbershop','salon','barbershop_salon') NOT NULL DEFAULT 'shop' AFTER location;
