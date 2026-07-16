<?php
// app/services/Schema026Service.php
// Applies migration 026 (tenant modules JSON, branch types). Safe to call repeatedly.

class Schema026Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (SchemaHelper::tableExists($db, 'tenants')
            && !SchemaHelper::columnExists($db, 'tenants', 'modules')) {
            try {
                $db->exec('ALTER TABLE tenants ADD COLUMN modules JSON NULL AFTER business_type');
                $log[] = 'Added tenants.modules';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed tenants.modules: ' . $e->getMessage();
            }
        }

        if (SchemaHelper::tableExists($db, 'branches')
            && !SchemaHelper::columnExists($db, 'branches', 'branch_type')) {
            try {
                $db->exec("ALTER TABLE branches ADD COLUMN branch_type ENUM('shop','barbershop','salon','barbershop_salon') NOT NULL DEFAULT 'shop' AFTER location");
                $log[] = 'Added branches.branch_type';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed branches.branch_type: ' . $e->getMessage();
            }
        }

        self::seedDefaultModules($db, $log);

        $ok = !SchemaHelper::tableExists($db, 'tenants')
            || SchemaHelper::columnExists($db, 'tenants', 'modules');

        return ['ok' => $ok, 'log' => $log];
    }

    private static function seedDefaultModules(PDO $db, array &$log): void
    {
        if (!SchemaHelper::columnExists($db, 'tenants', 'modules')) {
            return;
        }
        try {
            $rows = $db->query('SELECT id, business_type, modules FROM tenants WHERE modules IS NULL')->fetchAll();
            foreach ($rows as $row) {
                $mods = TenantModules::defaults($row['business_type'] ?? 'shop');
                $stmt = $db->prepare('UPDATE tenants SET modules = ? WHERE id = ?');
                $stmt->execute([json_encode($mods), (int) $row['id']]);
            }
            if ($rows) {
                $log[] = 'Seeded default modules for ' . count($rows) . ' tenant(s)';
            }
        } catch (Throwable $e) {
            $log[] = 'Failed seed modules: ' . $e->getMessage();
        }
    }
}
