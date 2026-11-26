<?php

namespace ArchiPro\Silverstripe\SmartEnum\Tests\Fixtures;

/**
 * Int-backed enum whose declaration order does not match the backing values.
 *
 * High is declared first, so a native MySQL ENUM stores it at index 1 while its
 * backing value is 2. Low is index 2 while its backing value is 1.
 */
enum TestCrossedPriority: int
{
    case High = 2;
    case Low = 1;
}
