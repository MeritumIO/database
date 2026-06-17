<?php

namespace Meritum\Database\Support;

use JsonSerializable;
use Meritum\Database\Model;

/**
 * @template T of Model
 */
final class CursorPaginator implements JsonSerializable
{
    /**
     * @param Collection<T> $collection
     */
    public function __construct(
        private readonly Collection $collection,
        private readonly ?string $nextCursor,
        private readonly ?string $previousCursor,
        private readonly int $perPage
    ) {}

    /**
     * @return Collection<T>
     */
    public function collection(): Collection
    {
        return $this->collection;
    }

    public function perPage(): int
    {
        return $this->perPage;
    }

    public function nextCursor(): ?string
    {
        return $this->nextCursor;
    }

    public function previousCursor(): ?string
    {
        return $this->previousCursor;
    }

    public function hasMorePages(): bool
    {
        return null !== $this->nextCursor;
    }

    public function hasPreviousPages(): bool
    {
        return null !== $this->previousCursor;
    }

/**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'data'           => $this->collection()->toArray(),
            'perPage'        => $this->perPage(),
            'nextCursor'     => $this->nextCursor(),
            'previousCursor' => $this->previousCursor(),
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
