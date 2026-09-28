<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use RoleWarden\Account\ActivityLog;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;
use RoleWarden\Settings\DefaultRole;
use RoleWarden\Settings\SignIn;

/**
 * Settings screen (docs/design-system/components/Settings). Values live in
 * CodeIgniter's Settings library, so they change at runtime without touching
 * any file; who changed them last, and when, is kept alongside.
 */
class SettingsController extends BaseController
{
    private const CHANGED_AT = 'RoleWarden.settingsChangedAt';
    private const CHANGED_BY = 'RoleWarden.settingsChangedBy';
    private const RETENTION = 'RoleWarden.activityRetentionDays';

    public function index(): string
    {
        helper('setting');

        $changedBy = setting(self::CHANGED_BY);
        $changer = is_numeric($changedBy) ? model(UserModel::class)->findById((int) $changedBy) : null;
        $defaultRole = DefaultRole::role();

        return rw_panel('RoleWarden\Views\settings\index', [
            'roles' => model(RoleModel::class)->where('is_super_admin', 0)->orderBy('name')->findAll(),
            'defaultRole' => $defaultRole['slug'] ?? '',
            'signIn' => SignIn::values(),
            'retention' => (int) setting(self::RETENTION),
            'logEntries' => ActivityLog::count(),
            'errors' => session()->getFlashdata('rw_errors') ?? [],
            'changedAt' => setting(self::CHANGED_AT),
            'changedBy' => $changer !== null ? (string) ($changer->username ?? $changer->email) : null,
            'canUpdate' => can('settings.update'),
        ], 'settings', lang('RoleWarden.panel.settings.title'));
    }

    public function update(): RedirectResponse
    {
        helper('setting');

        $back = site_url('rolewarden/settings');
        $post = $this->request->getPost() ?? [];

        // Field errors stay under their control (V1 decision), not in a toast.
        $rules = SignIn::rules() + ['activity_retention' => 'required|in_list[' . implode(',', SignIn::choices(ActivityLog::RETENTIONS, (int) setting(self::RETENTION))) . ']'];

        if (! $this->validateData($post, $rules, $this->messages())) {
            return redirect()->to($back)->withInput()->with('rw_errors', $this->validator->getErrors());
        }

        $slug = (string) ($post['default_role'] ?? '');

        if ($slug !== '') {
            $reason = DefaultRole::reject(model(RoleModel::class)->where('slug', $slug)->first());

            if ($reason !== null) {
                return redirect()->to($back)->with('rw_error', lang('RoleWarden.panel.settings.' . $reason));
            }
        }

        $before = ['default_role' => DefaultRole::role()['slug'] ?? '', 'activity_retention' => (int) setting(self::RETENTION)] + SignIn::values();

        setting(DefaultRole::KEY, $slug);
        SignIn::save($post);
        setting(self::RETENTION, (int) $post['activity_retention']);
        setting(self::CHANGED_AT, date('Y-m-d H:i:s'));
        setting(self::CHANGED_BY, (int) auth()->id());

        $changes = ActivityLog::diff($before, ['default_role' => $slug, 'activity_retention' => (int) $post['activity_retention']] + SignIn::values());

        if ($changes !== []) {
            ActivityLog::record('settings.updated', 'settings', null, lang('RoleWarden.panel.settings.title'), $changes);
        }

        return redirect()->to($back)->with('rw_success', lang('RoleWarden.panel.settings.saved'));
    }

    /**
     * One message per field, naming the allowed range.
     *
     * @return array<string, array<string, string>>
     */
    private function messages(): array
    {
        $messages = [];

        foreach (['session_lifetime', 'remember_length', 'activity_retention'] as $field) {
            $messages[$field] = ['required' => lang('RoleWarden.panel.settings.choose'), 'in_list' => lang('RoleWarden.panel.settings.choose')];
        }

        foreach (SignIn::NUMBERS as $field => [, $min, $max]) {
            $range = lang('RoleWarden.panel.settings.range', [$min, $max]);
            $messages[$field] = array_fill_keys(['required', 'is_natural', 'greater_than_equal_to', 'less_than_equal_to'], $range);
        }

        return $messages;
    }
}
