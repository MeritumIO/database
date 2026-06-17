<?php

namespace Meritum\Database;

use RuntimeException;
use JsonSerializable;
use DateTimeInterface;
use DateTimeImmutable;
use InvalidArgumentException;

abstract class Model implements JsonSerializable
{
    /**
     * @var string[]
     */
    private const SUPPORTED_CASTS = [
        'int',
        'float',
        'string',
        'bool',
        'json',
        'datetime',
        'date',
        'timestamp',
    ];

    /**
     * The database table associated with the model
     */
    protected string $table = '';

    /**
     * The primary key column name
     */
    protected string $primaryKey = 'id';

    /**
     * The primary key column type (string or int)
     */
    protected string $primaryKeyType = 'string';

    /**
     * Indicates if the primary key is auto-incrementing
     */
    protected bool $incrementing = false;

    /**
     * Indicates if timestamps are auto managed
     */
    protected bool $timestamps = true;

    /**
     * Created at column name
     */
    protected string $createdAtColumn = 'created_at';

    /**
     * Updated at column name
     */
    protected string $updatedAtColumn = 'updated_at';

    /**
     * @var array<string, string>
     */
    protected array $casts = [];

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @var array<string, mixed>
     */
    private array $original = [];

    /**
     * @var array<string, mixed>
     */
    private array $attributeCache = [];

    /**
     * @var array<string, callable(mixed $value): mixed>|null
     */
    private ?array $accessorCache = null;

    /**
     * @var array<string, callable(mixed $value): mixed>|null
     */
    private ?array $mutatorCache = null;

