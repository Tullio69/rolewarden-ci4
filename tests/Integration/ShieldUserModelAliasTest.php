<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Config\Factories;
use CodeIgniter\Events\Events;
use CodeIgniter\Shield\Models\UserModel as ShieldUserModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class ShieldUserModelAliasTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        Factories::reset('models');
        Factories::reset('config');
        parent::tearDown();
    }

    private function wire(string $provider): void
    {
        config('Auth')->userProvider = $provider;
        UserModel::replaceShieldModel();
    }

    public function testShieldUserModelResolvesToRoleWardenModel(): void
    {
        $this->wire(UserModel::class);

        $this->assertInstanceOf(UserModel::class, model(ShieldUserModel::class));
        $this->assertSame(model(UserModel::class), model(ShieldUserModel::class));
    }

    public function testSyncGroupsThroughShieldModelWritesRoleWardenRoles(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table(config('RoleWarden')->table('roles'))->insert([
            'slug' => 'operatore', 'name' => 'operatore', 'is_system' => 0, 'is_super_admin' => 0,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = $this->makeUser('alias@example.test');
        $this->wire(UserModel::class);

        model(ShieldUserModel::class)->findById($id)->syncGroups('operatore');

        $this->assertSame(['operatore'], $this->rolesOf($id));
        $this->assertSame(0, $this->db->table('auth_groups_users')->where('user_id', $id)->countAllResults());
    }

    public function testSparkPreCommandRegistersTheAlias(): void
    {
        config('Auth')->userProvider = UserModel::class;

        Events::trigger('pre_command');

        $this->assertInstanceOf(UserModel::class, model(ShieldUserModel::class));
    }

    public function testNoAliasWithoutProvider(): void
    {
        $this->wire(ShieldUserModel::class);

        $this->assertSame(ShieldUserModel::class, model(ShieldUserModel::class)::class);
    }
}
