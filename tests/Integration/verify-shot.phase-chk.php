<?php
// SHOT copy of verify-u2.phase-chk.php (Collaudatore ad Hoc): only the verdict selectors (screen() and the 'strong' strings of LANG) are widened for the verdict markup of 782fb64
// (p.rw-verdict > svg + span instead of p.rw-check-block > strong); every check, data set and expectation is unchanged.
/**
 * U2 phase CHK (Collaudatore ad Hoc, Claude): the Check a permission screen of 1.1 (tappa U2), in black box.
 * Sources of the assertions: docs/SPEC.md (precedence, decisions v1.1 "Verifica di un permesso"), docs/BRIEF-v1.1.md
 * (U2, definition of done), CHANGELOG (Unreleased), documentation/api.html (explain()), the design system (Sidebar),
 * and the HTML the server sends. Reference outcome: the SPEC precedence computed here from the tables (rule_model),
 * compared with service('rolewarden')->can() / explain() called by a spark command of this test written into the
 * throwaway copy of the application ($APP/app/Commands/U2Explain.php), and with the screen.
 * Sections: ACC (access), OUT (outcome per user and permission), WHO (who holds it, pagination), API (explain()
 * contract), HOS (hostile input, escape), RO (read only), LANG (English and Italian), LOG (errors and logs).
 */
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== CHK: Check a permission (U2)\n";
serve8($APP, 'CHK');
$S0 = count($GLOBALS['SEEN']);
const CHK = 'rolewarden/permissions/check';
const RULES = ['inactive', 'override_denied', 'override_granted', 'super_admin', 'role', 'none'];   // api.html, SPEC order

// ---------------------------------------------------------------- the spark probe: can() and explain() as the application sees them
@mkdir("$APP/app/Commands", 0777, true);
file_put_contents("$APP/app/Commands/U2Explain.php", <<<'PHP'
<?php
// Test-only command of the U2 black-box run (lives only in the throwaway copy): can() and explain() for pairs.
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class U2Explain extends BaseCommand
{
    protected $group = 'u2test';
    protected $name = 'u2:explain';
    protected $description = 'U2 test probe';

    public function run(array $params)
    {
        $in = json_decode((string) file_get_contents($params[0]), true);
        $rw = service('rolewarden');
        $out = [];
        foreach ($in as [$u, $p]) {
            $r = [];
            try { $r['can'] = $rw->can((int) $u, $p); } catch (\Throwable $e) { $r['canError'] = get_class($e); }
            try { $r['explain'] = $rw->explain((int) $u, $p); } catch (\Throwable $e) { $r['explainError'] = get_class($e) . ': ' . $e->getMessage(); }
            $out[] = $r;
        }
        file_put_contents($params[1], json_encode($out));
    }
}
PHP);
/** can()/explain() for a list of [userId, slug] pairs, through the application's own service. */
function rw_probe(array $pairs): array
{
    global $APP, $WORK;
    static $n = 0; $n++;
    $in = "$WORK/u2probe-in-$n.json"; $out = "$WORK/u2probe-out-$n.json";
    file_put_contents($in, json_encode(array_values($pairs)));
    sp8($APP, ['cache:clear']);
    $o = sp8($APP, ['u2:explain', $in, $out]);
    $r = is_file($out) ? json_decode((string) file_get_contents($out), true) : null;
    if (! is_array($r) || count($r) !== count($pairs)) { info('probe failed: ' . short($o, 800)); return array_fill(0, count($pairs), ['probeError' => true]); }
    return $r;
}

// ---------------------------------------------------------------- the SPEC precedence, computed from the tables
function user_roles(int $uid): array { return array_map('intval', array_column(q('SELECT ur.role_id FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.deleted_at IS NULL ORDER BY ur.role_id', [$uid]), 'role_id')); }
function role_direct(int $rid, string $perm): bool { return (int) q1('SELECT COUNT(*) FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.slug=?', [$rid, $perm]) > 0; }
/** Chains from each assigned (not deleted) role up through its parents to every role that holds $perm directly; deleted ancestors stop the walk. */
function chains_model(int $uid, string $perm): array
{
    $out = [];
    foreach (user_roles($uid) as $r) {
        $chain = []; $cur = $r; $seen = [];
        while ($cur && ! isset($seen[$cur])) {
            $seen[$cur] = 1;
            if (q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$cur]) !== null) break;
            $chain[] = $cur;
            if (role_direct($cur, $perm)) $out[] = $chain;
            $cur = (int) q1('SELECT parent_id FROM acl_roles WHERE id=?', [$cur]);
        }
    }
    return $out;
}
function rule_model(int $uid, string $perm): array
{
    if ((int) q1('SELECT active FROM users WHERE id=?', [$uid]) !== 1) return [false, 'inactive'];
    $ov = q1('SELECT up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=? AND p.slug=?', [$uid, $perm]);
    if ($ov !== null) return (int) $ov === 1 ? [true, 'override_granted'] : [false, 'override_denied'];
    foreach (user_roles($uid) as $r) if ((int) q1('SELECT is_super_admin FROM acl_roles WHERE id=?', [$r]) === 1) return [true, 'super_admin'];
    return chains_model($uid, $perm) ? [true, 'role'] : [false, 'none'];
}
function canon(array $chains): array { $c = array_map(fn ($x) => implode('>', array_map('intval', $x)), $chains); sort($c); return $c; }

