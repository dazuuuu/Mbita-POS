<?php
// app/helpers/SchemaHelper.php
// Lightweight checks for whether expected DB columns/tables exist.

class SchemaHelper
{
    private static array $columnCache = [];

    public static function clearCache(): void
    {
        self::$columnCache = [];
    }

    public static function tableExists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }

    public static function columnExists(PDO $db, string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (!isset(self::$columnCache[$key])) {
            self::$columnCache[$key] = false;
            try {
                // SHOW COLUMNS works on AMPPS even when information_schema is restricted.
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
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
        }
        return self::$columnCache[$key];
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
            && self::tableExists($db, 'customers')
            && (!self::tableExists($db, 'commission_sales')
                || self::columnExists($db, 'commission_sales', 'receipt_number'));
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
