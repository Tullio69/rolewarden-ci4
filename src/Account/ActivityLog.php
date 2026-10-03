<?php

declare(strict_types=1);

namespace RoleWarden\Account;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Events\Events;

/**
 * The activity log: who changed what, on which object, when. Changes are
 * written here by the panel; sign-ins are read from Shield's auth_logins, which
 * already records them, and merged into the same list by page().
 *
 * Every entry also fires the CodeIgniter event "rolewarden.activity" with the
 * row as its argument, the hook for a host app that wants its own reaction.
 */
final class ActivityLog
{
    /** Actions written by the module, in the order the filter offers them. */
    public const ACTIONS = [
        'user.created', 'user.registered', 'user.updated', 'user.password_set', 'user.activated', 'user.deactivated',
        'user.deleted', 'user.role_assigned', 'user.role_revoked', 'user.override_granted', 'user.override_denied',
        'user.override_cleared', 'role.created', 'role.updated', 'role.deleted', 'role.permission_granted',
        'role.permission_revoked', 'settings.updated', 'account.password_changed', 'session.revoked',
        'session.remembered_forgotten', 'session.all_revoked', 'appearance.updated', 'auth.logout',
    ];

    /** Retention choices of the Settings screen, in days; 0 keeps everything. */
    public const RETENTIONS = [90, 365, 0];

    /** Actions read from Shield's auth_logins. */
    public const SIGN_IN_ACTIONS = ['auth.login', 'auth.failed'];

    private static ?bool $ready = null;

