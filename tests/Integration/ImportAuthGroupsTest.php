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

        $cfg = config('RoleWarden');
        $granted = $this->db->table($cfg->table('role_permissions') . ' rp')->select('p.slug')
            ->join($cfg->table('permissions') . ' p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $this->roleId('operatore'))->get()->getResultArray();
        $this->assertSame(['ticket.view'], array_column($granted, 'slug'));
    }

    public function testCollidingGroupLeavesExistingRoleUntouched(): void
    {
        $auth              = config('AuthGroups');
        $auth->groups      = ['admin' => ['title' => 'Host admin']];
        $auth->permissions = ['ticket.view' => 'View', 'ticket.edit' => 'Edit'];
        $auth->matrix      = ['admin' => ['ticket.*']];
        Factories::injectMock('config', 'AuthGroups', $auth);

        $id = $this->makeUser('boss@example.test');
        $this->db->table('auth_groups_users')->insert(['user_id' => $id, 'group' => 'admin', 'created_at' => date('Y-m-d H:i:s')]);

        $cfg    = config('RoleWarden');
        $grants = fn (): int => $this->db->table($cfg->table('role_permissions'))->where('role_id', $this->roleId('admin'))->countAllResults();
        $before = $grants();

        (new ImportAuthGroups())->up();

        $this->assertSame($before, $grants());
        $this->assertSame([], $this->rolesOf($id));

        $plan = GroupImport::plan(['admin'], ['admin' => 2], ['admin' => 1], ['admin']);
        $this->assertSame(
            ['Gruppo "admin" non importato: esiste già il ruolo "admin". 1 utente perde 2 permessi: concedili dal pannello (Ruoli).', 'Importati: . Saltati: admin.'],
            GroupImport::report($plan, 'it'),
        );
    }
}
