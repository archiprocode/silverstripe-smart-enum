<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\BackedEnumDetection;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestColor;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\UnitOnlyEnum;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class BackedEnumDetectionTest extends TestCase
{
    public function testIsBackedEnumClassReturnsTrueForBackedEnum(): void
    {
        $this->assertTrue(BackedEnumDetection::isBackedEnumClass(TestColor::class));
    }

    public function testIsBackedEnumClassReturnsFalseForUnitEnum(): void
    {
        $this->assertFalse(BackedEnumDetection::isBackedEnumClass(UnitOnlyEnum::class));
    }

    public function testIsBackedEnumClassReturnsFalseForOrdinaryClass(): void
    {
        $this->assertFalse(BackedEnumDetection::isBackedEnumClass(SmartEnumTestItem::class));
    }

    public function testIsBackedEnumClassReturnsFalseForNull(): void
    {
        $this->assertFalse(BackedEnumDetection::isBackedEnumClass(null));
    }

    public function testIsBackedEnumClassReturnsFalseForEmptyString(): void
    {
        $this->assertFalse(BackedEnumDetection::isBackedEnumClass(''));
    }
}
