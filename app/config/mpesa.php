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
    'consumer_key'    => 'GfYzueAo3WaZGLx4pPPnQ1rmQR94LRhAfOZ4OvHFGw2rU1kl',
    'consumer_secret' => 'cVrV7yIIKKcH18XbElAUdVVh1ooNA7SBLnIuatgI33T516xAd7LJs9wBzfB86RTw',
    // Lipa Na M-Pesa Online (STK) credentials:
    'shortcode' => '4167991',                 // sandbox Paybill test shortcode
    'passkey'   => 'e35db45304b2ff88e830b2cf4b1d23d4ad09b9b0380377b276c4b78a25de1766',
    // 'CustomerPayBillOnline' for Paybill, 'CustomerBuyGoodsOnline' for Till.
    'transaction_type' => 'CustomerPayBillOnline',
    'callback_url' => 'https://monkhood-outnumber-swept.ngrok-free.dev/Curlz/public/api/billing/mpesa_callback.php',

        'cacert_path' => '',
    'verify_ssl'  => false,   // LOCAL TESTING ONLY — set to true once a CA bundle is configured.
    
];