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
