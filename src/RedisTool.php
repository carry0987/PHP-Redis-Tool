<?php
namespace carry0987\Redis;

use Exception;
use RedisException;

class RedisTool
{
    private $redis = null;
    private $retryTimes;

    public function __construct(array $config, int $retryTimes = 3)
    {
        if (!class_exists('Redis')) throw new Exception('Class Redis does not exist !');

        // Get config
        [$host, $port, $pwd, $database] = self::setConfig($config);

        // Connect to Redis
        $this->retryTimes = $retryTimes;
        try {
            $this->redis = new \Redis();
            $count = 0;
            while (!$this->redis->connect($host, $port) && $count < $this->retryTimes) {
                $count++;
            }
            if ($count >= $this->retryTimes) {
                throw new Exception('Unable to connect to Redis');
            }
            if ($pwd !== null) $this->redis->auth($pwd);
            $this->redis->select($database);
        } catch (RedisException $e) {
            throw new Exception($e->getMessage());
        }
    }

    private static function setConfig(array $config): array
    {
        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 6379;
        $pwd = $config['password'] ?? null;
        $database = $config['database'] ?? 0;

        return [$host, $port, $pwd, $database];
    }

    private function isConnected()
    {
        try {
            $status = $this->redis->ping();
            return $status == '+PONG';
        } catch (RedisException $e) {
            return false;
        }
    }

    public function getRedis()
    {
        return $this->redis;
    }

    public function setValue(string $key, $value, ?int $ttl = 86400)
    {
        if (!$this->isConnected()) return false;
        if ($ttl !== null) {
            $status = $this->redis->setex($key, $ttl, $value);
        } else {
            $status = $this->redis->set($key, $value);
        }

        return $status === true;
    }

    public function setIndex(string $indexKey, string $value) 
    {
        return $this->setValue($indexKey, $value);
    }

    public function setHashValue(string $hash, string $key, $value, ?int $ttl = 86400)
    {
        if (!$this->isConnected()) return false;
        $this->redis->multi();
        $this->redis->hSet($hash, $key, $value);
        if ($ttl !== null) { 
            $this->redis->expire($hash, $ttl);
        }
        $status = $this->redis->exec();

        return $status !== false;
    }

    public function getValue(string $key)
    {
        if (!$this->isConnected()) return false;

        return $this->redis->get($key);
    }

    public function getHashValue(string $hash, string $key)
    {
        if (!$this->isConnected()) return false;

        return $this->redis->hGet($hash, $key);
    }

    public function getAllHash(string $hash)
    {
        if (!$this->isConnected()) return false;

        return $this->redis->hGetAll($hash);
    }

    public function deleteValue(string $key)
    {
        if (!$this->isConnected()) return false;

        return (bool) $this->redis->del($key);
    }

    public function exists(string $key)
    {
        if (!$this->isConnected()) return false;

        return (bool) $this->redis->exists($key);
    }

    public function flushDatabase()
    {
        if (!$this->isConnected()) return false;

        return $this->redis->flushDb();
    }

    public function keys(string $pattern)
    {
        if (!$this->isConnected()) return array();

        $it = null; /* Initialize our iterator to NULL */

        $redis = $this->redis;
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY); /* retry when we get no keys back */

        $keys = array();
        while ($array = $redis->scan($it, $pattern)) {
            foreach ($array as $key) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}
