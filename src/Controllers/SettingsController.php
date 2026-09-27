<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Settings\DefaultRole;

/**
 * Settings screen (docs/design-system/components/Settings). Values live in
 * CodeIgniter's Settings library, so they change at runtime without touching
 * any file; who changed them last, and when, is kept alongside.
 */
class SettingsController extends BaseController
{
    private const CHANGED_AT = 'RoleWarden.settingsChangedAt';
    private const CHANGED_BY = 'RoleWarden.settingsChangedBy';

    public function index(): string
    {
        helper('setting');

        $changedBy = setting(self::CHANGED_BY);
        $changer = is_numeric($changedBy) ? model(UserModel::class)->findById((int) $changedBy) : null;
        $defaultRole = DefaultRole::role();

        return rw_panel('RoleWarden\Views\settings\index', [
            'roles' => model(RoleModel::class)->where('is_super_admin', 0)->orderBy('name')->findAll(),
            'defaultRole' => $defaultRole['slug'] ?? '',
            'changedAt' => setting(self::CHANGED_AT),
            'changedBy' => $changer !== null ? (string) ($changer->username ?? $changer->email) : null,
            'canUpdate' => can('settings.update'),
        ], 'settings', lang('RoleWarden.panel.settings.title'));
    }

    public function update(): RedirectResponse
    {
        helper('setting');

        $slug = (string) $this->request->getPost('default_role');

        if ($slug !== '') {
            $reason = DefaultRole::reject(model(RoleModel::class)->where('slug', $slug)->first());

            if ($reason !== null) {
                return redirect()->to(site_url('rolewarden/settings'))->with('rw_error', lang('RoleWarden.panel.settings.' . $reason));
            }
        }

        setting(DefaultRole::KEY, $slug);
        setting(self::CHANGED_AT, date('Y-m-d H:i:s'));
        setting(self::CHANGED_BY, (int) auth()->id());

        return redirect()->to(site_url('rolewarden/settings'))->with('rw_success', lang('RoleWarden.panel.settings.saved'));
    }
}
