<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use BackedEnum;
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
        $map = [];
        $fields = DataObjectSchema::create()->databaseFields($this->getOwner()::class, false);

        foreach ($fields as $fieldName => $fieldSpec) {
            if ($this->tryResolveSmartEnumFromSpec($fieldSpec, $fieldName) !== null) {
                $map[$fieldName] = true;
            }
        }

        return $map;
    }

    /**
     * Instantiate the schema field spec and return a configured DBSmartEnum, or null when not applicable.
     */
    private function tryResolveSmartEnumFromSpec(string $fieldSpec, string $fieldName): ?DBSmartEnum
    {
        try {
            $field = Injector::inst()->create($fieldSpec, $fieldName);
        } catch (\Throwable) {
            return null;
        }

        if (!$field instanceof DBSmartEnum) {
            return null;
        }

        return $field->getEnumClass() !== null ? $field : null;
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
