<?php

use Ivi\Core\Bootstrap\App;

$app = new App(__DIR__);
$app->router->get('/', fn() => ['hello' => 'ivi.php']);
