<?php
namespace service;

use think\Cache;

class FastknifeCacheAdapter
{
    public function get($key, $default = null)
    {
        return Cache::get($key) ?? $default;
    }

    public function set($key, $value, $ttl = null)
    {
        return Cache::set($key, $value, $ttl);
    }

    public function rm($key)
    {
        return Cache::rm($key);
    }

    public function has($key)
    {
        return Cache::has($key);
    }

    public function delete($key)
    {
        $originalLevel = error_reporting();
        error_reporting($originalLevel & ~E_DEPRECATED);

        try {
            // ✅ 只 suppress 这一行
            $result = \think\Cache::rm($key);
        } finally {
            error_reporting($originalLevel);
        }

        return $result;
    }

}
