<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\DBSmartEnum;
use ArchiPro\Silverstripe\SmartEnum\SmartEnumServiceConfigurationLocator;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Injector resolution for enum-class field specs (no database).
 *
 * @internal
 */
class SmartEnumEnumClassInjectorTest extends SapphireTest
{
    protected bool $doSetSupportedModuleLocaleToUS = false;

    protected $usesDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        Injector::nest();
        SmartEnumServiceConfigurationLocator::install();
    }

    protected function tearDown(): void
    {
        Injector::unnest();
        parent::tearDown();
    }

    public function testInjectorCreateFromEnumClass(): void
    {
        $field = Injector::inst()->create(TestColor::class, 'Color');

        $this->assertInstanceOf(DBSmartEnum::class, $field);
        $this->assertSame('Color', $field->getName());
        $this->assertSame(TestColor::class, $field->getEnumClass());
        $this->assertSame(
            [TestColor::Red->value, TestColor::Blue->value],
            $field->getEnum()
        );
    }

    public function testInjectorCreateWithDefaultInSpec(): void
    {
        $spec = TestColor::class . '("red")';
        $field = Injector::inst()->create($spec, 'ColorWithDefault');

        $this->assertSame(TestColor::Red->value, $field->getDefault());
    }
}
