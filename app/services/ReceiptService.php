<?php
// app/services/ReceiptService.php
// Shared receipt rendering for POS sales and commission (service) invoices.

class ReceiptService
{
    public static function money(float $n, string $currency = 'KES'): string
    {
        return $currency . ' ' . number_format($n, 2);
    }

    /** @param array<string,mixed>|null $branch Row with title, branch_type */
    public static function tenantHeader(array $tenant, ?array $branch = null, ?string $logoUrl = null): string
    {
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $shop = $h($tenant['name'] ?? 'Shop');
        $loc  = trim($tenant['location'] ?? '') ?: trim($tenant['address'] ?? '');
        $kra  = trim($tenant['kra_pin'] ?? '');
        $phone = trim($tenant['phone'] ?? '');

        $logoHtml = '';
        if ($logoUrl) {
            $logoHtml = '<img src="' . $h($logoUrl) . '" alt="" style="max-height:48px;margin-bottom:8px;">';
        }

        $lines = '';
        if ($loc) {
            $lines .= '<div style="font-size:12px;color:#475569;">' . $h($loc) . '</div>';
        }
        if ($kra) {
            $lines .= '<div style="font-size:11px;color:#64748b;">KRA PIN: ' . $h($kra) . '</div>';
        }
        if ($phone) {
            $lines .= '<div style="font-size:11px;color:#64748b;">Tel: ' . $h($phone) . '</div>';
        }

        $branchLine = self::branchLine($branch);
        if ($branchLine !== '') {
            $lines .= '<div style="font-size:12px;color:#475569;margin-top:2px;">' . $branchLine . '</div>';
        }

        return '<div style="text-align:center;border-bottom:2px dashed #cbd5e1;padding-bottom:10px;margin-bottom:10px;">'
            . $logoHtml
            . '<div style="font-size:18px;font-weight:700;">' . $shop . '</div>'
            . $lines
            . '</div>';
    }

    /** @param array<string,mixed>|null $branch */
    private static function branchLine(?array $branch): string
    {
        if (!$branch) {
            return '';
        }
        $name = trim((string) ($branch['title'] ?? ''));
        if ($name === '') {
            return '';
        }
        $type = (string) ($branch['branch_type'] ?? 'shop');
        $label = TenantModules::isShopType($type) ? 'Shop' : 'Branch';
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        return $label . ': ' . $h($name);
    }

    /** POS sale receipt (multi-line items). */
    public static function posReceiptHtml(
        array $sale,
        array $items,
        array $tenant,
        ?array $branch,
        string $staffName,
        ?string $logoUrl = null
    ): string {
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $currency = $tenant['currency'] ?? 'KES';
        $rows = '';
        foreach ($items as $it) {
            $qty = rtrim(rtrim(number_format((float) $it['quantity'], 2), '0'), '.');
            $rows .= '<tr>'
                . '<td style="padding:4px 0;">' . $h($it['product_name'])
                . '<br><span style="color:#64748b;font-size:12px;">' . $qty . ' ' . $h($it['unit'])
                . ' &times; ' . self::money((float) $it['unit_price'], $currency) . '</span></td>'
                . '<td style="padding:4px 0;text-align:right;white-space:nowrap;">' . self::money((float) $it['line_total'], $currency) . '</td>'
                . '</tr>';
        }

        $method = $sale['payment_method'] ?? 'cash';
        if ($method === 'credit') {
            $payLine = '<tr><td style="color:#64748b;">Payment</td><td style="text-align:right;">On credit</td></tr>';
        } elseif ($method === 'cash') {
            $payLine = '<tr><td style="color:#64748b;">Cash given</td><td style="text-align:right;">' . self::money((float) $sale['amount_given'], $currency) . '</td></tr>'
                . '<tr><td style="color:#64748b;">Change</td><td style="text-align:right;">' . self::money((float) $sale['change_given'], $currency) . '</td></tr>';
        } else {
            $payLine = '<tr><td style="color:#64748b;">Paid by</td><td style="text-align:right;">M-Pesa</td></tr>';
        }

        $cust = self::customerBlock($sale['customer_name'] ?? '', $sale['customer_phone'] ?? '', $sale['customer_email'] ?? '');
        $footer = trim($tenant['receipt_footer'] ?? '');

        return self::wrap(
            self::tenantHeader($tenant, $branch, $logoUrl)
            . '<div style="font-size:12px;color:#64748b;text-align:center;margin-bottom:8px;">'
            . 'Receipt <strong>' . $h($sale['receipt_number']) . '</strong><br>'
            . $h(date('j M Y, g:i a', strtotime($sale['created_at']))) . '<br>'
            . 'Served by ' . $h($staffName)
            . '</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $rows . '</table>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;border-top:2px dashed #cbd5e1;margin-top:8px;">'
            . '<tr><td style="font-weight:700;padding-top:8px;">Total</td>'
            . '<td style="text-align:right;font-weight:700;padding-top:8px;">' . self::money((float) $sale['total'], $currency) . '</td></tr>'
            . $payLine . '</table>'
            . $cust
            . ($footer ? '<p style="text-align:center;font-size:12px;color:#64748b;margin-top:12px;">' . $h($footer) . '</p>' : '')
            . '<p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:10px;">Thank you for your business.</p>'
        );
    }

