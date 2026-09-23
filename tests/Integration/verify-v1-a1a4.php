<?php
/** Decisions 2026-09-23: only HTTP and rolewarden_test queries, no module imports. */
declare(strict_types=1);
require __DIR__ . '/verify-v1.lib.php';
require __DIR__ . '/verify-v1.fixtures.php';
make_fixtures(); // fresh install per phase (verify-v1-a1a4.sh): Dana is the only active super admin
$ajax = ['X-Requested-With: XMLHttpRequest', 'Accept: application/json'];
$password = 'A1a4-' . bin2hex(random_bytes(12)) . '-Aa1!';
$role = fn(string $s): int => (int) q1('SELECT id FROM acl_roles WHERE slug=?', [$s]);
$perm = fn(string $s): int => (int) q1('SELECT id FROM acl_permissions WHERE slug=?', [$s]);
$responses = [];
function capture(Client $c, string $label): void {
    $GLOBALS['responses'][] = [$label, $c->status, $c->body];
}
function user(string $label, array $roles, ?int $override = null): array {
    q('INSERT INTO users (username,active,created_at) VALUES (?,1,NOW())', [$label]);
    $id = (int) db()->insert_id;
    $email = 'a1a4-' . $id . '@fixture.test';
    q('INSERT INTO auth_identities (user_id,type,secret,secret2,force_reset,created_at) VALUES (?,?,?,?,0,NOW())', [$id, 'email_password', $email, password_hash($GLOBALS['password'], PASSWORD_DEFAULT)]);
    foreach ($roles as $role) q('INSERT INTO acl_user_roles (user_id,role_id) VALUES (?,?)', [$id, $role]);
    if ($override !== null) q('INSERT INTO acl_user_permissions (user_id,permission_id,granted) VALUES (?,?,?)', [$id, ($GLOBALS['perm'])('roles.assign'), $override]);
    return [$id, $email];
}
function actor(string $name, array $roles, ?int $override = null): array {
    [$id, $email] = user($name, $roles, $override);
    $c = new Client('a1a4-' . $id);
    check('FIX', "$name signs in", $c->login($email, $GLOBALS['password']), "HTTP {$c->status}");
    return [$id, $c, $email];
}
function alert_text(string $html): string {
    preg_match_all('/<(?:div|p|section)[^>]*(?:role="(?:alert|status)"|class="[^"]*(?:flash|alert|notice)[^"]*")[^>]*>(.*?)<\/(?:div|p|section)>/s', $html, $m);
    return trim(preg_replace('/\s+/', ' ', strip_tags(implode(' | ', $m[1]))));
}
function assigned(int $id, int $rid): bool {
    return (int) q1('SELECT COUNT(*) FROM acl_user_roles WHERE user_id=? AND role_id=?', [$id,$rid]) === 1;
}
function revoke(Client $c, int $id, int $rid, string $label): array {
    $before = q('SELECT * FROM acl_user_roles ORDER BY user_id,role_id');
    $token = $c->token("rolewarden/users/$id");
    check('CSRF', "$label fresh page token", $token !== '');
    $c->req('POST', "rolewarden/users/$id/roles/$rid/revoke", ['csrf_test_name'=>$token]);
    capture($c, $label);
    $status = $c->status;
    $notCsrf = !csrf_refused($c);
    $location = $c->location;
    if ($location !== '') { $c->get($location); capture($c, "$label redirect"); }
    $text = trim(preg_replace('/\s+/', ' ', strip_tags(clean_html($c->body))));
    check('REACHED', "$label is not a CSRF rejection", $notCsrf && !csrf_refused($c), "HTTP $status");
    echo "EVIDENCE $label: HTTP $status -> " . alert_text($c->clean()) . "\n";
    return [$status, alert_text($c->clean()), $before];
}

echo "== A1 identical failed-login messages\n";
[$registeredId, $registered] = user('A1Known', []);
$alerts = [];
foreach ([$registered, 'a1a4-unknown@fixture.test'] as $email) {
    $c = new Client('a1a4-login-' . count($alerts));
    $c->post('login', ['email'=>$email, 'password'=>'incorrect-Aa123!'], 'login');
    capture($c, 'failed login POST');
    check('A1.POST', 'failed login reaches authentication and redirects to login', !csrf_refused($c) && in_array($c->status,[302,303],true) && str_contains($c->location, 'login'), "HTTP {$c->status}");
    $c->get('login'); capture($c, 'failed login alert');
    preg_match('/<[^>]*role="alert"[^>]*>(.*?)<\/(div|p)>/s', $c->clean(), $m);
    $alerts[] = trim(preg_replace('/\s+/', ' ', strip_tags($m[1] ?? '')));
}
check('A1', 'registered wrong password / unknown email: identical nonempty alert', $alerts[0] !== '' && $alerts[0] === $alerts[1], json_encode($alerts));
echo 'EVIDENCE A1 ' . json_encode($alerts) . "\n";

