<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

use ArchiPro\Silverstripe\SmartEnum\SmartEnumDataExtension;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * DataObject using backed enum FQCN field specs (requires {@see SmartEnumServiceConfigurationLocator::install()}).
 */
class SmartEnumTestItemEnumClass extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'SmartEnumTestItemEnumClass';

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
        'Color' => TestColor::class,
        'ColorWithDefault' => TestColor::class . '("red")',
        'ColorNoDefault' => TestColor::class,
        'Priority' => TestPriority::class . '(1)',
        'PriorityNoDefault' => TestPriority::class,
    ];
}
