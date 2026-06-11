<?php

namespace ArchiPro\Silverstripe\SmartEnum;

/**
 * Shared checks for whether a string is a resolvable PHP BackedEnum class name.
 */
final class BackedEnumDetection
{
    /**
     * @param class-string|null $className
     */
    public static function isBackedEnumClass(?string $className): bool
    {
        if ($className === null || $className === '') {
            return false;
        }

        if (!enum_exists($className)) {
            return false;
        }

        return (new \ReflectionEnum($className))->isBacked();
    }
}
