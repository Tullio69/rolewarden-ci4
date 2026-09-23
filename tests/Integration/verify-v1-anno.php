<?php
/**
 * UserDetail "disable the control and explain in the marginal note" (author decision 2026-09-23).
 * Black-box: HTTP against the panel, direct queries on rolewarden_test only. No module imports.
 * Run by verify-v1-a1a4.sh on a fresh README install, in development and in production.
 */
declare(strict_types=1);
require __DIR__ . '/verify-v1.lib.php';
require __DIR__ . '/verify-v1.fixtures.php';
$f = make_fixtures(); // Dana: only active super admin; Priya: only role admin; Mara: user; Lena: inactive admin
$u = $f['u']; $pw = $f['pw']; $role = $f['role']; $perm = $f['perm'];
$env = getenv('RW_ANNO_ENV') ?: 'development';
$bodies = [];

/** Ledger rows of the page: [key label, control cell html, marginal-note text (decoded), raw note html]. */
function ledger(string $html): array
{
    preg_match_all('/<tr\b.*?<\/tr>/s', $html, $m);
    $rows = [];
    foreach ($m[0] as $tr) {
        if (! preg_match('/<td class="rw-anno">(.*?)<\/td>/s', $tr, $a)) continue;
        preg_match('/<(?:th|td) class="rw-l-key"[^>]*>(.*?)<\/(?:th|td)>/s', $tr, $k);
        preg_match('/<td class="rw-l-ctl">(.*?)<\/td>/s', $tr, $c);
        $rows[] = [trim(html_entity_decode(strip_tags($k[1] ?? ''))), $c[1] ?? '', trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($a[1])))), $a[1]];
    }
    return $rows;
}
function row(array $rows, string $key): ?array
{
    foreach ($rows as $r) if ($r[0] === $key) return $r;
    return null;
}
/** State of a named button in a control cell: 'disabled', 'enabled', or 'absent'. */
function btn(?array $row, string $label): string
{
    if ($row === null || ! preg_match('/<button\b([^>]*)>\s*' . preg_quote($label, '/') . '\s*<\/button>/', $row[1], $b)) return 'absent';
    return preg_match('/\sdisabled\b/', $b[1]) ? 'disabled' : 'enabled';
}
/** Note HTML is well-formed escaped text: only the rw-note span, no other tag, no bare ampersand. */
function note_escaped(string $raw): bool
{
    $inner = preg_replace('/^\s*<span class="rw-note">(.*)<\/span>\s*$/s', '$1', $raw);
    return ! preg_match('/[<>]/', $inner) && ! preg_match('/&(?![a-zA-Z]+;|#\d+;|#x[0-9a-fA-F]+;)/', $inner);
}
function page(Client $c, string $path, string $label): array
{
    $c->get($path);
    $GLOBALS['bodies'][] = [$label, $c->status, $c->body];
    return [$c->status, ledger($c->clean())];
}
function sign_in(string $key): Client
{
    $c = new Client('anno-' . $key . '-' . $GLOBALS['env']);
    check('FIX', "{$GLOBALS['u'][$key]['name']} signs in", $c->login($GLOBALS['u'][$key]['email'], $GLOBALS['pw']), "HTTP {$c->status}");
    return $c;
}
$E = strtoupper(substr($env, 0, 4)); // DEVE / PROD prefix in check ids
$show = fn(array $rows) => json_encode(array_map(fn($r) => [$r[0], $r[2]], $rows));

echo "== [$env] own page, sole roles.assign role (Priya: admin only)\n";
$priya = sign_in('admin2');
[$st, $rows] = page($priya, 'rolewarden/users/' . $u['admin2']['id'], 'priya own page');
$adminRow = row($rows, 'Admin');
$tRole = $adminRow[2] ?? '';
check("$E.R1", 'own page: Remove of the admin role disabled', $st === 200 && btn($adminRow, 'Remove') === 'disabled', "HTTP $st; Remove=" . btn($adminRow, 'Remove'));
check("$E.R2", 'own page: marginal note of the admin row carries the explanation', $tRole !== '' && preg_match('/role/i', $tRole) === 1, "note='$tRole'");
check("$E.R3", 'the explanation appears on no other row', count(array_filter($rows, fn($r) => $r !== $adminRow && $r[2] === $tRole)) === 0, $show($rows));
echo "EVIDENCE $E role note: '$tRole'\n";

