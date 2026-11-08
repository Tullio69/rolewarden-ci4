<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Migration;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Install\GroupImport;

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
    public function up(): void
    {
        config('Database')->seeder()->setSilent(true)->call(RoleWardenSeeder::class);

        if (setting(GroupImport::DEFERRED) === true) {
            return; // the installer wizard imports the groups
        }

        $cfg = config('RoleWarden');
        $auth = config('AuthGroups');
        $now = date('Y-m-d H:i:s');

        $permissionIds = [];

        foreach ($auth->permissions as $slug => $description) {
            if (preg_match(GroupImport::SLUG, $slug) !== 1) {
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

        $memberCounts = [];

        foreach ($this->db->table('auth_groups_users')->select('`group`, COUNT(*) AS n')->groupBy('group')->get()->getResultArray() as $row) {
            $memberCounts[$row['group']] = (int) $row['n'];
        }

        $matrixCounts = array_map(fn (array $p): int => count($this->expand($p, $permissionIds)), $auth->matrix);
        $existing = array_column($this->db->table($cfg->table('roles'))->select('slug')->get()->getResultArray(), 'slug');
        $plan = GroupImport::plan(array_keys($auth->groups), $matrixCounts, $memberCounts, $existing);

        if (is_cli() && ENVIRONMENT !== 'testing') {
            foreach (GroupImport::report($plan, service('request')->getLocale()) as $line) {
                CLI::write($line, 'yellow');
            }
        }

        $roleIds = [];

        foreach ($plan['import'] as $slug) {
            $info = $auth->groups[$slug];
            $roleIds[$slug] = $this->insertNew($cfg->table('roles'), $slug, [
                'name' => $info['title'] ?? $slug,
                'description' => $info['description'] ?? null,
                'is_system' => 0,
                'is_super_admin' => 0,
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
        return array_map(
            static fn (string $slug): int => $permissionIds[$slug],
            GroupImport::expand($permissions, array_map('strval', array_keys($permissionIds))),
        );
    }
}
