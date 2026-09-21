<?php

declare(strict_types=1);

namespace RoleWarden\Tests\_support;

use RoleWarden\Authorization\Contracts\Cache;

final class ArrayCache implements Cache
{
    /** @var array<string, array<string, mixed>> */
    public array $items = [];

    public function get(string $key): ?array
    {
        return $this->items[$key] ?? null;
    }

    public function set(string $key, array $value): void
    {
        $this->items[$key] = $value;
    }

    public function forget(string $key): void
    {
        unset($this->items[$key]);
    }
}
