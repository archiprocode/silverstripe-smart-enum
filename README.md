# Silverstripe SmartEnum DBField

Map a PHP 8.1+ `BackedEnum` to a Silverstripe `DataObject` database column. Values are derived from enum cases automatically. By default the column uses a database-native `ENUM` type (MySQL today); set `use_native_db_enum` to `false` to use a scalar column (`VARCHAR` for string-backed enums, `INT` for int-backed enums) when you need to avoid costly `ENUM` alters on large tables. This option refers to the database column type, not PHP `BackedEnum`.

CMS ModelAdmin and `DataObject` edit forms scaffold a `DropdownField` with the enum’s backing values (inherited from Silverstripe’s `DBEnum`).

## Installation

```bash
composer require archipro/silverstripe-smart-enum
```

### Monorepo / path repository

```json
"repositories": [
  {
    "type": "path",
    "url": "packages/silverstripe-smart-enum",
    "options": { "symlink": true }
  }
],
"require": {
  "archipro/silverstripe-smart-enum": "@dev"
}
```

Run `composer update archipro/silverstripe-smart-enum` and `sake dev/build` (or your usual schema build).

The module registers a `SmartEnum` Injector alias for use in `$db` field specs.

## Define a SmartEnum on a DataObject

Use the `SmartEnum` Injector alias in your `$db` array. Double-escape backslashes in the enum class name inside the field specification string:

```php
use SilverStripe\ORM\DataObject;

class MyRecord extends DataObject
{
    private static $table_name = 'MyRecord';

    private static $db = [
        'Status' => 'SmartEnum("My\\\\Namespace\\\\Status", "PENDING")',
    ];
}
```

Int-backed enums work the same way; pass the int backing scalar as the default:

```php
private static $db = [
    'Priority' => 'SmartEnum("My\\\\Namespace\\\\Priority", 1)',
];
```

## Default value

Omit the second argument when the column should have no default. New records start with an empty value until the field is set:

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status")',
```

Pass an explicit default as the second argument. Use the **backing scalar** of the enum case that represents the initial business state (for example the status a new record should start in):

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status", "PENDING")',
'Priority' => 'SmartEnum("My\\\\Namespace\\\\Priority", 1)',
```

You can also pass `null` explicitly; that is equivalent to omitting the default:

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status", null)',
```

When building the `$db` array in PHP (not a string field spec), you may pass a `BackedEnum` case instead of the scalar.

The default must match a case on the enum. Invalid scalars and enum cases from another type are rejected at field construction time.

Unlike core `DBEnum`, integer defaults are **not** treated as list indices. Pass the actual backing value (or an enum case), not a positional index.

## MySQL ENUM vs scalar columns

| `use_native_db_enum` | String-backed enum | Int-backed enum |
|----------------------|-------------------|-----------------|
| `true` (default) | MySQL `ENUM` | MySQL `ENUM` (values stored as quoted ints; coerced on read) |
| `false` | `VARCHAR` | `INT` |

Use `use_native_db_enum: true` for smaller schemas with values enforced by the database. Set `use_native_db_enum` to `false` on large tables where altering an `ENUM` is slow or risky, or when you want a native `INT` column for int-backed enums.

Per-field override in the field spec options (4th argument):

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status", "PENDING", ["use_native_db_enum" => false, "varchar_length" => 64])',
'Priority' => 'SmartEnum("My\\\\Namespace\\\\Priority", 1, ["use_native_db_enum" => false])',
```

For string-backed enums with `use_native_db_enum: false`, `varchar_length` is optional; when omitted, the length is `max(50, longest backing value length)` capped at 255.

Int-backed enums with `use_native_db_enum: true` return stringified values from MySQL. `DBSmartEnum` coerces numeric strings back to `int` at the field boundary (for example when reading via `dbObject()` or typed accessors).

### YAML / site-wide default

Per-field `use_native_db_enum` must be set in the field spec options (4th argument) as shown above. To change the default for all SmartEnum fields that omit it, use static config:

```yaml
---
Name: my-smartenum-storage
---
ArchiPro\Silverstripe\SmartEnum\DBSmartEnum:
  default_use_native_db_enum: true   # or false
```

## Typed accessors (optional)

`SmartEnumDataExtension` is **not** applied globally. Add it to each `DataObject` that uses SmartEnum columns.

YAML:

```yaml
---
Name: myrecord-smartenum
---
MyRecord:
  extensions:
    - ArchiPro\Silverstripe\SmartEnum\SmartEnumDataExtension
```

Or in PHP:

```php
private static array $extensions = [
    ArchiPro\Silverstripe\SmartEnum\SmartEnumDataExtension::class,
];
```

For each `DBSmartEnum` column `Status` the extension provides:

- `getStatus(): ?BackedEnum` — `tryFrom()` on the stored scalar; `null` when empty or unknown.
- `setStatus(BackedEnum|string|int|null $value)` — accepts an enum instance or a valid backing scalar.

If the model already defines `getStatus()` / `setStatus()`, those methods take precedence over the extension.

### Property access and PHPDoc

The primary use case is property access on the `DataObject`. When the extension is applied, `$record->Status` resolves via `getStatus()` / `setStatus()` and returns a `BackedEnum` instance (or `null`). Property assignment validates the value; invalid backing scalars throw `InvalidArgumentException`.

Declare the typed property on your model for IDE and static analysis support:

```php
use My\Namespace\Status;

/**
 * @property Status|null $Status
 */
class MyRecord extends DataObject
{
    // ...
}
```

`getField('Status')` still returns the raw backing scalar stored in the database record, not the enum instance.

## CMS forms

No extra configuration is required. `scaffoldFormField()` returns a `DropdownField` listing all backing values, whether or not `use_native_db_enum` is enabled.

## Migrating database ENUM → scalar columns on production

Flipping `use_native_db_enum` from `true` to `false` on a live, large table is an operational task. `dev/build` may issue `ALTER TABLE` statements that lock or rebuild the table for a long time. Plan a manual migration and maintenance window; do not rely on a casual `dev/build` on production for column-type changes.

## Running tests

```bash
composer install
composer test
composer phpstan
composer lint      # PHPCS (PSR-12)
composer check     # phpstan, lint, and test in sequence
```

`composer test` requires a reachable MySQL-compatible database. Configure the connection via a project `.env` file or `SS_DATABASE_*` environment variables (for example `SS_DATABASE_SERVER`, `SS_DATABASE_USERNAME`, `SS_DATABASE_PASSWORD`, and optionally `SS_DATABASE_CHOOSE_NAME=true`). The suite fails if the database is missing or unreachable. GitHub Actions sets these automatically via [silverstripe/gha-ci](https://github.com/silverstripe/gha-ci).

## License

BSD-3-Clause
