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
        $this->makeRole('operatore');
        $id = $this->makeUser('h@example.test');
        $u  = $this->user($id);
        $this->assertFalse($u->inGroup('operatore'));
        $this->assertFalse($u->can('x.y'));

        $u->addGroup('operatore');

        $this->assertTrue($u->inGroup('operatore'));
    }
}
