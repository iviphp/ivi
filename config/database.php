<?php

declare(strict_types=1);

return [
    'driver'   => 'mysql',           // mysql | pgsql | sqlite
    'host'     => '127.0.0.1',
    'port'     => 3306,
    'database' => 'ivi',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'options'  => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ],
];
