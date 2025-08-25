<?php
namespace App\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

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

    public function deleteByPattern(string $pattern): void
    {
        // If using TagAwareCacheInterface, we can use tags for better cache management
        if ($this->cache instanceof TagAwareCacheInterface) {
            // For pattern-based deletion, we'll need to implement a different approach
            // Since pattern matching is not directly supported by PSR-6
            $this->cache->invalidateTags(['missions']);
        } else {
            // Alternative approach: clear all cache if pattern deletion is not supported
            $this->cache->clear();
        }
    }

    public function invalidateTag(string $tag): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $this->cache->invalidateTags([$tag]);
        }
    }

    public function setValueWithTags(string $key, mixed $value, array $tags = [], int $ttl = 3600): void
    {
        if ($this->cache instanceof TagAwareCacheInterface) {
            $item = $this->cache->getItem($key);
            $item->set($value);
            $item->expiresAfter($ttl);
            $item->tag($tags);
            $this->cache->save($item);
        } else {
            $this->setValue($key, $value, $ttl);
        }
    }
}
