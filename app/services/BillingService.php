<?php
// app/services/BillingService.php
// Subscription payment via M-Pesa STK push.
//   initiate()       -> sends the prompt to the owner's phone, records a pending row
//   handleCallback() -> on a successful payment, activates the owner + subscription
//   status()         -> what the registration page polls while waiting
//
// NOTE: live STK requires real Daraja credentials in app/config/mpesa.php and a
// publicly reachable callback URL. The activation logic here is what the callback
// triggers, and is tested with a simulated callback.

use Models\SubscriptionModel;

class BillingService
{
    private PDO $db;
    private ?MpesaService $mpesa;
    /** @var callable|null Called with activation info after a successful payment (best-effort). */
    private $onActivated;

    public function __construct(PDO $db, ?MpesaService $mpesa = null, ?callable $onActivated = null)
    {
        $this->db = $db;
        $this->mpesa = $mpesa;
        $this->onActivated = $onActivated;
    }

    /**
     * @param array $ctx tenant_id,user_id,subscription_id,plan_id,interval,amount,phone,account_ref,desc
     * @return array ['ok'=>bool, 'checkout_request_id'=>?string, 'error'=>?string]
     */
    public function initiate(array $ctx): array
    {
        if ($this->mpesa === null) {
            return ['ok' => false, 'error' => 'Billing is not configured.'];
        }
        $phone = $this->mpesa->normalizePhone((string) $ctx['phone']);
        if ($phone === null) {
            return ['ok' => false, 'error' => 'That phone number doesn\'t look like a valid M-Pesa number.'];
        }

        $amount = max(1, (int) ceil((float) $ctx['amount']));

        // Record the attempt up front (pending) so a callback always has a row to find.
        $stmt = $this->db->prepare(
            'INSERT INTO subscription_stk
               (tenant_id, user_id, subscription_id, plan_id, billing_interval, amount, phone, status)
             VALUES (:t, :u, :s, :p, :i, :a, :ph, \'pending\')'
        );
        $stmt->execute([
            ':t' => $ctx['tenant_id'], ':u' => $ctx['user_id'], ':s' => $ctx['subscription_id'] ?? null,
            ':p' => $ctx['plan_id'], ':i' => $ctx['interval'], ':a' => $amount, ':ph' => $phone,
        ]);
        $stkId = (int) $this->db->lastInsertId();

        try {
            $resp = $this->mpesa->stkPush($phone, $amount, $ctx['account_ref'] ?? ('SUB' . $ctx['tenant_id']), $ctx['desc'] ?? 'Subscription');
        } catch (\Throwable $e) {
            $this->db->prepare('UPDATE subscription_stk SET status=\'failed\', result_desc=:d WHERE id=:id')
                ->execute([':d' => 'Could not reach M-Pesa', ':id' => $stkId]);
            return ['ok' => false, 'error' => 'Could not reach M-Pesa right now. Please try again.'];
        }

        $checkout = $resp['CheckoutRequestID'] ?? null;
        $merchant = $resp['MerchantRequestID'] ?? null;
        $code     = (string) ($resp['ResponseCode'] ?? '1');

        $this->db->prepare('UPDATE subscription_stk SET checkout_request_id=:c, merchant_request_id=:m WHERE id=:id')
            ->execute([':c' => $checkout, ':m' => $merchant, ':id' => $stkId]);

        if ($code !== '0' || !$checkout) {
            $this->db->prepare('UPDATE subscription_stk SET status=\'failed\', result_desc=:d WHERE id=:id')
                ->execute([':d' => $resp['errorMessage'] ?? 'Payment request was not accepted', ':id' => $stkId]);
            return ['ok' => false, 'error' => $resp['errorMessage'] ?? 'M-Pesa did not accept the request. Please try again.'];
        }

        return ['ok' => true, 'checkout_request_id' => $checkout, 'error' => null];
    }

    /**
     * Process a Daraja STK callback. Idempotent: a repeated callback for an
     * already-finalised row is a no-op.
     * @return array ['ok'=>bool, 'status'=>?string]
     */
    public function handleCallback(array $body): array
    {
        $cb = MpesaService::parseCallback($body);
        if (empty($cb['valid']) || empty($cb['checkout_request_id'])) {
            return ['ok' => false, 'status' => null];
        }

        $stmt = $this->db->prepare('SELECT * FROM subscription_stk WHERE checkout_request_id = ? LIMIT 1');
        $stmt->execute([$cb['checkout_request_id']]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'status' => null];
        }
        if ($row['status'] !== 'pending') {
            return ['ok' => true, 'status' => $row['status']]; // already handled
        }

        if ((int) $cb['result_code'] === 0) {
            $this->db->prepare(
                'UPDATE subscription_stk SET status=\'success\', result_code=0, result_desc=:d, mpesa_receipt=:r WHERE id=:id'
            )->execute([':d' => $cb['result_desc'] ?? 'Success', ':r' => $cb['receipt'] ?? null, ':id' => $row['id']]);

            $this->activateAccount($row);

            // Best-effort welcome/receipt notification. A mail failure must NEVER
            // undo the activation, so it is fully isolated.
            if ($this->onActivated) {
                try {
                    ($this->onActivated)($this->gatherActivationInfo($row, $cb['receipt'] ?? null));
                } catch (\Throwable $e) {
                    error_log('BillingService onActivated failed: ' . $e->getMessage());
                }
            }

            return ['ok' => true, 'status' => 'success'];
        }

        // 1032 = request cancelled by user; everything else is a failure.
        $status = ((int) $cb['result_code'] === 1032) ? 'cancelled' : 'failed';
        $this->db->prepare('UPDATE subscription_stk SET status=:st, result_code=:c, result_desc=:d WHERE id=:id')
            ->execute([':st' => $status, ':c' => (int) $cb['result_code'], ':d' => $cb['result_desc'] ?? '', ':id' => $row['id']]);
        return ['ok' => true, 'status' => $status];
    }

