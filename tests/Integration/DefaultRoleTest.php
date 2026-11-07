<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Config\Factories;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class DefaultRoleTest extends DatabaseTestCase
{
    private function hostDefaultGroup(string $group): void
    {
        $auth               = config('AuthGroups');
        $auth->defaultGroup = $group;
        Factories::injectMock('config', 'AuthGroups', $auth);
    }

    private function makeRole(string $slug): void
    {
        $this->db->table(config('RoleWarden')->table('roles'))->insert([
            'slug' => $slug, 'name' => $slug, 'is_system' => 0, 'is_super_admin' => 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    protected function tearDown(): void
    {
        helper('setting');
        setting()->forget(DefaultRole::KEY);
        Factories::reset('config');
        parent::tearDown();
    }

    public function testRegistrationGetsTheHostDefaultGroup(): void
    {
        $this->makeRole('cliente');
        $this->hostDefaultGroup('cliente');
        $id = $this->makeUser('a@example.test');

        DefaultRole::assignTo($id);

        $this->assertSame(['cliente'], $this->rolesOf($id));
    }

    public function testMissingHostDefaultRoleAssignsNothing(): void
    {
        $this->hostDefaultGroup('nonexistent');
        $id = $this->makeUser('b@example.test');

        DefaultRole::assignTo($id);

        $this->assertSame([], $this->rolesOf($id));
    }
}
