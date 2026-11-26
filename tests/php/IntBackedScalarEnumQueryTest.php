<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\DBSmartEnum;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestPriority;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DB;

/**
 * Int-backed fields follow default_use_native_db_enum when the field spec does not override it.
 * This path already compares backing values and must keep doing so.
 *
 * @internal
 */
class IntBackedScalarEnumQueryTest extends SapphireTest
{
    protected bool $doSetSupportedModuleLocaleToUS = false;

    /**
     * @var array<int, class-string>
     */
    protected static $extra_dataobjects = [
        SmartEnumTestItem::class,
    ];

    /**
     * @var array<int, string>
     */
    protected static $fixture_file = [];

    protected $usesDatabase = true;

    public static function setUpBeforeClass(): void
    {
        // Boot before changing config, then build the schema against that config.
        static::start();
        DBSmartEnum::config()->set('default_use_native_db_enum', false);
        parent::setUpBeforeClass();
    }

    public static function tearDownAfterClass(): void
    {
        DBSmartEnum::config()->remove('default_use_native_db_enum');
        parent::tearDownAfterClass();
    }

    public function testConfigDisabledNativeEnumFiltersByBackingValue(): void
    {
        $columns = DB::query('SHOW COLUMNS FROM "SmartEnumTestItem" LIKE \'Priority\'');
        $column = null;
        foreach ($columns as $row) {
            $column = $row;
        }
        $this->assertIsArray($column);
        $this->assertStringStartsWith(
            'int',
            (string) $column['Type'],
            'default_use_native_db_enum false stores the int-backed field as an INT column'
        );

        $low = SmartEnumTestItem::create();
        $low->Priority = TestPriority::Low->value;
        $low->write();
        $high = SmartEnumTestItem::create();
        $high->Priority = TestPriority::High->value;
        $high->write();

        $matched = SmartEnumTestItem::get()->filter('Priority', TestPriority::High->value);
        $actual = array_map('intval', $matched->column('ID'));
        sort($actual);
        $this->assertSame(
            [(int) $high->ID],
            $actual,
            'An INT column filters by the high backing value'
        );
        $lowMatched = SmartEnumTestItem::get()->filter('Priority', TestPriority::Low->value);
        $this->assertSame(
            [(int) $low->ID],
            array_map('intval', $lowMatched->column('ID')),
            'An INT column filters by the low backing value'
        );

        $miss = SmartEnumTestItem::get()->filter('Priority', 2);
        $this->assertSame(
            [],
            array_map('intval', $miss->column('ID')),
            'An INT column does not match an integer that is not a backing value'
        );
    }
}