echo "== [$env] own page: Deactivate / Delete\n";
$status = row($rows, 'Status'); $delete = row($rows, 'Delete');
$tSelf = $status[2] ?? '';
check("$E.S1", 'own page: Deactivate disabled', btn($status, 'Deactivate') === 'disabled', 'Deactivate=' . btn($status, 'Deactivate'));
check("$E.S2", 'own page: Status note explains (not the ordinary "reversible")', $tSelf !== '' && strcasecmp($tSelf, 'reversible') !== 0 && preg_match('/own|yourself/i', $tSelf) === 1, "note='$tSelf'");
check("$E.S3", 'own page: Delete disabled', btn($delete, 'Delete') === 'disabled', 'Delete=' . btn($delete, 'Delete'));
check("$E.S4", 'own page: Delete note explains the same self protection', ($delete[2] ?? '') === $tSelf && $tSelf !== '', "note='" . ($delete[2] ?? '') . "'");
check("$E.S5", 'own page: self-protection text is not on the role or password rows', count(array_filter($rows, fn($r) => ! in_array($r[0], ['Status', 'Delete'], true) && $r[2] === $tSelf)) === 0, $show($rows));
echo "EVIDENCE $E self note: '$tSelf'\n";

echo "== [$env] own page with a second roles.assign grant (role removable)\n";
q('INSERT INTO acl_roles (slug,name,parent_id,is_system,is_super_admin,created_at) VALUES (?,?,NULL,0,0,NOW())', ['anno-grant', 'Anno Grant']);
q('INSERT INTO acl_role_permissions (role_id,permission_id) VALUES (?,?)', [$role('anno-grant'), $perm('roles.assign')]);
q('INSERT INTO acl_user_roles (user_id,role_id) VALUES (?,?)', [$u['admin2']['id'], $role('anno-grant')]);
[$st, $rows] = page($priya, 'rolewarden/users/' . $u['admin2']['id'], 'priya own page, two grants');
check("$E.R4", 'removable admin role: Remove enabled on both role rows', btn(row($rows, 'Admin'), 'Remove') === 'enabled' && btn(row($rows, 'Anno Grant'), 'Remove') === 'enabled', 'Admin=' . btn(row($rows, 'Admin'), 'Remove') . ' AnnoGrant=' . btn(row($rows, 'Anno Grant'), 'Remove'));
check("$E.R5", 'removable role: no lock explanation on any role row', ($r = row($rows, 'Admin')) && $r[2] !== $tRole && row($rows, 'Anno Grant')[2] !== $tRole && count(array_filter($rows, fn($r) => $r[2] === $tRole)) === 0, $show($rows));
q('DELETE FROM acl_user_roles WHERE user_id=? AND role_id=?', [$u['admin2']['id'], $role('anno-grant')]);

echo "== [$env] last super admin's page viewed by another admin (Priya -> Dana)\n";
[$st, $rows] = page($priya, 'rolewarden/users/' . $u['owner']['id'], 'dana page by priya');
$status = row($rows, 'Status'); $delete = row($rows, 'Delete');
$tLast = $status[2] ?? '';
check("$E.L1", 'last super admin: Deactivate and Delete disabled', $st === 200 && btn($status, 'Deactivate') === 'disabled' && btn($delete, 'Delete') === 'disabled', 'Deactivate=' . btn($status, 'Deactivate') . ' Delete=' . btn($delete, 'Delete'));
check("$E.L2", 'last super admin: both notes explain the last-super-admin protection', $tLast !== '' && ($delete[2] ?? '') === $tLast && preg_match('/super.?admin/i', $tLast) === 1 && $tLast !== $tSelf, "status='$tLast' delete='" . ($delete[2] ?? '') . "'");
echo "EVIDENCE $E last-super-admin note: '$tLast'\n";
// Priya can activate/delete: on a normal user both controls are live (checked below). Negative: a second super admin lifts the lock.
q('INSERT INTO users (username,active,created_at) VALUES (?,1,NOW())', ['AnnoSecondSuper']);
$second = (int) db()->insert_id;
q('INSERT INTO acl_user_roles (user_id,role_id) VALUES (?,?)', [$second, $role('super-admin')]);
[$st, $rows] = page($priya, 'rolewarden/users/' . $u['owner']['id'], 'dana page, two super admins');
check("$E.L3", 'with a second active super admin: Deactivate/Delete enabled, no lock note', btn(row($rows, 'Status'), 'Deactivate') === 'enabled' && btn(row($rows, 'Delete'), 'Delete') === 'enabled' && row($rows, 'Status')[2] !== $tLast && row($rows, 'Delete')[2] !== $tLast, $show($rows));
q('DELETE FROM acl_user_roles WHERE user_id=?', [$second]);
q('DELETE FROM users WHERE id=?', [$second]);