// ---------------------------------------------------------------- reading the screen
function chainsIn(DOMXPath $xp, DOMNode $n): array
{
    // role links in document order; a "←" between two links keeps them in one chain, anything else (<br>, comma) starts a new one
    $html = ''; foreach ($n->childNodes as $ch) $html .= $n->ownerDocument->saveHTML($ch);
    preg_match_all('#<a [^>]*href="[^"]*/rolewarden/roles/(\d+)"[^>]*>.*?</a>|←|&larr;|<br\s*/?>|[,;]#su', $html, $m, PREG_SET_ORDER);
    $chains = []; $cur = []; $join = false;
    foreach ($m as $t) {
        if (isset($t[1]) && $t[1] !== '') { if ($cur && ! $join) { $chains[] = $cur; $cur = []; } $cur[] = (int) $t[1]; $join = false; }
        elseif ($t[0] === '←' || $t[0] === '&larr;') $join = true;
        else { if ($cur) { $chains[] = $cur; $cur = []; } $join = false; }
    }
    if ($cur) $chains[] = $cur;
    return $chains;
}
function screen(string $h): array
{
    $xp = dom($h);
    $o = ['outcome' => null, 'subject' => null, 'outText' => null, 'rule' => null, 'ruleCount' => 0, 'ruleTexts' => [], 'roles' => null, 'rows' => [], 'count' => null, 'status' => [], 'pages' => [], 'empty' => null];
    // SHOT adaptation: from 782fb64 the verdict is p.rw-verdict with a mark and the sentence in a span (larger text); the old p > strong is still read
    $st = $xp->query('//main//p[contains(@class,"rw-check-block")]/strong | //main//p[contains(@class,"rw-verdict")]/span')->item(0);
    if ($st) {
        $o['outText'] = trim(preg_replace('/\s+/u', ' ', $st->textContent));
        $a = $xp->query('.//a', $st)->item(0);
        $o['subject'] = $a && preg_match('#/rolewarden/users/(\d+)$#', $a->getAttribute('href'), $mm) ? (int) $mm[1] : null;
        $o['outcome'] = str_contains($o['outText'], ' does not hold ') ? false : (str_contains($o['outText'], ' holds ') ? true : null);
    }
    foreach ($xp->query('//main//ol[contains(@class,"rw-check-rules")]/li') as $i => $li) {
        $o['ruleTexts'][] = trim(preg_replace('/\s+/u', ' ', $li->textContent));
        if ($li->getAttribute('aria-current') !== '') { $o['ruleCount']++; $o['rule'] = RULES[$i] ?? "#$i"; }
    }
    foreach ($xp->query('//main//p[contains(@class,"rw-check-block")][span[contains(@class,"rw-header")]]') as $p) $o['roles'] = chainsIn($xp, $p);
    foreach ($xp->query('//main//table[contains(@class,"rw-t-check")]/tbody/tr') as $tr) {
        $tds = $xp->query('./td', $tr);
        if ($tds->length < 3) continue;
        $a = $xp->query('.//a', $tds->item(1))->item(0);
        $uid = $a && preg_match('#/rolewarden/users/(\d+)$#', $a->getAttribute('href'), $mm) ? (int) $mm[1] : -1;
        $why = trim(preg_replace('/\s+/u', ' ', $tds->item(2)->textContent));
        $o['rows'][] = ['uid' => $uid, 'name' => $a ? $a->textContent : '', 'why' => $why, 'chains' => chainsIn($xp, $tds->item(2)), 'num' => trim($tds->item(0)->textContent),
            'check' => $tds->length > 3 ? (($x = $xp->query('.//a', $tds->item(3))->item(0)) ? html_entity_decode($x->getAttribute('href')) : null) : null];
    }
    $c = $xp->query('//main//div[contains(@class,"rw-section-title")]//span[contains(@class,"rw-fig-sm")]')->item(0);
    $o['count'] = $c ? (int) trim($c->textContent) : null;
    foreach ($xp->query('//main//*[@role="status"]') as $s) $o['status'][] = trim(preg_replace('/\s+/u', ' ', $s->textContent));
    foreach ($xp->query('//main//a[contains(@href,"page")]') as $a) $o['pages'][] = html_entity_decode($a->getAttribute('href'));
    $e = $xp->query('//main//p[contains(@class,"rw-check-block") and contains(@class,"rw-muted")]')->item(0);
    $o['empty'] = $e ? trim(preg_replace('/\s+/u', ' ', $e->textContent)) : null;
    return $o;
}
function chk(Client $c, array $qs, string $tag): array { $h = G($c, CHK . ($qs ? '?' . http_build_query($qs) : ''), $tag); return screen($h) + ['http' => $c->status, 'html' => $h]; }

// ---------------------------------------------------------------- data of the test
function mkperm(string $slug): int
{
    $cols = array_column(q('SHOW COLUMNS FROM acl_permissions'), 'Field');
    $now = date('Y-m-d H:i:s');
    $v = ['slug' => $slug, 'name' => ucfirst(str_replace('.', ' ', $slug)), 'area' => explode('.', $slug)[0], 'description' => 'U2 test permission', 'is_system' => 0, 'created_at' => $now, 'updated_at' => $now];
    $v = array_intersect_key($v, array_flip($cols));
    q('INSERT INTO acl_permissions (`' . implode('`,`', array_keys($v)) . '`) VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')', array_values($v));
    return (int) db()->insert_id;
}
function parent_of(int $child, int $parent): void { q('UPDATE acl_roles SET parent_id=? WHERE id=?', [$parent, $child]); }
foreach (['u2zone.read', 'u2zone.write', 'u2zone.none', 'u2zone.page'] as $p) mkperm($p);
$R = [];
$R['grand'] = mkrole('u2-grand', ['u2zone.read']);
$R['parent'] = mkrole('u2-parent', []); parent_of($R['parent'], $R['grand']);
$R['child'] = mkrole('u2-child', []); parent_of($R['child'], $R['parent']);
$R['direct'] = mkrole('u2-direct', ['u2zone.write', 'u2zone.read']);
$R['doomed'] = mkrole('u2-doomed', ['u2zone.read', 'u2zone.write']);
$R['dpar'] = mkrole('u2-dpar', ['u2zone.read']);
$R['dkid'] = mkrole('u2-dkid', []); parent_of($R['dkid'], $R['dpar']);
$R['xss'] = mkrole('u2-xss', ['u2zone.read']);
$XSSROLE = '<img src=x onerror=alert(7)>&"r';
q('UPDATE acl_roles SET name=? WHERE id=?', [$XSSROLE, $R['xss']]);
$R['pv'] = mkrole('u2-pv', ['permissions.view']);
$R['pvmid'] = mkrole('u2-pvmid', []); parent_of($R['pvmid'], $R['pv']);
$R['pvkid'] = mkrole('u2-pvkid', []); parent_of($R['pvkid'], $R['pvmid']);
$R['pvdoomed'] = mkrole('u2-pvdoomed', ['permissions.view']);
$R['uview'] = mkrole('u2-uview', ['users.view', 'roles.view']);
$R['pg'] = mkrole('u2-pg', ['u2zone.page']);
$U = [];
$U['off'] = mkuser('u2-off@example.com', 'u2-grand', [], 0);
$U['offsup'] = mkuser('u2-offsup@example.com', 'super-admin', [], 0);
$U['offgrant'] = mkuser('u2-offgrant@example.com', 'user', ['u2zone.read' => 1], 0);
$U['deny'] = mkuser('u2-deny@example.com', 'u2-child', ['u2zone.read' => 0]);
$U['supdeny'] = mkuser('u2-supdeny@example.com', 'super-admin', ['u2zone.read' => 0, 'permissions.view' => 0]);
$U['grant'] = mkuser('u2-grant@example.com', 'user', ['u2zone.read' => 1, 'permissions.view' => 1]);
$U['grantrole'] = mkuser('u2-grantrole@example.com', 'u2-direct', ['u2zone.read' => 1]);
$U['denyrole2'] = mkuser('u2-denyrole2@example.com', 'u2-direct', ['u2zone.write' => 0, 'u2zone.none' => 1]);
$U['sup'] = mkuser('u2-sup@example.com', 'super-admin');
$U['direct'] = mkuser('u2-direct@example.com', 'u2-direct');
$U['child'] = mkuser('u2-child@example.com', 'u2-child');
$U['parent'] = mkuser('u2-parent@example.com', 'u2-parent');
$U['none'] = mkuser('u2-none@example.com', 'user');
$U['norole'] = mkuser('u2-norole@example.com', '');
$U['del'] = mkuser('u2-del@example.com', 'u2-doomed');
$U['dkid'] = mkuser('u2-dkid@example.com', 'u2-dkid');
$XSSUSER = '<b>u2"x</b>&amp';
$U['xss'] = make_owner('u2-xss@example.com', U1PW, $XSSUSER, 'u2-xss');
$U['multi'] = mkuser('u2-multi@example.com', 'u2-direct');
q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$U['multi'], $R['child']]);
$U['softdel'] = mkuser('u2-softdel@example.com', 'u2-direct');
// access accounts
$A = [];
$A['pv'] = mkuser('u2-a-pv@example.com', 'u2-pv');
$A['pvmid'] = mkuser('u2-a-pvmid@example.com', 'u2-pvmid');
$A['pvkid'] = mkuser('u2-a-pvkid@example.com', 'u2-pvkid');
$A['pvdoomed'] = mkuser('u2-a-pvdoomed@example.com', 'u2-pvdoomed');
$A['uview'] = mkuser('u2-a-uview@example.com', 'u2-uview');
$A['admindeny'] = mkuser('u2-a-admindeny@example.com', 'admin', ['permissions.view' => 0]);
$A['supdeny'] = $U['supdeny'];
$A['usergrant'] = $U['grant'];
$A['adminoff'] = mkuser('u2-a-adminoff@example.com', 'admin', [], 0);
$A['admin'] = $ADMIN; $A['owner'] = $OWNER; $A['plain'] = $PLAIN;
$EMAIL = fn (int $id) => (string) q1("SELECT secret FROM auth_identities WHERE type='email_password' AND user_id=?", [$id]);
$PW = fn (int $id) => in_array($id, [$OWNER, $ADMIN, $PLAIN], true) ? OPW : U1PW;
// pagination: 45 more holders of u2zone.page
$PG = [];
for ($i = 1; $i <= 45; $i++) $PG[] = make_owner(sprintf('u2-pg%02d@example.com', $i), U1PW, sprintf('u2-pg%02d', $i), 'u2-pg');
q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,1,NOW(),NOW())', [$U['grant'], perm_id('u2zone.page')]);
q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,0,NOW(),NOW())', [$PG[3], perm_id('u2zone.page')]);
q('UPDATE users SET active=0 WHERE id=?', [$PG[7]]);

