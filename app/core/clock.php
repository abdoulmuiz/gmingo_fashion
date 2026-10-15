<?php

declare(strict_types=1);

function app_timezone(): DateTimeZone
{
    $name = (string) (app_config()['timezone'] ?? 'Africa/Lagos');
    if (!in_array($name, timezone_identifiers_list(), true)) {
        $name = 'Africa/Lagos';
    }
    return new DateTimeZone($name);
}

function app_now(): DateTimeImmutable
{
    $zone = app_timezone();
    $frozen = $GLOBALS['gmingo_now'] ?? null;
    if (defined('GMINGO_TESTING') && is_string($frozen) && $frozen !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $frozen, $zone);
        if ($parsed instanceof DateTimeImmutable) {
            return $parsed;
        }
    }
    return new DateTimeImmutable('now', $zone);
}

function app_today(): string
{
    return app_now()->format('Y-m-d');
}
