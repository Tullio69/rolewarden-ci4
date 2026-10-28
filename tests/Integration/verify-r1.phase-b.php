<?php
/**
 * R1 phase B (Collaudatore ad Hoc), loaded by verify-r1.s3.php right before phase L, so that the sign-in limit it sets
 * does not touch the other phases. Black-box: the three behaviours the author listed as touched after 4be1a8d
 * (RoleModel::update, UsersController, SignInThrottle), read from the outside only:
 *   B1x  editing a role so that the hierarchy would loop: the protection's message, no error, nothing saved
 *   B2x  editing the last super admin role so that it stops being super admin, with an active super admin: same
 *   B3x  users list filtered by role, also with the filter left empty (and odd values)
 *   B4x  rw-signin on the login route with the thresholds chosen in the Settings screen: lock per email after N failures
 *        for the chosen minutes (registered or not), limit per address per minute, normal sign-in still working
 * Expected texts: the English language file of the module (protection.*, signIn.*), the SPEC (V2 decisions: throttling).
 */
if (in_array('B', $PHASES, true)) {
    echo "\n== B: role protection messages, users list by role, rw-signin with the Settings thresholds\n";
    // the owner's panel may be in Italian after phase T: either language file's text counts
    $MSG_CYCLE = ['A role cannot inherit from itself or from one of its descendants.', 'Un ruolo non può ereditare da sé stesso né da un suo discendente.'];
    $MSG_SUPER = ['This would leave the system without an active super admin.', 'Il sistema resterebbe senza un super admin attivo.'];
    $has = fn (string $page, array $alts): bool => (bool) array_filter($alts, fn ($m) => str_contains($page, $m));
    $MSG_RATE = 'Too many sign-in attempts from this address. Wait a minute and try again.';
    $MSG_LOCK_RE = '/Too many failed sign-ins for this email\. Try again in (\d+) minutes?\./';
    $raw = fn (string $h): string => html_entity_decode(stripslashes($h), ENT_QUOTES | ENT_HTML5);
    $clean = fn (Client $c): bool => $c->status < 500 && ! leaks_sql($c->body) && ! phperr(text_of($c->body)) && ! preg_match('/ProtectionException|Exception|Stack trace|RoleModel/', text_of($c->body));
    $own = c('B-owner');
    $lok = login2($own, OWNER, OPW);
    check('B00', 'The super admin signs in for phase B (redirect inside, dashboard by default)', $lok && inside(resolve($own->url, $own->location)), "{$own->status} {$own->location}");

    /* ---- B1: hierarchy cycle */
    $mk = function (string $name, ?int $parent) use ($own): int {
        $h = G($own, 'rolewarden/roles/create', "B create $name");
        $f = form_with($h, 'name');
        $v = array_replace($f['fields'] ?? [], ['name' => $name, 'description' => 'R1 phase B']);
        if (array_key_exists('slug', $f['types'] ?? [])) $v['slug'] = strtolower(str_replace(' ', '-', $name));
        $pk = null; foreach ($f['types'] ?? [] as $k => $t) if (preg_match('/parent/i', $k)) $pk = $k;
        if ($pk !== null) $v[$pk] = $parent === null ? '' : (string) $parent;
        foreach ($f['types'] ?? [] as $k => $t) if ($t === 'checkbox' && preg_match('/super/i', $k)) unset($v[$k]);
        P($own, resolve($own->url, $f['action'] ?? ''), $v, "B create post $name");
        info("B create $name: form " . json_encode($f['types'] ?? null) . " action " . json_encode($f['action'] ?? null) . " -> {$own->status} {$own->location} " . short(text_of($own->body), 200));
        return (int) q1('SELECT id FROM acl_roles WHERE name=? AND deleted_at IS NULL', [$name]);
    };
    $BP = $mk('R1 Parent', null);
    $BC = $mk('R1 Child', $BP);
    $BG = $mk('R1 Grandchild', $BC);
    $chain = q('SELECT id, parent_id FROM acl_roles WHERE id IN (?,?,?) ORDER BY id', [$BP, $BC, $BG]);
    info('B roles: ' . json_encode($chain));
    check('B10', 'Setup: three roles in a chain Parent <- Child <- Grandchild created from the panel', $BP && $BC && $BG && (int) q1('SELECT parent_id FROM acl_roles WHERE id=?', [$BC]) === $BP && (int) q1('SELECT parent_id FROM acl_roles WHERE id=?', [$BG]) === $BC, json_encode($chain));
    $edit = function (int $rid, array $over, array $drop, string $tag) use ($own, $raw): array {
        $h = G($own, "rolewarden/roles/$rid/edit", "B edit form $tag");
        $f = form_with($h, 'name');
        if (! $f) return ['form' => false, 'status' => $own->status, 'page' => '', 'post' => null];
        $v = $f['fields'];
        foreach ($over as $re => $val) foreach ($f['types'] as $k => $t) if (preg_match($re, $k)) $v[$k] = $val;
        foreach ($drop as $re) foreach (array_keys($v) as $k) if (preg_match($re, $k)) unset($v[$k]);
        P($own, resolve($own->url, $f['action']), $v, "B edit post $tag");
        $post = ['status' => $own->status, 'location' => $own->location, 'body' => $own->body];
        $page = $own->body;
        $clean1 = $own->status < 500 && ! leaks_sql($own->body) && ! phperr(text_of($own->body));
        if (in_array($own->status, [301, 302, 303], true)) { $page = follow($own, "B edit after $tag"); }
        return ['form' => true, 'types' => $f['types'], 'fields' => $f['fields'], 'status' => $own->status, 'post' => $post, 'page' => $raw($page), 'clean' => $clean1 && $own->status < 500 && ! leaks_sql($own->body) && ! phperr(text_of($own->body)) && ! preg_match('/ProtectionException|Stack trace|RoleModel/', text_of($own->body))];
    };
    $r = $edit($BP, ['/parent/i' => (string) $BG], [], 'Parent under its grandchild');
    $pk = $r['form'] ? (array_values(array_filter(array_keys($r['types']), fn ($k) => preg_match('/parent/i', $k)))[0] ?? null) : null;
    info('B role edit form fields: ' . json_encode($r['types'] ?? []) . '; answer to the POST ' . ($r['post']['status'] ?? '?') . ' ' . ($r['post']['location'] ?? ''));
    check('B11', 'Edit: Parent made a child of its own grandchild -> the protection\'s message on the page, no error page', $r['form'] && $pk !== null && $r['clean'] && $has($r['page'], $MSG_CYCLE), 'status ' . ($r['status'] ?? '') . ' ' . short(text_of($r['page']), 300));
    check('B12', 'Nothing saved: Parent still without a parent', q1('SELECT parent_id FROM acl_roles WHERE id=?', [$BP]) === null, json_encode(q('SELECT id, parent_id FROM acl_roles WHERE id=?', [$BP])));
    $r2 = $edit($BC, ['/parent/i' => (string) $BC], [], 'Child under itself');
    check('B13', 'Edit: a role made its own parent -> the same message, no error page, nothing saved', $r2['form'] && $r2['clean'] && $has($r2['page'], $MSG_CYCLE) && (int) q1('SELECT parent_id FROM acl_roles WHERE id=?', [$BC]) === $BP, 'status ' . ($r2['status'] ?? '') . ' ' . short(text_of($r2['page']), 300));
    $r3 = $edit($BG, ['/parent/i' => '', '/^name$/' => 'R1 Grandchild moved'], [], 'Grandchild to top level');
    check('B14', 'A valid edit still saves (Grandchild moved to the top level and renamed): the protection does not refuse everything', $r3['form'] && $r3['clean'] && q1('SELECT parent_id FROM acl_roles WHERE id=?', [$BG]) === null && q1('SELECT name FROM acl_roles WHERE id=?', [$BG]) === 'R1 Grandchild moved', 'status ' . ($r3['status'] ?? '') . ' ' . json_encode(q('SELECT id, name, parent_id FROM acl_roles WHERE id=?', [$BG])));

    /* ---- B2: last super admin role */
    $supers = q('SELECT id, slug, is_system FROM acl_roles WHERE is_super_admin=1 AND deleted_at IS NULL');
    $activeSupers = (int) q1('SELECT COUNT(*) FROM users u JOIN acl_user_roles ur ON ur.user_id=u.id JOIN acl_roles r ON r.id=ur.role_id WHERE r.is_super_admin=1 AND u.active=1 AND u.deleted_at IS NULL');
    info('B super admin roles: ' . json_encode($supers) . "; active users holding one: $activeSupers");
    $SA = (int) ($supers[0]['id'] ?? 0);
    $r4 = $edit($SA, [], ['/super/i'], 'super admin role without the flag');
    $sk = $r4['form'] ? array_values(array_filter(array_keys($r4['types']), fn ($k) => preg_match('/super/i', $k))) : [];
    info('B super admin role edit form: ' . json_encode($r4['types'] ?? []) . ' super field(s) ' . json_encode($sk) . '; answer ' . ($r4['post']['status'] ?? '?') . ' ' . ($r4['post']['location'] ?? ''));
    check('B20', 'Precondition: one super admin role, held by an active super admin', count($supers) === 1 && $activeSupers >= 1, json_encode([$supers, $activeSupers]));
    check('B21', 'Edit: the flag taken off the last super admin role -> the protection\'s message on the page, no error page', $r4['form'] && $sk !== [] && $r4['clean'] && $has($r4['page'], $MSG_SUPER), 'status ' . ($r4['status'] ?? '') . ' ' . short(text_of($r4['page']), 300));
    check('B22', 'Nothing saved: the role is still super admin and the owner still reaches a super-admin-only screen', (int) q1('SELECT is_super_admin FROM acl_roles WHERE id=?', [$SA]) === 1 && (function () use ($own) { G($own, 'rolewarden/settings', 'B settings after'); return $own->status === 200; })(), json_encode(q('SELECT id, is_super_admin FROM acl_roles WHERE id=?', [$SA])));
    // the same refusal for a second super admin role made from the panel? Not the last one: a super role can be dropped then
    $r5 = $edit($BP, ['/super/i' => '1'], [], 'Parent made super admin');
    $two = (int) q1('SELECT is_super_admin FROM acl_roles WHERE id=?', [$BP]) === 1;
    $r6 = $two ? $edit($BP, [], ['/super/i'], 'Parent super flag off again') : null;
    check('B23', 'With two super admin roles, taking the flag off one of them is accepted (the guard is about the last one)', $two && $r6 && $r6['clean'] && (int) q1('SELECT is_super_admin FROM acl_roles WHERE id=?', [$BP]) === 0 && ! $has($r6['page'], $MSG_SUPER), json_encode([$two, $r6['status'] ?? null, q('SELECT id, is_super_admin FROM acl_roles WHERE id=?', [$BP])]));

    /* ---- B3: users list filtered by role */
    $hu = G($own, 'rolewarden/users', 'B users');
    $ff = null; $rk = null;
    foreach (forms($hu) as $f) if ($f['method'] === 'get') foreach ($f['types'] as $k => $t) if ($t === 'select' && preg_match('/role/i', $k)) { $ff = $f; $rk = $k; }
    $opts = [];
    if ($ff) foreach (dom($ff['html'])->query('//select[@name="' . $rk . '"]/option') as $op) $opts[$op->getAttribute('value')] = trim($op->textContent);
    info('B users filter form: action ' . json_encode($ff['action'] ?? null) . ' fields ' . json_encode($ff['fields'] ?? null) . ' role options ' . json_encode($opts));
    $listed = function (string $h): array {
        $xp = dom($h); $ids = [];
        foreach ($xp->query('//main//a/@href|//table//a/@href') as $a) if (preg_match('#/rolewarden/users/(\d+)(?:$|[?\#])#', $a->nodeValue, $m)) $ids[(int) $m[1]] = true;
        $ids = array_keys($ids); sort($ids); return $ids;
    };
    $all = array_map('intval', array_column(q('SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id'), 'id'));
    $byRole = function (string $val): array {
        $rows = q('SELECT DISTINCT ur.user_id FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id JOIN users u ON u.id=ur.user_id WHERE u.deleted_at IS NULL AND (r.id=? OR r.slug=?) ORDER BY ur.user_id', [ctype_digit($val) ? (int) $val : -1, $val]);
        return array_map('intval', array_column($rows, 'user_id'));
    };
    $base0 = $listed($hu);
    check('B30', 'Users list without filter: 200, every user listed (' . count($all) . ')', $own->status === 200 && $base0 === $all, json_encode([$base0, $all]));
    $act = $ff ? resolve($own->url, $ff['action'] ?: 'rolewarden/users') : BASE . 'rolewarden/users';
    $sep = str_contains($act, '?') ? '&' : '?';
    $roleBad = []; $tried = 0;
    foreach ($opts as $val => $label) {
        if ($val === '') continue;
        $tried++;
        $h = G($own, $act . $sep . http_build_query(array_replace($ff['fields'], [$rk => $val])), "B users role $val");
        $got = $listed($h); $exp = $byRole((string) $val);
        if (! $clean($own) || $own->status !== 200 || $got !== $exp) $roleBad[] = "$val ($label): status {$own->status} got " . json_encode($got) . ' expected ' . json_encode($exp);
    }
    check('B31', "Users list filtered by each role offered ($tried): 200, exactly the users holding that role (database)", $ff && $tried > 0 && $roleBad === [], json_encode($roleBad));
    $h = G($own, $act . $sep . http_build_query(array_replace($ff['fields'] ?? [], [$rk ?? 'role' => ''])), 'B users role empty');
    check('B32', 'Users list with the role filter submitted empty (the form as it comes): 200, every user, no error', $clean($own) && $own->status === 200 && $listed($h) === $all, $own->status . ' ' . json_encode($listed($h)));
    $h = G($own, 'rolewarden/users?' . ($rk ?? 'role') . '=', 'B users role= only');
    check('B33', 'Users list with only `' . ($rk ?? 'role') . '=` in the address: 200, every user, no error', $clean($own) && $own->status === 200 && $listed($h) === $all, $own->status . ' ' . json_encode($listed($h)));
    $odd = [];
    foreach (['999999', 'no-such-role', '0', '-1', "x'y", 'a[]'] as $v) {
        $h = G($own, 'rolewarden/users?' . ($rk ?? 'role') . '=' . rawurlencode($v), "B users role odd $v");
        $odd[] = "$v: {$own->status} " . count($listed($h));
        if (! $clean($own)) $odd[] = "$v NOT CLEAN";
    }
    $h = G($own, 'rolewarden/users?' . ($rk ?? 'role') . '[]=1', 'B users role array'); $odd[] = 'array: ' . $own->status . ' ' . count($listed($h)); if (! $clean($own)) $odd[] = 'array NOT CLEAN';
    info('B users list with values not offered (status, users listed): ' . json_encode($odd));
    check('B34', 'Users list with a role value not offered (unknown id or slug, quote, array): no 500, no SQL or PHP error on screen', ! preg_grep('/NOT CLEAN|: 5\d\d /', $odd), json_encode($odd));

    /* ---- B4: rw-signin with the thresholds of the Settings screen */
    $hs = G($own, 'rolewarden/settings', 'B settings');
    $fs = settings_form($hs);
    $keys = ['attempts' => null, 'minutes' => null, 'rate' => null];
    foreach (($fs['types'] ?? []) as $k => $t) {
        if (preg_match('/lock.?attempts|attempts.?lock|lockAttempts/i', $k)) $keys['attempts'] = $k;
        elseif (preg_match('/lock.?minutes|lockMinutes/i', $k)) $keys['minutes'] = $k;
        elseif (preg_match('/rate/i', $k)) $keys['rate'] = $k;
    }
    $optsOf = function (string $k) use ($fs): array { $o = []; foreach (dom($fs['html'])->query('//select[@name="' . $k . '"]/option') as $op) $o[] = $op->getAttribute('value'); return $o; };
    info('B settings fields: ' . json_encode($fs['types'] ?? null) . ' current ' . json_encode(array_intersect_key($fs['fields'] ?? [], array_flip(array_filter($keys)))) . ' options ' . json_encode(array_map(fn ($k) => $k && ($fs['types'][$k] ?? '') === 'select' ? $optsOf($k) : 'free', $keys)));
    $N = 0; $MIN = 0;
    if ($fs && $keys['attempts'] && $keys['minutes']) {
        $vals = $fs['fields'];
        $ao = ($fs['types'][$keys['attempts']] === 'select') ? array_map('intval', $optsOf($keys['attempts'])) : [3];
        $mo = ($fs['types'][$keys['minutes']] === 'select') ? array_map('intval', $optsOf($keys['minutes'])) : [7];
        sort($ao); sort($mo);
        // the smallest attempts value that is not the current one, and a duration that is not the current one
        foreach ($ao as $x) if ($x !== (int) $vals[$keys['attempts']]) { $N = $x; break; }
        foreach ($mo as $x) if ($x !== (int) $vals[$keys['minutes']] && $x > 1) { $MIN = $x; break; }
        $vals[$keys['attempts']] = (string) $N; $vals[$keys['minutes']] = (string) $MIN;
        P($own, resolve($own->url, $fs['action']), $vals, 'B settings lock');
        info("B lock thresholds chosen in the Settings screen: $N attempts, $MIN minutes; answer {$own->status}; stored " . json_encode(q("SELECT `key`, value FROM settings WHERE `key` LIKE '%lock%' OR `key` LIKE '%ate%'")));
    }
    check('B40', 'Settings screen: lock after N failures and lock duration saved with the screen\'s own form', $N > 0 && $MIN > 0 && $own->status < 400, json_encode([$keys, $N, $MIN, $own->status]));
    $try = function (string $email, string $pw, string $tag) use ($raw): array {
        $cl = c($tag);
        $h = G($cl, 'login', "$tag form");
        $f = form_with($h, 'password');
        P($cl, resolve($cl->url, $f['action'] ?? 'login'), array_replace($f['fields'] ?? [], ['email' => $email, 'password' => $pw]), "$tag post");
        $st = $cl->status; $loc = $cl->location; $ok = in_array($st, [302, 303], true) && ! str_contains($loc, 'login');
        $page = in_array($st, [302, 303], true) ? follow($cl, "$tag follow") : $cl->body;
        return ['ok' => $ok, 'status' => $st, 'loc' => $loc, 'page' => $raw($page), 'clean' => $st < 500 && ! leaks_sql($page) && ! phperr(text_of($page))];
    };
    $UPWB = $GLOBALS['NPW'] ?? UPW;
    $who = uid('user1@s3.test') ? 'user1@s3.test' : null;
    $seq = [];
    if ($who && $N > 1) {
        for ($i = 1; $i < $N; $i++) { $t = $try($who, 'Wrong-' . $i, "B4a-f$i"); $seq[] = "f$i:{$t['status']}"; }
        $ok1 = $try($who, $UPWB, 'B4a-ok'); $seq[] = 'right:' . ($ok1['ok'] ? 'in' : 'refused ' . short(text_of($ok1['page']), 80));
        check('B41', "Below the threshold: " . ($N - 1) . " failures, then the right password signs in (the screen's N = $N, not a lower value)", $ok1['ok'] && $ok1['clean'], json_encode($seq));
        $seq = [];
        $allClean = true;
        for ($i = 1; $i <= $N; $i++) { $t = $try($who, 'Wrong-b' . $i, "B4b-f$i"); $seq[] = "f$i:{$t['status']}"; $allClean = $allClean && $t['clean']; }
        $lk = $try($who, $UPWB, 'B4b-right');
        preg_match($MSG_LOCK_RE, $lk['page'], $mm);
        $seq[] = 'right:' . ($lk['ok'] ? 'in' : 'refused');
        check('B42', "At the threshold: after $N failures since the last success, the right password is refused with the lock message", ! $lk['ok'] && $lk['clean'] && $allClean && isset($mm[1]), json_encode($seq) . ' ' . short(text_of($lk['page']), 200));
        check('B43', "The lock message names the duration chosen in the Settings screen ($MIN minutes)", isset($mm[1]) && (int) $mm[1] <= $MIN && (int) $mm[1] >= $MIN - 1, $mm[0] ?? 'no lock message');
    } else {
        check('B41', 'A registered user to test the lock with (user1 of phase U)', false, "who=$who N=$N");
    }
    $nob = 'nobody-r1-' . bin2hex(random_bytes(2)) . '@s3.test';
    $seq = [];
    for ($i = 1; $i <= $N; $i++) { $t = $try($nob, 'Wrong-' . $i, "B4c-f$i"); $seq[] = "f$i:{$t['status']}" . (preg_match($MSG_LOCK_RE, $t['page']) ? 'L' : ''); }
    $t = $try($nob, 'Wrong-x', 'B4c-after');
    preg_match($MSG_LOCK_RE, $t['page'], $mn);
    check('B44', "An email that is not registered is locked the same way after $N failures (same message: no hint that it does not exist)", isset($mn[1]) && $t['clean'] && (int) $mn[1] <= $MIN, json_encode($seq) . ' ' . short(text_of($t['page']), 200));
    // per-address limit: down to the smallest value of the screen; everything below comes from this machine's one address
    $R = 0;
    $hs = G($own, 'rolewarden/settings', 'B settings 2');
    $fs = settings_form($hs);
    if ($fs && $keys['rate']) {
        $ro = ($fs['types'][$keys['rate']] === 'select') ? array_map('intval', $optsOf($keys['rate'])) : [5];
        sort($ro); $R = $ro[0];
        $vals = $fs['fields']; $vals[$keys['rate']] = (string) $R;
        P($own, resolve($own->url, $fs['action']), $vals, 'B settings rate');
        info("B per-address limit chosen in the Settings screen: $R per minute; answer {$own->status}; stored " . json_encode(q("SELECT `key`, value FROM settings WHERE `key` LIKE '%ate%'")));
    }
    $seq = []; $first = 0; $allClean = true;
    for ($i = 1; $i <= $R + 2; $i++) {
        $t = $try('rate-' . $i . '-' . bin2hex(random_bytes(2)) . '@s3.test', 'Wrong', "B4d-$i");
        $hit = str_contains($t['page'], $MSG_RATE);
        $seq[] = "$i:{$t['status']}" . ($hit ? 'R' : '');
        $allClean = $allClean && $t['clean'];
        if ($hit && ! $first) $first = $i;
    }
    $t = $try(OWNER, OPW, 'B4d-owner');
    $seq[] = 'owner:' . ($t['ok'] ? 'in' : ($t['status'] . (str_contains($t['page'], $MSG_RATE) ? 'R' : '')));
    info('B per-address limit sequence (attempt:status, R = limit message): ' . json_encode($seq));
    check('B45', "Per-address limit from the Settings screen ($R per minute): attempts beyond it get the limit message, no error page", $R > 0 && $first > 0 && $first <= $R + 1 && $allClean, json_encode($seq));
    check('B46', 'Past the per-address limit even the right password of the super admin is refused with the same message', ! $t['ok'] && str_contains($t['page'], $MSG_RATE) && $t['clean'], json_encode($seq));
}
