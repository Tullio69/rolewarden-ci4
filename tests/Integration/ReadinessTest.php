<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Config\Factories;
use CodeIgniter\Shield\Authorization\AuthorizationException;
use CodeIgniter\Config\Services;
use CodeIgniter\Events\Events;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use RoleWarden\Account\SecurityMail;
use RoleWarden\Config\RouteRegistrar;
use RoleWarden\Install\Readiness;
use RoleWarden\Models\UserModel;
use RoleWarden\Settings\SignIn;
use RoleWarden\Tests\_support\DatabaseTestCase;

/**
 * The module is loaded (user provider, routes, events) but its migrations have not run:
 * the host keeps working and only /rolewarden/* says the module is not installed yet.
 */
final class ReadinessTest extends DatabaseTestCase
{
    use FeatureTestTrait;

    private bool $dropped = false;

    protected function setUp(): void
    {
        parent::setUp();
        $routes = service('routes');
        $routes->resetRoutes();
        service('auth')->routes($routes);
        RouteRegistrar::register($routes);
        helper(['setting', 'auth', 'form']);
        config('Security')->csrfProtection = 'session';
        // As in the real install: the host points Shield at RoleWarden's user model.
        config('Auth')->userProvider = UserModel::class;
        Services::resetSingle('auth');
    }

    protected function tearDown(): void
    {
        if ($this->dropped) {
            $this->restoreModuleTables();
        }

        // Empty SecurityMail's queue here, so nothing is sent at PHPUnit's shutdown.
        $this->flushMail();
        Readiness::forget();
        foreach (SignIn::NUMBERS as [$key]) {
            setting()->forget($key);
        }
        Factories::reset('config');
        Services::resetSingle('auth');
        service('routes')->resetRoutes();
        parent::tearDown();
    }

    public function testHostKeepsWorkingWithoutTables(): void
    {
        $id = $this->makeUser('host@example.com');
        $this->dropModuleTables();

        $user = (new UserModel())->findById($id);

        $this->assertFalse($user->can('users.view'));
        $this->assertFalse($user->hasPermission('users.view'));
        $this->assertSame([], $user->getGroups());
        $this->assertFalse($user->inGroup('admin'));
        // Nothing to resolve: still safe.
        $user->syncGroups();
        $this->assertSame([], $user->getGroups());
    }

    public function testGroupWritesSayModuleIsNotInstalled(): void
    {
        $id = $this->makeUser('writes@example.com');
        $this->dropModuleTables();
        $user = (new UserModel())->findById($id);

        foreach (['addGroup', 'removeGroup', 'syncGroups'] as $method) {
            try {
                $user->{$method}('admin');
                $this->fail($method . ' did not throw');
            } catch (AuthorizationException $e) {
                $this->assertSame('RoleWarden is not installed yet: groups cannot be changed.', $e->getMessage());
            }
        }
    }

    public function testHostSignInPathDoesNotTouchMissingTables(): void
    {
        $id = $this->makeUser('signin@example.com');
        $this->dropModuleTables();
        $users = new UserModel();
        $user  = $users->findById($id);

        // Shield's events around sign-in, registration and sign-out.
        Events::trigger('login', $user);
        Events::trigger('logout', $user);
        Events::trigger('register', $user);
        $users->addToDefaultGroup($user);

        // The failed sign-in that reaches the lock threshold alerts the admins (a role lookup).
        setting('RoleWarden.lockAttempts', 1);
        $this->db->table(config('Auth')->tables['logins'])->insert([
            'ip_address' => '127.0.0.1', 'id_type' => 'email_password', 'identifier' => 'signin@example.com',
            'user_id' => $id, 'date' => date('Y-m-d H:i:s'), 'success' => 0,
        ]);
        Events::trigger('failedLogin', ['email' => 'signin@example.com']);
        // No admin can hold security.alerts yet; the account owner is still told.
        $this->assertSame(['signin@example.com'], $this->flushMail());

        // The user model's callbacks: protection checks and cache invalidation.
        $users->update($id, ['active' => 0]);
        $users->delete($id);

        $this->assertNull($users->findById($id));
    }

    public function testHostSignInFormWorksWithoutTables(): void
    {
        $this->makeUser('form@example.com');
        $this->dropModuleTables();

        $result = $this->post('login', ['email' => 'form@example.com', 'password' => 'Str0ng-Passw0rd-for-tests', csrf_token() => csrf_hash()]);

        $result->assertRedirect();
        $this->assertNotNull(auth()->user());
        $this->assertFalse(auth()->user()->can('users.view'));
    }

    public function testNothingIsCachedWhileTablesAreMissing(): void
    {
        $id = $this->makeUser('later@example.com');
        // Ids restart with every refresh: drop what an earlier test cached under this one.
        service('rolewarden')->forgetUser($id);
        $this->dropModuleTables();
        $this->assertFalse((new UserModel())->findById($id)->can('settings.view'));

        // The migrations run afterwards: the answer must come from the new tables, not a cached "nothing".
        // A granted override, so the answer does not depend on what the seeder gives each role.
        $this->restoreModuleTables();
        $cfg = config('RoleWarden');
        $this->db->table($cfg->table('user_permissions'))->insert([
            'user_id'       => $id,
            'permission_id' => (int) $this->db->table($cfg->table('permissions'))->where('slug', 'settings.view')->get()->getRow()->id,
            'granted'       => 1,
        ]);

        $this->assertTrue((new UserModel())->findById($id)->can('settings.view'));
    }

    public function testPanelShowsNotInstalledPage(): void
    {
        $this->dropModuleTables();

        $result = $this->get('rolewarden');

        $result->assertStatus(503);
        $result->assertSee('not installed yet');
        $result->assertSee('rolewarden/install');
    }

    public function testInstallerStaysReachable(): void
    {
        $this->dropModuleTables();

        $this->assertNotSame(503, $this->get('rolewarden/install')->response()->getStatusCode());
    }

    /**
     * Sends nothing: empties SecurityMail's queue through its "rolewarden.mail" hook.
     *
     * @return list<string> the recipients that were queued
     */
    private function flushMail(): array
    {
        $to = [];
        $catch = static function (array $message) use (&$to): bool {
            $to[] = $message['to'];

            return false;
        };
        Events::on('rolewarden.mail', $catch, Events::PRIORITY_HIGH);
        SecurityMail::flush();
        Events::removeListener('rolewarden.mail', $catch);

        return $to;
    }

    private function dropModuleTables(): void
    {
        $prefix = config('RoleWarden')->table('');
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($this->db->listTables() as $table) {
            if (str_starts_with($table, $prefix)) {
                Database::forge($this->db)->dropTable($table);
            }
        }

        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');
        $this->dropped = true;
        Readiness::forget();
    }

    /** Runs the module's migrations again, so the next test's refresh can roll them back. */
    private function restoreModuleTables(): void
    {
        $this->db->table('migrations')->where('namespace', 'RoleWarden')->delete();
        $runner = service('migrations', null, null, false);
        $runner->setNamespace('RoleWarden')->latest('tests');
        $this->dropped = false;
        Readiness::forget();
    }
}
