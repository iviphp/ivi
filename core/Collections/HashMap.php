<?php

namespace Ivi\Core\Collections;

/**
 * @template K of array-key
 * @template V
 * @implements \IteratorAggregate<K,V>
 */
final class HashMap implements \IteratorAggregate, \Countable
{
    /** @var array<K,V> */
    private array $map = [];

    /** @param iterable<K,V> $items */
    public function __construct(iterable $items = [])
    {
        foreach ($items as $k => $v) {
            $this->map[$k] = $v;
        }
    }

    /** @param K $key @param V $value */
    public function put(int|string $key, mixed $value): void
    {
        $this->map[$key] = $value;
    }

    /** @param K $key @return V|null */
    public function get(int|string $key): mixed
    {
        return $this->map[$key] ?? null;
    }

    /** @param K $key */
    public function has(int|string $key): bool
    {
        return array_key_exists($key, $this->map);
    }

    /** @param K $key */
    public function remove(int|string $key): void
    {
        unset($this->map[$key]);
    }

    /** @return \Traversable<K,V> */
    public function getIterator(): \Traversable
    {
        yield from $this->map;
    }

    public function count(): int
    {
        return \count($this->map);
    }
    public function clear(): void
    {
        $this->map = [];
    }
    /** @return array<K,V> */ public function toArray(): array
    {
        return $this->map;
    }
}
