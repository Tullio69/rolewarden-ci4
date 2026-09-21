<?php

declare(strict_types=1);

namespace RoleWarden\Adapters;

use CodeIgniter\Cache\CacheInterface;
use RoleWarden\Authorization\Contracts\Cache;

/**
 * Resolver cache on top of the CI4 cache driver configured by the host.
 */
class CiCache implements Cache
{
    public function __construct(private readonly CacheInterface $cache)
    {
    }

    public function get(string $key): ?array
    {
        $value = $this->cache->get($this->safe($key));

        return is_array($value) ? $value : null;
    }

    public function set(string $key, array $value): void
    {
        $this->cache->save($this->safe($key), $value, 0);
    }

    public function forget(string $key): void
    {
        $this->cache->delete($this->safe($key));
    }

    // CI4 reserves {}()/\@: in keys; the resolver keys only carry dots.
    private function safe(string $key): string
    {
        return str_replace('.', '_', $key);
    }
}
