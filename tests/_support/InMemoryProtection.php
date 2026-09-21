<?php

declare(strict_types=1);

namespace RoleWarden\Tests\_support;

use RoleWarden\Authorization\Contracts\ProtectionStore;

final class InMemoryProtection implements ProtectionStore
{
    /** @var list<int> */
    public array $systemRoles = [];

    /** @var list<string> */
    public array $systemPermissions = [];

    public function __construct(private readonly InMemoryStore $store)
    {
    }

    public function isSystemRole(int $roleId): bool
    {
        return in_array($roleId, $this->systemRoles, true);
    }

    public function isSystemPermission(string $slug): bool
    {
        return in_array($slug, $this->systemPermissions, true);
    }

    public function activeSuperAdminIds(): array
    {
        $ids = [];

        foreach ($this->store->userRoles as $userId => $roleIds) {
            if (! ($this->store->active[$userId] ?? false)) {
                continue;
            }

            foreach ($roleIds as $roleId) {
                if ($this->store->roles[$roleId]['isSuperAdmin'] ?? false) {
                    $ids[] = $userId;
                    break;
                }
            }
        }

        return $ids;
    }
}
