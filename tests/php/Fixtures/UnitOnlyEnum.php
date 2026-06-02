<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

/**
 * Non-backed enum used to assert DBSmartEnum rejects unit enums.
 */
enum UnitOnlyEnum
{
    case One;
}
