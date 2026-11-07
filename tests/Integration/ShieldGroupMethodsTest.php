<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Authorization\AuthorizationException;
use RoleWarden\Authorization\ProtectionException;
use RoleWarden\Entities\User;
use RoleWarden\Models\UserModel;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class ShieldGroupMethodsTest extends DatabaseTestCase
{
    private function makeRole(string $slug, int $super = 0): int
    {
        $this->db->table(config('RoleWarden')->table('roles'))->insert([
            'slug' => $slug, 'name' => $slug, 'is_system' => 0, 'is_super_admin' => $super,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    private function user(int $id): User
    {
        return model(UserModel::class)->findById($id);
    }

    protected function tearDown(): void
    {
        helper('setting');
        setting()->forget(DefaultRole::KEY);
        Factories::reset('config');
        parent::tearDown();
    }

    public function testAddGroupAssignsTheRole(): void
    {
        $this->makeRole('operatore');
        $this->makeRole('cliente');
        $id = $this->makeUser('a@example.test');

        $this->user($id)->addGroup('Operatore');

        $this->assertSame(['operatore'], $this->rolesOf($id));
        $this->assertSame(0, $this->db->table('auth_groups_users')->where('user_id', $id)->countAllResults());
    }

    public function testSyncGroupsReplacesRoles(): void
    {
        $this->makeRole('operatore');
        $this->makeRole('cliente');
        $id = $this->makeUser('b@example.test');
        $u  = $this->user($id);
        $u->addGroup('cliente');

        $u->syncGroups('operatore');

        $this->assertSame(['operatore'], $this->rolesOf($id));
        $this->assertSame(0, $this->db->table('auth_groups_users')->where('user_id', $id)->countAllResults());
    }

    public function testRemoveGroup(): void
    {
        $this->makeRole('operatore');
        $this->makeRole('cliente');
        $id = $this->makeUser('c@example.test');
        $u  = $this->user($id);
        $u->addGroup('operatore', 'cliente');

        $u->removeGroup('operatore');

        $this->assertSame(['cliente'], $this->rolesOf($id));
    }

    public function testRemoveGroupIgnoresUnknownGroups(): void
    {
        $this->makeRole('operatore');
        $this->makeRole('cliente');
        $id = $this->makeUser('n@example.test');
        $u  = $this->user($id);
        $u->addGroup('operatore', 'cliente');

        // As in Shield: a group that does not exist is not there to remove.
        $u->removeGroup('ghost', 'operatore');

        $this->assertSame(['cliente'], $this->rolesOf($id));
    }

    public function testUnknownGroupThrowsAndWritesNothing(): void
    {
        $this->makeRole('operatore');
        $id = $this->makeUser('d@example.test');

        $this->expectException(AuthorizationException::class);

        try {
            $this->user($id)->addGroup('operatore', 'ghost');
        } finally {
            $this->assertSame([], $this->rolesOf($id));
        }
    }

    public function testSyncGroupsCannotRemoveTheLastSuperAdmin(): void
    {
        $this->makeRole('boss', 1);
        $id = $this->makeUser('e@example.test');
        $u  = $this->user($id);
        $u->addGroup('boss');

        try {
            $u->syncGroups();
            $this->fail('expected ProtectionException');
        } catch (ProtectionException) {
            $this->assertSame(['boss'], $this->rolesOf($id));
        }
    }

    public function testAddToDefaultGroupUsesTheDefaultRole(): void
    {
        $this->makeRole('cliente');
        helper('setting');
        setting(DefaultRole::KEY, 'cliente');
        $id = $this->makeUser('f@example.test');

        model(UserModel::class)->addToDefaultGroup($this->user($id));

        $this->assertSame(['cliente'], $this->rolesOf($id));
        $this->assertSame(0, $this->db->table('auth_groups_users')->where('user_id', $id)->countAllResults());
    }

    public function testAddToDefaultGroupTwiceAssignsOnce(): void
    {
        $this->makeRole('cliente');
        helper('setting');
        setting(DefaultRole::KEY, 'cliente');
        $id = $this->makeUser('g@example.test');
        $u  = $this->user($id);

        model(UserModel::class)->addToDefaultGroup($u);
        model(UserModel::class)->addToDefaultGroup($u);

        $this->assertSame(['cliente'], $this->rolesOf($id));
        $this->assertSame(1, $this->db->table(config('RoleWarden')->table('user_roles'))->where('user_id', $id)->countAllResults());
    }

    public function testCanReflectsTheChangeImmediately(): void
    {
        $roleId = $this->makeRole('operatore');
        $now    = date('Y-m-d H:i:s');
        $this->db->table(config('RoleWarden')->table('permissions'))->insert(['slug' => 'x.y', 'area' => 'x', 'is_system' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $this->db->table(config('RoleWarden')->table('role_permissions'))->insert(['role_id' => $roleId, 'permission_id' => (int) $this->db->insertID()]);
        $id = $this->makeUser('h@example.test');
        $u  = $this->user($id);
        $this->assertFalse($u->can('x.y')); // warms the resolver cache

        $u->addGroup('operatore');

        $this->assertTrue($u->can('x.y'));
    }

    public function testSyncGroupsRollsBackWhenARevokeIsRefused(): void
    {
        $this->makeRole('boss', 1);
        $this->makeRole('cliente');
        $id = $this->makeUser('i@example.test');
        $u  = $this->user($id);
        $u->addGroup('cliente', 'boss');

        try {
            $u->syncGroups();
            $this->fail('expected ProtectionException');
        } catch (ProtectionException) {
            $this->assertSame(['boss', 'cliente'], $this->rolesOf($id));
        }
    }

    public function testRemoveGroupRollsBackWhenARevokeIsRefused(): void
    {
        $this->makeRole('boss', 1);
        $this->makeRole('cliente');
        $id = $this->makeUser('j@example.test');
        $u  = $this->user($id);
        $u->addGroup('cliente', 'boss');

        try {
            $u->removeGroup('cliente', 'boss');
            $this->fail('expected ProtectionException');
        } catch (ProtectionException) {
            $this->assertSame(['boss', 'cliente'], $this->rolesOf($id));
        }
    }

    public function testSyncGroupsCanSwapBetweenSuperAdminRoles(): void
    {
        $this->makeRole('boss1', 1);
        $this->makeRole('boss2', 1);
        $id = $this->makeUser('k@example.test');
        $u  = $this->user($id);
        $u->addGroup('boss1');

        $u->syncGroups('boss2');

        $this->assertSame(['boss2'], $this->rolesOf($id));
    }

    public function testSoftDeletedRoleIsAnUnknownGroup(): void
    {
        $roleId = $this->makeRole('vecchio');
        model(\RoleWarden\Models\RoleModel::class)->delete($roleId);
        $id = $this->makeUser('l@example.test');

        $this->expectException(AuthorizationException::class);
        $this->user($id)->addGroup('vecchio');
    }

    public function testAddToDefaultGroupWithoutDefaultRoleAssignsNothing(): void
    {
        helper('setting');
        setting(DefaultRole::KEY, '');
        $id = $this->makeUser('m@example.test');

        model(UserModel::class)->addToDefaultGroup($this->user($id));

        $this->assertSame([], $this->rolesOf($id));
    }
}
