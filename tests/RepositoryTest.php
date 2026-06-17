<?php

namespace Meritum\Database\Test;

use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Meritum\Database\Model;
use Meritum\Database\Repository;
use Meritum\Database\Support\Cursor;
use Meritum\Database\Support\Collection;
use Meritum\Database\Support\Paginator;
use Meritum\Database\Support\CursorPaginator;
use Georgeff\Database\Contract\SelectInterface;
use Georgeff\Database\Contract\InsertInterface;
use Georgeff\Database\Contract\UpdateInterface;
use Georgeff\Database\Contract\DeleteInterface;
use Georgeff\Database\Contract\DatabaseManagerInterface;

trait ExposesAttributes
{
    public function get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    public function set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }
}

class UserModel extends Model
{
    use ExposesAttributes;

    protected string $table = 'users';

    protected bool $timestamps = false;
}

class CounterModel extends Model
{
    use ExposesAttributes;

    protected string $table = 'counters';

    protected string $primaryKeyType = 'int';

    protected bool $incrementing = true;

    protected bool $timestamps = false;
}

class PostModel extends Model
{
    use ExposesAttributes;

    protected string $table = 'posts';
}

/**
 * @extends Repository<UserModel>
 */
class TestRepository extends Repository
{
    /**
     * @param class-string<Model> $modelClass
     */
    public function __construct(
        DatabaseManagerInterface $db,
        private readonly string $modelClass = UserModel::class,
    ) {
        parent::__construct($db);
    }

    protected function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * @param string[] $columns
     */
    public function newQuery(array $columns = ['*']): SelectInterface
    {
        return $this->query($columns);
    }

    public function callFirst(): ?Model
    {
        return $this->first();
    }

    public function callGet(): Collection
    {
        return $this->get();
    }

    public function callCount(bool $reset = true): int
    {
        return $this->count($reset);
    }

    public function callPaginate(int $perPage, int $currentPage): Paginator
    {
        return $this->paginate($perPage, $currentPage);
    }

    public function callCursor(int $perPage, ?string $cursor = null): CursorPaginator
    {
        return $this->cursor($perPage, $cursor);
    }

    /**
     * @param callable(SelectInterface $query): void $callback
     */
    public function callAddScope(string $name, callable $callback): static
    {
        return $this->addScope($name, $callback);
    }

    public function callWithoutScope(string $name): static
    {
        return $this->withoutScope($name);
    }

    public function callWithoutScopes(): static
    {
        return $this->withoutScopes();
    }

    public function callGetTable(): string
    {
        return $this->getTable();
    }
}

