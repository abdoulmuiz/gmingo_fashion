<?php

declare(strict_types=1);

define('GMINGO_TESTING', true);

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Refusing to run tests: config/config.php is missing.\n");
    exit(1);
}
$config = require $configFile;
$name = (string) ($config['db']['name'] ?? '');
if ($name === '') {
    fwrite(STDERR, "Refusing to run tests: database name is empty.\n");
    exit(1);
}
$testName = str_ends_with($name, '_test') ? $name : $name . '_test';
if (!str_ends_with($testName, '_test')) {
    fwrite(STDERR, "Refusing to run tests: database name must end with _test.\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9_]+$/', $testName)) {
    fwrite(STDERR, "Refusing to run tests: database name is not safe.\n");
    exit(1);
}
define('GMINGO_DB_NAME', $testName);

require $root . '/app/bootstrap.php';
require $root . '/app/routes.php';
require __DIR__ . '/assert.php';

check(str_ends_with(GMINGO_DB_NAME, '_test'), 'tests bind only to a _test database');
check(GMINGO_DB_NAME !== $name || str_ends_with($name, '_test'), 'tests do not use the main database name');
