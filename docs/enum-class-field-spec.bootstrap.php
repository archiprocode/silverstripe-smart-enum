<?php

/**
 * Opt-in bootstrap: enable backed enum FQCNs in DataObject `$db` arrays.
 *
 * Copy to your application (for example `app/_config/smart-enum-enum-class.php`).
 * Do not place this file under this module's `_config/` directory.
 */

use ArchiPro\Silverstripe\SmartEnum\SmartEnumServiceConfigurationLocator;

// Preferred: idempotent, safe if bootstrap runs more than once.
SmartEnumServiceConfigurationLocator::install();

// Equivalent explicit form:
// use SilverStripe\Core\Injector\Injector;
// $injector = Injector::inst();
// $injector->setConfigLocator(new SmartEnumServiceConfigurationLocator($injector->getConfigLocator()));
