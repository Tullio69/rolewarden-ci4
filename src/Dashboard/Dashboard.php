<?php

declare(strict_types=1);

namespace RoleWarden\Dashboard;

use RoleWarden\Account\ActivityLog;
use RoleWarden\Settings\SignIn;

/**
 * The dashboard's numbers (SPEC, decisions of 2026-10-18 and 2026-10-21), read
 * only from tables that already exist: Shield's auth_logins and users, the
 * module's roles and activity log. Every figure is a plain count over a
 * calendar period, so a direct query on the same period gives the same number.
 */
final class Dashboard
{
    public const PERIODS = [7, 30, 90];
    public const DEFAULT_PERIOD = 30;

    /** The five blocks, in page order, each with its permission. */
    public const BLOCKS = [
        'security' => 'dashboard.security',
        'figures' => 'dashboard.figures',
        'signins' => 'dashboard.signins',
        'roles' => 'dashboard.roles',
        'changes' => 'dashboard.changes',
    ];

    /** Activity-log actions that count as a permission change. */
    public const CHANGE_ACTIONS = [
        'role.permission_granted', 'role.permission_revoked', 'user.override_granted', 'user.override_denied',
        'user.override_cleared', 'user.role_assigned', 'user.role_revoked',
    ];

    public const LATEST = 5;
    public const SHARES = 4;

    /**
     * The application's blocks from Config\RoleWarden::$dashboardBlocks, by
     * key. A class that is missing, doesn't extend Block or has a key that is
     * malformed, taken or a module block's is left out and logged.
     *
     * @param list<string>|null $classes null reads the configuration
     *
     * @return array<string, Block>
     */
    public static function registered(?array $classes = null): array
    {
        $found = [];

        foreach ($classes ?? config('RoleWarden')->dashboardBlocks as $class) {
            if (! is_string($class) || ! class_exists($class) || ! is_subclass_of($class, Block::class)) {
                log_message('error', 'RoleWarden: dashboard block {class} is not a class extending ' . Block::class . '.', ['class' => is_string($class) ? $class : gettype($class)]);

                continue;
            }

            $block = new $class();
            $key = $block->key();

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key) !== 1 || isset(self::BLOCKS[$key]) || isset($found[$key])) {
                log_message('error', 'RoleWarden: dashboard block {class} has a key that is malformed or taken: {key}.', ['class' => $class, 'key' => $key]);

                continue;
            }

