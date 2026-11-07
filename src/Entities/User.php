<?php

declare(strict_types=1);

namespace RoleWarden\Entities;

use CodeIgniter\Shield\Authorization\AuthorizationException;
use CodeIgniter\Shield\Entities\User as ShieldUser;
use RoleWarden\Adapters\DatabaseStore;
use RoleWarden\Install\Readiness;
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
        $ids = $this->roleIds($groups);

        return $this->atomically(function (UserRoles $roles) use ($ids): void {
            foreach ($ids as $roleId) {
                $roles->assign((int) $this->id, $roleId);
            }
        });
    }

    /**
     * Unknown slugs are ignored, as in Shield: there is nothing to remove.
     */
    public function removeGroup(string ...$groups): static
    {
        $ids = $this->roleIds($groups, true);

        return $this->atomically(function (UserRoles $roles) use ($ids): void {
            foreach ($ids as $roleId) {
                $roles->revoke((int) $this->id, $roleId);
            }
        });
    }

    /**
     * Assigns first, then revokes the rest, so swapping between two super admin roles passes the
     * last-super-admin protection; all in one transaction so a refusal leaves the roles untouched.
     */
    public function syncGroups(string ...$groups): static
    {
        $want    = $this->roleIds($groups);
        $extra   = array_diff($this->roleIds($this->getGroups()), $want);

        return $this->atomically(function (UserRoles $roles) use ($want, $extra): void {
            foreach ($want as $roleId) {
                $roles->assign((int) $this->id, $roleId);
            }

            foreach ($extra as $roleId) {
                $roles->revoke((int) $this->id, $roleId);
            }
        });
    }

    /**
     * @param callable(UserRoles): void $writes
     */
    private function atomically(callable $writes): static
    {
        $db = db_connect();
        $db->transBegin();

        try {
            $writes(new UserRoles());
            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            // The resolver cache may have been dropped mid-way; drop it again after the rollback.
            service('rolewarden')->forgetUser((int) $this->id);

            throw $e;
        }

        // Dropped mid-way too, but a concurrent request may have cached the pre-commit roles since.
        service('rolewarden')->forgetUser((int) $this->id);

        return $this;
    }

    /**
     * Resolves every slug before any write, so an unknown one changes nothing,
     * or, with $skipUnknown, is left out.
     *
     * @param list<string> $slugs
     *
     * @return list<int>
     *
     * @throws AuthorizationException
     */
    private function roleIds(array $slugs, bool $skipUnknown = false): array
    {
        // No roles table yet: reads answer empty, but a write asked for cannot be done.
        if (! Readiness::tablesExist()) {
            if ($slugs !== []) {
                throw new AuthorizationException(lang('RoleWarden.install.notInstalled.groups'));
            }

            return [];
        }

        $ids = [];

        foreach ($slugs as $slug) {
            $role = model(RoleModel::class)->where('slug', strtolower($slug))->first();

            if ($role === null && $skipUnknown) {
                continue;
            }

            if ($role === null) {
                throw AuthorizationException::forUnknownGroup($slug);
            }

            $ids[(int) $role['id']] = (int) $role['id'];
        }

        return array_values($ids);
    }
}
