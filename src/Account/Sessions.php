<?php

declare(strict_types=1);

namespace RoleWarden\Account;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\HTTP\UserAgent;

/**
 * Signed-in sessions of Shield's session authenticator, one row per browser in
 * the `sessions` table, so a user or an admin can list them and revoke them.
 *
 * Shield keeps no such list. track() runs on every request (pre_system, see
 * Config/Events.php): the first request of a signed-in session stores a random
 * token in it and a row with its hash; any later request whose row is gone, or
 * idle longer than the Session lifetime setting, is signed out on the spot. So a
 * revocation holds from the very next request.
 *
 * A row also records the selector of the Shield remember-me token its browser
 * holds: revoking the session deletes that token too, otherwise the browser
 * would sign itself back in, and revoking the token ends the session.
 */
final class Sessions
{
    private const KEY = 'rolewarden_session';

    /** Seconds between two writes of last_seen_at for the same session. */
    private const TOUCH_EVERY = 60;

    /** SHA-256 of the token of this request's session, once tracked. */
    private static ?string $current = null;

    public static function track(): void
    {
        // No cookie, no session to look at: don't start one for every guest.
        if (is_cli() || $_COOKIE === []) {
            return;
        }

        helper('setting');
        $info = session(setting('Auth.sessionConfig')['field']);

        // Not signed in, or halfway through a Shield auth action.
        if (! is_array($info) || ! isset($info['id']) || isset($info['auth_action'])) {
            return;
        }

        $userId = (int) $info['id'];
        $token = session(self::KEY);

        if (! is_string($token)) {
            $token = bin2hex(random_bytes(32));
            session()->set(self::KEY, $token);
            self::$current = hash('sha256', $token);
            $request = service('request');
            $now = date('Y-m-d H:i:s');

            self::table()->insert([
                'user_id' => $userId,
                'token_hash' => self::$current,
                'remember_selector' => self::rememberSelector($userId),
                'ip_address' => $request->getIPAddress(),
                'user_agent' => mb_substr((string) $request->getUserAgent(), 0, 255),
                'created_at' => $now,
                'last_seen_at' => $now,
            ]);

            return;
        }

        $hash = hash('sha256', $token);
        $row = self::table()->where('token_hash', $hash)->where('user_id', $userId)->get()->getRowArray();

        if ($row === null) {
            self::signOut($userId, true);

            return;
        }

        $idle = time() - strtotime((string) $row['last_seen_at']);

        if ($idle > (int) setting('RoleWarden.sessionLifetime')) {
            // Idle, not revoked: a remembered browser may sign itself back in.
            self::table()->where('id', $row['id'])->delete();
            self::signOut($userId, false);

            return;
        }

        self::$current = $hash;
        $changes = $idle >= self::TOUCH_EVERY ? ['last_seen_at' => date('Y-m-d H:i:s')] : [];

        // "Remember me" sets its cookie on the sign-in response, so the link is made on a later request.
        if ($row['remember_selector'] === null && ($selector = self::rememberSelector($userId)) !== null) {
            $changes['remember_selector'] = $selector;
        }

        if ($changes !== []) {
            self::table()->where('id', $row['id'])->update($changes);
        }
    }

    /**
     * Shield's logout event: the session data is already gone, so the row is
     * found through the hash kept by track() earlier in the same request.
     */
    public static function forgetCurrent(): void
    {
        if (self::$current !== null) {
            self::table()->where('token_hash', self::$current)->delete();
            self::$current = null;
        }
    }