            $found[$key] = $block;
        }

        return $found;
    }

    /**
     * The period of $days calendar days ending now, today included, and the
     * same number of days before it, as 'Y-m-d H:i:s' in the application's
     * time zone. The current period runs from its first midnight to now; the
     * previous one ends just before that midnight.
     *
     * @return array{days: int, from: string, to: string, prevFrom: string, prevTo: string, dates: list<string>}
     */
    public static function period(int $days, int $now): array
    {
        $days = in_array($days, self::PERIODS, true) ? $days : self::DEFAULT_PERIOD;
        $today = (int) strtotime(date('Y-m-d 00:00:00', $now));
        $from = (int) strtotime('-' . ($days - 1) . ' days', $today);
        $prevFrom = (int) strtotime('-' . $days . ' days', $from);
        $dates = [];

        for ($i = 0; $i < $days; $i++) {
            $dates[] = date('Y-m-d', (int) strtotime('+' . $i . ' days', $from));
        }

        return [
            'days' => $days,
            'from' => date('Y-m-d H:i:s', $from),
            'to' => date('Y-m-d H:i:s', $now),
            'prevFrom' => date('Y-m-d H:i:s', $prevFrom),
            'prevTo' => date('Y-m-d H:i:s', $from - 1),
            'dates' => $dates,
        ];
    }

    /** Heat-map step 0-4 of a cell holding $n when the busiest holds $max. */
    public static function heatLevel(int $n, int $max): int
    {
        return $n <= 0 || $max <= 0 ? 0 : min(4, (int) ceil(4 * $n / $max));
    }

    /**
     * Sign-ins per day of the period and successful ones per weekday (0 =
     * Monday) and hour, from rows of auth_logins.
     *
     * @param list<array{date: string, success: int|string}> $rows
     * @param list<string>                                   $dates
     *
     * @return array{ok: array<string, int>, failed: array<string, int>, heat: list<list<int>>}
     */
    public static function buckets(array $rows, array $dates): array
    {
        $ok = array_fill_keys($dates, 0);
        $failed = $ok;
        $heat = array_fill(0, 7, array_fill(0, 24, 0));

        foreach ($rows as $row) {
            $at = (int) strtotime($row['date']);
            $day = date('Y-m-d', $at);

            if (! isset($ok[$day])) {
                continue;
            }

            if ((int) $row['success'] === 1) {
                $ok[$day]++;
                $heat[(int) date('N', $at) - 1][(int) date('G', $at)]++;
            } else {
                $failed[$day]++;
            }
        }

        return ['ok' => $ok, 'failed' => $failed, 'heat' => $heat];
    }

    /**
     * Every address that reached $threshold failed sign-ins on one calendar
     * day, once per day, latest day first and the busiest first within a day.
     *
     * @param list<array{date: string, ip_address: string|null}> $failures
     *
     * @return list<array{ip: string, day: string, count: int}>
     */
    public static function addresses(array $failures, int $threshold): array
    {
        $counts = [];

        foreach ($failures as $row) {
            $ip = (string) ($row['ip_address'] ?? '');

            if ($ip !== '') {
                $key = substr($row['date'], 0, 10) . ' ' . $ip;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        $found = [];

        foreach ($counts as $key => $count) {
            [$day, $ip] = explode(' ', $key, 2);

            if ($count >= $threshold) {
                $found[] = ['ip' => $ip, 'day' => $day, 'count' => $count];
            }
        }

        usort($found, static fn (array $a, array $b): int => [$b['day'], $b['count'], $a['ip']] <=> [$a['day'], $a['count'], $b['ip']]);

        return $found;
    }

    /**
     * The first $keep roles by holders and the rest summed as "other".
     *
     * @param list<array{name: string, total: int}> $roles largest first
     *
     * @return list<array{name: string|null, total: int}> name null for "other"
     */
    public static function shares(array $roles, int $keep): array
    {
        $shown = array_slice($roles, 0, $keep);
        $rest = array_sum(array_column(array_slice($roles, $keep), 'total'));

        if ($rest > 0) {
            $shown[] = ['name' => null, 'total' => $rest];
        }

        return $shown;
    }

    /**
     * The four key figures, each with its change on the previous period.
     *
     * @return array{active: int, users: int, created: int, signins: int, signinsPrev: int, failed: int, failedPrev: int, changes: int, changesPrev: int, overrides: int, peak: array{day: string, count: int}|null}
     */
    public static function figures(array $period, array $buckets): array
    {
        $db = db_connect();
        $users = static fn () => $db->table(config('Auth')->tables['users'])->where('deleted_at', null);
        $logins = static fn (bool $ok, string $from, string $to): int => $db->table(config('Auth')->tables['logins'])
            ->where('success', $ok ? 1 : 0)->where('date >=', $from)->where('date <=', $to)->countAllResults();
        $changes = static fn (string $from, string $to, ?string $prefix = null): int => ActivityLog::ready()
            ? $db->table(config('RoleWarden')->table('activity_log'))->whereIn('action', self::CHANGE_ACTIONS)
                ->where('created_at >=', $from)->where('created_at <=', $to)
                ->when($prefix !== null, static fn ($q) => $q->like('action', $prefix, 'after'))->countAllResults()
            : 0;

        $peak = null;

        foreach ($buckets['ok'] as $day => $count) {
            if ($count > 0 && ($peak === null || $count >= $peak['count'])) {
                $peak = ['day' => $day, 'count' => $count];
            }
        }

        return [
            'active' => $users()->where('active', 1)->countAllResults(),
            'users' => $users()->countAllResults(),
            'created' => $users()->where('created_at >=', $period['from'])->where('created_at <=', $period['to'])->countAllResults(),
            'signins' => $logins(true, $period['from'], $period['to']),
            'signinsPrev' => $logins(true, $period['prevFrom'], $period['prevTo']),
            'failed' => $logins(false, $period['from'], $period['to']),
            'failedPrev' => $logins(false, $period['prevFrom'], $period['prevTo']),
            'changes' => $changes($period['from'], $period['to']),
            'changesPrev' => $changes($period['prevFrom'], $period['prevTo']),
            'overrides' => $changes($period['from'], $period['to'], 'user.override_'),
            'peak' => $peak,
        ];
    }

    /**
     * Rows of auth_logins in the period, for the charts and the peak day.
     *
     * ponytail: reads the period's rows and counts in PHP, portable across databases; GROUP BY day and hour if a site signs in hundreds of thousands a quarter.
     *
     * @return list<array{date: string, success: int|string}>
     */
    public static function signInRows(array $period): array
    {
        return db_connect()->table(config('Auth')->tables['logins'])->select('date, success')
            ->where('date >=', $period['from'])->where('date <=', $period['to'])->get()->getResultArray();
    }

    /**
     * The security notice: every address that reached the lock threshold on
     * one day of the period and every email locked now; null when neither.
     *
     * @return array{addresses: list<array{ip: string, day: string, count: int}>, locked: list<string>, threshold: int}|null
     */
    public static function security(array $period, int $now): ?array
    {
        helper('setting');
        $threshold = (int) setting('RoleWarden.lockAttempts');
        $minutes = (int) setting('RoleWarden.lockMinutes');
        $logins = config('Auth')->tables['logins'];
        $failures = db_connect()->table($logins)->select('date, ip_address')->where('success', 0)
            ->where('date >=', $period['from'])->where('date <=', $period['to'])->get()->getResultArray();
        $addresses = self::addresses($failures, $threshold);
        $since = date('Y-m-d H:i:s', $now - $minutes * 60);
        $locked = [];

        foreach (db_connect()->table($logins)->distinct()->select('identifier')->where('success', 0)->where('date >', $since)->orderBy('identifier')->get()->getResultArray() as $row) {
            $email = (string) $row['identifier'];

            if ($email !== '' && SignIn::lockedFor(SignIn::recentFailures($email), $threshold, $minutes, $now) > 0) {
                $locked[] = $email;
            }
        }

        return $addresses !== [] || $locked !== [] ? ['addresses' => $addresses, 'locked' => $locked, 'threshold' => $threshold] : null;
    }

    /**
     * Holders per role, direct assignments of users not deleted to roles not
     * deleted, largest first.
     *
     * @return array{roles: list<array{name: string, total: int}>, users: int, count: int}
     */
    public static function roles(): array
    {
        $cfg = config('RoleWarden');
        $db = db_connect();
        $rows = $db->table($cfg->table('user_roles') . ' ur')
            ->select('r.name, COUNT(*) AS total')
            ->join($cfg->table('roles') . ' r', 'r.id = ur.role_id')
            ->join(config('Auth')->tables['users'] . ' u', 'u.id = ur.user_id')
            ->where('r.deleted_at', null)->where('u.deleted_at', null)
            ->groupBy('r.id, r.name')->orderBy('total', 'desc')->orderBy('r.name', 'asc')
            ->get()->getResultArray();

        return [
            'roles' => array_map(static fn (array $r): array => ['name' => (string) $r['name'], 'total' => (int) $r['total']], $rows),
            'users' => $db->table(config('Auth')->tables['users'])->where('deleted_at', null)->countAllResults(),
            'count' => $db->table($cfg->table('roles'))->where('deleted_at', null)->countAllResults(),
        ];
    }

    /**
     * The latest permission changes of the period and how many there are.
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function changes(array $period): array
    {
        if (! ActivityLog::ready()) {
            return ['rows' => [], 'total' => 0];
        }

        $query = static fn () => db_connect()->table(config('RoleWarden')->table('activity_log'))
            ->whereIn('action', self::CHANGE_ACTIONS)->where('created_at >=', $period['from'])->where('created_at <=', $period['to']);

        return [
            'rows' => $query()->orderBy('created_at', 'desc')->orderBy('id', 'desc')->limit(self::LATEST)->get()->getResultArray(),
            'total' => $query()->countAllResults(),
        ];
    }
}
