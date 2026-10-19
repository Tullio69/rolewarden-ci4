<?php

declare(strict_types=1);

namespace RoleWarden\Authorization;

use Generator;
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
    public const RULE_INACTIVE = 'inactive';
    public const RULE_OVERRIDE_DENIED = 'override_denied';
    public const RULE_OVERRIDE_GRANTED = 'override_granted';
    public const RULE_SUPER_ADMIN = 'super_admin';
    public const RULE_ROLE = 'role';
    public const RULE_NONE = 'none';
    private const GRANTING = [self::RULE_OVERRIDE_GRANTED, self::RULE_SUPER_ADMIN, self::RULE_ROLE];

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

        return in_array($this->rule($this->snapshot($userId), $permission), self::GRANTING, true);
    }

    /**
     * Why can() answers as it does: the rule that decides (one of the RULE_*
     * constants) and the role chains that hold the permission, each from the
     * assigned role up to the role it is granted to directly. The chains are
     * listed even when another rule decides, so a denial can name what it beats.
     *
     * @return array{allowed: bool, rule: string, via: list<list<int>>}
     */
    public function explain(int $userId, string $permission): array
    {
        $this->assertSlug($permission);
        $rule = $this->rule($this->snapshot($userId), $permission);
        $via = [];

        foreach ($this->store->userRoleIds($userId) as $roleId) {
            $chain = [];

            foreach ($this->ancestors($roleId) as $id => $role) {
                $chain[] = $id;

                if (in_array($permission, $role['permissions'], true)) {
                    $via[] = $chain;
                    break;
                }
            }
        }

        return ['allowed' => in_array($rule, self::GRANTING, true), 'rule' => $rule, 'via' => $via];
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

    /**
     * The precedence of the class comment, in one place for can() and explain().
     *
     * @param array{active: bool, superAdmin: bool, denied: list<string>, granted: list<string>, rolePermissions: list<string>} $s
     */
    private function rule(array $s, string $permission): string
    {
        return match (true) {
            ! $s['active'] => self::RULE_INACTIVE,
            in_array($permission, $s['denied'], true) => self::RULE_OVERRIDE_DENIED,
            in_array($permission, $s['granted'], true) => self::RULE_OVERRIDE_GRANTED,
            $s['superAdmin'] => self::RULE_SUPER_ADMIN,
            in_array($permission, $s['rolePermissions'], true) => self::RULE_ROLE,
            default => self::RULE_NONE,
        };
    }

    /**
     * The role and its parents, nearest first; a deleted role ends the walk,
     * and a cycle already in the data stops at the first repeat.
     *
     * @return Generator<int, array{parentId: ?int, isSuperAdmin: bool, permissions: list<string>}>
     */
    private function ancestors(int $roleId): Generator
    {
        $seen = [];

        for ($id = $roleId; $id !== null && ! isset($seen[$id]); $id = $role['parentId']) {
            $role = $this->store->role($id);

            if ($role === null) {
                return;
            }
            $seen[$id] = true;

            yield $id => $role;
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

        foreach ($this->store->userRoleIds($userId) as $roleId) {
            foreach ($this->ancestors($roleId) as $id => $role) {
                // Super admin is a flag on the assigned role, not inherited by its children.
                $snapshot['superAdmin'] = $snapshot['superAdmin'] || ($id === $roleId && $role['isSuperAdmin']);
                array_push($snapshot['rolePermissions'], ...$role['permissions']);
            }
        }

        $snapshot['rolePermissions'] = array_values(array_unique($snapshot['rolePermissions']));

        return $snapshot;
    }
}
