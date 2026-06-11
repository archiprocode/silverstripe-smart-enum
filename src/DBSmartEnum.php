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
     * Global default for database-native ENUM vs scalar column when not overridden per field.
     *
     * @config
     */
    private static bool $default_use_native_db_enum = true;

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
     * When true, persist as a database-native ENUM; when false, use VARCHAR (string-backed) or INT (int-backed).
     */
    protected bool $useNativeDbEnum = true;

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
     * @param array<string, mixed> $options Optional field options; `use_native_db_enum` (bool) and
     *                                      `varchar_length` (int) are consumed by this class.
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

            if (!BackedEnumDetection::isBackedEnumClass($enumClass)) {
                throw new \RuntimeException(sprintf(
                    'DBSmartEnum: enum class "%s" is not a BackedEnum. '
                    . 'DBEnum requires scalar values to persist, so only BackedEnums are supported.',
                    $enumClass
                ));
            }

            $enumReflection = new \ReflectionEnum($enumClass);
            $backingType = $enumReflection->getBackingType();
            $this->backingType = $backingType->getName();

            $values = array_map(
                fn (\ReflectionEnumBackedCase $case) => $case->getBackingValue(),
                $enumReflection->getCases()
            );
        }

        $this->useNativeDbEnum = $this->resolveUseNativeDbEnum($options);

        if (!$this->useNativeDbEnum && $this->backingType === 'string') {
            $this->varcharLength = $this->resolveVarcharLength($values ?? [], $options);
        }

        $parentOptions = $options;
        unset($parentOptions['use_native_db_enum'], $parentOptions['varchar_length']);

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
     * Coerce stringified ints from database-native ENUM columns; scalar INT columns return ints already.
     *
     * @return mixed
     */
    public function getValue()
    {
        $value = parent::getValue();

        if (
            $this->useNativeDbEnum
            && $this->backingType === 'int'
            && is_string($value)
            && is_numeric($value)
        ) {
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
        match ($this->getColumnType()) {
            'enum' => parent::requireField(),
            'int' => $this->requireIntField(),
            'varchar' => $this->requireVarcharField(),
            default => throw new \LogicException(
                'DBSmartEnum: unexpected column type "' . $this->getColumnType() . '".'
            ),
        };
    }

    /**
     * Physical database column type: `enum`, `varchar`, or `int`.
     */
    private function getColumnType(): string
    {
        if ($this->useNativeDbEnum) {
            return 'enum';
        }

        return $this->backingType === 'int' ? 'int' : 'varchar';
    }

    /**
     * @return void
     */
    private function requireIntField(): void
    {
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
    }

    /**
     * @return void
     */
    private function requireVarcharField(): void
    {
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
    private function resolveUseNativeDbEnum(array $options): bool
    {
        if (array_key_exists('use_native_db_enum', $options)) {
            $value = $options['use_native_db_enum'];
            if (!is_bool($value)) {
                throw new \InvalidArgumentException(
                    'DBSmartEnum: use_native_db_enum must be a boolean.'
                );
            }

            return $value;
        }

        $default = Config::inst()->get(static::class, 'default_use_native_db_enum') ?? true;
        if (!is_bool($default)) {
            throw new \InvalidArgumentException(
                'DBSmartEnum: default_use_native_db_enum config must be a boolean.'
            );
        }

        return $default;
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

    private function clampVarcharLength(int $length): int
    {
        return max(1, min(255, $length));
    }
}
