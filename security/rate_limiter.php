<?php
/**
 * security/rate_limiter.php
 * RateLimiter — file-based or Redis-based attempt tracking.
 * Usage:
 *   $rl = new RateLimiter();
 *   if (!$rl->check('login:' . $ip, 10, 900)) { // 10 attempts per 15 min
 *       die('Too many attempts');
 *   }
 *   $rl->increment('login:' . $ip, 900);
 */
declare(strict_types=1);

class RateLimiter
{
    private string $storageDir;
    private bool   $useRedis = false;
    private ?object $redis   = null;

    public function __construct(string $storageDir = '')
    {
        $this->storageDir = $storageDir ?: (sys_get_temp_dir() . '/ecom_ratelimit');
        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0750, true);
        }

        // Try Redis if predis is available
        if (class_exists('Predis\\Client')) {
            try {
                $params = [
                    'scheme' => 'tcp',
                    'host'   => getenv('REDIS_HOST') ?: '127.0.0.1',
                    'port'   => (int)(getenv('REDIS_PORT') ?: 6379),
                ];
                $client = new \Predis\Client($params);
                $client->ping();
                $this->redis    = $client;
                $this->useRedis = true;
            } catch (Throwable) {
                $this->useRedis = false;
            }
        }
    }

    /**
     * Check if the key is under the limit.
     * Returns true if under limit (allowed), false if limit exceeded.
     */
    public function check(string $key, int $maxAttempts, int $windowSeconds): bool
    {
        return $this->getCount($key) < $maxAttempts;
    }

    /**
     * Increment attempt count for the key.
     */
    public function increment(string $key, int $windowSeconds): void
    {
        if ($this->useRedis) {
            try {
                $rkey = 'rl:' . $key;
                $this->redis->incr($rkey);
                $this->redis->expire($rkey, $windowSeconds);
                return;
            } catch (Throwable) {}
        }

        // File-based fallback
        $data  = $this->readFile($key);
        $now   = time();

        if ($data === null || $data['expires'] <= $now) {
            $data = ['count' => 1, 'expires' => $now + $windowSeconds];
        } else {
            $data['count']++;
        }

        $this->writeFile($key, $data);
    }

    /**
     * Reset attempt counter for the key.
     */
    public function reset(string $key): void
    {
        if ($this->useRedis) {
            try {
                $this->redis->del(['rl:' . $key]);
                return;
            } catch (Throwable) {}
        }
        @unlink($this->filePath($key));
    }

    private function getCount(string $key): int
    {
        if ($this->useRedis) {
            try {
                return (int)($this->redis->get('rl:' . $key) ?? 0);
            } catch (Throwable) {}
        }

        $data = $this->readFile($key);
        if ($data === null || $data['expires'] <= time()) return 0;
        return (int)$data['count'];
    }

    private function filePath(string $key): string
    {
        return $this->storageDir . '/' . sha1($key) . '.rl';
    }

    private function readFile(string $key): ?array
    {
        $file = $this->filePath($key);
        if (!is_file($file)) return null;
        try {
            $raw  = file_get_contents($file);
            $data = $raw !== false ? json_decode($raw, true) : null;
            return is_array($data) ? $data : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function writeFile(string $key, array $data): void
    {
        @file_put_contents($this->filePath($key), json_encode($data), LOCK_EX);
    }
}
