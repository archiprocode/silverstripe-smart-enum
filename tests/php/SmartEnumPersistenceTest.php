<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
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
            $reloaded->getColorAsVarchar(),
            'New records default ColorAsVarchar to the red enum case from the field spec'
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
            'ColorAsVarchar via typed setter' => ['ColorAsVarchar', 'setter', TestColor::Red],
            'ColorAsVarchar via scalar property' => ['ColorAsVarchar', 'property', TestColor::Red],
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
}