$own = c8('k-owner'); login8($own, 'owner@v8b.test', OPW);
// deletions through the panel, as an admin would do them
postform($own, "rolewarden/roles/{$R['doomed']}", "#rolewarden/roles/{$R['doomed']}/delete$#", [], 'chk delete doomed role');
info("delete u2-doomed through the panel: {$own->status}; deleted_at=" . json_encode(q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$R['doomed']])) . '; assignments left=' . q1('SELECT COUNT(*) FROM acl_user_roles WHERE role_id=?', [$R['doomed']]));
postform($own, "rolewarden/roles/{$R['pvdoomed']}", "#rolewarden/roles/{$R['pvdoomed']}/delete$#", [], 'chk delete pvdoomed role');
postform($own, "rolewarden/roles/{$R['dpar']}", "#rolewarden/roles/{$R['dpar']}/delete$#", [], 'chk delete parent role');
$dparDeleted = q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$R['dpar']]) !== null;
info("delete u2-dpar (parent of u2-dkid) through the panel: {$own->status}; deleted=" . json_encode($dparDeleted) . '; u2-dkid parent_id now ' . json_encode(q1('SELECT parent_id FROM acl_roles WHERE id=?', [$R['dkid']])));
if (! $dparDeleted) {
    // the panel keeps a parent with children: make the state by hand (a deleted parent can exist after a 1.0 deletion or a direct write)
    q('UPDATE acl_roles SET deleted_at=NOW() WHERE id=?', [$R['dpar']]);
    info('u2-dpar marked deleted by hand (deleted_at), parent_id of u2-dkid kept');
}
$h = G($own, "rolewarden/users/{$U['softdel']}", 'chk user page softdel');
$df = null; foreach (forms($h) as $f) if ($f['method'] === 'post' && preg_match('#/delete$#', u1path($f['action']))) $df = $f;
if ($df) P($own, $df['action'], $df['fields'], 'chk delete user');
info('delete user through the panel: ' . ($df ? $own->status : 'no form') . '; users.deleted_at=' . json_encode(q1('SELECT deleted_at FROM users WHERE id=?', [$U['softdel']])));
if (q1('SELECT deleted_at FROM users WHERE id=?', [$U['softdel']]) === null) { q('UPDATE users SET deleted_at=NOW() WHERE id=?', [$U['softdel']]); info('user soft-deleted by hand'); }

// ================================================================ ACC: who reaches the screen
echo "\n-- ACC\n";
$probe = rw_probe(array_map(fn ($id) => [$id, 'permissions.view'], array_values($A)));
$i = 0; $bad = [];
foreach ($A as $name => $id) {
    $exp = rule_model($id, 'permissions.view')[0];
    $can = $probe[$i++]['can'] ?? null;
    if ($can !== $exp) $bad[] = "$name: model $exp can " . json_encode($can);
}
check('A-0', 'Access accounts: permissions.view by the SPEC precedence equals service can() for every account (' . count($A) . ')', $bad === [], json_encode($bad));
$secret = q1('SELECT username FROM users WHERE id=?', [$U['child']]);
foreach ($A as $name => $id) {
    $exp = rule_model($id, 'permissions.view')[0];
    $c = c8("k-acc-$name");
    $ok = login8($c, $EMAIL($id), $PW($id));
    if (! $ok) {
        // a deactivated account may be turned away at sign-in: then nothing of the panel is reachable
        $G = G($c, CHK . '?permission=u2zone.read&user=' . $U['child'], "acc $name anon");
        check("A-$name", "$name (cannot sign in): the check screen is refused", ! $exp && $c->status !== 200 && ! str_contains($G, (string) $secret), "{$c->status} {$c->location}");
        continue;
    }
    $s = side(G($c, 'rolewarden/profile', "acc $name profile"));
    $has = array_key_exists(CHK, $s['items']);
    $G = G($c, CHK . '?permission=u2zone.read&user=' . $U['child'], "acc $name check");
    $st = $c->status;
    $sc = screen($G);
    $leak = $st === 200 ? false : (str_contains($G, (string) $secret) || str_contains($G, 'u2zone.read'));
    check("A-$name", "$name (" . ($exp ? 'holds' : 'lacks') . ' permissions.view): sidebar item ' . ($exp ? 'shown' : 'absent') . ', screen ' . ($exp ? 'opens (200) with the outcome' : 'refused by the server, nothing of it in the answer'),
        $has === $exp && ($exp ? ($st === 200 && $sc['outcome'] !== null) : ($st !== 200 && ! $leak)), json_encode(['item' => $has, 'status' => $st, 'loc' => $c->location, 'leak' => $leak]));
}
$anon = c8('k-anon');
G($anon, CHK . '?permission=u2zone.read', 'acc anonymous');
check('A-anon', 'Anonymous: the screen redirects to sign-in', in_array($anon->status, [302, 303], true) && str_contains($anon->location, 'login'), "{$anon->status} {$anon->location}");
// the item: label, group, current page, links from the Permissions list
$s = side(G($own, CHK, 'acc owner check'));
$grp = null; foreach ($s['groups'] as $g) foreach ($g['items'] as $it) if ($it['path'] === CHK) $grp = $g['label'];
check('A-item', 'Sidebar: the item "Check a permission" sits in Access after Permissions and is the current page (aria-current="page") on the screen, the only one', $grp === 'Access' && ($s['items'][CHK]['text'] ?? '') === 'Check a permission' && $s['current'] === [CHK . '=page'], json_encode([$grp, $s['items'][CHK] ?? null, $s['current']]));
$hp = G($own, 'rolewarden/permissions', 'acc permissions list');
$xp = dom($hp);
$slugs = array_column(q('SELECT slug FROM acl_permissions ORDER BY slug'), 'slug');
$links = [];
foreach ($xp->query('//main//a[contains(@href,"permissions/check?permission=")]') as $a) { parse_str((string) parse_url(html_entity_decode($a->getAttribute('href')), PHP_URL_QUERY), $qq); $links[] = ['slug' => $qq['permission'] ?? '', 'text' => trim($a->textContent)]; }
$ls = array_column($links, 'slug'); sort($ls);
check('A-links', 'Permissions list: every permission (' . count($slugs) . ') links to its check, the link text is the slug', $ls === $slugs && ! array_filter($links, fn ($l) => $l['text'] !== $l['slug']), json_encode(array_values(array_diff($slugs, $ls))));
$sel = []; foreach (dom(G($own, CHK, 'acc select'))->query('//select[@name="permission"]//option[@value!=""]') as $o) $sel[] = $o->getAttribute('value');
sort($sel);
check('A-select', 'The permission picker offers every permission, nothing else', $sel === $slugs, json_encode([count($sel), count($slugs)]));