class RepositoryTest extends TestCase
{
    /**
     * @return SelectInterface&MockObject
     */
    private function fluentSelect(): SelectInterface
    {
        $select = $this->createMock(SelectInterface::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('orderBy')->willReturnSelf();
        $select->method('limit')->willReturnSelf();
        $select->method('offset')->willReturnSelf();
        $select->method('setPaging')->willReturnSelf();
        $select->method('groupBy')->willReturnSelf();

        return $select;
    }

    /**
     * @return DatabaseManagerInterface&MockObject
     */
    private function db(): DatabaseManagerInterface
    {
        return $this->createMock(DatabaseManagerInterface::class);
    }

    /**
     * @return InsertInterface&MockObject
     */
    private function insertMock(): InsertInterface
    {
        $insert = $this->createMock(InsertInterface::class);
        $insert->method('into')->willReturnSelf();
        $insert->method('addRow')->willReturnSelf();
        $insert->method('column')->willReturnSelf();

        return $insert;
    }

    /**
     * @return UpdateInterface&MockObject
     */
    private function updateMock(): UpdateInterface
    {
        $update = $this->createMock(UpdateInterface::class);
        $update->method('table')->willReturnSelf();
        $update->method('where')->willReturnSelf();
        $update->method('columns')->willReturnSelf();
        $update->method('column')->willReturnSelf();

        return $update;
    }

    /**
     * @return DeleteInterface&MockObject
     */
    private function deleteMock(): DeleteInterface
    {
        $delete = $this->createMock(DeleteInterface::class);
        $delete->method('from')->willReturnSelf();
        $delete->method('where')->willReturnSelf();

        return $delete;
    }

    // --- save: insert ---

    #[Test]
    public function test_save_inserts_new_model_and_generates_string_uuid(): void
    {
        $insert = $this->insertMock();
        $insert->expects($this->once())
               ->method('into')
               ->with('users')
               ->willReturnSelf();
        $insert->expects($this->once())
               ->method('addRow')
               ->with($this->callback(function (array $data): bool {
                   return 'Alice' === $data['name']
                       && is_string($data['id'])
                       && 36 === strlen($data['id']);
               }))
               ->willReturnSelf();

        $db = $this->db();
        $db->method('insert')->willReturn($insert);
        $db->expects($this->once())->method('fetchAffected')->with($insert)->willReturn(1);
        $db->expects($this->never())->method('lastInsertId');

        $repo  = new TestRepository($db);
        $model = new UserModel(['name' => 'Alice']);

        $this->assertTrue($repo->save($model));
        $this->assertIsString($model->getPrimaryKeyValue());
        $this->assertFalse($model->isNew());
        $this->assertFalse($model->isDirty());
    }

    #[Test]
    public function test_save_returns_false_when_insert_affects_no_rows(): void
    {
        $db = $this->db();
        $db->method('insert')->willReturn($this->insertMock());
        $db->method('fetchAffected')->willReturn(0);

        $repo  = new TestRepository($db);
        $model = new UserModel(['name' => 'Alice']);

        $this->assertFalse($repo->save($model));
        $this->assertTrue($model->isNew());
    }

    #[Test]
    public function test_save_returns_false_when_model_is_not_dirty(): void
    {
        $db = $this->db();
        $db->expects($this->never())->method('insert');
        $db->expects($this->never())->method('update');
        $db->expects($this->never())->method('fetchAffected');

        $repo  = new TestRepository($db);
        $model = new UserModel(['id' => 'abc', 'name' => 'Alice']);
        $model->syncOriginal();

        $this->assertFalse($repo->save($model));
    }

    #[Test]
    public function test_save_populates_last_insert_id_for_incrementing_model(): void
    {
        $db = $this->db();
        $db->method('insert')->willReturn($this->insertMock());
        $db->method('fetchAffected')->willReturn(1);
        $db->expects($this->once())->method('lastInsertId')->willReturn('42');

        $repo  = new TestRepository($db, CounterModel::class);
        $model = new CounterModel(['name' => 'Widget']);

        $this->assertTrue($repo->save($model));
        $this->assertSame(42, $model->getPrimaryKeyValue());
    }

    #[Test]
    public function test_save_does_not_generate_uuid_for_incrementing_model(): void
    {
        $db = $this->db();
        $db->method('insert')->willReturn($this->insertMock());
        $db->method('fetchAffected')->willReturn(1);
        $db->method('lastInsertId')->willReturn(null);

        $repo  = new TestRepository($db, CounterModel::class);
        $model = new CounterModel(['name' => 'Widget']);

        $repo->save($model);

        // lastInsertId was null, so no primary key should have been set
        $this->assertNull($model->getPrimaryKeyValue());
    }

    #[Test]
    public function test_save_touches_timestamps_on_model_with_timestamps(): void
    {
        $db = $this->db();
        $db->method('insert')->willReturn($this->insertMock());
        $db->method('fetchAffected')->willReturn(1);

        $repo  = new TestRepository($db, PostModel::class);
        $model = new PostModel(['title' => 'Hello']);

        $repo->save($model);

        $this->assertNotNull($model->get('created_at'));
        $this->assertNotNull($model->get('updated_at'));
    }

    // --- save: update ---

    #[Test]
    public function test_save_updates_existing_dirty_model(): void
    {
        $update = $this->updateMock();
        $update->expects($this->once())->method('table')->with('users')->willReturnSelf();
        $update->expects($this->once())->method('where')->with('id', 'abc')->willReturnSelf();
        $update->expects($this->once())
               ->method('columns')
               ->with(['name' => 'Robert'])
               ->willReturnSelf();

        $db = $this->db();
        $db->expects($this->never())->method('insert');
        $db->method('update')->willReturn($update);
        $db->expects($this->once())->method('fetchAffected')->with($update)->willReturn(1);

        $repo  = new TestRepository($db);
        $model = new UserModel(['id' => 'abc', 'name' => 'Bob']);
        $model->syncOriginal();
        $model->set('name', 'Robert');

        $this->assertTrue($repo->save($model));
        $this->assertFalse($model->isDirty());
    }

    #[Test]
    public function test_save_returns_false_when_update_affects_no_rows(): void
    {
        $db = $this->db();
        $db->method('update')->willReturn($this->updateMock());
        $db->method('fetchAffected')->willReturn(0);

        $repo  = new TestRepository($db);
        $model = new UserModel(['id' => 'abc', 'name' => 'Bob']);
        $model->syncOriginal();
        $model->set('name', 'Robert');

        $this->assertFalse($repo->save($model));
        $this->assertTrue($model->isDirty());
    }

    // --- delete ---

    #[Test]
    public function test_delete_returns_true_when_a_row_is_affected(): void
    {
        $delete = $this->deleteMock();
        $delete->expects($this->once())->method('from')->with('users')->willReturnSelf();
        $delete->expects($this->once())->method('where')->with('id', 'abc')->willReturnSelf();

        $db = $this->db();
        $db->method('delete')->willReturn($delete);
        $db->expects($this->once())->method('fetchAffected')->with($delete)->willReturn(1);

        $repo  = new TestRepository($db);
        $model = new UserModel(['id' => 'abc']);

        $this->assertTrue($repo->delete($model));
    }

    #[Test]
    public function test_delete_returns_false_when_no_row_is_affected(): void
    {
        $db = $this->db();
        $db->method('delete')->willReturn($this->deleteMock());
        $db->method('fetchAffected')->willReturn(0);

        $repo  = new TestRepository($db);
        $model = new UserModel(['id' => 'abc']);

        $this->assertFalse($repo->delete($model));
    }

    // --- find / findBy ---

    #[Test]
    public function test_find_hydrates_model_from_primary_key(): void
    {
        $select = $this->fluentSelect();
        $select->expects($this->once())->method('where')->with('id', 'abc')->willReturnSelf();

        $db = $this->db();
        $db->method('select')->willReturn($select);
        $db->expects($this->once())
           ->method('fetchOne')
           ->willReturn(['id' => 'abc', 'name' => 'Alice']);

        $repo  = new TestRepository($db);
        $model = $repo->find('abc');

        $this->assertInstanceOf(UserModel::class, $model);
        $this->assertSame('Alice', $model->get('name'));
        $this->assertFalse($model->isNew());
        $this->assertFalse($model->isDirty());
    }

    #[Test]
    public function test_find_returns_null_when_no_row_found(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchOne')->willReturn(null);

        $repo = new TestRepository($db);

        $this->assertNull($repo->find('missing'));
    }

    #[Test]
    public function test_find_by_queries_given_column(): void
    {
        $select = $this->fluentSelect();
        $select->expects($this->once())->method('where')->with('email', 'a@b.com')->willReturnSelf();

        $db = $this->db();
        $db->method('select')->willReturn($select);
        $db->method('fetchOne')->willReturn(['id' => 'abc', 'email' => 'a@b.com']);

        $repo  = new TestRepository($db);
        $model = $repo->findBy('email', 'a@b.com');

        $this->assertInstanceOf(UserModel::class, $model);
    }

    #[Test]
    public function test_find_or_fail_returns_model_when_found(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchOne')->willReturn(['id' => 'abc', 'name' => 'Alice']);

        $repo  = new TestRepository($db);
        $model = $repo->findOrFail('abc');

        $this->assertInstanceOf(UserModel::class, $model);
        $this->assertSame('Alice', $model->get('name'));
    }

    #[Test]
    public function test_find_or_fail_throws_when_not_found(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchOne')->willReturn(null);

        $repo = new TestRepository($db);

        $this->expectException(\Meritum\Database\Exception\ModelNotFoundException::class);
        $this->expectExceptionMessage('abc');

        $repo->findOrFail('abc');
    }

    // --- first ---

    #[Test]
    public function test_first_throws_when_query_not_initialized(): void
    {
        $repo = new TestRepository($this->db());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call first, query has not been initialized');

        $repo->callFirst();
    }

    #[Test]
    public function test_first_resets_query_when_result_is_null(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchOne')->willReturn(null);

        $repo = new TestRepository($db);
        $repo->newQuery();
        $repo->callFirst();

        $this->expectException(RuntimeException::class);
        $repo->callFirst();
    }

    // --- get ---

    #[Test]
    public function test_get_throws_when_query_not_initialized(): void
    {
        $repo = new TestRepository($this->db());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call get, query has not been initialized');

        $repo->callGet();
    }

    #[Test]
    public function test_get_returns_collection_keyed_by_primary_key(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchAll')->willReturn([
            ['id' => 'a', 'name' => 'Alice'],
            ['id' => 'b', 'name' => 'Bob'],
        ]);

