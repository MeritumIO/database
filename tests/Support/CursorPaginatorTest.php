<?php

namespace Meritum\Database\Test\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Meritum\Database\Model;
use Meritum\Database\Support\Collection;
use Meritum\Database\Support\CursorPaginator;

class CursorPaginatorTest extends TestCase
{
    /**
     * @return Collection<Model>
     */
    private function emptyCollection(): Collection
    {
        return new Collection([]);
    }

    private function paginator(
        ?string $nextCursor = null,
        ?string $previousCursor = null,
        int $perPage = 15,
        Collection $collection = new Collection([]),
    ): CursorPaginator {
        return new CursorPaginator($collection, $nextCursor, $previousCursor, $perPage);
    }

    // --- accessors ---

    #[Test]
    public function test_per_page(): void
    {
        $this->assertSame(15, $this->paginator(perPage: 15)->perPage());
    }

    #[Test]
    public function test_next_cursor_is_returned(): void
    {
        $this->assertSame('abc', $this->paginator(nextCursor: 'abc')->nextCursor());
    }

    #[Test]
    public function test_previous_cursor_is_returned(): void
    {
        $this->assertSame('xyz', $this->paginator(previousCursor: 'xyz')->previousCursor());
    }

    #[Test]
    public function test_next_cursor_is_null_when_not_set(): void
    {
        $this->assertNull($this->paginator()->nextCursor());
    }

    #[Test]
    public function test_previous_cursor_is_null_when_not_set(): void
    {
        $this->assertNull($this->paginator()->previousCursor());
    }

    // --- hasMorePages / hasPreviousPages ---

    #[Test]
    public function test_has_more_pages_when_next_cursor_set(): void
    {
        $this->assertTrue($this->paginator(nextCursor: 'abc')->hasMorePages());
    }

    #[Test]
    public function test_no_more_pages_when_next_cursor_null(): void
    {
        $this->assertFalse($this->paginator()->hasMorePages());
    }

    #[Test]
    public function test_has_previous_pages_when_previous_cursor_set(): void
    {
        $this->assertTrue($this->paginator(previousCursor: 'xyz')->hasPreviousPages());
    }

    #[Test]
    public function test_no_previous_pages_when_previous_cursor_null(): void
    {
        $this->assertFalse($this->paginator()->hasPreviousPages());
    }

    // --- toArray ---

    #[Test]
    public function test_to_array_contains_expected_keys(): void
    {
        $result = $this->paginator(nextCursor: 'abc', previousCursor: 'xyz')->toArray();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('nextCursor', $result);
        $this->assertArrayHasKey('previousCursor', $result);
    }

    #[Test]
    public function test_to_array_cursor_values_match(): void
    {
        $result = $this->paginator(nextCursor: 'abc', previousCursor: 'xyz')->toArray();

        $this->assertSame('abc', $result['nextCursor']);
        $this->assertSame('xyz', $result['previousCursor']);
    }

    #[Test]
    public function test_json_serialize_matches_to_array(): void
    {
        $paginator = $this->paginator(nextCursor: 'abc');

        $this->assertSame($paginator->toArray(), $paginator->jsonSerialize());
    }

    #[Test]
    public function test_collection_is_returned(): void
    {
        $collection = $this->emptyCollection();
        $paginator  = new CursorPaginator($collection, null, null, 15);

        $this->assertSame($collection, $paginator->collection());
    }
}