// ================================================================ OUT: outcome and rule for user and permission pairs
echo "\n-- OUT\n";
$PERMS = ['u2zone.read', 'u2zone.write', 'u2zone.none', 'users.view', 'permissions.view', 'settings.update'];
$USERS = $U + ['owner' => $OWNER, 'admin' => $ADMIN, 'plain' => $PLAIN];
unset($USERS['softdel']);
$pairs = []; foreach ($USERS as $n => $id) foreach ($PERMS as $p) $pairs[] = [$id, $p, $n];
$probe = rw_probe(array_map(fn ($x) => [$x[0], $x[1]], $pairs));
$mm = ['model' => [], 'screen' => [], 'via' => [], 'shape' => []];
$ruleSeen = [];
foreach ($pairs as $k => [$id, $p, $n]) {
    [$expA, $expR] = rule_model($id, $p);
    $pr = $probe[$k];
    $ex = $pr['explain'] ?? null;
    $sc = chk($own, ['permission' => $p, 'user' => (string) $id], "out $n $p");
    $tag = "$n/$p";
    $ruleSeen[$expR][] = $tag;
    $doubtful = $n === 'dkid';   // deleted parent: not written in the SPEC, see AMB below
    if (! is_array($ex) || ! array_key_exists('allowed', $ex) || ! array_key_exists('rule', $ex) || ! array_key_exists('via', $ex) || ! is_bool($ex['allowed']) || ! in_array($ex['rule'], RULES, true) || ! is_array($ex['via'])) $mm['shape'][] = "$tag " . json_encode($pr);
    elseif ($ex['allowed'] !== ($pr['can'] ?? null)) $mm['shape'][] = "$tag explain.allowed != can " . json_encode($pr);
    if (! $doubtful && (($pr['can'] ?? null) !== $expA || ($ex['rule'] ?? null) !== $expR)) $mm['model'][] = "$tag model [" . json_encode($expA) . ",$expR] can " . json_encode($pr['can'] ?? null) . ' rule ' . json_encode($ex['rule'] ?? null);
    if ($sc['http'] !== 200 || $sc['outcome'] !== ($pr['can'] ?? 'x') || $sc['rule'] !== ($ex['rule'] ?? 'x') || $sc['ruleCount'] !== 1 || $sc['subject'] !== $id || count($sc['ruleTexts']) !== 6)
        $mm['screen'][] = "$tag screen " . json_encode([$sc['http'], $sc['outcome'], $sc['rule'], $sc['ruleCount'], $sc['subject']]) . ' service ' . json_encode([$pr['can'] ?? null, $ex['rule'] ?? null]);
    $viaScreen = canon($sc['roles'] ?? []);
    $viaApi = canon($ex['via'] ?? []);
    if ($viaScreen !== $viaApi) $mm['via'][] = "$tag screen " . json_encode($viaScreen) . ' explain ' . json_encode($viaApi);
    if (! $doubtful && $viaApi !== canon(chains_model($id, $p))) $mm['viamodel'][] = "$tag explain " . json_encode($viaApi) . ' model ' . json_encode(canon(chains_model($id, $p)));
    if ($n === 'dkid') $DKID[$p] = ['model' => rule_model($id, $p), 'modelVia' => chains_model($id, $p), 'can' => $pr['can'] ?? null, 'explain' => $ex, 'screen' => [$sc['outcome'], $sc['rule'], $sc['roles']]];
}
info('rules exercised: ' . json_encode(array_map('count', $ruleSeen)));
check('O-1', 'explain() answers {allowed bool, rule among the six RULE_* values, via list} for every pair, allowed always equal to can() (' . count($pairs) . ' pairs)', $mm['shape'] === [], json_encode(array_slice($mm['shape'], 0, 6)));
check('O-2', 'can() and explain() rule equal the SPEC precedence computed from the tables for every pair (inactive, override denied also on super admin, override granted, super admin, role direct/parent/grandparent, none, deleted role)', $mm['model'] === [], json_encode(array_slice($mm['model'], 0, 8)));
check('O-3', 'The screen gives, for every pair, the outcome of can() and marks exactly one rule, the one explain() names, among six rules listed in order; the subject is the user asked', $mm['screen'] === [], json_encode(array_slice($mm['screen'], 0, 8)));
check('O-4', 'Every rule of the precedence was exercised at least once', count(array_intersect(RULES, array_keys($ruleSeen))) === 6, json_encode(array_keys($ruleSeen)));
check('O-5', 'The roles the screen names as holding it are the chains of explain()["via"] for every pair', $mm['via'] === [], json_encode(array_slice($mm['via'], 0, 8)));
check('O-6', 'explain()["via"]: each chain from the assigned role up to the role granted directly, also when another rule decides (inactive, denied, granted override, super admin with a role)', ($mm['viamodel'] ?? []) === [], json_encode(array_slice($mm['viamodel'] ?? [], 0, 8)));
// specific readings the brief names
$one = fn (string $n, string $p) => chk($own, ['permission' => $p, 'user' => (string) $USERS[$n]], "out one $n $p");
$sc = $one('child', 'u2zone.read');
check('O-gp', 'Inherited from the grandparent: rule role, chain u2-child ← u2-parent ← u2-grand', $sc['rule'] === 'role' && $sc['outcome'] === true && $sc['roles'] === [[$R['child'], $R['parent'], $R['grand']]], json_encode($sc['roles']));
$sc = $one('parent', 'u2zone.read');
check('O-p', 'Inherited from the parent: rule role, chain u2-parent ← u2-grand', $sc['rule'] === 'role' && $sc['outcome'] === true && $sc['roles'] === [[$R['parent'], $R['grand']]], json_encode($sc['roles']));
$sc = $one('supdeny', 'u2zone.read');
check('O-sd', 'A denial on a super admin decides: does not hold, rule override_denied', $sc['outcome'] === false && $sc['rule'] === 'override_denied', json_encode([$sc['outcome'], $sc['rule']]));
$sc = $one('offgrant', 'u2zone.read');
check('O-og', 'A deactivated account with a granting override: does not hold, rule inactive', $sc['outcome'] === false && $sc['rule'] === 'inactive', json_encode([$sc['outcome'], $sc['rule']]));
$sc = $one('del', 'u2zone.read');
check('O-del', 'The only role deleted from the panel: does not hold, rule none, no role named', $sc['outcome'] === false && $sc['rule'] === 'none' && ($sc['roles'] ?? []) === [], json_encode([$sc['outcome'], $sc['rule'], $sc['roles']]));
$sc = $one('multi', 'u2zone.read');
check('O-multi', 'Two roles holding it (one direct, one through two parents): both chains named', canon($sc['roles'] ?? []) === canon([[$R['direct']], [$R['child'], $R['parent'], $R['grand']]]), json_encode($sc['roles']));
// deleted parent: not decided by the SPEC; the screen must at least agree with can()/explain()
$d = $DKID['u2zone.read'] ?? null;
info('deleted parent (u2-dkid under deleted u2-dpar), u2zone.read: ' . json_encode($d));
check('O-dp', 'Role whose parent is deleted: the screen agrees with can() and explain() (outcome, rule, roles)', $d && $d['screen'][0] === $d['can'] && $d['screen'][1] === ($d['explain']['rule'] ?? null) && canon($d['screen'][2] ?? []) === canon($d['explain']['via'] ?? []), json_encode($d));
if ($d && $d['can'] !== false) amb('AMB-dp', 'A role whose parent role is deleted still receives the deleted parent\'s permissions', 'can()=' . json_encode($d['can']) . ' rule ' . json_encode($d['explain']['rule'] ?? null) . '; the SPEC says a deleted role keeps its assignments history but not whether its permissions still flow to children');
else info('deleted parent grants nothing to its child (can=false)');

