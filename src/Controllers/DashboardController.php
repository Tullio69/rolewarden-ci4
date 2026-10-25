<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RoleWarden\Account\ActivityLog;
use RoleWarden\Dashboard\Dashboard;
use RoleWarden\Dashboard\Layout;
use RoleWarden\Settings\Sidebar;

/**
 * The dashboard, the panel's first page: the module's five blocks and the
 * application's, each rendered only for those who hold its permission, in
 * the order and with the period of the user's layout. Whoever can see none
 * lands on the first screen of the sidebar they can see.
 */
class DashboardController extends BaseController
{
    public function index(): RedirectResponse|string
    {
        $userId = (int) auth()->id();
        $registered = Dashboard::registered();
        $allowed = $this->allowed($registered);

        if ($allowed === []) {
            $first = Sidebar::groups($userId)[0]['items'][0]['url'] ?? site_url('rolewarden/profile');

            return redirect()->to($first);
        }

        $layout = Layout::for($userId);
        $blocks = array_values(array_diff(array_intersect($layout['order'], $allowed), $layout['hidden']));
        $now = time();
        // A period not offered in ?period= falls back to the saved one.
        $asked = (int) rw_query('period');
        $period = Dashboard::period(in_array($asked, Dashboard::PERIODS, true) ? $asked : $layout['period'], $now);
        $shows = static fn (string $block): bool => in_array($block, $blocks, true);
        $buckets = $shows('figures') || $shows('signins') ? Dashboard::buckets(Dashboard::signInRows($period), $period['dates']) : null;
        $me = auth()->user();
        $app = [];

        foreach ($registered as $key => $block) {
            if ($shows($key)) {
                $app[$key] = ['block' => $block, 'data' => $block->data($period, $userId)];
            }
        }

        return rw_panel('RoleWarden\Views\dashboard\index', [
            'blocks' => $blocks,
            'period' => $period,
            'name' => (string) ($me->username ?? $me->email),
            'hour' => (int) date('G', $now),
            'security' => $shows('security') ? Dashboard::security($period, $now) : null,
            'figures' => $shows('figures') && $buckets !== null ? Dashboard::figures($period, $buckets) : null,
            'buckets' => $buckets,
            'roles' => $shows('roles') ? Dashboard::roles() : null,
            'changes' => $shows('changes') ? Dashboard::changes($period) : null,
            'app' => $app,
            'customize' => [
                'blocks' => array_map(static fn (string $key): array => [
                    'key' => $key,
                    'title' => isset($registered[$key]) ? $registered[$key]->title() : lang('RoleWarden.panel.dashboard.customize.blocks.' . $key),
                    'shown' => ! in_array($key, $layout['hidden'], true),
                    'pinned' => $key === Layout::PINNED,
                ], array_values(array_intersect($layout['order'], $allowed))),
                'period' => $layout['period'],
                'global' => can('settings.update'),
            ],
        ], 'dashboard', lang('RoleWarden.panel.dashboard.title'));
    }

    /**
     * Saves the customize panel: for the user ("me"), as everyone's default
     * ("global", settings.update only) or drops the user's choice ("reset").
     * Only the blocks the user can see are taken from the form.
     */
    public function layout(): RedirectResponse
    {
        $userId = (int) auth()->id();
        $back = site_url('rolewarden');
        $scope = (string) $this->request->getPost('scope');
        $allowed = $this->allowed(Dashboard::registered());

        if ($allowed === [] || ($scope === 'global' && ! can('settings.update'))) {
            return redirect()->to(config('Auth')->permissionDeniedRedirect())->with('error', lang('RoleWarden.accessDenied'));
        }

        if ($scope === 'reset') {
            Layout::reset($userId);

            return redirect()->to($back)->with('rw_success', lang('RoleWarden.panel.dashboard.customize.reset'));
        }

        $period = (int) $this->request->getPost('period');

        if (! in_array($scope, ['me', 'global'], true) || ! in_array($period, Dashboard::PERIODS, true)) {
            return redirect()->to($back)->with('rw_error', lang('RoleWarden.panel.dashboard.customize.invalid'));
        }

        $order = array_values(array_intersect(self::strings($this->request->getPost('order')), $allowed));
        $shown = self::strings($this->request->getPost('shown'));
        $current = $scope === 'global' ? Layout::global() : Layout::for($userId);
        // Blocks out of the user's sight keep what the layout already says about them.
        $hidden = array_merge(array_diff($order, $shown), array_diff($current['hidden'], $allowed));
        $layout = ['order' => Layout::reorder($current['order'], $order), 'hidden' => array_values($hidden), 'period' => $period];

        if ($scope === 'global') {
            $after = Layout::resolve($layout, Layout::known());
            Layout::save(null, $layout);
            $changes = ActivityLog::diff(self::flat($current), self::flat($after));

            if ($changes !== []) {
                ActivityLog::record('settings.updated', 'settings', null, lang('RoleWarden.panel.dashboard.title'), $changes);
            }

            return redirect()->to($back)->with('rw_success', lang('RoleWarden.panel.dashboard.customize.savedGlobal'));
        }

        Layout::save($userId, $layout);

        return redirect()->to($back)->with('rw_success', lang('RoleWarden.panel.dashboard.customize.saved'));
    }

    /**
     * Keys of the blocks the signed-in user holds the permission of.
     *
     * @param array<string, \RoleWarden\Dashboard\Block> $registered
     *
     * @return list<string>
     */
    private function allowed(array $registered): array
    {
        $permissions = Dashboard::BLOCKS + array_map(static fn ($block): string => $block->permission(), $registered);

        return array_keys(array_filter($permissions, static fn (string $permission): bool => can($permission)));
    }

    /**
     * The plain strings of a posted list; nested arrays and anything else dropped.
     *
     * @return list<string>
     */
    private static function strings(mixed $posted): array
    {
        return array_values(array_filter(is_array($posted) ? $posted : [], 'is_string'));
    }

    /**
     * @param array{order: list<string>, hidden: list<string>, period: int} $layout
     *
     * @return array{dashboard_order: string, dashboard_hidden: string, dashboard_period: int}
     */
    private static function flat(array $layout): array
    {
        return ['dashboard_order' => implode(', ', $layout['order']), 'dashboard_hidden' => implode(', ', $layout['hidden']), 'dashboard_period' => $layout['period']];
    }
}
