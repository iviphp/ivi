<?php

namespace Modules\Auth\Core\Tests\Fakes;

class FakeRouter
{
    public array $registered = [];

    public function get(string $path, $handler): void
    {
        $this->registered[] = ['GET', $path, $handler];
    }

    public function post(string $path, $handler): void
    {
        $this->registered[] = ['POST', $path, $handler];
    }
}
