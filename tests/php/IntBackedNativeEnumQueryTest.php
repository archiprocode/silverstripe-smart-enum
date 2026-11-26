<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests;

use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\CrossedIntEnumItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\IntEnumFilterHolder;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\SmartEnumTestItem;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestCrossedPriority;
use ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures\TestPriority;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Filters\ExactMatchFilter;
use SilverStripe\ORM\Filters\WithinRangeFilter;
use SilverStripe\ORM\Search\SearchContext;

/**
 * Int-backed SmartEnum columns default to a MySQL ENUM of the backing numbers.
 * Queries that send those numbers as PHP integers must match the backing value.
 *
 * These tests fail while an integer parameter is compared as a 1-based ENUM
 * index. They pass once comparisons use the backing value.
 *
 * @internal
 */
class IntBackedNativeEnumQueryTest extends SapphireTest
{
    /**
     * Test classes live under tests/php and are not in the Silverstripe class manifest.
     */
    protected bool $doSetSupportedModuleLocaleToUS = false;

    /**
     * @var array<int, class-string>
     */
    protected static $extra_dataobjects = [
        SmartEnumTestItem::class,
        CrossedIntEnumItem::class,
        IntEnumFilterHolder::class,
    ];

    /**
     * Empty list avoids fixture path resolution (test classes are not in the SS manifest).
     *
     * @var array<int, string>
     */
    protected static $fixture_file = [];

    protected $usesDatabase = true;

    public function testWritesStoreBackingValueForIntStringEnumAndFormCast(): void
    {
        $byInt = $this->writePriority(TestPriority::High->value);
        $byString = $this->writePriority((string) TestPriority::High->value);
        $byEnum = $this->writePriority(TestPriority::High);
        $byFormCast = SmartEnumTestItem::create();
        $byFormCast->setCastedField('Priority', (string) TestPriority::High->value);
        $byFormCast->write();

        foreach ([$byInt, $byString, $byEnum, $byFormCast->ID] as $id) {
            $reloaded = SmartEnumTestItem::get()->byID($id);
            $this->assertSame(
                TestPriority::High,
                $reloaded->getPriority(),
                'A stored high priority reads back as the high case'
            );
            $this->assertEquals(
                TestPriority::High->value,
                $reloaded->getField('Priority'),
                'The column value read from the row is the backing value'
            );
        }
    }

