<?php

declare(strict_types=1);

namespace RoleWarden\Controllers;

use RoleWarden\Account\ActivityLog;
use RoleWarden\Models\RoleModel;
use RoleWarden\Models\UserModel;

/**
 * Activity log screen: the module's own log and Shield's sign-in attempts in
 * one list, filtered by user, action and period.
 */
class ActivityController extends BaseController
{
    private const PER_PAGE = 25;

    public function index(): string
    {
        $filters = [
            'user' => trim((string) $this->request->getGet('user')),
            'action' => (string) $this->request->getGet('action'),
            'from' => (string) $this->request->getGet('from'),
            'to' => (string) $this->request->getGet('to'),
        ];
        $actions = [...ActivityLog::SIGN_IN_ACTIONS, ...ActivityLog::ACTIONS];

        if (! in_array($filters['action'], $actions, true)) {
            $filters['action'] = '';
        }

        $page = max(1, (int) $this->request->getGet('page'));
        $result = ActivityLog::page($filters, $page, self::PER_PAGE);

        $pager = service('pager');
        $pager->store('default', $page, self::PER_PAGE, $result['total']);
        $pager->only(['user', 'action', 'from', 'to']);

        return rw_panel('RoleWarden\Views\activity\index', [
            'rows' => $result['rows'],
            'total' => $result['total'],
            'filters' => $filters,
            'actions' => $actions,
            'pager' => $pager,
            'start' => ($page - 1) * self::PER_PAGE,
            'links' => $this->links($result['rows']),
        ], 'activity', lang('RoleWarden.panel.activity.title'));
    }

    /**
     * Subjects that still exist, so the view links only to pages that open.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return array{user: array<int, true>, role: array<int, true>}
     */
    private function links(array $rows): array
    {
        $ids = ['user' => [], 'role' => []];

        foreach ($rows as $row) {
            if (isset($ids[$row['subject_type']]) && $row['subject_id'] !== null) {
                $ids[$row['subject_type']][] = (int) $row['subject_id'];
            }
        }

        $found = ['user' => [], 'role' => []];

        if ($ids['user'] !== [] && can('users.view')) {
            $found['user'] = array_fill_keys(array_map('intval', array_column(model(UserModel::class)->select('id')->whereIn('id', array_unique($ids['user']))->asArray()->findAll(), 'id')), true);
        }

        if ($ids['role'] !== [] && can('roles.view')) {
            $found['role'] = array_fill_keys(array_map('intval', array_column(model(RoleModel::class)->select('id')->whereIn('id', array_unique($ids['role']))->findAll(), 'id')), true);
        }

        return $found;
    }
}
