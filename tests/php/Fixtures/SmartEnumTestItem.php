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
        'ColorAsVarchar' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor", '
            . '"red", ["storage" => "varchar"])',
        'ColorNoDefault' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor")',
        'Priority' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestPriority", 1)',
        'PriorityScalar' => 'SmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestPriority", '
            . '3, ["storage" => "scalar"])',
        'PriorityNoDefault' => 'SmartEnum('
            . '"ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestPriority", '
            . 'null, ["storage" => "scalar"])',
    ];
}
