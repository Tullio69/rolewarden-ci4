<?php

declare(strict_types=1);

namespace RoleWarden\Entities;

use CodeIgniter\Shield\Entities\User as ShieldUser;
use RoleWarden\Adapters\DatabaseStore;

/**
 * Shield user whose permission checks come from the RoleWarden resolver.
 * Group compatibility: a Shield "group" is the slug of an assigned role.
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
}
