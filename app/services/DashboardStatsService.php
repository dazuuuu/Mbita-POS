<?php
// app/services/DashboardStatsService.php
// Aggregates tenant dashboard metrics from curlz_db (read-only, no schema changes).

class DashboardStatsService
{
    private PDO $db;
    private int $tenantId;

    public function __construct(PDO $db, int $tenantId)
    {
        $this->db = $db;
        $this->tenantId = $tenantId;
    }

    public function build(): array
    {
        $currency = $this->scalar("SELECT currency FROM tenants WHERE id = ?", [$this->tenantId], 'KES') ?: 'KES';

        $todayPos = $this->safeQuery(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS revenue
               FROM sales WHERE tenant_id = ? AND status = 'completed' AND DATE(created_at) = CURDATE()",
            [$this->tenantId]
        ) ?: ['cnt' => 0, 'revenue' => 0];

        $monthPos = $this->safeQuery(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS revenue
               FROM sales WHERE tenant_id = ? AND status = 'completed'
                 AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$this->tenantId]
        ) ?: ['cnt' => 0, 'revenue' => 0];

        $allPos = $this->safeQuery(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total),0) AS revenue
               FROM sales WHERE tenant_id = ? AND status = 'completed'",
            [$this->tenantId]
        ) ?: ['cnt' => 0, 'revenue' => 0];

        $todayComm = ['cnt' => 0, 'revenue' => 0];
        if ($this->hasTable('commission_sales')) {
            $todayComm = $this->safeQuery(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(charged_amount),0) AS revenue
                   FROM commission_sales WHERE tenant_id = ? AND DATE(created_at) = CURDATE()",
                [$this->tenantId]
            ) ?: $todayComm;
        }

        $monthCommCount = 0;
        if ($this->hasTable('commission_sales')) {
            $monthCommCount = (int) $this->scalar(
                "SELECT COUNT(*) FROM commission_sales WHERE tenant_id = ?
                   AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
                [$this->tenantId]
            );
        }

        $customers = $this->hasTable('customers')
            ? (int) $this->scalar('SELECT COUNT(*) FROM customers WHERE tenant_id = ?', [$this->tenantId])
            : 0;

        $staff = (int) $this->scalar(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.tenant_id = ? AND r.role_name = 'staff' AND u.is_active = 1",
            [$this->tenantId]
        );

        $agents = (int) $this->scalar(
            "SELECT COUNT(*) FROM users u JOIN roles r ON r.id = u.role_id
              WHERE u.tenant_id = ? AND r.role_name = 'sales_agent' AND u.is_active = 1",
            [$this->tenantId]
        );

        $products = $this->hasTable('products')
            ? (int) $this->scalar("SELECT COUNT(*) FROM products WHERE tenant_id = ? AND status = 'active'", [$this->tenantId])
            : 0;

        $services = $this->hasTable('tenant_services')
            ? (int) $this->scalar("SELECT COUNT(*) FROM tenant_services WHERE tenant_id = ? AND status = 'active'", [$this->tenantId])
            : 0;

        $profitPct = $this->profitMarginPercent();
        $lineChart = $this->last7DaysSeries();
        $barChart  = $this->staffRevenueBars();

        $todayTx = (int) $todayPos['cnt'] + (int) $todayComm['cnt'];
        $todayRev = (float) $todayPos['revenue'] + (float) $todayComm['revenue'];

        $monthRev = (float) $monthPos['revenue'];
        $prevMonthRev = (float) ($this->scalar(
            "SELECT COALESCE(SUM(total),0) FROM sales WHERE tenant_id = ? AND status = 'completed'
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
               AND created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$this->tenantId]
        ) ?: 0);

        $salesPct = $prevMonthRev > 0
            ? min(100, (int) round(($monthRev / $prevMonthRev) * 100))
            : ($monthRev > 0 ? 100 : 0);

        $custPct = min(100, $customers > 0 ? min(100, 20 + $customers * 8) : 5);

        $monthTxTotal = (int) $monthPos['cnt'] + $monthCommCount;
        $txPct = $monthTxTotal > 0
            ? min(100, (int) round(($todayTx / $monthTxTotal) * 100))
            : ($todayTx > 0 ? 100 : 0);

        return [
            'currency' => $currency,
            'cards' => [
                ['label' => 'Daily Sales', 'value' => $todayTx, 'icon' => 'fa-eye', 'color' => '#e74c3c'],
                ['label' => 'Today Revenue', 'value' => $this->fmtMoney($todayRev, $currency), 'icon' => 'fa-cart-shopping', 'color' => '#e67e22', 'raw' => $todayRev],
                ['label' => 'Commission Sales', 'value' => $monthCommCount, 'icon' => 'fa-comments', 'color' => '#3498db'],
                ['label' => 'Customers', 'value' => $customers, 'icon' => 'fa-users', 'color' => '#2ecc71'],
            ],
            'rings' => [
                ['label' => 'Profit', 'pct' => $profitPct, 'color' => '#3498db'],
                ['label' => 'Today Activity', 'pct' => $txPct, 'color' => '#e74c3c'],
                ['label' => 'Customers', 'pct' => $custPct, 'color' => '#1abc9c'],
                ['label' => 'Sales Growth', 'pct' => $salesPct, 'color' => '#f39c12'],
            ],
            'line' => $lineChart,
            'bar'  => $barChart,
            'totals' => [
                'month_revenue' => $monthRev,
                'all_revenue'   => (float) $allPos['revenue'],
                'products'      => $products,
                'services'      => $services,
                'staff'         => $staff,
                'agents'        => $agents,
            ],
        ];
    }

    private function profitMarginPercent(): int
    {
        if (!$this->hasTable('sale_items')) {
            return 0;
        }
        $row = $this->safeQuery(
            "SELECT COALESCE(SUM(si.line_total),0) AS rev,
                    COALESCE(SUM(si.quantity * COALESCE(p.buying_price,0)),0) AS cost
               FROM sale_items si
               JOIN sales s ON s.id = si.sale_id
          LEFT JOIN products p ON p.id = si.product_id
              WHERE si.tenant_id = ? AND s.status = 'completed'
                AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            [$this->tenantId]
        );
        if (!$row || (float) $row['rev'] <= 0) {
            return 0;
        }
        $margin = ((float) $row['rev'] - (float) $row['cost']) / (float) $row['rev'] * 100;
        return (int) max(0, min(100, round($margin)));
    }

    private function last7DaysSeries(): array
    {
        $labels = [];
        $pos = [];
        $comm = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('D', strtotime($d));
            $pos[] = (float) ($this->scalar(
                "SELECT COALESCE(SUM(total),0) FROM sales
                  WHERE tenant_id = ? AND status='completed' AND DATE(created_at)=?",
                [$this->tenantId, $d]
            ) ?: 0);
            $comm[] = $this->hasTable('commission_sales')
                ? (float) ($this->scalar(
                    "SELECT COALESCE(SUM(charged_amount),0) FROM commission_sales
                      WHERE tenant_id = ? AND DATE(created_at)=?",
                    [$this->tenantId, $d]
                ) ?: 0)
                : 0;
        }
        return ['labels' => $labels, 'pos' => $pos, 'commission' => $comm];
    }

    private function staffRevenueBars(): array
    {
        $rows = $this->safeQueryAll(
            "SELECT COALESCE(u.username,'Unknown') AS name, COALESCE(SUM(s.total),0) AS revenue
               FROM sales s
          LEFT JOIN users u ON u.id = s.staff_id
              WHERE s.tenant_id = ? AND s.status = 'completed'
                AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
           GROUP BY s.staff_id, u.username
           ORDER BY revenue DESC LIMIT 6",
            [$this->tenantId]
        ) ?: [];
        if (!$rows && $this->hasTable('commission_sales')) {
            $rows = $this->safeQueryAll(
                "SELECT COALESCE(u.username,'Agent') AS name, COALESCE(SUM(cs.charged_amount),0) AS revenue
                   FROM commission_sales cs
                   JOIN users u ON u.id = cs.agent_user_id
                  WHERE cs.tenant_id = ?
                    AND cs.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
               GROUP BY cs.agent_user_id, u.username
               ORDER BY revenue DESC LIMIT 6",
                [$this->tenantId]
            ) ?: [];
        }
        return [
            'labels' => array_column($rows, 'name'),
            'values' => array_map(fn($r) => (float) $r['revenue'], $rows),
        ];
    }

    private function fmtMoney(float $n, string $cur): string
    {
        return number_format($n, 0);
    }

    private function hasTable(string $table): bool
    {
        return SchemaHelper::tableExists($this->db, $table);
    }

    private function scalar(string $sql, array $params, $default = 0)
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $v = $stmt->fetchColumn();
            return $v !== false ? $v : $default;
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function safeQuery(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            return $r ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    private function safeQueryAll(string $sql, array $params): ?array
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return null;
        }
    }
}