        $repo = new TestRepository($db);
        $repo->newQuery();
        $collection = $repo->callGet();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertCount(2, $collection);
        $this->assertSame(['a', 'b'], $collection->keys());
        $this->assertSame('Bob', $collection->get('b')->get('name'));
    }

    #[Test]
    public function test_get_resets_query_after_running(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchAll')->willReturn([]);

        $repo = new TestRepository($db);
        $repo->newQuery();
        $repo->callGet();

        $this->expectException(RuntimeException::class);
        $repo->callGet();
    }

    #[Test]
    public function test_get_throws_when_model_has_no_primary_key(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchAll')->willReturn([
            ['name' => 'Alice'],
        ]);

        $repo = new TestRepository($db);
        $repo->newQuery();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Model returned from query has no primary key value.');

        $repo->callGet();
    }

    // --- count ---

    #[Test]
    public function test_count_throws_when_query_not_initialized(): void
    {
        $repo = new TestRepository($this->db());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call count, query has not been initialized');

        $repo->callCount();
    }

    #[Test]
    public function test_count_returns_result_and_resets_by_default(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('count')->willReturn(5);

        $repo = new TestRepository($db);
        $repo->newQuery();

        $this->assertSame(5, $repo->callCount());

        $this->expectException(RuntimeException::class);
        $repo->callCount();
    }

    #[Test]
    public function test_count_preserves_query_when_reset_is_false(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('count')->willReturn(7);

        $repo = new TestRepository($db);
        $repo->newQuery();

        $this->assertSame(7, $repo->callCount(false));
        $this->assertSame(7, $repo->callCount(false));
    }

    // --- paginate ---

    #[Test]
    public function test_paginate_throws_when_query_not_initialized(): void
    {
        $repo = new TestRepository($this->db());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call paginate, query has not been initialized');

        $repo->callPaginate(10, 1);
    }

    #[Test]
    public function test_paginate_throws_when_per_page_is_zero(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());

        $repo = new TestRepository($db);
        $repo->newQuery();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pagination per page cannot be zero');

        $repo->callPaginate(0, 1);
    }

    #[Test]
    public function test_paginate_builds_paginator(): void
    {
        $select = $this->fluentSelect();
        $select->expects($this->once())->method('setPaging')->with(4, 2)->willReturnSelf();

        $db = $this->db();
        $db->method('select')->willReturn($select);
        $db->method('count')->willReturn(10);
        $db->method('fetchAll')->willReturn([
            ['id' => 'e', 'name' => 'Eve'],
            ['id' => 'f', 'name' => 'Frank'],
        ]);

        $repo = new TestRepository($db);
        $repo->newQuery();
        $paginator = $repo->callPaginate(4, 2);

        $this->assertInstanceOf(Paginator::class, $paginator);
        $this->assertSame(10, $paginator->total());
        $this->assertSame(4, $paginator->perPage());
        $this->assertSame(2, $paginator->currentPage());
        $this->assertSame(3, $paginator->lastPage());
        $this->assertCount(2, $paginator->collection());
    }

    // --- cursorPaginate ---

    #[Test]
    public function test_cursor_throws_when_query_not_initialized(): void
    {
        $repo = new TestRepository($this->db());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot call cursor, query has not been initialized.');

        $repo->callCursor(10);
    }

    #[Test]
    public function test_cursor_throws_when_per_page_is_zero(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());

        $repo = new TestRepository($db);
        $repo->newQuery();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cursor per page cannot be zero');

        $repo->callCursor(0);
    }

    #[Test]
    public function test_cursor_returns_next_cursor_when_more_records_exist(): void
    {
        $select = $this->fluentSelect();
        $select->expects($this->once())->method('orderBy')->with('id', 'ASC')->willReturnSelf();
        $select->expects($this->once())->method('limit')->with(3)->willReturnSelf();

        $db = $this->db();
        $db->method('select')->willReturn($select);
        $db->method('fetchAll')->willReturn([
            ['id' => 'a', 'name' => 'A'],
            ['id' => 'b', 'name' => 'B'],
            ['id' => 'c', 'name' => 'C'],
        ]);

        $repo      = new TestRepository($db);
        $repo->newQuery();
        $paginator = $repo->callCursor(2);

        $this->assertInstanceOf(CursorPaginator::class, $paginator);
        $this->assertCount(2, $paginator->collection());
        $this->assertTrue($paginator->hasMorePages());

        $decoded = Cursor::decode($paginator->nextCursor());
        $this->assertSame('b', $decoded->value);
        $this->assertSame('next', $decoded->direction);
    }

    #[Test]
    public function test_cursor_has_no_next_cursor_on_last_page(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchAll')->willReturn([
            ['id' => 'a', 'name' => 'A'],
            ['id' => 'b', 'name' => 'B'],
        ]);

        $repo      = new TestRepository($db);
        $repo->newQuery();
        $paginator = $repo->callCursor(2);

        $this->assertNull($paginator->nextCursor());
        $this->assertFalse($paginator->hasMorePages());
        $this->assertNull($paginator->previousCursor());
    }

    #[Test]
    public function test_cursor_prev_direction_reverses_results_and_sets_next_cursor(): void
    {
        $select = $this->fluentSelect();
        $select->expects($this->once())->method('orderBy')->with('id', 'DESC')->willReturnSelf();
        $select->expects($this->once())->method('where')->with('id', 'x', '<')->willReturnSelf();

        $db = $this->db();
        $db->method('select')->willReturn($select);
        $db->method('fetchAll')->willReturn([
            ['id' => 'c', 'name' => 'C'],
            ['id' => 'b', 'name' => 'B'],
        ]);

        $repo      = new TestRepository($db);
        $repo->newQuery();
        $cursor    = Cursor::encode('x', 'prev');
        $paginator = $repo->callCursor(2, $cursor);

        $this->assertSame(['b', 'c'], $paginator->collection()->keys());

        $nextDecoded = Cursor::decode($paginator->nextCursor());
        $this->assertSame('c', $nextDecoded->value);
        $this->assertSame('next', $nextDecoded->direction);

        $this->assertNull($paginator->previousCursor());
        $this->assertFalse($paginator->hasPreviousPages());
    }

    #[Test]
    public function test_cursor_with_next_cursor_sets_previous_cursor(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());
        $db->method('fetchAll')->willReturn([
            ['id' => 'c', 'name' => 'C'],
            ['id' => 'd', 'name' => 'D'],
        ]);

        $repo      = new TestRepository($db);
        $repo->newQuery();
        $cursor    = Cursor::encode('b', 'next');
        $paginator = $repo->callCursor(2, $cursor);

        $this->assertNull($paginator->nextCursor());
        $this->assertNotNull($paginator->previousCursor());

        $prev = Cursor::decode($paginator->previousCursor());
        $this->assertSame('c', $prev->value);
        $this->assertSame('prev', $prev->direction);
    }

    #[Test]
    public function test_cursor_prev_direction_with_more_pages_sets_previous_cursor(): void
    {
        $select = $this->fluentSelect();
        $select->expects($this->once())->method('orderBy')->with('id', 'DESC')->willReturnSelf();

        $db = $this->db();
        $db->method('select')->willReturn($select);
        $db->method('fetchAll')->willReturn([
            ['id' => 'c', 'name' => 'C'],
            ['id' => 'b', 'name' => 'B'],
            ['id' => 'a', 'name' => 'A'],
        ]);

        $repo      = new TestRepository($db);
        $repo->newQuery();
        $cursor    = Cursor::encode('d', 'prev');
        $paginator = $repo->callCursor(2, $cursor);

        $this->assertSame(['b', 'c'], $paginator->collection()->keys());
        $this->assertNotNull($paginator->nextCursor());
        $this->assertNotNull($paginator->previousCursor());

        $prev = Cursor::decode($paginator->previousCursor());
        $this->assertSame('b', $prev->value);
        $this->assertSame('prev', $prev->direction);
    }

    // --- scopes ---

    #[Test]
    public function test_added_scope_is_applied_to_query(): void
    {
        $select = $this->fluentSelect();
        $select->expects($this->once())->method('where')->with('status', 'active')->willReturnSelf();

        $db = $this->db();
        $db->method('select')->willReturn($select);

        $repo = new TestRepository($db);
        $repo->callAddScope('status', static function (SelectInterface $query): void {
            $query->where('status', 'active');
        });

        $repo->newQuery();
    }

    #[Test]
    public function test_without_scope_disables_a_single_scope(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());

        $calledA = false;
        $calledB = false;

        $repo = new TestRepository($db);
        $repo->callAddScope('a', function () use (&$calledA): void {
            $calledA = true;
        });
        $repo->callAddScope('b', function () use (&$calledB): void {
            $calledB = true;
        });

        $repo->callWithoutScope('a');
        $repo->newQuery();

        $this->assertFalse($calledA);
        $this->assertTrue($calledB);
    }

    #[Test]
    public function test_without_scope_only_applies_to_the_next_query(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());

        $calls = 0;

        $repo = new TestRepository($db);
        $repo->callAddScope('a', function () use (&$calls): void {
            $calls++;
        });

        $repo->callWithoutScope('a');
        $repo->newQuery(); // disabled here
        $repo->newQuery(); // re-enabled here

        $this->assertSame(1, $calls);
    }

    #[Test]
    public function test_without_scopes_disables_all_scopes_for_next_query(): void
    {
        $db = $this->db();
        $db->method('select')->willReturn($this->fluentSelect());

        $calls = 0;

        $repo = new TestRepository($db);
        $repo->callAddScope('a', function () use (&$calls): void {
            $calls++;
        });
        $repo->callAddScope('b', function () use (&$calls): void {
            $calls++;
        });

        $repo->callWithoutScopes();
        $repo->newQuery(); // all disabled
        $repo->newQuery(); // re-enabled, both run

        $this->assertSame(2, $calls);
    }

    // --- table ---

    #[Test]
    public function test_get_table_returns_model_table(): void
    {
        $repo = new TestRepository($this->db());

        $this->assertSame('users', $repo->callGetTable());
    }
}
