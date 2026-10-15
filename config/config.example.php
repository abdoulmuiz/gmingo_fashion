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
