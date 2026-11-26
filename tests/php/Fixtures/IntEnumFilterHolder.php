<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;

/**
 * Parent used to filter an int-backed SmartEnum through a has_one relation.
 *
 * @method SmartEnumTestItem Item()
 */
class IntEnumFilterHolder extends DataObject implements TestOnly
{
    /**
     * @config
     */
    private static string $table_name = 'IntEnumFilterHolder';

    /**
     * @config
     */
    private static array $has_one = [
        'Item' => SmartEnumTestItem::class,
    ];
}
