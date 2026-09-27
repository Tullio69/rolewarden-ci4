<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;
use RoleWarden\Database\Seeds\RoleWardenSeeder;

/**
 * v1.0 upgrade path for V2, same as AddSettingsPermissions: adds the system
 * permissions sessions.view and sessions.revoke, granted to "admin", to
 * installations that ran the seeder before they existed.
 */
class AddSessionPermissions extends Migration
{
    private const SLUGS = ['sessions.view', 'sessions.revoke'];

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
