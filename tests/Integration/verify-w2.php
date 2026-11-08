<?php
/**
 * W2 black-box acceptance driver for /rolewarden/setup.
 *
 * Usage: php verify-w2.php <scenario> <baseUrl> <hostRoot>
 * Needs env W2_DBPW (root password of the rolewarden-db container, never written to disk) and
 * HOSTPW (password of the seeded host users). Talks to the database only through
 * `docker exec rolewarden-db mariadb ... rolewarden_test`.
 */
require __DIR__ . '/verify-w2-lib.php';

[$self, $scenario, $base, $root] = $argv + [null, null, null, null];
$P    = '/index.php/rolewarden/setup';
$log  = [];
$fail = 0;

function out(string $s): void
{
    global $log;
    $log[] = $s;
    echo $s, "\n";
}
function check(string $id, bool $ok, string $evidence): void
{
    global $fail;
    if (! $ok) {
        $fail++;
    }
    out(sprintf('[%s] %s | %s', $ok ? 'PASS' : 'FAIL', $id, $evidence));
}
function sql(string $q): string
{
    $cmd = 'docker exec -e MYSQL_PWD=' . escapeshellarg(getenv('W2_DBPW')) . ' rolewarden-db mariadb -uroot -N -B rolewarden_test -e ' . escapeshellarg($q) . ' 2>&1';

    return trim((string) shell_exec($cmd));
}
function tables(): string
{
    return sql("select group_concat(table_name order by table_name) from information_schema.tables where table_schema='rolewarden_test'");
}
function hashes(string $root): array
{
    $h  = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/app', FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $h[str_replace('\\', '/', substr($f->getPathname(), strlen($root)))] = hash_file('sha256', $f->getPathname());
    }
    foreach (glob($root . '/public/*') as $f) {
        if (is_file($f)) {
            $h['/public/' . basename($f)] = hash_file('sha256', $f);
        }
    }
    ksort($h);

    return $h;
}
function mainForm(string $html): ?array
{
    foreach (W2Client::forms($html) as $f) {
        if (($f['fields']['step'] ?? 'locale') !== 'locale') {
            return $f;
        }
    }

    return null;
}
function curStep(W2Client $c): array
{
    global $P;
    $r = $c->get($P);
    $f = mainForm($r['body']);

    return [$r, $f, $f['fields']['step'] ?? null];
}
/** GET the page, assert we are on $step, submit its main form merged with $over. */
function answer(W2Client $c, string $step, array $over = []): array
{
    global $P;
    [$r, $f, $s] = curStep($c);
    if ($s !== $step) {
        out("  !! expected step $step, found " . var_export($s, true) . ' : ' . substr(W2Client::text($r['body']), 0, 300));

        return ['code' => 0, 'body' => '', 'step' => $s, 'aborted' => true];
    }
    $data = array_merge($f['fields'], $over);
    foreach ($data as $k => $v) {
        if ($v === null) {
            unset($data[$k]);
        }
    }
    $r         = $c->req('POST', $P, $data);
    $nf        = mainForm($r['body']);
    $r['step'] = $nf['fields']['step'] ?? null;
    $r['form'] = $nf;
    if (w2_sql_leak($r['body'])) {
        check("no-sql-on-screen@$step", false, 'SQL-like text in response');
    }

    return $r;
}
function txt(array $r, int $n = 400): string
{
    return substr(W2Client::text($r['body']), 0, $n);
}
function manifest(string $root): array
{
    $f = $root . '/writable/rolewarden/install-manifest.json';

    return is_file($f) ? (json_decode((string) file_get_contents($f), true) ?: []) : [];
}
function writableRw(string $root): array
{
    return is_dir($root . '/writable/rolewarden') ? array_values(array_diff(scandir($root . '/writable/rolewarden'), ['.', '..'])) : [];
}
function enterCode(W2Client $c, string $root): array
{
    curStep($c);

    return answer($c, 'code', ['code' => trim((string) file_get_contents($root . '/writable/rolewarden/install-code.txt'))]);
}
function probe(string $base, string $user, string $pw): array
{
    $c = new W2Client($base, tempnam(sys_get_temp_dir(), 'w2p'));
    $c->login($user, $pw);
    $p = json_decode($c->get('/index.php/probe')['body'], true) ?: [];
    $p['_panel'] = $c->get('/index.php/rolewarden')['code'];

    return $p;
}

