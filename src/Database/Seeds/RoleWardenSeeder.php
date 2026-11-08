<?php

declare(strict_types=1);

namespace RoleWarden\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RoleWarden\Settings\PanelRole;

/**
 * Seeds the system roles and permissions. Safe to run more than once:
 * rows are matched by slug and only inserted when missing, and grants an
 * application revoked from "admin" are not given back.
 */
class RoleWardenSeeder extends Seeder
{
    /** Setting: false skips "admin" and "user" (unset means true). */
    public const DEFAULT_ROLES_KEY = 'RoleWarden.seedDefaultRoles';

    /** slug => is_super_admin; names and descriptions in Language/en/RoleWardenData.php */
    private const ROLES = ['super-admin' => 1, 'admin' => 0, 'user' => 0];

    public function run(): void
    {
        $cfg = config('RoleWarden');
        $now = date('Y-m-d H:i:s');

        helper('setting');
        $seedDefaults = setting(self::DEFAULT_ROLES_KEY) !== false;
        $newRoles     = [];

        foreach (self::ROLES as $slug => $isSuper) {
            if (! $seedDefaults && ! $isSuper) {
                continue;
            }

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

        // A panel role created now gets every permission; an existing one only the
        // permissions created now, so a grant the application revoked stays revoked.
        $panelSlug = PanelRole::slug();
        $roleId    = PanelRole::roleId();
        $slugs     = ($newRoles[$panelSlug] ?? false) ? self::slugs() : $newSlugs;

        if ($roleId === null || $slugs === []) {
            return;
        }

        $permissionIds = array_column(
            $this->db->table($cfg->table('permissions'))->select('id')->whereIn('slug', $slugs)->get()->getResultArray(),
            'id',
        );

        foreach ($permissionIds as $permissionId) {
            $pair = ['role_id' => $roleId, 'permission_id' => $permissionId];

            if ($this->db->table($cfg->table('role_permissions'))->where($pair)->countAllResults() === 0) {
                $this->db->table($cfg->table('role_permissions'))->insert($pair);
            }
        }
    }

    /**
     * Only this seeder's own slugs: the table can hold more by the time it runs
     * (the AuthGroups import adds its own), and those are not ours to grant.
     *
     * @return list<string>
     */
    public static function slugs(): array
    {
        $slugs = [];

        foreach (self::permissions() as $area => $actions) {
            foreach (array_keys($actions) as $action) {
                $slugs[] = "{$area}.{$action}";
            }
        }

        return $slugs;
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
