<?php
// app/services/Schema025Service.php
// Applies migration 025 (business types, PIN auth, staff roles). Safe to call repeatedly.

class Schema025Service
{
    public static function ensureApplied(PDO $db): array
    {
        $log = [];

        if (!SchemaHelper::tableExists($db, 'tenants')) {
            return ['ok' => false, 'log' => ['tenants table missing']];
        }

        if (!SchemaHelper::columnExists($db, 'tenants', 'business_type')) {
            try {
                $db->exec("ALTER TABLE tenants ADD COLUMN business_type ENUM('barbershop_salon','shop') NOT NULL DEFAULT 'shop' AFTER slug");
                $log[] = 'Added tenants.business_type';
                SchemaHelper::clearCache();
            } catch (Throwable $e) {
                $log[] = 'Failed tenants.business_type: ' . $e->getMessage();
            }
        }

        if (SchemaHelper::tableExists($db, 'users')) {
            foreach ([
                'staff_type'       => "ALTER TABLE users ADD COLUMN staff_type ENUM('cashier','reception','sales','barber','junior_admin','general') NULL AFTER branch_id",
                'login_pin_hash'   => "ALTER TABLE users ADD COLUMN login_pin_hash VARCHAR(255) NULL AFTER must_reset_password",
                'login_pin_lookup' => "ALTER TABLE users ADD COLUMN login_pin_lookup CHAR(64) NULL AFTER login_pin_hash",
            ] as $col => $sql) {
                if (!SchemaHelper::columnExists($db, 'users', $col)) {
                    try {
                        $db->exec($sql);
                        $log[] = "Added users.{$col}";
                        SchemaHelper::clearCache();
                    } catch (Throwable $e) {
                        $log[] = "Failed users.{$col}: " . $e->getMessage();
                    }
                }
            }
        }

        self::seedRoles($db, $log);

        return ['ok' => SchemaHelper::migration025Ready($db), 'log' => $log];
    }

    private static function seedRoles(PDO $db, array &$log): void
    {
        if (!SchemaHelper::tableExists($db, 'roles')) {
            return;
        }

        $roles = [
            'cashier' => ['payments.receive', 'payments.update', 'sales.view'],
            'reception' => ['appointments.manage', 'invoices.manage', 'customers.manage', 'customers.checkin'],
            'sales' => ['sales.record', 'inventory.view', 'commission.view'],
            'barber' => ['commission.record', 'commission.view'],
            'junior_admin' => ['inventory.view', 'inventory.edit', 'stock.enter', 'sales.view', 'reports.view', 'payments.receive'],
        ];

        foreach ($roles as $name => $caps) {
            $stmt = $db->prepare('SELECT id FROM roles WHERE role_name = ? LIMIT 1');
            $stmt->execute([$name]);
            if (!$stmt->fetchColumn()) {
                try {
                    $ins = $db->prepare('INSERT INTO roles (role_name, scope, capabilities) VALUES (?, ?, ?)');
                    $ins->execute([$name, 'tenant', json_encode($caps)]);
                    $log[] = "Seeded role {$name}";
                } catch (Throwable $e) {
                    $log[] = "Failed seed role {$name}: " . $e->getMessage();
                }
            }
        }
    }
}
