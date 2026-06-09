<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use BackedEnum;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\Connect\MySQLDatabase;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBEnum;

/**
 * DB field whose allowed values are derived from a PHP BackedEnum.
 *
 * When registering on a DataObject `$db` array, double-escape the enum class name in the field spec:
 *
 * ```php
 * $db = ['MyEnum' => 'SmartEnum("My\\\\Namespace\\\\MyEnum", "Value1")'];
 * ```
 */
class DBSmartEnum extends DBEnum
{
    use Configurable;

    /**
     * Global default logical storage when not overridden per field in constructor options.
     *
     * @config
     */
    private static string $default_storage = 'enum';

    /**
     * Fully-qualified BackedEnum class name, when known.
     *
     * @var class-string<BackedEnum>|null
     */
    protected ?string $enumClass = null;

    /**
     * Backing scalar type of the PHP enum: `string` or `int`.
     */
    protected string $backingType = 'string';

    /**
     * Logical storage mode: `enum` (MySQL ENUM) or `scalar` (VARCHAR for strings, INT for ints).
     */
    protected string $storage = 'enum';

    /**
     * VARCHAR column length when {@see getColumnType()} is `varchar`.
     */
    protected int $varcharLength = 255;

    /**
     * @param string|null $name Field name passed through to {@see DBEnum::__construct}
     * @param class-string<BackedEnum>|null $enumClass Fully-qualified PHP enum class name. May be null
     *                               because Silverstripe instantiates DBField subclasses with null
     *                               arguments while inspecting types
     *                               (see {@see \SilverStripe\ORM\DataObject::dbObject()}).
     * @param \BackedEnum|int|string|null $default Backing scalar or enum case; null when omitted.
     * @param array<string, mixed> $options Optional field options; `storage` (`enum`|`scalar`|`varchar`)
     *                                      and `varchar_length` (int) are consumed by this class.
     *
     * @throws \RuntimeException When $enumClass is provided but cannot be resolved (e.g. autoload race in a
     *                           paratest worker fork) or is not a BackedEnum. Without this guard we would
     *                           silently produce a DBEnum with no values, the schema manager would drop the
     *                           column, but the matching $indexes entry would survive, generating a
     *                           `Key column doesn't exist` failure later in CI.
     */
    public function __construct($name = null, $enumClass = null, $default = null, $options = [])
    {
        $values = null;
        $this->enumClass = $enumClass ? (string) $enumClass : null;

        if ($enumClass) {
            if (!enum_exists($enumClass)) {
                throw new \RuntimeException(sprintf(
                    'DBSmartEnum: enum class "%s" could not be resolved. '
                    . 'This usually indicates an autoload race in a forked test worker. '
                    . 'Failing fast here so the schema build does not silently drop the column '
                    . 'while keeping its index.',
                    $enumClass
                ));
            }

            $enumReflection = new \ReflectionEnum($enumClass);

            if (!$enumReflection->isBacked()) {
                throw new \RuntimeException(sprintf(
                    'DBSmartEnum: enum class "%s" is not a BackedEnum. '
                    . 'DBEnum requires scalar values to persist, so only BackedEnums are supported.',
                    $enumClass
                ));
            }

            $backingType = $enumReflection->getBackingType();
            $this->backingType = $backingType->getName();

            $values = array_map(
                fn (\ReflectionEnumBackedCase $case) => $case->getBackingValue(),
                $enumReflection->getCases()
            );
        }

        $this->storage = $this->resolveStorage($options);

        if ($this->storage === 'scalar' && $this->backingType === 'string') {
            $this->varcharLength = $this->resolveVarcharLength($values ?? [], $options);
        }

        $parentOptions = $options;
        unset($parentOptions['storage'], $parentOptions['varchar_length']);

        $resolvedDefault = $this->resolveDefault($this->enumClass, $values ?? [], $default);

        parent::__construct($name, $values, null, $parentOptions);

        if ($resolvedDefault !== null) {
            $this->setDefault($resolvedDefault);
        }
    }

    /**
     * BackedEnum class backing this field, or null when not configured.
     *
     * @return class-string<BackedEnum>|null
     */
    public function getEnumClass(): ?string
    {
        return $this->enumClass;
    }

    /**
     * Backing scalar type of the PHP enum: `string` or `int`.
     */
    public function getBackingType(): string
    {
        return $this->backingType;
    }

    /**
     * Logical storage mode for this field instance: `enum` or `scalar`.
     */
    public function getStorage(): string
    {
        return $this->storage;
    }

    /**
     * Physical database column type: `enum`, `varchar`, or `int`.
     */
    public function getColumnType(): string
    {
        if ($this->storage === 'enum') {
            return 'enum';
        }

        return $this->backingType === 'int' ? 'int' : 'varchar';
    }

    /**
     * VARCHAR length when {@see getColumnType()} is `varchar`.
     */
    public function getVarcharLength(): int
    {
        return $this->varcharLength;
    }

