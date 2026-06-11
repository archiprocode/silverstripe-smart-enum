<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use InvalidArgumentException;
use SilverStripe\Core\Injector\Factory;

/**
 * Creates {@see DBSmartEnum} instances when a backed enum FQCN is used as an Injector service id.
 */
class DBSmartEnumFactory implements Factory
{
    /**
     * @param string $service BackedEnum FQCN from the synthesized Injector spec `class` key
     * @param array<int, mixed> $params Field name, optional default, optional options array
     */
    public function create($service, array $params = []): DBSmartEnum
    {
        if (!is_string($service) || !BackedEnumDetection::isBackedEnumClass($service)) {
            throw new InvalidArgumentException(sprintf(
                'DBSmartEnumFactory: service "%s" is not a BackedEnum class name.',
                is_string($service) ? $service : get_debug_type($service)
            ));
        }

        $name = $params[0] ?? null;
        $default = array_key_exists(1, $params) ? $params[1] : null;
        $options = isset($params[2]) && is_array($params[2]) ? $params[2] : [];

        return new DBSmartEnum($name, $service, $default, $options);
    }
}
