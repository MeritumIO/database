# Changelog

All notable changes to this project will be documented in this file.

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
- `Uuid` — UUIDv4 and UUIDv7 generation helper.
- `DatabaseModule` — kernel module wiring `DatabaseManagerInterface` and `ConnectionManagerInterface` from environment variables (`DB_DRIVER`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, `DB_READ_HOSTS`, `DB_STICKY_WRITE`, `DB_PGSQL_SCHEMA`, `DB_PGSQL_SSL_MODE`, `DB_MYSQL_CHARSET`).
- `ModelNotFoundException` — thrown by `findOrFail()` when no record is found.
