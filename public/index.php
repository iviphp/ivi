<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/early_errors.php';

session_start();
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$devErrors = dirname(__DIR__) . '/bootstrap/dev_errors.php';
if (is_file($devErrors)) {
  require_once $devErrors;
}

$app = new \Ivi\Core\Bootstrap\App(
  baseDir: dirname(__DIR__),
  resolver: fn(string $class) => new $class()
);

$app->run();
