<?php

namespace Meritum\Database\Test;

use RuntimeException;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Meritum\Database\Model;

class ModelTest extends TestCase
{
    private function makeModel(array $casts = [], array $attributes = [], array $accessors = [], array $mutators = []): Model
    {
        return new class($attributes, $casts, $accessors, $mutators) extends Model {
            protected string $table = 'items';

            public function __construct(
                array $attributes,
                array $casts,
                private readonly array $accessorMap,
                private readonly array $mutatorMap,
            ) {
                $this->casts = $casts;
                parent::__construct($attributes);
            }

            protected function accessors(): array
            {
                return $this->accessorMap;
            }

            protected function mutators(): array
            {
                return $this->mutatorMap;
            }

            public function get(string $name): mixed
            {
                return $this->getAttribute($name);
            }

            public function set(string $name, mixed $value): void
            {
                $this->setAttribute($name, $value);
            }
        };
    }

    #[Test]
    public function test_hydrate_sets_attributes(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice', 'age' => 30]);

        $this->assertSame('Alice', $model->get('name'));
        $this->assertSame(30, $model->get('age'));
    }

    #[Test]
    public function test_hydrate_throws_when_already_hydrated(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot hydrate a model that already has attributes.');

        $model->hydrate(['name' => 'Bob']);
    }

    #[Test]
    public function test_hydrate_allows_empty_initial_attributes(): void
    {
        $model = $this->makeModel();

        $this->assertSame([], $model->toArray());
    }

    #[Test]
    public function test_get_attribute_returns_null_for_missing_key(): void
    {
        $model = $this->makeModel();

        $this->assertNull($model->get('missing'));
    }

    #[Test]
    public function test_get_attribute_returns_null_for_null_value(): void
    {
        $model = $this->makeModel(attributes: ['name' => null]);

        $this->assertNull($model->get('name'));
    }

    #[Test]
    public function test_set_attribute_updates_value(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);

        $model->set('name', 'Bob');

