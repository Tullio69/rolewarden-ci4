<?php
// R1 copy of verify-v8.lib.php: identical, except that it loads verify-r1.v7lib.php (application on PHP 8.5 in Docker).
/**
 * V8 "Demo e documentazione" black-box helpers (Collaudatore ad Hoc, Claude).
 * Reuses verify-v7.lib.php (HTTP client, mysqli on rolewarden_test:3317 only, php -S with the
 * V7 router, spark, junctions) and adds a complete database state for "no residue" checks.
 * Credentials only from RW_DB_USERNAME / RW_DB_PASSWORD (process), never written to a file.
 */
declare(strict_types=1);

require_once __DIR__ . '/verify-r1.v7lib.php'; // R1: application on PHP 8.5 in Docker

/**
 * Complete content of rolewarden_test: every table, its CREATE TABLE (AUTO_INCREMENT counter
 * stripped, reported apart) and every row of every column, ordered by all columns.
 * Nothing is excluded: this is the "identical to before" comparison of the definition of done.
 *
 * @return array<string, array{create: string, ai: ?string, rows: list<array>}>
 */
function fullstate(): array
{
    $out = [];
    foreach (tables() as $t) {
        $create = (string) q("SHOW CREATE TABLE `$t`")[0]['Create Table'];
        preg_match('/ AUTO_INCREMENT=(\d+)/', $create, $m);
        $create = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create);
        $cols = array_map(fn ($r) => '`' . $r['Field'] . '`', q("SHOW COLUMNS FROM `$t`"));
        $rows = q("SELECT * FROM `$t` ORDER BY " . implode(',', $cols));
        $out[$t] = ['create' => $create, 'ai' => $m[1] ?? null, 'rows' => $rows];
    }
    return $out;
}

/** Human-readable differences between two fullstate() results (AUTO_INCREMENT listed apart). */
function statediff(array $a, array $b, bool $withAutoIncrement = false): array
{
    $d = [];
    foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $t) {
        if (! isset($a[$t])) { $d[] = "table added: $t (" . count($b[$t]['rows']) . ' rows)'; continue; }
        if (! isset($b[$t])) { $d[] = "table removed: $t"; continue; }
        if ($a[$t]['create'] !== $b[$t]['create']) $d[] = "structure changed: $t";
        if ($withAutoIncrement && $a[$t]['ai'] !== $b[$t]['ai']) $d[] = "AUTO_INCREMENT $t: {$a[$t]['ai']} -> {$b[$t]['ai']}";
        $ra = array_map('json_encode', $a[$t]['rows']);
        $rb = array_map('json_encode', $b[$t]['rows']);
        $gone = array_diff($ra, $rb);
        $new = array_diff($rb, $ra);
        foreach (array_slice($gone, 0, 5) as $r) $d[] = "$t row missing: " . substr($r, 0, 300);
        foreach (array_slice($new, 0, 5) as $r) $d[] = "$t row extra: " . substr($r, 0, 300);
        if (count($gone) > 5 || count($new) > 5) $d[] = "$t: " . count($gone) . ' missing, ' . count($new) . ' extra in total';
    }
    return $d;
}

/** A super admin written straight into the database (Shield format, password_hash default). */
function make_owner(string $email, string $pw, string $username = 'v8owner', string $role = 'super-admin', int $active = 1): int
{
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO users (username, active, created_at, updated_at) VALUES (?,?,?,?)', [$username, $active, $now, $now]);
    $id = (int) db()->insert_id;
    q('INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at, updated_at) VALUES (?,?,?,?,0,?,?)', [$id, 'email_password', $email, password_hash($pw, PASSWORD_DEFAULT), $now, $now]);
    if ($role !== '') q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?, (SELECT id FROM acl_roles WHERE slug = ?))', [$id, $role]);
    return $id;
}

/** Signs in through Shield's /login; true when Shield redirects away from the login page. */
function signin(Client $c, string $email, string $pw): bool
{
    $h = $c->go('login');
    $c->post('login', ['csrf_test_name' => csrf_of($h), 'email' => $email, 'password' => $pw]);
    return in_array($c->status, [302, 303], true) && ! str_contains($c->location, '/login');
}

function uid(string $email): int
{
    return (int) q1("SELECT user_id FROM auth_identities WHERE type='email_password' AND secret=?", [$email]);
}
