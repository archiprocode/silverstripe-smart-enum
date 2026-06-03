<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

use ArchiPro\Silverstripe\SmartEnum\DBSmartEnum;
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
        'Color' => DBSmartEnum::class
            . '("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor", "red")',
        'ColorAsVarchar' => DBSmartEnum::class
            . '("ArchiPro\\\\Silverstripe\\\\SmartEnum\\\\Tests\\\\Fixtures\\\\TestColor", "red", '
            . '["storage" => "varchar"])',
    ];
}