echo "== [$env] normal and inactive users viewed by an admin\n";
q('INSERT INTO acl_roles (slug,name,parent_id,is_system,is_super_admin,created_at) VALUES (?,?,NULL,0,0,NOW())', ['anno-esc', 'Anno <i>x</i> & "q"']);
q('INSERT INTO acl_user_roles (user_id,role_id) VALUES (?,?)', [$u['subject']['id'], $role('anno-esc')]);
[$st, $rows] = page($priya, 'rolewarden/users/' . $u['subject']['id'], 'mara page by priya');
$raw = $priya->clean();
$status = row($rows, 'Status'); $delete = row($rows, 'Delete');
check("$E.N1", 'normal user: Deactivate and Delete enabled', $st === 200 && btn($status, 'Deactivate') === 'enabled' && btn($delete, 'Delete') === 'enabled', 'Deactivate=' . btn($status, 'Deactivate') . ' Delete=' . btn($delete, 'Delete'));
check("$E.N2", 'normal user: Status note is the ordinary "reversible" note', strcasecmp($status[2] ?? '', 'reversible') === 0, "note='" . ($status[2] ?? '') . "'");
check("$E.N3", 'normal user: Delete note carries no lock explanation', ! in_array($delete[2] ?? '', [$tSelf, $tLast, $tRole], true) && ($delete[2] ?? '') === '', "note='" . ($delete[2] ?? '') . "'");
check("$E.N4", 'normal user: role rows removable with no lock note', btn(row($rows, 'User'), 'Remove') === 'enabled' && count(array_filter($rows, fn($r) => in_array($r[2], [$tSelf, $tLast, $tRole], true))) === 0, $show($rows));
check("$E.X1", 'role name with markup is escaped on the page', str_contains($raw, 'Anno &lt;i&gt;x&lt;/i&gt; &amp;') && ! str_contains($raw, 'Anno <i>x</i>'), 'raw role name found');
[$st, $rows] = page($priya, 'rolewarden/users/' . $u['disabled']['id'], 'lena page by priya');
$status = row($rows, 'Status');
check("$E.N5", 'inactive user: Activate enabled with the ordinary "reversible" note', btn($status, 'Activate') === 'enabled' && btn($status, 'Deactivate') === 'absent' && strcasecmp($status[2] ?? '', 'reversible') === 0, 'Activate=' . btn($status, 'Activate') . " note='" . ($status[2] ?? '') . "'");

echo "== [$env] Dana on her own page (self and last super admin at once)\n";
$dana = sign_in('owner');
[$st, $rows] = page($dana, 'rolewarden/users/' . $u['owner']['id'], 'dana own page');
$status = row($rows, 'Status'); $delete = row($rows, 'Delete');
check("$E.D1", 'owner own page: Deactivate/Delete disabled, both notes explain (non-empty, not "reversible")', btn($status, 'Deactivate') === 'disabled' && btn($delete, 'Delete') === 'disabled' && in_array($status[2] ?? '', [$tSelf, $tLast], true) && in_array($delete[2] ?? '', [$tSelf, $tLast], true), $show($rows));
echo "EVIDENCE $E owner own page notes: status='" . ($status[2] ?? '') . "' delete='" . ($delete[2] ?? '') . "'\n";

echo "== [$env] escaping and SQL text\n";
$bad = []; $leaks = [];
foreach ($bodies as [$label, $code, $body]) {
    preg_match_all('/<td class="rw-anno">(.*?)<\/td>/s', clean_html($body), $m);
    foreach ($m[1] as $n) if (trim($n) !== '' && ! note_escaped($n)) $bad[] = "$label: $n";
    if (leaks_sql($body)) $leaks[] = "$label HTTP $code";
}
check("$E.X2", 'every marginal note is escaped text inside rw-note (' . count($bodies) . ' pages)', ! $bad, implode(' | ', $bad));
check("$E.SQL", 'no SQL/DB error text on the pages', ! $leaks, implode('; ', $leaks));

$pass = count(array_filter($GLOBALS['results'], fn($r) => $r[2]));
$fail = count($GLOBALS['results']) - $pass;
echo "TOTAL marginal notes ($env): $pass PASS, $fail FAIL\n";
exit($fail ? 1 : 0);
