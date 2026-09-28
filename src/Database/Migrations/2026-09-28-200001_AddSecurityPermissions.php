<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;
use RoleWarden\Database\Seeds\RoleWardenSeeder;

/**
 * v1.0 upgrade path for V4, same as AddSettingsPermissions: adds the system
 * permission security.alerts, granted to "admin", to installations that ran
 * the seeder before it existed.
 */
class AddSecurityPermissions extends Migration
{
    private const SLUGS = ['security.alerts'];

    public function up(): void
    {
        config('Database')->seeder()->call(RoleWardenSeeder::class);
        $this->forgetAdmin();
    }

    public function down(): void
    {
        $this->db->table(config('RoleWarden')->table('permissions'))->whereIn('slug', self::SLUGS)->delete();
        $this->forgetAdmin();
    }

    private function forgetAdmin(): void
    {
        $roleId = $this->db->table(config('RoleWarden')->table('roles'))->select('id')->where('slug', 'admin')->get()->getRow('id');

        if ($roleId !== null) {
            service('rolewarden')->forgetRole((int) $roleId);
        }
    }
}
