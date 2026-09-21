<?php

declare(strict_types=1);

namespace RoleWarden\Authorization;

use RoleWarden\Authorization\Contracts\AuthorizationStore;
use RoleWarden\Authorization\Contracts\ProtectionStore;

/**
 * Write-side invariants, framework-free like the resolver. Every method runs
 * before the write and throws ProtectionException to refuse it.
 */
class Guard
{
    public function __construct(
        private readonly AuthorizationStore $store,
        private readonly ProtectionStore $protection,
    ) {
    }

    /**
     * Refuses a parent that does not exist or that would close a loop through the role.
     */
    public function assertParent(int $roleId, ?int $parentId): void
    {
        if ($parentId === null) {
            return;
        }

        if ($this->store->role($parentId) === null) {
            throw new ProtectionException(ProtectionException::PARENT_MISSING);
        }

        $seen = [];

        // A loop already in the data stops at the first repeat.
        for ($id = $parentId; $id !== null && ! isset($seen[$id]); $id = $role['parentId']) {
            if ($id === $roleId) {
                throw new ProtectionException(ProtectionException::HIERARCHY_CYCLE);
            }
            $seen[$id] = true;
            $role = $this->store->role($id);

            if ($role === null) {
                break;
            }
        }
    }

    public function assertRoleDeletable(int $roleId): void
    {
        if ($this->protection->isSystemRole($roleId)) {
            throw new ProtectionException(ProtectionException::SYSTEM_RECORD);
        }

        // Decided by the author: move or delete the children first, never orphan them silently.
        if ($this->store->childRoleIds($roleId) !== []) {
            throw new ProtectionException(ProtectionException::ROLE_HAS_CHILDREN);
        }

        $this->assertRoleStopsBeingSuper($roleId);
    }

    public function assertPermissionDeletable(string $slug): void
    {
        if ($this->protection->isSystemPermission($slug)) {
            throw new ProtectionException(ProtectionException::SYSTEM_RECORD);
        }
    }

    /**
     * Call when the super admin flag of a role is being saved.
     */
    public function assertSuperAdminFlag(int $roleId, bool $isSuperAdmin): void
    {
        if (! $isSuperAdmin) {
            $this->assertRoleStopsBeingSuper($roleId);
        }
    }

    public function assertRoleUnassignable(int $userId, int $roleId): void
    {
        if (($this->store->role($roleId)['isSuperAdmin'] ?? false) && ! $this->keepsSuperAdmin($userId, $roleId)) {
            $this->assertSuperAdminRemains([$userId]);
        }
    }

    /**
     * For deleting or deactivating a user.
     */
    public function assertUserRemovable(int $userId): void
    {
        $this->assertSuperAdminRemains([$userId]);
    }

    private function assertRoleStopsBeingSuper(int $roleId): void
    {
        if (! ($this->store->role($roleId)['isSuperAdmin'] ?? false)) {
            return;
        }

        $lost = array_filter(
            $this->store->userIdsWithRole($roleId),
            fn (int $userId): bool => ! $this->keepsSuperAdmin($userId, $roleId),
        );

        $this->assertSuperAdminRemains(array_values($lost));
    }

    private function keepsSuperAdmin(int $userId, int $exceptRoleId): bool
    {
        foreach ($this->store->userRoleIds($userId) as $id) {
            if ($id !== $exceptRoleId && ($this->store->role($id)['isSuperAdmin'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<int> $lostUserIds users that would stop being super admin
     */
    private function assertSuperAdminRemains(array $lostUserIds): void
    {
        $active = $this->protection->activeSuperAdminIds();

        if (array_intersect($lostUserIds, $active) !== [] && array_diff($active, $lostUserIds) === []) {
            throw new ProtectionException(ProtectionException::LAST_SUPER_ADMIN);
        }
    }
}
