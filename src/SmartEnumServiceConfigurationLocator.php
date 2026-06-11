<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Injector\ServiceConfigurationLocator;

/**
 * Decorator that maps backed enum FQCNs to {@see DBSmartEnum} Injector specs when no explicit binding exists.
 *
 * Opt in via {@see install()} at application bootstrap; not enabled by default.
 */
class SmartEnumServiceConfigurationLocator implements ServiceConfigurationLocator
{
    private ServiceConfigurationLocator $inner;

    public function __construct(ServiceConfigurationLocator $inner)
    {
        $this->inner = $inner;
    }

    /**
     * Wrap the Injector's current config locator so enum-class `$db` specs resolve without per-enum YAML.
     *
     * Idempotent: does nothing when this decorator is already installed.
     */
    public static function install(): void
    {
        $injector = Injector::inst();
        $existing = $injector->getConfigLocator();
        if ($existing instanceof SmartEnumServiceConfigurationLocator) {
            return;
        }

        $injector->setConfigLocator(new SmartEnumServiceConfigurationLocator($existing));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function locateConfigFor($name)
    {
        $config = $this->inner->locateConfigFor($name);
        if ($config !== null) {
            return $config;
        }

        if (!BackedEnumDetection::isBackedEnumClass($name)) {
            return null;
        }

        return [
            'class' => $name,
            'factory' => DBSmartEnumFactory::class,
            'type' => 'prototype',
        ];
    }
}
