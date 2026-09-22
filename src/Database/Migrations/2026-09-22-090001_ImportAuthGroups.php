<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;
use RoleWarden\Database\Seeds\RoleWardenSeeder;

/**
 * Entry path for an app that already has Shield configured: reads the host's
 * app/Config/AuthGroups.php (groups, permissions, matrix) and the group
 * memberships already in auth_groups_users, and turns them into roles,
 * permissions, role_permissions and user_roles rows. Reversible: down()
 * removes exactly the slugs this file would import, never touching
 * hand-created records.
 *
 * Runs the system seed first, unconditionally: the README installs this
 * migration and the seeder as two separate commands, in that order, so the
 * system roles ("admin", "user" among them) would not exist yet when this
 * file's collision check runs. Without this, Shield's own stock group
 * names "admin"/"user" would import as ordinary roles, and the seeder
 * would then find those slugs already taken and grant them the full
 * system permission set anyway, leaving what looks like our protected
 * "admin" role actually unprotected and holding Shield's grants too.
 * RoleWardenSeeder::run() is idempotent, so calling it again from the
 * documented `db:seed` step afterward is a no-op.
 *
 * A group whose slug already names an existing role is skipped entirely
 * (not merged into it, not granted its matrix, no assignments imported for
 * it): folding a Shield group's grants into an existing role would change
 * what it can do without anyone asking for that. A permission slug that
 * already exists is reused as-is (permission slugs are a shared
 * vocabulary, not a namespace we own), and a wildcard in the matrix
 * ("area.*") expands to every listed permission in that area. A slug that
 * does not fit "area.action" is skipped rather than failing the whole
 * import.
 */
class ImportAuthGroups extends Migration
{
    private const SLUG = '/^[a-z][a-z0-9_-]*\.[a-z][a-z0-9_-]*$/';

    public function up(): void
    {
        config('Database')->seeder()->call(RoleWardenSeeder::class);

        $cfg = config('RoleWarden');
        $auth = config('AuthGroups');
        $now = date('Y-m-d H:i:s');

        $roleIds = [];

        foreach ($auth->groups as $slug => $info) {
            $id = $this->insertNew($cfg->table('roles'), $slug, [
                'name' => $info['title'] ?? $slug,
                'description' => $info['description'] ?? null,
                'is_system' => 0,
                'is_super_admin' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($id !== null) {
                $roleIds[$slug] = $id;
            }
        }

        $permissionIds = [];

        foreach ($auth->permissions as $slug => $description) {
            if (preg_match(self::SLUG, $slug) !== 1) {
                continue;
            }

            $permissionIds[$slug] = $this->insertMissing($cfg->table('permissions'), $slug, [
                'area' => substr($slug, 0, strpos($slug, '.')),
                'description' => $description,
                'is_system' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach ($auth->matrix as $group => $permissions) {
            if (! isset($roleIds[$group])) {
                continue;
            }

            foreach ($this->expand($permissions, $permissionIds) as $permissionId) {
                $pair = ['role_id' => $roleIds[$group], 'permission_id' => $permissionId];

                if ($this->db->table($cfg->table('role_permissions'))->where($pair)->countAllResults() === 0) {
                    $this->db->table($cfg->table('role_permissions'))->insert($pair);
                }
            }
        }

        $assignments = $this->db->table('auth_groups_users')->select('user_id, `group`')->get()->getResultArray();

        foreach ($assignments as $row) {
            if (! isset($roleIds[$row['group']])) {
                continue;
            }

            $pair = ['user_id' => (int) $row['user_id'], 'role_id' => $roleIds[$row['group']]];

            if ($this->db->table($cfg->table('user_roles'))->where($pair)->countAllResults() === 0) {
                $this->db->table($cfg->table('user_roles'))->insert($pair);
            }
        }
    }

    public function down(): void
    {
        $cfg = config('RoleWarden');
        $auth = config('AuthGroups');

        $roleIds = $this->idsBySlug($cfg->table('roles'), array_keys($auth->groups));
        $permissionIds = $this->idsBySlug($cfg->table('permissions'), array_keys($auth->permissions));

        if ($roleIds !== []) {
            // Cascades remove the matching role_permissions and user_roles rows too.
            $this->db->table($cfg->table('roles'))->whereIn('id', $roleIds)->where('is_system', 0)->delete();
        }

        if ($permissionIds !== []) {
            $this->db->table($cfg->table('permissions'))->whereIn('id', $permissionIds)->where('is_system', 0)->delete();
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function insertMissing(string $table, string $slug, array $data): int
    {
        $existing = $this->db->table($table)->select('id')->where('slug', $slug)->get()->getRowArray();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return (int) $this->insertNew($table, $slug, $data);
    }

    /**
     * Inserts a new row and returns its id, or null when the slug is
     * already taken: the caller decides what that means (reuse, for a
     * shared vocabulary like permissions; skip, for a role we do not own).
     *
     * @param array<string, mixed> $data
     */
    private function insertNew(string $table, string $slug, array $data): ?int
    {
        if ($this->db->table($table)->where('slug', $slug)->countAllResults() > 0) {
            return null;
        }

        $this->db->table($table)->insert(['slug' => $slug] + $data);
        $inserted = $this->db->table($table)->select('id')->where('slug', $slug)->get()->getRowArray();

        return (int) $inserted['id'];
    }

    /**
     * @param list<string> $slugs
     *
     * @return list<int>
     */
    private function idsBySlug(string $table, array $slugs): array
    {
        if ($slugs === []) {
            return [];
        }

        $rows = $this->db->table($table)->select('id')->whereIn('slug', $slugs)->get()->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    /**
     * @param list<string> $permissions matrix entries for one group, wildcards allowed
     * @param array<string, int> $permissionIds slug => id, already imported
     *
     * @return list<int>
     */
    private function expand(array $permissions, array $permissionIds): array
    {
        $ids = [];

        foreach ($permissions as $entry) {
            if (str_ends_with($entry, '.*')) {
                $area = substr($entry, 0, -2);

                foreach ($permissionIds as $slug => $id) {
                    if (str_starts_with($slug, $area . '.')) {
                        $ids[] = $id;
                    }
                }

                continue;
            }

            if (isset($permissionIds[$entry])) {
                $ids[] = $permissionIds[$entry];
            }
        }

        return array_values(array_unique($ids));
    }
}