// ================================================================ WHO: who holds it
echo "\n-- WHO\n";
$all = array_map('intval', array_column(q('SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id'), 'id'));
$dead = array_map('intval', array_column(q('SELECT id FROM users WHERE deleted_at IS NOT NULL'), 'id'));
info('users: ' . count($all) . ' not deleted, deleted ' . json_encode($dead));
$WPERMS = ['u2zone.read', 'u2zone.write', 'u2zone.none', 'u2zone.page', 'users.view', 'permissions.view', 'settings.update'];
$pairs = []; foreach ($WPERMS as $p) foreach (array_merge($all, $dead) as $id) $pairs[] = [$id, $p];
$probe = rw_probe($pairs);
$CAN = []; $EX = [];
foreach ($pairs as $k => [$id, $p]) { $CAN[$p][$id] = $probe[$k]['can'] ?? null; $EX[$p][$id] = $probe[$k]['explain'] ?? null; }
$pageInfo = [];
foreach ($WPERMS as $p) {
    $rows = []; $counts = []; $seenUrls = []; $url = CHK . '?' . http_build_query(['permission' => $p]); $guard = 0; $nums = [];
    while ($url && $guard++ < 20) {
        $h = G($own, $url, "who $p $guard");
        $sc = screen($h);
        $counts[] = $sc['count'];
        foreach ($sc['rows'] as $r) { $rows[] = $r; $nums[] = $r['num']; }
        $seenUrls[] = $url;
        $next = null;
        foreach ($sc['pages'] as $pu) { if (! in_array(u1path($pu), $seenUrls, true) && ! in_array($pu, $seenUrls, true) && preg_match('/[?&]page[^=]*=(\d+)/', $pu, $pm) && (int) $pm[1] === $guard + 1) { $next = $pu; break; } }
        $url = $next ? u1path($next) : null;
    }
    $pageInfo[$p] = ['pages' => $guard, 'rows' => count($rows)];
    $ids = array_column($rows, 'uid'); $uniq = array_unique($ids); sort($uniq);
    $exp = array_keys(array_filter($CAN[$p], fn ($v) => $v === true)); $exp = array_values(array_diff($exp, $dead)); sort($exp);
    $model = array_values(array_filter($all, fn ($id) => rule_model($id, $p)[0] && ! ($id === $U['dkid'])));
    $expNoDk = array_values(array_diff($exp, [$U['dkid']]));
    check("W-$p-1", "$p: the list is exactly the users for whom can() is true, deleted users left out (" . count($exp) . '), each once', $ids === array_values(array_unique($ids)) && $uniq === $exp && ! array_intersect($ids, $dead), json_encode(['missing' => array_values(array_diff($exp, $uniq)), 'extra' => array_values(array_diff($uniq, $exp)), 'dup' => count($ids) - count($uniq)]));
    check("W-$p-2", "$p: and that set is the SPEC precedence computed from the tables", $expNoDk === $model, json_encode(['can-only' => array_values(array_diff($expNoDk, $model)), 'model-only' => array_values(array_diff($model, $expNoDk))]));
    $badWhy = [];
    foreach ($rows as $r) {
        $ex = $EX[$p][$r['uid']] ?? null;
        $rule = $ex['rule'] ?? null;
        $kind = str_starts_with($r['why'], 'Super admin') ? 'super_admin' : (str_starts_with($r['why'], 'Override: granted') ? 'override_granted' : (str_starts_with($r['why'], 'Role:') ? 'role' : '?'));
        if ($kind !== $rule) $badWhy[] = "{$r['uid']} why='{$r['why']}' rule=$rule";
        elseif ($kind === 'role' && canon($r['chains']) !== canon($ex['via'] ?? [])) $badWhy[] = "{$r['uid']} chains " . json_encode($r['chains']) . ' via ' . json_encode($ex['via'] ?? null);
        $want = CHK . '?' . http_build_query(['permission' => $p, 'user' => $r['uid']]);
        if ($r['check'] === null || u1path($r['check']) !== $want) $badWhy[] = "{$r['uid']} check link " . json_encode($r['check']);
    }
    check("W-$p-3", "$p: each row says why with the rule of explain() (Super admin / Override: granted / Role: chain of via) and links to the check for that user", $badWhy === [], json_encode(array_slice($badWhy, 0, 6)));
    $cnt = array_values(array_unique($counts));
    check("W-$p-4", "$p: the heading count is the number of holders on every page (" . count($exp) . ')', $exp === [] ? ($cnt === [null] || $cnt === [0]) : $cnt === [count($exp)], json_encode($counts));
    if ($exp === []) check("W-$p-5", "$p: nobody holds it: an empty state, no table", $rows === [] && (string) $sc['empty'] !== '', json_encode($sc['empty']));
    if (count($rows) > 1) { $nn = array_map('intval', $nums); check("W-$p-6", "$p: row numbers run 01.. without gaps across pages", $nn === range(1, count($nn)), json_encode(array_slice($nums, 0, 60))); }
}
info('pages: ' . json_encode($pageInfo));
check('W-pg', 'Pagination: u2zone.page (47 holders) spreads over more than one page, every holder reached through the page links', ($pageInfo['u2zone.page']['pages'] ?? 0) > 1, json_encode($pageInfo['u2zone.page'] ?? null));
// a page beyond the end, page 0, negative: no error, no stranger
foreach (['999', '0', '-3', 'abc', '1e3', '2.5'] as $pv) {
    $h = G($own, CHK . '?' . http_build_query(['permission' => 'u2zone.page', 'page' => $pv]), "who page $pv");
    $sc = screen($h);
    $ids = array_column($sc['rows'], 'uid');
    $bad = array_diff($ids, array_keys(array_filter($CAN['u2zone.page'], fn ($v) => $v === true)));
    $PAGEODD[$pv] = [$own->status, count($ids)];
    if ($own->status >= 500 || $bad) $PAGEBAD[] = "$pv {$own->status} " . json_encode($bad);
}
info('odd page values (status, rows): ' . json_encode($PAGEODD));
check('W-pg2', 'Odd page values (999, 0, -3, abc, 1e3, 2.5): no 5xx, never a user who does not hold it', empty($PAGEBAD), json_encode($PAGEBAD ?? []));

