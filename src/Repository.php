<?php

namespace Meritum\Database;

use RuntimeException;
use InvalidArgumentException;
use Meritum\Database\Support\Uuid;
use Meritum\Database\Support\Cursor;
use Meritum\Database\Support\Paginator;
use Meritum\Database\Support\Collection;
use Meritum\Database\Support\CursorPaginator;
use Georgeff\Database\Contract\SelectInterface;
use Meritum\Database\Exception\ModelNotFoundException;
use Georgeff\Database\Contract\DatabaseManagerInterface;

/**
 * @template T of Model
 *
 * @implements RepositoryInterface<T>
 */
abstract class Repository implements RepositoryInterface
{
    private ?SelectInterface $query = null;

    private ?Model $model = null;

    /**
     * @var array<string, callable(SelectInterface $query): void>
     */
    private array $scopes = [];

    /**
     * @var array<string, bool>
     */
    private array $disabledScopes = [];

    private bool $disableAllScopes = false;

    public function __construct(protected readonly DatabaseManagerInterface $db) {}

    /**
     * Get the model's fully qualified class name
     *
     * @return class-string<T>
     */
    abstract protected function getModelClass(): string;

    private function getModelName(): string
    {
        return substr(strrchr($this->getModelClass(), '\\') ?: $this->getModelClass(), 1);
    }

    /**
     * Create a new fluent query
     *
     * @param string[] $columns
     */
    protected function query(array $columns = ['*']): SelectInterface
    {
        $this->query = null;

        $query = $this->db->select($columns)->from($this->getTable());

        $this->applyScopes($query);

        return $this->query = $query;
    }

    protected function getTable(): string
    {
        return $this->getModel()->getTable();
    }

    protected function getModel(): Model
    {
        $this->model ??= $this->newModelInstance();

        return $this->model;
    }

    /**
     * @return T
     */
    private function newModelInstance(): Model
    {
        $class = $this->getModelClass();

        return new $class();
    }

    /**
     * Insert or Update
     */
    public function save(Model $model): bool
    {
        if ($model->shouldGeneratePrimaryKey()) {
            $model->setPrimaryKeyValue($this->generateUuid());
        }

        if ($model->hasTimestamps()) {
            $model->touchTimestamps();
        }

        $table = $model->getTable();
        $data  = $model->getDirty();

        if ([] === $data) {
            return false;
        }

        $query = $model->isNew()
            ? $this->db->insert()->into($table)->addRow($data)
            : $this->db
                   ->update()
                   ->table($table)
                   ->where($model->getPrimaryKeyName(), $model->getPrimaryKeyValue())
                   ->columns($data);

        $affected = $this->db->fetchAffected($query);

        $isSaved = $affected > 0;

        if ($isSaved) {
            if ($model->isNew() && $model->isIncrementing()) {
                $id = $this->db->lastInsertId();

                if (null !== $id) {
                    $id = ('int' === $model->getPrimaryKeyType()) ? (int) $id : $id;

                    $model->setPrimaryKeyValue($id);
                }
            }

            $model->syncOriginal();
        }

        return $isSaved;
    }

    public function delete(Model $model): bool
    {
        $query = $this->db
                      ->delete()
                      ->from($model->getTable())
                      ->where($model->getPrimaryKeyName(), $model->getPrimaryKeyValue());

        $affected = $this->db->fetchAffected($query);

        return $affected > 0;
    }

    public function find(int|string $pk): ?Model
    {
        return $this->findBy($this->getModel()->getPrimaryKeyName(), $pk);
    }

    public function findOrFail(int|string $pk): Model
    {
        $model = $this->find($pk);

        if (null === $model) {
            throw new ModelNotFoundException($this->getModelName() . ' was not found');
        }

        return $model;
    }

    public function findBy(string $column, mixed $value): ?Model
    {
        $this->query()
             ->where($column, $value);

        return $this->first();
    }

    /**
     * @param callable(SelectInterface $query): void $callback
     */
    protected function addScope(string $name, callable $callback): static
    {
        $this->scopes[$name] = $callback;

        return $this;
    }

    /**
     * Disable a given scope
     */
    protected function withoutScope(string $name): static
    {
        $this->disabledScopes[$name] = true;

        return $this;
    }

    /**
     * Disable all scopes
     */
    protected function withoutScopes(): static
    {
        $this->disableAllScopes = true;

        return $this;
    }

    private function applyScopes(SelectInterface $query): void
    {
        if ($this->disableAllScopes) {
            $this->disableAllScopes = false;

            $this->disabledScopes = [];

            return;
        }

        foreach ($this->scopes as $name => $callback) {
            if (isset($this->disabledScopes[$name])) {
                continue;
            }

            $callback($query);
        }

        $this->disabledScopes = [];
    }

