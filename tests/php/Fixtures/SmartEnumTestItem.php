<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

use ArchiPro\Silverstripe\SmartEnum\SmartEnumDataExtension;
use SilverStripe\ORM\DataObject;

/**
 * Minimal DataObject exercising SmartEnum fields in tests.
 */
class SmartEnumTestItem extends DataObject
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
        'Color' => 'DBSmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor", "red")',
        'ColorAsVarchar' => 'DBSmartEnum("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor", "red", ["storage" => "varchar"])',
    ];
}
