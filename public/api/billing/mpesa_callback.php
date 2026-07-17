<?php
// public/api/billing/mpesa_callback.php
// Safaricom Daraja calls this server-to-server after an STK push. A successful
// result activates the owner + subscription and sends the welcome/receipt email.
require_once __DIR__ . '/../../../app/app.php';
require_once ROOT_PATH . '/app/services/emails/welcome_email.php';

$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (is_array($body)) {
    // Login link for the email — set app url in app/config/app.php (['url' => 'https://yourdomain']).
    $appCfg   = is_file(ROOT_PATH . '/app/config/app.php') ? require ROOT_PATH . '/app/config/app.php' : [];
    $appUrl   = rtrim($appCfg['url'] ?? 'http://localhost/Curlz', '/');
    $loginUrl = $appUrl . '/public/auth/login.php';

    $mailer = new MailService();

    $onActivated = function (array $info) use ($mailer, $loginUrl) {
        if (empty($info['email'])) { return; }
        $msg = build_welcome_email($info['business'] ?: 'there', [
            'plan'       => $info['plan'],
            'interval'   => $info['interval'],
            'amount'     => $info['amount'],
            'receipt'    => $info['receipt'],
            'period_end' => $info['period_end'],
            'login_url'  => $loginUrl,
        ]);
        $mailer->send($info['email'], $msg['subject'], $msg['html']);
    };

    try {
        (new BillingService(Database::pdo(), null, $onActivated))->handleCallback($body);
    } catch (\Throwable $e) {
        error_log('mpesa_callback: ' . $e->getMessage());
    }
}

// Always acknowledge so Safaricom doesn't retry endlessly.
header('Content-Type: application/json');
echo json_encode(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);