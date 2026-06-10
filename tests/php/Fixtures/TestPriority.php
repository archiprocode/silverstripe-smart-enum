<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

/**
 * Int-backed enum for use_native_db_enum true/false column tests.
 */
enum TestPriority: int
{
    case Low = 1;
    case High = 3;
}
