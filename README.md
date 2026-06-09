# Silverstripe SmartEnum DBField

Map a PHP 8.1+ `BackedEnum` to a Silverstripe `DataObject` database column. Values are derived from enum cases automatically. Physical storage can be a MySQL `ENUM` (default) or a `VARCHAR` when you need to avoid costly `ENUM` alters on large tables.

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

Double-escape backslashes in the enum class name inside the field specification string:

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

You can also reference the field class directly in PHP if you build the `$db` array in code:

```php
use ArchiPro\Silverstripe\SmartEnum\DBSmartEnum;

'Status' => DBSmartEnum::class . '("My\\\\Namespace\\\\Status", "PENDING")',
```

## Default value

Omit the second argument when the column should have no default. New records start with an empty value until the field is set:

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status")',
```

Pass an explicit default as the second argument. Use the **backing scalar** of the enum case that represents the initial business state (for example the status a new record should start in):

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status", "PENDING")',
```

You can also pass `null` explicitly; that is equivalent to omitting the default:

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status", null)',
```

When building the `$db` array in PHP (not a string field spec), you may pass a `BackedEnum` case instead of the scalar.

The default must match a case on the enum. Invalid scalars and enum cases from another type are rejected at field construction time.

Unlike core `DBEnum`, integer defaults are **not** treated as list indices. Pass the actual backing value (or an enum case), not a positional index.

## MySQL ENUM vs VARCHAR

| Storage | When to use |
|--------|-------------|
| `enum` (default) | Smaller schema; values enforced by MySQL; fine for smaller tables or early-stage models. |
| `varchar` | Large tables where altering an `ENUM` is slow or risky; values still defined by the PHP enum in application code. |

Per-field override in the field spec options (4th argument):

```php
'Status' => 'SmartEnum("My\\\\Namespace\\\\Status", "PENDING", ["storage" => "varchar", "varchar_length" => 64])',
```

`varchar_length` is optional; when omitted, the length is `max(50, longest backing value length)` capped at 255.

### YAML / site-wide default

Per-field storage must be set in the field spec options (4th argument) as shown above. To change the default for all SmartEnum fields that do not pass `storage` in their options, use static config:

```yaml
---
Name: my-smartenum-storage
---
ArchiPro\Silverstripe\SmartEnum\DBSmartEnum:
  default_storage: enum   # or varchar
```

Previously documented `enum_storage` (per PHP enum class) is removed; move those overrides into each `$db` field spec.

## Typed accessors (optional)

`SmartEnumDataExtension` is applied to all `DataObject` records. For each `DBSmartEnum` column `Status` it provides:

- `getStatus(): ?BackedEnum` — `tryFrom()` on the stored scalar; `null` when empty or unknown.
- `setStatus(BackedEnum|string|int|null $value)` — accepts an enum instance or a backing scalar.

If the model already defines `getStatus()` / `setStatus()`, those methods take precedence over the extension.

Writes via `$record->Status = 'PENDING'` still store the scalar; the extension is for typed ergonomics.

## CMS forms

No extra configuration is required. `scaffoldFormField()` returns a `DropdownField` listing all backing values, for both `enum` and `varchar` storage modes.

## Migrating ENUM → VARCHAR on production

Changing storage on a live, large table is an operational task. `dev/build` may issue `ALTER TABLE` statements that lock or rebuild the table for a long time. Plan a manual migration and maintenance window; do not rely on a casual `dev/build` on production for storage flips.

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
