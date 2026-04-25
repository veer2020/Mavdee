<?php

/**
 * includes/cache.php
 * Unified cache layer: Redis (via predis/predis) with FileCache fallback.
 * Usage:  $cache = Cache::instance();
 *         $cache->get('key');
 *         $cache->set('key', $value, 3600);
 *         $cache->delete('key');
 */

declare(strict_types=1);

// ── FileCache fallback (no external deps) ─────────────────────────────────────
class FileCache
{
    private string $dir;

    public function __construct(string $dir = '')
    {
        $this->dir = $dir ?: (sys_get_temp_dir() . '/ecom_cache');
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0750, true);
        }
    }

    private function path(string $key): string
    {
        return $this->dir . '/' . sha1($key) . '.cache';
    }

    public function get(string $key): mixed
    {
        $file = $this->path($key);
        if (!is_file($file)) return null;
        try {
            $raw  = file_get_contents($file);
            $data = $raw !== false ? json_decode($raw, true) : null;
            if (!is_array($data) || $data['ttl'] < time()) {
                @unlink($file);
                return null;
            }
            return $data['val'];
        } catch (Throwable $e) {
            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        $payload = json_encode(['val' => $value, 'ttl' => time() + $ttl]);
        return @file_put_contents($this->path($key), $payload, LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        return @unlink($this->path($key));
    }

    public function flush(): bool
    {
        $ok = true;
        foreach (glob($this->dir . '/*.cache') ?: [] as $f) {
            $ok = @unlink($f) && $ok;
        }
        return $ok;
    }
}

// ── Main Cache class ──────────────────────────────────────────────────────────
class Cache
{
    private const PREFIX = 'ecom:';

    /** @var Cache|null */
    private static ?Cache $instance = null;

    /** @var object Either a Predis\Client or FileCache */
    private object $backend;

    /** @var bool */
    private bool $usingRedis = false;

    private function __construct()
    {
        $this->backend = $this->connect();
    }

    private function connect(): object
    {
        $enabled = filter_var(getenv('REDIS_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return new FileCache();
        }

        // Attempt Redis via predis/predis (if installed via Composer)
        if (class_exists('Predis\\Client')) {
            try {
                $params = [
                    'scheme' => 'tcp',
                    'host'   => getenv('REDIS_HOST') ?: '127.0.0.1',
                    'port'   => (int)(getenv('REDIS_PORT') ?: 6379),
                ];
                $options = [];
                $password = getenv('REDIS_PASSWORD') ?: '';
                if ($password !== '') {
                    $options['parameters']['password'] = $password;
                }

                $client = new \Predis\Client($params, $options);
                $client->ping(); // Test connection
                $this->usingRedis = true;
                return $client;
            } catch (Throwable $e) {
                // Fall through to FileCache
            }
        }

        return new FileCache();
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function prefixed(string $key): string
    {
        return self::PREFIX . $key;
    }

    public function get(string $key): mixed
    {
        try {
            if ($this->usingRedis) {
                $raw = $this->backend->get($this->prefixed($key));
                if ($raw === null) return null;
                return json_decode($raw, true);
            }
            return $this->backend->get($this->prefixed($key));
        } catch (Throwable $e) {
            return null;
        }
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        try {
            if ($this->usingRedis) {
                $this->backend->setex($this->prefixed($key), $ttl, json_encode($value));
                return true;
            }
            return $this->backend->set($this->prefixed($key), $value, $ttl);
        } catch (Throwable $e) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            if ($this->usingRedis) {
                $this->backend->del([$this->prefixed($key)]);
                return true;
            }
            return $this->backend->delete($this->prefixed($key));
        } catch (Throwable $e) {
            return false;
        }
    }

    public function flush(): bool
    {
        try {
            if ($this->usingRedis) {
                $this->backend->flushdb();
                return true;
            }
            return $this->backend->flush();
        } catch (Throwable $e) {
            return false;
        }
    }

    public function isUsingRedis(): bool
    {
        return $this->usingRedis;
    }
}
