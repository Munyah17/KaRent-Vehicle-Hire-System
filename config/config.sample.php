<?php
/**
 * Application configuration sample.
 * Copy to config.php and fill in real values. NEVER commit real credentials.
 */
return [
    // 'dev' shows errors and demo banners; 'production' hides errors.
    'env' => 'dev',

    'app_name' => 'Vehicle Hire Management System',
    'company_name' => 'Vehicle Hire',
    'app_url' => 'http://localhost:8000',
    'currency' => 'USD',
    'currency_symbol' => '$',
    'timezone' => 'Africa/Harare',

    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'vehicle_hire',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],

    // Paynow (Zimbabwe) credentials — keep out of the web root / public files.
    'paynow' => [
        'integration_id' => '',
        'integration_key' => '',
        // Leave blank to auto-derive from app_url.
        'result_url' => '',
        'return_url' => '',
        'test_mode' => true,
    ],

    'mail' => [
        'from_address' => 'no-reply@example.com',
        'from_name' => 'Vehicle Hire',
    ],

    'uploads' => [
        'max_bytes' => 5 * 1024 * 1024,
        'image_types' => ['image/jpeg', 'image/png', 'image/webp'],
        'doc_types' => ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'],
    ],

    // Shows demo credentials on the login page. Disable in production.
    'demo_mode' => true,
];
