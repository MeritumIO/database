<?php

namespace Meritum\Database\Support;

use Iterator;
use Countable;
use JsonSerializable;
use Meritum\Database\Model;

/**
 * @template T of Model
 *
 * @implements Iterator<int|string, T>
 */
final class Collection implements Iterator, Countable, JsonSerializable
{
    /**
     * @var array<int|string, T>
     */
    private array $models;

    /**
     * @param array<int|string, T> $models
     */
    public function __construct(array $models)
    {
        $this->models = $models;
    }

    public function isEmpty(): bool
    {
        return empty($this->models);
    }

    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->models);
    }

    /**
     * @return T|null
     */
    public function get(int|string $key): ?Model
    {
        return $this->models[$key] ?? null;
    }

    /**
     * @return array<int|string, T>
     */
    public function all(): array
    {
        return $this->models;
    }

    /**
     * @return array<int|string>
     */
    public function keys(): array
    {
        return array_keys($this->models);
    }

    /**
     * @param self<T> $collection
     *
     * @return self<T>
     */
    public function merge(Collection $collection): self
    {
        return new self($this->models + $collection->all());
    }

    /**
     * @return T|null
     */
    public function first(): ?Model
    {
        $key = array_key_first($this->models);

        return null !== $key ? $this->models[$key] : null;
    }

    /**
     * @return T|null
     */
    public function last(): ?Model
    {
        $key = array_key_last($this->models);

        return null !== $key ? $this->models[$key] : null;
    }

    /**
     * @param callable(T $model): bool $callback
     *
     * @return self<T>
     */
    public function filter(callable $callback): self
    {
        return new self(array_filter($this->models, $callback));
    }

    /**
     * @param callable(T $model): void $callback
     *
     * @return self<T>
     */
    public function each(callable $callback): self
    {
        foreach ($this->models as $model) {
            $callback($model);
        }

        return $this;
    }

    /**
     * @param T $model
     *
     * @return self<T>
     */
    public function push(Model $model): self
    {
        $models = $this->models;

        $key = $model->getPrimaryKeyValue();

        if (null === $key) {
            $models[] = $model;
        } else {
            $models[$key] = $model;
        }

        return new self($models);
    }

    public function count(): int
    {
        return count($this->models);
    }

    /**
     * @return T|false
     */
    public function current(): mixed
    {
        return current($this->models);
    }

    /**
     * @return int|string|null
     */
    public function key(): mixed
    {
        return key($this->models);
    }

    public function next(): void
    {
        next($this->models);
    }

    public function rewind(): void
    {
        reset($this->models);
    }

    public function valid(): bool
    {
        return null !== $this->key();
    }

    /**
     * @return array<int|string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach ($this->models as $key => $model) {
            $data[$key] = $model->toArray();
        }

        return $data;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

}
