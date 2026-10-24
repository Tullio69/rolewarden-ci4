<?php

declare(strict_types=1);

namespace RoleWarden\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the system roles and permissions. Safe to run more than once:
 * rows are matched by slug and only inserted when missing, and grants an
 * application revoked from "admin" are not given back.
 */
class RoleWardenSeeder extends Seeder
{
    /** slug => is_super_admin; names and descriptions in Language/en/RoleWardenData.php */
    private const ROLES = ['super-admin' => 1, 'admin' => 0, 'user' => 0];

    /** Roles that receive every seeded permission. */
    private const ALL_PERMISSIONS_ROLES = ['admin'];

    public function run(): void
    {
        $cfg = config('RoleWarden');
        $now = date('Y-m-d H:i:s');

        $newRoles = [];

        foreach (self::ROLES as $slug => $isSuper) {
            $newRoles[$slug] = $this->insertMissing($cfg->table('roles'), $slug, [
                'name' => self::texts()['roles'][$slug]['name'],
                'description' => self::texts()['roles'][$slug]['description'],
                'is_system' => 1,
                'is_super_admin' => $isSuper,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $newSlugs = [];

        foreach (self::permissions() as $area => $actions) {
            foreach ($actions as $action => $description) {
                if ($this->insertMissing($cfg->table('permissions'), "{$area}.{$action}", [
                    'area' => $area,
                    'description' => $description,
                    'is_system' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])) {
                    $newSlugs[] = "{$area}.{$action}";
                }
            }
        }

        // Only this seeder's own slugs: the table can hold more by the time this
        // runs (the AuthGroups import adds its own), and those are not ours to grant.
        $ownSlugs = [];

        foreach (self::permissions() as $area => $actions) {
            foreach (array_keys($actions) as $action) {
                $ownSlugs[] = "{$area}.{$action}";
            }
        }

        // A role created now gets every permission; an existing one only the
        // permissions created now, so a grant the application revoked stays revoked.
        foreach (self::ALL_PERMISSIONS_ROLES as $slug) {
            $slugs = $newRoles[$slug] ? $ownSlugs : $newSlugs;

            if ($slugs === []) {
                continue;
            }

            $permissionIds = array_column(
                $this->db->table($cfg->table('permissions'))->select('id')->whereIn('slug', $slugs)->get()->getResultArray(),
                'id',
            );
            $roleId = $this->db->table($cfg->table('roles'))->select('id')->where('slug', $slug)->get()->getRow('id');

            foreach ($permissionIds as $permissionId) {
                $pair = ['role_id' => $roleId, 'permission_id' => $permissionId];

                if ($this->db->table($cfg->table('role_permissions'))->where($pair)->countAllResults() === 0) {
                    $this->db->table($cfg->table('role_permissions'))->insert($pair);
                }
            }
        }
    }

    /**
     * area => [action => description], in English as stored.
     *
     * @return array<string, array<string, string>>
     */
    private static function permissions(): array
    {
        return self::texts()['permissions'];
    }

    /**
     * The module's own file, read directly: lines an application adds to its
     * copy translate its own roles and permissions and must not be seeded.
     *
     * @return array<string, mixed>
     */
    private static function texts(): array
    {
        return require __DIR__ . '/../../Language/en/RoleWardenData.php';
    }

    /** @return bool whether the row was inserted */
    private function insertMissing(string $table, string $slug, array $data): bool
    {
        if ($this->db->table($table)->where('slug', $slug)->countAllResults() !== 0) {
            return false;
        }

        $this->db->table($table)->insert(['slug' => $slug] + $data);

        return true;
    }
}
