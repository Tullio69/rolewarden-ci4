<?php

declare(strict_types=1);

namespace RoleWarden\Install;

/**
 * Read-only analysis of the host app for the install wizard: its Shield groups,
 * their member counts and the users that could become super admin. Writes nothing.
 */
final class HostScan
{
    /** Groups of Shield's own stock AuthGroups: a host with only these has nothing of its own to adapt. */
    private const SHIELD_STOCK_GROUPS = ['superadmin', 'admin', 'developer', 'user', 'beta'];

    /**
     * @param array<string, int> $memberCounts users per group slug
     *
     * @return list<array{slug: string, title: string, users: int, permissions: int}>
     */
    public static function groups(object $authGroups, array $memberCounts): array
    {
        $known = array_values(array_filter(
            array_map('strval', array_keys($authGroups->permissions ?? [])),
            static fn (string $slug): bool => preg_match(GroupImport::SLUG, $slug) === 1,
        ));
        $out = [];

        foreach (($authGroups->groups ?? []) as $slug => $info) {
            $slug = (string) $slug;
            $out[] = [
                'slug' => $slug,
                'title' => (string) ($info['title'] ?? $slug),
                'users' => $memberCounts[$slug] ?? 0,
                'permissions' => count(GroupImport::expand($authGroups->matrix[$slug] ?? [], $known)),
            ];
        }

        return $out;
    }

    /**
     * @param list<array{slug: string}> $groups
     * @param list<string> $systemSlugs
     *
     * @return list<string> host group slugs that name a system role
     */
    public static function collisions(array $groups, array $systemSlugs): array
    {
        return array_values(array_intersect(array_column($groups, 'slug'), $systemSlugs));
    }

    /**
     * @param list<array{slug: string}> $groups
     */
    public static function suggestedMode(array $groups): string
    {
        return array_diff(array_column($groups, 'slug'), self::SHIELD_STOCK_GROUPS) === [] ? 'import' : 'adapt';
    }

    public static function defaultGroup(object $authGroups): ?string
    {
        $group = $authGroups->defaultGroup ?? null;

        return is_string($group) && $group !== '' ? $group : null;
    }

    /**
     * @return array<string, int>
     */
    public static function memberCounts(): array
    {
        $db = db_connect();

        $db->resetDataCache();

        if (! $db->tableExists('auth_groups_users')) {
            return [];
        }

        $counts = [];

        foreach ($db->table('auth_groups_users')->select('`group`, COUNT(*) AS n')->groupBy('group')->get()->getResultArray() as $row) {
            $counts[$row['group']] = (int) $row['n'];
        }

        return $counts;
    }

    /**
     * Permissions given to single users in Shield (auth_permissions_users). RoleWarden does
     * not import them (R17): the wizard only warns. Zeros when the table does not exist.
     *
     * @return array{users: int, permissions: int}
     */
    public static function directPermissions(): array
    {
        $db = db_connect();

        if (! $db->tableExists('auth_permissions_users', false)) {
            return ['users' => 0, 'permissions' => 0];
        }

        $row = $db->table('auth_permissions_users')->select('COUNT(DISTINCT user_id) AS users, COUNT(*) AS permissions', false)->get()->getRowArray();

        return ['users' => (int) ($row['users'] ?? 0), 'permissions' => (int) ($row['permissions'] ?? 0)];
    }

    /**
     * Slugs of RoleWarden's system roles already in the database (an earlier `spark migrate`
     * seeds them); empty while its tables do not exist.
     *
     * @return list<string>
     */
    public static function systemRoles(): array
    {
        $db    = db_connect();
        $table = config('RoleWarden')->table('roles');

        if (! $db->tableExists($table, false)) {
            return [];
        }

        return array_map('strval', array_column($db->table($table)->select('slug')->where('is_system', 1)->get()->getResultArray(), 'slug'));
    }

    /**
     * Host group slugs that "adapt" cannot turn into roles: super-admin, which it always
     * creates, and any system role already in the database. Only "import" resolves them.
     *
     * @param list<string> $slugs host group slugs
     *
     * @return list<string>
     */
    public static function adaptBlocked(array $slugs): array
    {
        return array_values(array_intersect($slugs, ['super-admin', ...self::systemRoles()]));
    }

    /**
     * Users who can sign in: active, not banned, not soft-deleted.
     *
     * @return list<array{id: int, username: string, email: string}>
     */
    public static function activeUsers(int $limit = 200): array
    {
        $rows = db_connect()->table('users u')
            ->select('u.id, u.username, i.secret AS email')
            ->join('auth_identities i', "i.user_id = u.id AND i.type = 'email_password'", 'left')
            ->where('u.active', 1)
            ->where('u.deleted_at', null)
            ->groupStart()->where('u.status', null)->orWhere('u.status !=', 'banned')->groupEnd()
            ->orderBy('u.id')
            ->limit($limit)
            ->get()->getResultArray();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'username' => (string) ($r['username'] ?? ''),
            'email' => (string) ($r['email'] ?? ''),
        ], $rows);
    }
}
