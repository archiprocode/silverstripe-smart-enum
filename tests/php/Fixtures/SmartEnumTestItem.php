<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

use ArchiPro\Silverstripe\SmartEnum\SmartEnumDataExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Minimal DataObject exercising SmartEnum fields in tests.
 */
class SmartEnumTestItem extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'SmartEnumTestItem';

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
        'Color' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor", "red")',
        'ColorAsScalar' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor", '
            . '"red", ["use_native_db_enum" => false])',
        'ColorNoDefault' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor")',
        'Priority' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestPriority", 1)',
        'PriorityAsInt' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestPriority", '
            . '3, ["use_native_db_enum" => false])',
        'PriorityNoDefault' => 'SmartEnum('
            . '"ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestPriority", '
            . 'null, ["use_native_db_enum" => false])',
    ];
}
