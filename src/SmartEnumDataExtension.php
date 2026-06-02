<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use BackedEnum;
use SilverStripe\ORM\DataExtension;
use SilverStripe\ORM\DataObject;
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
        /** @var DataObject $owner */
        $owner = $this->getOwner();
        $enumClass = $this->getEnumClassForField($field);
        if ($enumClass === null) {
            return null;
        }

        $value = $owner->getField($field);
        if ($value === null || $value === '') {
            return null;
        }

        /** @var class-string<BackedEnum> $enumClass */
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
        /** @var DataObject $owner */
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
        /** @var DataObject $owner */
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
        /** @var DataObject $owner */
        $owner = $this->getOwner();

        try {
            $dbObject = $owner->dbObject($field);
            if ($dbObject instanceof DBSmartEnum) {
                $enumClass = $dbObject->getEnumClass();
                if ($enumClass !== null && is_subclass_of($enumClass, BackedEnum::class)) {
                    return $enumClass;
                }
            }
        } catch (\Throwable) {
            // Fall through to schema parsing when the database is unavailable (e.g. unit tests).
        }

        $fieldSpec = DataObjectSchema::create()->databaseField(get_class($owner), $field, false);
        if (!is_string($fieldSpec)) {
            return null;
        }

        if (!preg_match('/(?:DB)?SmartEnum\("((?:[^"\\\\]|\\\\.)+)"/', $fieldSpec, $matches)) {
            return null;
        }

        $enumClass = stripcslashes($matches[1]);

        return is_subclass_of($enumClass, BackedEnum::class) ? $enumClass : null;
    }
}
