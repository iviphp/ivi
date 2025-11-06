<?php
require __DIR__ . '/../vendor/autoload.php';

$handler = new \Ivi\Core\Exceptions\ExceptionHandler([
    'debug' => true,
    'env' => 'local',
    'error_detail' => 'safe', // change à 'full' pour voir plus
]);

try {
    throw new \Ivi\Core\Exceptions\ORM\DatabaseConnectionException('CLI DB explosion');
} catch (\Throwable $e) {
    $resp = $handler->handle($e, null); // null => CLI
    $resp->send();
}
