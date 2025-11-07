<?php

declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

$devErrors = dirname(__DIR__) . '/bootstrap/dev_errors.php';
if (is_file($devErrors)) {
    require_once $devErrors;
}
