<?php

declare(strict_types=1);

namespace RoleWarden\Tests\_support;

use RoleWarden\Authorization\Contracts\AuthorizationStore;

final class InMemoryStore implements AuthorizationStore
{
    /** @var array<int, bool> */
    public array $active = [];

    /** @var array<int, array<string, bool>> */
    public array $overrides = [];

    /** @var array<int, list<int>> */
    public array $userRoles = [];

    /** @var array<int, array{parentId: ?int, isSuperAdmin: bool, permissions: list<string>}> */
    public array $roles = [];

    public int $reads = 0;

    public function isActive(int $userId): bool
    {
        $this->reads++;

        return $this->active[$userId] ?? false;
    }

    public function userOverrides(int $userId): array
    {
        return $this->overrides[$userId] ?? [];
    }

    public function userRoleIds(int $userId): array
    {
        return $this->userRoles[$userId] ?? [];
    }

    public function role(int $roleId): ?array
    {
        return $this->roles[$roleId] ?? null;
    }

    public function childRoleIds(int $roleId): array
    {
        $ids = [];

        foreach ($this->roles as $id => $role) {
            if ($role['parentId'] === $roleId) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function userIdsWithRole(int $roleId): array
    {
        $ids = [];

        foreach ($this->userRoles as $userId => $roleIds) {
            if (in_array($roleId, $roleIds, true)) {
                $ids[] = $userId;
            }
        }

        return $ids;
    }
}