    /**
     * Indicates if the record has been persisted to the database
     */
    private bool $exists = false;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->hydrate($attributes);
    }

    /**
     * Hydrate the model
     *
     * @param array<string, mixed> $data
     *
     * @throws \RuntimeException
     */
    public function hydrate(array $data): static
    {
        if ([] !== $this->attributes) {
            throw new RuntimeException('Cannot hydrate a model that already has attributes.');
        }

        foreach ($data as $name => $value) {
            $this->attributes[$name] = $this->uncastAttribute($name, $value);
        }

        $this->attributeCache = [];

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach (array_keys($this->attributes) as $name) {
            $casted = $this->getAttribute($name);

            // Format DateTime so json_encode safe
            if ($casted instanceof DateTimeInterface) {
                $data[$name] = $casted->format('c'); // ISO 8601
            } else {
                $data[$name] = $casted;
            }
        }

        return $data;
    }

    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    /**
     * Get an attribute with casting applied (used by property hooks)
     *
     * @throws \InvalidArgumentException If the cast type is not supported
     */
    protected function getAttribute(string $name): mixed
    {
        if (array_key_exists($name, $this->attributeCache)) {
            return $this->attributeCache[$name];
        }

        $attribute = $this->attributes[$name] ?? null;

        if (null === $attribute) {
            return null;
        }

        $attribute = $this->castAttribute($name, $attribute);

        $attribute = $this->applyAccessor($name, $attribute);

        return $this->attributeCache[$name] = $attribute;
    }

    /**
     * Set an attribute (used by property hooks)
     *
     * @throws \RuntimeException If attempting to change immutable primary key
     */
    protected function setAttribute(string $name, mixed $value): void
    {
        if ($this->primaryKey === $name) {
            $currentValue = $this->attributes[$name] ?? null;

            if (null !== $currentValue && $value !== $currentValue) {
                throw new RuntimeException(sprintf(
                    'Cannot modify primary key [%s] once set.  Current: %s, Attempted: %s',
                    $name,
                    (string) $currentValue, // @phpstan-ignore cast.string
                    (string) $value // @phpstan-ignore cast.string
                ));
            }
        }

        if (!array_key_exists($name, $this->original) && array_key_exists($name, $this->attributes)) {
            $this->original[$name] = $this->attributes[$name];
        }

        $value = $this->applyMutator($name, $value);

        $this->attributes[$name] = $this->uncastAttribute($name, $value);

        unset($this->attributeCache[$name]);
    }

    /**
     * Cast attribute value from database to PHP type
     *
     * @throws \InvalidArgumentException Invalid cast type
     */
    private function castAttribute(string $name, mixed $value): mixed
    {
        if (!isset($this->casts[$name]) || null === $value) {
            return $value;
        }

        $type = $this->casts[$name];

        $this->validateCastType($type);

        return match ($type) {
            'int'       => (int) $value, // @phpstan-ignore cast.int
            'float'     => (float) $value, // @phpstan-ignore cast.double
            'string'    => (string) $value, // @phpstan-ignore cast.string
            'bool'      => (bool) $value,
            'json'      => is_string($value) ? json_decode($value, true, flags: JSON_THROW_ON_ERROR) : $value,
            'datetime'  => $this->asDateTime($value),
            'date'      => $this->asDate($value),
            'timestamp' => $this->asTimestamp($value)
        };
    }

    /**
     * Reverse cast value from PHP type to database format
     *
     * @throws \InvalidArgumentException Invalid cast type
     */
    private function uncastAttribute(string $name, mixed $value): mixed
    {
        if (!isset($this->casts[$name]) || null === $value) {
            return $value;
        }

        $type = $this->casts[$name];

        $this->validateCastType($type);

        return match ($type) {
            'int'       => (int) $value, // @phpstan-ignore cast.int
            'float'     => (float) $value, // @phpstan-ignore cast.double
            'string'    => (string) $value, // @phpstan-ignore cast.string
            'bool'      => (bool) $value,
            'json'      => is_array($value) ? $this->jsonEncode($value) : $value,
            'datetime'  => $value instanceof DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value,
            'date'      => $value instanceof DateTimeInterface ? $value->format('Y-m-d') : $value,
            'timestamp' => $value instanceof DateTimeInterface ? $value->getTimestamp() : $value
        };
    }

    /**
     * Callbacks applied to attributes on get
     *
     * @return array<string, callable(mixed $value): mixed>
     */
    protected function accessors(): array
    {
        return [];
    }

    /**
     * Callbacks applied to attributes on set
     *
     * @return array<string, callable(mixed $value): mixed>
     */
    protected function mutators(): array
    {
        return [];
    }

    private function applyAccessor(string $name, mixed $value): mixed
    {
        /** @var ?callable(mixed $value): mixed $callback */
        $callback = ($this->accessorCache ??= $this->accessors())[$name] ?? null;

        if (null === $callback) {
            return $value;
        }

        return $callback($value);
    }

    private function applyMutator(string $name, mixed $value): mixed
    {
        /** @var ?callable(mixed $value): mixed $callback */
        $callback = ($this->mutatorCache ??= $this->mutators())[$name] ?? null;

        if (null === $callback) {
            return $value;
        }

        return $callback($value);
    }

    /**
     * @param array<mixed, mixed> $value
     */
    private function jsonEncode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \InvalidArgumentException
     *
     * @phpstan-assert 'int'|'float'|'string'|'bool'|'json'|'datetime'|'date'|'timestamp' $type
     */
    private function validateCastType(string $type): void
    {
        if (!in_array($type, self::SUPPORTED_CASTS, true)) {
            throw new InvalidArgumentException(sprintf(
                'Unsupported cast type %s.  Supported Types: %s',
                $type,
                implode(', ', self::SUPPORTED_CASTS)
            ));
        }
    }

    private function asDateTime(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value);
        }

        if (is_int($value)) {
            return new DateTimeImmutable()->setTimestamp($value);
        }

        return null;
    }

    private function asDate(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTime(0, 0, 0);
        }

        if (is_string($value)) {
            return new DateTimeImmutable($value)->setTime(0, 0, 0);
        }

        if (is_int($value)) {
            return new DateTimeImmutable()->setTimestamp($value)->setTime(0, 0, 0);
        }

        return null;
    }

    private function asTimestamp(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if (is_numeric($value)) {
            return new DateTimeImmutable()->setTimestamp((int) $value);
        }

        return null;
    }

    /**
     * Check if the model is new (not yet persisted)
     */
    public function isNew(): bool
    {
        return false === $this->exists();
    }

    /**
     * Check if the model exists (has been persisted)
     */
    public function exists(): bool
    {
        return $this->exists;
    }

    public function isDirty(?string $name = null): bool
    {
        if (null !== $name) {
            $inOriginal   = array_key_exists($name, $this->original);
            $inAttributes = array_key_exists($name, $this->attributes);

            if ($inOriginal !== $inAttributes) {
                return true;
            }

            return $inOriginal && $this->original[$name] !== $this->attributes[$name];
        }

        foreach ($this->attributes as $key => $value) {
            if ($this->isDirty($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach (array_keys($this->attributes) as $name) {
            if ($this->isDirty($name)) {
                $dirty[$name] = $this->attributes[$name];
            }
        }

        return $dirty;
    }

    /**
     * Get the value of an attribute before modification (raw value from database)
     */
    public function getOriginal(?string $name = null): mixed
    {
        if (null === $name) {
            return $this->original;
        }

        return $this->original[$name] ?? null;
    }

    /**
     * Sync current attributes as original (called after save)
     */
    public function syncOriginal(): void
    {
        $this->original = $this->attributes;

        $this->exists = true;
    }

    public function getPrimaryKeyName(): string
    {
        return $this->primaryKey;
    }

    /**
     * @return string 'string' or 'int'
     */
    public function getPrimaryKeyType(): string
    {
        return $this->primaryKeyType;
    }

    public function getPrimaryKeyValue(): int|string|null
    {
        /** @var int|string|null */
        return $this->getAttribute($this->getPrimaryKeyName());
    }

    /**
     * @throws \RuntimeException When attempting to reset the value
     */
    public function setPrimaryKeyValue(int|string|null $value): void
    {
        $this->setAttribute($this->getPrimaryKeyName(), $value);
    }

    /**
     * Indicates if the primary key is auto incrementing
     */
    public function isIncrementing(): bool
    {
        return $this->incrementing;
    }

    /**
     * @throws \RuntimeException When table property is empty
     */
    public function getTable(): string
    {
        if ('' === $this->table) {
            throw new RuntimeException('Table name not set');
        }

        return $this->table;
    }

    /**
     * Indicates if the model has timestamps
     */
    public function hasTimestamps(): bool
    {
        return $this->timestamps;
    }

    public function getCreatedAtColumn(): string
    {
        return $this->createdAtColumn;
    }

    public function getUpdatedAtColumn(): string
    {
        return $this->updatedAtColumn;
    }

    /**
     * Touch the timestamps before saving
     */
    public function touchTimestamps(): void
    {
        if (!$this->hasTimestamps()) {
            return;
        }

        $now = new DateTimeImmutable();

        $createdAt = $this->attributes[$this->getCreatedAtColumn()] ?? null;

        if (null === $createdAt) {
            $this->setAttribute($this->getCreatedAtColumn(), $now);
        }

        $this->setAttribute($this->getUpdatedAtColumn(), $now);
    }

    /**
     * Indicates if the primary key should be auto-generated
     */
    public function shouldGeneratePrimaryKey(): bool
    {
        return $this->isNew()
            && null === $this->getPrimaryKeyValue()
            && !$this->isIncrementing()
            && 'string' === $this->getPrimaryKeyType();
    }
}
