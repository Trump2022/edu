<?php

namespace service;

use think\cache\driver\Redis;

class FastknifeCacheAdapter
{
    protected $redis;

    public function __construct()
    {
        $config = config('cache.redis');
        $this->redis = (new Redis($config))->handler(); // 原生 Redis 实例
    }

    public function get($key, $default = null)
    {
        return $this->redis->get($key) ?? $default;
    }

    public function set($key, $value, $ttl = null)
    {
        return $this->redis->set($key, $value, $ttl);
    }

    public function rm($key)
    {
        return $this->redis->del($key); // ✅ 使用 del()，不触发 deprecated
    }

    public function has($key)
    {
        return $this->redis->exists($key);
    }

    public function delete($key)
    {
        return $this->rm($key); // ✅ 映射 delete → rm → del()
    }
}
