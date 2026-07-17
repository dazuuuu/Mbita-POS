-- 027_branch_modules_wholesale.sql
ALTER TABLE branches ADD COLUMN modules JSON NULL AFTER branch_type;
ALTER TABLE products ADD COLUMN wholesale_price DECIMAL(12,2) NULL AFTER selling_price;