    /** Commission / service invoice receipt (single line item). */
    public static function commissionReceiptHtml(
        array $sale,
        array $expenses,
        array $tenant,
        ?array $branch,
        string $servedBy,
        ?string $logoUrl = null,
        ?string $checkedInBy = null
    ): string {
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $currency = $tenant['currency'] ?? 'KES';
        $qty = rtrim(rtrim(number_format((float) ($sale['quantity'] ?? 1), 2), '0'), '.');
        $typeLabel = ($sale['item_type'] ?? '') === 'service' ? 'Service' : 'Product';
        $pending = ($sale['payment_status'] ?? 'paid') === 'pending';

        $line = '<tr><td style="padding:4px 0;">' . $h($sale['item_name'])
            . '<br><span style="color:#64748b;font-size:12px;">' . $h($typeLabel);
        if ((float) ($sale['quantity'] ?? 1) != 1) {
            $line .= ' &times; ' . $h($qty);
        }
        $line .= ' &mdash; std ' . self::money((float) $sale['standard_price'], $currency) . '</span></td>'
            . '<td style="padding:4px 0;text-align:right;">' . self::money((float) $sale['charged_amount'], $currency) . '</td></tr>';

        $expRows = '';
        foreach ($expenses as $e) {
            $expRows .= '<tr><td style="font-size:12px;color:#64748b;padding:2px 0;">+ ' . $h($e['expense_name']) . '</td>'
                . '<td style="font-size:12px;text-align:right;color:#64748b;">' . self::money((float) $e['cost'], $currency) . '</td></tr>';
        }

        $method = $sale['payment_method'] ?? 'cash';
        $payLabel = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'credit' => 'Credit'][$method] ?? 'Cash';
        if ($pending) {
            $payLabel = 'Unpaid — pay at till';
        }

        $meta = 'Receipt <strong>' . $h($sale['receipt_number']) . '</strong><br>'
            . $h(date('j M Y, g:i a', strtotime($sale['created_at']))) . '<br>'
            . 'Served by ' . $h($servedBy !== '' ? $servedBy : '—');
        if ($checkedInBy !== null && $checkedInBy !== '' && $checkedInBy !== $servedBy) {
            $meta .= '<br>Checked in by ' . $h($checkedInBy);
        }
        if ($pending) {
            $meta = '<span style="font-weight:700;color:#0f172a;">UNPAID</span><br>' . $meta;
        }

        $cust = self::customerBlock($sale['customer_name'] ?? '', $sale['customer_phone'] ?? '');
        $footer = trim($tenant['receipt_footer'] ?? '');

        $commissionRow = '';
        if (!$pending && (float) ($sale['total_commission'] ?? 0) > 0) {
            $commissionRow = '<tr><td style="color:#64748b;font-size:12px;">Staff commission</td>'
                . '<td style="text-align:right;font-size:12px;color:#16a34a;">' . self::money((float) $sale['total_commission'], $currency) . '</td></tr>';
        }

        return self::wrap(
            self::tenantHeader($tenant, $branch, $logoUrl)
            . '<div style="font-size:12px;color:#64748b;text-align:center;margin-bottom:8px;">' . $meta . '</div>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;">' . $line . $expRows . '</table>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px;border-top:2px dashed #cbd5e1;margin-top:8px;">'
            . '<tr><td style="font-weight:700;padding-top:8px;">Amount charged</td>'
            . '<td style="text-align:right;font-weight:700;padding-top:8px;">' . self::money((float) $sale['charged_amount'], $currency) . '</td></tr>'
            . '<tr><td style="color:#64748b;">Payment</td><td style="text-align:right;">' . $h($payLabel) . '</td></tr>'
            . $commissionRow
            . '</table>'
            . $cust
            . ($footer ? '<p style="text-align:center;font-size:12px;color:#64748b;margin-top:12px;">' . $h($footer) . '</p>' : '')
            . '<p style="text-align:center;font-size:12px;color:#94a3b8;margin-top:10px;">Thank you for your business.</p>'
        );
    }

    private static function customerBlock(string $name, string $phone = '', string $email = ''): string
    {
        if ($name === '' && $phone === '' && $email === '') {
            return '';
        }
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $parts = $h($name ?: '—');
        if ($phone !== '') {
            $parts .= ' · ' . $h($phone);
        }
        if ($email !== '') {
            $parts .= '<br>' . $h($email);
        }
        return '<p style="margin:10px 0 0;font-size:12px;color:#64748b;">Customer: ' . $parts . '</p>';
    }

    private static function wrap(string $inner): string
    {
        return '<div style="font-family:-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:360px;margin:0 auto;color:#0f172a;">'
            . $inner . '</div>';
    }
}
