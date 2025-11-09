<?php
if (!function_exists('config')) {
    /**
     * @param string|null $key
     * @param mixed $default
     * @return mixed|\Ivi\Core\Config
     */
    function config(?string $key = null, $default = null) {}
}

if (!function_exists('view')) {
    /**
     * @return \Ivi\Core\View\View
     */
    function view() {}
}

if (!function_exists('migrations')) {
    /**
     * @return \Ivi\Core\Migrations\MigrationManager
     */
    function migrations() {}
}

if (!function_exists('container')) {
    /**
     * @return \Ivi\Core\Container\Container
     */
    function container() {}
}
