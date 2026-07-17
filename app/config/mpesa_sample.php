<?php
// app/config/mpesa.php
// Fill these in from your Safaricom Daraja app (developer.safaricom.co.ke).
// Keep this file OUT of version control once real credentials are in it.
// IMPORTANT: callback_url must be a PUBLIC https URL that Safaricom can reach.
// On localhost use a tunnel like ngrok:  ngrok http 80
// then set callback_url to e.g. https://<id>.ngrok-free.app/Ismano/public/api/store/checkout/mpesa_callback.php
return [
    // 'sandbox' while testing, 'production' when live (Go-Live approved).
    'env' => 'production',
    'consumer_key'    => '',
    'consumer_secret' => '',
    // Lipa Na M-Pesa Online (STK) credentials:
    'shortcode' => '',                 // sandbox Paybill test shortcode
    'passkey'   => '',
    // 'CustomerPayBillOnline' for Paybill, 'CustomerBuyGoodsOnline' for Till.
    'transaction_type' => 'CustomerPayBillOnline',
    'callback_url' => 'https://monkhood-outnumber-swept.ngrok-free.dev/Modern/public/api/billing/mpesa_callback.php',

    
        'cacert_path' => '',
    'verify_ssl'  => false,   // LOCAL TESTING ONLY — set to true once a CA bundle is configured.
    
];