    /**
     * Ensure int-backed values are returned as ints (e.g. MySQL ENUM stringifies ints).
     *
     * @return mixed
     */
    public function getValue()
    {
        $value = parent::getValue();

        if ($this->backingType === 'int' && is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * @param mixed $value
     * @param DataObject|array|null $record
     * @param bool $markChanged
     * @return $this
     */
    public function setValue($value, $record = null, $markChanged = true)
    {
        if (!$markChanged) {
            return parent::setValue($value, $record, $markChanged);
        }

        $normalised = $this->normaliseBackingValue($value, true);

        return parent::setValue($normalised, $record, $markChanged);
    }

    /**
     * @return void
     */
    public function requireField()
    {
        if ($this->storage === 'enum') {
            parent::requireField();
            return;
        }

        if ($this->backingType === 'int') {
            $default = $this->getDefault();

            $parts = [
                'datatype' => 'int',
                'precision' => 11,
                'null' => 'not null',
                'default' => $default !== null ? (int) $default : null,
                'arrayValue' => $this->arrayValue,
            ];

            DB::require_field($this->getTable(), $this->getName(), [
                'type' => 'int',
                'parts' => $parts,
            ]);

            return;
        }

        $charset = Config::inst()->get(MySQLDatabase::class, 'charset');
        $collation = Config::inst()->get(MySQLDatabase::class, 'collation');

        $parts = [
            'datatype' => 'varchar',
            'precision' => $this->varcharLength,
            'character set' => $charset,
            'collate' => $collation,
            'default' => $this->getDefault(),
            'arrayValue' => $this->arrayValue,
        ];

        DB::require_field($this->getTable(), $this->getName(), [
            'type' => 'varchar',
            'parts' => $parts,
        ]);
    }

    /**
     * Normalise and validate the field default against the backing enum.
     *
     * @param class-string<BackedEnum>|null $enumClass
     * @param list<int|string> $values
     *
     * @throws \InvalidArgumentException When a non-null default is not a case or backing scalar on $enumClass.
     */
    private function resolveDefault(?string $enumClass, array $values, mixed $default): int|string|null
    {
        if ($default === null || $enumClass === null) {
            return null;
        }

        return $this->normaliseBackingValue($default, true, $values);
    }

    /**
     * Validate and coerce a value to the enum backing scalar.
     *
     * @param list<int|string>|null $values Allowed backing scalars; defaults to {@see getEnum()}.
     *
     * @throws \InvalidArgumentException When $value is not a valid backing scalar for this enum.
     */
    private function normaliseBackingValue(mixed $value, bool $allowNull, ?array $values = null): int|string|null
    {
        if ($value === null) {
            if ($allowNull) {
                return null;
            }

            throw new \InvalidArgumentException(
                'DBSmartEnum: value must be a BackedEnum case, string, int, or null; null given.'
            );
        }

        $values ??= $this->getEnum();
        $enumClass = $this->enumClass;

        if ($value instanceof BackedEnum) {
            if ($enumClass !== null && !is_a($value, $enumClass)) {
                throw new \InvalidArgumentException(sprintf(
                    'DBSmartEnum: enum case must be an instance of "%s", %s given.',
                    $enumClass,
                    $value::class
                ));
            }

            return $this->coerceScalarForBackingType($value->value);
        }

        if (!is_int($value) && !is_string($value)) {
            throw new \InvalidArgumentException(sprintf(
                'DBSmartEnum: value must be a BackedEnum case, string, int, or null; %s given.',
                get_debug_type($value)
            ));
        }

        $scalar = $this->coerceScalarForBackingType($value);

        if ($enumClass !== null && !in_array($scalar, $values, true)) {
            throw new \InvalidArgumentException(sprintf(
                'DBSmartEnum: value "%s" does not match any case on "%s".',
                (string) $value,
                $enumClass
            ));
        }

        return $scalar;
    }

    private function coerceScalarForBackingType(int|string $value): int|string
    {
        if ($this->backingType === 'int') {
            return is_int($value) ? $value : (int) $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function resolveStorage(array $options): string
    {
        if (isset($options['storage'])) {
            return $this->normaliseStorage((string) $options['storage']);
        }

        $default = Config::inst()->get(static::class, 'default_storage') ?? 'enum';

        return $this->normaliseStorage((string) $default);
    }

    /**
     * @param list<int|string> $backingValues
     * @param array<string, mixed> $options
     */
    private function resolveVarcharLength(array $backingValues, array $options): int
    {
        if (isset($options['varchar_length'])) {
            return $this->clampVarcharLength((int) $options['varchar_length']);
        }

        $maxLen = 0;
        foreach ($backingValues as $value) {
            $maxLen = max($maxLen, strlen((string) $value));
        }

        return $this->clampVarcharLength(max(50, $maxLen));
    }

    private function normaliseStorage(string $storage): string
    {
        $storage = strtolower($storage);

        if ($storage === 'varchar') {
            return 'scalar';
        }

        if (!in_array($storage, ['enum', 'scalar'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'DBSmartEnum: storage must be "enum", "scalar", or "varchar", "%s" given.',
                $storage
            ));
        }

        return $storage;
    }

    private function clampVarcharLength(int $length): int
    {
        return max(1, min(255, $length));
    }
}