echo "== A2 own role removal\n";
foreach (['direct','parent','child'] as $name) {
    q('INSERT INTO acl_roles (slug,name,parent_id,is_system,is_super_admin,created_at) VALUES (?,?,?,0,0,NOW())', ['a1a4-'.$name, 'A1A4 '.$name, $name === 'child' ? $role('a1a4-parent') : null]);
}
foreach (['direct','parent'] as $name) q('INSERT INTO acl_role_permissions (role_id,permission_id) VALUES (?,?)', [$role('a1a4-'.$name),$perm('roles.assign')]);
$admin = $role('admin');
[$only, $c] = actor('A2OnlyAdmin', [$admin]);
$c->get("rolewarden/users/$only"); capture($c, 'only-admin own page');
// Served markup: the role row's control is labelled "Remove" (UserDetail README), the explanation sits in its title.
preg_match_all('/<button\b[^>]*\bdisabled\b[^>]*>\s*(?:Remove|Revoke)\s*<\/button>/', $c->clean(), $buttons);
$why = '';
foreach ($buttons[0] as $b) if (preg_match('/title="([^"]+)"/', $b, $t)) $why = html_entity_decode($t[1]);
preg_match('/<td class="rw-anno">(.*?)<\/td>/s', $c->clean(), $anno);
check('A2.UI', 'sole admin: Remove control of the admin role disabled, with an explanation', $c->status === 200 && count($buttons[0]) === 1 && $why !== '', 'disabled controls: ' . count($buttons[0]) . "; explanation '$why'");
echo "EVIDENCE A2.UI title='$why'; marginal note cell='" . trim(strip_tags($anno[1] ?? '')) . "'\n";
[$status,$text,$before] = revoke($c,$only,$admin,'sole admin self revoke');
check('A2.BLOCK', 'own last roles.assign role refused and all assignments unchanged', assigned($only,$admin) && q('SELECT * FROM acl_user_roles ORDER BY user_id,role_id') === $before && $status < 500 && $text !== '', "HTTP $status; role present=" . (int)assigned($only,$admin));
echo 'EVIDENCE A2.BLOCK HTTP ' . $status . ' role_present=' . (int)assigned($only,$admin) . "\n";
foreach (['direct','child'] as $extra) {
    [$id,$c] = actor('A2With'.$extra, [$admin,$role('a1a4-'.$extra)]);
    $c->get("rolewarden/users/$id"); capture($c, "own page with $extra");
    check('A2.UI+'.$extra, "own page: no Remove control disabled when another $extra role grants roles.assign", $c->status === 200 && !preg_match('/<button\b[^>]*\bdisabled\b[^>]*>\s*(?:Remove|Revoke)\s*<\/button>/', $c->clean()), 'a Remove control is disabled');
    [$status] = revoke($c,$id,$admin,"second $extra grant self revoke");
    check('A2.'.$extra, "admin removable with another $extra roles.assign grant", !assigned($id,$admin) && assigned($id,$role('a1a4-'.$extra)), "HTTP $status");
}
$blockText = $text;
foreach ([1,0] as $override) {
    [$id,$c] = actor('A2Override'.$override, [$admin],$override);
    [$status,$ovText] = revoke($c,$id,$admin,"override=$override self revoke");
    $kept = (int)q1('SELECT granted FROM acl_user_permissions WHERE user_id=? AND permission_id=?',[$id,$perm('roles.assign')]) === $override;
    if ($override === 1 || !assigned($id,$admin)) {
        check('A2.OV'.$override, "user override roles.assign=$override: own admin removal allowed", !assigned($id,$admin) && $kept, "HTTP $status; role_present=" . (int)assigned($id,$admin) . "; message='$ovText'");
    } else {
        // Denied override: the actor no longer holds roles.assign at all. Only the self-protection rule is under test here.
        check('A2.OV0', 'user override roles.assign=0: refusal (if any) is not the self-protection rule', $ovText !== $blockText && $kept, "HTTP $status; message='$ovText' equals the self-protection message");
        amb('A2.DENY', 'denied override on roles.assign: "removal allowed" cannot be exercised over HTTP', "HTTP $status, role kept, message '$ovText'. With roles.assign denied the actor cannot use the revoke route at all (route authorization), so the decision \"an override decides alone\" is only observable as: not refused by the self-protection rule.");
    }
}
[$id,$c] = actor('A2PlainRole',[$admin,$role('user')]);
[$status] = revoke($c,$id,$role('user'),'self non-granting role');
check('A2.PLAIN', 'own role without roles.assign removable', !assigned($id,$role('user')) && assigned($id,$admin), "HTTP $status");
[$other] = user('A2OtherAdmin',[$admin]);
[$status] = revoke($c,$other,$admin,'admin revokes another admin');
check('A2.OTHER', 'admin may revoke admin from another user', !assigned($other,$admin), "HTTP $status");
$owner = (int)q1("SELECT user_id FROM auth_identities WHERE secret='dana@northwind-studio.test'");
[$status] = revoke($c,$owner,$role('super-admin'),'last super admin via another admin');
check('A2.SUPER', 'last active super admin remains protected', assigned($owner,$role('super-admin')), "HTTP $status");

