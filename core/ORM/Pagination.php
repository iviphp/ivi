<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

/**
 * Simple Pagination DTO
 * @template T
 */
final class Pagination
{
    /** @var array<int, mixed> */
    public array $items;
    public int $total;
    public int $perPage;
    public int $currentPage;
    public int $lastPage;

    /**
     * @param array<int,mixed> $items
     */
    public function __construct(array $items, int $total, int $perPage, int $currentPage)
    {
        $this->items       = $items;
        $this->total       = max(0, $total);
        $this->perPage     = max(1, $perPage);
        $this->currentPage = max(1, $currentPage);
        $this->lastPage    = (int)max(1, (int)ceil($this->total / $this->perPage));
        if ($this->currentPage > $this->lastPage) {
            $this->currentPage = $this->lastPage;
        }
    }

    public function hasNext(): bool
    {
        return $this->currentPage < $this->lastPage;
    }
    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }
    public function nextPage(): int
    {
        return min($this->lastPage, $this->currentPage + 1);
    }
    public function prevPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'items'        => $this->items,
            'total'        => $this->total,
            'per_page'     => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page'    => $this->lastPage,
            'has_next'     => $this->hasNext(),
            'has_prev'     => $this->hasPrev(),
        ];
    }
}