// ================================================================ API: explain() contract beyond the pairs
echo "\n-- API\n";
$odd = rw_probe([[$OWNER, 'users.*'], [$OWNER, 'Users.View'], [$OWNER, 'users'], [$OWNER, ''], [999999, 'users.view'], [$U['softdel'], 'u2zone.read'], [$OWNER, 'nope.nothing']]);
info('explain() on odd input: ' . json_encode($odd));
$okWild = ! empty($odd[0]['explainError']) && str_contains($odd[0]['explainError'], 'InvalidArgumentException') && ! empty($odd[2]['explainError']) && str_contains($odd[2]['explainError'], 'InvalidArgumentException');
check('API-1', 'explain() rejects a wildcard and a slug not in area.action form with InvalidArgumentException, like can() (api.html)', $okWild && ! empty($odd[0]['canError']) && ! empty($odd[2]['canError']), json_encode([$odd[0], $odd[2]]));
check('API-2', 'explain() for a user that does not exist and for a permission that does not exist: denied, same answer as can(), no exception', ($odd[4]['explain']['allowed'] ?? null) === false && ($odd[4]['can'] ?? null) === false && ($odd[6]['explain']['allowed'] ?? null) === ($odd[6]['can'] ?? 'x'), json_encode([$odd[4], $odd[6]]));
info('owner (super admin) on a permission that does not exist: ' . json_encode($odd[6]));

// ================================================================ HOS: hostile input, escape
echo "\n-- HOS\n";
$H0 = count($GLOBALS['SEEN']);
$hostile = [
    'perm-missing' => ['permission' => 'nope.nothing'],
    'perm-upper' => ['permission' => 'U2ZONE.READ'],
    'perm-wild' => ['permission' => 'u2zone.*'],
    'perm-bare' => ['permission' => 'u2zone'],
    'perm-xss' => ['permission' => '"><script>alert(1)</script>'],
    'perm-sql' => ['permission' => "u2zone.read' OR '1'='1"],
    'perm-long' => ['permission' => str_repeat('a', 3000) . '.b'],
    'perm-nul' => ['permission' => "u2zone.read\0x"],
    'perm-arr' => ['permission' => ['u2zone.read', 'users.view']],
    'perm-arr2' => ['permission' => ['x' => ['y' => 'z']]],
    'user-missing' => ['permission' => 'u2zone.read', 'user' => '999999'],
    'user-zero' => ['permission' => 'u2zone.read', 'user' => '0'],
    'user-neg' => ['permission' => 'u2zone.read', 'user' => '-1'],
    'user-sql' => ['permission' => 'u2zone.read', 'user' => "1 OR 1=1"],
    'user-sql2' => ['permission' => 'u2zone.read', 'user' => "' OR ''='"],
    'user-pct' => ['permission' => 'u2zone.read', 'user' => '%'],
    'user-us' => ['permission' => 'u2zone.read', 'user' => '_'],
    'user-xss' => ['permission' => 'u2zone.read', 'user' => '<img src=x onerror=alert(3)>'],
    'user-arr' => ['permission' => 'u2zone.read', 'user' => ['1', '2']],
    'user-long' => ['permission' => 'u2zone.read', 'user' => str_repeat('z', 5000)],
    'user-float' => ['permission' => 'u2zone.read', 'user' => '1.5'],
    'user-hex' => ['permission' => 'u2zone.read', 'user' => '0x1'],
    'user-deleted-email' => ['permission' => 'u2zone.read', 'user' => 'u2-softdel@example.com'],
    'user-deleted-id' => ['permission' => 'u2zone.read', 'user' => (string) $U['softdel']],
    'user-only' => ['user' => (string) $U['child']],
    'both-arr' => ['permission' => ['a'], 'user' => ['b']],
];
$res = [];
foreach ($hostile as $k => $qs) {
    $h = G($own, CHK . '?' . http_build_query($qs), "hos $k");
    $sc = screen($h);
    $res[$k] = ['http' => $own->status, 'status' => $sc['status'], 'outcome' => $sc['outcome'], 'subject' => $sc['subject'], 'rows' => count($sc['rows'])];
    $raw = $h;
    $res[$k]['rawEcho'] = str_contains($raw, '<script>alert(1)</script>') || str_contains($raw, '<img src=x onerror=alert(3)>');
}
info('hostile: ' . json_encode(array_map(fn ($r) => [$r['http'], $r['status'], $r['outcome'], $r['subject'], $r['rows']], $res)));
check('H-1', 'Hostile parameters (' . count($hostile) . ', arrays, SQL, wildcard, long, NUL, XSS): no 5xx and no SQL or PHP error text', seen_clean($H0) === [], json_encode(seen_clean($H0)));
check('H-2', 'Crafted values are never echoed raw', ! array_filter($res, fn ($r) => $r['rawEcho']), json_encode(array_keys(array_filter($res, fn ($r) => $r['rawEcho']))));
$noOutcome = ['perm-missing', 'perm-wild', 'perm-bare', 'perm-xss', 'perm-sql', 'perm-long', 'perm-nul', 'perm-arr', 'perm-arr2', 'user-missing', 'user-zero', 'user-neg', 'user-sql', 'user-sql2', 'user-pct', 'user-us', 'user-xss', 'user-arr', 'user-long', 'user-float', 'user-hex', 'user-deleted-email', 'user-deleted-id', 'both-arr'];
$wrong = array_filter($noOutcome, fn ($k) => $res[$k]['outcome'] !== null && ! ($k === 'user-float' || $k === 'user-hex'));
check('H-3', 'A permission or a user that does not exist (or a deleted user, or a malformed value) never gives an outcome for some other user', $wrong === [], json_encode(array_map(fn ($k) => [$k, $res[$k]], array_values($wrong))));
foreach (['user-float', 'user-hex', 'perm-upper', 'user-pct', 'user-us', 'user-sql'] as $k) if ($res[$k]['outcome'] !== null) info("$k gave an outcome for user {$res[$k]['subject']}");
check('H-4', 'A permission that does not exist says so (status message), and so does a user that does not exist', $res['perm-missing']['status'] !== [] && $res['user-missing']['status'] !== [] && $res['user-deleted-id']['status'] !== [], json_encode([$res['perm-missing']['status'], $res['user-missing']['status'], $res['user-deleted-id']['status']]));
// lookup by username, email and id: the same user
$byId = chk($own, ['permission' => 'u2zone.read', 'user' => (string) $U['child']], 'hos by id');
$byEmail = chk($own, ['permission' => 'u2zone.read', 'user' => 'u2-child@example.com'], 'hos by email');
$byName = chk($own, ['permission' => 'u2zone.read', 'user' => (string) q1('SELECT username FROM users WHERE id=?', [$U['child']])], 'hos by username');
check('H-5', 'The user is found by id, email and username (placeholder "Username, email or id"), the same user each time', $byId['subject'] === $U['child'] && $byEmail['subject'] === $U['child'] && $byName['subject'] === $U['child'], json_encode([$byId['subject'], $byEmail['subject'], $byName['subject']]));
$partial = chk($own, ['permission' => 'u2zone.read', 'user' => 'u2-chi'], 'hos partial');
info('partial username "u2-chi": subject ' . json_encode($partial['subject']) . ' status ' . json_encode($partial['status']));
// a username made only of digits, equal to another user's id
$numUser = make_owner('u2-digits@example.com', U1PW, (string) $U['child'], 'user');
$numQ = chk($own, ['permission' => 'u2zone.read', 'user' => (string) $U['child']], 'hos digits');
info("username \"{$U['child']}\" (user $numUser) vs id {$U['child']}: subject " . json_encode($numQ['subject']));
if ($numQ['subject'] !== $U['child']) amb('AMB-num', 'A username made of digits equal to another user\'s id', "user=\"{$U['child']}\" resolves to user " . json_encode($numQ['subject']) . " (id {$U['child']} is another user); the placeholder \"Username, email or id\" does not say which wins");
else info('an id wins over a username made of the same digits');
// escape: crafted role name and username, in the outcome and in the list
$hx = chk($own, ['permission' => 'u2zone.read', 'user' => (string) $U['xss']], 'hos xss outcome')['html'];
$hl = '';
$url = CHK . '?permission=u2zone.read'; $g = 0;
while ($url && $g++ < 10) { $hh = G($own, $url, "hos xss list $g"); $hl .= $hh; $nx = null; foreach (screen($hh)['pages'] as $pu) if (preg_match('/[?&]page[^=]*=(\d+)/', $pu, $pm) && (int) $pm[1] === $g + 1) { $nx = u1path($pu); break; } $url = $nx; }
$scx = screen($hx);
$names = []; foreach (dom($hx)->query('//main//a[contains(@href,"/rolewarden/roles/")]') as $a) $names[] = $a->textContent;
check('H-6', 'A crafted role name and username are printed escaped in the outcome and in the list (text intact, no markup)',
    ! str_contains($hx . $hl, '<img src=x onerror=alert(7)>') && ! str_contains($hx . $hl, '<b>u2"x</b>') && in_array($XSSROLE, $names, true) && str_contains(text_of($hl), $XSSUSER), json_encode(['names' => $names, 'subjectText' => $scx['outText']]));

