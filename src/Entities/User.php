<?php

declare(strict_types=1);

namespace RoleWarden\Entities;

use CodeIgniter\Shield\Authorization\AuthorizationException;
use CodeIgniter\Shield\Entities\User as ShieldUser;
use RoleWarden\Adapters\DatabaseStore;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserRoles;

/**
 * Shield user whose permission checks come from the RoleWarden resolver.
 * Group compatibility: a Shield "group" is the slug of an assigned role, so
 * inGroup/getGroups read and addGroup/removeGroup/syncGroups write the RoleWarden
 * assignments (never Shield's auth_groups_users). Slugs are case-insensitive.
 */
class User extends ShieldUser
{
    /**
     * True when at least one permission is granted, as in Shield.
     */
    public function can(string ...$permissions): bool
    {
        return service('rolewarden')->canAny((int) $this->id, array_values($permissions));
    }

    public function hasPermission(string $permission): bool
    {
        return $this->can($permission);
    }

    public function inGroup(string ...$groups): bool
    {
        return array_intersect(array_map('strtolower', $groups), $this->getGroups()) !== [];
    }

    /**
     * @return list<string>
     */
    public function getGroups(): array
    {
        return (new DatabaseStore())->userRoleSlugs((int) $this->id);
    }

    public function addGroup(string ...$groups): static
    {
        $roles = new UserRoles();

        foreach ($this->roleIds($groups) as $roleId) {
            $roles->assign((int) $this->id, $roleId);
        }

        return $this;
    }

    public function removeGroup(string ...$groups): static
    {
        $roles = new UserRoles();

        foreach ($this->roleIds($groups) as $roleId) {
            $roles->revoke((int) $this->id, $roleId);
        }

        return $this;
    }

    public function syncGroups(string ...$groups): static
    {
        $want    = $this->roleIds($groups);
        $roles   = new UserRoles();
        $current = $this->roleIds($this->getGroups());

        foreach (array_diff($current, $want) as $roleId) {
            $roles->revoke((int) $this->id, $roleId);
        }

        foreach ($want as $roleId) {
            $roles->assign((int) $this->id, $roleId);
        }

        return $this;
    }

    /**
     * Resolves every slug before any write, so an unknown one changes nothing.
     *
     * @param list<string> $slugs
     *
     * @return list<int>
     *
     * @throws AuthorizationException
     */
    private function roleIds(array $slugs): array
    {
        $ids = [];

        foreach ($slugs as $slug) {
            $role = model(RoleModel::class)->where('slug', strtolower($slug))->first();

            if ($role === null) {
                throw AuthorizationException::forUnknownGroup($slug);
            }

            $ids[(int) $role['id']] = (int) $role['id'];
        }

        return array_values($ids);
    }
}