        $this->assertSame('Bob', $model->get('name'));
    }

    #[Test]
    public function test_set_attribute_adds_new_attribute(): void
    {
        $model = $this->makeModel();

        $model->set('name', 'Alice');

        $this->assertSame('Alice', $model->get('name'));
    }

    #[Test]
    public function test_set_attribute_invalidates_cache(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);

        $model->get('name'); // populate cache
        $model->set('name', 'Bob');

        $this->assertSame('Bob', $model->get('name'));
    }

    // --- Primary key ---

    #[Test]
    public function test_get_primary_key_value_returns_null_when_not_set(): void
    {
        $model = $this->makeModel();

        $this->assertNull($model->getPrimaryKeyValue());
    }

    #[Test]
    public function test_set_primary_key_value(): void
    {
        $model = $this->makeModel();

        $model->setPrimaryKeyValue('abc-123');

        $this->assertSame('abc-123', $model->getPrimaryKeyValue());
    }

    #[Test]
    public function test_primary_key_cannot_be_changed_once_set(): void
    {
        $model = $this->makeModel(attributes: ['id' => 'abc-123']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot modify primary key [id] once set.');

        $model->setPrimaryKeyValue('different-id');
    }

    #[Test]
    public function test_primary_key_can_be_set_when_current_is_null(): void
    {
        $model = $this->makeModel();

        $model->setPrimaryKeyValue('abc-123');

        $this->assertSame('abc-123', $model->getPrimaryKeyValue());
    }

    #[Test]
    public function test_primary_key_set_to_same_value_does_not_throw(): void
    {
        $model = $this->makeModel(attributes: ['id' => 'abc-123']);

        $model->setPrimaryKeyValue('abc-123');

        $this->assertSame('abc-123', $model->getPrimaryKeyValue());
    }

    // --- exists / isNew ---

    #[Test]
    public function test_model_is_new_before_sync(): void
    {
        $model = $this->makeModel();

        $this->assertTrue($model->isNew());
        $this->assertFalse($model->exists());
    }

    #[Test]
    public function test_model_exists_after_sync_original(): void
    {
        $model = $this->makeModel();

        $model->syncOriginal();

        $this->assertFalse($model->isNew());
        $this->assertTrue($model->exists());
    }

    // --- dirty tracking ---

    #[Test]
    public function test_new_model_is_not_dirty(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);
        $model->syncOriginal();

        $this->assertFalse($model->isDirty());
    }

    #[Test]
    public function test_model_is_dirty_after_attribute_change(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);
        $model->syncOriginal();

        $model->set('name', 'Bob');

        $this->assertTrue($model->isDirty());
        $this->assertTrue($model->isDirty('name'));
    }

    #[Test]
    public function test_specific_attribute_dirty_check(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice', 'age' => 30]);
        $model->syncOriginal();

        $model->set('name', 'Bob');

        $this->assertTrue($model->isDirty('name'));
        $this->assertFalse($model->isDirty('age'));
    }

    #[Test]
    public function test_get_dirty_returns_changed_attributes(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice', 'age' => 30]);
        $model->syncOriginal();

        $model->set('name', 'Bob');

        $this->assertSame(['name' => 'Bob'], $model->getDirty());
    }

    #[Test]
    public function test_get_dirty_returns_empty_when_nothing_changed(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);
        $model->syncOriginal();

        $this->assertSame([], $model->getDirty());
    }

    #[Test]
    public function test_null_attribute_is_dirty_on_new_model(): void
    {
        $model = $this->makeModel(attributes: ['name' => null]);

        $this->assertTrue($model->isDirty('name'));
        $this->assertTrue($model->isDirty());
    }

    #[Test]
    public function test_get_dirty_includes_null_attribute_on_new_model(): void
    {
        $model = $this->makeModel(attributes: ['name' => null]);

        $dirty = $model->getDirty();

        $this->assertArrayHasKey('name', $dirty);
        $this->assertNull($dirty['name']);
    }

    #[Test]
    public function test_sync_original_clears_dirty_state(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);
        $model->syncOriginal();
        $model->set('name', 'Bob');

        $model->syncOriginal();

        $this->assertFalse($model->isDirty());
    }

    #[Test]
    public function test_get_original_returns_pre_modification_value(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);
        $model->syncOriginal();

        $model->set('name', 'Bob');

        $this->assertSame('Alice', $model->getOriginal('name'));
    }

    #[Test]
    public function test_get_original_returns_all_originals_when_no_name(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice', 'age' => 30]);
        $model->syncOriginal();

        $model->set('name', 'Bob');

        $this->assertSame(['name' => 'Alice', 'age' => 30], $model->getOriginal());
    }

    #[Test]
    public function test_get_original_returns_null_for_untracked_attribute(): void
    {
        $model = $this->makeModel();

        $this->assertNull($model->getOriginal('missing'));
    }

    // --- shouldGeneratePrimaryKey ---

    #[Test]
    public function test_should_generate_primary_key_when_new_string_pk_with_no_value(): void
    {
        $model = $this->makeModel();

        $this->assertTrue($model->shouldGeneratePrimaryKey());
    }

    #[Test]
    public function test_should_not_generate_primary_key_when_already_exists(): void
    {
        $model = $this->makeModel();
        $model->syncOriginal();

        $this->assertFalse($model->shouldGeneratePrimaryKey());
    }

    #[Test]
    public function test_should_not_generate_primary_key_when_value_already_set(): void
    {
        $model = $this->makeModel(attributes: ['id' => 'abc-123']);

        $this->assertFalse($model->shouldGeneratePrimaryKey());
    }

    #[Test]
    public function test_should_not_generate_primary_key_when_incrementing(): void
    {
        $model = new class extends Model {
            protected string $table = 'items';
            protected bool $incrementing = true;
            protected string $primaryKeyType = 'int';
        };

        $this->assertFalse($model->shouldGeneratePrimaryKey());
    }

    #[Test]
    public function test_should_not_generate_primary_key_when_int_type(): void
    {
        $model = new class extends Model {
            protected string $table = 'items';
            protected string $primaryKeyType = 'int';
        };

        $this->assertFalse($model->shouldGeneratePrimaryKey());
    }

    // --- table ---

    #[Test]
    public function test_get_table_returns_table_name(): void
    {
        $model = $this->makeModel();

        $this->assertSame('items', $model->getTable());
    }

    #[Test]
    public function test_get_table_throws_when_not_set(): void
    {
        $model = new class extends Model {};

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Table name not set');

        $model->getTable();
    }

    // --- timestamps ---

    #[Test]
    public function test_touch_timestamps_sets_created_at_and_updated_at_on_new_model(): void
    {
        $model = $this->makeModel();

        $model->touchTimestamps();

        $this->assertNotNull($model->get('created_at'));
        $this->assertNotNull($model->get('updated_at'));
    }

    #[Test]
    public function test_touch_timestamps_does_not_overwrite_created_at(): void
    {
        $model = $this->makeModel(attributes: ['created_at' => '2020-01-01 00:00:00']);
        $model->syncOriginal();

        $model->touchTimestamps();

        $this->assertSame('2020-01-01 00:00:00', $model->get('created_at'));
    }

    #[Test]
    public function test_touch_timestamps_always_updates_updated_at(): void
    {
        $model = $this->makeModel(attributes: ['updated_at' => '2020-01-01 00:00:00']);
        $model->syncOriginal();

        $model->touchTimestamps();

        $this->assertNotSame('2020-01-01 00:00:00', $model->get('updated_at'));
    }

    #[Test]
    public function test_touch_timestamps_does_nothing_when_timestamps_disabled(): void
    {
        $model = new class extends Model {
            protected string $table = 'items';
            protected bool $timestamps = false;

            public function get(string $name): mixed
            {
                return $this->getAttribute($name);
            }
        };

        $model->touchTimestamps();

        $this->assertNull($model->get('created_at'));
        $this->assertNull($model->get('updated_at'));
    }

    // --- toArray / toJson / jsonSerialize ---

    #[Test]
    public function test_to_array_returns_all_attributes(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice', 'age' => 30]);

        $this->assertSame(['name' => 'Alice', 'age' => 30], $model->toArray());
    }

    #[Test]
    public function test_to_array_formats_datetime_as_iso8601(): void
    {
        $model = $this->makeModel(
            casts: ['created_at' => 'datetime'],
            attributes: ['created_at' => '2024-01-15 12:00:00'],
        );

        $result = $model->toArray();

        $this->assertStringContainsString('2024-01-15', $result['created_at']);
    }

    #[Test]
    public function test_json_serialize_returns_array(): void
    {
        $model = $this->makeModel(attributes: ['name' => 'Alice']);

        $this->assertSame(['name' => 'Alice'], $model->jsonSerialize());
    }

    // --- casts ---

    #[Test]
    public function test_cast_int(): void
    {
        $model = $this->makeModel(casts: ['count' => 'int'], attributes: ['count' => '42']);

        $this->assertSame(42, $model->get('count'));
    }

    #[Test]
    public function test_cast_float(): void
    {
        $model = $this->makeModel(casts: ['price' => 'float'], attributes: ['price' => '9.99']);

        $this->assertSame(9.99, $model->get('price'));
    }

    #[Test]
    public function test_cast_string(): void
    {
        $model = $this->makeModel(casts: ['code' => 'string'], attributes: ['code' => 123]);

        $this->assertSame('123', $model->get('code'));
    }

    #[Test]
    public function test_cast_bool(): void
    {
        $model = $this->makeModel(casts: ['active' => 'bool'], attributes: ['active' => 1]);

        $this->assertTrue($model->get('active'));
    }

    #[Test]
    public function test_cast_json_decodes_string(): void
    {
        $model = $this->makeModel(casts: ['meta' => 'json'], attributes: ['meta' => '{"key":"value"}']);

        $this->assertSame(['key' => 'value'], $model->get('meta'));
    }

    #[Test]
    public function test_cast_json_throws_on_malformed_json(): void
    {
        $model = $this->makeModel(casts: ['meta' => 'json'], attributes: ['meta' => 'not-json']);

        $this->expectException(\JsonException::class);

        $model->get('meta');
    }

    #[Test]
    public function test_cast_datetime_from_string(): void
    {
        $model = $this->makeModel(casts: ['created_at' => 'datetime'], attributes: ['created_at' => '2024-01-15 12:00:00']);

        $result = $model->get('created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('2024-01-15', $result->format('Y-m-d'));
    }

    #[Test]
    public function test_cast_datetime_from_int(): void
    {
        $ts = mktime(12, 0, 0, 1, 15, 2024);
        $model = $this->makeModel(casts: ['created_at' => 'datetime'], attributes: ['created_at' => $ts]);

        $result = $model->get('created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
    }

    #[Test]
    public function test_cast_date_zeroes_time(): void
    {
        $model = $this->makeModel(casts: ['dob' => 'date'], attributes: ['dob' => '1990-05-20 14:30:00']);

        $result = $model->get('dob');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    #[Test]
    public function test_cast_date_from_int(): void
    {
        $ts = mktime(14, 30, 0, 5, 20, 1990);
        $model = $this->makeModel(casts: ['dob' => 'date'], attributes: ['dob' => $ts]);

        $result = $model->get('dob');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame('1990-05-20', $result->format('Y-m-d'));
        $this->assertSame('00:00:00', $result->format('H:i:s'));
    }

    #[Test]
    public function test_cast_timestamp_from_numeric(): void
    {
        $ts = mktime(12, 0, 0, 1, 15, 2024);
        $model = $this->makeModel(casts: ['created_at' => 'timestamp'], attributes: ['created_at' => $ts]);

        $result = $model->get('created_at');

        $this->assertInstanceOf(DateTimeImmutable::class, $result);
        $this->assertSame($ts, $result->getTimestamp());
    }

    #[Test]
    public function test_cast_null_value_returns_null_without_casting(): void
    {
        $model = $this->makeModel(casts: ['count' => 'int'], attributes: ['count' => null]);

        $this->assertNull($model->get('count'));
    }

    #[Test]
    public function test_invalid_cast_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported cast type');

        $this->makeModel(casts: ['field' => 'unsupported'], attributes: ['field' => 'value']);
    }

    // --- uncast (set with cast) ---

    #[Test]
    public function test_set_datetime_stores_formatted_string(): void
    {
        $model = $this->makeModel(casts: ['created_at' => 'datetime']);
        $dt = new DateTimeImmutable('2024-01-15 12:00:00');

        $model->set('created_at', $dt);

        $dirty = $model->getDirty();
        $this->assertSame('2024-01-15 12:00:00', $dirty['created_at']);
    }

    #[Test]
    public function test_set_json_stores_encoded_string(): void
    {
        $model = $this->makeModel(casts: ['meta' => 'json']);

        $model->set('meta', ['key' => 'value']);

        $dirty = $model->getDirty();
        $this->assertSame('{"key":"value"}', $dirty['meta']);
    }

    #[Test]
    public function test_set_timestamp_stores_int(): void
    {
        $model = $this->makeModel(casts: ['created_at' => 'timestamp']);
        $dt = new DateTimeImmutable('2024-01-15 12:00:00');

        $model->set('created_at', $dt);

        $dirty = $model->getDirty();
        $this->assertSame($dt->getTimestamp(), $dirty['created_at']);
    }

    // --- attribute cache ---

    #[Test]
    public function test_get_attribute_result_is_cached(): void
    {
        $callCount = 0;

        $model = $this->makeModel(
            accessors: ['name' => function (mixed $value) use (&$callCount): mixed {
                $callCount++;
                return $value;
            }],
            attributes: ['name' => 'Alice'],
        );

        $model->get('name');
        $model->get('name');

        $this->assertSame(1, $callCount);
    }

    #[Test]
    public function test_cache_handles_null_returning_accessor(): void
    {
        $callCount = 0;

        $model = $this->makeModel(
            accessors: ['name' => function (mixed $value) use (&$callCount): mixed {
                $callCount++;
                return null;
            }],
            attributes: ['name' => 'Alice'],
        );

        $model->get('name');
        $model->get('name');

        $this->assertSame(1, $callCount);
    }

    // --- accessors / mutators ---

    #[Test]
    public function test_accessor_transforms_value_on_get(): void
    {
        $model = $this->makeModel(
            accessors: ['name' => fn(mixed $v): string => strtoupper((string) $v)],
            attributes: ['name' => 'alice'],
        );

        $this->assertSame('ALICE', $model->get('name'));
    }

    #[Test]
    public function test_mutator_transforms_value_on_set(): void
    {
        $model = $this->makeModel(
            mutators: ['name' => fn(mixed $v): string => strtolower((string) $v)],
        );

        $model->set('name', 'ALICE');

        $this->assertSame('alice', $model->get('name'));
    }
}
