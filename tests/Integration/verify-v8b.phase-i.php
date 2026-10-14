<?php
// Phase I: installer restart (SPEC V7 "Installer", decision A2 of 2026-10-10): without the lock file and without an
// ACTIVE super admin the installer starts again, always behind the setup code; with an active super admin it does not.
// Own copy of the test application (the installer may rewrite its .env), same database.
$IA = app_copy('appinst', $MODFULL, true);
$lock = "$IA/writable/rolewarden/installed.lock";
$codef = "$IA/writable/rolewarden/install-code.txt";
function istep(string $h): string { $f = form_with($h, 'step'); return $f['fields']['step'] ?? 'none'; }
function ipost(Client $c, array $v, string $tag, bool $csrf = true): string
{
    $h = G($c, 'rolewarden/install', "GET before $tag");
    $tok = csrf_of($h);
    if ($tok === '') $tok = csrf_of(G($c, 'login', 'token'));
    return P($c, 'rolewarden/install', ($csrf ? ['csrf_test_name' => $tok] : []) + $v, $tag);
}
$already = fn (string $h) => str_contains(text_of($h), 'Already installed') && form_with($h, 'step') === null;
$sa = fn () => (int) q1("SELECT COUNT(*) FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id JOIN users u ON u.id=ur.user_id WHERE r.is_super_admin=1 AND r.deleted_at IS NULL AND u.active=1 AND u.deleted_at IS NULL");
$ci = serve8($IA, 'inst');
check('I00', 'Fixture: no lock file, one active super admin (owner), demo installed', ! file_exists($lock) && $sa() === 1, 'sa=' . $sa());

// I01 active super admin, no lock
$state = dbstate();
$h = G(c8('i1'), 'rolewarden/install', 'I01');
check('I01', 'No lock, active super admin: installer refuses ("Already installed", no form), no setup code created', $already($h) && ! file_exists($codef), text_of($h));
$tok = csrf_of(G($ci, 'login', 'tok'));
foreach ([['step' => 'migrate'], ['step' => 'admin', 'username' => 'intr8b', 'email' => 'intr8b@v8b.test', 'password' => OPW . 'x', 'password_confirm' => OPW . 'x']] as $a) {
    $h = P($ci, 'rolewarden/install', ['csrf_test_name' => $tok] + $a, "I01 POST {$a['step']}");
    check('I01-' . $a['step'][0], "No lock, active super admin: hand-made POST of step {$a['step']} (valid CSRF) changes nothing", dbstate() === $state && ! leaks_sql($h) && ! file_exists($codef), "status {$ci->status} " . short(text_of($h), 200));
    $tok = csrf_of(G($ci, 'login', 'tok'));
}

