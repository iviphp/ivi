<?php

namespace Modules\Auth\Core\Tests;

use Modules\Auth\Core\Tests\Fakes\FakeRouter;
use PHPUnit\Framework\TestCase;

final class RoutesTest extends TestCase
{
    public function testRoutesFileExists(): void
    {
        $this->assertFileExists(__DIR__ . '/../routes/web.php');
    }

    public function testRoutesCanBeLoaded(): void
    {
        $router = new FakeRouter();

        require __DIR__ . '/../routes/web.php';

        $this->assertNotEmpty(
            $router->registered,
            "Routes file should register at least one route"
        );
    }
}
