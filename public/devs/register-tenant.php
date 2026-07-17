<?php
// public/devs/register-tenant.php
// DEV / SETUP TOOL — create a new tenant and its owner account, then email the
// owner their login credentials using the same PHPMailer + mail config the rest
// of the app uses (app/config/mail.php). Key-guarded. DELETE or restrict via
// web-server IP allowlist before going to production.
//
//   http://localhost{your-base-path}/public/devs/register-tenant.php?key=curlz-dev
//   Set base_path in app/config/paths.php

declare(strict_types=1);
require_once __DIR__ . '/../../app/app.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailerException;

// ── Key guard ─────────────────────────────────────────────────────────────────
const DEV_KEY = 'curlz-dev';   // change before deploying; remove the file entirely at launch
if (!hash_equals(DEV_KEY, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit('Forbidden — append ?key=curlz-dev to the URL.');
}

// ── Load mail config (same file mail-test.php uses) ──────────────────────────
$cfg = is_file(ROOT_PATH . '/app/config/mail.php')
     ? require ROOT_PATH . '/app/config/mail.php'
     : [];

$phpmailerAvailable = class_exists(PHPMailer::class);

// ── State ─────────────────────────────────────────────────────────────────────
$errors     = [];
$success    = null;   // summary string shown in the green banner
$mailResult = null;   // 'ok' | 'error' | 'skipped'
$mailError  = '';

// ─────────────────────────────────────────────────────────────────────────────
// POST handler
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // ── Collect & sanitise ────────────────────────────────────────────────────
    $name          = trim($_POST['name']           ?? '');
    $slug          = trim($_POST['slug']           ?? '');
    $status        = $_POST['status']              ?? 'active';
    $ownerName     = trim($_POST['owner_name']     ?? '');
    $ownerEmail    = trim($_POST['owner_email']    ?? '');
    $ownerPassword = $_POST['owner_password']      ?? '';
    $ownerPhone    = trim($_POST['owner_phone']    ?? '');
    $businessType  = $_POST['business_type']       ?? 'shop';

    // ── Validate ──────────────────────────────────────────────────────────────
    if ($name === '') {
        $errors[] = 'Shop name is required.';
    }
    if ($slug === '') {
        $errors[] = 'Slug is required.';
    } elseif (!preg_match('/^[a-z0-9\-]+$/', $slug)) {
        $errors[] = 'Slug may only contain lowercase letters, numbers, and hyphens.';
    }
    if (!in_array($status, ['active', 'suspended', 'cancelled'], true)) {
        $errors[] = 'Invalid status value.';
    }
    if ($ownerName === '') {
        $errors[] = 'Owner name is required.';
    }
    if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid owner e-mail is required.';
    }
    if (strlen($ownerPassword) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    if (!in_array($businessType, ['shop', 'barbershop_salon'], true)) {
        $errors[] = 'Choose a valid business type.';
    }

    if (empty($errors)) {
        $pdo = Database::pdo();

        try {
            $pdo->beginTransaction();

            // 1. Slug uniqueness check
            $chk = $pdo->prepare('SELECT id FROM tenants WHERE slug = ? LIMIT 1');
            $chk->execute([$slug]);
            if ($chk->fetchColumn()) {
                $errors[] = 'That slug is already taken — choose another.';
                $pdo->rollBack();
            } else {

                Schema025Service::ensureApplied($pdo);

                // 2. Insert tenant row
                $tenantCols = 'name, slug, status';
                $tenantVals = ':name, :slug, :status';
                $tenantParams = [':name' => $name, ':slug' => $slug, ':status' => $status];
                if (SchemaHelper::columnExists($pdo, 'tenants', 'business_type')) {
                    $tenantCols .= ', business_type';
                    $tenantVals .= ', :business_type';
                    $tenantParams[':business_type'] = $businessType;
                }
                $pdo->prepare("INSERT INTO tenants ({$tenantCols}) VALUES ({$tenantVals})")->execute($tenantParams);
                $tenantId = (int) $pdo->lastInsertId();

                // 3. Insert owner user
                $roleId = (int) $pdo->query("SELECT id FROM roles WHERE role_name = 'tenant_owner' LIMIT 1")->fetchColumn();
                if (!$roleId) {
                    throw new RuntimeException('tenant_owner role missing — run migration 014.');
                }
                $hash = password_hash($ownerPassword, PASSWORD_DEFAULT);
                $pdo->prepare(
                    'INSERT INTO users (tenant_id, username, email, password_hash, role_id, is_active, email_verified)
                     VALUES (:tenant_id, :username, :email, :password_hash, :role_id, 1, 1)'
                )->execute([
                    ':tenant_id'     => $tenantId,
                    ':username'      => $ownerName,
                    ':email'         => $ownerEmail,
                    ':password_hash' => $hash,
                    ':role_id'       => $roleId,
                ]);
                $ownerId = (int) $pdo->lastInsertId();

                if ($ownerPhone !== '') {
                    $pdo->prepare(
                        'INSERT INTO user_profiles (user_id, phone) VALUES (?, ?)
                         ON DUPLICATE KEY UPDATE phone = VALUES(phone)'
                    )->execute([$ownerId, $ownerPhone]);
                }

                // 4. Back-fill owner_user_id
                $pdo->prepare('UPDATE tenants SET owner_user_id = ? WHERE id = ?')
                    ->execute([$ownerId, $tenantId]);

                $pdo->commit();

                $success = "Tenant <strong>" . htmlspecialchars($name) . "</strong> created "
                         . "(ID&nbsp;$tenantId) with owner account for "
                         . htmlspecialchars($ownerEmail) . ".";

                // ── Send credentials email ────────────────────────────────────
                if (!$phpmailerAvailable) {
                    $mailResult = 'skipped';
                    $mailError  = 'PHPMailer is not loaded — credentials email was NOT sent. '
                                . 'Run <code>composer require phpmailer/phpmailer</code>.';
                } elseif (empty($cfg['host']) || empty($cfg['username'])) {
                    $mailResult = 'skipped';
                    $mailError  = 'Mail config is incomplete (app/config/mail.php). '
                                . 'Credentials email was NOT sent — share them manually.';
                } else {
                    try {
                        $mail = new PHPMailer(true);

                        // Use same debug level as mail-test.php but discard output here
                        $mail->SMTPDebug   = SMTP::DEBUG_OFF;
                        $mail->isSMTP();
                        $mail->Host        = (string) $cfg['host'];
                        $mail->Port        = (int)   ($cfg['port'] ?? 587);

                        $enc = (string) ($cfg['encryption'] ?? 'tls');
                        if ($enc === 'tls') {
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        } elseif ($enc === 'ssl') {
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        } else {
                            $mail->SMTPSecure  = '';
                            $mail->SMTPAutoTLS = false;
                        }

                        if (!empty($cfg['username'])) {
                            $mail->SMTPAuth = true;
                            $mail->Username = (string) $cfg['username'];
                            $mail->Password = (string) ($cfg['password'] ?? '');
                        }

                        $mail->Timeout = 15;
                        $mail->setFrom(
                            (string) ($cfg['from_email'] ?? 'no-reply@localhost'),
                            (string) ($cfg['from_name']  ?? 'Curlz POS')
                        );
                        $mail->addAddress($ownerEmail, $ownerName);

                        $mail->isHTML(true);
                        $mail->Subject = 'Your ' . htmlspecialchars($name) . ' account is ready';

                        // ── HTML email body ───────────────────────────────────
                        $fromName = htmlspecialchars((string) ($cfg['from_name'] ?? 'Curlz POS'));
                        $safeShop = htmlspecialchars($name);
                        $safeOwner = htmlspecialchars($ownerName);
                        $safeEmail = htmlspecialchars($ownerEmail);
                        $safePhone = htmlspecialchars($ownerPhone ?: '—');
                        $loginUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                                   . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                                   . public_path('auth/login.php');

                        $mail->Body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:-apple-system,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a;">
  <table width="100%" cellpadding="0" cellspacing="0" style="padding:32px 0;">
    <tr><td align="center">
      <table width="540" cellpadding="0" cellspacing="0" style="max-width:540px;width:100%;">

        <!-- Header -->
        <tr><td style="background:#1e40af;border-radius:12px 12px 0 0;padding:28px 32px;">
          <p style="margin:0;font-size:1.2rem;font-weight:700;color:#fff;">{$fromName}</p>
          <p style="margin:6px 0 0;font-size:.9rem;color:#bfdbfe;">Account credentials</p>
        </td></tr>

        <!-- Body -->
        <tr><td style="background:#fff;padding:28px 32px;">
          <p style="margin:0 0 16px;font-size:1rem;">Hi <strong>{$safeOwner}</strong>,</p>
          <p style="margin:0 0 20px;font-size:.95rem;color:#334155;">
            Your <strong>{$safeShop}</strong> account has been created. Here are your login credentials — keep them safe.
          </p>

          <!-- Credentials box -->
          <table width="100%" cellpadding="0" cellspacing="0"
                 style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;margin-bottom:24px;">
            <tr>
              <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;">
                <p style="margin:0;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Email</p>
                <p style="margin:4px 0 0;font-size:.95rem;font-weight:600;color:#0f172a;">{$safeEmail}</p>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 18px;border-bottom:1px solid #e2e8f0;">
                <p style="margin:0;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Password</p>
                <p style="margin:4px 0 0;font-size:.95rem;font-weight:600;color:#0f172a;font-family:monospace;">{$ownerPassword}</p>
              </td>
            </tr>
            <tr>
              <td style="padding:14px 18px;">
                <p style="margin:0;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:#64748b;">Phone</p>
                <p style="margin:4px 0 0;font-size:.95rem;font-weight:600;color:#0f172a;">{$safePhone}</p>
              </td>
            </tr>
          </table>

          <!-- CTA -->
          <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr><td style="background:#1e40af;border-radius:8px;">
              <a href="{$loginUrl}"
                 style="display:inline-block;padding:12px 28px;color:#fff;text-decoration:none;font-weight:600;font-size:.95rem;">
                Log in to your account →
              </a>
            </td></tr>
          </table>

          <p style="margin:0;font-size:.85rem;color:#64748b;">
            We recommend changing your password after your first login.<br>
            If you did not expect this email, please ignore it.
          </p>
        </td></tr>

        <!-- Footer -->
        <tr><td style="background:#f8fafc;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 12px 12px;padding:16px 32px;">
          <p style="margin:0;font-size:.8rem;color:#94a3b8;text-align:center;">
            {$fromName} &middot; Sent automatically by the tenant setup tool
          </p>
        </td></tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

                        $mail->AltBody = "Hi {$ownerName},\n\n"
                            . "Your {$name} account has been set up.\n\n"
                            . "Email:    {$ownerEmail}\n"
                            . "Password: {$ownerPassword}\n"
                            . "Phone:    {$ownerPhone}\n\n"
                            . "Login: {$loginUrl}\n\n"
                            . "We recommend changing your password after your first login.";

                        $mail->send();
                        $mailResult = 'ok';

                    } catch (MailerException $e) {
                        $mailResult = 'error';
                        $mailError  = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
                    }
                }
                // ── end credentials email ─────────────────────────────────────
            }

        } catch (\PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

$h = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Register Tenant — Dev Tool</title>
<style>
  *, *::before, *::after { box-sizing: border-box; }
  body   { font-family: -apple-system,'Segoe UI',Roboto,Arial,sans-serif;
           background: #f1f5f9; color: #0f172a; margin: 0; padding: 0; line-height: 1.5; }
  .wrap  { max-width: 580px; margin: 0 auto; padding: 32px 18px; }
  h1     { font-size: 1.35rem; margin: 0 0 4px; }
  .lead  { color: #64748b; font-size: .9rem; margin: 0 0 20px; }
  .card  { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px;
           padding: 22px 24px; margin-bottom: 18px; }
  .card h2 { font-size: .78rem; text-transform: uppercase; letter-spacing: .05em;
             color: #64748b; margin: 0 0 16px; }
  label  { display: block; font-size: .82rem; font-weight: 600;
           color: #475569; margin-bottom: 4px; }
  input, select {
    width: 100%; padding: 9px 11px; border: 1px solid #cbd5e1;
    border-radius: 8px; font-size: .92rem; background: #fff;
    outline: none; margin-bottom: 14px;
  }
  input:focus, select:focus {
    border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,.15);
  }
  .two { display: grid; grid-template-columns: 1fr 1fr; gap: 0 18px; }
  .btn { width: 100%; padding: 11px; background: #1e40af; color: #fff;
         border: none; border-radius: 8px; font-size: .95rem;
         font-weight: 600; cursor: pointer; }
  .btn:hover { background: #1d4ed8; }
  .alert { border-radius: 8px; padding: 12px 16px; font-size: .88rem; margin-bottom: 14px; }
  .alert.ok  { background: #dcfce7; color: #166534; }
  .alert.err { background: #fee2e2; color: #991b1b; }
  .alert.warn { background: #fef9c3; color: #854d0e; border: 1px solid #fde68a; }
  .alert.info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }
  .badge { display: inline-block; background: #fef3c7; color: #92400e;
           font-size: .68rem; font-weight: 700; padding: 2px 7px;
           border-radius: 999px; margin-left: 6px; vertical-align: middle; }
  .pill { font-size:.72rem; padding:2px 9px; border-radius:999px; font-weight:600; }
  .pill.ok  { background:#dcfce7; color:#166534; }
  .pill.bad { background:#fee2e2; color:#991b1b; }
  code { background: #f1f5f9; padding: 1px 5px; border-radius: 4px; font-size: .85em; }
  ul { margin: 6px 0 0; padding-left: 18px; }
  ul li { margin-bottom: 3px; }
</style>
</head>
<body>
<div class="wrap">

  <h1>Register Tenant <span class="badge">DEV ONLY</span></h1>
  <p class="lead">Creates a tenant workspace and owner account, then emails the owner their credentials using your SMTP config.</p>

  <div class="alert warn">
    ⚠ This page has <strong>no authentication</strong>. Restrict it at the web-server level (IP allowlist) or delete it after setup.
  </div>

  <?php /* ── Validation errors ── */ ?>
  <?php if (!empty($errors)): ?>
    <div class="alert err">
      <strong>Please fix the following:</strong>
      <ul>
        <?php foreach ($errors as $e): ?>
          <li><?= $h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <?php /* ── Tenant created banner ── */ ?>
  <?php if ($success): ?>
    <div class="alert ok">✓ <?= $success ?></div>
  <?php endif; ?>

  <?php /* ── Email result banners ── */ ?>
  <?php if ($mailResult === 'ok'): ?>
    <div class="alert ok">✓ Credentials email sent to <strong><?= $h($_POST['owner_email'] ?? '') ?></strong>. Ask the owner to check their inbox (and spam folder).</div>
  <?php elseif ($mailResult === 'error'): ?>
    <div class="alert err">
      <strong>Account created, but the credentials email failed to send.</strong><br>
      <?= $h($mailError) ?><br><br>
      Share these credentials manually:<br>
      Email: <code><?= $h($_POST['owner_email'] ?? '') ?></code><br>
      Password: <code><?= $h($_POST['owner_password'] ?? '') ?></code>
    </div>
  <?php elseif ($mailResult === 'skipped'): ?>
    <div class="alert warn">
      <strong>Account created, but email was skipped.</strong> <?= $mailError ?><br><br>
      Share these credentials manually:<br>
      Email: <code><?= $h($_POST['owner_email'] ?? '') ?></code><br>
      Password: <code><?= $h($_POST['owner_password'] ?? '') ?></code>
    </div>
  <?php endif; ?>

  <?php /* ── PHPMailer / mail config environment notice ── */ ?>
  <?php if (!$phpmailerAvailable): ?>
    <div class="alert info">
      <strong>PHPMailer not detected.</strong> Tenant creation will still work, but no credentials email will be sent.
      Run <code>composer require phpmailer/phpmailer</code> to enable it.
    </div>
  <?php elseif (empty($cfg['host'])): ?>
    <div class="alert info">
      <strong>Mail config is empty or missing</strong> (<code>app/config/mail.php</code>). Credentials email will be skipped.
      Use <code>public/devs/mail-test.php</code> to configure and verify SMTP first.
    </div>
  <?php endif; ?>

  <?php /* ── Form ── */ ?>
  <form method="post" action="?key=<?= $h(DEV_KEY) ?>">

    <div class="card">
      <h2>Tenant Details</h2>

      <label for="name">Shop Name</label>
      <input id="name" name="name" type="text" required
             value="<?= $h($_POST['name'] ?? '') ?>"
             placeholder="e.g. Curlz General Store">

      <label for="slug">Slug <small style="font-weight:400">(URL-safe, unique — staff use this as shop code)</small></label>
      <input id="slug" name="slug" type="text" required pattern="[a-z0-9\-]+"
             value="<?= $h($_POST['slug'] ?? '') ?>"
             placeholder="e.g. curlz-general-store">

      <label for="business_type">Business type</label>
      <select id="business_type" name="business_type">
        <option value="shop" <?= (($_POST['business_type'] ?? 'shop') === 'shop') ? 'selected' : '' ?>>Retail shop — products, inventory, discounts, credits</option>
        <option value="barbershop_salon" <?= (($_POST['business_type'] ?? '') === 'barbershop_salon') ? 'selected' : '' ?>>Barbershop &amp; salon — services &amp; commission</option>
      </select>

      <label for="status">Status</label>
      <select id="status" name="status">
        <?php foreach (['active' => 'Active', 'suspended' => 'Suspended', 'cancelled' => 'Cancelled'] as $val => $lbl): ?>
          <option value="<?= $val ?>" <?= (($_POST['status'] ?? 'active') === $val) ? 'selected' : '' ?>>
            <?= $lbl ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="card">
      <h2>Owner Account</h2>

      <label for="owner_name">Full Name</label>
      <input id="owner_name" name="owner_name" type="text" required
             value="<?= $h($_POST['owner_name'] ?? '') ?>"
             placeholder="Jane Doe">

      <div class="two">
        <div>
          <label for="owner_email">Email</label>
          <input id="owner_email" name="owner_email" type="email" required
                 value="<?= $h($_POST['owner_email'] ?? '') ?>"
                 placeholder="jane@example.com">
        </div>
        <div>
          <label for="owner_phone">Phone <small style="font-weight:400">(optional)</small></label>
          <input id="owner_phone" name="owner_phone" type="tel"
                 value="<?= $h($_POST['owner_phone'] ?? '') ?>"
                 placeholder="+254700000000">
        </div>
      </div>

      <label for="owner_password">Password</label>
      <input id="owner_password" name="owner_password" type="text"
             required minlength="8"
             value="<?= $h($_POST['owner_password'] ?? '') ?>"
             placeholder="Min. 8 characters"
             autocomplete="off">
      <p style="margin:-10px 0 14px;font-size:.78rem;color:#64748b;">
        Shown as plain text so you can confirm it before sending. It will be hashed before storing.
      </p>
    </div>

    <button class="btn" type="submit">Create Tenant &amp; Send Credentials</button>
  </form>

</div>
</body>
</html>