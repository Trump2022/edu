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

    // ✅ Fastknife 会调用这个方法，你必须兜住
    public function delete($key)
    {
        return $this->rm($key); // ✅ 映射到 ThinkPHP 的 rm()
    }
}