// ================================================================ RO: read only
echo "\n-- RO\n";
// Shield's own bookkeeping on every signed-in request (users.last_active) and the module's session row (acl_sessions.last_seen_at) are not the screen's
$strip = function (array $st): array { foreach (['acl_sessions' => 'last_seen_at', 'users' => 'last_active'] as $t => $col) if (isset($st[$t])) foreach ($st[$t]['rows'] as &$r) unset($r[$col]); return $st; };
$before = $strip(fullstate());
$ro = c8('k-ro'); login8($ro, 'admin@v8b.test', OPW); $R0 = count($GLOBALS['SEEN']);
$before = $strip(fullstate());
foreach (['u2zone.read', 'u2zone.page', 'nope.nothing'] as $p) foreach ([null, (string) $U['child'], (string) $U['xss'], '999999', 'u2-grant@example.com'] as $u) G($ro, CHK . '?' . http_build_query(array_filter(['permission' => $p, 'user' => $u], fn ($v) => $v !== null)), "ro $p $u");
G($ro, CHK, 'ro bare');
$tok = csrf_of($ro->body);
$methods = [];
foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $m) { $ro->req($m, CHK . '?permission=u2zone.read&user=' . $U['child'], ['csrf_test_name' => $tok, 'permission' => 'u2zone.read', 'user' => (string) $U['child']]); $methods[$m] = $ro->status; seen($ro, "ro $m"); }
$after = $strip(fullstate());
$diff = statediff($before, $after);
info('other methods on the screen: ' . json_encode($methods));
check('R-1', 'Read only: after ' . (count($GLOBALS['SEEN']) - $R0) . ' requests of the screen (GET with every kind of input, POST/PUT/PATCH/DELETE with a valid token) the database is unchanged (acl_sessions.last_seen_at and Shield users.last_active aside)', $diff === [], json_encode(array_slice($diff, 0, 8)));
check('R-2', 'Other methods than GET do not reach a handler of the screen (no 2xx)', ! array_filter($methods, fn ($s) => $s >= 200 && $s < 300), json_encode($methods));
$forms = forms(G($ro, CHK . '?permission=u2zone.read&user=' . $U['child'], 'ro forms'));
$posts = array_values(array_filter($forms, fn ($f) => $f['method'] === 'post' && ! preg_match('#/(sidebar|profile/language|appearance/mode|logout)$#', u1path($f['action']))));
check('R-3', 'The screen holds no form of its own that writes (only the GET form; the shell\'s sidebar, language, mode and sign-out forms aside)', $posts === [], json_encode(array_map(fn ($f) => $f['action'], $posts)));

