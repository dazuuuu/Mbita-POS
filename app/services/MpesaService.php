<?php
// app/services/MpesaService.php
// Thin, testable wrapper around the Safaricom Daraja STK Push API.

class MpesaService {
    private $cfg;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
    }

    private function baseUrl(): string {
        return (($this->cfg['env'] ?? 'sandbox') === 'production')
            ? 'https://api.safaricom.co.ke'
            : 'https://sandbox.safaricom.co.ke';
    }

    /**
     * Normalise Kenyan numbers to the 2547XXXXXXXX / 2541XXXXXXXX form Daraja wants.
     * Returns null if it doesn't look like a valid MSISDN.
     */
    public function normalizePhone(string $phone): ?string {
        $d = preg_replace('/\D+/', '', $phone);
        if ($d === '') return null;

        // 07XXXXXXXX or 01XXXXXXXX (local, 10 digits)
        if (strlen($d) === 10 && $d[0] === '0') {
            $d = '254' . substr($d, 1);
        }
        // 7XXXXXXXX or 1XXXXXXXX (9 digits, missing leading 0)
        elseif (strlen($d) === 9 && ($d[0] === '7' || $d[0] === '1')) {
            $d = '254' . $d;
        }

        if (strlen($d) === 12 && strpos($d, '254') === 0 && ($d[3] === '7' || $d[3] === '1')) {
            return $d;
        }
        return null;
    }

    public function timestamp(): string {
        return date('YmdHis');
    }

    public function password(string $timestamp): string {
        return base64_encode(($this->cfg['shortcode'] ?? '') . ($this->cfg['passkey'] ?? '') . $timestamp);
    }

    public function buildStkPayload(string $phone, int $amount, string $accountRef, string $desc): array {
        $ts = $this->timestamp();
        return [
            'BusinessShortCode' => $this->cfg['shortcode'] ?? '',
            'Password'          => $this->password($ts),
            'Timestamp'         => $ts,
            'TransactionType'   => $this->cfg['transaction_type'] ?? 'CustomerPayBillOnline',
            'Amount'            => $amount,
            'PartyA'            => $phone,
            'PartyB'            => $this->cfg['shortcode'] ?? '',
            'PhoneNumber'       => $phone,
            'CallBackURL'       => $this->cfg['callback_url'] ?? '',
            'AccountReference'  => substr($accountRef, 0, 12),
            'TransactionDesc'   => substr($desc, 0, 60) ?: 'Payment',
        ];
    }

    /** OAuth access token (valid ~1h). Throws on failure. */
    public function accessToken(): string {
        $url = $this->baseUrl() . '/oauth/v1/generate?grant_type=client_credentials';
        $cred = base64_encode(($this->cfg['consumer_key'] ?? '') . ':' . ($this->cfg['consumer_secret'] ?? ''));
        $res = $this->httpGet($url, ['Authorization: Basic ' . $cred]);
        $j = json_decode($res, true);
        if (empty($j['access_token'])) {
            throw new RuntimeException('Failed to obtain M-Pesa access token');
        }
        return $j['access_token'];
    }

    /** Trigger an STK push. Returns the decoded Daraja response array. */
    public function stkPush(string $phone, int $amount, string $accountRef, string $desc): array {
        $token = $this->accessToken();
        $url = $this->baseUrl() . '/mpesa/stkpush/v1/processrequest';
        $payload = $this->buildStkPayload($phone, $amount, $accountRef, $desc);
        $res = $this->httpPost($url, $payload, [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        $j = json_decode($res, true);
        return is_array($j) ? $j : ['ResponseCode' => '1', 'errorMessage' => 'Invalid response from M-Pesa'];
    }

    /**
     * Extract the useful fields from a Daraja STK callback body.
     * Returns ['valid'=>bool, 'result_code'=>int, 'checkout_request_id'=>..,
     *          'receipt'=>.., 'phone'=>.., 'amount'=>..].
     */
    public static function parseCallback(array $body): array {
        $cb = $body['Body']['stkCallback'] ?? null;
        if (!$cb) return ['valid' => false];

        $out = [
            'valid'               => true,
            'merchant_request_id' => $cb['MerchantRequestID'] ?? null,
            'checkout_request_id' => $cb['CheckoutRequestID'] ?? null,
            'result_code'         => (int) ($cb['ResultCode'] ?? -1),
            'result_desc'         => $cb['ResultDesc'] ?? '',
        ];
        foreach (($cb['CallbackMetadata']['Item'] ?? []) as $m) {
            switch ($m['Name'] ?? '') {
                case 'MpesaReceiptNumber': $out['receipt'] = $m['Value'] ?? null; break;
                case 'PhoneNumber':        $out['phone']   = (string) ($m['Value'] ?? ''); break;
                case 'Amount':             $out['amount']  = $m['Value'] ?? null; break;
            }
        }
        return $out;
    }

    // --- HTTP (protected so tests can stub them) -----------------------------

    protected function httpGet(string $url, array $headers): string {
        return $this->curl($url, 'GET', null, $headers);
    }

    protected function httpPost(string $url, array $payload, array $headers): string {
        return $this->curl($url, 'POST', json_encode($payload), $headers);
    }

    private function curl(string $url, string $method, ?string $body, array $headers): string {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);

        // SSL handling. On Windows/Ampps, cURL often lacks a CA bundle and fails
        // with "SSL certificate problem". Point cacert_path at a cacert.pem to fix
        // it properly; only set verify_ssl=false as a last resort on local dev.
        $verify = $this->cfg['verify_ssl'] ?? true;
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verify);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verify ? 2 : 0);
        if (!empty($this->cfg['cacert_path']) && is_file($this->cfg['cacert_path'])) {
            curl_setopt($ch, CURLOPT_CAINFO, $this->cfg['cacert_path']);
        }

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $res = curl_exec($ch);
        if ($res === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException('M-Pesa request failed: ' . $err);
        }
        curl_close($ch);
        return $res;
    }
}