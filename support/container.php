<?php

declare(strict_types=1);

use Ivi\Core\Container\Container;

//
// -------------------------------------------------------
//  Global DI Container + Helper Functions
// -------------------------------------------------------
//

/**
 * Global container instance.
 */
global $container;

if (!$container instanceof Container) {
    $container = new Container();
}

/**
 * Access the global DI container instance.
 *
 * Example usage:
 *   $c = container();
 *   $instance = $c->make(SomeClass::class);
 *
 * @return Container
 */
if (!function_exists('container')) {
    function container(): Container
    {
        global $container;

        if (!$container instanceof Container) {
            $container = new Container();
        }

        return $container;
    }
}

/**
 * Shortcut to create an instance using auto-wiring from the container.
 *
 * Example usage:
 *   $userService = make(UserService::class);
 *
 * @param string $class Fully-qualified class name
 * @return mixed
 */
if (!function_exists('make')) {
    function make(string $class): mixed
    {
        return container()->make($class);
    }
}
