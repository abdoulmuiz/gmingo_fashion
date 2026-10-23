<?php

declare(strict_types=1);

require __DIR__ . '/core/helpers.php';

$timezone = (string) (app_config()['timezone'] ?? 'Africa/Lagos');
if (!in_array($timezone, timezone_identifiers_list(), true)) {
    $timezone = 'Africa/Lagos';
}
date_default_timezone_set($timezone);
require __DIR__ . '/core/clock.php';
require __DIR__ . '/core/i18n.php';
require __DIR__ . '/core/errors.php';
require __DIR__ . '/core/csrf.php';
require __DIR__ . '/core/auth.php';
require __DIR__ . '/core/router.php';
require __DIR__ . '/core/db.php';
