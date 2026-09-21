<?php

declare(strict_types=1);

namespace RoleWarden\Authorization\Contracts;

/**
 * Minimal cache the resolver needs. No TTL: entries live until the resolver
 * invalidates them on a write.
 */
interface Cache
{
    /**
     * @return array<string, mixed>|null
     */
    public function get(string $key): ?array;

    /**
     * @param array<string, mixed> $value
     */
    public function set(string $key, array $value): void;

    public function forget(string $key): void;
}
