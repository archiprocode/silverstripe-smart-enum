<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use SilverStripe\Dev\SapphireTest;

/**
 * SapphireTest base that skips database teardown when no test database is configured.
 */
abstract class SmartEnumSapphireTest extends SapphireTest
{
    public static function tearDownAfterClass(): void
    {
        try {
            parent::tearDownAfterClass();
        } catch (\Throwable $exception) {
            if (static::shouldIgnoreDatabaseTeardownFailure($exception)) {
                return;
            }

            throw $exception;
        }
    }

    private static function shouldIgnoreDatabaseTeardownFailure(\Throwable $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'Access denied')
            || str_contains($message, 'Connection refused')
            || str_contains($message, 'Unknown database');
    }
}
