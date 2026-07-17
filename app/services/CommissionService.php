<?php
// app/services/CommissionService.php
// Commission sale recording, totals, and payout settlement.

class CommissionService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** Ensure commission tables exist before read/write operations. */
    public static function ensureSchema(PDO $db): bool
    {
        Schema023Service::ensureApplied($db);
        Schema024Service::ensureApplied($db);
        Schema030Service::ensureApplied($db);
        Schema031Service::ensureApplied($db);
        Schema032Service::ensureApplied($db);
        return SchemaHelper::migration023Ready($db);
    }

    private function hasPaymentWorkflow(): bool
    {
        return SchemaHelper::columnExists($this->db, 'commission_sales', 'payment_status');
    }

    /** Reception creates a service invoice — payment collected later at till. */
    public function recordInvoice(int $tenantId, int $recordedBy, array $in): array
    {
        $deferAgent = !empty($in['defer_agent']);
        $agentId = (int) ($in['agent_user_id'] ?? 0);
        if (!$deferAgent && $agentId <= 0) {
            return ['ok' => false, 'errors' => ['agent_user_id' => 'Assign the staff member who performed the service.']];
        }
        $in['pending'] = true;
        $in['recorded_by_user_id'] = $recordedBy;
        return $this->recordSale($tenantId, $deferAgent ? 0 : $agentId, $in);
    }

    /** Reception check-in — multiple pending service invoices for one customer visit. */
    public function recordCheckIn(int $tenantId, int $recordedBy, array $common, array $items): array
    {
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one service.']];
        }
        $deferAgent = !empty($common['defer_agent']);
        $agentId = (int) ($common['agent_user_id'] ?? 0);
        if (!$deferAgent && $agentId <= 0) {
            return ['ok' => false, 'errors' => ['agent_user_id' => 'Assign the staff member for this visit.']];
        }
        $customerName = trim($common['customer_name'] ?? '');
        if ($customerName === '') {
            return ['ok' => false, 'errors' => ['customer_name' => 'Customer name is required for check-in.']];
        }

        $ids = [];
        $receipts = [];
        $total = 0.0;
        foreach ($items as $item) {
            if (($item['item_type'] ?? '') !== 'service') {
                continue;
            }
            $res = $this->recordInvoice($tenantId, $recordedBy, array_merge($common, $item, [
                'agent_user_id' => $deferAgent ? 0 : $agentId,
                'defer_agent'   => $deferAgent,
                'customer_name' => $customerName,
            ]));
            if (!$res['ok']) {
                return $res;
            }
            $ids[] = (int) $res['id'];
            $receipts[] = $res['receipt_number'];
            $total += (float) ($item['charged_amount'] ?? 0);
        }
        if (!$ids) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one service.']];
        }
        return [
            'ok' => true,
            'ids' => $ids,
            'id' => $ids[0],
            'receipt_numbers' => $receipts,
            'receipt_number' => $receipts[0],
            'total' => $total,
            'count' => count($ids),
            'errors' => [],
        ];
    }

    /** Cashier/reception marks a pending invoice as paid. */
    public function processPayment(int $tenantId, int $saleId, array $in): array
    {
        if (!$this->hasPaymentWorkflow()) {
            return ['ok' => false, 'error' => 'Payment workflow is not set up. Run fix-all-schema.php once.'];
        }

        $method = $in['payment_method'] ?? 'cash';
        $isCredit = $method === 'credit';
        if (!$isCredit && !in_array($method, ['cash', 'mpesa'], true)) {
            return ['ok' => false, 'error' => 'Choose cash, M-Pesa, or credit.'];
        }

        $sale = $this->find($tenantId, $saleId);
        if (!$sale) {
            return ['ok' => false, 'error' => 'Invoice not found.'];
        }
        if (($sale['payment_status'] ?? 'paid') !== 'pending') {
            return ['ok' => false, 'error' => 'This invoice is already paid or voided.'];
        }

        $agentId = (int) ($in['agent_user_id'] ?? 0);
        $existingAgent = (int) ($sale['agent_user_id'] ?? 0);
        if ($existingAgent <= 0) {
            if ($agentId <= 0) {
                return ['ok' => false, 'error' => 'Assign the stylist or barber before collecting payment.'];
            }
        } elseif ($agentId > 0 && $agentId !== $existingAgent) {
            $existingAgent = $agentId;
        }

        $amount = (float) ($sale['charged_amount'] ?? 0);
        $creditDueAt = null;

        if ($isCredit) {
            if (!$this->serviceCreditsEnabled($tenantId)) {
                return ['ok' => false, 'error' => 'Service credits are not enabled for this shop.'];
            }
            $customerId = (int) ($sale['customer_id'] ?? 0);
            if ($customerId <= 0) {
                return ['ok' => false, 'error' => 'Customer is required for credit payment. Check-in must include customer name.'];
            }
            $custSvc = new CustomerService($this->db);
            $summary = $custSvc->creditSummary($tenantId, $customerId);
            if ($summary['available'] + 0.01 < $amount) {
                return ['ok' => false, 'error' => 'Credit not available. Limit KES '
                    . number_format($summary['limit'], 0) . ', used KES '
                    . number_format($summary['used'], 0) . ', available KES '
                    . number_format($summary['available'], 0) . '.'];
            }
            $days = max(1, (int) ($in['credit_days'] ?? $this->defaultCreditDays($tenantId)));
            $creditDueAt = date('Y-m-d H:i:s', strtotime("+{$days} days"));
        }

        $ownsTx = !$this->db->inTransaction();
        if ($ownsTx) {
            $this->db->beginTransaction();
        }
        try {
            if ($isCredit) {
                $custSvc = new CustomerService($this->db);
                $custSvc->addCredit($tenantId, (int) $sale['customer_id'], $amount);
            }

            if ($existingAgent > 0 && (int) ($sale['agent_user_id'] ?? 0) !== $existingAgent) {
                $this->db->prepare(
                    'UPDATE commission_sales SET agent_user_id = ? WHERE id = ? AND tenant_id = ?'
                )->execute([$existingAgent, $saleId, $tenantId]);
            }

            $creditSql = SchemaHelper::columnExists($this->db, 'commission_sales', 'credit_due_at')
                ? ', credit_due_at = ?' : '';
            $params = [$method, $isCredit ? 1 : 0];
            if ($creditSql) {
                $params[] = $creditDueAt;
            }
            $params[] = $saleId;
            $params[] = $tenantId;

            $stmt = $this->db->prepare(
                "UPDATE commission_sales
                    SET payment_method = ?, payment_status = 'paid', paid_at = NOW(), is_credit = ?{$creditSql}
                  WHERE id = ? AND tenant_id = ? AND payment_status = 'pending'"
            );
            $stmt->execute($params);
            if ($stmt->rowCount() !== 1) {
                if ($ownsTx && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return ['ok' => false, 'error' => 'Could not process payment.'];
            }
            if ($ownsTx) {
                $this->db->commit();
            }
            return ['ok' => true, 'id' => $saleId, 'receipt_number' => $sale['receipt_number']];
        } catch (Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'error' => 'Could not process payment.'];
        }
    }

    /** Pending invoices awaiting payment at till. */
    public function pendingPayments(int $tenantId, ?int $branchId = null): array
    {
        if (!$this->hasPaymentWorkflow()) {
            return [];
        }
        $branchSql = '';
        $params = [$tenantId];
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND cs.branch_id = ?';
            $params[] = $branchId;
        }
        return $this->rows(
            "SELECT cs.*, u.username AS agent_name, r.username AS recorded_by_name, b.title AS branch_name,
                    c.credit_balance, c.credit_limit AS customer_credit_limit
               FROM commission_sales cs
          LEFT JOIN users u ON u.id = cs.agent_user_id
          LEFT JOIN users r ON r.id = cs.recorded_by_user_id
          LEFT JOIN branches b ON b.id = cs.branch_id
          LEFT JOIN customers c ON c.id = cs.customer_id
              WHERE cs.tenant_id = ? AND cs.payment_status = 'pending' {$branchSql}
           ORDER BY cs.created_at ASC
              LIMIT 200",
            $params
        );
    }

    /** Whether this user may process payments (module + capability). */
    public static function canProcessPayment(PDO $db, ?array $tenant, ?array $branch = null): bool
    {
        if (!TenantContext::can(Capabilities::PAYMENTS_RECEIVE)) {
            return false;
        }
        $modules = $branch
            ? TenantModules::fromBranch($branch)
            : TenantModules::fromTenant($tenant);
        if (empty($modules[TenantModules::PAYMENT_PROCESSING])) {
            return true;
        }
        $role = TenantContext::role();
        if (in_array($role, ['tenant_owner', 'cashier', 'reception'], true)) {
            return true;
        }
        return TenantContext::can(Capabilities::PAYMENTS_RECEIVE);
    }

    /** Decode cart JSON from the record-sale form into sale line items. */
    public static function parseCartItems($cart): array
    {
        if (!is_array($cart)) {
            return [];
        }
        $items = [];
        foreach ($cart as $c) {
            if (!is_array($c)) {
                continue;
            }
            $type = $c['type'] ?? '';
            if (!in_array($type, ['service', 'product'], true)) {
                continue;
            }
            $items[] = [
                'item_type'      => $type,
                'service_id'     => $type === 'service' ? (int) ($c['id'] ?? 0) : null,
                'product_id'     => $type === 'product' ? (int) ($c['id'] ?? 0) : null,
                'item_name'      => (string) ($c['name'] ?? ''),
                'charged_amount' => (float) ($c['charged'] ?? 0),
                'quantity'       => (float) ($c['quantity'] ?? 1),
                'expenses'       => is_array($c['expenses'] ?? null) ? $c['expenses'] : [],
            ];
        }
        return $items;
    }

    private function hasCommissionSales(): bool
    {
        return SchemaHelper::tableExists($this->db, 'commission_sales');
    }

    private function scalar(string $sql, array $params, float $default = 0.0): float
    {
        if (!$this->hasCommissionSales()) {
            return $default;
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (float) $stmt->fetchColumn();
        } catch (Throwable $e) {
            return $default;
        }
    }

    private function rows(string $sql, array $params): array
    {
        if (!$this->hasCommissionSales()) {
            return [];
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Calculate base + overage commission from settings and charged amount. */
    public static function calculateCommission(
        string $type,
        float $value,
        float $standardPrice,
        float $chargedAmount
    ): array {
        $base = $type === 'percent'
            ? round($chargedAmount * ($value / 100), 2)
            : round($value, 2);
        $overage = round(max(0, $chargedAmount - $standardPrice), 2);
        return [
            'base_commission'    => $base,
            'overage_commission' => $overage,
            'total_commission'   => round($base + $overage, 2),
        ];
    }

    public function recordSale(int $tenantId, int $agentUserId, array $in): array
    {
        if (!self::ensureSchema($this->db)) {
            return ['ok' => false, 'errors' => ['_' => 'Commission tables are not set up. Ask your manager to run database migrations.']];
        }

        $itemType = $in['item_type'] ?? '';
        if (!in_array($itemType, ['service', 'product'], true)) {
            return ['ok' => false, 'errors' => ['_' => 'Select a service or product.']];
        }

        $charged = (float) ($in['charged_amount'] ?? 0);
        if ($charged <= 0) {
            return ['ok' => false, 'errors' => ['charged_amount' => 'Enter the amount charged.']];
        }

        $paymentMethod = in_array($in['payment_method'] ?? 'cash', ['cash', 'mpesa', 'credit'], true)
            ? $in['payment_method'] : 'cash';
        $isCredit = $paymentMethod === 'credit';
        if (!empty($in['pending'])) {
            $paymentMethod = 'cash';
            $isCredit = false;
        }
        $customerName = trim($in['customer_name'] ?? '');
        $customerPhone = trim($in['customer_phone'] ?? '');

        if ($isCredit && $customerName === '') {
            return ['ok' => false, 'errors' => ['customer_name' => 'Customer name is required for credit sales.']];
        }
        if ($isCredit && !$this->creditsEnabled($tenantId)) {
            return ['ok' => false, 'errors' => ['_' => 'Credit sales are disabled for this shop.']];
        }

        $quantity = max(0.01, (float) ($in['quantity'] ?? 1));
        $branchId = isset($in['branch_id']) && (int) $in['branch_id'] > 0 ? (int) $in['branch_id'] : null;

        $standardPrice = 0.0;
        $commType = 'percent';
        $commValue = 0.0;
        $itemName = '';
        $serviceId = null;
        $productId = null;
        $expenseTotal = 0.0;
        $expenseRows = [];

        if ($itemType === 'service') {
            $serviceId = (int) ($in['service_id'] ?? 0);
            $svc = (new OfferedServiceService($this->db))->find($tenantId, $serviceId);
            if (!$svc || $svc['status'] !== 'active') {
                return ['ok' => false, 'errors' => ['_' => 'Service not found.']];
            }
            $standardPrice = (float) $svc['charge_amount'];
            $commType = $svc['commission_type'];
            $commValue = (float) $svc['commission_value'];
            $itemName = $svc['name'];
            foreach ($in['expenses'] ?? [] as $exp) {
                $cost = (float) ($exp['cost'] ?? 0);
                $expenseTotal += $cost;
                if (trim($exp['name'] ?? '') !== '') {
                    $expenseRows[] = ['name' => trim($exp['name']), 'cost' => $cost];
                }
            }
        } else {
            $productId = (int) ($in['product_id'] ?? 0);
            $prod = $this->loadProduct($tenantId, $productId);
            if (!$prod) {
                return ['ok' => false, 'errors' => ['_' => 'Product not found.']];
            }
            if ($quantity > (float) $prod['quantity']) {
                return ['ok' => false, 'errors' => ['_' => "Not enough stock for {$prod['name']}."]];
            }
            $standardPrice = (float) $prod['selling_price'];
            $commType = $prod['commission_type'];
            $commValue = (float) $prod['commission_value'];
            $itemName = $prod['name'];
            if ($isCredit && empty($prod['credit_allowed'])) {
                return ['ok' => false, 'errors' => ['_' => 'This product cannot be sold on credit.']];
            }
        }

        [$lineStandard, $lineCharged] = $this->lineAmounts($standardPrice, $charged, $quantity, $itemType);
        $calc = self::calculateCommission($commType, $commValue, $lineStandard, $lineCharged);
        $customerId = null;
        if ($customerName !== '') {
            $customerId = (new CustomerService($this->db))->resolve($tenantId, $customerName, $customerPhone ?: null);
        }

        $ownsTx = !$this->db->inTransaction();
        if ($ownsTx) {
            $this->db->beginTransaction();
        }
        try {
            if ($itemType === 'product' && $productId) {
                if (!$this->decrementProductStock($tenantId, $productId, $quantity)) {
                    if ($ownsTx && $this->db->inTransaction()) { $this->db->rollBack(); }
                    return ['ok' => false, 'errors' => ['_' => 'Stock changed while saving. Please try again.']];
                }
            }

            $stmt = $this->db->prepare(
                'INSERT INTO commission_sales
                 (tenant_id, receipt_number, agent_user_id, branch_id, customer_id, customer_name, customer_phone,
                  item_type, service_id, product_id, item_name, quantity, standard_price, charged_amount,
                  payment_method, is_credit, expense_total, base_commission, overage_commission, total_commission, notes)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $tenantId, 'PENDING', $agentUserId > 0 ? $agentUserId : null, $branchId, $customerId,
                $customerName ?: null, $customerPhone ?: null,
                $itemType, $serviceId, $productId, $itemName, $quantity,
                $standardPrice, $lineCharged, $paymentMethod, $isCredit ? 1 : 0, $expenseTotal,
                $calc['base_commission'], $calc['overage_commission'], $calc['total_commission'],
                trim($in['notes'] ?? '') ?: null,
            ]);
            $saleId = (int) $this->db->lastInsertId();
            $receipt = 'CRS-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE commission_sales SET receipt_number = ? WHERE id = ?')
                ->execute([$receipt, $saleId]);

            if ($expenseRows) {
                $ins = $this->db->prepare(
                    'INSERT INTO commission_sale_expenses (commission_sale_id, expense_name, cost) VALUES (?,?,?)'
                );
                foreach ($expenseRows as $e) {
                    $ins->execute([$saleId, $e['name'], $e['cost']]);
                }
            }

            if ($isCredit && $customerId) {
                (new CustomerService($this->db))->addCredit($tenantId, $customerId, $lineCharged);
            }

            if ($ownsTx) {
                $this->db->commit();
            }

            if (!empty($in['pending']) && $this->hasPaymentWorkflow()) {
                $this->db->prepare(
                    "UPDATE commission_sales SET payment_status = 'pending', recorded_by_user_id = ?
                     WHERE id = ? AND tenant_id = ?"
                )->execute([(int) ($in['recorded_by_user_id'] ?? $agentUserId), $saleId, $tenantId]);
            }

            return [
                'ok' => true, 'id' => $saleId, 'receipt_number' => $receipt,
                'commission' => $calc, 'errors' => [],
            ];
        } catch (\Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) { $this->db->rollBack(); }
            return ['ok' => false, 'errors' => ['_' => 'Could not record sale.']];
        }
    }

    /**
     * Owner backfill: past service/product commission sale without catalog or stock changes.
     * @param array $in staff_id, item_type, item_name, amount, sale_date, payment_method, branch_id?, customer_name?
     */
    public function recordManual(int $tenantId, array $in): array
    {
        if (!self::ensureSchema($this->db)) {
            return ['ok' => false, 'errors' => ['_' => 'Commission tables are not set up. Run fix-all-schema.php once.']];
        }

        $agentId = (int) ($in['staff_id'] ?? 0);
        $itemType = in_array($in['item_type'] ?? '', ['service', 'product'], true) ? $in['item_type'] : '';
        $itemName = trim($in['item_name'] ?? '');
        $amount = round((float) ($in['amount'] ?? 0), 2);
        $saleAt = trim($in['sale_date'] ?? '');
        $method = in_array($in['payment_method'] ?? '', ['cash', 'mpesa'], true) ? $in['payment_method'] : null;

        $errors = [];
        if ($agentId <= 0) {
            $errors['staff_id'] = 'Choose who made this sale.';
        }
        if ($itemType === '') {
            $errors['item_type'] = 'Choose service or product.';
        }
        if ($itemName === '') {
            $errors['item_name'] = 'Enter what was sold.';
        }
        if ($amount <= 0) {
            $errors['amount'] = 'Enter a valid amount.';
        }
        if (!$method) {
            $errors['payment_method'] = 'Choose how it was paid.';
        }
        $ts = strtotime($saleAt);
        if ($saleAt === '' || $ts === false) {
            $errors['sale_date'] = 'Enter a valid date and time.';
        }
        if ($errors) {
            return ['ok' => false, 'errors' => $errors];
        }

        $staffOk = $this->db->prepare(
            'SELECT id FROM users WHERE id = ? AND tenant_id = ? AND is_active = 1 LIMIT 1'
        );
        $staffOk->execute([$agentId, $tenantId]);
        if (!$staffOk->fetch()) {
            return ['ok' => false, 'errors' => ['staff_id' => 'Staff member not found.']];
        }

        $branchId = isset($in['branch_id']) && (int) $in['branch_id'] > 0 ? (int) $in['branch_id'] : null;
        $customerName = trim($in['customer_name'] ?? '');
        $createdAt = date('Y-m-d H:i:s', $ts);
        $ownerId = (int) (\TenantContext::userId() ?? 0);

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO commission_sales
                 (tenant_id, receipt_number, agent_user_id, branch_id, customer_name,
                  item_type, service_id, product_id, item_name, quantity, standard_price, charged_amount,
                  payment_method, is_credit, expense_total, base_commission, overage_commission, total_commission, notes, created_at)
                 VALUES (?,?,?,?,?,?,NULL,NULL,?,?,?,?,?,0,0,0,0,?,?)'
            );
            $stmt->execute([
                $tenantId,
                'PENDING',
                $agentId,
                $branchId,
                $customerName !== '' ? $customerName : null,
                $itemType,
                $itemName,
                1,
                $amount,
                $amount,
                $method,
                0,
                'Manual entry by owner',
                $createdAt,
            ]);
            $saleId = (int) $this->db->lastInsertId();
            $receipt = 'CRS-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
            $this->db->prepare('UPDATE commission_sales SET receipt_number = ? WHERE id = ? AND tenant_id = ?')
                ->execute([$receipt, $saleId, $tenantId]);

            if ($this->hasPaymentWorkflow()) {
                $this->db->prepare(
                    "UPDATE commission_sales SET payment_status = 'paid', paid_at = ?, recorded_by_user_id = ?
                     WHERE id = ? AND tenant_id = ?"
                )->execute([$createdAt, $ownerId ?: $agentId, $saleId, $tenantId]);
            }

            return [
                'ok' => true,
                'id' => $saleId,
                'receipt_number' => $receipt,
                'errors' => [],
            ];
        } catch (Throwable $e) {
            return ['ok' => false, 'errors' => ['_' => 'Could not save this sale. Please try again.']];
        }
    }

    /** Remove a commission/service sale (owner). Restores product stock when applicable. */
    public function deleteSale(int $tenantId, int $saleId): array
    {
        if (!self::ensureSchema($this->db)) {
            return ['ok' => false, 'error' => 'Commission tables are not set up.'];
        }

        $sale = $this->find($tenantId, $saleId);
        if (!$sale) {
            return ['ok' => false, 'error' => 'Sale not found.'];
        }
        if (!empty($sale['payout_id'])) {
            return ['ok' => false, 'error' => 'This sale is part of a commission payout and cannot be deleted.'];
        }
        if (!empty($sale['is_credit'])) {
            return ['ok' => false, 'error' => 'Credit sales cannot be deleted here. Adjust from customer credit instead.'];
        }

        $ownsTx = !$this->db->inTransaction();
        if ($ownsTx) {
            $this->db->beginTransaction();
        }
        try {
            if (($sale['item_type'] ?? '') === 'product' && (int) ($sale['product_id'] ?? 0) > 0) {
                $qty = max(0.01, (float) ($sale['quantity'] ?? 1));
                $this->db->prepare(
                    'UPDATE products SET quantity = quantity + ? WHERE id = ? AND tenant_id = ?'
                )->execute([$qty, (int) $sale['product_id'], $tenantId]);
            }

            if (SchemaHelper::tableExists($this->db, 'commission_sale_expenses')) {
                $this->db->prepare('DELETE FROM commission_sale_expenses WHERE commission_sale_id = ?')
                    ->execute([$saleId]);
            }

            $del = $this->db->prepare('DELETE FROM commission_sales WHERE id = ? AND tenant_id = ?');
            $del->execute([$saleId, $tenantId]);
            if ($del->rowCount() !== 1) {
                if ($ownsTx && $this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                return ['ok' => false, 'error' => 'Could not delete this sale.'];
            }

            if ($ownsTx) {
                $this->db->commit();
            }
            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'error' => 'Could not delete this sale. Please try again.'];
        }
    }

    /**
     * Record multiple services/products in one visit (shared customer & payment).
     * Each line becomes its own commission sale row and receipt.
     */
    public function recordSaleBatch(int $tenantId, int $agentUserId, array $common, array $items): array
    {
        if (!self::ensureSchema($this->db)) {
            return ['ok' => false, 'errors' => ['_' => 'Commission tables are not set up. Ask your manager to run database migrations.']];
        }
        if (!$items) {
            return ['ok' => false, 'errors' => ['_' => 'Add at least one service or product to the sale.']];
        }

        $paymentMethod = in_array($common['payment_method'] ?? 'cash', ['cash', 'mpesa', 'credit'], true)
            ? $common['payment_method'] : 'cash';
        $isCredit = $paymentMethod === 'credit';
        $customerName = trim($common['customer_name'] ?? '');
        $customerPhone = trim($common['customer_phone'] ?? '');
        $branchId = isset($common['branch_id']) && (int) $common['branch_id'] > 0 ? (int) $common['branch_id'] : null;
        $notes = trim($common['notes'] ?? '') ?: null;

        if ($isCredit && $customerName === '') {
            return ['ok' => false, 'errors' => ['customer_name' => 'Customer name is required for credit sales.']];
        }
        if ($isCredit && !$this->creditsEnabled($tenantId)) {
            return ['ok' => false, 'errors' => ['_' => 'Credit sales are disabled for this shop.']];
        }

        $prepared = [];
        $totalCharged = 0.0;
        foreach ($items as $item) {
            $row = $this->prepareSaleItem($tenantId, array_merge($common, $item), $paymentMethod, $isCredit);
            if (!$row['ok']) {
                return $row;
            }
            $prepared[] = $row['data'];
            $totalCharged += $row['data']['charged'];
        }

        $customerId = null;
        if ($customerName !== '') {
            $customerId = (new CustomerService($this->db))->resolve($tenantId, $customerName, $customerPhone ?: null);
        }

        $ownsTx = !$this->db->inTransaction();
        if ($ownsTx) {
            $this->db->beginTransaction();
        }
        try {
            $ids = [];
            $receipts = [];
            $totalCommission = 0.0;
            foreach ($prepared as $row) {
                $inserted = $this->insertPreparedSale(
                    $tenantId, $agentUserId, $row, $customerId, $customerName, $customerPhone, $branchId, $notes
                );
                $ids[] = $inserted['id'];
                $receipts[] = $inserted['receipt_number'];
                $totalCommission += $inserted['commission']['total_commission'];
            }
            if ($isCredit && $customerId) {
                (new CustomerService($this->db))->addCredit($tenantId, $customerId, $totalCharged);
            }
            if ($ownsTx) {
                $this->db->commit();
            }
            return [
                'ok' => true,
                'ids' => $ids,
                'receipt_numbers' => $receipts,
                'id' => $ids[0],
                'receipt_number' => $receipts[0],
                'count' => count($ids),
                'total_commission' => round($totalCommission, 2),
                'errors' => [],
            ];
        } catch (Throwable $e) {
            if ($ownsTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['ok' => false, 'errors' => ['_' => 'Could not record sale.']];
        }
    }

    private function prepareSaleItem(int $tenantId, array $in, string $paymentMethod, bool $isCredit): array
    {
        $itemType = $in['item_type'] ?? '';
        if (!in_array($itemType, ['service', 'product'], true)) {
            return ['ok' => false, 'errors' => ['_' => 'Select a service or product.']];
        }

        $charged = (float) ($in['charged_amount'] ?? 0);
        if ($charged <= 0) {
            return ['ok' => false, 'errors' => ['charged_amount' => 'Enter the amount charged for each item.']];
        }

        $quantity = max(0.01, (float) ($in['quantity'] ?? 1));
        $standardPrice = 0.0;
        $commType = 'percent';
        $commValue = 0.0;
        $itemName = '';
        $serviceId = null;
        $productId = null;
        $expenseTotal = 0.0;
        $expenseRows = [];

        if ($itemType === 'service') {
            $serviceId = (int) ($in['service_id'] ?? 0);
            $svc = (new OfferedServiceService($this->db))->find($tenantId, $serviceId);
            if (!$svc || $svc['status'] !== 'active') {
                return ['ok' => false, 'errors' => ['_' => 'Service not found: ' . ($in['item_name'] ?? '')]];
            }
            $standardPrice = (float) $svc['charge_amount'];
            $commType = $svc['commission_type'];
            $commValue = (float) $svc['commission_value'];
            $itemName = $svc['name'];
            foreach ($in['expenses'] ?? [] as $exp) {
                $cost = (float) ($exp['cost'] ?? 0);
                $expenseTotal += $cost;
                if (trim($exp['name'] ?? '') !== '') {
                    $expenseRows[] = ['name' => trim($exp['name']), 'cost' => $cost];
                }
            }
        } else {
            $productId = (int) ($in['product_id'] ?? 0);
            $prod = $this->loadProduct($tenantId, $productId);
            if (!$prod) {
                return ['ok' => false, 'errors' => ['_' => 'Product not found.']];
            }
            if ($quantity > (float) $prod['quantity']) {
                return ['ok' => false, 'errors' => ['_' => "Not enough stock for {$prod['name']}."]];
            }
            $standardPrice = (float) $prod['selling_price'];
            $commType = $prod['commission_type'];
            $commValue = (float) $prod['commission_value'];
            $itemName = $prod['name'];
            if ($isCredit && empty($prod['credit_allowed'])) {
                return ['ok' => false, 'errors' => ['_' => $itemName . ' cannot be sold on credit.']];
            }
        }

        [$lineStandard, $lineCharged] = $this->lineAmounts($standardPrice, $charged, $quantity, $itemType);
        $calc = self::calculateCommission($commType, $commValue, $lineStandard, $lineCharged);
        return [
            'ok' => true,
            'data' => [
                'item_type' => $itemType,
                'service_id' => $serviceId,
                'product_id' => $productId,
                'item_name' => $itemName,
                'quantity' => $quantity,
                'standard_price' => $standardPrice,
                'charged' => $lineCharged,
                'payment_method' => $paymentMethod,
                'is_credit' => $isCredit,
                'expense_total' => $expenseTotal,
                'expense_rows' => $expenseRows,
                'commission' => $calc,
            ],
        ];
    }

    private function insertPreparedSale(
        int $tenantId,
        int $agentUserId,
        array $row,
        ?int $customerId,
        string $customerName,
        string $customerPhone,
        ?int $branchId,
        ?string $notes
    ): array {
        if ($row['item_type'] === 'product' && !empty($row['product_id'])) {
            if (!$this->decrementProductStock($tenantId, (int) $row['product_id'], (float) $row['quantity'])) {
                throw new RuntimeException('Stock decrement failed');
            }
        }

        $calc = $row['commission'];
        $stmt = $this->db->prepare(
            'INSERT INTO commission_sales
             (tenant_id, receipt_number, agent_user_id, branch_id, customer_id, customer_name, customer_phone,
              item_type, service_id, product_id, item_name, quantity, standard_price, charged_amount,
              payment_method, is_credit, expense_total, base_commission, overage_commission, total_commission, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([
            $tenantId, 'PENDING', $agentUserId > 0 ? $agentUserId : null, $branchId, $customerId,
            $customerName ?: null, $customerPhone ?: null,
            $row['item_type'], $row['service_id'], $row['product_id'], $row['item_name'], $row['quantity'],
            $row['standard_price'], $row['charged'], $row['payment_method'], $row['is_credit'] ? 1 : 0,
            $row['expense_total'], $calc['base_commission'], $calc['overage_commission'], $calc['total_commission'],
            $notes,
        ]);
        $saleId = (int) $this->db->lastInsertId();
        $receipt = 'CRS-' . str_pad((string) $saleId, 6, '0', STR_PAD_LEFT);
        $this->db->prepare('UPDATE commission_sales SET receipt_number = ? WHERE id = ?')
            ->execute([$receipt, $saleId]);

        if ($row['expense_rows']) {
            $ins = $this->db->prepare(
                'INSERT INTO commission_sale_expenses (commission_sale_id, expense_name, cost) VALUES (?,?,?)'
            );
            foreach ($row['expense_rows'] as $e) {
                $ins->execute([$saleId, $e['name'], $e['cost']]);
            }
        }

        return ['id' => $saleId, 'receipt_number' => $receipt, 'commission' => $calc];
    }

    /** Unpaid commission total for an agent (current payout cycle). */
    public function unpaidTotal(int $tenantId, int $agentUserId): float
    {
        return $this->scalar(
            'SELECT COALESCE(SUM(total_commission), 0) FROM commission_sales
              WHERE tenant_id = ? AND agent_user_id = ? AND payout_id IS NULL',
            [$tenantId, $agentUserId]
        );
    }

    /** Commission earned today (unpaid only). */
    public function todayTotal(int $tenantId, int $agentUserId): float
    {
        return $this->scalar(
            "SELECT COALESCE(SUM(total_commission), 0) FROM commission_sales
              WHERE tenant_id = ? AND agent_user_id = ? AND payout_id IS NULL
                AND DATE(created_at) = CURDATE()",
            [$tenantId, $agentUserId]
        );
    }

    public function unpaidSales(int $tenantId, int $agentUserId): array
    {
        $paidFilter = $this->hasPaymentWorkflow()
            ? " AND (cs.payment_status = 'paid' OR cs.payment_status IS NULL)"
            : '';
        return $this->rows(
            "SELECT cs.*, b.title AS branch_name
               FROM commission_sales cs
          LEFT JOIN branches b ON b.id = cs.branch_id
              WHERE cs.tenant_id = ? AND cs.agent_user_id = ? AND cs.payout_id IS NULL{$paidFilter}
           ORDER BY cs.branch_id ASC, cs.created_at DESC",
            [$tenantId, $agentUserId]
        );
    }

    /** Commission sales for owner view, optionally filtered by branch. */
    public function commissionSalesForTenant(int $tenantId, string $period = 'all', ?int $branchId = null): array
    {
        if (!$this->hasCommissionSales()) {
            return [];
        }
        $periodSql = match ($period) {
            'today' => 'AND DATE(cs.created_at) = CURDATE()',
            'week'  => 'AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
            'month' => 'AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
            default => '',
        };
        $branchSql = '';
        $params = [$tenantId];
        if ($branchId !== null && $branchId > 0) {
            $branchSql = ' AND cs.branch_id = ?';
            $params[] = $branchId;
        }
        return $this->rows(
            "SELECT cs.*, b.title AS branch_name, u.username AS agent_name
               FROM commission_sales cs
          LEFT JOIN branches b ON b.id = cs.branch_id
          LEFT JOIN users u ON u.id = cs.agent_user_id
              WHERE cs.tenant_id = ? {$periodSql}{$branchSql}
           ORDER BY cs.created_at DESC
              LIMIT 1000",
            $params
        );
    }

    /** Group commission rows by branch title for display. */
    public static function branchBreakdown(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            $name = ($r['branch_name'] ?? '') !== '' ? $r['branch_name'] : 'No branch';
            if (!isset($out[$name])) {
                $out[$name] = ['count' => 0, 'revenue' => 0.0, 'commission' => 0.0];
            }
            $out[$name]['count']++;
            $out[$name]['revenue'] += (float) ($r['charged_amount'] ?? 0);
            $out[$name]['commission'] += (float) ($r['total_commission'] ?? 0);
        }
        uasort($out, fn($a, $b) => $b['revenue'] <=> $a['revenue']);
        return $out;
    }

    /** Summary per agent for owner payout screen. */
    public function agentSummaries(int $tenantId): array
    {
        if (!$this->hasCommissionSales()) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                "SELECT u.id, u.username, u.email, r.role_name,
                        COALESCE(SUM(cs.total_commission), 0) AS unpaid_total,
                        COUNT(cs.id) AS sale_count
                   FROM users u
                   JOIN roles r ON r.id = u.role_id
              LEFT JOIN commission_sales cs ON cs.agent_user_id = u.id
                        AND cs.tenant_id = u.tenant_id AND cs.payout_id IS NULL
                  WHERE u.tenant_id = ? AND r.role_name IN ('sales_agent', 'staff')
               GROUP BY u.id, u.username, u.email, r.role_name
                 HAVING unpaid_total > 0 OR r.role_name = 'sales_agent'
               ORDER BY unpaid_total DESC, u.username ASC"
            );
            $stmt->execute([$tenantId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    public function find(int $tenantId, int $id): ?array
    {
        if (!$this->hasCommissionSales()) {
            return null;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT * FROM commission_sales WHERE id = ? AND tenant_id = ? LIMIT 1'
            );
            $stmt->execute([$id, $tenantId]);
            $row = $stmt->fetch();
            return $row ?: null;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function expenses(int $tenantId, int $saleId): array
    {
        if (!SchemaHelper::tableExists($this->db, 'commission_sale_expenses')) {
            return [];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT e.expense_name, e.cost
                   FROM commission_sale_expenses e
                   JOIN commission_sales cs ON cs.id = e.commission_sale_id
                  WHERE e.commission_sale_id = ? AND cs.tenant_id = ?
               ORDER BY e.id'
            );
            $stmt->execute([$saleId, $tenantId]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Super confirms payout — marks unpaid sales as paid and resets balance. */
    public function payAgent(int $tenantId, int $agentUserId, int $paidBy, ?string $notes = null): array
    {
        if (!SchemaHelper::tableExists($this->db, 'commission_payouts')) {
            return ['ok' => false, 'error' => 'Commission payout tables are not set up. Run database migrations first.'];
        }

        $unpaid = $this->unpaidSales($tenantId, $agentUserId);
        if (!$unpaid) {
            return ['ok' => false, 'error' => 'No unpaid commission for this agent.'];
        }
        $amount = array_sum(array_column($unpaid, 'total_commission'));

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO commission_payouts (tenant_id, agent_user_id, amount_paid, sales_count, paid_by, notes)
                 VALUES (?,?,?,?,?,?)'
            );
            $stmt->execute([$tenantId, $agentUserId, $amount, count($unpaid), $paidBy, $notes]);
            $payoutId = (int) $this->db->lastInsertId();

            $upd = $this->db->prepare(
                'UPDATE commission_sales SET payout_id = ? WHERE tenant_id = ? AND agent_user_id = ? AND payout_id IS NULL'
            );
            $upd->execute([$payoutId, $tenantId, $agentUserId]);
            $this->db->commit();
            return ['ok' => true, 'payout_id' => $payoutId, 'amount' => $amount];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) { $this->db->rollBack(); }
            return ['ok' => false, 'error' => 'Payout failed.'];
        }
    }

    public function payoutHistory(int $tenantId, ?int $agentUserId = null): array
    {
        if (!SchemaHelper::tableExists($this->db, 'commission_payouts')) {
            return [];
        }
        $sql = 'SELECT cp.*, u.username AS agent_name, p.username AS paid_by_name
                  FROM commission_payouts cp
                  JOIN users u ON u.id = cp.agent_user_id
                  JOIN users p ON p.id = cp.paid_by
                 WHERE cp.tenant_id = ?';
        $params = [$tenantId];
        if ($agentUserId) {
            $sql .= ' AND cp.agent_user_id = ?';
            $params[] = $agentUserId;
        }
        $sql .= ' ORDER BY cp.paid_at DESC LIMIT 50';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) {
            return [];
        }
    }

    private function creditsEnabled(int $tenantId): bool
    {
        if (!SchemaHelper::columnExists($this->db, 'tenants', 'credits_enabled')) {
            return true;
        }
        $stmt = $this->db->prepare('SELECT credits_enabled FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return (bool) $stmt->fetchColumn();
    }

    private function serviceCreditsEnabled(int $tenantId): bool
    {
        if (!SchemaHelper::columnExists($this->db, 'tenants', 'service_credits_enabled')) {
            return !empty($this->creditsEnabled($tenantId));
        }
        $stmt = $this->db->prepare('SELECT service_credits_enabled, credits_enabled FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        return !empty($row['service_credits_enabled']) || !empty($row['credits_enabled']);
    }

    private function defaultCreditDays(int $tenantId): int
    {
        if (!SchemaHelper::columnExists($this->db, 'tenants', 'default_credit_days')) {
            return 30;
        }
        $stmt = $this->db->prepare('SELECT default_credit_days FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $days = (int) $stmt->fetchColumn();
        return $days > 0 ? $days : 30;
    }

    private function loadProduct(int $tenantId, int $productId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT name, selling_price, quantity, commission_type, commission_value, credit_allowed
               FROM products WHERE id = ? AND tenant_id = ? AND status = 'active' LIMIT 1"
        );
        $stmt->execute([$productId, $tenantId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{0: float, 1: float} line standard and line charged totals */
    private function lineAmounts(float $unitStandard, float $unitCharged, float $quantity, string $itemType): array
    {
        if ($itemType === 'product' && $quantity > 1) {
            return [round($unitStandard * $quantity, 2), round($unitCharged * $quantity, 2)];
        }
        return [round($unitStandard, 2), round($unitCharged, 2)];
    }

    private function decrementProductStock(int $tenantId, int $productId, float $quantity): bool
    {
        $lock = $this->db->prepare(
            "SELECT quantity FROM products WHERE id = ? AND tenant_id = ? AND status = 'active' FOR UPDATE"
        );
        $lock->execute([$productId, $tenantId]);
        $stock = $lock->fetchColumn();
        if ($stock === false || $quantity > (float) $stock) {
            return false;
        }
        $dec = $this->db->prepare(
            'UPDATE products SET quantity = quantity - ? WHERE id = ? AND tenant_id = ? AND quantity >= ?'
        );
        $dec->execute([$quantity, $productId, $tenantId, $quantity]);
        return $dec->rowCount() === 1;
    }
}
