<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Config\Factories;
use RoleWarden\Database\Migrations\ImportAuthGroups;
use RoleWarden\Install\GroupImport;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class ImportAuthGroupsTest extends DatabaseTestCase
{
    protected function tearDown(): void
    {
        helper('setting');
        setting()->forget(GroupImport::DEFERRED);
        Factories::reset('config');
        parent::tearDown();
    }

    private function hostGroups(): int
    {
        $auth              = config('AuthGroups');
        $auth->groups      = ['operatore' => ['title' => 'Operatore']];
        $auth->permissions = ['ticket.view' => 'View'];
        $auth->matrix      = ['operatore' => ['ticket.view']];
        Factories::injectMock('config', 'AuthGroups', $auth);

        $id = $this->makeUser('op@example.test');
        $this->db->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'operatore', 'created_at' => date('Y-m-d H:i:s')]);

        return $id;
    }

    private function rerun(): void
    {
        $migration = new ImportAuthGroups();
        $migration->down();
        $migration->up();
    }

    public function testDeferredImportsNothing(): void
    {
        $id = $this->hostGroups();
        setting(GroupImport::DEFERRED, true);

        $this->rerun();

        $this->assertNull($this->roleId('operatore'));
        $this->assertSame([], $this->rolesOf($id));
    }

    public function testNotDeferredBehavesAsBefore(): void
    {
        $id = $this->hostGroups();

        $this->rerun();

        $this->assertNotNull($this->roleId('operatore'));
        $this->assertSame(['operatore'], $this->rolesOf($id));
    }
}
