<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use SilverStripe\Dev\SapphireTest;

/**
 * SapphireTest base that skips database teardown when no test database is configured.
 */
abstract class SmartEnumSapphireTest extends SapphireTest
{
    /**
     * Test classes live under tests/php and are not in the Silverstripe class manifest.
     */
    protected bool $doSetSupportedModuleLocaleToUS = false;

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
            || str_contains($message, 'Unknown database')
            || str_contains($message, 'activate() on bool')
            || str_contains($message, 'mysqli object is not fully initialized');
    }

    /**
     * Whether PHPUnit can reach MySQL using SS_* database environment variables.
     */
    protected static function isTestDatabaseReachable(): bool
    {
        static $reachable = null;
        if ($reachable !== null) {
            return $reachable;
        }

        $host = \SilverStripe\Core\Environment::getEnv('SS_DATABASE_SERVER') ?: '127.0.0.1';
        $user = \SilverStripe\Core\Environment::getEnv('SS_DATABASE_USERNAME') ?: 'silverstripe';
        $password = \SilverStripe\Core\Environment::getEnv('SS_DATABASE_PASSWORD') ?: '';

        try {
            $mysqli = @new \mysqli($host, $user, $password);
            if ($mysqli->connect_errno) {
                $reachable = false;

                return false;
            }
            $mysqli->close();
            $reachable = true;

            return true;
        } catch (\Throwable) {
            $reachable = false;

            return false;
        }
    }
}
