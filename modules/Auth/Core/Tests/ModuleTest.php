<?php

namespace Modules\Auth\Core\Tests;

use PHPUnit\Framework\TestCase;
use App\Modules\ModuleContract;
use Ivi\Core\Router\Router;

final class ModuleTest extends TestCase
{
    public function testModuleImplementsContract(): void
    {
        $module = require __DIR__ . '/../Module.php';

        $this->assertInstanceOf(
            ModuleContract::class,
            $module,
            "Module must implement ModuleContract"
        );
    }

    public function testModuleNameIsCorrect(): void
    {
        $module = require __DIR__ . '/../Module.php';
        $this->assertSame('Auth/Core', $module->name());
    }

    public function testModuleRegisterLoadsConfig(): void
    {
        $module = require __DIR__ . '/../Module.php';

        // Preparation: simulate the config array
        $GLOBALS['__ivi_config'] = [];

        $module->register();

        $this->assertArrayHasKey('auth', $GLOBALS['__ivi_config']);
        $this->assertIsArray($GLOBALS['__ivi_config']['auth']);
    }

    public function testModuleBootRegistersRoutesAndViews(): void
    {
        $router = $this->getMockBuilder(Router::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['get', 'post'])
            ->getMock();

        $module = require __DIR__ . '/../Module.php';

        $GLOBALS['__ivi_migration_paths'] = [];
        $GLOBALS['__ivi_seeder_paths'] = [];

        $module->boot($router);

        $this->assertNotEmpty($GLOBALS['__ivi_migration_paths']);
        $this->assertNotEmpty($GLOBALS['__ivi_seeder_paths']);
    }
}
