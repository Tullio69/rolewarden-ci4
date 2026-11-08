<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use CodeIgniter\Database\ConnectionInterface;

/**
 * Decisions, messages and writes of the Shield group import, shared by the
 * ImportAuthGroups migration and the install wizard (RoleSetup).
 */
final class GroupImport
{
    /** When true the migration imports nothing: the installer wizard does it. */
    public const DEFERRED = 'RoleWarden.importDeferred';

    /** A permission slug fits "area.action"; anything else is skipped on import. */
    public const SLUG = '/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*$/';

    /**
     * Expands a group's matrix entries ("area.*" wildcards allowed) against the known permission slugs.
     *
     * @param list<string> $patterns matrix entries for one group
     * @param list<string> $knownSlugs permission slugs that exist
     *
     * @return list<string> matching slugs, each once, in the order they are found
     */
    public static function expand(array $patterns, array $knownSlugs): array
    {
        $out = [];

        foreach ($patterns as $entry) {
            if (str_ends_with($entry, '.*')) {
                $area = substr($entry, 0, -2) . '.';

                foreach ($knownSlugs as $slug) {
                    if (str_starts_with($slug, $area)) {
                        $out[] = $slug;
                    }
                }
            } elseif (in_array($entry, $knownSlugs, true)) {
                $out[] = $entry;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Creates the host's permissions that fit "area.action" and are missing; an existing slug
     * is reused as-is (permission slugs are a shared vocabulary).
     *
     * @return array<string, int> slug => id of every valid host permission
     */
    public static function importPermissions(ConnectionInterface $db, object $authGroups): array
    {
        $table = config('RoleWarden')->table('permissions');
        $now   = date('Y-m-d H:i:s');
        $ids   = [];

        foreach (($authGroups->permissions ?? []) as $slug => $description) {
            $slug = (string) $slug;

            if (preg_match(self::SLUG, $slug) !== 1) {
                continue;
            }

            $ids[$slug] = self::idOf($db, $table, $slug) ?? self::insertNew($db, $table, $slug, [
                'area' => substr($slug, 0, (int) strpos($slug, '.')),
                'description' => $description,
                'is_system' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        return $ids;
    }

    /**
     * Creates a non-system role for a host group.
     *
     * @param array<string, mixed> $info the group's entry in AuthGroups::$groups
     *
     * @return int|null the new id, or null when the slug is already taken
     */
    public static function createRole(ConnectionInterface $db, string $slug, array $info): ?int
    {
        $table = config('RoleWarden')->table('roles');

        if (self::idOf($db, $table, $slug) !== null) {
            return null;
        }

        $now = date('Y-m-d H:i:s');

        return self::insertNew($db, $table, $slug, [
            'name' => $info['title'] ?? $slug,
            'description' => $info['description'] ?? null,
            'is_system' => 0,
            'is_super_admin' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * Grants a role the matrix entries of a group, wildcards expanded against $permissionIds.
     *
     * @param list<string> $patterns
     * @param array<string, int> $permissionIds slug => id
     */
    public static function grant(ConnectionInterface $db, int $roleId, array $patterns, array $permissionIds): void
    {
        $table = config('RoleWarden')->table('role_permissions');

        foreach (self::expand($patterns, array_map('strval', array_keys($permissionIds))) as $slug) {
            $pair = ['role_id' => $roleId, 'permission_id' => $permissionIds[$slug]];

            if ($db->table($table)->where($pair)->countAllResults() === 0) {
                $db->table($table)->insert($pair);
            }
        }
    }

    /** Assigns a role to every member of a Shield group (auth_groups_users). */
    public static function addMembers(ConnectionInterface $db, int $roleId, string $group): void
    {
        $table = config('RoleWarden')->table('user_roles');

        foreach ($db->table('auth_groups_users')->select('user_id')->where('group', $group)->get()->getResultArray() as $row) {
            $pair = ['user_id' => (int) $row['user_id'], 'role_id' => $roleId];

            if ($db->table($table)->where($pair)->countAllResults() === 0) {
                $db->table($table)->insert($pair);
            }
        }
    }

    private static function idOf(ConnectionInterface $db, string $table, string $slug): ?int
    {
        $row = $db->table($table)->select('id')->where('slug', $slug)->get()->getRowArray();

        return $row === null ? null : (int) $row['id'];
    }

    /** @param array<string, mixed> $data */
    private static function insertNew(ConnectionInterface $db, string $table, string $slug, array $data): int
    {
        $db->table($table)->insert(['slug' => $slug] + $data);

        return (int) self::idOf($db, $table, $slug);
    }

    /**
     * @param list<string> $groups group slugs
     * @param array<string, int> $matrixCounts permissions granted to each group
     * @param array<string, int> $memberCounts users in each group
     * @param list<string> $existingRoleSlugs
     *
     * @return array{import: list<string>, skipped: list<array{slug: string, users: int, permissions: int}>}
     */
    public static function plan(array $groups, array $matrixCounts, array $memberCounts, array $existingRoleSlugs): array
    {
        $plan = ['import' => [], 'skipped' => []];

        foreach ($groups as $slug) {
            if (in_array($slug, $existingRoleSlugs, true)) {
                $plan['skipped'][] = [
                    'slug' => $slug,
                    'users' => $memberCounts[$slug] ?? 0,
                    'permissions' => $matrixCounts[$slug] ?? 0,
                ];
            } else {
                $plan['import'][] = $slug;
            }
        }

        return $plan;
    }

    /**
     * @param array{import: list<string>, skipped: list<array{slug: string, users: int, permissions: int}>} $plan
     *
     * @return list<string>
     */
    public static function report(array $plan, string $locale): array
    {
        if ($plan['skipped'] === []) {
            return [];
        }

        $lines = [];

        foreach ($plan['skipped'] as $group) {
            // Members lose inGroup('<slug>') even when the group grants no permission.
            $key = $group['users'] === 0 ? 'skippedNoLoss' : ($group['permissions'] > 0 ? 'skipped' : 'skippedMembers');
            $lines[] = lang("RoleWarden.install.import.{$key}", [$group['slug'], $group['users'], $group['permissions']], $locale);
        }

        $lines[] = lang('RoleWarden.install.import.summary', [
            implode(', ', $plan['import']),
            implode(', ', array_column($plan['skipped'], 'slug')),
        ], $locale);

        return $lines;
    }
}