// I02 the only super admin deactivated (other accounts, e.g. an admin, still active), no lock
q('UPDATE users SET active=0 WHERE id=?', [$OWNER]);
check('I02f', 'Fixture: owner deactivated; admin and plain user still active; no active super admin', $sa() === 0 && (int) q1('SELECT active FROM users WHERE id=?', [$ADMIN]) === 1);
$c2 = c8('i2');
$h = G($c2, 'rolewarden/install', 'I02');
$code = trim((string) @file_get_contents($codef));
check('I02', 'No lock, super admin present but deactivated: the installer starts again at the setup-code step', ! $already($h) && istep($h) === 'code' && $c2->status === 200 && ! leaks_sql($h), "status {$c2->status} step " . istep($h) . ' ' . short(text_of($h), 300));
check('I02b', 'Setup code saved in writable/rolewarden/install-code.txt and not printed in the page', strlen($code) >= 8 && ! str_contains($h, $code), 'len ' . strlen($code));
// behind the code: later steps refused, wrong code refused
$state = dbstate();
foreach ([['step' => 'migrate'], ['step' => 'admin', 'username' => 'skip8b', 'email' => 'skip8b@v8b.test', 'password' => 'Skip-Lantern-8840', 'password_confirm' => 'Skip-Lantern-8840'], ['step' => 'database', 'baseURL' => 'http://evil.test/', 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => 'x', 'password' => 'y']] as $a) {
    $envb = (string) file_get_contents("$IA/.env");
    $h = ipost($c2, $a, "I03 {$a['step']} without code");
    check('I03-' . $a['step'][0], "Restarted installer, step {$a['step']} posted before the code: nothing written (database, .env), no SQL", dbstate() === $state && file_get_contents("$IA/.env") === $envb && uid('skip8b@v8b.test') === 0 && ! leaks_sql($h), istep($h) . ' ' . short(text_of($h), 200));
}
$h = ipost($c2, ['step' => 'code', 'code' => 'WRONG-' . $code], 'I04 wrong code');
check('I04', 'Restarted installer: wrong code refused, still at the code step', istep($h) === 'code' && dbstate() === $state, short(text_of($h), 200));
$h = ipost($c2, ['step' => 'code', 'code' => $code], 'I04 no csrf', false);
check('I04b', 'Restarted installer: right code without CSRF refused', istep($h) !== 'database' && ! form_with($h, 'hostname'), "status {$c2->status}");
// through the code and on to a new super admin (the recovery path)
$h = ipost($c2, ['step' => 'code', 'code' => $code], 'I05 code ok');
$steps = [istep($h)];
for ($i = 0; $i < 6; $i++) {
    $s = istep($h);
    if ($s === 'database') $h = ipost($c2, ['step' => 'database', 'baseURL' => BASE, 'hostname' => '127.0.0.1', 'port' => '3317', 'database' => 'rolewarden_test', 'username' => getenv('RW_DB_USERNAME'), 'password' => getenv('RW_DB_PASSWORD')], 'I05 db');
    elseif ($s === 'migrate') $h = ipost($c2, ['step' => 'migrate'], 'I05 migrate');
    elseif ($s === 'admin') { $h = ipost($c2, ['step' => 'admin', 'username' => 'rescue8b', 'email' => 'rescue8b@v8b.test', 'password' => 'Lantern-Quay-4417', 'password_confirm' => 'Lantern-Quay-4417'], 'I05 admin'); $steps[] = 'admin-posted'; break; }
    else { $h = G($c2, 'rolewarden/install', 'I05 next'); }
    $steps[] = istep($h);
}
info('restarted installer steps: ' . implode(' > ', $steps) . ' / ' . short(text_of($h), 200));
$ru = uid('rescue8b@v8b.test');
check('I05', 'Restarted installer, right code: it goes on and creates a new active super admin (recovery)', $ru > 0 && $sa() === 1 && q1('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=?', [$ru]) === 'super-admin', implode('>', $steps) . ' ' . short(text_of($h), 200));
check('I05b', 'Recovery keeps the existing data: owner, admin, plain user, demo roles still there; no SQL on screen', (int) q1('SELECT COUNT(*) FROM users WHERE id IN (?,?,?)', [$OWNER, $ADMIN, $PLAIN]) === 3 && role_id('demo-author') > 0 && ! leaks_sql($h));
check('I05c', 'After the recovery: lock file written, setup code deleted', is_file($lock) && ! file_exists($codef));
$cr = c8('rescue');
$li = login8($cr, 'rescue8b@v8b.test', 'Lantern-Quay-4417'); $lis = "login {$cr->status} {$cr->location}"; G($cr, 'rolewarden/roles');
check('I05d', 'The rescue super admin signs in and opens the panel', $li && $cr->status === 200, "$lis; roles page {$cr->status}");
$h = G(c8('i6'), 'rolewarden/install', 'I06');
check('I06', 'After the recovery the installer refuses again', $already($h), short(text_of($h), 200));

// I07 lock present, no active super admin: refuses (the lock alone is enough)
q('UPDATE users SET active=0 WHERE id=?', [$ru]);
$h = G(c8('i7'), 'rolewarden/install', 'I07');
check('I07', 'Lock file present and no active super admin: installer refuses, no code', $already($h) && ! file_exists($codef), short(text_of($h), 200));
// I08 no lock, no super admin assigned at all
rename($lock, "$WORK/inst.lock.aside");
q("DELETE ur FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE r.is_super_admin=1");
$h = G(c8('i8'), 'rolewarden/install', 'I08');
check('I08', 'No lock and no user holding a super admin role: the installer starts at the code step', istep($h) === 'code' && file_exists($codef), istep($h) . ' ' . short(text_of($h), 200));
@unlink($codef);
// I09 no lock, super admin active through a second super-admin role (is_super_admin flag on a custom role)
q("INSERT INTO acl_roles (name, slug, description, is_system, is_super_admin, created_at, updated_at) VALUES ('Owners 8b','owners-8b','',0,1,NOW(),NOW())");
q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$PLAIN, role_id('owners-8b')]);
$h = G(c8('i9'), 'rolewarden/install', 'I09');
check('I09', 'No lock, an active user on a custom role flagged super admin: installer refuses (the flag counts, not the slug)', $already($h) && ! file_exists($codef), istep($h) . ' ' . short(text_of($h), 200));
@unlink($codef);
server_stop();