$hpw = getenv('HOSTPW');
out("=== scenario $scenario @ $base (" . date('c') . ')');

// ---------------------------------------------------------------- common: questions write nothing
function questionsNoWrite(string $root, callable $flow): void
{
    $t0 = tables();
    $h0 = hashes($root);
    $flow();
    $t1 = tables();
    $h1 = hashes($root);
    $changed = array_keys(array_diff_assoc($h1, $h0)) + [];
    $changed = array_merge($changed, array_keys(array_diff_key($h0, $h1)));
    check('no-writes:tables', $t0 === $t1, 'tables before==after: ' . ($t0 === $t1 ? 'yes' : "no ($t0 -> $t1)"));
    check('no-writes:host-files', $changed === [], 'changed host files: ' . json_encode($changed));
    $rw = writableRw($root);
    check('no-writes:writable-only-manifest', array_diff($rw, ['install-manifest.json', 'install-code.txt']) === [], 'writable/rolewarden: ' . json_encode($rw));
    $s = sql("select count(*) from information_schema.tables where table_schema='rolewarden_test' and table_name='settings'") === '1'
        ? sql("select count(*) from settings where class like '%RoleWarden%'") : '0';
    check('no-writes:settings', $s === '0', "RoleWarden settings rows: $s");
}

switch ($scenario) {
    case 'adapt':
        // hostCustom fresh: adapt, panelRole=admin, existing super admin alice, visibility all, Italian browser.
        $c  = new W2Client($base, tempnam(sys_get_temp_dir(), 'w2'), 'it-IT,it;q=0.9,en;q=0.5');
        $g  = $c->get($P);
        check('focus5:guest-redirected-to-host-login', $g['code'] === 302 && str_contains($g['location'], '/login'), "GET $P as guest -> {$g['code']} {$g['location']}");
        $c->login('alice', $hpw);
        [$r] = curStep($c);
        check('lang:initial-from-browser-it', str_contains($r['body'], 'Codice di configurazione') || str_contains(W2Client::text($r['body']), 'Configura RoleWarden'), 'Accept-Language it -> ' . txt($r, 160));
        $f = W2Client::forms($r['body']);
        $loc = null;
        foreach ($f as $x) {
            if (($x['fields']['step'] ?? '') === 'locale') {
                $loc = $x;
            }
        }
        $r2 = $c->req('POST', $P, array_merge($loc['fields'], ['locale' => 'en']));
        if (in_array($r2['code'], [302, 303], true)) {
            $r2 = $c->get($P);
        }
        check('lang:selector-switches-to-en', str_contains(W2Client::text($r2['body']), 'Setup code'), 'POST locale=en -> ' . txt($r2, 160));
        check('focus5:code-page-explains-signin-en', str_contains(W2Client::text($r2['body']), 'sign in with any existing account'), 'code page text contains the sign-in hint');
        // a direct jump to a later step without code
        $r3 = $c->req('POST', $P, W2Client::csrf($r2['body']) + ['step' => 'roles', 'mode' => 'adapt', 'panelRole' => 'admin']);
        $st = mainForm($r3['body'])['fields']['step'] ?? null;
        check('code:skip-ahead-refused', $st === 'code', "POST step=roles before code -> page step=$st");

        questionsNoWrite($root, function () use ($c, $root) {
            $r = enterCode($c, $root);
            check('code:accepted', $r['step'] === 'requirements', 'right code -> ' . $r['step']);
            $r = answer($c, 'requirements');
            check('filters:preview-shown', str_contains($r['body'], 'rolewarden/setup*') && $r['step'] === 'filters', txt($r, 300));
            $r = answer($c, 'filters');
            check('roles:adapt-suggested-for-custom-groups', ($r['form']['fields']['mode'] ?? '') === 'adapt' && str_contains(W2Client::text($r['body']), 'recommended'), 'checked mode=' . ($r['form']['fields']['mode'] ?? '?'));
            $r = answer($c, 'roles', ['mode' => 'adapt', 'panelRole' => 'admin']);
            check('adapt:no-collision-step', $r['step'] === 'newUsers', 'after roles(adapt) -> ' . $r['step']);
            // Back keeps choices
            $b = answer($c, 'newUsers', ['back' => '1']);
            check('back:keeps-choices', ($b['form']['fields']['mode'] ?? '') === 'adapt' && ($b['form']['fields']['panelRole'] ?? '') === 'admin', 'back -> step=' . $b['step'] . ' mode=' . ($b['form']['fields']['mode'] ?? '?') . ' panelRole=' . ($b['form']['fields']['panelRole'] ?? '?'));
            $r = answer($c, 'roles', ['mode' => 'adapt', 'panelRole' => 'admin']);
            check('newUsers:prefilled-defaultGroup', ($r['form']['fields']['defaultRole'] ?? '') === 'cliente', 'defaultRole=' . ($r['form']['fields']['defaultRole'] ?? '?') . ' options=' . json_encode($r['form']['selects']['defaultRole'] ?? []));
            $r = answer($c, 'newUsers', ['defaultRole' => 'cliente']);
            $ids = array_map(fn ($o) => (int) $o, $r['form']['selects']['id'] ?? []);
            check('superAdmin:only-active-listed', ! array_intersect($ids, [5, 6, 7]) && in_array(1, $ids, true), 'listed ids=' . json_encode($ids) . ' (5 inactive, 6 banned, 7 deleted)');
            foreach (['5', '6', '7', '999', '0', '-1'] as $bad) {
                $t = answer($c, 'superAdmin', ['kind' => 'existing', 'id' => $bad]);
                check("focus4:tampered-id-$bad-rejected", $t['step'] === 'superAdmin', "POST id=$bad -> step=" . $t['step']);
            }
            $r = answer($c, 'superAdmin', ['kind' => 'existing', 'id' => '1']);
            check('visibility:shown', $r['step'] === 'visibility', 'id=1 -> ' . $r['step']);
            $r = answer($c, 'visibility', ['visibility' => 'all']);
            check('confirm:shown-with-previews', $r['step'] === 'confirm' && str_contains($r['body'], 'RouteRegistrar::register') && str_contains($r['body'], 'RoleWarden\\Views\\auth\\login'), txt($r, 200));
        });
        $m = manifest($root);
        check('manifest:choices-saved', ($m['choices']['mode'] ?? '') === 'adapt' && ($m['done'] ?? ['x']) === [], json_encode($m['choices'] ?? null));

        $r = answer($c, 'confirm');
        $m = manifest($root);
        check('apply:completed', ! empty($m['completed_at']) && ($m['done'] ?? []) === ['migrations', 'roles', 'superAdmin', 'filters', 'wiring'], 'done=' . json_encode($m['done'] ?? null) . ' completed_at=' . ($m['completed_at'] ?? 'null') . ' page=' . txt($r, 250));
        check('apply:edits-logged', count($m['edits'] ?? []) === 3 && ! array_diff(['path', 'before', 'after', 'at'], array_keys($m['edits'][0] ?? [])), 'edits=' . json_encode(array_map(fn ($e) => basename($e['path']) . '@' . $e['at'], $m['edits'] ?? [])));
        check('apply:order-filters-routes-auth', array_map(fn ($e) => basename(str_replace('\\', '/', $e['path'])), $m['edits'] ?? []) === ['Filters.php', 'Routes.php', 'Auth.php'], 'edit order');
        foreach (['Filters', 'Routes', 'Auth'] as $f) {
            check("backup:$f", is_file("$root/app/Config/$f.php.rolewarden-bak"), "$f.php.rolewarden-bak exists");
        }
        check('code:deleted', ! is_file("$root/writable/rolewarden/install-code.txt"), 'install-code.txt removed');
        $auth = (string) file_get_contents("$root/app/Config/Auth.php");
        check('visibility-all:login-view-and-redirect', str_contains($auth, 'RoleWarden\\Views\\auth\\login') && (bool) preg_match("/'login'\\s*=>\\s*'\\/?rolewarden'/", $auth), 'Auth.php views/redirects changed');
        out('DB roles: ' . sql("select group_concat(concat(r.slug,'[',(select count(*) from acl_role_permissions rp where rp.role_id=r.id),'p:',ifnull((select group_concat(user_id order by user_id) from acl_user_roles u where u.role_id=r.id),''),']') order by r.slug) from acl_roles r"));
        check('adapt:no-system-user-role', sql("select count(*) from acl_roles where slug='user'") === '1', "'user' exists only as host group (count=" . sql("select count(*) from acl_roles where slug='user'") . ')');
        check('adapt:panelRole-setting', str_contains(sql("select value from settings where `key`='panelRole'"), 'admin'), 'panelRole=' . sql("select value from settings where `key`='panelRole'"));
        check('adapt:admin-has-host-and-panel-perms', (int) sql("select count(*) from acl_role_permissions rp join acl_roles r on r.id=rp.role_id where r.slug='admin'") >= 33, 'admin perms=' . sql("select count(*) from acl_role_permissions rp join acl_roles r on r.id=rp.role_id where r.slug='admin'"));
        check('superAdmin:alice-assigned', sql("select count(*) from acl_user_roles ur join acl_roles r on r.id=ur.role_id where r.slug='super-admin' and ur.user_id=1") === '1', 'alice in super-admin');
        $c2 = new W2Client($base, tempnam(sys_get_temp_dir(), 'w2'));
        $x  = $c2->get($P);
        check('done:wizard-403', $x['code'] === 403, "GET $P -> {$x['code']}");
        $pa = probe($base, 'alice', $hpw);
        check('done:panel-role-opens-panel', ($pa['_panel'] ?? 0) === 200 && ($pa['provider'] ?? '') === 'RoleWarden\\Models\\UserModel', json_encode($pa));
        $pb = probe($base, 'bob', $hpw);
        check('done:host-can-through-rolewarden', ($pb['tickets.close'] ?? null) === true && ($pb['users.view'] ?? null) === false && str_starts_with($pb['class'] ?? '', 'RoleWarden'), json_encode($pb));
        $l = (new W2Client($base, tempnam(sys_get_temp_dir(), 'w2')))->get('/index.php/login');
        check('done:login-page-200', $l['code'] === 200, 'GET /login -> ' . $l['code']);
        break;

    case 'stock':
        // hostStock: Shield's stock groups only, no global filter. Focus 1 + rename/merge collisions.
        $c = new W2Client($base, tempnam(sys_get_temp_dir(), 'w2'), 'en-US,en;q=0.8');
        [$r] = curStep($c);
        check('stock:wizard-open-for-guest-without-session-filter', $r['code'] === 200 && mainForm($r['body'])['fields']['step'] === 'code', "GET -> {$r['code']}");
        questionsNoWrite($root, function () use ($c, $root) {
            $r = enterCode($c, $root);
            $r = answer($c, 'requirements');
            check('stock:filters-none', str_contains(W2Client::text($r['body']), 'No change needed'), txt($r, 300));
            $r = answer($c, 'filters');
            check('focus1:import-suggested', ($r['form']['fields']['mode'] ?? '') === 'import', 'checked mode=' . ($r['form']['fields']['mode'] ?? '(none)') . ' | ' . substr(W2Client::text($r['body']), 0, 900));
            $r = answer($c, 'roles', ['mode' => 'import', 'panelRole' => null]);
            check('focus1:not-blocked', $r['step'] === 'collisions', 'after import -> ' . $r['step'] . ' radios=' . json_encode(array_keys($r['form']['radios'] ?? [])));
            $r = answer($c, 'collisions', ['collisions[admin][action]' => 'rename', 'collisions[admin][newSlug]' => 'app-admin', 'collisions[user][action]' => 'merge']);
            check('collisions:accepted', $r['step'] === 'newUsers', '-> ' . $r['step'] . ' options=' . json_encode($r['form']['selects']['defaultRole'] ?? []));
            $r = answer($c, 'newUsers', ['defaultRole' => 'user']);
            $r = answer($c, 'superAdmin', ['kind' => 'existing', 'id' => '1']);
            $r = answer($c, 'visibility', ['visibility' => 'admins']);
            check('stock:confirm', $r['step'] === 'confirm', txt($r, 600));
        });
        $r = answer($c, 'confirm');
        $m = manifest($root);
        check('stock:apply-completed', ! empty($m['completed_at']), 'done=' . json_encode($m['done'] ?? null) . ' ' . txt($r, 300));
        check('stock:no-filters-edit', count($m['edits'] ?? []) === 2, 'edits=' . json_encode(array_map(fn ($e) => basename(str_replace('\\', '/', $e['path'])), $m['edits'] ?? [])));
        out('DB roles: ' . sql("select group_concat(concat(r.slug,'[',(select count(*) from acl_role_permissions rp where rp.role_id=r.id),'p:',ifnull((select group_concat(user_id order by user_id) from acl_user_roles u where u.role_id=r.id),''),']') order by r.slug) from acl_roles r"));
        check('rename:app-admin-has-host-perms-and-members', sql("select concat((select count(*) from acl_role_permissions rp where rp.role_id=r.id),'/',(select group_concat(user_id order by user_id) from acl_user_roles u where u.role_id=r.id)) from acl_roles r where slug='app-admin'") !== '', 'app-admin=' . sql("select concat((select count(*) from acl_role_permissions rp where rp.role_id=r.id),'/',ifnull((select group_concat(user_id order by user_id) from acl_user_roles u where u.role_id=r.id),'')) from acl_roles r where slug='app-admin'"));
        check('rename:system-admin-untouched-members', sql("select count(*) from acl_user_roles ur join acl_roles r on r.id=ur.role_id where r.slug='admin'") === '0', 'system admin members=' . sql("select count(*) from acl_user_roles ur join acl_roles r on r.id=ur.role_id where r.slug='admin'"));
        check('merge:user-members', sql("select group_concat(user_id order by user_id) from acl_user_roles ur join acl_roles r on r.id=ur.role_id where r.slug='user'") !== 'NULL', 'user members=' . sql("select group_concat(user_id order by user_id) from acl_user_roles ur join acl_roles r on r.id=ur.role_id where r.slug='user'"));
        $x = (new W2Client($base, tempnam(sys_get_temp_dir(), 'w2')))->get($P);
        check('stock:done-403', $x['code'] === 403, "-> {$x['code']}");
        $pa = probe($base, 'alice', $hpw);
        check('stock:super-admin-opens-panel', ($pa['_panel'] ?? 0) === 200, json_encode($pa));
        break;

    case 'odd':
        // hostOdd: custom user provider, commented RouteRegistrar line, except as string.
        $c = new W2Client($base, tempnam(sys_get_temp_dir(), 'w2'));
        $c->login('alice', $hpw);
        questionsNoWrite($root, function () use ($c, $root) {
            $r = enterCode($c, $root);
            $r = answer($c, 'requirements');
            out('  filters page: ' . txt($r, 900));
            $r = answer($c, 'filters');
            $r = answer($c, 'roles', ['mode' => 'adapt', 'panelRole' => 'admin']);
            $r = answer($c, 'newUsers', []);
            $r = answer($c, 'superAdmin', ['kind' => 'existing', 'id' => '1']);
            $r = answer($c, 'visibility', ['visibility' => 'admins']);
            out('  confirm page: ' . txt($r, 4000));
        });
        $r = answer($c, 'confirm');
        $m = manifest($root);
        out('  after apply: ' . txt($r, 3000));
        out('  done=' . json_encode($m['done'] ?? null) . ' edits=' . json_encode(array_map(fn ($e) => basename(str_replace('\\', '/', $e['path'])), $m['edits'] ?? [])));
        file_put_contents(__DIR__ . '/verify-w2-odd-stop.html', $r['body']);
        break;

    case 'recheck':
        // 610bca9: D1 + D2 + import/collisions + resume, on hostCustom with global session filter.
        $c = new W2Client($base, tempnam(sys_get_temp_dir(), 'w2'));
        $g = $c->get($P);
        check('focus5:guest-redirected', $g['code'] === 302 && str_contains($g['location'], '/login'), "guest -> {$g['code']} {$g['location']}");
        $c->login('alice', $hpw);
        [$r] = curStep($c);
        check('code:shown-when-signed-in', (mainForm($r['body'])['fields']['step'] ?? '') === 'code', 'signed-in alice sees step code');
        $n = $c->req('POST', $P, ['step' => 'code', 'code' => 'x']);
        check('csrf:post-without-token-403', $n['code'] === 403, "POST without token -> {$n['code']}");
        $w = answer($c, 'code', ['code' => 'wrongcode']);
        check('code:wrong-rejected', $w['step'] === 'code' && str_contains(W2Client::text($w['body']), 'not the setup code'), 'wrong code -> ' . $w['step']);
        $r3 = $c->req('POST', $P, W2Client::csrf($w['body']) + ['step' => 'roles', 'mode' => 'import']);
        check('code:skip-ahead-refused', (mainForm($r3['body'])['fields']['step'] ?? null) === 'code', 'POST step=roles before code -> ' . (mainForm($r3['body'])['fields']['step'] ?? '?'));
        questionsNoWrite($root, function () use ($c, $root) {
            $r = enterCode($c, $root);
            $r = answer($c, 'requirements');
            $r = answer($c, 'filters');
            $r = answer($c, 'roles', ['mode' => 'import', 'panelRole' => null]);
            check('import:collisions-step', $r['step'] === 'collisions' && str_contains(W2Client::text($r['body']), '2 users lose 8 permissions'), '-> ' . $r['step'] . ' (skip warning "2 users lose 8 permissions" present)');
            $r = answer($c, 'collisions', ['collisions[admin][action]' => 'merge', 'collisions[user][action]' => 'skip']);
            $r = answer($c, 'newUsers', ['defaultRole' => 'cliente']);
            $pw = 'Zq9!long-Passphrase';
            foreach ([['alice', 'zz@host.test', 'D1:duplicate-username-rejected'], ['newroot', 'alice@host.test', 'D1:duplicate-email-rejected'], ['ALICE', 'zz2@host.test', 'D1:duplicate-username-case-variant'], ['bob', 'BOB@HOST.TEST', 'D1:duplicate-both-email-uppercase']] as [$u, $e, $id]) {
                $t   = answer($c, 'superAdmin', ['kind' => 'new', 'id' => null, 'username' => $u, 'email' => $e, 'password' => $pw, 'password_confirm' => $pw]);
                $msg = W2Client::text($t['body']);
                check($id, $t['step'] === 'superAdmin', "new user $u/$e -> step=" . $t['step'] . ' | ' . substr($msg, max(0, (int) strpos($msg, 'A new user')), 260));
                if ($t['step'] !== 'superAdmin' && $t['step'] !== null) {
                    answer($c, (string) $t['step'], ['back' => '1']);
                }
            }
            foreach (['5', '6', '7', '999'] as $bad) {
                $t = answer($c, 'superAdmin', ['kind' => 'existing', 'id' => $bad]);
                check("focus4:tampered-id-$bad", $t['step'] === 'superAdmin', '-> ' . $t['step']);
            }
            $r = answer($c, 'superAdmin', ['kind' => 'new', 'id' => null, 'username' => 'newroot', 'email' => 'newroot@host.test', 'password' => $pw, 'password_confirm' => $pw]);
            check('superAdmin:valid-new-accepted', $r['step'] === 'visibility', '-> ' . $r['step']);
            $r = answer($c, 'visibility', ['visibility' => 'admins']);
            check('confirm:reached', $r['step'] === 'confirm', txt($r, 160));
        });
        $m = manifest($root);
        check('manifest:no-cleartext-password', ! str_contains((string) file_get_contents("$root/writable/rolewarden/install-manifest.json"), 'Zq9!'), 'superAdmin=' . json_encode(array_diff_key($m['choices']['superAdmin'] ?? [], ['passwordHash' => 1])) . ' hash? ' . (isset($m['choices']['superAdmin']['passwordHash']) ? 'yes' : 'no'));
        // Focus 3 / D2: Filters.php read-only
        $fp = str_replace('/', '\\', "$root/app/Config/Filters.php");
        shell_exec('attrib +R "' . $fp . '"');
        $h0 = hashes($root);
        $r  = answer($c, 'confirm');
        $m  = manifest($root);
        check('resume:stops-at-filters', ($m['done'] ?? []) === ['migrations', 'roles', 'superAdmin'] && ($m['edits'] ?? ['x']) === [], 'done=' . json_encode($m['done'] ?? null) . ' edits=' . count($m['edits'] ?? []) . ' | ' . txt($r, 300));
        check('resume:host-files-untouched', hashes($root) === $h0, 'host file hashes unchanged after failed apply');
        check('resume:superadmin-created-hash-removed', ! isset($m['choices']['superAdmin']['passwordHash']) && isset($m['choices']['superAdmin']['id']), json_encode($m['choices']['superAdmin'] ?? null));
        check('D2:failure-response-no-false-claim', ! str_contains(W2Client::text($r['body']), 'Nothing has been written yet'), 'POST response: ' . txt($r, 600));
        [$g2] = curStep($c);
        $gt   = W2Client::text($g2['body']);
        check('D2:get-after-failure-no-false-claim', ! str_contains($gt, 'Nothing has been written yet'), 'GET: ' . substr($gt, 0, 900));
        check('D2:get-after-failure-lists-done-steps', str_contains($gt, 'Already applied'), 'mentions "Already applied": ' . (str_contains($gt, 'Already applied') ? 'yes' : 'no'));
        $pb = probe($base, 'bob', $hpw);
        check('host-works-mid-install', ($pb['user'] ?? '') === 'bob', json_encode($pb));
        $r = answer($c, 'confirm');
        check('resume:continue-without-fix-stays', (manifest($root)['done'] ?? []) === ['migrations', 'roles', 'superAdmin'], 'done=' . json_encode(manifest($root)['done'] ?? null));
        $back = answer($c, 'confirm', ['back' => '1']);
        out('  back after start -> step=' . var_export($back['step'], true) . ' : ' . txt($back, 300));
        $lockedTxt = W2Client::text($back['body']);
        if ($back['step'] !== 'confirm' && $back['step'] !== null) {
            $lk        = answer($c, (string) $back['step'], ['back' => '1']);
            $lockedTxt = W2Client::text($lk['body']);
        }
        check('locked:answers-after-start', str_contains($lockedTxt, 'can no longer change') || $back['step'] === 'confirm', substr($lockedTxt, 0, 300));
        shell_exec('attrib -R "' . $fp . '"');
        [, , $s] = curStep($c);
        for ($i = 0; $i < 5 && $s !== 'confirm' && $s !== null; $i++) {
            answer($c, (string) $s);
            [, , $s] = curStep($c);
        }
        $r = answer($c, 'confirm');
        $m = manifest($root);
        check('resume:completes', ! empty($m['completed_at']) && ($m['done'] ?? []) === ['migrations', 'roles', 'superAdmin', 'filters', 'wiring'], 'done=' . json_encode($m['done'] ?? null) . ' | ' . txt($r, 300));
        check('resume:edits-only-real', array_map(fn ($e) => basename(str_replace('\\', '/', $e['path'])), $m['edits'] ?? []) === ['Filters.php', 'Routes.php', 'Auth.php'], json_encode(array_map(fn ($e) => basename(str_replace('\\', '/', $e['path'])) . '@' . $e['at'], $m['edits'] ?? [])));
        $dup = sql("select concat_ws('/',(select count(*) from (select 1 from acl_user_roles group by user_id,role_id having count(*)>1) a),(select count(*) from (select 1 from acl_role_permissions group by role_id,permission_id having count(*)>1) b),(select count(*) from (select 1 from acl_roles group by slug having count(*)>1) c))");
        check('resume:no-duplicates', $dup === '0/0/0', "dup user_roles/role_perms/roles = $dup");
        out('DB roles: ' . sql("select group_concat(concat(r.slug,'[',(select count(*) from acl_role_permissions rp where rp.role_id=r.id),'p:',ifnull((select group_concat(user_id order by user_id) from acl_user_roles u where u.role_id=r.id),''),']') order by r.slug) from acl_roles r"));
        $adm = sql("select concat((select count(*) from acl_role_permissions rp where rp.role_id=r.id),':',(select group_concat(user_id order by user_id) from acl_user_roles u where u.role_id=r.id)) from acl_roles r where slug='admin'");
        check('import:merge-admin', $adm === '33:1,8', "admin=$adm");
        $um = sql("select count(*) from acl_user_roles ur join acl_roles r on r.id=ur.role_id where r.slug='user'");
        check('import:skip-user', $um === '0', "user members=$um");
        check('superAdmin:newroot', sql("select count(*) from acl_user_roles ur join acl_roles r on r.id=ur.role_id join users u on u.id=ur.user_id where r.slug='super-admin' and u.username='newroot'") === '1' && sql("select count(*) from users where username='newroot'") === '1', 'newroot is the single new user and super-admin');
        $x = (new W2Client($base, tempnam(sys_get_temp_dir(), 'w2')))->get($P);
        check('done:403', $x['code'] === 403, "-> {$x['code']}");
        $pr = probe($base, 'newroot', 'Zq9!long-Passphrase');
        check('done:new-super-admin-opens-panel', ($pr['_panel'] ?? 0) === 200, json_encode($pr));
        $pi = probe($base, 'ivan', $hpw);
        check('done:can-through-rolewarden', ($pi['tickets.view'] ?? null) === false && str_starts_with($pi['class'] ?? '', 'RoleWarden'), json_encode($pi));
        break;

    case 'odd-continue':
        $c = new W2Client($base, tempnam(sys_get_temp_dir(), 'w2'));
        $c->login('alice', $hpw);
        [$r, $f, $s] = curStep($c);
        out('  current step=' . var_export($s, true) . ' : ' . txt($r, 300));
        if ($s === 'code') {
            $r = enterCode($c, $root);
            out('  after code -> ' . $r['step'] . ' : ' . txt($r, 300));
        }
        $r = answer($c, 'confirm');
        $m = manifest($root);
        out('  after continue: ' . txt($r, 1500));
        out('  done=' . json_encode($m['done'] ?? null) . ' completed_at=' . ($m['completed_at'] ?? 'null') . ' edits=' . json_encode(array_map(fn ($e) => basename(str_replace('\\', '/', $e['path'])), $m['edits'] ?? [])));
        break;
}
out("=== scenario $scenario: " . ($fail === 0 ? 'ALL CHECKS PASS' : "$fail CHECK(S) FAILED"));
file_put_contents(__DIR__ . "/verify-w2-$scenario.out.txt", implode("\n", $log) . "\n");
