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
        $this->assertSame(TestColor::class, $dbField->getEnumClass(), 'BackedEnum class name is retained on the field');
        $this->assertSame('enum', $dbField->getStorage(), 'Default physical storage is MySQL ENUM');
    }

    public function testConstructorAcceptsNullEnumClass(): void
    {
        $dbField = new DBSmartEnum('Status');

        $this->assertSame('Status', $dbField->getName(), 'Field name is set when enum class is omitted');
        $this->assertSame([], $dbField->getEnum(), 'No enum values when enum class is omitted');
        $this->assertNull($dbField->getEnumClass(), 'Enum class remains null when not configured');
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
     * @return array<string, array{0: array<string, mixed>, 1: string, 2?: int}>
     */
    public function storageFromOptionsProvider(): array
    {
        return [
            'default enum storage' => [
                [],
                'enum',
            ],
            'varchar without explicit length' => [
                ['storage' => 'varchar'],
                'varchar',
            ],
            'varchar with explicit length' => [
                ['storage' => 'varchar', 'varchar_length' => 32],
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
        ?int $expectedLength = null
    ): void {
        $dbField = new DBSmartEnum('Color', TestColor::class, TestColor::Red->value, $options);

        $this->assertSame(
            $expectedStorage,
            $dbField->getStorage(),
            'Physical storage matches field spec options'
        );

        if ($expectedStorage !== 'varchar') {
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
            'varchar storage' => ['varchar', ['storage' => 'varchar']],
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
}
