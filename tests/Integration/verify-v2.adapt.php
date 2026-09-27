<?php
// Included by verify-v2.php adapt (Collaudatore ad Hoc, Claude).
// U: the upgrade window (folder replaced, migrations not yet run) and the state after rolling back
//    the V2 migrations with the V2 folder still in place, in development and production.
// R: the V1 default-role behaviour re-checked through the full V2 Settings form, since the V1M1
//    script posts default_role alone.

function set_env(string $e): void
{
    global $APP;
    file_put_contents("$APP/.env", preg_replace('/CI_ENVIRONMENT = \w+/', 'CI_ENVIRONMENT = ' . $e, file_get_contents("$APP/.env")));
    cache_clear();
}

function app_log_lines(): array
{
    global $APP;
    $out = [];
    foreach (glob("$APP/writable/logs/*.log") as $f) {
        foreach (file($f) as $line) {
            if (preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /', $line)) {
                $out[] = trim($line);
            }
        }
        unlink($f);
    }
    return $out;
}

function probe_state(string $id, string $label, Client $anon, Client $adm): void
{
    $anon->get('login');
    $s1 = $anon->status; $l1 = leaks_sql($anon->body);
    $adm->get('rolewarden/users');
    $s2 = $adm->status; $l2 = leaks_sql($adm->body);
    $anon->get('');
    $s3 = $anon->status; $l3 = leaks_sql($anon->body);
    echo "[INFO] $id $label: /login HTTP $s1, panel HTTP $s2, host home HTTP $s3\n";
    check($id, "$label: no SQL error text on screen (login, panel, host home)", ! $l1 && ! $l2 && ! $l3, "login sql=" . json_encode($l1) . " panel sql=" . json_encode($l2) . " home sql=" . json_encode($l3));
    $GLOBALS['ADAPT_STATUS'][$id] = [$s1, $s2, $s3];
    // Re-verification of D1 (37bb47f): the panel is expected to keep working while acl_sessions is missing.
    check($id . 's', "$label: login, signed-in panel and host home all answer 200", [$s1, $s2, $s3] === [200, 200, 200], json_encode([$s1, $s2, $s3]));
}

