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

    public function testConstructorThrowsWhenUseNativeDbEnumIsNotBool(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('use_native_db_enum must be a boolean');

        new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, ['use_native_db_enum' => 'false']);
    }

    /**
     * @return array<string, array{0: array<string, mixed>}>
     */
    public function useNativeDbEnumFromOptionsProvider(): array
    {
        return [
            'default native db enum' => [[]],
            'scalar column' => [['use_native_db_enum' => false]],
            'scalar with explicit length' => [['use_native_db_enum' => false, 'varchar_length' => 32]],
        ];
    }

    /**
     * @dataProvider useNativeDbEnumFromOptionsProvider
     * @param array<string, mixed> $options
     */
    public function testConsumesUseNativeDbEnumOptions(array $options): void
    {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, $options);

        $this->assertArrayNotHasKey(
            'use_native_db_enum',
            $dbField->getOptions(),
            'use_native_db_enum is consumed and not passed to parent DBEnum'
        );

        if (!array_key_exists('varchar_length', $options)) {
            return;
        }

        $this->assertArrayNotHasKey(
            'varchar_length',
            $dbField->getOptions(),
            'varchar_length is consumed and not passed to parent DBEnum'
        );
    }

    /**
     * @return array<string, array{0: bool, 1: array<string, mixed>}>
     */
    public function enumValuesUseNativeDbEnumProvider(): array
    {
        return [
            'native db enum' => [true, []],
            'scalar column' => [false, ['use_native_db_enum' => false]],
        ];
    }

    /**
     * @dataProvider enumValuesUseNativeDbEnumProvider
     * @param array<string, mixed> $options
     */
    public function testEnumValuesForCmsDropdown(bool $useNativeDbEnum, array $options): void
    {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, $options);

        $this->assertSame(
            [
                TestColor::Red->value => TestColor::Red->value,
                TestColor::Blue->value => TestColor::Blue->value,
            ],
            $dbField->enumValues(false),
            sprintf(
                'CMS dropdown options stay backing-value keyed when use_native_db_enum is %s',
                $useNativeDbEnum ? 'true' : 'false'
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

    public function testGetValueCoercesStringifiedIntOnlyForNativeDbEnum(): void
    {
        $nativeDbEnumField = new DBSmartEnum('Priority', TestPriority::class);
        $nativeDbEnumField->setValue('3', null, false);

        $this->assertSame(
            TestPriority::High->value,
            $nativeDbEnumField->getValue(),
            'getValue() coerces stringified ENUM ints when use_native_db_enum is true'
        );

        $scalarField = new DBSmartEnum(
            'Priority',
            TestPriority::class,
            null,
            ['use_native_db_enum' => false]
        );
        $scalarField->setValue('3', null, false);

        $this->assertSame(
            '3',
            $scalarField->getValue(),
            'getValue() does not coerce stringified ints when use_native_db_enum is false'
        );
    }

    public function testSetValueRejectsInvalidScalar(): void
    {
        $dbField = new DBSmartEnum('Color', TestColor::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('does not match any case');

        $dbField->setValue('green');
    }

    public function testDefaultUseNativeDbEnumFromConfig(): void
    {
        DBSmartEnum::config()->set('default_use_native_db_enum', false);

        try {
            $dbField = new DBSmartEnum('Priority', TestPriority::class);
            $dbField->setValue('3', null, false);

            $this->assertSame(
                '3',
                $dbField->getValue(),
                'default_use_native_db_enum config applies when field spec omits use_native_db_enum'
            );
        } finally {
            DBSmartEnum::config()->remove('default_use_native_db_enum');
        }
    }
}
