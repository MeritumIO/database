# meritum/database

Model and repository layer for the Meritum ecosystem. Provides an active-record style model with casting, accessors, and mutators, paired with a repository pattern for querying and persisting data.

## Requirements

- PHP 8.4+
- `georgeff/kernel` ^1.7
- `georgeff/database` ^1.1

## Installation

```bash
composer require meritum/database
```

## Setup

Register `DatabaseModule` with the kernel. It reads database credentials from environment variables.

```php
use Meritum\Database\DatabaseModule;

$kernel->register(new DatabaseModule());
```

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `DB_DRIVER` | — | `pgsql`, `mysql`, or `sqlite` |
| `DB_HOST` | — | Database host |
| `DB_PORT` | — | Database port |
| `DB_DATABASE` | — | Database name (or file path for SQLite) |
| `DB_USERNAME` | — | Database username |
| `DB_PASSWORD` | — | Database password |
| `DB_READ_HOSTS` | `[]` | JSON array of read replica hostnames |
| `DB_STICKY_WRITE` | `true` | Route reads after a write to the write host |
| `DB_PGSQL_SCHEMA` | `public` | PostgreSQL schema |
| `DB_PGSQL_SSL_MODE` | `prefer` | PostgreSQL SSL mode |
| `DB_MYSQL_CHARSET` | `utf8mb4` | MySQL charset |

`DB_READ_HOSTS` accepts a JSON array:

```
DB_READ_HOSTS=["replica1.example.com","replica2.example.com"]
```

---

## Models

Define a model by extending `Model` and setting the `$table` property.

```php
use Meritum\Database\Model;

class User extends Model
{
    protected string $table = 'users';
}
```

### Primary Keys

By default the primary key column is `id` with type `string`, and a UUIDv4 is auto-generated on insert. For auto-incrementing integer keys:

```php
class Order extends Model
{
    protected string $table = 'orders';
    protected string $primaryKey = 'order_id';
    protected string $primaryKeyType = 'int';
    protected bool $incrementing = true;
}
```

### Timestamps

Timestamps are enabled by default. The model automatically sets `created_at` on insert and `updated_at` on every save. To disable:

```php
class EventLog extends Model
{
    protected string $table = 'event_logs';
    protected bool $timestamps = false;
}
```

To use different column names:

```php
protected string $createdAtColumn = 'inserted_at';
protected string $updatedAtColumn = 'modified_at';
```

### Casting

Define casts to automatically convert database values to PHP types on read, and back on write.

```php
class Post extends Model
{
    protected string $table = 'posts';

    protected array $casts = [
        'published_at' => 'datetime',
        'view_count'   => 'int',
        'is_featured'  => 'bool',
        'metadata'     => 'json',
    ];
}
```

Supported cast types:

| Type | PHP Type | Notes |
|---|---|---|
| `int` | `int` | |
| `float` | `float` | |
| `string` | `string` | |
| `bool` | `bool` | |
| `json` | `array` | Encoded as JSON string in the database |
| `datetime` | `DateTimeImmutable` | Stored as `Y-m-d H:i:s` |
| `date` | `DateTimeImmutable` | Stored as `Y-m-d`, time zeroed |
| `timestamp` | `DateTimeImmutable` | Stored as Unix timestamp |

### Accessors and Mutators

Use `accessors()` to transform attribute values on read, and `mutators()` to transform them on write.

```php
class User extends Model
{
    protected string $table = 'users';

    protected function accessors(): array
    {
        return [
            'email' => fn(mixed $v): string => strtolower((string) $v),
        ];
    }

    protected function mutators(): array
    {
        return [
            'name' => fn(mixed $v): string => trim((string) $v),
        ];
    }
}
```

### Serialization

Models implement `JsonSerializable`. `DateTime` values are formatted as ISO 8601.

```php
json_encode($user); // {"id":"...","name":"Alice","created_at":"2024-01-15T12:00:00+00:00"}
```

Models also support PHP native serialization via `__serialize()`/`__unserialize()`, making them compatible with any PSR-6/PSR-16 cache that uses `serialize()` internally (Redis, APCu, etc.). Attributes and relations are preserved; the unserialized model has no dirty state. Callers are responsible for ensuring any relations stored on the model are themselves serializable.

