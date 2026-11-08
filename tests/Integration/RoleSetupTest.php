<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use InvalidArgumentException;
use RoleWarden\Database\Migrations\ImportAuthGroups;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Install\GroupImport;
use RoleWarden\Install\RoleSetup;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Settings\PanelRole;
use RoleWarden\Settings\Visibility;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class RoleSetupTest extends DatabaseTestCase
{
    private int $boss;
    private int $operator;

    protected function setUp(): void
    {
        parent::setUp();
        helper('setting');

        $this->boss     = $this->makeUser('boss@example.test');
        $this->operator = $this->makeUser('op@example.test');
        $now            = date('Y-m-d H:i:s');
        $this->db->table('auth_groups_users')->insertBatch([
            ['user_id' => $this->boss, 'group' => 'admin', 'created_at' => $now],
            ['user_id' => $this->operator, 'group' => 'operatore', 'created_at' => $now],
        ]);
    }

    protected function tearDown(): void
    {
        foreach ([GroupImport::DEFERRED, RoleWardenSeeder::DEFAULT_ROLES_KEY, PanelRole::KEY, DefaultRole::KEY, Visibility::KEY] as $key) {
            setting()->forget($key);
        }

        parent::tearDown();
    }

    /** Mirrors the helpdesk's app/Config/AuthGroups.php: admin holds 14 permissions. */
    private function helpdesk(): object
    {
        return new class () {
            public string $defaultGroup = 'cliente';
            public array $groups = [
                'admin' => ['title' => 'Admin', 'description' => 'Host admin'],
                'operatore' => ['title' => 'Operatore'],
                'cliente' => [],
            ];
            public array $permissions = [
                'tickets.create' => 'a', 'tickets.view-own' => 'a', 'tickets.view-assigned' => 'a',
                'tickets.view-unassigned' => 'a', 'tickets.view-all' => 'a', 'tickets.reply' => 'a',
                'tickets.internal-notes' => 'a', 'tickets.change-status' => 'a', 'tickets.change-priority' => 'a',
                'tickets.take' => 'a', 'tickets.assign' => 'a', 'tickets.close-own' => 'a',
                'admin.categories' => 'a', 'admin.users' => 'a',
            ];
            public array $matrix = [
                'admin' => ['tickets.*', 'admin.*'],
                'operatore' => ['tickets.create', 'tickets.reply'],
                'cliente' => ['tickets.create'],
            ];
        };
    }

    /** @param array<string, mixed> $extra */
    private function choices(string $mode, array $extra = []): array
    {
        return $extra + ['mode' => $mode, 'panelRole' => '', 'collisions' => [], 'defaultRole' => 'cliente', 'visibility' => 'admins'];
    }

    /** Reproduces what the wizard runs before apply(): prepare(), then the migrations on an empty module. */
    private function migrate(array $choices): void
    {
        $cfg = config('RoleWarden');

        foreach (['user_roles', 'role_permissions', 'permissions', 'roles'] as $table) {
            $this->db->table($cfg->table($table))->emptyTable();
        }

        RoleSetup::prepare($choices);
        (new ImportAuthGroups())->up();
    }

    /** @return list<string> */
    private function permissionsOf(string $role): array
    {
        $cfg  = config('RoleWarden');
        $rows = $this->db->table($cfg->table('role_permissions') . ' rp')->select('p.slug')
            ->join($cfg->table('permissions') . ' p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $this->roleId($role))->orderBy('p.slug')->get()->getResultArray();

        return array_column($rows, 'slug');
    }

    /** @return array<string, int> */
    private function counts(): array
    {
        $cfg = config('RoleWarden');
        $out = [];

        foreach (['roles', 'permissions', 'role_permissions', 'user_roles'] as $table) {
            $out[$table] = $this->db->table($cfg->table($table))->countAllResults();
        }

        return $out;
    }

    public function testAdaptImportsHostGroupsAndMakesPanelRole(): void
    {
        $choices = $this->choices('adapt', ['panelRole' => 'admin']);
        $this->migrate($choices);

        RoleSetup::apply($choices, $this->helpdesk());

        $this->assertNull($this->roleId('user'));
        $this->assertNotNull($this->roleId('super-admin'));
        $admin = $this->db->table(config('RoleWarden')->table('roles'))->where('slug', 'admin')->get()->getRowArray();
        $this->assertSame(['0', 'Admin'], [(string) $admin['is_system'], $admin['name']]);
        $this->assertCount(14 + count(RoleWardenSeeder::slugs()), $this->permissionsOf('admin'));
        $this->assertSame(['tickets.create', 'tickets.reply'], $this->permissionsOf('operatore'));
        $this->assertSame(['tickets.create'], $this->permissionsOf('cliente'));
        $this->assertContains('admin', $this->rolesOf($this->boss));
        $this->assertContains('operatore', $this->rolesOf($this->operator));
        $this->assertSame('admin', setting(PanelRole::KEY));
        $this->assertSame('cliente', setting(DefaultRole::KEY));
        $this->assertSame('admins', setting(Visibility::KEY));
        $this->assertFalse(setting(GroupImport::DEFERRED));
    }

    public function testImportMergeGivesHostPermissionsToSystemAdmin(): void
    {
        $choices = $this->choices('import', ['collisions' => ['admin' => ['action' => 'merge']]]);
        $before  = $this->permissionsOf('admin');

        RoleSetup::apply($choices, $this->helpdesk());

        $after = $this->permissionsOf('admin');
        $this->assertCount(count($before) + 14, $after);
        $this->assertContains('admin.users', $after);
        $this->assertContains('admin', $this->rolesOf($this->boss));
        $this->assertSame(['operatore'], array_values(array_diff($this->rolesOf($this->operator), ['user'])));
        $this->assertNull(setting(PanelRole::KEY));
    }

    public function testImportRename(): void
    {
        $before = $this->permissionsOf('admin');

        RoleSetup::apply($this->choices('import', ['collisions' => ['admin' => ['action' => 'rename', 'newSlug' => 'app-admin']]]), $this->helpdesk());

        $this->assertCount(14, $this->permissionsOf('app-admin'));
        $this->assertContains('app-admin', $this->rolesOf($this->boss));
        $this->assertNotContains('admin', $this->rolesOf($this->boss));
        $this->assertSame($before, $this->permissionsOf('admin'));
    }

    public function testImportSkip(): void
    {
        $before = $this->permissionsOf('admin');

        RoleSetup::apply($this->choices('import', ['collisions' => ['admin' => ['action' => 'skip']]]), $this->helpdesk());

        $this->assertSame($before, $this->permissionsOf('admin'));
        $this->assertNotContains('admin', $this->rolesOf($this->boss));
        $this->assertNotNull($this->roleId('operatore'));
        $this->assertNotNull($this->roleId('cliente'));
    }

    public function testApplyTwiceDoesNotDuplicate(): void
    {
        $choices = $this->choices('import', ['collisions' => ['admin' => ['action' => 'rename', 'newSlug' => 'app-admin']]]);

        RoleSetup::apply($choices, $this->helpdesk());
        $once = $this->counts();
        RoleSetup::apply($choices, $this->helpdesk());

        $this->assertSame($once, $this->counts());
    }

    public function testRenameToTakenSlugFails(): void
    {
        $before  = $this->counts();
        $default = setting(DefaultRole::KEY);

        try {
            RoleSetup::apply($this->choices('import', ['collisions' => ['admin' => ['action' => 'rename', 'newSlug' => 'user']]]), $this->helpdesk());
            $this->fail('expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame($before, $this->counts());
        $this->assertNull($this->roleId('operatore'));
        $this->assertSame($default, setting(DefaultRole::KEY));
    }
}
