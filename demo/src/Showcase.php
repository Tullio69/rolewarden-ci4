<?php

declare(strict_types=1);

namespace RoleWardenDemo;

/**
 * Showcase mode (SPEC, V8 decisions): switching the session between the demo
 * accounts without a password, for the online demo and product videos. Off
 * unless demo.showcase is true, and never in production.
 */
final class Showcase
{
    public static function enabled(): bool
    {
        return ENVIRONMENT !== 'production' && config('Demo')->showcase === true;
    }

    /**
     * The demo accounts the bar may switch to: only those demo:install created.
     *
     * @return list<array{id: int, username: string, role: string}>
     */
    public static function accounts(): array
    {
        helper('setting');
        $ids = array_map('intval', (array) setting('Demo.showcaseUsers'));

        if ($ids === []) {
            return [];
        }

        $cfg = config('RoleWarden');
        $rows = db_connect()->table(config('Auth')->tables['users'] . ' u')
            ->select('u.id, u.username, r.slug, r.name')
            ->join($cfg->table('user_roles') . ' ur', 'ur.user_id = u.id', 'left')
            ->join($cfg->table('roles') . ' r', 'r.id = ur.role_id', 'left')
            ->whereIn('u.id', $ids)->where('u.active', 1)->where('u.deleted_at', null)
            ->orderBy('u.id')->get()->getResultArray();

        $accounts = [];

        foreach ($rows as $row) {
            $accounts[(int) $row['id']] ??= ['id' => (int) $row['id'], 'username' => (string) $row['username'], 'role' => (string) (\RoleWarden\Models\SeededText::role($row)['name'] ?? '')];
        }

        return array_values($accounts);
    }

    public static function canSwitchTo(int $userId): bool
    {
        return self::enabled() && in_array($userId, array_column(self::accounts(), 'id'), true);
    }
}