    /**
     * The user's sessions, most recent first, and the remember-me tokens no
     * session is using right now (a remembered browser that was closed).
     *
     * @return array{sessions: list<array<string, mixed>>, remembered: list<array<string, mixed>>}
     */
    public static function forUser(int $userId): array
    {
        $tokens = [];

        foreach (self::rememberTable()->where('user_id', $userId)->where('expires >', date('Y-m-d H:i:s'))->get()->getResultArray() as $token) {
            $tokens[$token['selector']] = $token;
        }

        $sessions = [];
        $agent = new UserAgent();

        foreach (self::table()->where('user_id', $userId)->orderBy('last_seen_at', 'desc')->get()->getResultArray() as $row) {
            $agent->parse($row['user_agent']);
            $token = $row['remember_selector'] !== null ? ($tokens[$row['remember_selector']] ?? null) : null;
            unset($tokens[$row['remember_selector'] ?? '']);

            $sessions[] = [
                'id' => (int) $row['id'],
                'browser' => $agent->getBrowser(),
                'platform' => $agent->getPlatform(),
                'ip' => $row['ip_address'],
                'createdAt' => $row['created_at'],
                'lastSeenAt' => $row['last_seen_at'],
                'rememberedUntil' => $token['expires'] ?? null,
                'current' => $row['token_hash'] === self::$current,
            ];
        }

        $remembered = array_map(static fn (array $token): array => [
            'id' => (int) $token['id'],
            'createdAt' => $token['created_at'] ?? null,
            'expires' => $token['expires'],
        ], array_values($tokens));

        return ['sessions' => $sessions, 'remembered' => $remembered];
    }

    public static function revokeSession(int $userId, int $id): bool
    {
        $row = self::table()->where('id', $id)->where('user_id', $userId)->get()->getRowArray();

        if ($row === null) {
            return false;
        }

        self::table()->where('id', $id)->delete();

        if ($row['remember_selector'] !== null) {
            self::rememberTable()->where('selector', $row['remember_selector'])->where('user_id', $userId)->delete();
        }

        return true;
    }

    public static function revokeRemembered(int $userId, int $tokenId): bool
    {
        $token = self::rememberTable()->where('id', $tokenId)->where('user_id', $userId)->get()->getRowArray();

        if ($token === null) {
            return false;
        }

        self::rememberTable()->where('id', $tokenId)->delete();
        self::table()->where('remember_selector', $token['selector'])->where('user_id', $userId)->delete();

        return true;
    }

    /**
     * Every session and remember-me token of the user, except the session
     * making this request (and its token), so "sign out everywhere else" keeps
     * the one who asked signed in.
     */
    public static function revokeAll(int $userId): void
    {
        $keep = self::$current !== null
            ? self::table()->where('token_hash', self::$current)->where('user_id', $userId)->get()->getRowArray()
            : null;

        $sessions = self::table()->where('user_id', $userId);
        $tokens = self::rememberTable()->where('user_id', $userId);

        if ($keep !== null) {
            $sessions->where('id !=', $keep['id']);

            if ($keep['remember_selector'] !== null) {
                $tokens->where('selector !=', $keep['remember_selector']);
            }
        }

        $sessions->delete();
        $tokens->delete();
    }

    /**
     * Signs this browser out without Shield's logout(), which would also purge
     * the remember-me tokens of the user's other browsers.
     */
    private static function signOut(int $userId, bool $revoked): void
    {
        if ($revoked && ($selector = self::cookieSelector()) !== null) {
            self::rememberTable()->where('selector', $selector)->where('user_id', $userId)->delete();
        }

        $session = session();

        foreach (array_keys($session->get() ?? []) as $key) {
            $session->remove($key);
        }

        $session->regenerate(true);
    }

    /** Selector of this browser's remember-me cookie, when it names a live token of the user. */
    private static function rememberSelector(int $userId): ?string
    {
        $selector = self::cookieSelector();

        if ($selector === null) {
            return null;
        }

        $found = self::rememberTable()->where('selector', $selector)->where('user_id', $userId)->countAllResults() > 0;

        return $found ? $selector : null;
    }

    private static function cookieSelector(): ?string
    {
        $cookie = service('request')->getCookie(setting('Cookie.prefix') . setting('Auth.sessionConfig')['rememberCookieName']);

        if (! is_string($cookie) || ! str_contains($cookie, ':')) {
            return null;
        }

        return explode(':', $cookie, 2)[0];
    }

    private static function table(): BaseBuilder
    {
        return db_connect()->table(config('RoleWarden')->table('sessions'));
    }

    private static function rememberTable(): BaseBuilder
    {
        return db_connect()->table(config('Auth')->tables['remember_tokens']);
    }
}
