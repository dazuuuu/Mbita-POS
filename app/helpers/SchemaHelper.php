<?php
// app/helpers/SchemaHelper.php
// Lightweight checks for whether expected DB columns/tables exist.

class SchemaHelper
{
    private static array $columnCache = [];
    private static array $tableCache = [];

    public static function clearCache(): void
    {
        self::$columnCache = [];
        self::$tableCache = [];
    }

    public static function tableExists(PDO $db, string $table): bool
    {
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        if ($safeTable === '') {
            return false;
        }
        if (isset(self::$tableCache[$safeTable])) {
            return self::$tableCache[$safeTable];
        }

        self::$tableCache[$safeTable] = false;

        try {
            $stmt = $db->prepare(
                'SELECT 1 FROM information_schema.TABLES
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$safeTable]);
            if ($stmt->fetchColumn()) {
                self::$tableCache[$safeTable] = true;
                return true;
            }
        } catch (Throwable $e) {
            // fall through
        }

        try {
            // SHOW TABLES does not work reliably with prepared LIKE placeholders on all MySQL builds.
            $stmt = $db->query('SHOW TABLES');
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                if (($row[0] ?? '') === $safeTable) {
                    self::$tableCache[$safeTable] = true;
                    break;
                }
            }
        } catch (Throwable $e) {
            self::$tableCache[$safeTable] = false;
        }

        return self::$tableCache[$safeTable];
    }

    /** True when MySQL reports duplicate table/column/index — safe to ignore on re-run. */
    public static function isDuplicateSchemaError(Throwable $e): bool
    {
        $msg = $e->getMessage();
        if (str_contains($msg, '1060') || str_contains($msg, '1061') || str_contains($msg, '1062')) {
            return true;
        }
        if (str_contains($msg, '1050') || str_contains($msg, 'already exists')) {
            return true;
        }
        $code = (string) $e->getCode();
        return in_array($code, ['42S01', '23000'], true) && str_contains(strtolower($msg), 'exist');
    }

    public static function columnExists(PDO $db, string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (isset(self::$columnCache[$key])) {
            return self::$columnCache[$key];
        }

        self::$columnCache[$key] = false;
        $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);

        try {
            $stmt = $db->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute([$safeTable, $column]);
            if ($stmt->fetchColumn()) {
                self::$columnCache[$key] = true;
                return true;
            }
        } catch (Throwable $e) {
            // fall through to SHOW COLUMNS
        }

        try {
            $stmt = $db->query("SHOW COLUMNS FROM `{$safeTable}`");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if (($row['Field'] ?? '') === $column) {
                    self::$columnCache[$key] = true;
                    break;
                }
            }
        } catch (Throwable $e) {
            self::$columnCache[$key] = false;
        }

        return self::$columnCache[$key];
    }

    /** Tenants table present (tableExists had a bug with prepared SHOW TABLES). */
    public static function tenantsTableReady(PDO $db): bool
    {
        return self::tableExists($db, 'tenants') || self::columnExists($db, 'tenants', 'id');
    }

    public static function migration023Ready(PDO $db): bool
    {
        return self::tableExists($db, 'commission_sales')
            && self::tableExists($db, 'commission_sale_expenses')
            && self::tableExists($db, 'commission_payouts');
    }

    public static function migration024Ready(PDO $db): bool
    {
        return self::columnExists($db, 'tenants', 'kra_pin')
            && self::columnExists($db, 'tenants', 'credits_enabled')
            && (self::tableExists($db, 'customers') || self::columnExists($db, 'customers', 'id'))
            && (!self::tableExists($db, 'commission_sales')
                || self::columnExists($db, 'commission_sales', 'receipt_number'));
    }

    public static function migration025Ready(PDO $db): bool
    {
        return self::columnExists($db, 'tenants', 'business_type')
            && self::columnExists($db, 'users', 'login_pin_hash')
            && self::columnExists($db, 'users', 'login_pin_lookup');
    }

    public static function migration026Ready(PDO $db): bool
    {
        return self::columnExists($db, 'tenants', 'modules')
            && (!self::tableExists($db, 'branches') || self::columnExists($db, 'branches', 'branch_type'));
    }

    public static function inventoryReady(PDO $db): bool
    {
        return (self::tableExists($db, 'products') || self::columnExists($db, 'products', 'id'))
            && self::columnExists($db, 'products', 'tenant_id')
            && self::columnExists($db, 'products', 'selling_price');
    }

    /** Keep only keys that exist as real columns on the table. */
    public static function filterColumns(PDO $db, string $table, array $data): array
    {
        $out = [];
        foreach ($data as $col => $val) {
            if (self::columnExists($db, $table, $col)) {
                $out[$col] = $val;
            }
        }
        return $out;
    }
}
