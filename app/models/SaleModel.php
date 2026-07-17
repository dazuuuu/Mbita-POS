<?php
// app/models/SaleModel.php
namespace Models;

class SaleModel extends Model
{
    protected string $table = 'sales';

    /**
     * Record a sale atomically: validate stock, snapshot prices from the DB,
     * write the sale + items, and decrement stock. All-or-nothing.
     *
     * @param array $in branch_id, staff_id, payment_method, amount_given,
     *                  customer_name/phone/email, items[[product_id,quantity]]
     * @return array ok, sale_id, receipt_number, errors
     */
    public function record(array $in): array
    {
        $tid = \TenantContext::tenantId();
        if ($tid === null) {
            return ['ok' => false, 'errors' => ['_' => 'No shop in context.']];
        }
        $method = in_array($in['payment_method'] ?? '', ['cash', 'mpesa'], true) ? $in['payment_method'] : null;
        if (!$method) {
            return ['ok' => false, 'errors' => ['payment_method' => 'Choose how the customer paid.']];
        }
        $items = array_values(array_filter($in['items'] ?? [], fn($i) => (int)($i['product_id'] ?? 0) > 0 && (float)($i['quantity'] ?? 0) > 0));
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one product to the sale.']];
        }

        $db = $this->db;
        try {
            $db->beginTransaction();

            // Lock and price each product from the DB (never trust client prices).
            $sel = $db->prepare("SELECT id, name, selling_price, quantity, unit FROM products WHERE id = ? AND tenant_id = ? AND status = 'active' FOR UPDATE");
            $total = 0.0;
            $lines = [];
            foreach ($items as $it) {
                $pid = (int) $it['product_id'];
                $qty = (float) $it['quantity'];
                $sel->execute([$pid, $tid]);
                $p = $sel->fetch();
                if (!$p) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => 'One of the products is no longer available. Refresh and try again.']];
                }
                if ($qty > (float) $p['quantity']) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => "Not enough stock for {$p['name']} — only " . rtrim(rtrim(number_format((float)$p['quantity'], 2), '0'), '.') . " left."]];
                }
                $lineTotal = round((float) $p['selling_price'] * $qty, 2);
                $total += $lineTotal;
                $lines[] = [
                    'product_id'   => $pid,
                    'product_name' => $p['name'],
                    'unit'         => $p['unit'],
                    'unit_price'   => (float) $p['selling_price'],
                    'quantity'     => $qty,
                    'line_total'   => $lineTotal,
                ];
            }
            $total = round($total, 2);

            $amountGiven = null;
            $change = null;
            if ($method === 'cash') {
                $amountGiven = (float) ($in['amount_given'] ?? 0);
                if ($amountGiven + 0.0001 < $total) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['amount_given' => 'Cash given is less than the total.']];
                }
                $change = round($amountGiven - $total, 2);
            } else {
                $amountGiven = $total;
                $change = 0.0;
            }

            $ins = $db->prepare(
                "INSERT INTO sales (tenant_id, branch_id, staff_id, receipt_number, payment_method, total, amount_given, change_given, customer_name, customer_phone, customer_email, status)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?, 'completed')"
            );
            $ins->execute([
                $tid,
                !empty($in['branch_id']) ? (int) $in['branch_id'] : null,
                (int) $in['staff_id'],
                'PENDING',
                $method, $total, $amountGiven, $change,
                ($in['customer_name'] ?? '') !== '' ? trim($in['customer_name']) : null,
                ($in['customer_phone'] ?? '') !== '' ? trim($in['customer_phone']) : null,
                ($in['customer_email'] ?? '') !== '' ? trim($in['customer_email']) : null,
            ]);
            $saleId  = (int) $db->lastInsertId();
            $receipt = 'RCP-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
            $db->prepare("UPDATE sales SET receipt_number = ? WHERE id = ?")->execute([$receipt, $saleId]);

            $insItem = $db->prepare(
                "INSERT INTO sale_items (tenant_id, sale_id, product_id, product_name, unit, unit_price, quantity, line_total)
                 VALUES (?,?,?,?,?,?,?,?)"
            );
            $dec = $db->prepare("UPDATE products SET quantity = quantity - ? WHERE id = ? AND tenant_id = ? AND quantity >= ?");
            foreach ($lines as $l) {
                $insItem->execute([$tid, $saleId, $l['product_id'], $l['product_name'], $l['unit'], $l['unit_price'], $l['quantity'], $l['line_total']]);
                $dec->execute([$l['quantity'], $l['product_id'], $tid, $l['quantity']]);
                if ($dec->rowCount() !== 1) {
                    $db->rollBack();
                    return ['ok' => false, 'errors' => ['_' => "Stock changed for {$l['product_name']} while saving. Please redo the sale."]];
                }
            }

            $db->commit();
            return ['ok' => true, 'sale_id' => $saleId, 'receipt_number' => $receipt, 'errors' => []];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) { $db->rollBack(); }
            return ['ok' => false, 'errors' => ['_' => 'Could not complete the sale. Please try again.']];
        }
    }

    public function findScoped(int $id): ?array
    {
        return $this->find($id); // base Model already scopes to tenant
    }

    public function items(int $saleId): array
    {
        $tid = \TenantContext::tenantId();
        $stmt = $this->db->prepare("SELECT * FROM sale_items WHERE sale_id = ? AND tenant_id = ? ORDER BY id ASC");
        $stmt->execute([$saleId, $tid]);
        return $stmt->fetchAll();
    }

    /** Sales recorded by one user (for the staff "My sales" page).
     *  Pass $date as 'Y-m-d' to restrict to that day only. */
    public function forStaff(int $staffId, int $limit = 500, ?string $date = null): array
    {
        $tid = \TenantContext::tenantId();
        $dateSql = $date ? "AND DATE(s.created_at) = '" . preg_replace('/[^0-9-]/', '', $date) . "'" : '';
        $stmt = $this->db->prepare(
            "SELECT s.*, (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
               FROM sales s
              WHERE s.tenant_id = ? AND s.staff_id = ? {$dateSql}
           ORDER BY s.created_at DESC, s.id DESC
              LIMIT ?"
        );
        $stmt->bindValue(1, $tid, \PDO::PARAM_INT);
        $stmt->bindValue(2, $staffId, \PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** All sales for the tenant with staff + branch names (admin view).
     *  $period: 'today' | 'week' | 'month' | 'all' (default 'all') */
    public function forTenant(int $limit = 1000, string $period = 'all'): array
    {
        $tid = \TenantContext::tenantId();
        $periodSql = match ($period) {
            'today' => "AND DATE(s.created_at) = CURDATE()",
            'week'  => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            'month' => "AND s.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            default => '',
        };
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS staff_name, b.title AS branch_name,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id) AS item_count
               FROM sales s
          LEFT JOIN users u ON u.id = s.staff_id
          LEFT JOIN branches b ON b.id = s.branch_id
              WHERE s.tenant_id = ? {$periodSql}
           ORDER BY s.created_at DESC, s.id DESC
              LIMIT ?"
        );
        $stmt->bindValue(1, $tid, \PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** All sales for a specific tenant ID (for CLI cron — no TenantContext). */
    public function forTenantId(int $tenantId, string $date): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.*, u.username AS staff_name, b.title AS branch_name
               FROM sales s
          LEFT JOIN users u ON u.id = s.staff_id
          LEFT JOIN branches b ON b.id = s.branch_id
              WHERE s.tenant_id = ? AND DATE(s.created_at) = ?
           ORDER BY s.created_at ASC"
        );
        $stmt->execute([$tenantId, $date]);
        return $stmt->fetchAll();
    }

    /** Per-staff revenue breakdown from an already-fetched sales array. */
    public static function staffBreakdown(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $name = $r['staff_name'] ?? 'Unknown';
            if (!isset($out[$name])) { $out[$name] = ['count' => 0, 'revenue' => 0.0]; }
            $out[$name]['count']++;
            $out[$name]['revenue'] += (float) $r['total'];
        }
        uasort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $out;
    }

    /** Per-branch revenue breakdown from an already-fetched sales array. */
    public static function branchBreakdown(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $name = $r['branch_name'] ?: 'No branch';
            if (!isset($out[$name])) { $out[$name] = ['count' => 0, 'revenue' => 0.0]; }
            $out[$name]['count']++;
            $out[$name]['revenue'] += (float) $r['total'];
        }
        uasort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $out;
    }

    /** Totals for a set of sales rows (revenue, count, by method). */
    public static function summarize(array $rows): array
    {
        $sum = ['count' => 0, 'revenue' => 0.0, 'cash' => 0.0, 'mpesa' => 0.0];
        foreach ($rows as $r) {
            $sum['count']++;
            $sum['revenue'] += (float) $r['total'];
            $sum[$r['payment_method']] = ($sum[$r['payment_method']] ?? 0) + (float) $r['total'];
        }
        $sum['revenue'] = round($sum['revenue'], 2);
        return $sum;
    }
}