<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Config\Factories;
use CodeIgniter\Events\Events;
use RoleWarden\Account\SecurityMail;
use RoleWarden\Account\ActivityLog;
use RoleWarden\Config\RouteRegistrar;
use RoleWarden\Models\UserModel;
use RoleWarden\Models\UserRoles;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Settings\Sidebar;
use RoleWarden\Settings\SignIn;
use RoleWarden\Settings\Visibility;
use RoleWarden\Tests\_support\DatabaseTestCase;

final class AccountVisibilityTest extends DatabaseTestCase
{
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $routes = service('routes');
        $routes->resetRoutes();
        service('auth')->routes($routes);
        RouteRegistrar::register($routes);
        helper(['setting', 'auth', 'form']);
        config('Security')->csrfProtection = 'session';
    }

    protected function tearDown(): void
    {
        // The settings screen test writes these keys, and Settings caches them across tests.
        foreach ([Visibility::KEY, DefaultRole::KEY, 'RoleWarden.activityRetentionDays', Sidebar::TODAY, 'RoleWarden.settingsChangedAt', 'RoleWarden.settingsChangedBy'] as $key) {
            setting()->forget($key);
        }
        foreach (SignIn::NUMBERS as [$key]) {
            setting()->forget($key);
        }
        setting()->forget('RoleWarden.sessionLifetime');
        setting()->forget('Auth.sessionConfig');
        Factories::reset('config');
        service('routes')->resetRoutes();
        parent::tearDown();
    }

    private function as(int $id): static
    {
        return $this->actingAs((new UserModel())->findById($id));
    }

    public function testAdminsModeHidesProfileFromPlainUsers(): void
    {
        setting(Visibility::KEY, Visibility::ADMINS);
        $id = $this->makeUser('plain@example.com');

        // Outside the test harness the exception handler turns this into the 404 page.
        $this->expectException(PageNotFoundException::class);
        $this->as($id)->get('rolewarden/profile');
    }

    public function testAdminsModeSendsPlainUsersFromTheDashboardBackToTheHost(): void
    {
        setting(Visibility::KEY, Visibility::ADMINS);
        $id = $this->makeUser('landing@example.com');

        // Not to rolewarden/profile, a 404 for them: with $redirects['login'] = 'rolewarden' every plain user lands here.
        $this->as($id)->get('rolewarden')->assertRedirectTo(config('Auth')->permissionDeniedRedirect());
    }

    public function testNewSignInMailLinksTheSessionsOnlyToWhoCanOpenThem(): void
    {
        $id = $this->makeUser('mail@example.com');
        // The layout escapes the href for an attribute, so the link is checked through its label.
        $this->assertStringContainsString(lang('RoleWarden.mail.reviewSessions'), $this->newSignInMail($id));

        setting(Visibility::KEY, Visibility::ADMINS);
        $html = $this->newSignInMail($id);

        $this->assertStringNotContainsString(lang('RoleWarden.mail.reviewSessions'), $html);
        $this->assertStringNotContainsString('<a href', $html);
        $this->assertStringContainsString(esc(lang('RoleWarden.mail.newSignIn.notYouNoSessions')), $html);
    }

    /** The new sign-in email for $id, rendered as it would be sent; nothing is sent. */
    private function newSignInMail(int $id): string
    {
        $this->db->table(config('Auth')->tables['logins'])->insert([
            'ip_address' => '10.9.9.9', 'id_type' => 'email_password', 'identifier' => 'mail@example.com',
            'user_id' => $id, 'date' => date('Y-m-d H:i:s', time() - 3600), 'success' => 1,
        ]);
        $messages = [];
        $catch = static function (array $message) use (&$messages): bool {
            $messages[] = $message;

            return false;
        };
        SecurityMail::onLogin((new UserModel())->findById($id));
        Events::on('rolewarden.mail', $catch, Events::PRIORITY_HIGH);
        SecurityMail::flush();
        Events::removeListener('rolewarden.mail', $catch);
        $this->assertCount(1, $messages);

        return view('RoleWarden\Views\emails\\' . $messages[0]['view'], $messages[0]['data']);
    }

    public function testAdminsModeShowsProfileToPanelRole(): void
    {
        setting(Visibility::KEY, Visibility::ADMINS);
        $id = $this->makeUser('boss@example.com');
        (new UserRoles())->assign($id, $this->roleId('admin'));

        $this->as($id)->get('rolewarden/profile')->assertStatus(200);
    }

    public function testOverrideOnPanelPermissionCounts(): void
    {
        setting(Visibility::KEY, Visibility::ADMINS);
        $id = $this->makeUser('over@example.com');
        $this->db->table(config('RoleWarden')->table('user_permissions'))->insert([
            'user_id'       => $id,
            'permission_id' => (int) $this->db->table(config('RoleWarden')->table('permissions'))->where('slug', 'users.view')->get()->getRow()->id,
            'granted'       => 1,
        ]);
        // Written behind the resolver's back: drop its cached answer for this user.
        service('rolewarden')->forgetUser($id);

        $this->as($id)->get('rolewarden/profile')->assertStatus(200);
    }

    public function testAllModeUnchanged(): void
    {
        $id = $this->makeUser('plain2@example.com');

        $this->as($id)->get('rolewarden/profile')->assertStatus(200);
    }

    public function testSettingIsSavedFromTheSettingsScreen(): void
    {
        $id = $this->makeUser('root@example.com');
        (new UserRoles())->assign($id, $this->roleId('admin'));
        $post = SignIn::values() + ['activity_retention' => (string) ActivityLog::RETENTIONS[0], 'sidebar_today' => '1', 'default_role' => '', 'visibility' => Visibility::ADMINS, csrf_token() => csrf_hash()];

        $this->as($id)->post('rolewarden/settings', $post)->assertRedirectTo(site_url('rolewarden/settings'));

        $this->as($id)->get('rolewarden/settings')->assertSee('Who sees RoleWarden');
        $this->assertSame(Visibility::ADMINS, Visibility::mode());
        $this->assertSame(1, $this->db->table(config('RoleWarden')->table('activity_log'))->like('details', 'visibility')->countAllResults());
    }
}
