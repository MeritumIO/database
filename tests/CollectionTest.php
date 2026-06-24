<?php

namespace Meritum\Database\Test;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Meritum\Database\Model;
use Meritum\Database\Support\Collection;

class CollectionTest extends TestCase
{
    private function makeModel(string $id, array $attributes = []): Model
    {
        return new class(['id' => $id] + $attributes) extends Model {
            protected string $table = 'items';
        };
    }

    /**
     * @param array<string, Model> $models
     * @return Collection<Model>
     */
    private function collect(array $models = []): Collection
    {
        return new Collection($models);
    }

    // --- isEmpty / isNotEmpty ---

    #[Test]
    public function test_is_empty_on_empty_collection(): void
    {
        $this->assertTrue($this->collect()->isEmpty());
        $this->assertFalse($this->collect()->isNotEmpty());
    }

    #[Test]
    public function test_is_not_empty_with_models(): void
    {
        $collection = $this->collect(['a' => $this->makeModel('a')]);

        $this->assertFalse($collection->isEmpty());
        $this->assertTrue($collection->isNotEmpty());
    }

    // --- has ---

    #[Test]
    public function test_has_returns_true_for_existing_key(): void
    {
        $collection = $this->collect(['a' => $this->makeModel('a')]);

        $this->assertTrue($collection->has('a'));
    }

    #[Test]
    public function test_has_returns_false_for_missing_key(): void
    {
        $this->assertFalse($this->collect()->has('missing'));
    }

    // --- get ---

    #[Test]
    public function test_get_returns_model_by_key(): void
    {
        $model = $this->makeModel('a');
        $collection = $this->collect(['a' => $model]);

        $this->assertSame($model, $collection->get('a'));
    }

    #[Test]
    public function test_get_returns_null_for_missing_key(): void
    {
        $this->assertNull($this->collect()->get('missing'));
    }

    // --- all ---

    #[Test]
    public function test_all_returns_backing_array(): void
    {
        $model = $this->makeModel('a');
        $collection = $this->collect(['a' => $model]);

        $this->assertSame(['a' => $model], $collection->all());
    }

    // --- keys ---

    #[Test]
    public function test_keys_returns_array_of_keys(): void
    {
        $collection = $this->collect([
            'a' => $this->makeModel('a'),
            'b' => $this->makeModel('b'),
        ]);

        $this->assertSame(['a', 'b'], $collection->keys());
    }

    #[Test]
    public function test_keys_returns_empty_array_for_empty_collection(): void
    {
        $this->assertSame([], $this->collect()->keys());
    }

    // --- first / last ---

    #[Test]
    public function test_first_returns_first_model(): void
    {
        $a = $this->makeModel('a');
        $b = $this->makeModel('b');
        $collection = $this->collect(['a' => $a, 'b' => $b]);

        $this->assertSame($a, $collection->first());
    }

    #[Test]
    public function test_last_returns_last_model(): void
    {
        $a = $this->makeModel('a');
        $b = $this->makeModel('b');
        $collection = $this->collect(['a' => $a, 'b' => $b]);

        $this->assertSame($b, $collection->last());
    }

    #[Test]
    public function test_first_returns_null_on_empty_collection(): void
    {
        $this->assertNull($this->collect()->first());
    }

    #[Test]
    public function test_last_returns_null_on_empty_collection(): void
    {
        $this->assertNull($this->collect()->last());
    }

    // --- count ---

    #[Test]
    public function test_count_returns_number_of_models(): void
    {
        $collection = $this->collect([
            'a' => $this->makeModel('a'),
            'b' => $this->makeModel('b'),
        ]);

        $this->assertCount(2, $collection);
    }

    #[Test]
    public function test_count_returns_zero_for_empty_collection(): void
    {
        $this->assertCount(0, $this->collect());
    }

    // --- filter ---

    #[Test]
    public function test_filter_returns_matching_models(): void
    {
        $a = $this->makeModel('a');
        $b = $this->makeModel('b');
        $collection = $this->collect(['a' => $a, 'b' => $b]);

        $result = $collection->filter(fn(Model $m) => $m->getPrimaryKeyValue() === 'a');

        $this->assertCount(1, $result);
        $this->assertSame($a, $result->first());
    }

    #[Test]
    public function test_filter_preserves_keys(): void
    {
        $a = $this->makeModel('a');
        $b = $this->makeModel('b');
        $collection = $this->collect(['a' => $a, 'b' => $b]);

        $result = $collection->filter(fn(Model $m) => $m->getPrimaryKeyValue() === 'b');

        $this->assertTrue($result->has('b'));
    }

    #[Test]
    public function test_filter_returns_new_instance(): void
    {
        $collection = $this->collect(['a' => $this->makeModel('a')]);

        $result = $collection->filter(fn(Model $m) => true);

        $this->assertNotSame($collection, $result);
    }

    #[Test]
    public function test_filter_returns_empty_collection_when_no_match(): void
    {
        $collection = $this->collect(['a' => $this->makeModel('a')]);

        $result = $collection->filter(fn(Model $m) => false);

        $this->assertTrue($result->isEmpty());
    }

    // --- each ---

    #[Test]
    public function test_each_iterates_all_models(): void
    {
        $visited = [];
        $collection = $this->collect([
            'a' => $this->makeModel('a'),
            'b' => $this->makeModel('b'),
        ]);

        $collection->each(function (Model $m) use (&$visited): void {
            $visited[] = $m->getPrimaryKeyValue();
        });

        $this->assertSame(['a', 'b'], $visited);
    }

