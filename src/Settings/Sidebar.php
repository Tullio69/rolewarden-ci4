<?php

declare(strict_types=1);

namespace RoleWarden\Settings;

use RoleWarden\Account\ActivityLog;

/**
 * The panel's sidebar (SPEC, v1.1 decisions; docs/design-system/components/Sidebar):
 * grouped items shown only to those who hold their permission, counters, the
 * "Today" panel and the per-user choice of a collapsed rail. The style (Paper,
 * Ink, Charcoal) lives in the theme file, see Theme.
 *
 * Kept with CodeIgniter's Settings library: "Today" globally, the collapsed
 * rail and the last visit to the activity log in each user's context.
 */
final class Sidebar
{
    public const STYLES = ['paper', 'ink', 'charcoal'];
    public const TODAY = 'RoleWarden.sidebarToday';
    private const COLLAPSED = 'RoleWarden.sidebarCollapsed';
    private const SEEN = 'RoleWarden.activitySeenAt';

    // 16px glyphs, 1.5px stroke, square caps (design system, Iconography).
    private const ICONS = [
        'users' => '<circle cx="8" cy="5.5" r="2.5"/><path d="M3 13.5c.6-2.6 2.6-4 5-4s4.4 1.4 5 4"/>',
        'roles' => '<path d="M8 2.5l5.5 3-5.5 3-5.5-3z"/><path d="M2.5 8.5l5.5 3 5.5-3"/><path d="M2.5 11.5l5.5 3 5.5-3"/>',
        'permissions' => '<path d="M2.5 2.5h11v11h-11z"/><path d="M2.5 6.5h11M2.5 10h11M6.5 2.5v11"/>',
        'check' => '<circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5l3 3"/><path d="M5 7l1.5 1.5 2.5-2.5"/>',
        'activity' => '<path d="M5.5 4h8M5.5 8h8M5.5 12h8M2.5 4h.5M2.5 8h.5M2.5 12h.5"/>',
        'settings' => '<path d="M2.5 4.5h7M12 4.5h1.5M2.5 11.5h2M7 11.5h6.5"/><circle cx="10.5" cy="4.5" r="1.5"/><circle cx="5.5" cy="11.5" r="1.5"/>',
        'appearance' => '<circle cx="8" cy="8" r="5.5"/><path d="M8 2.5v11"/>',
        'sessions' => '<path d="M2.5 3.5h11v7.5h-11z"/><path d="M5.5 13.5h5"/>',
        'search' => '<circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5l3 3"/>',
        'action' => '<path d="M2.5 13.5l2-.5 8-8-1.5-1.5-8 8z"/>',
        'collapse' => '<path d="M10 3.5L5.5 8l4.5 4.5"/>',
        'expand' => '<path d="M6 3.5l4.5 4.5L6 12.5"/>',
    ];

    public static function icon(string $key): string
    {
        return '<svg class="rw-side-icon" width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="square" aria-hidden="true">' . self::ICONS[$key] . '</svg>';
    }

    public static function style(): string
    {
        return Theme::current()['sidebar'] ?? 'paper';
    }

    public static function showsToday(): bool
    {
        helper('setting');

        return (bool) setting(self::TODAY);
    }

    public static function collapsed(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        helper('setting');

        return (bool) setting()->get(self::COLLAPSED, 'user:' . $userId);
    }

    public static function setCollapsed(int $userId, bool $collapsed): void
    {
        helper('setting');
        setting()->set(self::COLLAPSED, $collapsed, 'user:' . $userId);
    }

    public static function markActivitySeen(int $userId): void
    {
        helper('setting');
        setting()->set(self::SEEN, date('Y-m-d H:i:s'), 'user:' . $userId);
    }

    /**
     * The groups the signed-in user can see, each with the items whose
     * permission they hold; empty groups are left out.
     *
     * @return list<array{key: string, items: list<array{key: string, url: string, icon: string, count: string|null, hot: bool}>}>
     */
    public static function groups(int $userId): array
    {
        $db = db_connect();
        $cfg = config('RoleWarden');
        $count = static fn (string $table): string => (string) $db->table($table)->countAllResults();

        $groups = [
            'overview' => [],
            'access' => [
                ['users', 'users.view', static fn (): string => (string) model(\RoleWarden\Models\UserModel::class)->countAllResults()],
                ['roles', 'roles.view', static fn (): string => (string) model(\RoleWarden\Models\RoleModel::class)->countAllResults()],
                ['permissions', 'permissions.view', static fn (): string => $count($cfg->table('permissions'))],
                ['check', 'permissions.view', null, 'permissions/check'],
            ],
            'security' => [
                ['activity', 'activity.view', static fn (): ?string => self::newActivity($userId)],
            ],
            'system' => [
                ['settings', 'settings.view', null],
                ['appearance', 'appearance.update', null],
            ],
        ];

        $out = [];

        foreach ($groups as $group => $items) {
            $shown = [];

            foreach ($items as $item) {
                [$key, $permission, $counter] = $item;

                if (! can($permission)) {
                    continue;
                }

                $value = $counter !== null ? $counter() : null;
                $shown[] = [
                    'key' => $key,
                    'url' => site_url('rolewarden/' . ($item[3] ?? $key)),
                    'icon' => $key,
                    'count' => $value,
                    'hot' => $key === 'activity' && $value !== null,
                ];
            }

            if ($shown !== []) {
                $out[] = ['key' => $group, 'items' => $shown];
            }
        }

        return $out;
    }

    /**
     * "N new" for the activity log: entries written by someone else and failed
     * sign-ins since the user last opened the log; null before the first visit
     * or when there is nothing new.
     */
    private static function newActivity(int $userId): ?string
    {
        helper('setting');
        $seen = setting()->get(self::SEEN, 'user:' . $userId);

        if (! is_string($seen) || ! ActivityLog::ready()) {
            return null;
        }

        $db = db_connect();
        $changes = $db->table(config('RoleWarden')->table('activity_log'))
            ->where('created_at >', $seen)
            ->groupStart()->where('actor_id !=', $userId)->orWhere('actor_id', null)->groupEnd()
            ->countAllResults();
        $failed = $db->table(config('Auth')->tables['logins'])->where('success', 0)->where('date >', $seen)->countAllResults();
        $n = $changes + $failed;

        return $n === 0 ? null : lang('RoleWarden.panel.nav.new', [$n > 99 ? '99+' : (string) $n]);
    }

    /**
     * Today's sign-ins from Shield's auth_logins: successful, failed, and the
     * successful ones per hour (0-23), in the application's time zone.
     *
     * @return array{ok: int, failed: int, hours: list<int>}
     */
    public static function today(): array
    {
        $hours = array_fill(0, 24, 0);
        $ok = 0;
        $failed = 0;

        // ponytail: reads today's rows and counts in PHP, portable across databases; a GROUP BY per hour if a site signs in tens of thousands a day.
        $rows = db_connect()->table(config('Auth')->tables['logins'])
            ->select('date, success')->where('date >=', date('Y-m-d 00:00:00'))->get()->getResultArray();

        foreach ($rows as $row) {
            if ((int) $row['success'] === 1) {
                $ok++;
                $hours[(int) date('G', (int) strtotime($row['date']))]++;
            } else {
                $failed++;
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'hours' => $hours];
    }
}
