<?php

namespace Meritum\Database\Test\Support;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Meritum\Database\Model;
use Meritum\Database\Support\Collection;
use Meritum\Database\Support\Paginator;

class PaginatorTest extends TestCase
{
    /**
     * @param array<string, Model> $models
     * @return Collection<Model>
     */
    private function collect(array $models = []): Collection
    {
        return new Collection($models);
    }

    private function paginator(
        int $total,
        int $perPage,
        int $currentPage,
        int $lastPage,
        Collection $collection = new Collection([]),
    ): Paginator {
        return new Paginator($collection, $total, $perPage, $currentPage, $lastPage);
    }

    // --- accessors ---

    #[Test]
    public function test_total(): void
    {
        $this->assertSame(100, $this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10)->total());
    }

    #[Test]
    public function test_per_page(): void
    {
        $this->assertSame(10, $this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10)->perPage());
    }

    #[Test]
    public function test_current_page(): void
    {
        $this->assertSame(3, $this->paginator(total: 100, perPage: 10, currentPage: 3, lastPage: 10)->currentPage());
    }

    #[Test]
    public function test_last_page(): void
    {
        $this->assertSame(10, $this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10)->lastPage());
    }

    // --- hasMorePages ---

    #[Test]
    public function test_has_more_pages_when_not_on_last_page(): void
    {
        $this->assertTrue($this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10)->hasMorePages());
    }

    #[Test]
    public function test_no_more_pages_on_last_page(): void
    {
        $this->assertFalse($this->paginator(total: 100, perPage: 10, currentPage: 10, lastPage: 10)->hasMorePages());
    }

    // --- from ---

    #[Test]
    public function test_from_on_first_page(): void
    {
        $this->assertSame(1, $this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10)->from());
    }

    #[Test]
    public function test_from_on_second_page(): void
    {
        $this->assertSame(11, $this->paginator(total: 100, perPage: 10, currentPage: 2, lastPage: 10)->from());
    }

    #[Test]
    public function test_from_returns_zero_when_total_is_zero(): void
    {
        $this->assertSame(0, $this->paginator(total: 0, perPage: 10, currentPage: 1, lastPage: 1)->from());
    }

    // --- to ---

    #[Test]
    public function test_to_on_full_page(): void
    {
        $this->assertSame(10, $this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10)->to());
    }

    #[Test]
    public function test_to_on_last_partial_page(): void
    {
        $this->assertSame(95, $this->paginator(total: 95, perPage: 10, currentPage: 10, lastPage: 10)->to());
    }

    #[Test]
    public function test_to_returns_zero_when_total_is_zero(): void
    {
        $this->assertSame(0, $this->paginator(total: 0, perPage: 10, currentPage: 1, lastPage: 1)->to());
    }

    // --- toArray ---

    #[Test]
    public function test_to_array_contains_expected_keys(): void
    {
        $result = $this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10)->toArray();

        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('perPage', $result);
        $this->assertArrayHasKey('currentPage', $result);
        $this->assertArrayHasKey('lastPage', $result);
        $this->assertArrayHasKey('from', $result);
        $this->assertArrayHasKey('to', $result);
    }

    #[Test]
    public function test_json_serialize_matches_to_array(): void
    {
        $paginator = $this->paginator(total: 100, perPage: 10, currentPage: 1, lastPage: 10);

        $this->assertSame($paginator->toArray(), $paginator->jsonSerialize());
    }

    #[Test]
    public function test_collection_is_returned(): void
    {
        $collection = $this->collect();
        $paginator  = new Paginator($collection, 0, 10, 1, 1);

        $this->assertSame($collection, $paginator->collection());
    }
}