    public function testExactFilterByIntBackingValueReturnsThatCase(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority', TestPriority::High->value),
            'Filtering by the high backing value returns the high row'
        );
        $this->assertSameIds(
            [$ids['low']],
            SmartEnumTestItem::get()->filter('Priority', TestPriority::Low->value),
            'Filtering by the low backing value returns the low row'
        );
    }

    public function testExactFilterByIndexThatIsNotABackingValueReturnsNothing(): void
    {
        $this->writeLowAndHigh();

        $this->assertSameIds(
            [],
            SmartEnumTestItem::get()->filter('Priority', 2),
            'An integer that is not a backing value matches no rows'
        );
    }

    public function testExactFilterByTypedAccessorBackingIntReturnsThatCase(): void
    {
        $ids = $this->writeLowAndHigh();
        $backing = SmartEnumTestItem::get()->byID($ids['high'])->getPriority()->value;

        $this->assertIsInt($backing);
        $this->assertSame(TestPriority::High->value, $backing);
        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority', $backing),
            'The int from the typed accessor filters back to the same case'
        );
    }

    public function testExactFilterByDbObjectValueReturnsThatCase(): void
    {
        $ids = $this->writeLowAndHigh();
        $backing = SmartEnumTestItem::get()->byID($ids['high'])->dbObject('Priority')->getValue();

        $this->assertEquals(TestPriority::High->value, $backing);
        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority', $backing),
            'The value returned by the field getter filters back to the same case'
        );
    }

    public function testInFilterByIntBackingValuesReturnsEachMatchingCase(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority', [TestPriority::High->value]),
            'An IN list containing only the high backing value returns the high row'
        );
        $this->assertSameIds(
            [$ids['low'], $ids['high']],
            SmartEnumTestItem::get()->filter('Priority', [
                TestPriority::Low->value,
                TestPriority::High->value,
            ]),
            'An IN list of both backing values returns both rows'
        );
        $this->assertSameIds(
            [],
            SmartEnumTestItem::get()->filter('Priority', [2]),
            'An IN list of an integer that is not a backing value returns nothing'
        );
    }

    public function testExcludeByIntBackingValueRemovesThatCase(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['low']],
            SmartEnumTestItem::get()->exclude('Priority', TestPriority::High->value),
            'Excluding the high backing value leaves the low row'
        );
    }

    public function testNotModifierByIntBackingValueRemovesThatCase(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['low']],
            SmartEnumTestItem::get()->filter('Priority:not', TestPriority::High->value),
            'A not-match on the high backing value leaves the low row'
        );
    }

    public function testFilterAnyByIntBackingValueReturnsThatCase(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filterAny('Priority', TestPriority::High->value),
            'filterAny on the high backing value returns the high row'
        );
    }

    public function testExcludeAnyByIntBackingValueRemovesThatCase(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['low']],
            SmartEnumTestItem::get()->excludeAny('Priority', TestPriority::High->value),
            'excludeAny on the high backing value leaves the low row'
        );
    }

    public function testLessThanIntBackingValueUsesBackingValue(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['low']],
            SmartEnumTestItem::get()->filter('Priority:LessThan', TestPriority::High->value),
            'LessThan the high backing value returns only the low row'
        );
    }

    public function testGreaterThanOrEqualIntBackingValueUsesBackingValue(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter(
                'Priority:GreaterThanOrEqual',
                TestPriority::High->value
            ),
            'GreaterThanOrEqual the high backing value returns only the high row'
        );
    }

    public function testLessThanOrEqualAndGreaterThanIntBackingValues(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['low']],
            SmartEnumTestItem::get()->filter('Priority:LessThanOrEqual', TestPriority::Low->value),
            'LessThanOrEqual the low backing value returns only the low row'
        );
        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority:GreaterThan', TestPriority::Low->value),
            'GreaterThan the low backing value returns only the high row'
        );
    }

    public function testExcludeLessThanIntBackingValueUsesInverseComparison(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->exclude('Priority:LessThan', TestPriority::High->value),
            'Excluding rows less than the high backing value leaves the high row'
        );
    }

    public function testComparisonFiltersCoerceNumericStringsBeforeBinding(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['low']],
            SmartEnumTestItem::get()->filter('Priority:LessThan', (string) TestPriority::High->value),
            'LessThan a numeric string uses the backing value, not the ENUM index'
        );
        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter(
                'Priority:GreaterThanOrEqual',
                (string) TestPriority::High->value
            ),
            'GreaterThanOrEqual a numeric string uses the backing value, not the ENUM index'
        );
    }

    public function testWhereClauseBoundIntBackingValueReturnsThatCase(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->where([
                '"SmartEnumTestItem"."Priority" = ?' => TestPriority::High->value,
            ]),
            'A parameterised WHERE bound with the high backing int returns the high row'
        );
        $this->assertSameIds(
            [],
            SmartEnumTestItem::get()->where([
                '"SmartEnumTestItem"."Priority" = ?' => 2,
            ]),
            'A parameterised WHERE bound with an int that is not a backing value returns nothing'
        );
    }

    public function testSearchContextIntBackingValueReturnsThatCase(): void
    {
        $ids = $this->writeLowAndHigh();
        $context = SearchContext::create(
            SmartEnumTestItem::class,
            null,
            ['Priority' => ExactMatchFilter::create('Priority')]
        );

        $this->assertSameIds(
            [$ids['high']],
            $context->getResults(['Priority' => TestPriority::High->value]),
            'SearchContext filters the high backing int to the high row'
        );
        $this->assertSameIds(
            [],
            $context->getResults(['Priority' => 2]),
            'SearchContext ignores an int that is not a backing value'
        );
    }

    public function testSearchContextStringBackingValueStillMatches(): void
    {
        $ids = $this->writeLowAndHigh();
        $context = SearchContext::create(
            SmartEnumTestItem::class,
            null,
            ['Priority' => ExactMatchFilter::create('Priority')]
        );

        $this->assertSameIds(
            [$ids['high']],
            $context->getResults(['Priority' => (string) TestPriority::High->value]),
            'SearchContext matches a string backing value'
        );
    }

    public function testHasOneFilterByIntBackingValueReturnsThatCase(): void
    {
        $ids = $this->writeLowAndHigh();
        $lowHolder = IntEnumFilterHolder::create();
        $lowHolder->ItemID = $ids['low'];
        $lowHolder->write();
        $highHolder = IntEnumFilterHolder::create();
        $highHolder->ItemID = $ids['high'];
        $highHolder->write();

        $this->assertSameIds(
            [$highHolder->ID],
            IntEnumFilterHolder::get()->filter('Item.Priority', TestPriority::High->value),
            'A has_one filter by the high backing value returns the holder of the high row'
        );
        $this->assertSameIds(
            [],
            IntEnumFilterHolder::get()->filter('Item.Priority', 2),
            'A has_one filter by an int that is not a backing value returns nothing'
        );
    }

    public function testWithinRangeIntBackingValueUsesBackingValue(): void
    {
        $ids = $this->writeLowAndHigh();

        $highOnly = SmartEnumTestItem::get()->alterDataQuery(function (DataQuery $query) {
            $filter = WithinRangeFilter::create('Priority');
            $filter->setMin(TestPriority::High->value);
            $filter->setMax(TestPriority::High->value);
            $filter->apply($query);
        });
        $this->assertSameIds(
            [$ids['high']],
            $highOnly,
            'A range of the high backing value returns the high row'
        );
    }

    public function testWithinRangeIndexThatIsNotABackingValueReturnsNothing(): void
    {
        $this->writeLowAndHigh();
        $indexOnly = SmartEnumTestItem::get()->alterDataQuery(function (DataQuery $query) {
            $filter = WithinRangeFilter::create('Priority');
            $filter->setMin(2);
            $filter->setMax(2);
            $filter->apply($query);
        });

        $this->assertSameIds(
            [],
            $indexOnly,
            'A range of an integer that is not a backing value returns nothing'
        );
    }

    public function testSortByFieldOrdersByIntBackingValues(): void
    {
        $ids = $this->writeLowAndHigh();
        $order = DB::get_conn()->sortByField(
            '"SmartEnumTestItem"."Priority"',
            [TestPriority::Low->value, TestPriority::High->value]
        );
        $ordered = SmartEnumTestItem::get()->alterDataQuery(function (DataQuery $query) use ($order) {
            $query->sort($order);
        });

        $this->assertSame(
            [$ids['low'], $ids['high']],
            array_map('intval', $ordered->column('ID')),
            'sortByField with backing ints orders the low case before the high case'
        );
    }

    public function testStringExactFilterStillMatchesBackingValue(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority', (string) TestPriority::High->value),
            'A string exact filter matches the backing value'
        );
        $this->assertSameIds(
            [],
            SmartEnumTestItem::get()->filter('Priority', '2'),
            'A string that is not a backing value matches no rows'
        );
    }

    public function testPartialStartsAndEndsWithIntUseBackingText(): void
    {
        $ids = $this->writeLowAndHigh();

        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority:PartialMatch', TestPriority::High->value),
            'PartialMatch of the high backing value returns the high row'
        );
        $this->assertSameIds(
            [],
            SmartEnumTestItem::get()->filter('Priority:PartialMatch', 2),
            'PartialMatch of an integer that is not a backing value returns nothing'
        );
        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority:StartsWith', TestPriority::High->value),
            'StartsWith the high backing value returns the high row'
        );
        $this->assertSameIds(
            [$ids['high']],
            SmartEnumTestItem::get()->filter('Priority:EndsWith', TestPriority::High->value),
            'EndsWith the high backing value returns the high row'
        );
    }

    public function testFieldSpecDisablingNativeEnumFiltersByBackingValue(): void
    {
        $low = SmartEnumTestItem::create();
        $low->PriorityAsInt = TestPriority::Low->value;
        $low->write();
        $high = SmartEnumTestItem::create();
        $high->PriorityAsInt = TestPriority::High->value;
        $high->write();

        $this->assertSameIds(
            [$high->ID],
            SmartEnumTestItem::get()->filter('PriorityAsInt', TestPriority::High->value),
            'A field spec with use_native_db_enum false filters by the backing value'
        );
        $this->assertSameIds(
            [],
            SmartEnumTestItem::get()->filter('PriorityAsInt', 2),
            'That field does not match an integer that is not a backing value'
        );
    }

    public function testCrossedEnumFilterReturnsTheRequestedCase(): void
    {
        $high = $this->writeRank(TestCrossedPriority::High);
        $low = $this->writeRank(TestCrossedPriority::Low);

        $this->assertSame(
            TestCrossedPriority::High,
            CrossedIntEnumItem::get()->byID($high)->getRank()
        );
        $this->assertSame(
            TestCrossedPriority::Low,
            CrossedIntEnumItem::get()->byID($low)->getRank()
        );
        $this->assertSameIds(
            [$low],
            CrossedIntEnumItem::get()->filter('Rank', TestCrossedPriority::Low->value),
            'Filtering for the low case returns the low row'
        );
        $this->assertSameIds(
            [$high],
            CrossedIntEnumItem::get()->filter('Rank', TestCrossedPriority::High->value),
            'Filtering for the high case returns the high row'
        );
    }

    public function testCrossedEnumDefaultRowMatchesItsBackingValue(): void
    {
        $item = CrossedIntEnumItem::create();
        $item->write();
        $reloaded = CrossedIntEnumItem::get()->byID($item->ID);

        $this->assertSame(
            TestCrossedPriority::High,
            $reloaded->getRank(),
            'The column default is the high case'
        );
        $this->assertSameIds(
            [$item->ID],
            CrossedIntEnumItem::get()->filter('Rank', TestCrossedPriority::High->value),
            'Filtering by the default case backing value returns the default row'
        );
    }

    public function testCrossedEnumDefaultIsNotReturnedForTheOtherCase(): void
    {
        $item = CrossedIntEnumItem::create();
        $item->write();

        $this->assertSame(
            TestCrossedPriority::High,
            CrossedIntEnumItem::get()->byID($item->ID)->getRank(),
            'The column default is the high case'
        );
        $this->assertSameIds(
            [],
            CrossedIntEnumItem::get()->filter('Rank', TestCrossedPriority::Low->value),
            'Filtering by the low backing value does not return the high default row'
        );
    }

    /**
     * @param array<int, int> $expectedIds
     */
    private function assertSameIds(array $expectedIds, DataList $list, string $message): void
    {
        $actual = array_map('intval', $list->column('ID'));
        sort($expectedIds);
        sort($actual);
        $this->assertSame($expectedIds, $actual, $message);
    }

    /**
     * @return array{low: int, high: int}
     */
    private function writeLowAndHigh(): array
    {
        return [
            'low' => $this->writePriority((string) TestPriority::Low->value),
            'high' => $this->writePriority((string) TestPriority::High->value),
        ];
    }

    private function writePriority(mixed $value): int
    {
        $item = SmartEnumTestItem::create();
        $item->Priority = $value;
        $id = $item->write();
        $this->assertNotFalse($id);

        return (int) $id;
    }

    private function writeRank(TestCrossedPriority $rank): int
    {
        $item = CrossedIntEnumItem::create();
        $item->Rank = (string) $rank->value;
        $id = $item->write();
        $this->assertNotFalse($id);

        return (int) $id;
    }
}
