<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$files = glob(__DIR__ . '/*_test.php');
sort($files);
foreach ($files as $file) {
    require $file;
}

$passed = $GLOBALS['__pass'];
$failed = $GLOBALS['__fail'];
echo "Tests: {$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