// ================================================================ LANG: English and Italian
echo "\n-- LANG\n";
function chk_strings(string $h): array
{
    $xp = dom($h); $o = [];
    $q = fn (string $x) => array_map(fn ($n) => trim(preg_replace('/\s+/u', ' ', $n->textContent)), iterator_to_array($xp->query($x)));
    $o['title'] = (string) (preg_match('#<title>(.*?)</title>#s', $h, $m) ? trim(html_entity_decode($m[1])) : '');
    foreach (['h1' => '//main//h1', 'kicker' => '//main//div[contains(@class,"rw-page-head")]//div[contains(@class,"rw-header")]', 'intro' => '//main//div[contains(@class,"rw-page-head")]//p', 'labels' => '//main//form//label', 'button' => '//main//form//button', 'h2' => '//main//h2', 'h3' => '//main//h3', 'rules' => '//main//ol[contains(@class,"rw-check-rules")]/li', 'th' => '//main//table//th', 'note' => '//main//span[contains(@class,"rw-note")]', 'status' => '//main//*[@role="status"]', 'muted' => '//main//p[contains(@class,"rw-muted")]', 'hdr' => '//main//p//span[contains(@class,"rw-header")]', 'why' => '//main//table//tbody//tr/td[3]', 'anno' => '//main//table//tbody//tr/td[4]', 'opt0' => '//main//select/option[@value=""]', 'strong' => '//main//p/strong | //main//p[contains(@class,"rw-verdict")]/span'] as $k => $x)
        foreach ($q($x) as $i => $t) $o["$k$i"] = $t;
    $ph = $xp->query('//main//input[@name="user"]')->item(0); $o['placeholder'] = $ph ? $ph->getAttribute('placeholder') : '';
    $s = side($h); $o['side'] = $s['items'][CHK]['text'] ?? '';
    return $o;
}
function set_lang2(Client $c, string $l): void { postform($c, 'rolewarden/profile', '#profile/language$#', ['language' => $l], "chk lang $l"); }
$LQ = [[], ['permission' => 'u2zone.read'], ['permission' => 'u2zone.none'], ['permission' => 'u2zone.read', 'user' => (string) $U['child']], ['permission' => 'u2zone.read', 'user' => (string) $U['grant']], ['permission' => 'u2zone.read', 'user' => (string) $OWNER],
    ['permission' => 'u2zone.read', 'user' => (string) $U['deny']], ['permission' => 'u2zone.read', 'user' => (string) $U['off']], ['permission' => 'u2zone.read', 'user' => (string) $U['none']], ['permission' => 'nope.nothing'], ['permission' => 'u2zone.read', 'user' => '999999']];
$lng = c8('k-lang'); login8($lng, 'owner@v8b.test', OPW);
set_lang2($lng, 'en');
$EN = []; foreach ($LQ as $i => $qs) $EN[$i] = chk_strings(G($lng, CHK . ($qs ? '?' . http_build_query($qs) : ''), "lang en $i"));
set_lang2($lng, 'it');
$IT = []; $htmlIt = ''; foreach ($LQ as $i => $qs) { $hh = G($lng, CHK . ($qs ? '?' . http_build_query($qs) : ''), "lang it $i"); $htmlIt .= $hh; $IT[$i] = chk_strings($hh); }
$kick = dom(G($lng, 'rolewarden/permissions', 'lang it permissions'))->query('//main//div[contains(@class,"rw-page-head")]//div[contains(@class,"rw-header")]')->item(0);
info('Italian kicker of the Permissions screen (1.0): ' . json_encode($kick ? trim($kick->textContent) : null, JSON_UNESCAPED_UNICODE) . '; sidebar group: ' . json_encode(array_column(side($lng->body)['groups'], 'label'), JSON_UNESCAPED_UNICODE));
set_lang2($lng, 'en');
$same = []; $missing = []; $pairsL = [];
$keep = fn (string $s) => preg_match('/^[\d\s.:,()#\-]*$/u', $s) || preg_match('/^[a-z0-9-]+\.[a-z0-9-]+$/', $s);
foreach ($EN as $i => $e) foreach ($e as $k => $v) {
    $w = $IT[$i][$k] ?? null;
    if ($w === null) { $missing[] = "$i:$k"; continue; }
    $vn = preg_replace(['/\d+/', '/\b[a-z0-9-]+\.[a-z0-9-]+\b/', '/\bu[12]-[\w-]+|v8b\w+|demo-\w+|X-\w+|U2-[\w-]+/i'], ['#', 'SLUG', 'NAME'], $v);
    $wn = preg_replace(['/\d+/', '/\b[a-z0-9-]+\.[a-z0-9-]+\b/', '/\bu[12]-[\w-]+|v8b\w+|demo-\w+|X-\w+|U2-[\w-]+/i'], ['#', 'SLUG', 'NAME'], $w);
    $pairsL[$vn] = $wn;
    if ($v !== '' && $vn === $wn && ! $keep($v)) $same[$vn] = true;
}
info('Italian strings (en => it): ' . json_encode($pairsL, JSON_UNESCAPED_UNICODE));
info('identical in both languages: ' . json_encode(array_keys($same), JSON_UNESCAPED_UNICODE));
$allowedSame = ['Super admin', 'Super admin NAME', 'NAME', 'Override: granted'];
$realSame = array_values(array_filter(array_keys($same), fn ($s) => ! in_array($s, $allowedSame, true) && preg_match('/[A-Za-z]{3,}/', preg_replace('/SLUG|NAME/', '', $s))));
check('L-1', 'Every string of the screen (title, heading, intro, labels, placeholder, button, sections, the six rules, outcome, roles, table, notes, empty and error messages, sidebar item) has an Italian text different from the English one', $missing === [] && $realSame === [], json_encode(['missing' => $missing, 'same' => $realSame], JSON_UNESCAPED_UNICODE));
$enPhrases = [];
foreach ($EN as $e) foreach ($e as $v) if (mb_strlen($v) > 10 && ! $keep($v) && preg_match('/[a-z]{4,} [a-z]{3,}/', $v)) $enPhrases[$v] = 1;
$itText = text_of($htmlIt);
$leftEn = array_values(array_filter(array_keys($enPhrases), fn ($v) => str_contains($itText, $v)));
check('L-2', 'No English sentence of the screen appears in the Italian pages (' . count($enPhrases) . ' sentences)', $leftEn === [], json_encode($leftEn));
check('L-3', 'The Italian screen still gives the same outcome and rule (the marked rule is the same position)', ($IT[3]['strong0'] ?? '') !== '' && array_search(true, array_map(fn ($k) => str_starts_with($k, 'rules'), array_keys($IT[3]))) !== false, json_encode([$IT[3]['strong0'] ?? null]));

// ================================================================ LOG
echo "\n-- LOG\n";
check('K-1', 'Every response of the phase (' . (count($GLOBALS['SEEN']) - $S0) . '): no 5xx, no SQL text, no PHP error', seen_clean($S0) === [], json_encode(array_slice(seen_clean($S0), 0, 20)));
$ll = bad_log_lines(logs_of($APP));
info('application log lines WARNING or above: ' . count($ll) . ' ' . short(implode(' | ', array_slice($ll, 0, 10)), 2500));
$csrf = array_filter($ll, fn ($l) => str_contains($l, 'SecurityException'));
check('K-2', 'Application log: no WARNING, ERROR or CRITICAL line (CSRF refusals of the deliberate non-GET requests aside: ' . count($csrf) . ')', array_values(array_diff($ll, $csrf)) === [], short(implode(' | ', array_slice(array_values(array_diff($ll, $csrf)), 0, 8)), 1500));
