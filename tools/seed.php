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
    echo "The demo shops are already set up.\n";
} else {
    echo "Demo shops ready: Kano Stitches Tailoring, Demo Factory, and Demo Brand.\n";
}
echo "Password for every demo login: password\n";
echo 'Platform admin: ' . platform_admin_email() . "\n";
echo "Tailor owner: demo@gmingo.test\n";
echo "Tailor staff: tailor-staff@gmingo.test\n";
echo "Factory owner: factory@gmingo.test\n";
echo "Factory staff: factory-staff@gmingo.test\n";
echo "Brand owner: brand@gmingo.test\n";
echo "Brand staff: brand-staff@gmingo.test\n";
