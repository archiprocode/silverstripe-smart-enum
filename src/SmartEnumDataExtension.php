<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use BackedEnum;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObjectSchema;

/**
 * Adds typed get/set methods for every {@see DBSmartEnum} column on a DataObject.
 */
class SmartEnumDataExtension extends DataExtension
{
    /**
     * @return string[]
     */
    public function allMethodNames(): array
    {
        $smartEnumFields = array_keys($this->getSmartEnumFieldMap());
        $getters = array_map(fn (string $field) => 'get' . $field, $smartEnumFields);
        $setters = array_map(fn (string $field) => 'set' . $field, $smartEnumFields);

        return array_merge($getters, $setters);
    }

    /**
     * @param string $method
     * @param array<int, mixed> $args
     * @return mixed
     */
    public function __call(string $method, array $args = [])
    {
        $smartEnumFields = $this->getSmartEnumFieldMap();

        if (str_starts_with($method, 'get')) {
            $field = substr($method, 3);
            if (isset($smartEnumFields[$field])) {
                return $this->readEnum($field);
            }
        }

        if (str_starts_with($method, 'set')) {
            $field = substr($method, 3);
            if (isset($smartEnumFields[$field])) {
                return $this->writeEnum($field, $args[0] ?? null);
            }
        }

        return parent::__call($method, $args);
    }

    /**
     * Resolve the stored scalar to a BackedEnum instance, or null when empty/unknown.
     */
    private function readEnum(string $field): ?BackedEnum
    {
        $dbField = $this->getDBSmartEnumForField($field);
        $enumClass = $dbField->getEnumClass();
        if ($enumClass === null) {
            return null;
        }

        $value = $dbField->getValue();
        if ($value === null || $value === '') {
            return null;
        }

        return $enumClass::tryFrom($value);
    }

    /**
     * Persist a BackedEnum instance or scalar backing value for the field.
     *
     * @param mixed $value
     * @return $this
     */
    private function writeEnum(string $field, mixed $value): static
    {
        $dbField = $this->getDBSmartEnumForField($field);
        $dbField->setValue($value);
        $this->getOwner()->setField($field, $dbField->getValue());

        return $this;
    }

    /**
     * Discover SmartEnum columns from schema field specs via the Injector binding.
     *
     * @return array<string, true>
     */
    private function getSmartEnumFieldMap(): array
    {
        static $cache = [];

        $class = $this->getOwner()::class;

        if (isset($cache[$class])) {
            return $cache[$class];
        }

        $map = [];
        $fields = DataObjectSchema::create()->databaseFields($class, false);

        foreach ($fields as $fieldName => $fieldSpec) {
            if ($this->resolveDBSmartEnumClass($fieldSpec) !== null) {
                $map[$fieldName] = true;
            }
        }

        $cache[$class] = $map;

        return $map;
    }

    /**
     * Resolve the DBField class for a schema field spec without instantiating the field.
     *
     * @return class-string<DBSmartEnum>|null
     */
    private function resolveDBSmartEnumClass(string $fieldSpec): ?string
    {
        if (class_exists($fieldSpec) && is_a($fieldSpec, DBSmartEnum::class, true)) {
            return $fieldSpec;
        }

        $serviceName = $fieldSpec;
        if (str_contains($fieldSpec, '(')) {
            [$serviceName] = ClassInfo::parse_class_spec($fieldSpec);
        }

        if (class_exists($serviceName) && is_a($serviceName, DBSmartEnum::class, true)) {
            return $serviceName;
        }

        $spec = Injector::inst()->getServiceSpec($serviceName);
        if (!$spec || empty($spec['class'])) {
            return null;
        }

        $class = $spec['class'];

        return is_a($class, DBSmartEnum::class, true) ? $class : null;
    }

    private function getDBSmartEnumForField(string $field): DBSmartEnum
    {
        $dbObject = $this->getOwner()->dbObject($field);
        if (!$dbObject instanceof DBSmartEnum) {
            throw new \InvalidArgumentException(sprintf(
                'SmartEnumDataExtension: field "%s" on "%s" is not a DBSmartEnum column.',
                $field,
                $this->getOwner()::class
            ));
        }

        return $dbObject;
    }
}
