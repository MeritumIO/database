# Changelog

All notable changes to this project will be documented in this file.

## [1.3.0] - 2026-06-23

### Added

- `Repository::firstOrFail()` — protected terminal method that throws `ModelNotFoundException` when a custom `query()->where(...)` chain returns no result. Companion to `findOrFail()` for repository methods that build their own queries.
- `Model::__serialize()`/`__unserialize()` — PHP native serialization support. Persists attributes, relations, and exists state; reconstructs original from attributes on unserialize so the model has no dirty state. Compatible with any cache backend that uses `serialize()` (PSR-6, PSR-16, Redis, APCu).
- `Collection::__serialize()`/`__unserialize()` — PHP native serialization support. Preserves model keys and delegates to each model's own serialization.

### Changed

- `ModelNotFoundException` message format changed from `"Record with primary key value of [{$pk}] not found"` to `"{ModelName} was not found"` — shorter and consistent between `findOrFail()` and `firstOrFail()`.

## [1.2.1] - 2026-06-21

### Fixed

- `Uuid::v7()` generated a non-standard 144-bit value due to using `%012x` for the timestamp field. The 48-bit timestamp is now correctly split across the first two UUID groups (`%08x` high 32 bits + `%04x` low 16 bits), producing a valid 128-bit `8-4-4-4-12` UUID string.

## [1.2.0] - 2026-06-21

### Added

- `Uuid::v7()` — time-ordered UUIDv7 generator. Encodes a millisecond Unix timestamp in the first 48 bits, making generated values sortable and suitable for use as cursor pagination keys.

### Changed

- Bumped `georgeff/database` requirement to `^1.1` to bring in `SelectInterface::resetOrderBy()`.

### Documentation

- Cursor pagination limitations documented in README and docblock: `cursor()` appends its own `ORDER BY` and does not clear prior ordering; any `orderBy()` call in a scope or query chain will conflict and produce incorrect results. Call `resetOrderBy()` before invoking `cursor()` if needed.
- Noted that UUIDv4 primary keys are unsuitable for cursor pagination due to random ordering; override `generateUuid()` to return `Uuid::v7()` on models used with `cursor()`.

## [1.1.0] - 2026-06-21

### Added

- `Model::setRelation()`, `getRelation()`, `hasRelation()` — protected relation bag for attaching loaded related data to a model without lazy loading or query triggering.
- `Model::toArray()` now merges loaded relations into the output. Objects implementing `JsonSerializable` have `jsonSerialize()` called; scalars and arrays are included as-is.
- `setRelation()` throws `LogicException` if the value is `null` or a non-`JsonSerializable` object, keeping the bag JSON-safe by construction.

## [1.0.0] - 2026-06-16

### Added

- `Model` — abstract base class with PHP 8.4 property hook support, attribute casting (`int`, `float`, `string`, `bool`, `json`, `datetime`, `date`, `timestamp`), accessors, mutators, dirty tracking, timestamp management, and `JsonSerializable` serialization.
- `Repository` — abstract base class providing `find()`, `findOrFail()`, `findBy()`, `save()`, `delete()`, `count()`, `first()`, `get()`, `paginate()`, and `cursor()`. Query building via `query()` with `addScope()` / `withoutScope()` / `withoutScopes()`.
- `RepositoryInterface` — contract for type-hinting repositories.
- `Collection` — immutable, primary-key-keyed model collection with `filter()`, `each()`, `push()`, `merge()`, and standard accessors.
- `Paginator` — offset-based pagination result with total, page, and window metadata.
- `CursorPaginator` — cursor-based pagination using opaque URL-safe base64 tokens; supports forward and backward navigation.
- `Cursor` — value object encapsulating cursor state.
- `Uuid` — UUIDv4 generation helper.
- `DatabaseModule` — kernel module wiring `DatabaseManagerInterface` and `ConnectionManagerInterface` from environment variables (`DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_READ_HOSTS`, `DB_STICKY_WRITE`, `DB_PGSQL_SCHEMA`, `DB_PGSQL_SSL_MODE`, `DB_MYSQL_CHARSET`).
- `ModelNotFoundException` — thrown by `findOrFail()` when no record is found.
