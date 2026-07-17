<?php
// app/services/ReceiptService.php
// Shared receipt rendering for POS sales and commission (service) invoices.

class ReceiptService
{
    private const C_TEXT   = '#111111';
    private const C_MUTED  = '#555555';
    private const C_LIGHT  = '#777777';
    private const C_BORDER = '#cccccc';

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
            $logoHtml = '<img src="' . $h($logoUrl) . '" alt="" style="max-height:52px;max-width:200px;margin:0 auto 8px;display:block;filter:grayscale(100%);">';
        }

        $lines = '';
        if ($loc) {
            $lines .= '<div style="font-size:11px;color:' . self::C_MUTED . ';">' . $h($loc) . '</div>';
        }
        if ($kra) {
            $lines .= '<div style="font-size:10px;color:' . self::C_LIGHT . ';">KRA PIN: ' . $h($kra) . '</div>';
        }
        if ($phone) {
            $lines .= '<div style="font-size:10px;color:' . self::C_LIGHT . ';">Tel: ' . $h($phone) . '</div>';
        }

        $branchLine = self::branchLine($branch);
        if ($branchLine !== '') {
            $lines .= '<div style="font-size:11px;color:' . self::C_MUTED . ';margin-top:4px;font-weight:600;">' . $branchLine . '</div>';
        }

        return '<div style="text-align:center;border-bottom:1px dashed ' . self::C_BORDER . ';padding-bottom:10px;margin-bottom:10px;">'
            . $logoHtml
            . '<div style="font-size:17px;font-weight:700;color:' . self::C_TEXT . ';">' . $shop . '</div>'
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
                . '<td style="padding:4px 0;color:' . self::C_TEXT . ';">' . $h($it['product_name'])
                . '<br><span style="color:' . self::C_LIGHT . ';font-size:11px;">' . $qty . ' ' . $h($it['unit'])
                . ' &times; ' . self::money((float) $it['unit_price'], $currency) . '</span></td>'
                . '<td style="padding:4px 0;text-align:right;white-space:nowrap;color:' . self::C_TEXT . ';">' . self::money((float) $it['line_total'], $currency) . '</td>'
                . '</tr>';
        }

        $method = $sale['payment_method'] ?? 'cash';
        if ($method === 'credit') {
            $payLine = '<tr><td style="color:' . self::C_MUTED . ';">Payment</td><td style="text-align:right;color:' . self::C_TEXT . ';">On credit</td></tr>';
        } elseif ($method === 'cash') {
            $payLine = '<tr><td style="color:' . self::C_MUTED . ';">Cash given</td><td style="text-align:right;color:' . self::C_TEXT . ';">' . self::money((float) $sale['amount_given'], $currency) . '</td></tr>'
                . '<tr><td style="color:' . self::C_MUTED . ';">Change</td><td style="text-align:right;color:' . self::C_TEXT . ';">' . self::money((float) $sale['change_given'], $currency) . '</td></tr>';
        } else {
            $payLine = '<tr><td style="color:' . self::C_MUTED . ';">Paid by</td><td style="text-align:right;color:' . self::C_TEXT . ';">M-Pesa</td></tr>';
        }

        $cust = self::customerBlock($sale['customer_name'] ?? '', $sale['customer_phone'] ?? '', $sale['customer_email'] ?? '');
        $footer = trim($tenant['receipt_footer'] ?? '');

        return self::wrap(
            self::tenantHeader($tenant, $branch, $logoUrl)
            . self::metaBlock($sale['receipt_number'] ?? '', $sale['created_at'] ?? '', 'Served by ' . $staffName)
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;">' . $rows . '</table>'
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;border-top:1px dashed ' . self::C_BORDER . ';margin-top:8px;">'
            . '<tr><td style="font-weight:700;padding-top:8px;color:' . self::C_TEXT . ';">Total</td>'
            . '<td style="text-align:right;font-weight:700;padding-top:8px;color:' . self::C_TEXT . ';">' . self::money((float) $sale['total'], $currency) . '</td></tr>'
            . $payLine . '</table>'
            . $cust
            . self::footerBlock($footer)
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

        $line = '<tr><td style="padding:4px 0;color:' . self::C_TEXT . ';">' . $h($sale['item_name'])
            . '<br><span style="color:' . self::C_LIGHT . ';font-size:11px;">' . $h($typeLabel);
        if ((float) ($sale['quantity'] ?? 1) != 1) {
            $line .= ' &times; ' . $h($qty);
        }
        $line .= ' &mdash; std ' . self::money((float) $sale['standard_price'], $currency) . '</span></td>'
            . '<td style="padding:4px 0;text-align:right;color:' . self::C_TEXT . ';">' . self::money((float) $sale['charged_amount'], $currency) . '</td></tr>';

        $expRows = '';
        foreach ($expenses as $e) {
            $expRows .= '<tr><td style="font-size:11px;color:' . self::C_LIGHT . ';padding:2px 0;">+ ' . $h($e['expense_name']) . '</td>'
                . '<td style="font-size:11px;text-align:right;color:' . self::C_LIGHT . ';">' . self::money((float) $e['cost'], $currency) . '</td></tr>';
        }

        $method = $sale['payment_method'] ?? 'cash';
        $payLabel = ['cash' => 'Cash', 'mpesa' => 'M-Pesa', 'credit' => 'Credit'][$method] ?? 'Cash';
        if ($pending) {
            $payLabel = 'Unpaid — pay at till';
        }

        $servedLine = 'Served by ' . ($servedBy !== '' ? $servedBy : '—');
        $metaExtra = '';
        if ($checkedInBy !== null && $checkedInBy !== '' && $checkedInBy !== $servedBy) {
            $metaExtra = '<br>Checked in by ' . $h($checkedInBy);
        }

        $cust = self::customerBlock($sale['customer_name'] ?? '', $sale['customer_phone'] ?? '');
        $footer = trim($tenant['receipt_footer'] ?? '');

        $commissionRow = '';
        if (!$pending && (float) ($sale['total_commission'] ?? 0) > 0) {
            $commissionRow = '<tr><td style="color:' . self::C_LIGHT . ';font-size:11px;">Staff commission</td>'
                . '<td style="text-align:right;font-size:11px;color:' . self::C_MUTED . ';">' . self::money((float) $sale['total_commission'], $currency) . '</td></tr>';
        }

        return self::wrap(
            self::tenantHeader($tenant, $branch, $logoUrl)
            . self::metaBlock($sale['receipt_number'] ?? '', $sale['created_at'] ?? '', $servedLine, $pending, $metaExtra)
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;">' . $line . $expRows . '</table>'
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;border-top:1px dashed ' . self::C_BORDER . ';margin-top:8px;">'
            . '<tr><td style="font-weight:700;padding-top:8px;color:' . self::C_TEXT . ';">Amount</td>'
            . '<td style="text-align:right;font-weight:700;padding-top:8px;color:' . self::C_TEXT . ';">' . self::money((float) $sale['charged_amount'], $currency) . '</td></tr>'
            . '<tr><td style="color:' . self::C_MUTED . ';">Payment</td><td style="text-align:right;color:' . self::C_TEXT . ';">' . $h($payLabel) . '</td></tr>'
            . $commissionRow
            . '</table>'
            . $cust
            . self::footerBlock($footer)
        );
    }

    private static function metaBlock(
        string $receiptNumber,
        string $createdAt,
        string $servedLine,
        bool $pending = false,
        string $extraHtml = ''
    ): string {
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $date = $createdAt !== '' ? $h(date('j M Y, g:i a', strtotime($createdAt))) : '';
        $status = $pending
            ? '<div style="font-size:11px;font-weight:700;color:' . self::C_TEXT . ';margin-top:4px;border:1px solid ' . self::C_TEXT . ';display:inline-block;padding:2px 8px;">UNPAID</div><br>'
            : '';
        return '<div style="font-size:11px;color:' . self::C_MUTED . ';text-align:center;margin-bottom:8px;">'
            . $status
            . 'Receipt <strong style="color:' . self::C_TEXT . ';">' . $h($receiptNumber) . '</strong><br>'
            . $date . '<br>'
            . $h($servedLine)
            . $extraHtml
            . '</div>';
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
        return '<p style="margin:10px 0 0;font-size:11px;color:' . self::C_MUTED . ';">Customer: ' . $parts . '</p>';
    }

    private static function footerBlock(string $footer): string
    {
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $out = '';
        if ($footer !== '') {
            $out .= '<p style="text-align:center;font-size:11px;color:' . self::C_LIGHT . ';margin-top:12px;">' . $h($footer) . '</p>';
        }
        $out .= '<p style="text-align:center;font-size:11px;color:' . self::C_LIGHT . ';margin-top:10px;">Thank you for your business.</p>';
        return $out;
    }

    private static function wrap(string $inner): string
    {
        return '<div class="receipt-print" style="font-family:Courier New,Consolas,monospace;max-width:320px;margin:0 auto;color:'
            . self::C_TEXT . ';background:#fff;">'
            . $inner . '</div>';
    }
}
