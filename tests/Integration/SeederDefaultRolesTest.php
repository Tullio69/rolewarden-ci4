<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Settings\PanelRole;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class SeederDefaultRolesTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        helper('setting');
        setting()->forget(RoleWardenSeeder::DEFAULT_ROLES_KEY);
        setting()->forget(PanelRole::KEY);
        parent::tearDown();
    }

    public function testDefaultsCreateAdminAndUser(): void
    {
        $this->assertNotNull($this->roleId('admin'));
        $this->assertNotNull($this->roleId('user'));
    }

    public function testDisabledCreatesOnlySuperAdmin(): void
    {
        $cfg = config('RoleWarden');

        // Start from an empty module, as on a host that has none of it yet.
        $this->db->table($cfg->table('role_permissions'))->emptyTable();
        $this->db->table($cfg->table('permissions'))->emptyTable();
        $this->db->table($cfg->table('roles'))->emptyTable();
        $this->db->table($cfg->table('roles'))->insert([
            'slug' => 'boss', 'name' => 'boss', 'is_system' => 0, 'is_super_admin' => 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $boss = (int) $this->db->insertID();

        helper('setting');
        setting(RoleWardenSeeder::DEFAULT_ROLES_KEY, false);
        setting(PanelRole::KEY, 'boss');

        (new RoleWardenSeeder(config('Database')))->run();

        $this->assertNull($this->roleId('admin'));
        $this->assertNull($this->roleId('user'));
        $this->assertNotNull($this->roleId('super-admin'));
        $this->assertSame(
            count(RoleWardenSeeder::slugs()),
            $this->db->table($cfg->table('role_permissions'))->where('role_id', $boss)->countAllResults(),
        );
    }
}
