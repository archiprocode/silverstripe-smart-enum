<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\DBSmartEnum;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\UnitOnlyEnum;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DBSmartEnumTest extends TestCase
{

    public function testConstructor(): void
    {
        $dbField = new DBSmartEnum(
            'Status',
            TestColor::class,
            TestColor::Red->value,
            ['foo' => 'bar']
        );

        $this->assertSame(
            [TestColor::Red->value, TestColor::Blue->value],
            $dbField->getEnum(),
            'Values from the enum were read and used to initialise DBEnum'
        );
        $this->assertSame(
            TestColor::Red->value,
            $dbField->getDefault(),
            'Default value was set correctly'
        );
        $this->assertSame('Status', $dbField->getName(), 'Field name was set correctly');
        $this->assertSame(['foo' => 'bar'], $dbField->getOptions(), 'Options are set correctly');
        $this->assertSame(TestColor::class, $dbField->getEnumClass());
        $this->assertSame('enum', $dbField->getStorage());
    }

    public function testConstructorAcceptsNullEnumClass(): void
    {
        $dbField = new DBSmartEnum('Status');

        $this->assertSame('Status', $dbField->getName());
        $this->assertSame([], $dbField->getEnum());
        $this->assertNull($dbField->getEnumClass());
    }

    public function testConstructorThrowsWhenEnumClassCannotBeResolved(): void
    {
        $unresolvableClass = 'ArchiPro\\Silverstripe\\SmartEnum\\Tests\\NonExistentEnum';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage($unresolvableClass);

        new DBSmartEnum('Status', $unresolvableClass);
    }

    public function testConstructorThrowsWhenEnumIsNotBacked(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not a BackedEnum');

        new DBSmartEnum('Status', UnitOnlyEnum::class);
    }

    public function testStorageFromOptions(): void
    {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, [
            'storage' => 'varchar',
            'varchar_length' => 32,
        ]);

        $this->assertSame('varchar', $dbField->getStorage());
        $this->assertSame(32, $dbField->getVarcharLength());
        $this->assertArrayNotHasKey('storage', $dbField->getOptions());
        $this->assertArrayNotHasKey('varchar_length', $dbField->getOptions());
    }

    public function testEnumValuesMatchBackingCasesForCmsDropdown(): void
    {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value);

        $this->assertSame(
            [
                TestColor::Red->value => TestColor::Red->value,
                TestColor::Blue->value => TestColor::Blue->value,
            ],
            $dbField->enumValues(false),
            'DBEnum::formField() builds DropdownField options from enumValues()'
        );
    }

    public function testEnumValuesUnchangedForVarcharStorage(): void
    {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, ['storage' => 'varchar']);

        $this->assertSame(
            [
                TestColor::Red->value => TestColor::Red->value,
                TestColor::Blue->value => TestColor::Blue->value,
            ],
            $dbField->enumValues(false),
            'VARCHAR storage affects DDL only; CMS dropdown options stay enum-backed'
        );
    }

    public function testDefaultFieldSpecUsesEnumStorage(): void
    {
        $field = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value);

        $this->assertSame('enum', $field->getStorage());
    }

    public function testFieldSpecCanRequestVarcharStorage(): void
    {
        $field = new DBSmartEnum(
            'ColorAsVarchar',
            TestColor::class,
            TestColor::Red->value,
            ['storage' => 'varchar']
        );

        $this->assertSame('varchar', $field->getStorage());
        $this->assertGreaterThanOrEqual(3, $field->getVarcharLength());
    }
}
