<?php
// app/services/Schema029Service.php
// Branch-scoped products and services.

class Schema029Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (SchemaHelper::tableExists($db, 'products')
            && !SchemaHelper::columnExists($db, 'products', 'branch_id')) {
            try {
                $db->exec('ALTER TABLE products ADD COLUMN branch_id INT NULL AFTER tenant_id');
                $db->exec('ALTER TABLE products ADD KEY idx_products_branch (branch_id)');
                $log[] = 'Added products.branch_id';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed products.branch_id: ' . $e->getMessage();
            }
        }

        if (SchemaHelper::tableExists($db, 'tenant_services')
            && !SchemaHelper::columnExists($db, 'tenant_services', 'branch_id')) {
            try {
                $db->exec('ALTER TABLE tenant_services ADD COLUMN branch_id INT NULL AFTER tenant_id');
                $db->exec('ALTER TABLE tenant_services ADD KEY idx_tenant_services_branch (branch_id)');
                $log[] = 'Added tenant_services.branch_id';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed tenant_services.branch_id: ' . $e->getMessage();
            }
        }

        self::backfillBranchIds($db, $log);

        $ok = (!SchemaHelper::tableExists($db, 'products') || SchemaHelper::columnExists($db, 'products', 'branch_id'))
            && (!SchemaHelper::tableExists($db, 'tenant_services') || SchemaHelper::columnExists($db, 'tenant_services', 'branch_id'));

        return ['ok' => $ok, 'log' => $log];
    }

    private static function backfillBranchIds(PDO $db, array &$log): void
    {
        if (!SchemaHelper::tableExists($db, 'branches')) {
            return;
        }
        try {
            if (SchemaHelper::columnExists($db, 'products', 'branch_id')) {
                $n = $db->exec(
                    'UPDATE products p
                        JOIN (
                            SELECT tenant_id, MIN(id) AS first_branch
                              FROM branches
                          GROUP BY tenant_id
                        ) b ON b.tenant_id = p.tenant_id
                       SET p.branch_id = b.first_branch
                     WHERE p.branch_id IS NULL'
                );
                if ($n) {
                    $log[] = "Backfilled branch_id on {$n} product(s)";
                }
            }
            if (SchemaHelper::columnExists($db, 'tenant_services', 'branch_id')) {
                $n = $db->exec(
                    'UPDATE tenant_services ts
                        JOIN (
                            SELECT tenant_id, MIN(id) AS first_branch
                              FROM branches
                          GROUP BY tenant_id
                        ) b ON b.tenant_id = ts.tenant_id
                       SET ts.branch_id = b.first_branch
                     WHERE ts.branch_id IS NULL'
                );
                if ($n) {
                    $log[] = "Backfilled branch_id on {$n} service(s)";
                }
            }
        } catch (Throwable $e) {
            $log[] = 'Branch backfill skipped: ' . $e->getMessage();
        }
    }
}
