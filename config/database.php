<?php

declare(strict_types=1);

/**
 * Lit la configuration DB depuis $_ENV['DATABASE_URL'] (chargé par Dotenv).
 *
 * Exemples .env :
 *   DATABASE_URL="mysql://root:@127.0.0.1:3306/ivi?charset=utf8mb4&collation=utf8mb4_unicode_ci&serverVersion=8.0"
 *   DATABASE_URL="pgsql://postgres:secret@127.0.0.1:5432/ivi"
 *   DATABASE_URL="sqlite:///var/database.sqlite"
 *   DATABASE_URL="sqlite:////%2Fabsolute%2Fpath%2Fto%2Fdb.sqlite"
 */

$databaseUrl = $_ENV['DATABASE_URL'] ?? 'mysql://root:@127.0.0.1:3306/ivi?charset=utf8mb4&collation=utf8mb4_unicode_ci';

$parts = parse_url($databaseUrl);
if ($parts === false || empty($parts['scheme'])) {
    throw new \RuntimeException("Invalid DATABASE_URL: {$databaseUrl}");
}

$driver = strtolower($parts['scheme']);
$user   = isset($parts['user']) ? urldecode($parts['user']) : '';
$pass   = isset($parts['pass']) ? urldecode($parts['pass']) : '';
$host   = isset($parts['host']) ? urldecode($parts['host']) : '127.0.0.1';
$port   = isset($parts['port']) ? (int)$parts['port'] : null;

// path commence par '/', on retire le leading slash
$dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
$dbName = urldecode($dbName);

// Query string -> array (clé en lower)
$query = [];
if (!empty($parts['query'])) {
    parse_str($parts['query'], $query);
    $query = array_change_key_case($query, CASE_LOWER);
}

// paramètres communs (avec défauts sûrs)
$charset       = (string)($query['charset']        ?? 'utf8mb4');
$collation     = (string)($query['collation']      ?? 'utf8mb4_unicode_ci');
$serverVersion = (string)($query['serverversion']  ?? '');

// options PDO de base (FQCN \PDO)
$options = [
    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
    \PDO::ATTR_EMULATE_PREPARES   => false,
];

// config standardisée pour le framework
$config = [
    'driver'        => $driver,   // mysql | pgsql | sqlite
    'host'          => $host,
    'port'          => $port,
    'database'      => $dbName,
    'username'      => $user,
    'password'      => $pass,
    'charset'       => $charset,
    'collation'     => $collation,
    'serverVersion' => $serverVersion,
    'options'       => $options,
];

// Spécificités drivers
switch ($driver) {
    case 'mysql':
        // Forcer émojis/Unicode
        $config['options'][\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset} COLLATE {$collation}";
        $config['port']     ??= 3306;
        $config['database']  = $config['database'] ?: 'ivi';
        break;

    case 'pgsql':
        $config['port']     ??= 5432;
        $config['database']  = $config['database'] ?: 'ivi';
        // charset/collation gérés côté DB en PG; on les laisse informatifs.
        break;

    case 'sqlite':
        // Pour SQLite, "database" = chemin fichier; si vide → mémoire
        if ($config['database'] === '' || $config['database'] === false) {
            $config['database'] = ':memory:';
        }
        // host/port/user/pass sans pertinence
        $config['host']     = '';
        $config['port']     = null;
        $config['username'] = '';
        $config['password'] = '';
        break;

    default:
        throw new \RuntimeException("Unsupported DATABASE_URL scheme '{$driver}'. Use mysql, pgsql or sqlite.");
}

return $config;
