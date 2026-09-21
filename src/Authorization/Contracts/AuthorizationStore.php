<?php

declare(strict_types=1);

namespace RoleWarden\Authorization\Contracts;

/**
 * Read-only access to the raw authorization data. The framework adapter
 * arrives with the Shield integration; the resolver only knows this contract.
 *
 * Roles that are soft-deleted must be reported as missing (null / not listed).
 */
interface AuthorizationStore
{
    public function isActive(int $userId): bool;

    /**
     * @return array<string, bool> permission slug => granted (true) or denied (false)
     */
    public function userOverrides(int $userId): array;

    /**
     * @return list<int>
     */
    public function userRoleIds(int $userId): array;

    /**
     * @return array{parentId: ?int, isSuperAdmin: bool, permissions: list<string>}|null
     *                                                                                   permissions are the ones granted directly to the role, as slugs
     */
    public function role(int $roleId): ?array;

    /**
     * @return list<int> direct children of the role
     */
    public function childRoleIds(int $roleId): array;

    /**
     * @return list<int> users the role is directly assigned to
     */
    public function userIdsWithRole(int $roleId): array;
}
