<?php

return [
    // Enable or disable caching (useful for local/dev)
    'enabled' => true,

    // Cache driver: 'file', 'redis', 'memcached'
    'driver' => 'redis',

    // File driver options
    'path' => sys_get_temp_dir() . '/cache',   // directory for file cache
    'prefix' => 'softadastra_',                // key prefix

    // Redis options (Predis)
    'host' => '127.0.0.1',                     // TCP host
    'port' => 6379,                            // TCP port
    'socket' => '/var/run/redis/redis.sock',   // UNIX socket (optional)
    'password' => null,                        // password if required

    // Memcached options
    'memcached_host' => '127.0.0.1',
    'memcached_port' => 11211,
];
