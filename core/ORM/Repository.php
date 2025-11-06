<?php

declare(strict_types=1);

namespace Ivi\Core\ORM;

/**
 * @template T of Model
 */
abstract class Repository
{
    /** @return class-string<T> */
    abstract protected function modelClass(): string;

    /** @return T[] */
    public function all(): array
    {
        $cls = $this->modelClass();
        return $cls::all();
    }

    /** @return T|null */
    public function find(int|string $id): ?Model
    {
        $cls = $this->modelClass();
        /** @var T|null $m */
        $m = $cls::find($id);
        return $m;
    }

    /** @param array<string,mixed> $data @return T */
    public function create(array $data): Model
    {
        $cls = $this->modelClass();
        /** @var T $m */
        $m = new $cls($data);
        return $m->save();
    }
}
