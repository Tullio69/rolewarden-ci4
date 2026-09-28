<?php

declare(strict_types=1);

namespace RoleWarden\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the system roles and permissions. Safe to run more than once:
 * rows are matched by slug and only inserted when missing.
 */
class RoleWardenSeeder extends Seeder
{
    /** slug => [name, description, is_super_admin] */
    private const ROLES = [
        'super-admin' => ['Super admin', 'Bypasses role checks; still subject to user-level denials.', 1],
        'admin' => ['Admin', 'Manages users, roles and permissions.', 0],
        'user' => ['User', 'Default role with no administrative permissions.', 0],
    ];

    /** area => [action => description] */
    private const PERMISSIONS = [
        'users' => [
            'view' => 'View users',
            'create' => 'Create users',
            'update' => 'Edit users',
            'delete' => 'Delete users',
            'activate' => 'Activate and deactivate users',
        ],
        'roles' => [
            'view' => 'View roles',
            'create' => 'Create roles',
            'update' => 'Edit roles',
            'delete' => 'Delete roles',
            'assign' => 'Assign roles to users',
        ],
        'permissions' => [
            'view' => 'View permissions',
            'override' => 'Grant or deny a permission to a single user',
        ],
        'settings' => [
            'view' => 'View module settings',
            'update' => 'Change module settings',
        ],
        'sessions' => [
            'view' => 'See the signed-in sessions of other users',
            'revoke' => 'Sign other users out of their sessions',
        ],
        'activity' => [
            'view' => 'Read the activity log',
        ],
        'security' => [
            'alerts' => 'Receive security alert emails (too many failed sign-ins)',
        ],
    ];

    /** Roles that receive every seeded permission. */
    private const ALL_PERMISSIONS_ROLES = ['admin'];

    public function run(): void
    {
        $cfg = config('RoleWarden');
        $now = date('Y-m-d H:i:s');

        foreach (self::ROLES as $slug => [$name, $description, $isSuper]) {
            $this->insertMissing($cfg->table('roles'), $slug, [
                'name' => $name,
                'description' => $description,
                'is_system' => 1,
                'is_super_admin' => $isSuper,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        foreach (self::PERMISSIONS as $area => $actions) {
            foreach ($actions as $action => $description) {
                $this->insertMissing($cfg->table('permissions'), "{$area}.{$action}", [
                    'area' => $area,
                    'description' => $description,
                    'is_system' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        // Only this seeder's own slugs: the table can hold more by the time this
        // runs (the AuthGroups import adds its own), and those are not ours to grant.
        $ownSlugs = [];

        foreach (self::PERMISSIONS as $area => $actions) {
            foreach (array_keys($actions) as $action) {
                $ownSlugs[] = "{$area}.{$action}";
            }
        }

        $permissionIds = array_column(
            $this->db->table($cfg->table('permissions'))->select('id')->whereIn('slug', $ownSlugs)->get()->getResultArray(),
            'id',
        );

        foreach (self::ALL_PERMISSIONS_ROLES as $slug) {
            $roleId = $this->db->table($cfg->table('roles'))->select('id')->where('slug', $slug)->get()->getRow('id');

            foreach ($permissionIds as $permissionId) {
                $pair = ['role_id' => $roleId, 'permission_id' => $permissionId];

                if ($this->db->table($cfg->table('role_permissions'))->where($pair)->countAllResults() === 0) {
                    $this->db->table($cfg->table('role_permissions'))->insert($pair);
                }
            }
        }
    }

    private function insertMissing(string $table, string $slug, array $data): void
    {
        if ($this->db->table($table)->where('slug', $slug)->countAllResults() === 0) {
            $this->db->table($table)->insert(['slug' => $slug] + $data);
        }
    }
}
