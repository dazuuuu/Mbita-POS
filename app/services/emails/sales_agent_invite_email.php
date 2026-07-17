<?php
// app/services/emails/sales_agent_invite_email.php

function build_sales_agent_invite_email(string $name, string $tempPassword, string $loginUrl, string $shopName = ''): array
{
    $logo = '/public/assets/images/logo/logo.png';
    $n    = htmlspecialchars($name, ENT_QUOTES);
    $pw   = htmlspecialchars($tempPassword, ENT_QUOTES);
    $shop = htmlspecialchars($shopName, ENT_QUOTES);
    $url  = htmlspecialchars($loginUrl, ENT_QUOTES);
    $shopLine = $shop !== '' ? " for <strong>{$shop}</strong>" : '';

    $subject = $shop !== '' ? "You've been added as a sales agent at {$shop}" : 'Your sales agent account';

    $html = <<<HTML
<div style="font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;background:#f1f5f9;padding:32px 0">
  <div style="max-width:480px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="padding:24px 28px;border-bottom:1px solid #f1f5f9"><img src="{$logo}" alt="Curlz" style="height:32px"></div>
    <div style="padding:28px">
      <h1 style="margin:0 0 12px;font-size:20px;color:#0f172a">Welcome, {$n}</h1>
      <p style="margin:0 0 16px;color:#475569;font-size:15px;line-height:1.5">
        A sales agent account has been created for you{$shopLine}. Log in with the temporary password below — you'll set your own password on first login.
      </p>
      <div style="background:#f1f5f9;border-radius:10px;padding:16px;text-align:center;margin-bottom:18px">
        <div style="color:#64748b;font-size:13px;margin-bottom:4px">Temporary password</div>
        <div style="font-size:22px;font-weight:800;letter-spacing:2px;color:#0f172a;font-family:monospace">{$pw}</div>
      </div>
      <a href="{$url}" style="display:inline-block;background:#2563eb;color:#fff;text-decoration:none;padding:12px 22px;border-radius:8px;font-size:15px;font-weight:600">Log in</a>
    </div>
  </div>
</div>
HTML;

    return ['subject' => $subject, 'html' => $html];
}