    #[Test]
    public function test_each_returns_same_instance(): void
    {
        $collection = $this->collect(['a' => $this->makeModel('a')]);

        $result = $collection->each(fn(Model $m) => null);

        $this->assertSame($collection, $result);
    }

    // --- push ---

    #[Test]
    public function test_push_adds_model_keyed_by_primary_key(): void
    {
        $collection = $this->collect();
        $model = $this->makeModel('a');

        $result = $collection->push($model);

        $this->assertTrue($result->has('a'));
        $this->assertSame($model, $result->get('a'));
    }

    #[Test]
    public function test_push_returns_new_instance(): void
    {
        $collection = $this->collect();

        $result = $collection->push($this->makeModel('a'));

        $this->assertNotSame($collection, $result);
    }

    #[Test]
    public function test_push_does_not_modify_original(): void
    {
        $collection = $this->collect();

        $collection->push($this->makeModel('a'));

        $this->assertTrue($collection->isEmpty());
    }

    #[Test]
    public function test_push_model_without_primary_key_appends_with_integer_key(): void
    {
        $model = new class extends Model {
            protected string $table = 'items';
        };

        $result = $this->collect()->push($model);

        $this->assertCount(1, $result);
    }

    // --- merge ---

    #[Test]
    public function test_merge_combines_two_collections(): void
    {
        $a = $this->collect(['a' => $this->makeModel('a')]);
        $b = $this->collect(['b' => $this->makeModel('b')]);

        $result = $a->merge($b);

        $this->assertCount(2, $result);
        $this->assertTrue($result->has('a'));
        $this->assertTrue($result->has('b'));
    }

    #[Test]
    public function test_merge_existing_wins_on_duplicate_key(): void
    {
        $original = $this->makeModel('a');
        $incoming = $this->makeModel('a');

        $a = $this->collect(['a' => $original]);
        $b = $this->collect(['a' => $incoming]);

        $result = $a->merge($b);

        $this->assertSame($original, $result->get('a'));
    }

    #[Test]
    public function test_merge_returns_new_instance(): void
    {
        $a = $this->collect();
        $b = $this->collect();

        $this->assertNotSame($a, $a->merge($b));
    }

    // --- iteration ---

    #[Test]
    public function test_collection_is_iterable_with_foreach(): void
    {
        $a = $this->makeModel('a');
        $b = $this->makeModel('b');
        $collection = $this->collect(['a' => $a, 'b' => $b]);

        $visited = [];

        foreach ($collection as $key => $model) {
            $visited[$key] = $model;
        }

        $this->assertSame(['a' => $a, 'b' => $b], $visited);
    }

    #[Test]
    public function test_collection_can_be_iterated_multiple_times(): void
    {
        $collection = $this->collect([
            'a' => $this->makeModel('a'),
            'b' => $this->makeModel('b'),
        ]);

        $first = iterator_to_array($collection);
        $second = iterator_to_array($collection);

        $this->assertSame($first, $second);
    }

    // --- toArray / toJson / jsonSerialize ---

    #[Test]
    public function test_to_array_returns_nested_model_arrays(): void
    {
        $collection = $this->collect(['a' => $this->makeModel('a', ['name' => 'Alice'])]);

        $result = $collection->toArray();

        $this->assertSame(['a' => ['id' => 'a', 'name' => 'Alice']], $result);
    }

    #[Test]
    public function test_json_serialize_matches_to_array(): void
    {
        $collection = $this->collect(['a' => $this->makeModel('a', ['name' => 'Alice'])]);

        $this->assertSame($collection->toArray(), $collection->jsonSerialize());
    }

    // --- serialize / unserialize ---

    #[Test]
    public function test_serialize_preserves_models(): void
    {
        $a = new SerializableModel(['id' => 'a', 'name' => 'Alice']);
        $a->syncOriginal();
        $b = new SerializableModel(['id' => 'b', 'name' => 'Bob']);
        $b->syncOriginal();

        $collection = new Collection(['a' => $a, 'b' => $b]);

        /** @var Collection<SerializableModel> $restored */
        $restored = unserialize(serialize($collection));

        $this->assertCount(2, $restored);
        $this->assertSame('Alice', $restored->get('a')->get('name'));
        $this->assertSame('Bob', $restored->get('b')->get('name'));
    }

    #[Test]
    public function test_serialize_preserves_keys(): void
    {
        $a = new SerializableModel(['id' => 'a']);
        $a->syncOriginal();
        $b = new SerializableModel(['id' => 'b']);
        $b->syncOriginal();

        $collection = new Collection(['a' => $a, 'b' => $b]);

        /** @var Collection<SerializableModel> $restored */
        $restored = unserialize(serialize($collection));

        $this->assertSame(['a', 'b'], $restored->keys());
    }

    #[Test]
    public function test_unserialized_models_are_not_dirty(): void
    {
        $model = new SerializableModel(['id' => 'a', 'name' => 'Alice']);
        $model->syncOriginal();

        $collection = new Collection(['a' => $model]);

        /** @var Collection<SerializableModel> $restored */
        $restored = unserialize(serialize($collection));

        $this->assertFalse($restored->get('a')->isDirty());
    }
}
