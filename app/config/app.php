<?php
// app/config/app.php
// Application-level settings only. All mail/SMTP settings live in app/config/mail.php.

return [
    'app_name'     => 'Mbita POS',
    'app_url'      => 'http://localhost/Mbita',   // full site URL — match app/config/paths.php base_path
    'debug'        => false,                         // set false in production
    'timezone'     => 'UTC',
    'session_name' => 'curlz_session',

    // Security
    'hash_cost'    => 12,

    // OTP testing aid: when true, the login screen shows the 6-digit code and, if
    // sending failed, the exact reason. TESTING ONLY — set to false in production.
    'otp_debug'    => false,

    // Secret token for the web-callable daily report endpoint.
    // Change this to a long random string before going live.
    'cron_token'   => 'change-this-to-a-long-random-secret',

    // One-time bootstrap code for public/devs/register-admin.php (change before production).
    'admin_registration_code' => 'ADMIN2024',
];