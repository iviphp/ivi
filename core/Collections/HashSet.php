<?php

namespace Ivi\Core\Collections;

/**
 * @template T of array-key
 * @implements \IteratorAggregate<int,T>
 */
final class HashSet implements \IteratorAggregate, \Countable
{
    /** @var array<T,bool> */
    private array $set = [];

    /** @param iterable<T> $items */
    public function __construct(iterable $items = [])
    {
        foreach ($items as $v) {
            $this->set[$v] = true;
        }
    }

    /** @param T $value */
    public function add(int|string $value): void
    {
        $this->set[$value] = true;
    }

    /** @param T $value */
    public function has(int|string $value): bool
    {
        return isset($this->set[$value]);
    }

    /** @param T $value */
    public function remove(int|string $value): void
    {
        unset($this->set[$value]);
    }

    public function count(): int
    {
        return \count($this->set);
    }

    /** @return \Traversable<int,T> */
    public function getIterator(): \Traversable
    {
        foreach ($this->set as $k => $_) yield $k;
    }
}
