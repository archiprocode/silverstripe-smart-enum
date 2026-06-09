<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use BackedEnum;
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
        $smartEnumFields = $this->getSmartEnumFieldNames();
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
        $smartEnumFields = $this->getSmartEnumFieldNames();

        if (str_starts_with($method, 'get')) {
            $field = substr($method, 3);
            if (in_array($field, $smartEnumFields, true)) {
                return $this->readEnum($field);
            }
        }

        if (str_starts_with($method, 'set')) {
            $field = substr($method, 3);
            if (in_array($field, $smartEnumFields, true)) {
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
        $owner = $this->getOwner();
        $enumClass = $this->getEnumClassForField($field);
        if ($enumClass === null) {
            return null;
        }

        $value = $owner->getField($field);
        if ($value === null || $value === '') {
            return null;
        }

        $value = $this->normaliseStoredValue($value, $field, $enumClass);

        if ($value === null) {
            return null;
        }

        return $enumClass::tryFrom($value);
    }

    /**
     * Coerce DB values to the enum backing type (e.g. MySQL ENUM returns stringified ints).
     *
     * @param class-string<BackedEnum> $enumClass
     */
    private function normaliseStoredValue(mixed $value, string $field, string $enumClass): int|string|null
    {
        if ($this->getBackingTypeForField($field) !== 'int') {
            return is_string($value) || is_int($value) ? $value : null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    /**
     * Backing scalar type for the field's enum: `string` or `int`.
     */
    private function getBackingTypeForField(string $field): string
    {
        $dbObject = $this->getOwner()->dbObject($field);
        if ($dbObject instanceof DBSmartEnum) {
            return $dbObject->getBackingType();
        }

        return 'string';
    }

    /**
     * Persist a BackedEnum instance or scalar backing value for the field.
     *
     * @param mixed $value
     * @return $this
     */
    private function writeEnum(string $field, mixed $value): static
    {
        $owner = $this->getOwner();

        if ($value instanceof BackedEnum) {
            $owner->setField($field, $value->value);
            return $this;
        }

        if ($value === null || is_string($value) || is_int($value)) {
            $owner->setField($field, $value);
            return $this;
        }

        throw new \InvalidArgumentException(sprintf(
            'SmartEnumDataExtension: set%s() expects a BackedEnum, string, int, or null; %s given.',
            $field,
            get_debug_type($value)
        ));
    }

    /**
     * Discover SmartEnum columns from the cached schema without opening a database connection.
     *
     * @return string[]
     */
    private function getSmartEnumFieldNames(): array
    {
        $owner = $this->getOwner();
        $fields = DataObjectSchema::create()->databaseFields(get_class($owner), false);

        return array_keys(array_filter(
            $fields,
            fn (string $fieldSpec) => $this->isSmartEnumFieldSpec($fieldSpec)
        ));
    }

    private function isSmartEnumFieldSpec(string $fieldSpec): bool
    {
        return $fieldSpec === DBSmartEnum::class
            || str_contains($fieldSpec, 'DBSmartEnum(')
            || str_contains($fieldSpec, 'SmartEnum(');
    }

    /**
     * @return class-string<BackedEnum>|null
     */
    private function getEnumClassForField(string $field): ?string
    {
        $dbObject = $this->getOwner()->dbObject($field);
        if (!$dbObject instanceof DBSmartEnum) {
            return null;
        }

        $enumClass = $dbObject->getEnumClass();
        if ($enumClass !== null && is_subclass_of($enumClass, BackedEnum::class)) {
            return $enumClass;
        }

        return null;
    }
}
