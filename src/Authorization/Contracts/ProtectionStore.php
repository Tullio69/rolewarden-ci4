<?php

declare(strict_types=1);

namespace RoleWarden\Authorization\Contracts;

/**
 * Extra reads the write-side protections need. Kept apart from
 * AuthorizationStore so the resolver's contract does not grow.
 */
interface ProtectionStore
{
    public function isSystemRole(int $roleId): bool;

    public function isSystemPermission(string $slug): bool;

    /**
     * @return list<int> active users directly holding a live (not soft-deleted) super admin role
     */
    public function activeSuperAdminIds(): array;
}