    /**
     * @param array<string, mixed> $details field => [before, after] for changes, or plain facts
     * @param object|null          $actor   the user acting; the signed-in user when omitted
     */
    public static function record(string $action, string $subjectType, ?int $subjectId, string $subjectLabel, array $details = [], ?object $actor = null): void
    {
        if (! self::ready()) {
            return;
        }

        $actor ??= auth()->user();
        $row = [
            'actor_id' => $actor !== null ? (int) $actor->id : null,
            'actor_label' => $actor !== null ? self::userLabel($actor) : null,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'subject_label' => mb_substr($subjectLabel, 0, 255),
            'details' => $details !== [] ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            'ip_address' => is_cli() ? null : service('request')->getIPAddress(),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        self::prune();
        self::table()->insert($row);
        Events::trigger('rolewarden.activity', $row);
    }

    public static function userLabel(object $user): string
    {
        return (string) ($user->username ?? $user->email ?? ('#' . $user->id));
    }

    /**
     * Fields whose value differs, as field => [before, after].
     *
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     *
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $field => $value) {
            $old = $before[$field] ?? null;

            if ((string) $old !== (string) $value) {
                $changes[$field] = [$old, $value];
            }
        }

        return $changes;
    }

    /**
     * Oldest timestamp kept for a retention of $days; null keeps everything.
     */
    public static function cutoff(int $days, int $now): ?string
    {
        return $days > 0 ? date('Y-m-d H:i:s', $now - $days * 86400) : null;
    }

    /**
     * One page of the merged log, newest first.
     *
     * @param array{user?: string, action?: string, from?: string, to?: string} $filters
     *
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public static function page(array $filters, int $page, int $perPage): array
    {
        $db = db_connect();
        $branches = [];
        $bindings = [];
        $action = $filters['action'] ?? '';

        if (self::ready() && ! in_array($action, self::SIGN_IN_ACTIONS, true)) {
            [$where, $binds] = self::conditions($filters, 'created_at', 'actor_id', 'actor_label', 'subject_type = \'user\' AND subject_id', 'subject_type = \'user\' AND subject_label');

            if ($action !== '') {
                $where[] = 'action = ?';
                $binds[] = $action;
            }

            $branches[] = 'SELECT \'log\' AS source, id, created_at AS at, actor_id, actor_label, action, subject_type, subject_id, subject_label, details, ip_address'
                . ' FROM ' . $db->prefixTable(config('RoleWarden')->table('activity_log')) . self::where($where);
            $bindings = [...$bindings, ...$binds];
        }

        if ($action === '' || in_array($action, self::SIGN_IN_ACTIONS, true)) {
            [$where, $binds] = self::conditions($filters, 'date', 'user_id', 'identifier', 'user_id', 'identifier');

            if ($action !== '') {
                $where[] = 'success = ?';
                $binds[] = $action === 'auth.login' ? 1 : 0;
            }

            $branches[] = 'SELECT \'login\' AS source, id, date AS at, user_id AS actor_id, identifier AS actor_label,'
                . ' CASE WHEN success = 1 THEN \'auth.login\' ELSE \'auth.failed\' END AS action, \'user\' AS subject_type,'
                . ' user_id AS subject_id, identifier AS subject_label, NULL AS details, ip_address'
                . ' FROM ' . $db->prefixTable(config('Auth')->tables['logins']) . self::where($where);
            $bindings = [...$bindings, ...$binds];
        }

        if ($branches === []) {
            return ['rows' => [], 'total' => 0];
        }

        $union = '(' . implode(') UNION ALL (', $branches) . ')';
        $total = (int) $db->query('SELECT COUNT(*) AS n FROM (' . $union . ') t', $bindings)->getRow('n');
        $rows = $db->query(
            'SELECT * FROM (' . $union . ') t ORDER BY at DESC, source ASC, id DESC LIMIT ' . max(1, $perPage) . ' OFFSET ' . max(0, ($page - 1) * $perPage),
            $bindings,
        )->getResultArray();

        return ['rows' => $rows, 'total' => $total];
    }

    public static function count(): int
    {
        return self::ready() ? self::table()->countAllResults() : 0;
    }

    /**
     * WHERE parts shared by both sources. The user filter matches the typed
     * text against usernames and emails, and against the names copied into
     * the log, so a deleted user's history is still found.
     *
     * @param array<string, string> $filters
     *
     * @return array{0: list<string>, 1: list<mixed>}
     */
    private static function conditions(array $filters, string $at, string $actorId, string $actorLabel, string $subjectId, string $subjectLabel): array
    {
        $where = [];
        $binds = [];
        $user = trim($filters['user'] ?? '');

        if ($user !== '') {
            $ids = self::userIds($user);
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $user) . '%';
            $idList = implode(',', $ids !== [] ? $ids : [0]);
            $where[] = "({$actorId} IN ({$idList}) OR ({$subjectId} IN ({$idList})) OR {$actorLabel} LIKE ? OR ({$subjectLabel} LIKE ?))";
            $binds[] = $like;
            $binds[] = $like;
        }

        foreach (['from' => ['>=', ' 00:00:00'], 'to' => ['<=', ' 23:59:59']] as $key => [$op, $time]) {
            $day = $filters[$key] ?? '';

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $day) === 1) {
                $where[] = "{$at} {$op} ?";
                $binds[] = $day . $time;
            }
        }

        return [$where, $binds];
    }

    /** @return list<int> */
    private static function userIds(string $text): array
    {
        // CI4 appends ESCAPE to LIKE but leaves the value alone (as in UsersController::index).
        $escape = db_connect()->likeEscapeChar;
        $text = str_replace([$escape, '%', '_'], [$escape . $escape, $escape . '%', $escape . '_'], $text);
        $users = db_connect()->table(config('Auth')->tables['users'])->select('id')->like('username', $text)->get()->getResultArray();
        $emails = db_connect()->table(config('Auth')->tables['identities'])->select('user_id AS id')
            ->where('type', 'email_password')->like('secret', $text)->get()->getResultArray();

        return array_values(array_unique(array_map('intval', array_column([...$users, ...$emails], 'id'))));
    }

    /** @param list<string> $where */
    private static function where(array $where): string
    {
        return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    }

    /**
     * Deletes entries older than the retention setting, once a day, on the
     * first write of that day: no cron job needed.
     */
    private static function prune(): void
    {
        helper('setting');
        $today = date('Y-m-d');

        if (setting('RoleWarden.activityPrunedOn') === $today) {
            return;
        }

        $cutoff = self::cutoff((int) setting('RoleWarden.activityRetentionDays'), time());

        if ($cutoff !== null) {
            self::table()->where('created_at <', $cutoff)->delete();
        }

        setting('RoleWarden.activityPrunedOn', $today);
    }

    /**
     * Whether the log table exists: between replacing the module's folder and
     * running its migrations it does not, and nothing is written.
     */
    private static function ready(): bool
    {
        return self::$ready ??= db_connect()->tableExists(config('RoleWarden')->table('activity_log'));
    }

    private static function table(): BaseBuilder
    {
        return db_connect()->table(config('RoleWarden')->table('activity_log'));
    }
}
