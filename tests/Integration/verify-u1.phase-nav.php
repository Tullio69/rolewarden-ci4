<?php
// U1 phase NAV (point 1): each sidebar item only with its permission, for accounts of every kind (none, one permission,
// overrides both ways, super admin with a denial, deactivated); no disabled item; no empty group; aria-current.
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== NAV: items, permissions, groups, aria-current\n";
serve8($APP, 'NAV');
$S0 = count($GLOBALS['SEEN']);
$R = [];
foreach (['users.view', 'roles.view', 'permissions.view', 'activity.view', 'settings.view', 'appearance.update'] as $p) $R[$p] = mkrole('u1r-' . str_replace('.', '-', $p), [$p]);
$R['create-import'] = mkrole('u1r-create-import', ['users.create', 'users.import', 'users.update', 'sessions.view', 'security.alerts']);
$R['mixed'] = mkrole('u1r-mixed', ['users.view', 'settings.view']);
$R['child'] = mkrole('u1r-child', []);
q('UPDATE acl_roles SET parent_id=? WHERE id=?', [$R['activity.view'], $R['child']]);   // inherits activity.view
$ACC = [
    'owner' => [$OWNER, 'owner@v8b.test', OPW],
    'admin' => [$ADMIN, 'admin@v8b.test', OPW],
    'plain' => [$PLAIN, 'plain@v8b.test', OPW],
];
foreach ($R as $k => $rid) if ($k !== 'child') { $e = 'nav-' . str_replace('.', '-', $k) . '@example.com'; $ACC["r:$k"] = [mkuser($e, (string) q1('SELECT slug FROM acl_roles WHERE id=?', [$rid])), $e, U1PW]; }
$ACC['child-role'] = [mkuser('nav-child@example.com', 'u1r-child'), 'nav-child@example.com', U1PW];
$ACC['admin-deny'] = [mkuser('nav-admin-deny@example.com', 'admin', ['users.view' => 0, 'activity.view' => 0]), 'nav-admin-deny@example.com', U1PW];
$ACC['super-deny'] = [mkuser('nav-super-deny@example.com', 'super-admin', ['settings.view' => 0, 'appearance.update' => 0]), 'nav-super-deny@example.com', U1PW];
$ACC['user-grant'] = [mkuser('nav-user-grant@example.com', 'user', ['roles.view' => 1, 'settings.view' => 1]), 'nav-user-grant@example.com', U1PW];
$ACC['grant-deny-child'] = [mkuser('nav-child-deny@example.com', 'u1r-child', ['activity.view' => 0, 'permissions.view' => 1]), 'nav-child-deny@example.com', U1PW];

$allRendered = [];
foreach ($ACC as $name => [$uid, $email, $pw]) {
    [$c, $ok] = u1login('nav-' . preg_replace('/[^a-z0-9-]/i', '-', $name), $email, $pw);
    if (! $ok) { check("N-$name-0", "$name signs in", false, "{$c->status} {$c->location}"); continue; }
    $h = G($c, 'rolewarden/profile', "nav profile $name");
    $s = side($h);
    $exp = expected_items($uid);
    $got = paths($s);
    $allRendered = array_merge($allRendered, $got);
    check("N-$name-1", "$name: the sidebar renders exactly the items of the permissions held (" . implode(',', array_map(fn ($p) => basename($p), $exp)) . ')', $s['found'] && $got === $exp, json_encode(['got' => $got, 'exp' => $exp]));
    $labels = array_column($s['groups'], 'label');
    $empty = array_filter($s['groups'], fn ($g) => $g['items'] === []);
    check("N-$name-2", "$name: no empty group, no disabled or link-less item", $empty === [] && $s['odd'] === [], json_encode([$labels, $s['odd']]));
    check("N-$name-3", "$name: group headers among Overview/Access/Security/System, in that order", array_values(array_intersect(GROUPS_EN, $labels)) === $labels, json_encode($labels));
    // the server agrees: every rendered item opens (200), every other one is refused
    $bad = [];
    foreach (NAVMAP as $p => $perm) {
        $hh = G($c, $p, "nav $name $p");
        $st = $c->status;
        $shown = in_array($p, $got, true);
        if ($shown && $st !== 200) $bad[] = "$p shown but $st";
        if (! $shown && $st === 200) $bad[] = "$p hidden but 200";
        if ($st === 200) {
            $ss = side($hh);
            $cur = $ss['current'];
            if ($cur !== ["$p=page"]) $bad[] = "$p current=" . json_encode($cur);
        }
    }
    check("N-$name-4", "$name: each shown item opens its screen with aria-current=\"page\" on that item only; each hidden one is refused", $bad === [], json_encode($bad));
}
$allRendered = array_values(array_unique($allRendered));
sort($allRendered);
check('N-all', 'No item outside the documented screens (Users, Roles, Permissions, Activity, Settings, Appearance) for any account', array_diff($allRendered, array_keys(NAVMAP)) === [], json_encode($allRendered));

