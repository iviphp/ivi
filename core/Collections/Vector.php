<?php

namespace Ivi\Core\Collections;

/**
 * @template T
 * @implements \ArrayAccess<int,T>
 * @implements \IteratorAggregate<int,T>
 */
final class Vector implements \ArrayAccess, \IteratorAggregate, \Countable
{
    /** @var array<int,mixed> */
    private array $data = [];

    public function __construct(iterable $items = [])
    {
        foreach ($items as $v) {
            $this->data[] = $v;
        }
    }

    /** @param T $value */
    public function push(mixed $value): void
    {
        $this->data[] = $value;
    }

    /** @return T */
    public function get(int $i): mixed
    {
        return $this->data[$i];
    }

    /** @param T $value */
    public function set(int $i, mixed $value): void
    {
        $this->data[$i] = $value;
    }

    public function pop(): mixed
    {
        return array_pop($this->data);
    }       // @return T|null
    public function clear(): void
    {
        $this->data = [];
    }
    public function count(): int
    {
        return \count($this->data);
    }

    // ArrayAccess
    public function offsetExists(mixed $o): bool
    {
        return isset($this->data[$o]);
    }
    public function offsetGet(mixed $o): mixed
    {
        return $this->data[$o];
    }         // @return T
    public function offsetSet(mixed $o, mixed $v): void
    {
        if ($o === null) $this->data[] = $v;
        else $this->data[$o] = $v;
    }
    public function offsetUnset(mixed $o): void
    {
        unset($this->data[$o]);
    }

    // IteratorAggregate
    public function getIterator(): \Traversable
    {
        yield from $this->data;
    }
}
