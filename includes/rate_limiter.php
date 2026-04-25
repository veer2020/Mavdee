<?php
/**
 * includes/rate_limiter.php
 * RateLimiter — File-based sliding window rate limiter with optional Redis support.
 */
declare(strict_types=1);

class RateLimiter
{
    private mixed $redis = null;
    private bool  $useRedis;

    public function __construct()
    {
        $this->useRedis = class_exists('Redis') && (bool)getenv('REDIS_ENABLED');

        if ($this->useRedis) {
            $this->redis = new \Redis();
            $this->redis->connect(
                getenv('REDIS_HOST') ?: '127.0.0.1',
                (int)(getenv('REDIS_PORT') ?: 6379)
            );
        }
    }

    /**
     * Check if the given key is within its rate limit.
     */
    public function check(string $key, int $limit, int $window): bool
    {
        if ($this->useRedis) {
            return (int)$this->redis->get($key) < $limit;
        }

        $data = $this->readFile($key);
        if ($data['reset'] < time()) {
            $data = ['count' => 0, 'reset' => time() + $window];
        }

        return $data['count'] < $limit;
    }

    /**
     * Increment the counter for the given key.
     */
    public function increment(string $key, int $window): void
    {
        if ($this->useRedis) {
            $this->redis->incr($key);
            $this->redis->expire($key, $window);
            return;
        }

        $data = $this->readFile($key);
        if ($data['reset'] < time()) {
            $data = ['count' => 0, 'reset' => time() + $window];
        }
        $data['count']++;
        $this->writeFile($key, $data);
    }

    private function readFile(string $key): array
    {
        $file = $this->filePath($key);
        $raw  = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : null;
        return $data ?: ['count' => 0, 'reset' => 0];
    }

    private function writeFile(string $key, array $data): void
    {
        file_put_contents($this->filePath($key), json_encode($data), LOCK_EX);
    }

    private function filePath(string $key): string
    {
        return sys_get_temp_dir() . '/rl_' . md5($key);
    }
}
