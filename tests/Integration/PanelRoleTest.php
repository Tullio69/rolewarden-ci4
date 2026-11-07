<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Settings\PanelRole;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class PanelRoleTest extends DatabaseTestCase
{
    private const SLUG = 'dashboard.figures';

    private function permissionId(): int
    {
        return (int) $this->db->table(config('RoleWarden')->table('permissions'))->where('slug', self::SLUG)->get()->getRow()->id;
    }

    private function grantCount(int $roleId): int
    {
        return $this->db->table(config('RoleWarden')->table('role_permissions'))->where('role_id', $roleId)->countAllResults();
    }

    private function dropPermission(): void
    {
        $this->db->table(config('RoleWarden')->table('permissions'))->where('slug', self::SLUG)->delete();
    }

    private function makeRole(string $slug): int
    {
        $this->db->table(config('RoleWarden')->table('roles'))->insert([
            'slug' => $slug, 'name' => $slug, 'is_system' => 0, 'is_super_admin' => 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    public function testSeederGrantsModulePermissionsToThePanelRole(): void
    {
        $boss = $this->makeRole('boss');
        helper('setting');
        setting(PanelRole::KEY, 'boss');
        $this->dropPermission();

        (new RoleWardenSeeder(config('Database')))->run();

        $pair = ['role_id' => $boss, 'permission_id' => $this->permissionId()];
        $this->assertSame(1, $this->db->table(config('RoleWarden')->table('role_permissions'))->where($pair)->countAllResults());
        $pair['role_id'] = $this->roleId('admin');
        $this->assertSame(0, $this->db->table(config('RoleWarden')->table('role_permissions'))->where($pair)->countAllResults());
    }

    public function testDeletedPanelRoleGrantsToNobody(): void
    {
        helper('setting');
        setting(PanelRole::KEY, 'ghost');
        $this->dropPermission();
        $before = $this->db->table(config('RoleWarden')->table('role_permissions'))->countAllResults();

        (new RoleWardenSeeder(config('Database')))->run();

        $this->assertSame($before, $this->db->table(config('RoleWarden')->table('role_permissions'))->countAllResults());
    }

    public function testEmptyPanelRoleGrantsToNobody(): void
    {
        helper('setting');
        setting(PanelRole::KEY, '');
        $this->dropPermission();
        $before = $this->db->table(config('RoleWarden')->table('role_permissions'))->countAllResults();

        (new RoleWardenSeeder(config('Database')))->run();

        $this->assertSame($before, $this->db->table(config('RoleWarden')->table('role_permissions'))->countAllResults());
    }
}
