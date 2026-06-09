<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

/**
 * Int-backed enum for scalar and ENUM storage tests.
 */
enum TestPriority: int
{
    case Low = 1;
    case High = 3;
}
