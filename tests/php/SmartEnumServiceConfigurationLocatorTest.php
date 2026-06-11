<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\DBSmartEnumFactory;
use ArchiPro\Silverstripe\SmartEnum\SmartEnumServiceConfigurationLocator;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\ServiceConfigurationLocator;
use SilverStripe\Dev\SapphireTest;

/**
 * @internal
 */
class SmartEnumServiceConfigurationLocatorTest extends SapphireTest
{
    protected bool $doSetSupportedModuleLocaleToUS = false;

    protected $usesDatabase = false;

    public function testDelegatesToInnerWhenConfigExists(): void
    {
        $inner = $this->createMock(ServiceConfigurationLocator::class);
        $inner->expects($this->once())
            ->method('locateConfigFor')
            ->with('SmartEnum')
            ->willReturn(['class' => 'Example']);

        $locator = new SmartEnumServiceConfigurationLocator($inner);

        $this->assertSame(['class' => 'Example'], $locator->locateConfigFor('SmartEnum'));
    }

    public function testSynthesizesSpecForBackedEnumWhenInnerReturnsNull(): void
    {
        $inner = $this->createMock(ServiceConfigurationLocator::class);
        $inner->method('locateConfigFor')->willReturn(null);

        $locator = new SmartEnumServiceConfigurationLocator($inner);
        $config = $locator->locateConfigFor(TestColor::class);

        $this->assertSame(TestColor::class, $config['class']);
        $this->assertSame(DBSmartEnumFactory::class, $config['factory']);
        $this->assertSame('prototype', $config['type']);
    }

    public function testReturnsNullForNonEnumServiceName(): void
    {
        $inner = $this->createMock(ServiceConfigurationLocator::class);
        $inner->method('locateConfigFor')->willReturn(null);

        $locator = new SmartEnumServiceConfigurationLocator($inner);

        $this->assertNull($locator->locateConfigFor(\stdClass::class));
    }

    public function testDecoratorChainPreservesInnerResolution(): void
    {
        $sentinel = ['class' => 'InnerWinner'];
        $inner = $this->createMock(ServiceConfigurationLocator::class);
        $inner->expects($this->once())
            ->method('locateConfigFor')
            ->with(TestColor::class)
            ->willReturn($sentinel);

        $locator = new SmartEnumServiceConfigurationLocator($inner);

        $this->assertSame($sentinel, $locator->locateConfigFor(TestColor::class));
    }

    public function testInstallWrapsExistingLocator(): void
    {
        Injector::nest();
        try {
            $before = Injector::inst()->getConfigLocator();
            SmartEnumServiceConfigurationLocator::install();
            $after = Injector::inst()->getConfigLocator();

            $this->assertInstanceOf(SmartEnumServiceConfigurationLocator::class, $after);
            $this->assertNotSame($before, $after);
        } finally {
            Injector::unnest();
        }
    }

    public function testInstallIsIdempotent(): void
    {
        Injector::nest();
        try {
            SmartEnumServiceConfigurationLocator::install();
            $first = Injector::inst()->getConfigLocator();
            SmartEnumServiceConfigurationLocator::install();
            $second = Injector::inst()->getConfigLocator();

            $this->assertSame($first, $second);
        } finally {
            Injector::unnest();
        }
    }
}
