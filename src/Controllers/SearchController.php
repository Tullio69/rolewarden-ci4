<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use RoleWarden\Models\PermissionModel;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Settings\Sidebar;

/**
 * Command search (SPEC, v1.1 decisions; docs/design-system/components/CommandSearch):
 * screens, actions and permission identifiers the signed-in user may reach,
 * filtered here, never in the browser. Actions open their screen; they never
 * act on their own. The overlay asks for the results alone; without
 * JavaScript the same results come as a page.
 */
class SearchController extends BaseController
{
    private const PERMISSIONS = 8;

    public function index(): string
    {
        $query = rw_query('q');
        $data = ['query' => $query, 'groups' => $this->results((int) auth()->id(), mb_strtolower($query))];

        if ($this->request->isAJAX()) {
            return view('RoleWarden\Views\search\results', $data + ['page' => false]);
        }

        return rw_panel('RoleWarden\Views\search\index', $data, 'search', lang('RoleWarden.panel.search.title'));
    }

    /**
     * Results by group (permission, screens, actions), each item with its
     * label, address, icon and, where it helps, a count or a one-line answer.
     * Empty groups are left out.
     *
     * @return array<string, list<array{label: string, url: string, icon: string, slug?: string, count?: string|null, answer?: string}>>
     */
    private function results(int $userId, string $q): array
    {
        $matches = static fn (string $text): bool => $q === '' || str_contains(mb_strtolower($text), $q);
        $groups = ['permission' => [], 'screens' => [], 'actions' => []];

        // A typed identifier matches permissions first: the top one answers who can do it.
        if ($q !== '' && can('permissions.view')) {
            $slugs = array_values(array_filter(
                array_column(model(PermissionModel::class)->select('slug')->orderBy('slug')->findAll(), 'slug'),
                static fn (string $slug): bool => str_contains($slug, $q),
            ));
            usort($slugs, static fn (string $a, string $b): int => (int) ! str_starts_with($a, $q) <=> (int) ! str_starts_with($b, $q));

            foreach (array_slice($slugs, 0, self::PERMISSIONS) as $i => $slug) {
                $groups['permission'][] = ['label' => $slug, 'slug' => $slug, 'icon' => 'check', 'url' => site_url('rolewarden/permissions/check') . '?' . http_build_query(['permission' => $slug])]
                    + ($i === 0 ? ['answer' => $this->answer($slug)] : []);
            }
        }

        $screens = [];

        foreach (Sidebar::groups($userId) as $group) {
            foreach ($group['items'] as $item) {
                $screens[] = ['label' => lang('RoleWarden.panel.nav.' . $item['key']), 'url' => $item['url'], 'icon' => $item['icon'], 'count' => $item['count']];
            }
        }

        // The signed-in user's own account: no permission beyond being signed in.
        $screens[] = ['label' => lang('RoleWarden.panel.nav.profile'), 'url' => site_url('rolewarden/profile'), 'icon' => 'users'];
        $screens[] = ['label' => lang('RoleWarden.panel.nav.sessions'), 'url' => site_url('rolewarden/sessions'), 'icon' => 'sessions'];

        $actions = [
            ['addUser', 'users/create', can('users.create')],
            ['importUsers', 'users/import', can('users.import')],
            ['addRole', 'roles/create', can('roles.create')],
            ['addOverride', 'users', can('permissions.override') && can('users.view')],
            ['password', 'profile', true],
        ];

        foreach ($actions as [$key, $path, $allowed]) {
            if ($allowed) {
                $groups['actions'][] = ['label' => lang('RoleWarden.panel.search.actions.' . $key), 'url' => site_url('rolewarden/' . $path), 'icon' => 'action'];
            }
        }

        $groups['screens'] = array_values(array_filter($screens, static fn (array $item): bool => $matches($item['label'])));
        $groups['actions'] = array_values(array_filter($groups['actions'], static fn (array $item): bool => $matches($item['label'])));

        return array_filter($groups, static fn (array $items): bool => $items !== []);
    }

    /**
     * "8 users through 2 roles, 1 denied by override": everyone who can do
     * it, how many roles grant it directly (not the children that inherit
     * it), and how many users a negative override keeps from it.
     */
    private function answer(string $slug): string
    {
        $holders = model(PermissionModel::class)->holderIds($slug);
        $cfg = config('RoleWarden');

        $roles = array_map('intval', array_column(db_connect()->table($cfg->table('role_permissions') . ' rp')
            ->select('rp.role_id')
            ->join($cfg->table('permissions') . ' p', 'p.id = rp.permission_id')
            ->where('p.slug', $slug)
            ->get()->getResultArray(), 'role_id'));
        $roles = $roles === [] ? 0 : model(RoleModel::class)->whereIn('id', $roles)->countAllResults();

        $denied = array_map('intval', array_column(db_connect()->table($cfg->table('user_permissions') . ' up')
            ->select('up.user_id')
            ->join($cfg->table('permissions') . ' p', 'p.id = up.permission_id')
            ->where('p.slug', $slug)->where('up.granted', 0)
            ->get()->getResultArray(), 'user_id'));
        $denied = $denied === [] ? 0 : model(UserModel::class)->whereIn('id', $denied)->countAllResults();

        return lang('RoleWarden.panel.search.answer', [count($holders), $roles, $denied]);
    }
}
