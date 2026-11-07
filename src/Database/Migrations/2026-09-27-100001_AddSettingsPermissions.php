<?php

declare(strict_types=1);

namespace RoleWarden\Database\Migrations;

use CodeIgniter\Database\Migration;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Settings\PanelRole;

/**
 * v1.0 upgrade path: adds the system permissions settings.view and
 * settings.update, granted to the "admin" role, to installations that ran
 * the seeder before they existed. A fresh install gets them from the seeder;
 * RoleWardenSeeder::run() is idempotent, so this is a no-op there.
 *
 * The seeder writes the tables directly, so the resolver cache of every user
 * holding "admin" (or a role inheriting from it) is dropped here, and the
 * new permission applies at their next request.
 */
class AddSettingsPermissions extends Migration
{
    private const SLUGS = ['settings.view', 'settings.update'];

    public function up(): void
    {
        config('Database')->seeder()->setSilent(true)->call(RoleWardenSeeder::class);
        $this->forgetPanelRole();
    }

    public function down(): void
    {
        // Rows in role_permissions and user_permissions go with them (ON DELETE CASCADE).
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
