<?php

declare(strict_types=1);

namespace Ivi\Core\Cache;

use Ivi\Core\Config\Config;
use Predis\Client as PredisClient;

/**
 * Class Cache
 *
 * Singleton service for caching with multiple drivers.
 *
 * Supported drivers:
 * - file (default)
 * - redis (Predis only)
 *
 * Features:
 * - TTL configurable per entry
 * - Key prefix configurable
 * - Can be disabled via config (useful for local/dev)
 * - Automatically removes large Redis keys (Predis)
 */
final class Cache
{
    /** @var ?self Singleton instance */
    private static ?self $instance = null;

    /** @var bool Whether caching is enabled */
    private bool $enabled;

    /** @var string Cache driver ('file', 'redis') */
    private string $driver;

    /** @var string Path for file cache */
    private string $path;

    /** @var string Key prefix */
    private string $prefix;

    /** @var PredisClient|null Redis client instance */
    private mixed $redis = null;

    /** @var mixed|null Memcached client instance (disabled by default) */
    private mixed $memcached = null;

    /** @var int Maximum key size for Predis (bytes) */
    private int $maxKeySizeBytes = 400_000;

    /**
     * Private constructor for singleton pattern.
     *
     * Reads config and initializes the cache driver.
     * Redis and Memcached are disabled by default.
     */
    private function __construct()
    {
        $cfg = Config::get('cache', []);

        $this->enabled = $cfg['enabled'] ?? true;
        if (!$this->enabled) return;

        $this->driver = $cfg['driver'] ?? 'file';
        $this->path   = $cfg['path'] ?? sys_get_temp_dir() . '/cache';
        $this->prefix = $cfg['prefix'] ?? 'ivi_';

        // --- File driver setup
        if ($this->driver === 'file') {
            if (!is_dir($this->path) && !@mkdir($this->path, 0777, true) && !is_dir($this->path)) {
                throw new \RuntimeException("Unable to create cache directory: {$this->path}");
            }
        }

        // --- Redis Predis setup
        if ($this->driver === 'redis' && class_exists(PredisClient::class)) {
            $socket = $cfg['socket'] ?? '/var/run/redis/redis.sock';
            $host   = $cfg['host'] ?? '127.0.0.1';
            $port   = $cfg['port'] ?? 6379;

            if (file_exists($socket)) {
                $this->redis = new PredisClient(['scheme' => 'unix', 'path' => $socket]);
            } else {
                $this->redis = new PredisClient(['scheme' => 'tcp', 'host' => $host, 'port' => $port]);
            }

            if (!empty($cfg['password'])) {
                $this->redis->auth($cfg['password']);
            }

            $this->deleteLargeKeys();
        }
    }

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate the actual cache key using prefix and hash.
     *
     * @param string $key
     * @return string
     */
    private function getKey(string $key): string
    {
        return $this->prefix . md5($key);
    }

    /**
     * Store a value in cache.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds
     */
    public function set(string $key, mixed $value, int $ttl = 3600): void
    {
        if (!$this->enabled) return;

        $k = $this->getKey($key);

        switch ($this->driver) {
            case 'file':
                $data = ['value' => $value, 'expires_at' => time() + $ttl];
                @file_put_contents($this->path . '/' . $k . '.cache', serialize($data));
                break;

            case 'redis':
                if ($this->redis instanceof PredisClient) {
                    $encoded = json_encode($value);
                    if (strlen($encoded) <= $this->maxKeySizeBytes) {
                        $this->redis->setex($k, $ttl, $encoded);
                    }
                }
                break;
        }
    }

