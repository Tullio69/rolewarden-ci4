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
        $this->verified();
        $this->assertSeeHtml($this->send(['step' => 'roles', 'mode' => 'adapt', 'panelRole' => 'admin']), 'name="step" value="newUsers"');

        $this->send(['step' => 'newUsers', 'back' => '1']);
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