// groups of the owner (every item): which items stand under which header, and no Overview group before U4
$c = c8('nav-own2'); login8($c, 'owner@v8b.test', OPW);
$s = side(G($c, 'rolewarden/users', 'nav owner groups'));
$map = []; foreach ($s['groups'] as $g) $map[$g['label']] = array_map(fn ($i) => basename($i['path']), $g['items']);
info('owner groups: ' . json_encode($map));
check('N-grp', 'Owner: Access holds Users, Roles, Permissions; Security the Activity log; System Settings and Appearance; no Overview group without an item (Dashboard enters with U4)',
    $map === ['Access' => ['users', 'roles', 'permissions'], 'Security' => ['activity'], 'System' => ['settings', 'appearance']], json_encode($map));
// aria-current on pages below a list: at most one item, the parent one
$sub = ['rolewarden/users/' . $ADMIN => 'rolewarden/users', 'rolewarden/users/create' => 'rolewarden/users', 'rolewarden/users/import' => 'rolewarden/users',
    'rolewarden/users/' . $ADMIN . '/edit' => 'rolewarden/users', 'rolewarden/roles/' . role_id('admin') => 'rolewarden/roles', 'rolewarden/roles/create' => 'rolewarden/roles',
    'rolewarden/roles/' . role_id('admin') . '/edit' => 'rolewarden/roles', 'rolewarden/profile' => null, 'rolewarden/sessions' => null];
$bad = []; $seenCur = [];
foreach ($sub as $u => $parent) {
    $hh = G($c, $u, "nav sub $u");
    $cur = side($hh)['current'];
    $seenCur[$u] = [$c->status, $cur];
    if ($c->status !== 200) { $bad[] = "$u {$c->status}"; continue; }
    if (count($cur) > 1 || ($cur && ($parent === null || $cur[0] !== "$parent=page"))) $bad[] = "$u " . json_encode($cur);
}
info('aria-current below the lists: ' . json_encode($seenCur));
check('N-sub', 'Pages below a list (detail, edit, create, import) and Profile/Sessions: at most one item marked current, and only the parent section', $bad === [], json_encode($bad));

// deactivated account: denies everything (SPEC precedence 1)
$off = mkuser('nav-off@example.com', 'admin', [], 0);
[$c, $ok] = u1login('nav-off', 'nav-off@example.com');
$h = G($c, 'rolewarden/profile', 'nav deactivated profile');
$s = side($h);
info("deactivated admin: login ok=" . json_encode($ok) . " profile {$c->status} items " . json_encode(paths($s)));
check('N-off', 'A deactivated account with the admin role gets no sidebar item (signed in or not)', paths($s) === [], json_encode([$ok, $c->status, paths($s)]));
// deactivated while signed in, through the panel's Deactivate (users.activate) by the owner
$mid = mkuser('nav-mid@example.com', 'admin');
[$c, $ok] = u1login('nav-mid', 'nav-mid@example.com');
$before = paths(side(G($c, 'rolewarden/profile', 'nav mid before')));
$own = c8('nav-mid-owner'); login8($own, 'owner@v8b.test', OPW);
postform($own, "rolewarden/users/$mid", "#rolewarden/users/$mid/deactivate$#", [], 'nav deactivate mid');
info("deactivate through the panel: {$own->status}, users.active=" . q1('SELECT active FROM users WHERE id=?', [$mid]));
$h = G($c, 'rolewarden/profile', 'nav mid after');
$after = paths(side($h));
check('N-mid', 'Deactivated from the panel while signed in: the next page shows no item', count($before) === 6 && (int) q1('SELECT active FROM users WHERE id=?', [$mid]) === 0 && $after === [], json_encode([$before, $c->status, $after]));
// a role deleted from the panel: the sidebar keeps agreeing with what the server allows
$dr = mkrole('u1r-doomed', ['roles.view', 'activity.view']);
$du = mkuser('nav-doomed@example.com', 'u1r-doomed');
$own = c8('nav-del-owner'); login8($own, 'owner@v8b.test', OPW);
postform($own, "rolewarden/roles/$dr", "#rolewarden/roles/$dr/delete$#", [], 'nav delete role');
info("role deleted from the panel: {$own->status}; deleted_at=" . json_encode(q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$dr])) . '; assignment left=' . q1('SELECT COUNT(*) FROM acl_user_roles WHERE role_id=?', [$dr]));
[$c, $ok] = u1login('nav-doomed', 'nav-doomed@example.com');
$got = paths(side(G($c, 'rolewarden/profile', 'nav doomed')));
$allowed = []; foreach (NAVMAP as $p => $_) { G($c, $p, "nav doomed $p"); if ($c->status === 200) $allowed[] = $p; }
sort($allowed);
check('N-del', 'After its only role is deleted from the panel, the user sees exactly the items the server still opens for him', $got === $allowed, json_encode([$got, $allowed]));
check('N-clean', 'NAV pages: no 5xx, no SQL or PHP error text', seen_clean($S0) === [], json_encode(seen_clean($S0)));
