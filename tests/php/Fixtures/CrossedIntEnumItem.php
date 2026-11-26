<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

use ArchiPro\Silverstripe\SmartEnum\SmartEnumDataExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Int-backed SmartEnum whose native ENUM indexes and backing values disagree.
 */
class CrossedIntEnumItem extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'CrossedIntEnumItem';

    /**
     * @config
     */
    private static array $extensions = [
        SmartEnumDataExtension::class,
    ];

    /**
     * @config
     */
    private static array $db = [
        'Rank' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestCrossedPriority", 2)',
    ];
}