```php
$user = $repo->findOrFail($id);
$cache->set("user:{$id}", serialize($user));

$user = unserialize($cache->get("user:{$id}"));
```

`Collection` supports the same native serialization and preserves model keys.

### Relations

Models carry a protected relation bag — a simple key/value store for attaching related data after it has been loaded. There is no lazy loading or query triggering; the caller fetches related data independently and attaches it to the model.

Expose relations through typed public wrapper methods on the concrete model:

```php
class EventLog extends Model
{
    protected string $table = 'event_logs';

    public function setEvent(Event $event): void
    {
        $this->setRelation('event', $event);
    }

    public function getEvent(): Event
    {
        /** @var Event */
        return $this->getRelation('event');
    }

    public function hasEvent(): bool
    {
        return $this->hasRelation('event');
    }
}
```

Attach the relation after loading both models:

```php
$log   = $logRepository->findOrFail($id);
$event = $eventRepository->findOrFail($log->eventId);

$log->setEvent($event);
```

Relations are merged into `toArray()` automatically. The key passed to `setRelation()` becomes the key in the serialized output:

```json
{
    "id": "...",
    "event_id": "...",
    "event": { "id": "...", "name": "PHP Conference" }
}
```

Relations can hold any JSON-safe value — a model, a collection, a paginator, an array, or a scalar. Objects must implement `JsonSerializable`; `setRelation()` throws a `LogicException` otherwise. The typed wrapper method on the concrete model is the actual type contract — the base class bag is deliberately untyped so it does not constrain what sources or shapes of data can be attached.

---

## Repositories

Define a repository by extending `Repository` and implementing `getModelClass()`.

```php
use Meritum\Database\Repository;
use Georgeff\Database\Contract\DatabaseManagerInterface;

class UserRepository extends Repository
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        $this->query()->where('email', $email);

        return $this->first();
    }

    public function allActive(): Collection
    {
        $this->query()->where('status', 'active');

        return $this->get();
    }
}
```

Inject via the container:

```php
use Georgeff\Database\Contract\DatabaseManagerInterface;

$repo = new UserRepository($container->get(DatabaseManagerInterface::class));
```

### Saving and Deleting

```php
// Insert
$user = new User(['name' => 'Alice', 'email' => 'alice@example.com']);
$repo->save($user); // returns true on success

// Update
$user = $repo->find('some-uuid');
$user->set('name', 'Alicia');
$repo->save($user);

// Delete
$repo->delete($user);
```

`save()` returns `false` without hitting the database if the model has no dirty attributes.

### Finding Records

```php
// By primary key — returns null if not found
$user = $repo->find('some-uuid');

// By primary key — throws ModelNotFoundException if not found
$user = $repo->findOrFail('some-uuid');

// By any column
$user = $repo->findBy('email', 'alice@example.com');
```

### Building Queries

Terminal methods (`first`, `firstOrFail`, `get`, `count`, `paginate`, `cursor`) are `protected` and intended to be called from within a repository method after calling `query()`.

```php
protected function activeCount(): int
{
    $this->query()->where('status', 'active');

    return $this->count();
}

public function findBySlug(string $slug): Post
{
    $this->query()->where('slug', $slug);

    return $this->firstOrFail();
}
```

`firstOrFail()` throws `ModelNotFoundException` when the query returns no result, equivalent to `findOrFail()` but for custom where queries built with `query()`.

### Offset Pagination

```php
public function paginate(int $perPage, int $page): Paginator
{
    $this->query();

    return $this->paginate($perPage, $page);
}
```

The returned `Paginator` provides:

```php
$paginator->collection();   // Collection<T>
$paginator->total();        // int — total matching records
$paginator->perPage();      // int
$paginator->currentPage();  // int
$paginator->lastPage();     // int
$paginator->from();         // int — 1-based first record index
$paginator->to();           // int — 1-based last record index
$paginator->hasMorePages(); // bool
```

Serializes to:

