<?php

// Load project .env before Silverstripe test bootstrap reads SS_DATABASE_*.
$envFile = dirname(__DIR__, 2) . '/.env';
if (is_readable($envFile)) {
    $ini = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($ini)) {
        foreach ($ini as $name => $value) {
            $_ENV[$name] = $value;
            putenv(sprintf('%s=%s', $name, $value));
        }
    }
}

// SapphireTest boots via HTTPApplication and reads `flush` from request vars, not argv.
$_GET['flush'] = 1;
$_REQUEST['flush'] = 1;
// Fallback when HTTPApplication is unavailable.
$_SERVER['argv'][] = 'flush';

require __DIR__ . '/../../vendor/silverstripe/framework/tests/bootstrap.php';