    /** Activate the owner account and start the subscription period. */
    private function activateAccount(array $stk): void
    {
        $now = date('Y-m-d H:i:s');

        $this->db->prepare(
            'UPDATE users SET is_active=1, email_verified=1, activated_at=:n, activation_token=NULL, activation_expires=NULL WHERE id=:id'
        )->execute([':n' => $now, ':id' => $stk['user_id']]);

        $subs = new SubscriptionModel($this->db);
        $sub = $subs->forTenant((int) $stk['tenant_id']);
        if ($sub) {
            $end = Billing::periodEnd($sub['billing_interval'], $now);
            $subs->activatePeriod((int) $sub['id'], $now, $end);
        }
    }

    /** Recipient + receipt details for the welcome email, read after activation. */
    private function gatherActivationInfo(array $stk, ?string $receipt): array
    {
        $hasSub = !empty($stk['subscription_id']);
        $sql = 'SELECT u.email, u.username, t.name AS business,
                       s.billing_interval, s.amount, s.current_period_end, p.name AS plan_name
                  FROM users u
                  JOIN tenants t ON t.id = u.tenant_id
                  JOIN subscriptions s ON s.tenant_id = t.id
             LEFT JOIN subscription_plans p ON p.id = s.plan_id
                 WHERE u.id = ? ' . ($hasSub ? 'AND s.id = ? ' : '') . 'ORDER BY s.id DESC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($hasSub ? [(int) $stk['user_id'], (int) $stk['subscription_id']] : [(int) $stk['user_id']]);
        $r = $stmt->fetch() ?: [];

        return [
            'email'      => $r['email'] ?? null,
            'business'   => $r['business'] ?? '',
            'plan'       => $r['plan_name'] ?? '',
            'interval'   => $r['billing_interval'] ?? ($stk['billing_interval'] ?? ''),
            'amount'     => $r['amount'] ?? ($stk['amount'] ?? null),
            'period_end' => $r['current_period_end'] ?? null,
            'receipt'    => $receipt,
        ];
    }

    /** Current state of a payment attempt (for polling). */
    public function status(string $checkoutRequestId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT status, user_id, tenant_id FROM subscription_stk WHERE checkout_request_id = ? LIMIT 1'
        );
        $stmt->execute([$checkoutRequestId]);
        return $stmt->fetch() ?: null;
    }
}