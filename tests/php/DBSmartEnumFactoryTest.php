<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\DBSmartEnum;
use ArchiPro\Silverstripe\SmartEnum\DBSmartEnumFactory;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestPriority;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\UnitOnlyEnum;
use InvalidArgumentException;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 */
class DBSmartEnumFactoryTest extends SapphireTest
{
    protected bool $doSetSupportedModuleLocaleToUS = false;

    protected $usesDatabase = false;

    public function testCreateBuildsFieldFromEnumClassName(): void
    {
        $factory = new DBSmartEnumFactory();
        $field = $factory->create(TestColor::class, ['Color', TestColor::Red->value]);

        $this->assertInstanceOf(DBSmartEnum::class, $field);
        $this->assertSame('Color', $field->getName());
        $this->assertSame(TestColor::class, $field->getEnumClass());
        $this->assertSame(TestColor::Red->value, $field->getDefault());
        $this->assertSame(
            [TestColor::Red->value, TestColor::Blue->value],
            $field->getEnum(),
            'Allowed values are derived from enum cases'
        );
    }

    public function testCreateAcceptsIntDefault(): void
    {
        $factory = new DBSmartEnumFactory();
        $field = $factory->create(TestPriority::class, ['Priority', 1]);

        $this->assertSame(1, $field->getDefault());
    }

    public function testCreateLeavesDefaultNullWhenOmitted(): void
    {
        $factory = new DBSmartEnumFactory();
        $field = $factory->create(TestColor::class, ['Color']);

        $this->assertNull($field->getDefault());
    }

    public function testCreateRejectsUnitEnum(): void
    {
        $factory = new DBSmartEnumFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(UnitOnlyEnum::class);

        $factory->create(UnitOnlyEnum::class, ['Status']);
    }

    public function testCreateRejectsNonEnumClass(): void
    {
        $factory = new DBSmartEnumFactory();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not a BackedEnum');

        $factory->create(\stdClass::class, ['Status']);
    }
}
