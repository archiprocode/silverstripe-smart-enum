<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\DBSmartEnum;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestPriority;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestSize;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\UnitOnlyEnum;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 */
class DBSmartEnumTest extends SapphireTest
{
    /**
     * Test classes live under tests/php and are not in the Silverstripe class manifest.
     */
    protected bool $doSetSupportedModuleLocaleToUS = false;

    protected $usesDatabase = false;

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
        $this->assertSame(TestColor::class, $dbField->getEnumClass(), 'BackedEnum class name is retained on the field');
        $this->assertSame('enum', $dbField->getStorage(), 'Default logical storage is MySQL ENUM');
        $this->assertSame('enum', $dbField->getColumnType(), 'Default column type is MySQL ENUM');
        $this->assertSame('string', $dbField->getBackingType(), 'String-backed enum reports string backing type');
    }

    public function testConstructorAcceptsNullEnumClass(): void
    {
        $dbField = new DBSmartEnum('Status');

        $this->assertSame('Status', $dbField->getName(), 'Field name is set when enum class is omitted');
        $this->assertSame([], $dbField->getEnum(), 'No enum values when enum class is omitted');
        $this->assertNull($dbField->getEnumClass(), 'Enum class remains null when not configured');
        $this->assertNull($dbField->getDefault(), 'Default is null when enum class is omitted');
    }

    public function testConstructorDefaultIsNullWhenOmitted(): void
    {
        $dbField = new DBSmartEnum('Status', TestColor::class);

        $this->assertNull(
            $dbField->getDefault(),
            'Omitted default leaves the column default null'
        );
    }

    public function testConstructorAcceptsEnumCaseAsDefault(): void
    {
        $dbField = new DBSmartEnum('Status', TestColor::class, TestColor::Red);

        $this->assertSame(
            TestColor::Red->value,
            $dbField->getDefault(),
            'Enum case default is normalised to the backing scalar'
        );
    }

    public function testConstructorAcceptsExplicitNullDefault(): void
    {
        $dbField = new DBSmartEnum('Status', TestColor::class, null);

        $this->assertNull(
            $dbField->getDefault(),
            'Explicit null default leaves the column default null'
        );
    }

    public function testConstructorThrowsWhenScalarDefaultNotInEnum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match any case');

        new DBSmartEnum('Status', TestColor::class, 'green');
    }

    public function testConstructorThrowsWhenIntegerDefaultIsIndexNotBackingValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match any case');

        new DBSmartEnum('Status', TestColor::class, 0);
    }

    public function testConstructorThrowsWhenEnumCaseClassMismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(TestColor::class);

        new DBSmartEnum('Status', TestColor::class, TestSize::Small);
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

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string, 2: string, 3?: int}>
     */
    public function storageFromOptionsProvider(): array
    {
        return [
            'default enum storage' => [
                [],
                'enum',
                'enum',
            ],
            'scalar without explicit length' => [
                ['storage' => 'scalar'],
                'scalar',
                'varchar',
            ],
            'varchar alias without explicit length' => [
                ['storage' => 'varchar'],
                'scalar',
                'varchar',
            ],
            'scalar with explicit length' => [
                ['storage' => 'scalar', 'varchar_length' => 32],
                'scalar',
                'varchar',
                32,
            ],
        ];
    }

    /**
     * @dataProvider storageFromOptionsProvider
     * @param array<string, mixed> $options
     */
    public function testResolvesStorageFromOptions(
        array $options,
        string $expectedStorage,
        string $expectedColumnType,
        ?int $expectedLength = null
    ): void {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, $options);

        $this->assertSame(
            $expectedStorage,
            $dbField->getStorage(),
            'Logical storage matches field spec options'
        );
        $this->assertSame(
            $expectedColumnType,
            $dbField->getColumnType(),
            'Physical column type matches field spec options'
        );

        if ($expectedColumnType !== 'varchar') {
            return;
        }

        if ($expectedLength !== null) {
            $this->assertSame(
                $expectedLength,
                $dbField->getVarcharLength(),
                'VARCHAR length respects explicit varchar_length option'
            );
            $this->assertArrayNotHasKey(
                'storage',
                $dbField->getOptions(),
                'storage is consumed and not passed to parent DBEnum'
            );
            $this->assertArrayNotHasKey(
                'varchar_length',
                $dbField->getOptions(),
                'varchar_length is consumed and not passed to parent DBEnum'
            );

            return;
        }

        $this->assertGreaterThanOrEqual(
            3,
            $dbField->getVarcharLength(),
            'VARCHAR length defaults from longest backing value when varchar_length is omitted'
        );
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>}>
     */
    public function enumValuesStorageProvider(): array
    {
        return [
            'enum storage' => ['enum', []],
            'scalar storage' => ['scalar', ['storage' => 'scalar']],
        ];
    }

    /**
     * @dataProvider enumValuesStorageProvider
     * @param array<string, mixed> $options
     */
    public function testEnumValuesForCmsDropdown(string $storageLabel, array $options): void
    {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, $options);

        $this->assertSame(
            [
                TestColor::Red->value => TestColor::Red->value,
                TestColor::Blue->value => TestColor::Blue->value,
            ],
            $dbField->enumValues(false),
            sprintf(
                'CMS dropdown options stay backing-value keyed when physical storage is %s',
                $storageLabel
            )
        );
    }

    public function testIntBackedEnumConstructor(): void
    {
        $dbField = new DBSmartEnum('Priority', TestPriority::class, 1);

        $this->assertSame(
            [TestPriority::Low->value, TestPriority::High->value],
            $dbField->getEnum(),
            'Int backing values are read from the enum cases'
        );
        $this->assertSame(1, $dbField->getDefault(), 'Int default is set correctly');
        $this->assertSame('int', $dbField->getBackingType(), 'Int-backed enum reports int backing type');
        $this->assertSame('enum', $dbField->getStorage(), 'Default logical storage is enum');
        $this->assertSame('enum', $dbField->getColumnType(), 'Default column type is MySQL ENUM for int-backed enum');
    }

    public function testIntBackedEnumAcceptsEnumCaseAsDefault(): void
    {
        $dbField = new DBSmartEnum('Priority', TestPriority::class, TestPriority::Low);

        $this->assertSame(
            TestPriority::Low->value,
            $dbField->getDefault(),
            'Enum case default is normalised to the int backing scalar'
        );
    }

    public function testIntBackedEnumRejectsInvalidIntDefault(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match any case');

        new DBSmartEnum('Priority', TestPriority::class, 2);
    }

    public function testIntBackedEnumScalarStorageUsesIntColumn(): void
    {
        $dbField = new DBSmartEnum(
            'Priority',
            TestPriority::class,
            TestPriority::High->value,
            ['storage' => 'scalar']
        );

        $this->assertSame('scalar', $dbField->getStorage(), 'Scalar logical storage is configured');
        $this->assertSame('int', $dbField->getColumnType(), 'Int-backed scalar storage maps to INT column');
    }

    public function testIntBackedEnumVarcharAliasUsesIntColumn(): void
    {
        $dbField = new DBSmartEnum(
            'Priority',
            TestPriority::class,
            TestPriority::High->value,
            ['storage' => 'varchar']
        );

        $this->assertSame('scalar', $dbField->getStorage(), 'varchar alias normalises to scalar');
        $this->assertSame('int', $dbField->getColumnType(), 'Int-backed varchar alias still maps to INT column');
    }

    public function testIntBackedEnumValuesForCmsDropdown(): void
    {
        $dbField = new DBSmartEnum('Priority', TestPriority::class, 1);

        $this->assertSame(
            [
                TestPriority::Low->value => TestPriority::Low->value,
                TestPriority::High->value => TestPriority::High->value,
            ],
            $dbField->enumValues(false),
            'CMS dropdown options stay int backing-value keyed'
        );
    }
}
