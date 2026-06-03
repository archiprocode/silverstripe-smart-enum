<?php

// PHPUnit 9 rejects unknown CLI flags; SapphireTest::start() looks for `flush` in argv.
$_SERVER['argv'][] = 'flush';

require __DIR__ . '/../../vendor/silverstripe/framework/tests/bootstrap.php';