    /**
     * Retrieve a value from cache.
     *
     * @param string $key
     * @param mixed $default Default value if key is not found
     * @return mixed
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->enabled) return $default;

        $k = $this->getKey($key);

        switch ($this->driver) {
            case 'file':
                $file = $this->path . '/' . $k . '.cache';
                if (!is_file($file)) return $default;
                $data = @unserialize(file_get_contents($file));
                if (!isset($data['value'], $data['expires_at']) || $data['expires_at'] < time()) {
                    @unlink($file);
                    return $default;
                }
                return $data['value'];

            case 'redis':
                if ($this->redis instanceof PredisClient) {
                    $value = $this->redis->get($k);
                    return $value !== null ? json_decode($value, true) : $default;
                }
                break;
        }

        return $default;
    }

    /**
     * Delete a cache key.
     *
     * @param string $key
     */
    public function delete(string $key): void
    {
        if (!$this->enabled) return;

        $k = $this->getKey($key);

        switch ($this->driver) {
            case 'file':
                $file = $this->path . '/' . $k . '.cache';
                if (is_file($file)) @unlink($file);
                break;

            case 'redis':
                if ($this->redis instanceof PredisClient) {
                    $this->redis->del([$k]);
                }
                break;
        }
    }

    /**
     * Clear the entire cache.
     */
    public function clear(): void
    {
        if (!$this->enabled) return;

        switch ($this->driver) {
            case 'file':
                foreach (glob($this->path . '/' . $this->prefix . '*.cache') as $file) {
                    @unlink($file);
                }
                break;

            case 'redis':
                if ($this->redis instanceof PredisClient) {
                    $this->redis->flushAll();
                }
                break;
        }
    }

    /**
     * Delete Redis keys that exceed max size (Predis only).
     */
    public function deleteLargeKeys(): void
    {
        if (!$this->enabled || !$this->redis instanceof PredisClient) return;

        $keys = $this->redis->keys('*');
        foreach ($keys as $key) {
            $raw = $this->redis->get($key);
            if ($raw && strlen($raw) > $this->maxKeySizeBytes) {
                $this->redis->del([$key]);
            }
        }
    }

    /**
     * Remember pattern: get from cache or compute and store.
     *
     * @param string $key
     * @param int $ttl
     * @param callable $callback
     * @return mixed
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $value = $this->get($key);
        if ($value !== null) return $value;

        $value = $callback();
        $this->set($key, $value, $ttl);
        return $value;
    }

    /**
     * Delete keys by pattern (Predis only).
     *
     * @param string $pattern
     * @param int $count
     * @return int Number of keys deleted
     */
    public function deleteByPattern(string $pattern, int $count = 1000): int
    {
        if (!$this->enabled || !$this->redis instanceof PredisClient) return 0;

        $cursor = '0';
        $deleted = 0;

        do {
            [$cursor, $keys] = $this->redis->scan($cursor, 'MATCH', $pattern, 'COUNT', $count);
            if ($keys) {
                try {
                    $this->redis->executeRaw(array_merge(['UNLINK'], $keys));
                    $deleted += count($keys);
                } catch (\Throwable $e) {
                    foreach (array_chunk($keys, 1024) as $chunk) {
                        try {
                            $deleted += (int)$this->redis->del($chunk);
                        } catch (\Throwable $e2) {
                        }
                    }
                }
            }
        } while ($cursor !== '0');

        return $deleted;
    }

    /**
     * Force flush Redis DB (Predis only).
     */
    public function flushDBForce(): void
    {
        if (!$this->enabled || !$this->redis instanceof PredisClient) return;

        try {
            $this->redis->executeRaw(['FLUSHDB', 'ASYNC']);
        } catch (\Throwable $e) {
            $this->redis->flushdb();
        }
    }

    /**
     * Force flush all Redis data (Predis only).
     */
    public function flushAllForce(): void
    {
        if (!$this->enabled || !$this->redis instanceof PredisClient) return;

        try {
            $this->redis->executeRaw(['FLUSHALL', 'ASYNC']);
        } catch (\Throwable $e) {
            $this->redis->flushall();
        }
    }

    /**
     * List all cache keys (file or Predis only).
     *
     * @return array
     */
    public function listKeys(): array
    {
        switch ($this->driver) {
            case 'file':
                $files = scandir($this->path);
                // On enlève "." et ".." et on retourne les noms de fichiers
                return array_values(array_filter($files, fn($f) => !in_array($f, ['.', '..'])));

            case 'redis':
                if ($this->redis instanceof PredisClient) {
                    return $this->redis->keys($this->prefix . '*');
                }
                return [];

            case 'memcached':
                return []; // Memcached: impossible à lister proprement
        }

        return [];
    }
}
