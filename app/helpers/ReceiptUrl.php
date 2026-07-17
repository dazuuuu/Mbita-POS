<?php
// app/helpers/ReceiptUrl.php — unified receipt links (layout auto-detected at view time).

class ReceiptUrl
{
    public static function forPos(int $id): string
    {
        return public_path('receipt/view.php') . '?type=pos&id=' . $id;
    }

    public static function forCommission(int $id): string
    {
        return public_path('receipt/view.php') . '?type=commission&id=' . $id;
    }

    public static function forUnifiedRow(array $row): string
    {
        $type = ($row['sale_type'] ?? 'pos') === 'commission' ? 'commission' : 'pos';
        return public_path('receipt/view.php') . '?type=' . $type . '&id=' . (int) ($row['id'] ?? 0);
    }
}
