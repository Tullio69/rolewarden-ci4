<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Settings\PanelRole;

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
        config('Database')->seeder()->setSilent(true)->call(RoleWardenSeeder::class);
        $this->forgetPanelRole();
    }

    public function down(): void
    {
        $this->db->table(config('RoleWarden')->table('permissions'))->whereIn('slug', self::SLUGS)->delete();
        $this->forgetPanelRole();
    }

    private function forgetPanelRole(): void
    {
        $roleId = PanelRole::roleId();

        if ($roleId !== null) {
            service('rolewarden')->forgetRole($roleId);
        }
    }
}
