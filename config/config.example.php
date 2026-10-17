<?php

declare(strict_types=1);

/**
 * Copy this file to config.php. Database settings live only here
 * so the same code runs on XAMPP and on MAMP.
 */
return [
    'base_path' => '/gmingo_fashion',
    'timezone' => 'Africa/Lagos',
    // Demo data loads only when this is local.
    'environment' => 'local',
    // Long random string used to encrypt Paystack secret keys.
    // Put the real value only in config.php. Do not commit config.php.
    'app_key' => '',
    // Site address used for Paystack until the shop has its own domain.
    // Paystack mode cannot be switched on while this is empty.
    'payment_placeholder_domain' => '',
    // Public origin for tracking links sent on WhatsApp, with no path.
    // Example: https://stitches.example
    // Empty, localhost, or an IP address means the link only works on this computer.
    'public_base_url' => '',
    // Paystack API address. Leave this as Paystack's own address.
    'paystack_base_url' => 'https://api.paystack.co',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'gmingo_fashion',
        'user' => 'root',
        'password' => '',
    ],
    'test_db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'gmingo_fashion_test',
        'user' => 'root',
        'password' => '',
    ],
];
