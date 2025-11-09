<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/early_errors.php';
require_once dirname(__DIR__) . '/vendor/autoload.php';
$modulesAutoload = __DIR__ . '/../support/modules_autoload.php';
if (is_file($modulesAutoload)) {
    require_once $modulesAutoload;
}

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/session.php';

use Ivi\Core\Bootstrap\App;

// Lancement de l’application
$app = new App(
    baseDir: dirname(__DIR__),
    resolver: fn(string $class) => new $class()
);

$app->run();
