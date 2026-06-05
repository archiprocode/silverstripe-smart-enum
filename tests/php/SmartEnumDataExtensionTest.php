<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 */
class SmartEnumDataExtensionTest extends SapphireTest
{
    /**
     * Test classes live under tests/php and are not in the Silverstripe class manifest.
     */
    protected bool $doSetSupportedModuleLocaleToUS = false;

    protected $usesDatabase = false;

    public function testGetterReturnsEnumInstance(): void
    {
        $item = SmartEnumTestItem::create();
        $item->Color = TestColor::Blue->value;

        $this->assertSame(
            TestColor::Blue,
            $item->getColor(),
            'getColor() maps a stored backing scalar to the matching BackedEnum case'
        );
    }

    /**
     * @return array<string, array{0: callable(SmartEnumTestItem): void, 1: TestColor}>
     */
    public function validSetterInputProvider(): array
    {
        return [
            'enum instance' => [
                fn (SmartEnumTestItem $item) => $item->setColor(TestColor::Blue),
                TestColor::Blue,
            ],
            'backing scalar' => [
                fn (SmartEnumTestItem $item) => $item->setColor(TestColor::Red->value),
                TestColor::Red,
            ],
        ];
    }

    /**
     * @dataProvider validSetterInputProvider
     */
    public function testSetterPersistsValidValues(callable $assign, TestColor $expected): void
    {
        $item = SmartEnumTestItem::create();
        $assign($item);

        $this->assertSame(
            $expected->value,
            $item->getField('Color'),
            'Color column stores the backing scalar after a valid setter call'
        );
        $this->assertSame(
            $expected,
            $item->getColor(),
            'getColor() returns the BackedEnum case matching the stored value'
        );
    }

    public function testColorAsVarcharSetterAndGetter(): void
    {
        $item = SmartEnumTestItem::create();
        $item->setColorAsVarchar(TestColor::Blue);

        $this->assertSame(
            TestColor::Blue->value,
            $item->getField('ColorAsVarchar'),
            'ColorAsVarchar column stores the backing scalar'
        );
        $this->assertSame(
            TestColor::Blue,
            $item->getColorAsVarchar(),
            'getColorAsVarchar() returns the matching enum case for varchar-backed storage'
        );
    }

    public function testGetterReturnsNullForUnknownStoredValue(): void
    {
        $item = SmartEnumTestItem::create();
        $item->setField('Color', 'not-a-real-color');

        $this->assertNull(
            $item->getColor(),
            'getColor() returns null when the stored scalar does not match any enum case'
        );
    }

    public function testSetterRejectsInvalidTypes(): void
    {
        $item = SmartEnumTestItem::create();

        $this->expectException(\InvalidArgumentException::class);
        $item->setColor(new \stdClass());
    }
}