try {
    foreach (['development', 'production'] as $environment) {
        echo "== U upgrade window, $environment ==\n";
        reset_db(); use_module($MOD_OLD); readme_install();
        $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
        set_env($environment); app_log_lines(); server_start();
        $adm = login2('up-admin-' . $environment, $U['admin2']['email'], $pw);
        $quiet = login2('up-quiet-' . $environment, $U['owner']['email'], $pw);
        page($adm, 'rolewarden/users'); $s1 = $adm->status; page($quiet, 'rolewarden/users'); $s2 = $quiet->status;
        check('U01-' . $environment, 'V1 (35f058f) two admins signed in', $s1 === 200 && $s2 === 200, "$s1 $s2");
        $v1batch = (int) q1('SELECT MAX(batch) FROM migrations');
        server_stop(); use_module($MOD_NEW); server_start(); // a fresh server: php -S keeps a realpath cache through the junction
        probe_state('U02-' . $environment, 'Folder replaced, migrate not yet run', new Client('up-anon-' . $environment), $adm);
        $adm->get('');
        echo '[INFO] U02 signed-in request to the host home page: HTTP ' . $adm->status . ' sql=' . json_encode(leaks_sql($adm->body)) . "\n";
        echo '[INFO] U02 app log: ' . json_encode(array_slice(app_log_lines(), 0, 4)) . "\n";
        spark('migrate', '-n', 'RoleWarden');
        // The quiet admin made no request between the folder swap and the migration.
        $h = page($quiet, 'rolewarden/sessions');
        check('U03-' . $environment, 'After migrate an admin signed in before the upgrade stays signed in and this browser is listed', $quiet->status === 200 && str_contains($h, 'this browser') && sessions($U['owner']['id']) === 1, 'HTTP ' . $quiet->status . ' rows=' . sessions($U['owner']['id']));
        $h = page($adm, 'rolewarden/sessions');
        check('U06-' . $environment, 'After migrate the admin who browsed during the window is still signed in and listed', $adm->status === 200 && str_contains($h, 'this browser') && sessions($U['admin2']['id']) === 1, 'HTTP ' . $adm->status . ' location=' . $adm->location . ' rows=' . sessions($U['admin2']['id']));
        spark('migrate:rollback', '-b', (string) $v1batch, '-f');
        probe_state('U04-' . $environment, 'V2 migrations rolled back, V2 folder still in place', new Client('rb-anon-' . $environment), $quiet);
        echo '[INFO] U04 app log: ' . json_encode(array_slice(app_log_lines(), 0, 4)) . "\n";
        server_stop(); use_module($MOD_OLD); server_start();
        probe_state('U05-' . $environment, 'Rolled back and V1 folder put back', new Client('rb1-anon-' . $environment), $quiet);
        echo '[INFO] U05 app log: ' . json_encode(array_slice(app_log_lines(), 0, 4)) . "\n";
        echo '[INFO] U05 settings rows: ' . json_encode(q('SELECT class, `key`, value FROM settings')) . "\n";
        $files = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$APP/writable/cache", FilesystemIterator::SKIP_DOTS)) as $fi) { $files[] = substr($fi->getPathname(), strlen("$APP/writable/cache")); }
        echo '[INFO] U05 writable/cache files: ' . json_encode($files) . "\n";
        server_stop();
    }

    echo "== R default role through the full V2 form ==\n";
    reset_db(); use_module($MOD_NEW); readme_install();
    $F = make_fixtures(); $U = $F['u']; $pw = $F['pw'];
    set_env('development'); app_log_lines(); server_start();
    $adm = login2('r-admin', $U['admin2']['email'], $pw);
    $f = matching(forms(page($adm, 'rolewarden/settings')), 'Save settings');
    echo '[INFO] settings form fields: ' . json_encode(array_keys($f['fields'])) . "\n";
    submit($adm, $f, ['default_role' => 'v1-limited']);
    action_toast('R01', 'Default role saved through the full V2 form', $adm, 'success');
    check('R02', 'Default role stored and re-read', selected_default(page($adm, 'rolewarden/settings')) === 'v1-limited', (string) selected_default($adm->body));
    $r = register('rreg1', 'reg1@v2.test', $pw); // Shield rejects a hyphen in usernames (first run used r-reg1: script error)
    $rid = q1('SELECT user_id FROM auth_identities WHERE secret=?', ['reg1@v2.test']);
    echo '[INFO] R03 register: HTTP ' . $r->status . ' -> ' . $r->location . ' user id=' . json_encode($rid) . ' shield groups=' . json_encode(q('SELECT `group` FROM auth_groups_users WHERE user_id=?', [$rid])) . ' acl roles=' . json_encode(roles_of('reg1@v2.test')) . "\n";
    if ($rid === null) {
        $rh = follow($r);
        preg_match_all('/<(?:div|p|span|li)[^>]*(?:alert|invalid|error)[^>]*>(.*?)<\/(?:div|p|span|li)>/is', clean_html($rh), $em);
        echo '[INFO] R03 register page after redirect: HTTP ' . $r->status . ' messages=' . json_encode(array_map(fn ($t) => trim(preg_replace('/\s+/', ' ', strip_tags($t))), $em[1])) . "\n";
        $r2 = register('rreg2', 'reg2@v2.test', 'Zq8!' . bin2hex(random_bytes(8)) . 'Kx');
        echo '[INFO] R03 second registration (no hyphen, other password): HTTP ' . $r2->status . ' -> ' . $r2->location . ' created=' . json_encode(user_exists('reg2@v2.test')) . ' roles=' . json_encode(roles_of('reg2@v2.test')) . "\n";
    }
    check('R03', 'User registered through Shield gets exactly the default role', roles_of('reg1@v2.test') === ['v1-limited'], "HTTP {$r->status} roles=" . json_encode(roles_of('reg1@v2.test')));
    panel_create($adm, 'rpanel1', 'panel1@v2.test', $pw);
    check('R04', 'User created from the panel gets exactly the default role', roles_of('panel1@v2.test') === ['v1-limited'], json_encode(roles_of('panel1@v2.test')));
    $prior = stored();
    $f = matching(forms(page($adm, 'rolewarden/settings')), 'Save settings');
    submit($adm, $f, ['default_role' => 'super-admin']);
    $st = $adm->status; $h = follow($adm);
    echo '[INFO] super-admin as default: HTTP ' . $st . ' toasts=' . json_encode(static_toasts($h)) . ' inline=' . json_encode(field_error($h, 'default_role')) . "\n";
    check('R05', 'Super admin role refused as default, value unchanged, message shown (inline or toast)', stored() === $prior && (field_error($h, 'default_role') || static_toasts($h) !== []));
    // The V1M1 script posts default_role alone; record what V2 does with the missing sign-in fields.
    $prior = stored();
    $adm->post('rolewarden/settings', ['default_role' => 'user'], 'rolewarden/settings');
    $st = $adm->status; $h = follow($adm);
    $errs = [];
    foreach (['session_lifetime', 'remember_length', 'lock_attempts', 'lock_minutes', 'sign_in_rate'] as $n) {
        if (field_error($h, $n)) { $errs[] = $n; }
    }
    echo '[INFO] partial POST (default_role only): HTTP ' . $st . ' stored changed=' . json_encode(stored() !== $prior) . ' toasts=' . json_encode(static_toasts($h)) . ' inline errors on=' . json_encode($errs) . "\n";
    check('R06', 'Partial POST without the sign-in fields: nothing written and the refusal is visible', stored() === $prior && ($errs !== [] || static_toasts($h) !== []), 'errors=' . json_encode($errs) . ' toasts=' . json_encode(static_toasts($h)));
    server_stop();
    $bad = app_log_lines();
    $bad = array_values(array_filter($bad, fn ($l) => ! str_contains($l, 'SecurityException') && ! str_contains($l, 'The action you requested is not allowed')));
    check('R07', 'R part: app log clean at max level', $bad === [], implode("\n", $bad));
} finally {
    server_stop();
}
$pass = count(array_filter($GLOBALS['results'], fn ($r) => $r[2]));
echo 'ADAPT RESULT ' . count($GLOBALS['results']) . ' checks ' . $pass . ' PASS ' . (count($GLOBALS['results']) - $pass) . " FAIL\n";
