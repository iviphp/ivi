<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

/**
 * Class Pagination
 *
 * @package Ivi\Core\ORM
 *
 * @brief Lightweight Pagination Data Transfer Object (DTO) for the Ivi.php ORM layer.
 *
 * The `Pagination` class encapsulates paginated result data for collections such as
 * database query results. It provides metadata like total count, current page,
 * items per page, and navigation helpers for next/previous pages.
 *
 * Designed for simplicity and interoperability with both HTML views and JSON APIs,
 * this class can be directly serialized using its `toArray()` method.
 *
 * ### Core Responsibilities
 * - Store paginated result sets in an immutable data structure.
 * - Provide helper methods for pagination navigation (`hasNext()`, `hasPrev()`, etc.).
 * - Ensure valid pagination boundaries and computed page counts.
 * - Enable direct conversion to array or JSON format.
 *
 * ### Example Usage
 * ```php
 * use Ivi\Core\ORM\Pagination;
 *
 * // Inside a controller:
 * $rows = User::query()
 *     ->orderBy('id DESC')
 *     ->limit(10)
 *     ->offset(20)
 *     ->get();
 *
 * $total = User::query()
 *     ->select('COUNT(*) as c')
 *     ->first()['c'] ?? 0;
 *
 * $page = new Pagination($rows, $total, 10, 3);
 *
 * echo json_encode($page->toArray(), JSON_PRETTY_PRINT);
 * ```
 *
 * ### Output Example
 * ```json
 * {
 *   "items": [ ... ],
 *   "total": 42,
 *   "per_page": 10,
 *   "current_page": 3,
 *   "last_page": 5,
 *   "has_next": true,
 *   "has_prev": true
 * }
 * ```
 *
 * ### Design Notes
 * - Automatic clamping ensures `currentPage` never exceeds `lastPage`.
 * - Defensive defaults guarantee safe values (`min(1, ...)` semantics).
 * - Ideal for pairing with query builders and REST API responses.
 *
 * @template T
 */
final class Pagination
{
    /** @var array<int, T> The paginated items for the current page. */
    public array $items;

    /** @var int Total number of items across all pages. */
    public int $total;

    /** @var int Number of items per page. */
    public int $perPage;

    /** @var int Current page number (1-indexed). */
    public int $currentPage;

    /** @var int Computed number of the last page. */
    public int $lastPage;

    /**
     * Create a new Pagination DTO instance.
     *
     * @param array<int, T> $items       Items on the current page.
     * @param int           $total       Total number of items.
     * @param int           $perPage     Number of items per page.
     * @param int           $currentPage Current page number (1-indexed).
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

    /**
     * Determine whether there is a next page available.
     */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    /**
     * Determine whether there is a previous page available.
     */
    public function hasPrev(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Get the next page number.
     */
    public function nextPage(): int
    {
        return min($this->lastPage, $this->currentPage + 1);
    }

    /**
     * Get the previous page number.
     */
    public function prevPage(): int
    {
        return max(1, $this->currentPage - 1);
    }

    /**
     * Convert pagination data to an associative array.
     *
     * @return array<string,mixed>
     */
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
