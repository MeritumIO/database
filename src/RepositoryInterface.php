<?php

namespace Meritum\Database;

use Meritum\Database\Exception\ModelNotFoundException;

/**
 * @template T of Model
 */
interface RepositoryInterface
{
    /**
     * Insert or Update
     *
     * @param T $model
     */
    public function save(Model $model): bool;

    /**
     * @param T $model
     */
    public function delete(Model $model): bool;

    /**
     * Find by primary key
     *
     * @return T|null
     */
    public function find(int|string $pk): ?Model;

    /**
     * Find by primary key or throw exception
     *
     * @return T
     *
     * @throws ModelNotFoundException
     */
    public function findOrFail(int|string $pk): Model;

    /**
     * Find by column/value
     *
     * @return T|null
     */
    public function findBy(string $column, mixed $value): ?Model;
}
