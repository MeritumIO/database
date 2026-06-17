<?php

namespace Meritum\Database\Support;

use JsonSerializable;
use Meritum\Database\Model;

/**
 * @template T of Model
 */
final class Paginator implements JsonSerializable
{
    /**
     * @param Collection<T> $collection
     */
    public function __construct(
        private readonly Collection $collection,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage,
        private readonly int $lastPage
    ) {}

    /**
     * @return Collection<T>
     */
    public function collection(): Collection
    {
        return $this->collection;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function currentPage(): int
    {
        return $this->currentPage;
    }

    public function lastPage(): int
    {
        return $this->lastPage;
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function from(): int
    {
        if (0 === $this->total) {
            return 0;
        }

        return ($this->currentPage - 1) * $this->perPage + 1;
    }

    public function to(): int
    {
        if (0 === $this->total) {
            return 0;
        }

        return min($this->currentPage * $this->perPage, $this->total);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data'        => $this->collection()->toArray(),
            'total'       => $this->total(),
            'perPage'     => $this->perPage(),
            'currentPage' => $this->currentPage(),
            'lastPage'    => $this->lastPage(),
            'from'        => $this->from(),
            'to'          => $this->to(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
