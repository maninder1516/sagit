<?php
namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;

class RedisService
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    public function setValue(string $key, mixed $value, int $ttl = 3600): void
    {
        $item = $this->cache->getItem($key);
        $item->set($value);
        $item->expiresAfter($ttl);
        $this->cache->save($item);
    }

    public function getValue(string $key): mixed
    {
        $item = $this->cache->getItem($key);
        return $item->isHit() ? $item->get() : null;
    }
}
