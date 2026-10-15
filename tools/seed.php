<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/domain/seed.php';

if (!seed_is_local(app_config())) {
    fwrite(STDERR, seed_refusal_message());
    exit(1);
}

try {
    $result = seed_demo();
} catch (Throwable $e) {
    $message = $e->getMessage();
    if ($e instanceof PDOException) {
        $message = 'Could not save the demo shop. Check that MySQL is running and that the database has been migrated.';
    }
    fwrite(STDERR, $message . "\n");
    exit(1);
}

if ($result['status'] === 'exists') {
    echo "The demo shop is already set up. Log in as demo@gmingo.test with the password: password\n";
    exit(0);
}

echo "Demo shop ready: Kano Stitches Tailoring.\n";
echo "Log in as demo@gmingo.test with the password: password\n";
