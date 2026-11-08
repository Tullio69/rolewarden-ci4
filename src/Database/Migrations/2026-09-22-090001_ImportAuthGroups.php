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

        $permissionIds = GroupImport::importPermissions($this->db, $auth);

        $memberCounts = [];

        foreach ($this->db->table('auth_groups_users')->select('`group`, COUNT(*) AS n')->groupBy('group')->get()->getResultArray() as $row) {
            $memberCounts[$row['group']] = (int) $row['n'];
        }

        $known = array_map('strval', array_keys($permissionIds));
        $matrixCounts = array_map(static fn (array $p): int => count(GroupImport::expand($p, $known)), $auth->matrix);
        $existing = array_column($this->db->table($cfg->table('roles'))->select('slug')->get()->getResultArray(), 'slug');
        $plan = GroupImport::plan(array_keys($auth->groups), $matrixCounts, $memberCounts, $existing);

        if (is_cli() && ENVIRONMENT !== 'testing') {
            foreach (GroupImport::report($plan, service('request')->getLocale()) as $line) {
                CLI::write($line, 'yellow');
            }
        }

        foreach ($plan['import'] as $slug) {
            $roleId = GroupImport::createRole($this->db, $slug, $auth->groups[$slug]);

            if ($roleId === null) {
                continue;
            }

            GroupImport::grant($this->db, $roleId, $auth->matrix[$slug] ?? [], $permissionIds);
            GroupImport::addMembers($this->db, $roleId, $slug);
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
}
