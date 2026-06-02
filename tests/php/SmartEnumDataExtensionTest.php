<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
/**
 * @internal
 */
class SmartEnumDataExtensionTest extends SmartEnumSapphireTest
{
    protected $usesDatabase = false;

    protected static $extraDataObjects = [
        SmartEnumTestItem::class,
    ];

    public function testGetterReturnsEnumInstance(): void
    {
        $item = SmartEnumTestItem::create();
        $item->Color = TestColor::Blue->value;

        $this->assertSame(TestColor::Blue, $item->getColor());
    }

    public function testSetterAcceptsEnumInstance(): void
    {
        $item = SmartEnumTestItem::create();
        $item->setColor(TestColor::Blue);

        $this->assertSame(TestColor::Blue->value, $item->getField('Color'));
        $this->assertSame(TestColor::Blue, $item->getColor());
    }

    public function testSetterAcceptsScalarBackingValue(): void
    {
        $item = SmartEnumTestItem::create();
        $item->setColor(TestColor::Red->value);

        $this->assertSame(TestColor::Red, $item->getColor());
    }

    public function testGetterReturnsNullForUnknownStoredValue(): void
    {
        $item = SmartEnumTestItem::create();
        $item->setField('Color', 'not-a-real-color');

        $this->assertNull($item->getColor());
    }

    public function testSetterRejectsInvalidTypes(): void
    {
        $item = SmartEnumTestItem::create();

        $this->expectException(\InvalidArgumentException::class);
        $item->setColor(new \stdClass());
    }
}
