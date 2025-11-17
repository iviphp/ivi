<?php

declare(strict_types=1);

return [
    'driver' => 'smtp',
    'host' => $_ENV['MAIL_HOST'] ?? 'localhost',
    'port' => $_ENV['MAIL_PORT'] ?? 25,
    'username' => $_ENV['MAIL_USERNAME'] ?? null,
    'password' => $_ENV['MAIL_PASSWORD'] ?? null,
    'encryption' => $_ENV['MAIL_ENCRYPTION'] ?? null,
    'from' => [
        'address' => $_ENV['MAIL_FROM_ADDRESS'] ?? 'no-reply@example.com',
        'name' => $_ENV['MAIL_FROM_NAME'] ?? 'App',
    ],
];
