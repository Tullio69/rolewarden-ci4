<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Config\Factories;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use RoleWarden\Controllers\SetupController;
use RoleWarden\Database\Seeds\RoleWardenSeeder;
use RoleWarden\Install\GroupImport;
use RoleWarden\Install\HostScan;
use RoleWarden\Install\Installer;
use RoleWarden\Install\SetupState;
use RoleWarden\Models\UserModel;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Settings\PanelRole;
use RoleWarden\Settings\Visibility;
use RoleWarden\Tests\_support\DatabaseTestCase;

/**
 * The /rolewarden/setup wizard. Questions 1 to 6: every answer lands in
 * SetupState, nothing else is written (no table, no host file, no setting).
 * Confirm applies through Setup::apply (covered in depth by SetupApplyTest).
 */
final class SetupWizardTest extends DatabaseTestCase
{
    use AuthenticationTesting;
    use FeatureTestTrait;

    private string $configDir;

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];
        // Shield refuses cookie-based CSRF once auth() runs (the signed-in test).
        config('Security')->csrfProtection = 'session';
        @unlink(SetupState::file());
        $this->configDir = sys_get_temp_dir() . '/rw-setup-' . bin2hex(random_bytes(4)) . '/';
        mkdir($this->configDir);

        foreach (['Filters.php', 'Auth.php', 'Routes.php'] as $file) {
            copy(SUPPORTPATH . 'fixtures/host/' . $file, $this->configDir . $file);
        }

        SetupController::useConfigPath($this->configDir);
        Factories::injectMock('config', 'AuthGroups', $this->helpdesk());
    }

    protected function tearDown(): void
    {
        SetupController::useConfigPath(null);
        @unlink(SetupState::file());
        helper('setting');

        foreach ([GroupImport::DEFERRED, RoleWardenSeeder::DEFAULT_ROLES_KEY, PanelRole::KEY, DefaultRole::KEY, Visibility::KEY] as $key) {
            setting()->forget($key);
        }

        foreach (glob($this->configDir . '*') ?: [] as $file) {
            chmod($file, 0666);
            unlink($file);
        }

        @rmdir($this->configDir);

        parent::tearDown();
    }

    public function testWithoutCodeShowsCodeStep(): void
    {
        $result = $this->get('rolewarden/setup');

        $result->assertOK();
        $this->assertSeeHtml($result, 'name="code"');
        $this->assertSeeHtml($result, 'install-code.txt');
        $this->assertFileDoesNotExist(SetupState::file());
    }

    public function testWrongCodeRejected(): void
    {
        $result = $this->send(['step' => 'code', 'code' => 'not-the-code']);

        $result->assertOK();
        $this->assertSeeHtml($result, lang('RoleWarden.setup.wrongCode'));
        $this->assertNotTrue($_SESSION['rw_setup_verified'] ?? null);
    }

    public function testPostWithoutCsrfTokenRejected(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('rolewarden/setup', ['step' => 'code', 'code' => Installer::code()]);
    }

    /** Review Focus 5: a signed-in user of the host still needs the one-time code. */
    public function testCodeEvenWhenSignedIn(): void
    {
        helper('auth');
        $user = model(UserModel::class)->findById($this->makeUser('host@example.test'));

        $result = $this->actingAs($user)->get('rolewarden/setup');

        $result->assertOK();
        $this->assertSeeHtml($result, 'name="code"');
    }

    public function testFullQuestionFlowWritesNothing(): void
    {
        $tables   = $this->tables();
        $settings = $this->db->table('settings')->countAllResults();
        $users    = $this->db->table('users')->countAllResults();
        $files    = array_map('md5_file', glob($this->configDir . '*') ?: []);

        $this->assertSeeHtml($this->send(['step' => 'code', 'code' => Installer::code()]), 'name="step" value="requirements"');
        $filters = $this->send(['step' => 'requirements']);
        $this->assertSeeHtml($filters, 'name="step" value="filters"');
        $this->assertSeeHtml($filters, 'rolewarden/assets/*');
        $this->assertSeeHtml($filters, 'rolewarden/setup*');
        $this->assertSeeHtml($this->send(['step' => 'filters']), 'name="mode"');
        $this->assertSeeHtml($this->send(['step' => 'roles', 'mode' => 'import']), 'name="collisions[admin][action]"');
        $this->assertSeeHtml($this->send(['step' => 'collisions', 'collisions' => ['admin' => ['action' => 'rename', 'newSlug' => 'app-admin']]]), 'name="defaultRole"');
        $this->assertSeeHtml($this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']), 'name="kind"');

        // Back keeps the answer already given.
        $back = $this->send(['step' => 'superAdmin', 'back' => '1']);
        $this->assertSeeHtml($back, 'name="step" value="newUsers"');
        $this->assertSeeHtml($back, '<option value="cliente" selected>');
        $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);

        $this->assertSeeHtml($this->send([
            'step' => 'superAdmin', 'kind' => 'new', 'username' => 'boss', 'email' => 'boss@example.test',
            'password' => 'Wizard-Str0ng-Pass!', 'password_confirm' => 'Wizard-Str0ng-Pass!',
        ]), 'name="visibility"');
        $confirm = $this->send(['step' => 'visibility', 'visibility' => 'admins']);
        $this->assertSeeHtml($confirm, 'name="step" value="confirm"');
        $this->assertSeeHtml($confirm, 'RouteRegistrar::register($routes);');
        $this->assertSeeHtml($confirm, 'RoleWarden\Models\UserModel');

        $this->assertSame($tables, $this->tables());
        $this->assertSame($settings, $this->db->table('settings')->countAllResults());
        $this->assertSame(0, $this->db->table('settings')->like('class', 'RoleWarden')->countAllResults());
        $this->assertSame($users, $this->db->table('users')->countAllResults());
        $this->assertSame($files, array_map('md5_file', glob($this->configDir . '*') ?: []));

        $choices = SetupState::load()->choices();
        $hash    = $choices['superAdmin']['passwordHash'] ?? '';
        unset($choices['superAdmin']['passwordHash']);
        $this->assertSame([
            'locale' => 'en',
            'mode' => 'import',
            'panelRole' => '',
            'collisions' => ['admin' => ['action' => 'rename', 'newSlug' => 'app-admin']],
            'defaultRole' => 'cliente',
            'superAdmin' => ['kind' => 'new', 'username' => 'boss', 'email' => 'boss@example.test'],
            'visibility' => 'admins',
        ], $choices);
        $this->assertTrue(service('passwords')->verify('Wizard-Str0ng-Pass!', $hash));
        $this->assertStringNotContainsString('Wizard-Str0ng-Pass!', (string) file_get_contents(SetupState::file()));
    }

    public function testCollisionsStepOnlyForImportWithCollisions(): void
    {
        // The test database already holds RoleWarden's system admin: adapt needs groups without that name.
        Factories::injectMock('config', 'AuthGroups', $this->helpdesk(['operatore' => [], 'cliente' => []]));
        $this->verified();
        $this->assertSeeHtml($this->send(['step' => 'roles', 'mode' => 'adapt', 'panelRole' => 'operatore']), 'name="step" value="newUsers"');

        $this->send(['step' => 'newUsers', 'back' => '1']);
        Factories::injectMock('config', 'AuthGroups', $this->helpdesk());
        $this->assertSeeHtml($this->send(['step' => 'roles', 'mode' => 'import']), 'name="step" value="collisions"');
        // Every colliding group needs a choice: RoleSetup::apply refuses one without.
        $this->assertSeeHtml($this->send(['step' => 'collisions']), 'name="step" value="collisions"');

        Factories::injectMock('config', 'AuthGroups', $this->helpdesk(['operatore' => [], 'cliente' => []]));
        $this->send(['step' => 'collisions', 'back' => '1']);
        $this->assertSeeHtml($this->send(['step' => 'roles', 'mode' => 'import']), 'name="step" value="newUsers"');
    }

    /** Review Focus 4: only active users can be chosen; a forged id is refused. */
    public function testTamperedSuperAdminIdRejected(): void
    {
        $active   = $this->makeUser('active@example.test');
        $inactive = $this->makeUser('inactive@example.test');
        $this->db->table('users')->where('id', $inactive)->update(['active' => 0]);
        Factories::injectMock('config', 'AuthGroups', $this->helpdesk(['operatore' => [], 'cliente' => []]));

        $this->verified();
        $this->send(['step' => 'roles', 'mode' => 'adapt', 'panelRole' => '']);
        $page = $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);
        $this->assertSeeHtml($page, '<option value="' . $active . '"');
        $this->assertDontSeeHtml($page, '<option value="' . $inactive . '"');

        $this->assertSeeHtml($this->send(['step' => 'superAdmin', 'kind' => 'existing', 'id' => (string) $inactive]), 'name="step" value="superAdmin"');
        $this->assertArrayNotHasKey('superAdmin', SetupState::load()->choices());

        $this->assertSeeHtml($this->send(['step' => 'superAdmin', 'kind' => 'existing', 'id' => (string) $active]), 'name="step" value="visibility"');
        $this->assertSame(['kind' => 'existing', 'id' => $active], SetupState::load()->choices()['superAdmin']);
    }

    /**
     * RoleWarden's system roles already in the database (an earlier `spark migrate`): adapt on a
     * group of the same name would only fail after the migrations, so the question refuses it.
     */
    public function testAdaptRefusedOnGroupNamedLikeExistingSystemRole(): void
    {
        $this->assertSame(1, $this->db->table(config('RoleWarden')->table('roles'))->where(['slug' => 'admin', 'is_system' => 1])->countAllResults());
        $settings = $this->db->table('settings')->countAllResults();
        $roles    = $this->db->table(config('RoleWarden')->table('roles'))->countAllResults();
        $files    = array_map('md5_file', glob($this->configDir . '*') ?: []);
        $this->verified();

        $page = $this->send(['step' => 'roles', 'mode' => 'adapt', 'panelRole' => 'admin']);

        $this->assertSeeHtml($page, 'name="step" value="roles"');
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.adaptBlocked', ['admin'])));
        $this->assertArrayNotHasKey('mode', SetupState::load()->choices());
        $this->assertSame($settings, $this->db->table('settings')->countAllResults());
        $this->assertSame($roles, $this->db->table(config('RoleWarden')->table('roles'))->countAllResults());
        $this->assertSame($files, array_map('md5_file', glob($this->configDir . '*') ?: []));

        // Import resolves the same group in the collisions step.
        $this->assertSeeHtml($this->send(['step' => 'roles', 'mode' => 'import']), 'name="collisions[admin][action]"');
    }

    /** R17: permissions given to users directly in Shield are not imported; the roles step and the summary say so. */
    public function testDirectShieldPermissionsWarned(): void
    {
        $this->assertSame(['users' => 0, 'permissions' => 0], HostScan::directPermissions());
        $a   = $this->makeUser('a@example.test');
        $b   = $this->makeUser('b@example.test');
        $now = date('Y-m-d H:i:s');
        $this->db->table('auth_permissions_users')->insertBatch([
            ['user_id' => $a, 'permission' => 'tickets.create', 'created_at' => $now],
            ['user_id' => $a, 'permission' => 'tickets.reply', 'created_at' => $now],
            ['user_id' => $b, 'permission' => 'tickets.create', 'created_at' => $now],
        ]);
        $this->assertSame(['users' => 2, 'permissions' => 3], HostScan::directPermissions());
        $warning = esc(lang('RoleWarden.setup.directPermissions', [2, 3]));

        $this->send(['step' => 'code', 'code' => Installer::code()]);
        $this->send(['step' => 'requirements']);
        $roles = $this->send(['step' => 'filters']);
        $this->assertSeeHtml($roles, 'name="step" value="roles"');
        $this->assertSeeHtml($roles, $warning);

        $this->send(['step' => 'roles', 'mode' => 'import']);
        $this->send(['step' => 'collisions', 'collisions' => ['admin' => ['action' => 'skip']]]);
        $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);
        $this->send(['step' => 'superAdmin', 'kind' => 'existing', 'id' => (string) $a]);
        $confirm = $this->send(['step' => 'visibility', 'visibility' => 'admins']);
        $this->assertSeeHtml($confirm, 'name="step" value="confirm"');
        $this->assertSeeHtml($confirm, $warning);
    }

    /** Confirm applies; a failed run shows what to paste and resumes; the summary lists the files; then 403. */
    public function testConfirmAppliesThenShowsSummary(): void
    {
        $this->verified();
        $this->send(['step' => 'roles', 'mode' => 'import']);
        $this->send(['step' => 'collisions', 'collisions' => ['admin' => ['action' => 'rename', 'newSlug' => 'app-admin']]]);
        $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);
        $this->send(['step' => 'superAdmin', 'kind' => 'new', 'username' => 'boss', 'email' => 'boss@example.test', 'password' => 'Wizard-Str0ng-Pass!', 'password_confirm' => 'Wizard-Str0ng-Pass!']);
        $this->send(['step' => 'visibility', 'visibility' => 'admins']);
        chmod($this->configDir . 'Routes.php', 0444);

        $failed = $this->send(['step' => 'confirm']);

        $this->assertSeeHtml($failed, 'role="alert"');
        $this->assertSeeHtml($failed, 'RouteRegistrar::register($routes);');
        $this->assertSeeHtml($failed, esc(lang('RoleWarden.setup.manualContinue')));
        $this->assertFalse(SetupState::load()->completed());

        chmod($this->configDir . 'Routes.php', 0666);
        $done = $this->send(['step' => 'confirm']);

        $done->assertOK();
        $this->assertSeeHtml($done, esc(lang('RoleWarden.setup.doneTitle')));
        $this->assertSeeHtml($done, 'app/Config/Filters.php');
        $this->assertSeeHtml($done, 'app/Config/Routes.php');
        $this->assertSeeHtml($done, esc(lang('RoleWarden.setup.doneSignInAgain')));
        $this->assertSame(1, $this->db->table('users')->where('username', 'boss')->countAllResults());
        $this->get('rolewarden/setup')->assertStatus(403);
    }

    /** R15: once an apply step is done, Back and answers are refused; no unused password hash is stored. */
    public function testAnswersLockedAfterPartialApply(): void
    {
        $this->verified();
        $this->send(['step' => 'roles', 'mode' => 'import']);
        $this->send(['step' => 'collisions', 'collisions' => ['admin' => ['action' => 'rename', 'newSlug' => 'app-admin']]]);
        $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);
        $this->send(['step' => 'superAdmin', 'kind' => 'new', 'username' => 'boss', 'email' => 'boss@example.test', 'password' => 'Wizard-Str0ng-Pass!', 'password_confirm' => 'Wizard-Str0ng-Pass!']);
        $this->send(['step' => 'visibility', 'visibility' => 'admins']);
        chmod($this->configDir . 'Filters.php', 0444);

        $failed = $this->send(['step' => 'confirm']);
        $this->assertTrue(SetupState::load()->done('superAdmin'));
        $this->assertDontSeeHtml($failed, 'name="back"');
        $choices = SetupState::load()->choices();

        $back = $this->send(['step' => 'confirm', 'back' => '1']);
        $this->assertSeeHtml($back, 'name="step" value="confirm"');
        $this->assertSeeHtml($back, esc(lang('RoleWarden.setup.locked')));
        $answer = $this->send([
            'step' => 'superAdmin', 'kind' => 'new', 'username' => 'other', 'email' => 'other@example.test',
            'password' => 'Wizard-Str0ng-Pass2!', 'password_confirm' => 'Wizard-Str0ng-Pass2!',
        ]);
        $this->assertSeeHtml($answer, esc(lang('RoleWarden.setup.locked')));
        $this->send(['step' => 'visibility', 'visibility' => 'all']);

        $this->assertSame($choices, SetupState::load()->choices());
        $this->assertStringNotContainsString('passwordHash', (string) file_get_contents(SetupState::file()));
    }

    /** R20: the super admin can still be chosen again until its own apply step is done; the other answers stay locked. */
    public function testSuperAdminReopenedUntilItsStepIsDone(): void
    {
        $first  = $this->makeUser('first@example.test');
        $second = $this->makeUser('second@example.test');
        $state  = new SetupState();

        foreach (['locale' => 'en', 'mode' => 'import', 'panelRole' => '', 'collisions' => ['admin' => ['action' => 'skip']], 'defaultRole' => 'cliente', 'superAdmin' => ['kind' => 'existing', 'id' => $first], 'visibility' => 'admins'] as $key => $value) {
            $state->setChoice($key, $value);
        }

        $state->markDone('migrations');
        $state->save();

        $confirm = $this->send(['step' => 'code', 'code' => Installer::code()]);
        $this->assertSeeHtml($confirm, 'name="step" value="confirm"');
        $this->assertSeeHtml($confirm, 'name="back"');

        $this->assertSeeHtml($this->send(['step' => 'confirm', 'back' => '1']), 'name="step" value="superAdmin"');
        $this->assertSeeHtml($this->send(['step' => 'superAdmin', 'back' => '1']), esc(lang('RoleWarden.setup.locked')));
        $this->assertSeeHtml($this->send(['step' => 'superAdmin', 'kind' => 'existing', 'id' => (string) $second]), 'name="step" value="confirm"');
        $this->assertSeeHtml($this->send(['step' => 'visibility', 'visibility' => 'all']), esc(lang('RoleWarden.setup.locked')));

        $choices = SetupState::load()->choices();
        $this->assertSame(['kind' => 'existing', 'id' => $second], $choices['superAdmin']);
        $this->assertSame('admins', $choices['visibility']);
    }

    /** Collaudo W2 D1: a new super admin needs a free username and email, as Shield's registration rules say. */
    public function testNewSuperAdminWithTakenUsernameOrEmailRefused(): void
    {
        $alice = $this->makeUser('alice@host.test');
        $this->db->table('users')->where('id', $alice)->update(['username' => 'alice']);

        $this->verified();
        $this->send(['step' => 'roles', 'mode' => 'import']);
        $this->send(['step' => 'collisions', 'collisions' => ['admin' => ['action' => 'skip']]]);
        $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);

        foreach ([['alice', 'other@host.test', 'username'], ['other', 'alice@host.test', 'email']] as [$username, $email, $field]) {
            $page = $this->send(['step' => 'superAdmin', 'kind' => 'new', 'username' => $username, 'email' => $email, 'password' => 'Wizard-Str0ng-Pass!', 'password_confirm' => 'Wizard-Str0ng-Pass!']);
            $this->assertSeeHtml($page, 'name="step" value="superAdmin"');
            $this->assertSeeHtml($page, 'id="rw-setup-' . $field . '-error"');
            $this->assertArrayNotHasKey('superAdmin', SetupState::load()->choices());
        }

        $this->assertSeeHtml($this->send(['step' => 'superAdmin', 'kind' => 'new', 'username' => 'other', 'email' => 'other@host.test', 'password' => 'Wizard-Str0ng-Pass!', 'password_confirm' => 'Wizard-Str0ng-Pass!']), 'name="step" value="visibility"');
    }

    /** Collaudo W2 D2: after an interrupted apply the confirm page says what is already applied and continues. */
    public function testConfirmAfterInterruptedApplyListsDoneSteps(): void
    {
        $state = $this->fullState($this->makeUser('first@example.test'));
        $state->markDone('migrations');
        $state->markDone('roles');
        $state->save();
        $routes = $this->configDir . 'Routes.php';
        file_put_contents($routes, (string) \RoleWarden\Install\ConfigEditor::withRouteRegistrar((string) file_get_contents($routes)));

        $page = $this->send(['step' => 'code', 'code' => Installer::code()]);

        $this->assertSeeHtml($page, 'name="step" value="confirm"');
        $this->assertDontSeeHtml($page, esc(lang('RoleWarden.setup.confirmIntro')));
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.confirmIntroResume')));
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.appliedTitle')));
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.step.migrations')));
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.step.roles')));
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.continueInstall')));
        $this->assertDontSeeHtml($page, '>' . esc(lang('RoleWarden.setup.apply')) . '</button>');
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.fileApplied', ['app/Config/Routes.php'])));
        $this->assertDontSeeHtml($page, esc(lang('RoleWarden.setup.fileUnchanged', ['app/Config/Routes.php'])));
    }

    /** An answer that no longer holds (R12) shows Back only, never "Apply again", also for the super admin. */
    public function testInvalidAnswerHidesApply(): void
    {
        $inactive = $this->makeUser('gone@example.test');
        $this->db->table('users')->where('id', $inactive)->update(['active' => 0]);

        // Not started: invalidSaved.
        $this->fullState($inactive)->save();
        $page = $this->send(['step' => 'code', 'code' => Installer::code()]);
        $page = $this->send(['step' => 'confirm']);
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.invalidSaved', [lang('RoleWarden.setup.superAdminTitle')])));
        $this->assertDontSeeHtml($page, esc(lang('RoleWarden.setup.retry')));
        $this->assertSeeHtml($page, 'name="back"');

        // Started, super admin still open (R20): invalidReopen.
        $state = $this->fullState($inactive);
        $state->markDone('migrations');
        $state->save();
        $page = $this->send(['step' => 'confirm']);
        $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.invalidReopen', [lang('RoleWarden.setup.superAdminTitle')])));
        $this->assertDontSeeHtml($page, esc(lang('RoleWarden.setup.retry')));
        $this->assertSeeHtml($page, 'name="back"');
    }

    private function fullState(int $superAdmin): SetupState
    {
        $state = new SetupState();

        foreach (['locale' => 'en', 'mode' => 'import', 'panelRole' => '', 'collisions' => ['admin' => ['action' => 'skip']], 'defaultRole' => 'cliente', 'superAdmin' => ['kind' => 'existing', 'id' => $superAdmin], 'visibility' => 'admins'] as $key => $value) {
            $state->setChoice($key, $value);
        }

        return $state;
    }

    /** R20: an existing super admin is checked against the database, not against the first 200 users listed. */
    public function testSuperAdminBeyondTheListAccepted(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('users')->insertBatch(array_map(static fn (int $i): array => ['username' => 'bulk' . $i, 'active' => 1, 'created_at' => $now, 'updated_at' => $now], range(1, 201)));
        $last = (int) $this->db->table('users')->selectMax('id')->get()->getRow()->id;
        $this->assertNotContains($last, array_column(HostScan::activeUsers(), 'id'));

        $this->verified();
        $this->send(['step' => 'roles', 'mode' => 'import']);
        $this->send(['step' => 'collisions', 'collisions' => ['admin' => ['action' => 'skip']]]);
        $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);

        $this->assertSeeHtml($this->send(['step' => 'superAdmin', 'kind' => 'existing', 'id' => (string) $last]), 'name="step" value="visibility"');
        $this->assertSame(['kind' => 'existing', 'id' => $last], SetupState::load()->choices()['superAdmin']);
    }

    /** A database error on a question page or in apply's checks is logged; the page shows only a generic message. */
    public function testDatabaseErrorShowsNoSql(): void
    {
        $this->verified();
        $this->send(['step' => 'roles', 'mode' => 'import']);
        $this->send(['step' => 'collisions', 'collisions' => ['admin' => ['action' => 'skip']]]);
        $this->send(['step' => 'newUsers', 'defaultRole' => 'cliente']);
        $this->send(['step' => 'superAdmin', 'kind' => 'existing', 'id' => (string) $this->makeUser('boss@example.test')]);
        $this->db->query('RENAME TABLE ' . $this->db->prefixTable('users') . ' TO ' . $this->db->prefixTable('users_moved'));

        try {
            // The confirm page lists the users; the apply checks the chosen one (R12) before its own try.
            foreach ([['step' => 'visibility', 'visibility' => 'admins'], ['step' => 'confirm']] as $post) {
                $page = $this->send($post);
                $page->assertStatus(500);
                $this->assertSeeHtml($page, esc(lang('RoleWarden.setup.scanFailedIntro')));
                $this->assertDontSeeHtml($page, 'users_moved');
                $this->assertDontSeeHtml($page, 'SELECT');
            }
        } finally {
            $this->db->query('RENAME TABLE ' . $this->db->prefixTable('users_moved') . ' TO ' . $this->db->prefixTable('users'));
        }
    }

    public function testCompletedReturns403(): void
    {
        $state = new SetupState();
        $state->complete();
        $state->save();

        $result = $this->get('rolewarden/setup');

        $result->assertStatus(403);
        $this->assertSeeHtml($result, lang('RoleWarden.setup.completedTitle'));
    }

    public function testBrowserLanguageThenSelector(): void
    {
        $this->assertSeeHtml($this->withHeaders(['Accept-Language' => 'it-IT,it;q=0.9'])->get('rolewarden/setup'), '<html lang="it"');

        $this->verified();
        $this->assertSeeHtml($this->send(['step' => 'locale', 'locale' => 'it']), '<html lang="it"');
        $this->assertSame('it', SetupState::load()->choices()['locale']);
    }

    /** @return list<string> */
    private function tables(): array
    {
        $tables = $this->db->listTables(true);
        sort($tables);

        return $tables;
    }

    private function verified(): void
    {
        $this->send(['step' => 'code', 'code' => Installer::code()]);
        $this->send(['step' => 'requirements']);
        $this->send(['step' => 'filters']);
    }

    private function assertSeeHtml(TestResponse $result, string $needle): void
    {
        $this->assertStringContainsString($needle, (string) $result->response()->getBody());
    }

    private function assertDontSeeHtml(TestResponse $result, string $needle): void
    {
        $this->assertStringNotContainsString($needle, (string) $result->response()->getBody());
    }

    /** @param array<string, mixed> $post */
    private function send(array $post): TestResponse
    {
        return $this->withSession()->post('rolewarden/setup', $post + [csrf_token() => csrf_hash()]);
    }

    /** The helpdesk's AuthGroups (admin collides with RoleWarden's admin), or other groups. */
    private function helpdesk(?array $groups = null): object
    {
        $config = new class () {
            public string $defaultGroup = 'cliente';
            public array $groups = [];
            public array $permissions = [
                'tickets.create' => 'a', 'tickets.reply' => 'a', 'admin.users' => 'a',
            ];
            public array $matrix = [
                'admin' => ['tickets.*', 'admin.*'],
                'operatore' => ['tickets.create', 'tickets.reply'],
                'cliente' => ['tickets.create'],
            ];
        };
        $config->groups = $groups ?? ['admin' => ['title' => 'Admin'], 'operatore' => ['title' => 'Operatore'], 'cliente' => []];

        return $config;
    }
}
