<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Run this from the command line.\n");
    exit(1);
}

require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/core/schema.php';

try {
    $applied = schema_migrate();
} catch (Throwable $e) {
    fwrite(STDERR, "Could not update the database. " . $e->getMessage() . "\n");
    exit(1);
}

if ($applied === []) {
    echo "Database is up to date.\n";
    exit(0);
}

echo "Applied:\n";
foreach ($applied as $file) {
    echo "  {$file}\n";
}
