<?php

/**
 * Production-mode spot check for verify-v1 (same data as the development run):
 * failures seen in development must not show SQL text in production either, and the
 * affected feature is re-tested to tell a development-only artefact from a real defect.
 */

declare(strict_types=1);

require __DIR__ . '/verify-v1.lib.php';

$pw = 'V1p-' . bin2hex(random_bytes(6)) . '-Aa1!';
$oid = (int) q1("SELECT user_id FROM auth_identities WHERE secret='dana@northwind-studio.test'");
q('UPDATE auth_identities SET secret2=? WHERE user_id=?', [password_hash($pw, PASSWORD_DEFAULT), $oid]);

$o = new Client('prod-owner');
check('Q00', 'production: owner signs in through the RoleWarden login view', $o->login('dana@northwind-studio.test', $pw), "HTTP {$o->status}");
$o->get('rolewarden/users');
check('Q01', 'production: users list 200', $o->status === 200, "HTTP {$o->status}");
foreach (['rolewarden/users?q=mara', 'rolewarden/users?q=Priya'] as $u) {
    $o->get($u);
    check('Q02', "production: $u returns 200 (search works)", $o->status === 200, "HTTP {$o->status}; body starts: " . substr(trim(strip_tags(clean_html($o->body))), 0, 120));
    check('Q03', "production: $u shows no SQL text", ! leaks_sql($o->body), 'SQL text on screen');
}
$mid = (int) q1("SELECT user_id FROM auth_identities WHERE secret='mara@northwind-studio.test'");
$o->post("rolewarden/users/$mid/roles", ['role_id' => '99999'], "rolewarden/users/$mid");
check('Q05', "production: add nonexistent role 99999 to a user: no 500, no SQL text (HTTP {$o->status})", $o->status < 500 && ! leaks_sql($o->body), 'body starts: ' . substr(trim(strip_tags(clean_html($o->body))), 0, 160));
$o->post('rolewarden/users/99999/roles', ['role_id' => (string) q1("SELECT id FROM acl_roles WHERE slug='user'")], 'rolewarden/users');
check('Q05b', "production: add a role to nonexistent user 99999: no 500, no SQL text (HTTP {$o->status})", $o->status < 500 && ! leaks_sql($o->body), 'body starts: ' . substr(trim(strip_tags(clean_html($o->body))), 0, 160));
$o->post("rolewarden/users/$oid/deactivate", [], "rolewarden/users/$oid");
check('Q06', "production: self-deactivation refused, no 500 (HTTP {$o->status})", $o->status < 500 && (int) q1('SELECT active FROM users WHERE id=?', [$oid]) === 1, 'active=' . q1('SELECT active FROM users WHERE id=?', [$oid]));
q('UPDATE users SET active=1 WHERE id=?', [$oid]);
$o->post("rolewarden/users/$oid/delete", [], "rolewarden/users/$oid");
check('Q07', "production: self-deletion refused, no 500 (HTTP {$o->status})", $o->status < 500 && (int) q1('SELECT COUNT(*) FROM users WHERE id=? AND deleted_at IS NULL', [$oid]) === 1, 'deleted');
q('UPDATE users SET deleted_at=NULL WHERE id=?', [$oid]);
$a = new Client('prod-anon');
$a->get('login');
check('Q08', 'production: login page has the recovery link and a static type="password"', str_contains($a->body, 'login/magic-link') && (bool) preg_match('/<input[^>]*\stype="password"[^>]*name="password"|<input[^>]*name="password"[^>]*\stype="password"/', $a->body), 'missing');
$o->get('rolewarden/users/99999');
check('Q04', 'production: nonexistent user 404, no SQL text', $o->status === 404 && ! leaks_sql($o->body), "HTTP {$o->status}");

$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
echo "\nTOTAL (production): $pass PASS, $fail FAIL\n";
exit($fail ? 1 : 0);