echo "== A3 inherited and direct permission revocation\n";
[$matrixId,$matrix] = actor('A3MatrixActor',[$admin]);
$parent = $role('a1a4-parent'); $child = $role('a1a4-child');
q('INSERT INTO acl_role_permissions (role_id,permission_id) VALUES (?,?)',[$child,$perm('users.view')]);
$before = q('SELECT * FROM acl_role_permissions ORDER BY role_id,permission_id');
$matrix->post("rolewarden/roles/$child/permissions",['permission_id'=>$perm('roles.assign'),'granted'=>0],"rolewarden/roles/$child",$ajax);
capture($matrix,'revoke inherited cell'); $j = json_decode($matrix->body,true);
check('A3.REJECT', 'inherited revoke: non-2xx, ok=false, origin role named, not CSRF', $matrix->status >= 400 && $matrix->status < 500 && ($j['ok']??null) === false && str_contains($matrix->body,'A1A4 parent') && !csrf_refused($matrix), "HTTP {$matrix->status}");
check('A3.DB', 'refused inherited revoke changes no role grants', q('SELECT * FROM acl_role_permissions ORDER BY role_id,permission_id') === $before);
echo 'EVIDENCE A3 HTTP ' . $matrix->status . ' ' . json_encode(['ok'=>$j['ok']??null,'message'=>$j['message']??null]) . "\n";
$matrix->post("rolewarden/roles/$child/permissions",['permission_id'=>$perm('users.view'),'granted'=>0],"rolewarden/roles/$child",$ajax);
capture($matrix,'revoke direct cell'); $j = json_decode($matrix->body,true);
check('A3.DIRECT', 'direct grant revoke succeeds and deletes only that cell', $matrix->status >= 200 && $matrix->status < 300 && ($j['ok']??null) === true && (int)q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?',[$child,$perm('users.view')]) === 0 && (int)q1('SELECT COUNT(*) FROM acl_role_permissions WHERE role_id=? AND permission_id=?',[$parent,$perm('roles.assign')]) === 1, "HTTP {$matrix->status}");

echo "== A4 literal search\n";
[$pct] = user('A4%Literal',[]);
[$under] = user('A4_Literal',[]);
[$apostrophe] = user("A4O'Brien",[]);
foreach (['%'=>[$pct], '_'=>[$under], 'd_na'=>[], 'A4%'=>[$pct], 'A4_'=>[$under], 'Dana'=>[$owner], 'dana@northwind'=>[$owner], "O'Brien"=>[$apostrophe]] as $query=>$expected) {
    $matrix->get('rolewarden/users?q='.rawurlencode($query)); capture($matrix,'search '.$query);
    preg_match_all('/<td class="rw-m-num">\d+<\/td>\s*<td class="rw-c-name"><a href="[^"]*\/users\/(\d+)">/',$matrix->clean(),$matches);
    $ids = array_map('intval',$matches[1]); sort($ids); sort($expected);
    check('A4.SEARCH', 'literal/substring query '.json_encode($query).' returns exact users', $matrix->status === 200 && $ids === $expected && !leaks_sql($matrix->body), "HTTP {$matrix->status}; actual=".json_encode($ids).'; expected='.json_encode($expected));
}
$leaks = [];
foreach ($responses as [$name,$status,$body]) if (leaks_sql($body)) $leaks[] = "$name HTTP $status";
check('SQL', 'no SQL/DB error text in dedicated responses', !$leaks,implode('; ',$leaks));
$pass = count(array_filter($GLOBALS['results'], fn($r)=>$r[2]));
$fail = count($GLOBALS['results'])-$pass;
echo "TOTAL A1-A4: $pass PASS, $fail FAIL; ".count($GLOBALS['ambiguities'])." ambiguity\n";
exit($fail ? 1 : 0);