```json
{
    "data": [...],
    "total": 100,
    "perPage": 15,
    "currentPage": 2,
    "lastPage": 7,
    "from": 16,
    "to": 30
}
```

### Cursor Pagination

Cursor pagination is efficient for large datasets. It uses an opaque token to track position rather than an offset.

```php
public function browse(int $perPage, ?string $cursor = null): CursorPaginator
{
    $this->query();

    return $this->cursor($perPage, $cursor);
}
```

The returned `CursorPaginator` provides:

```php
$paginator->collection();      // Collection<T>
$paginator->perPage();         // int
$paginator->nextCursor();      // ?string — pass to next forward request
$paginator->previousCursor();  // ?string — pass to go back
$paginator->hasMorePages();    // bool
$paginator->hasPreviousPages(); // bool
```

Serializes to:

```json
{
    "data": [...],
    "perPage": 15,
    "nextCursor": "eyJ2IjoxL...",
    "previousCursor": null
}
```

Cursors are URL-safe base64 tokens. Pass `nextCursor` or `previousCursor` back as the `cursor` parameter to navigate forward or backward. Direction is encoded in the token — no separate parameter is needed.

> **Limitations**
>
> `cursor()` sorts and paginates by the model's primary key. It appends its own `ORDER BY` to the query rather than replacing any existing ordering — any prior `orderBy()` call (including those applied by scopes) will conflict and produce incorrect results. If a scope applies an ordering, call `resetOrderBy()` on the query before invoking `cursor()`.
>
> UUIDv4 primary keys are randomly generated and have no natural ordering, making them unsuitable as a cursor column. Override `generateUuid()` to return `Uuid::v7()` on any model used with cursor pagination — UUIDv7 is time-ordered and sorts correctly.

### Scopes

Scopes are invariant filters registered at construction time via injected dependencies. They are applied automatically to every query built with `query()`.

```php
class PostRepository extends Repository
{
    public function __construct(
        DatabaseManagerInterface $db,
        private readonly Tenant $tenant,
    ) {
        parent::__construct($db);

        $this->addScope('tenant', function (SelectInterface $query): void {
            $query->where('tenant_id', $this->tenant->id());
        });
    }

    protected function getModelClass(): string
    {
        return Post::class;
    }
}
```

To bypass a scope for a single query:

```php
// Disable one scope
$this->withoutScope('tenant')->query()->where('status', 'published');

// Disable all scopes
$this->withoutScopes()->query();
```

Scope bypasses apply only to the next query and reset automatically.

### Generating UUIDs

`Meritum\Database\Support\Uuid` provides two UUID generators:

| Method | Version | Ordering |
|---|---|---|
| `Uuid::v4()` | UUIDv4 | Random — no natural ordering |
| `Uuid::v7()` | UUIDv7 | Time-ordered — millisecond timestamp in the first 48 bits |

By default, string primary keys are auto-generated as UUIDv4. Override `generateUuid()` to change this:

```php
use Meritum\Database\Support\Uuid;

protected function generateUuid(): string
{
    return Uuid::v7();
}
```

---

## Collections

`Collection` is an immutable, keyed collection of models. Models are keyed by their primary key value.

```php
$collection->count();           // int
$collection->isEmpty();         // bool
$collection->isNotEmpty();      // bool
$collection->has('some-id');    // bool
$collection->get('some-id');    // ?Model
$collection->first();           // ?Model
$collection->last();            // ?Model
$collection->all();             // array<int|string, Model>
$collection->keys();            // array<int|string>
$collection->filter($callback); // new Collection
$collection->each($callback);   // same Collection (for side effects)
$collection->push($model);      // new Collection
$collection->merge($other);     // new Collection — existing keys win on conflict
```

---

## Exception Handling

`ModelNotFoundException` is thrown by `findOrFail()` and `firstOrFail()`. The message is formatted as `"{ModelName} was not found"`. Map it to a 404 in your HTTP exception handler:

```php
use Meritum\Database\Exception\ModelNotFoundException;

// In your exception handler:
if ($e instanceof ModelNotFoundException) {
    return $response->withStatus(404);
}
```
