<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestPriority;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 */
class SmartEnumPersistenceTest extends SapphireTest
{
    /**
     * Test classes live under tests/php and are not in the Silverstripe class manifest.
     */
    protected bool $doSetSupportedModuleLocaleToUS = false;

    /**
     * @var array<int, class-string<SmartEnumTestItem>>
     */
    protected static $extra_dataobjects = [
        SmartEnumTestItem::class,
    ];

    /**
     * Empty list avoids fixture path resolution (test classes are not in the SS manifest).
     *
     * @var array<int, string>
     */
    protected static $fixture_file = [];

    protected $usesDatabase = true;

    public function testFixtureWithoutDefaultPersistsNull(): void
    {
        $item = SmartEnumTestItem::create();
        $item->write();

        $reloaded = SmartEnumTestItem::get()->byID($item->ID);

        $this->assertNull(
            $reloaded->getColorNoDefault(),
            'New records without a field default return null from the typed getter'
        );

        $stored = $reloaded->getField('ColorNoDefault');
        $this->assertTrue(
            $stored === null || $stored === '',
            'New records without a field default persist an empty column value'
        );
    }

    public function testFixtureDefaultsToRed(): void
    {
        $item = SmartEnumTestItem::create();
        $item->write();

        $reloaded = SmartEnumTestItem::get()->byID($item->ID);

        $this->assertSame(
            TestColor::Red,
            $reloaded->getColor(),
            'New records default Color to the red enum case from the field spec'
        );
        $this->assertSame(
            TestColor::Red,
            $reloaded->getColorAsScalar(),
            'New records default ColorAsScalar to the red enum case from the field spec'
        );
        $this->assertSame(
            TestPriority::Low,
            $reloaded->getPriority(),
            'New records default Priority to the low enum case from the int field spec'
        );
        $this->assertSame(
            TestPriority::High,
            $reloaded->getPriorityAsInt(),
            'New records default PriorityAsInt to the high enum case from the int scalar field spec'
        );
    }

    public function testIntScalarWithoutDefaultReturnsNullFromGetter(): void
    {
        $item = SmartEnumTestItem::create();
        $item->write();

        $reloaded = SmartEnumTestItem::get()->byID($item->ID);

        $this->assertNull(
            $reloaded->getPriorityNoDefault(),
            'Unset int scalar column returns null from getter when 0 is not a valid case'
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: TestColor}>
     */
    public function roundTripProvider(): array
    {
        return [
            'Color via typed setter' => ['Color', 'setter', TestColor::Blue],
            'Color via scalar property' => ['Color', 'property', TestColor::Blue],
            'ColorAsScalar via typed setter' => ['ColorAsScalar', 'setter', TestColor::Red],
            'ColorAsScalar via scalar property' => ['ColorAsScalar', 'property', TestColor::Red],
        ];
    }

    /**
     * @dataProvider roundTripProvider
     */
    public function testSmartEnumRoundTripsThroughDatabase(string $field, string $writeApi, TestColor $color): void
    {
        $item = SmartEnumTestItem::create();

        if ($writeApi === 'setter') {
            $setter = 'set' . $field;
            $item->$setter($color);
        } else {
            $item->$field = $color->value;
        }

        $writeResult = $item->write();
        $this->assertNotFalse(
            $writeResult,
            sprintf('%s value was persisted to the database', $field)
        );

        $reloaded = SmartEnumTestItem::get()->byID($item->ID);
        $getter = 'get' . $field;

        $this->assertSame(
            $color,
            $reloaded->$getter(),
            sprintf('Reloaded record returns %s via %s()', $color->name, $getter)
        );
        $this->assertSame(
            $color->value,
            $reloaded->getField($field),
            sprintf('Reloaded %s column holds the backing scalar in the database', $field)
        );
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: TestPriority, 3: bool}>
     */
    public function intRoundTripProvider(): array
    {
        return [
            'Priority via typed setter' => ['Priority', 'setter', TestPriority::High, false],
            'Priority via scalar property' => ['Priority', 'property', TestPriority::High, false],
            'PriorityAsInt via typed setter' => ['PriorityAsInt', 'setter', TestPriority::Low, true],
            'PriorityAsInt via scalar property' => ['PriorityAsInt', 'property', TestPriority::Low, true],
        ];
    }

    /**
     * @dataProvider intRoundTripProvider
     */
    public function testIntSmartEnumRoundTripsThroughDatabase(
        string $field,
        string $writeApi,
        TestPriority $priority,
        bool $strictScalarType
    ): void {
        $item = SmartEnumTestItem::create();

        if ($writeApi === 'setter') {
            $setter = 'set' . $field;
            $item->$setter($priority);
        } else {
            $item->$field = $priority->value;
        }

        $writeResult = $item->write();
        $this->assertNotFalse(
            $writeResult,
            sprintf('%s value was persisted to the database', $field)
        );

        $reloaded = SmartEnumTestItem::get()->byID($item->ID);
        $getter = 'get' . $field;

        $this->assertSame(
            $priority,
            $reloaded->$getter(),
            sprintf('Reloaded record returns %s via %s()', $priority->name, $getter)
        );

        $stored = $reloaded->getField($field);
        $columnLabel = $strictScalarType ? 'INT' : 'ENUM';
        $this->assertEquals(
            $priority->value,
            $stored,
            sprintf('Reloaded %s %s column holds the backing scalar (may be stringified)', $field, $columnLabel)
        );
    }
}