    /**
     * @return T|null
     */
    protected function first(): ?Model
    {
        if (!$this->query) {
            throw new RuntimeException('Cannot call first, query has not been initialized');
        }

        $result = $this->db->fetchOne($this->query);

        $this->reset();

        if (null === $result) {
            return null;
        }

        $model = $this->newModelInstance();

        /** @var array<string, mixed> $result */
        $model->hydrate($result);

        $model->syncOriginal();

        return $model;
    }

    /**
     * @return T
     */
    protected function firstOrFail(): Model
    {
        $model = $this->first();

        if (null === $model) {
            throw new ModelNotFoundException($this->getModelName() . ' was not found');
        }

        return $model;
    }

    /**
     * @return Collection<T>
     */
    protected function get(): Collection
    {
        if (!$this->query) {
            throw new RuntimeException('Cannot call get, query has not been initialized');
        }

        $models = [];

        $rows = $this->db->fetchAll($this->query);

        foreach ($rows as $item) {
            $model = $this->newModelInstance()->hydrate($item);

            $model->syncOriginal();

            $pk = $model->getPrimaryKeyValue();

            if (null === $pk) {
                throw new RuntimeException('Model returned from query has no primary key value.');
            }

            $models[$pk] = $model;
        }

        $this->reset();

        return new Collection($models);
    }

    /**
     * @param bool $reset Indicates if the query instance should be reset
     */
    protected function count(bool $reset = true): int
    {
        if (!$this->query) {
            throw new RuntimeException('Cannot call count, query has not been initialized');
        }

        $count = $this->db->count($this->query);

        if ($reset) {
            $this->reset();
        }

        return $count;
    }

    /**
     * @return Paginator<T>
     */
    protected function paginate(int $perPage, int $currentPage): Paginator
    {
        if (!$this->query) {
            throw new RuntimeException('Cannot call paginate, query has not been initialized');
        }

        if (0 === $perPage) {
            throw new InvalidArgumentException('Pagination per page cannot be zero');
        }

        $total = $this->count(false);

        $this->query->setPaging($perPage, $currentPage);

        $collection = $this->get();

        $lastPage = (int) ceil($total / $perPage);

        return new Paginator($collection, $total, $perPage, $currentPage, $lastPage);
    }

    /**
     * This method uses the model's primary key attribute for setting the sort order
     * Any previous calls to $this->query()->orderBy() are not cleared and will cause unexpected results
     * To clear any ordering calls before invoking this method call $this->query()->resetOrderBy()
     *
     * If using auto-generated UUID primary keys, overwrite Repository::generateUuid() to return Uuid::v7()
     *
     * @return CursorPaginator<T>
     */
    protected function cursor(int $perPage, ?string $cursor = null): CursorPaginator
    {
        if (!$this->query) {
            throw new RuntimeException('Cannot call cursor, query has not been initialized.');
        }

        if (0 === $perPage) {
            throw new InvalidArgumentException('Cursor per page cannot be zero');
        }

        $column    = $this->getModel()->getPrimaryKeyName();
        $direction = 'next';
        $decoded   = null;

        if (null !== $cursor) {
            $decoded   = Cursor::decode($cursor);
            $direction = $decoded->direction;

            $this->query->where($column, $decoded->value, 'next' === $direction ? '>' : '<');
        }

        $this->query
             ->orderBy($column, 'next' === $direction ? 'ASC' : 'DESC')
             ->limit($perPage + 1);

        $collection = $this->get();

        $hasMore = $collection->count() > $perPage;

        $arr = $collection->all();

        if ($hasMore) {
            array_pop($arr);
        }

        if ('prev' === $direction) {
            $arr = array_reverse($arr, true);
        }

        $nextCursor     = null;
        $previousCursor = null;

        if ([] !== $arr) {
            /** @var int|string $firstPk */
            $firstPk = array_key_first($arr);

            /** @var int|string $lastPk */
            $lastPk = array_key_last($arr);

            if ('next' === $direction) {
                $nextCursor     = $hasMore ? Cursor::encode($lastPk, 'next') : null;
                $previousCursor = null !== $decoded ? Cursor::encode($firstPk, 'prev') : null;
            } else {
                $nextCursor     = Cursor::encode($lastPk, 'next');
                $previousCursor = $hasMore ? Cursor::encode($firstPk, 'prev') : null;
            }
        }

        return new CursorPaginator(new Collection($arr), $nextCursor, $previousCursor, $perPage);
    }

    /**
     * Generate a UUID for string primary keys (default UUIDv4)
     *
     * Overwrite this method to change the UUID version
     */
    protected function generateUuid(): string
    {
        return Uuid::v4();
    }

    /**
     * Reset the repository state
     */
    private function reset(): void
    {
        $this->query = null;
        $this->model = null;
    }
}
