<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RoleWarden\Dashboard\Dashboard;
use RoleWarden\Settings\Sidebar;

/**
 * The dashboard, the panel's first page: five blocks, each rendered only for
 * those who hold its dashboard.* permission. Whoever holds none lands on the
 * first screen of the sidebar they can see.
 */
class DashboardController extends BaseController
{
    public function index(): RedirectResponse|string
    {
        $blocks = array_keys(array_filter(Dashboard::BLOCKS, static fn (string $permission): bool => can($permission)));

        if ($blocks === []) {
            $first = Sidebar::groups((int) auth()->id())[0]['items'][0]['url'] ?? site_url('rolewarden/profile');

            return redirect()->to($first);
        }

        $now = time();
        $period = Dashboard::period((int) rw_query('period'), $now);
        $shows = static fn (string $block): bool => in_array($block, $blocks, true);
        $buckets = $shows('figures') || $shows('signins') ? Dashboard::buckets(Dashboard::signInRows($period), $period['dates']) : null;
        $me = auth()->user();

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
        ], 'dashboard', lang('RoleWarden.panel.dashboard.title'));
    }
}
