<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\SmartEnumServiceConfigurationLocator;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItemEnumClass;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestPriority;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;

/**
 * @internal
 */
class SmartEnumEnumClassFieldSpecTest extends SapphireTest
{
    protected bool $doSetSupportedModuleLocaleToUS = false;

    /**
     * @var array<int, class-string<SmartEnumTestItemEnumClass>>
     */
    protected static $extra_dataobjects = [
        SmartEnumTestItemEnumClass::class,
    ];

    /**
     * @var array<int, string>
     */
    protected static $fixture_file = [];

    protected $usesDatabase = true;

    protected function setUp(): void
    {
        parent::setUp();
        Injector::nest();
        SmartEnumServiceConfigurationLocator::install();
        DataObject::getSchema()->reset();
    }

    protected function tearDown(): void
    {
        Injector::unnest();
        parent::tearDown();
    }

    public function testFixtureDefaultsFromExplicitSpec(): void
    {
        $item = SmartEnumTestItemEnumClass::create();
        $item->write();

        $reloaded = SmartEnumTestItemEnumClass::get()->byID($item->ID);

        $this->assertSame(
            TestColor::Red,
            $reloaded->getColorWithDefault(),
            'ColorWithDefault uses the backing scalar from the enum-class spec parens'
        );
        $this->assertSame(
            TestPriority::Low,
            $reloaded->getPriority(),
            'Priority uses the int default from enum-class spec parens'
        );
    }

    public function testFixtureWithoutDefaultPersistsNull(): void
    {
        $item = SmartEnumTestItemEnumClass::create();
        $item->write();

        $reloaded = SmartEnumTestItemEnumClass::get()->byID($item->ID);

        $this->assertNull($reloaded->getColorNoDefault());
        $this->assertNull($reloaded->getPriorityNoDefault());
    }

    public function testExtensionExposesGettersForEnumClassFields(): void
    {
        $item = SmartEnumTestItemEnumClass::create();
        $item->setColor(TestColor::Blue);
        $item->write();

        $reloaded = SmartEnumTestItemEnumClass::get()->byID($item->ID);

        $this->assertSame(TestColor::Blue, $reloaded->getColor());
        $this->assertSame(TestColor::Blue->value, $reloaded->getField('Color'));
    }
}
