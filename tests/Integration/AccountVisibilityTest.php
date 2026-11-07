<?php

declare(strict_types=1);

namespace RoleWarden\Tests\Integration;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Config\Factories;
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
