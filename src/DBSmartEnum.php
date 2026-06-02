<?php

namespace ArchiPro\Silverstripe\SmartEnum;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\Connect\MySQLDatabase;
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
     * Global default physical storage when not overridden per field or per enum class.
     *
     * @config
     */
    private static string $default_storage = 'enum';

    /**
     * Per-PHP-enum-class storage overrides. Values may be `enum`, `varchar`, or a map with
     * `storage` and optional `varchar_length` keys.
     *
     * @config
     *
     * @var array<string, string|array{storage?: string, varchar_length?: int}>
     */
    private static array $enum_storage = [];

    /**
     * Fully-qualified BackedEnum class name, when known.
     */
    protected ?string $enumClass = null;

    /**
     * Physical column storage: `enum` (MySQL ENUM) or `varchar`.
     */
    protected string $storage = 'enum';

    /**
     * VARCHAR column length when {@see $storage} is `varchar`.
     */
    protected int $varcharLength = 255;

    /**
     * @param string|null $name      Field name passed through to {@see DBEnum::__construct}
     * @param string|null $enumClass Fully-qualified PHP enum class name. May be null because Silverstripe
     *                               instantiates DBField subclasses with null arguments while inspecting types
     *                               (see {@see \SilverStripe\ORM\DataObject::dbObject()}); we must tolerate that
     *                               and pass through to the parent unchanged.
     * @param int|string|null $default
     * @param array<string, mixed> $options Optional field options; `storage` (`enum`|`varchar`) and
     *                                      `varchar_length` (int) are consumed by this class.
     *
     * @throws \RuntimeException When $enumClass is provided but cannot be resolved (e.g. autoload race in a
     *                           paratest worker fork) or is not a BackedEnum. Without this guard we would
     *                           silently produce a DBEnum with no values, the schema manager would drop the
     *                           column, but the matching $indexes entry would survive, generating a
     *                           `Key column doesn't exist` failure later in CI.
     */
    public function __construct($name = null, $enumClass = null, $default = 0, $options = [])
    {
        $values = null;
        $this->enumClass = $enumClass ? (string) $enumClass : null;

        if ($enumClass) {
            if (!enum_exists($enumClass)) {
                throw new \RuntimeException(sprintf(
                    'DBSmartEnum: enum class "%s" could not be resolved. This usually indicates an autoload race in a forked test worker. Failing fast here so the schema build does not silently drop the column while keeping its index.',
                    $enumClass
                ));
            }

            $enumReflection = new \ReflectionEnum($enumClass);

            if (!$enumReflection->isBacked()) {
                throw new \RuntimeException(sprintf(
                    'DBSmartEnum: enum class "%s" is not a BackedEnum. DBEnum requires scalar values to persist, so only BackedEnums are supported.',
                    $enumClass
                ));
            }

            $values = array_map(
                fn (\ReflectionEnumBackedCase $case) => $case->getBackingValue(),
                $enumReflection->getCases()
            );
        }

        $this->storage = $this->resolveStorage($this->enumClass, $options);
        $this->varcharLength = $this->resolveVarcharLength($values ?? [], $options, $this->enumClass);

        $parentOptions = $options;
        unset($parentOptions['storage'], $parentOptions['varchar_length']);

        parent::__construct($name, $values, $default, $parentOptions);
    }

    /**
     * BackedEnum class backing this field, or null when not configured.
     */
    public function getEnumClass(): ?string
    {
        return $this->enumClass;
    }

    /**
     * Physical storage mode for this field instance: `enum` or `varchar`.
     */
    public function getStorage(): string
    {
        return $this->storage;
    }

    /**
     * VARCHAR length when storage is `varchar`.
     */
    public function getVarcharLength(): int
    {
        return $this->varcharLength;
    }

    /**
     * @return void
     */
    public function requireField()
    {
        if ($this->storage !== 'varchar') {
            parent::requireField();
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
     * @param array<string, mixed> $options
     */
    private function resolveStorage(?string $enumClass, array $options): string
    {
        if (isset($options['storage'])) {
            return $this->normaliseStorage((string) $options['storage']);
        }

        if ($enumClass) {
            $enumStorage = $this->getConfigValue('enum_storage', []);
            if (isset($enumStorage[$enumClass])) {
                $entry = $enumStorage[$enumClass];
                if (is_array($entry) && isset($entry['storage'])) {
                    return $this->normaliseStorage((string) $entry['storage']);
                }
                if (is_string($entry)) {
                    return $this->normaliseStorage($entry);
                }
            }
        }

        $default = $this->getConfigValue('default_storage', static::$default_storage);

        return $this->normaliseStorage((string) $default);
    }

    /**
     * @param list<int|string>|null $backingValues
     * @param array<string, mixed> $options
     */
    private function resolveVarcharLength(?array $backingValues, array $options, ?string $enumClass): int
    {
        if (isset($options['varchar_length'])) {
            return $this->clampVarcharLength((int) $options['varchar_length']);
        }

        if ($enumClass) {
            $enumStorage = $this->getConfigValue('enum_storage', []);
            if (isset($enumStorage[$enumClass]) && is_array($enumStorage[$enumClass])) {
                $length = $enumStorage[$enumClass]['varchar_length'] ?? null;
                if ($length !== null) {
                    return $this->clampVarcharLength((int) $length);
                }
            }
        }

        $maxLen = 0;
        foreach ($backingValues ?? [] as $value) {
            $maxLen = max($maxLen, strlen((string) $value));
        }

        return $this->clampVarcharLength(max(50, $maxLen));
    }

    private function normaliseStorage(string $storage): string
    {
        $storage = strtolower($storage);
        if (!in_array($storage, ['enum', 'varchar'], true)) {
            throw new \InvalidArgumentException(sprintf(
                'DBSmartEnum: storage must be "enum" or "varchar", "%s" given.',
                $storage
            ));
        }

        return $storage;
    }

    private function clampVarcharLength(int $length): int
    {
        return max(1, min(255, $length));
    }

    /**
     * Read configurable defaults when Silverstripe config manifests are available (e.g. skipped in bare PHPUnit).
     */
    private function getConfigValue(string $name, mixed $fallback): mixed
    {
        try {
            return Config::inst()->get(static::class, $name) ?? $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
