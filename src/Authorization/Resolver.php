<?php

declare(strict_types=1);

namespace RoleWarden\Authorization;

use InvalidArgumentException;
use RoleWarden\Authorization\Contracts\AuthorizationStore;
use RoleWarden\Authorization\Contracts\Cache;

/**
 * Answers can(user, permission). Framework-free by design.
 *
 * Precedence, first rule that answers wins: inactive user denies; negative
 * user override denies; positive user override grants; super admin grants;
 * permission from a role (own or inherited) grants; otherwise deny.
 */
class Resolver
{
    // \z, not $: $ also matches before a trailing newline, which would slip past a stored slug.
    private const SLUG = '/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*\z/';

    public function __construct(
        private readonly AuthorizationStore $store,
        private readonly Cache $cache,
    ) {
    }

    public function can(int $userId, string $permission): bool
    {
        $this->assertSlug($permission);
        $s = $this->snapshot($userId);

        if (! $s['active'] || in_array($permission, $s['denied'], true)) {
            return false;
        }

        return in_array($permission, $s['granted'], true)
            || $s['superAdmin']
            || in_array($permission, $s['rolePermissions'], true);
    }

    /**
     * An empty list is false: nothing was granted.
     *
     * @param list<string> $permissions
     */
    public function canAny(int $userId, array $permissions): bool
    {
        array_map($this->assertSlug(...), $permissions);

        foreach ($permissions as $permission) {
            if ($this->can($userId, $permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An empty list is true: nothing was denied.
     *
     * @param list<string> $permissions
     */
    public function canAll(int $userId, array $permissions): bool
    {
        array_map($this->assertSlug(...), $permissions);

        foreach ($permissions as $permission) {
            if (! $this->can($userId, $permission)) {
                return false;
            }
        }

        return true;
    }

    public function authorize(int $userId, string $permission): void
    {
        if (! $this->can($userId, $permission)) {
            throw AuthorizationException::denied($permission);
        }
    }

    /**
     * Listed effective permissions. A super admin also passes checks for
     * slugs not listed here, so this is the explicit set, not the whole truth.
     *
     * @return list<string>
     */
    public function permissions(int $userId): array
    {
        $s = $this->snapshot($userId);

        if (! $s['active']) {
            return [];
        }

        $all = array_unique([...$s['rolePermissions'], ...$s['granted']]);
        sort($all);

        return array_values(array_diff($all, $s['denied']));
    }

    public function forgetUser(int $userId): void
    {
        $this->cache->forget($this->key($userId));
    }

    /**
     * @param list<int> $userIds
     */
    public function forgetUsers(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->forgetUser($userId);
        }
    }

    /**
     * Call after any change to a role, its permissions or its parent: every
     * user holding the role or one of its descendants is affected.
     */
    public function forgetRole(int $roleId): void
    {
        $seen = [];
        $queue = [$roleId];

        while ($queue !== []) {
            $id = array_shift($queue);

            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $this->forgetUsers($this->store->userIdsWithRole($id));
            array_push($queue, ...$this->store->childRoleIds($id));
        }
    }

    private function assertSlug(string $permission): void
    {
        if (preg_match(self::SLUG, $permission) !== 1) {
            throw new InvalidArgumentException(sprintf(
                'Invalid permission "%s": use the "area.action" format in lowercase, and no wildcards when checking.',
                $permission,
            ));
        }
    }

    private function key(int $userId): string
    {
        return 'rolewarden.user.' . $userId;
    }

    /**
     * @return array{active: bool, superAdmin: bool, denied: list<string>, granted: list<string>, rolePermissions: list<string>}
     */
    private function snapshot(int $userId): array
    {
        $cached = $this->cache->get($this->key($userId));

        if ($cached !== null) {
            /** @var array{active: bool, superAdmin: bool, denied: list<string>, granted: list<string>, rolePermissions: list<string>} $cached */
            return $cached;
        }

        $snapshot = $this->build($userId);
        $this->cache->set($this->key($userId), $snapshot);

        return $snapshot;
    }

    /**
     * @return array{active: bool, superAdmin: bool, denied: list<string>, granted: list<string>, rolePermissions: list<string>}
     */
    private function build(int $userId): array
    {
        $snapshot = ['active' => $this->store->isActive($userId), 'superAdmin' => false, 'denied' => [], 'granted' => [], 'rolePermissions' => []];

        if (! $snapshot['active']) {
            return $snapshot;
        }

        foreach ($this->store->userOverrides($userId) as $slug => $granted) {
            $snapshot[$granted ? 'granted' : 'denied'][] = $slug;
        }

        $seen = [];

        foreach ($this->store->userRoleIds($userId) as $roleId) {
            // Super admin is a flag on the assigned role, not inherited by its children.
            $snapshot['superAdmin'] = $snapshot['superAdmin'] || ($this->store->role($roleId)['isSuperAdmin'] ?? false);

            // Walk up the parents; a cycle already in the data stops at the first repeat.
            for ($id = $roleId; $id !== null && ! isset($seen[$id]); $id = $role['parentId']) {
                $role = $this->store->role($id);

                if ($role === null) {
                    break;
                }
                $seen[$id] = true;
                array_push($snapshot['rolePermissions'], ...$role['permissions']);
            }
        }

        $snapshot['rolePermissions'] = array_values(array_unique($snapshot['rolePermissions']));

        return $snapshot;
    }
}
