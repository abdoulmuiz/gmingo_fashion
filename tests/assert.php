<?php

declare(strict_types=1);

$GLOBALS['__pass'] = 0;
$GLOBALS['__fail'] = 0;
$GLOBALS['__fail_messages'] = [];

function check(bool $ok, string $message): void
{
    if ($ok) {
        $GLOBALS['__pass']++;
        return;
    }
    $GLOBALS['__fail']++;
    $GLOBALS['__fail_messages'][] = $message;
    fwrite(STDERR, "FAIL: {$message}\n");
}

function check_eq(mixed $actual, mixed $expected, string $message): void
{
    check($actual === $expected, $message . ' (got ' . var_export($actual, true) . ')');
}

function check_throws(callable $fn, string $message): void
{
    try {
        $fn();
        check(false, $message);
    } catch (Throwable $e) {
        check(true, $message);
    }
}
