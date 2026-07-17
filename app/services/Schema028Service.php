<?php
// app/services/Schema028Service.php
// Owner login method preference (password vs PIN keypad).

class Schema028Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (SchemaHelper::tableExists($db, 'tenants')
            && !SchemaHelper::columnExists($db, 'tenants', 'owner_login_method')) {
            try {
                $db->exec(
                    "ALTER TABLE tenants ADD COLUMN owner_login_method ENUM('password','pin') NOT NULL DEFAULT 'password' AFTER modules"
                );
                $log[] = 'Added tenants.owner_login_method';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed tenants.owner_login_method: ' . $e->getMessage();
            }
        }

        $ok = !SchemaHelper::tableExists($db, 'tenants')
            || SchemaHelper::columnExists($db, 'tenants', 'owner_login_method');

        return ['ok' => $ok, 'log' => $log];
    }
}
