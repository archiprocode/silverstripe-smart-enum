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

// PHPUnit 9 rejects unknown CLI flags; SapphireTest::start() looks for `flush` in argv.
$_SERVER['argv'][] = 'flush';

require __DIR__ . '/../../vendor/silverstripe/framework/tests/bootstrap.php';
