<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;
use RoleWarden\Database\Seeds\RoleWardenSeeder;

/**
 * v1.1 upgrade path for the dashboard (SPEC, decisions of 2026-10-18 and
 * 2026-10-21): adds the five dashboard.* permissions, granted to "admin" by
 * the seeder, and grants them to every role that holds activity.view
 * directly, so whoever reads the log on a 1.0 installation gets the dashboard.
 * Roles inheriting activity.view get them through the same inheritance.
 */
class AddDashboardPermissions extends Migration
{
    private const SLUGS = ['dashboard.figures', 'dashboard.signins', 'dashboard.security', 'dashboard.roles', 'dashboard.changes'];

    public function up(): void
    {
        config('Database')->seeder()->call(RoleWardenSeeder::class);

        $cfg = config('RoleWarden');
        $grants = $this->db->table($cfg->table('role_permissions'));
        $roleIds = array_map('intval', array_column(
            $this->db->table($cfg->table('role_permissions') . ' rp')
                ->select('rp.role_id')
                ->join($cfg->table('permissions') . ' p', 'p.id = rp.permission_id')
                ->where('p.slug', 'activity.view')
                ->get()->getResultArray(),
            'role_id',
        ));
        $permissionIds = array_map('intval', array_column(
            $this->db->table($cfg->table('permissions'))->select('id')->whereIn('slug', self::SLUGS)->get()->getResultArray(),
            'id',
        ));

        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                $pair = ['role_id' => $roleId, 'permission_id' => $permissionId];

                if ($grants->where($pair)->countAllResults() === 0) {
                    $grants->insert($pair);
                }
            }
        }

        $this->forget([...$roleIds, ...$this->adminId()], []);
    }

    /**
     * Deletes the five permissions; every grant and override of them goes
     * with them (ON DELETE CASCADE), nothing else is touched.
     */
    public function down(): void
    {
        $cfg = config('RoleWarden');
        $ids = array_column($this->db->table($cfg->table('permissions'))->select('id')->whereIn('slug', self::SLUGS)->get()->getResultArray(), 'id');

        if ($ids === []) {
            return;
        }

        $roleIds = array_column($this->db->table($cfg->table('role_permissions'))->select('role_id')->whereIn('permission_id', $ids)->get()->getResultArray(), 'role_id');
        $userIds = array_column($this->db->table($cfg->table('user_permissions'))->select('user_id')->whereIn('permission_id', $ids)->get()->getResultArray(), 'user_id');

        $this->db->table($cfg->table('permissions'))->whereIn('id', $ids)->delete();
        $this->forget(array_map('intval', $roleIds), array_map('intval', $userIds));
    }

    /**
     * @param list<int> $roleIds
     * @param list<int> $userIds
     */
    private function forget(array $roleIds, array $userIds): void
    {
        foreach (array_unique($roleIds) as $roleId) {
            service('rolewarden')->forgetRole($roleId);
        }

        service('rolewarden')->forgetUsers(array_values(array_unique($userIds)));
    }

    /** @return list<int> */
    private function adminId(): array
    {
        $roleId = $this->db->table(config('RoleWarden')->table('roles'))->select('id')->where('slug', 'admin')->get()->getRow('id');

        return $roleId !== null ? [(int) $roleId] : [];
    }
}
