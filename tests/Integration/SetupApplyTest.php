<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use Config\Database;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Install\ConfigEditor;
use RoleWarden\Install\GroupImport;
use RoleWarden\Install\Installer;
use RoleWarden\Install\Readiness;
use RoleWarden\Install\Setup;
use RoleWarden\Install\SetupState;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Settings\PanelRole;
use RoleWarden\Settings\Visibility;
use RoleWarden\Tests\_support\DatabaseTestCase;

/**
 * The wizard's "Confirm and apply": the host already runs Shield, RoleWarden's
 * tables do not exist yet. Setup::apply runs the steps in the binding order,
 * resumes after a failure and never writes a host file before the tables exist.
 */
final class SetupApplyTest extends DatabaseTestCase
{
    private string $dir;
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

        $this->dir = sys_get_temp_dir() . '/rw-apply-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->dir);

        foreach (['Filters.php', 'Auth.php', 'Routes.php'] as $file) {
            copy(SUPPORTPATH . 'fixtures/host/' . $file, $this->dir . $file);
        }

        @unlink(SetupState::file());
        Installer::code();
        $this->dropModuleTables();
    }

    protected function tearDown(): void
    {
        ConfigEditor::useLinter(null);

        // The next test's refresh rolls the module back: its migrations must be recorded.
        if ($this->db->table('migrations')->where('namespace', 'RoleWarden')->countAllResults() === 0) {
            $this->dropModuleTables();
            service('migrations', null, null, false)->setNamespace('RoleWarden')->latest('tests');
        }

        foreach ([GroupImport::DEFERRED, RoleWardenSeeder::DEFAULT_ROLES_KEY, PanelRole::KEY, DefaultRole::KEY, Visibility::KEY] as $key) {
            setting()->forget($key);
        }

        foreach (glob($this->dir . '*') ?: [] as $file) {
            @chmod($file, 0666);
            @unlink($file);
        }

        @rmdir($this->dir);
        @unlink(SetupState::file());
        Readiness::forget();

        parent::tearDown();
    }

    public function testApplyAdaptEndToEnd(): void
    {
        $state = $this->state(['superAdmin' => ['kind' => 'existing', 'id' => $this->boss]]);

        $result = Setup::apply($state, $this->helpdesk(), $this->dir);

        $this->assertSame(['ok' => true, 'failedStep' => null, 'messages' => []], $result);
        Readiness::forget();
        $this->assertTrue(Readiness::tablesExist());
        $this->assertNotNull($this->roleId('operatore'));
        $this->assertNotNull($this->roleId('cliente'));
        $this->assertNull($this->roleId('user'));
        $this->assertSame('admin', setting(PanelRole::KEY));
        $this->assertSame(['admin', 'super-admin'], $this->rolesOf($this->boss));
        $this->assertSame(['operatore'], $this->rolesOf($this->operator));

        $this->assertTrue(ConfigEditor::hasFilterExcept($this->read('Filters.php'), Setup::EXCEPTS));
        $this->assertTrue(ConfigEditor::hasUserProvider($this->read('Auth.php')));
        $this->assertFalse(ConfigEditor::hasLoginView($this->read('Auth.php'))); // visibility: admins
        $this->assertTrue(ConfigEditor::hasRouteRegistrar($this->read('Routes.php')));

        $saved = SetupState::load();
        $this->assertTrue($saved->completed());
        $this->assertSame(['Filters.php', 'Auth.php', 'Routes.php'], array_map(static fn (array $e): string => basename($e['path']), $saved->edits()));
        $this->assertFileDoesNotExist(Installer::codeFile());
    }

    /** Review Focus 3: a failure leaves an exact log, and a second run neither repeats nor duplicates. */
    public function testResumeAfterFailure(): void
    {
        $hash  = service('passwords')->hash('Wizard-Str0ng-Pass!');
        $state = $this->state(['superAdmin' => ['kind' => 'new', 'username' => 'chief', 'email' => 'chief@example.test', 'passwordHash' => $hash]]);
        chmod($this->dir . 'Filters.php', 0444);
        $users = $this->db->table('users')->countAllResults();

        $first = Setup::apply($state, $this->helpdesk(), $this->dir);

        $this->assertFalse($first['ok']);
        $this->assertSame('filters', $first['failedStep']);
        $this->assertStringContainsString('rolewarden/setup*', implode("\n", $first['messages']));
        $saved = SetupState::load();

        foreach (['migrations', 'roles', 'superAdmin'] as $step) {
            $this->assertTrue($saved->done($step), $step);
        }

        $this->assertFalse($saved->done('filters'));
        $this->assertFalse($saved->completed());
        $this->assertSame([], $saved->edits());
        $this->assertFileDoesNotExist($this->dir . 'Filters.php.rolewarden-bak');
        // R2: the user exists, the hash is gone from the manifest.
        $this->assertSame($users + 1, $this->db->table('users')->countAllResults());
        $this->assertArrayNotHasKey('passwordHash', $saved->choices()['superAdmin']);
        $this->assertStringNotContainsString($hash, (string) file_get_contents(SetupState::file()));
        $counts = $this->counts();

        chmod($this->dir . 'Filters.php', 0666);
        $second = Setup::apply($saved, $this->helpdesk(), $this->dir);

        $this->assertTrue($second['ok']);
        $this->assertSame($counts, $this->counts());
        $this->assertSame($users + 1, $this->db->table('users')->countAllResults());
        $this->assertCount(3, SetupState::load()->edits());
        $this->assertTrue(SetupState::load()->completed());
    }

    public function testUnrecognisedFileShowsManualText(): void
    {
        $line = 'public string $userProvider = UserModel::class;';
        file_put_contents($this->dir . 'Auth.php', str_replace($line, '', $this->read('Auth.php')));
        $state = $this->state(['superAdmin' => ['kind' => 'existing', 'id' => $this->boss]]);

        $first = Setup::apply($state, $this->helpdesk(), $this->dir);

        $this->assertFalse($first['ok']);
        $this->assertSame('wiring', $first['failedStep']);
        $text = implode("\n", $first['messages']);
        $this->assertStringContainsString('app/Config/Auth.php', $text);
        $this->assertStringContainsString('\RoleWarden\Models\UserModel::class', $text);
        $this->assertFalse(SetupState::load()->done('wiring'));

        // "I edited the file by hand, continue": the file is checked again, nothing logged for it.
        file_put_contents($this->dir . 'Auth.php', str_replace('class Auth extends ShieldAuth' . "\n{", 'class Auth extends ShieldAuth' . "\n{\n    public string \$userProvider = \\RoleWarden\\Models\\UserModel::class;", $this->read('Auth.php')));
        $this->assertTrue(ConfigEditor::hasUserProvider($this->read('Auth.php')));

        $second = Setup::apply(SetupState::load(), $this->helpdesk(), $this->dir);

        $this->assertTrue($second['ok']);
        $this->assertSame(['Filters.php', 'Routes.php'], array_map(static fn (array $e): string => basename($e['path']), SetupState::load()->edits()));
    }

    /** The first host file is written only once the tables exist; until then the host is untouched. */
    public function testHostKeepsWorkingBeforeWiring(): void
    {
        $files = array_map(fn (string $f): string => md5_file($this->dir . $f), ['Auth.php' => 'Auth.php', 'Routes.php' => 'Routes.php']);
        $seen  = [];
        ConfigEditor::useLinter(function (string $path) use (&$seen, $files): bool {
            if ($seen === []) {
                Readiness::forget();
                $seen = ['file' => basename($path), 'tables' => Readiness::tablesExist(), 'others' => array_map(fn (string $f): string => md5_file($this->dir . $f), array_keys($files))];
            }

            return true;
        });

        $this->assertTrue(Setup::apply($this->state(['superAdmin' => ['kind' => 'existing', 'id' => $this->boss]]), $this->helpdesk(), $this->dir)['ok']);

        $this->assertSame(['file' => 'Filters.php', 'tables' => true, 'others' => array_values($files)], $seen);
    }

    /** R12: a saved answer that no longer holds stops the run before anything is written. */
    public function testInvalidChoiceStopsBeforeWriting(): void
    {
        $result = Setup::apply($this->state(['defaultRole' => 'ghost', 'superAdmin' => ['kind' => 'existing', 'id' => $this->boss]]), $this->helpdesk(), $this->dir);

        $this->assertSame('newUsers', $result['failedStep']);
        $this->assertFalse($this->db->tableExists(config('RoleWarden')->table('roles'), false));
        $this->assertFileDoesNotExist(SetupState::file());

        $this->db->table('users')->where('id', $this->boss)->update(['active' => 0]);
        $this->assertSame('superAdmin', Setup::apply($this->state(['superAdmin' => ['kind' => 'existing', 'id' => $this->boss]]), $this->helpdesk(), $this->dir)['failedStep']);
    }

    /** @param array<string, mixed> $extra */
    private function state(array $extra): SetupState
    {
        $state = new SetupState();

        foreach ($extra + ['locale' => 'en', 'mode' => 'adapt', 'panelRole' => 'admin', 'collisions' => [], 'defaultRole' => 'cliente', 'visibility' => 'admins'] as $key => $value) {
            $state->setChoice($key, $value);
        }

        return $state;
    }

    private function read(string $file): string
    {
        return (string) file_get_contents($this->dir . $file);
    }

    /** As on a host that runs Shield but not RoleWarden yet. */
    private function dropModuleTables(): void
    {
        $prefix = config('RoleWarden')->table('');
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->db->listTables(false) as $table) {
            if (str_starts_with($table, $prefix)) {
                Database::forge($this->db)->dropTable($table);
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        $this->db->table('migrations')->where('namespace', 'RoleWarden')->delete();
        $this->db->resetDataCache();
        Readiness::forget();
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

    /** The helpdesk's AuthGroups. */
    private function helpdesk(): object
    {
        return new class () {
            public string $defaultGroup = 'cliente';
            public array $groups = [
                'admin' => ['title' => 'Admin'],
                'operatore' => ['title' => 'Operatore'],
                'cliente' => [],
            ];
            public array $permissions = ['tickets.create' => 'a', 'tickets.reply' => 'a', 'admin.users' => 'a'];
            public array $matrix = [
                'admin' => ['tickets.*', 'admin.*'],
                'operatore' => ['tickets.create', 'tickets.reply'],
                'cliente' => ['tickets.create'],
            ];
        };
    }
}
