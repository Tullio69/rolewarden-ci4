<?php
/**
 * U3 phase SRCH (Collaudatore ad Hoc, Claude): the command search of 1.1 (tappa U3) over HTTP, in black box.
 * Sources of the assertions: docs/SPEC.md (v1.1 "Ricerca comandi", "Elementi condizionati dai permessi", precedence),
 * docs/BRIEF-v1.1.md (U3, definition of done), CHANGELOG (Unreleased: GET /rolewarden/search, the overlay asks for the
 * results alone), documentation/configuration.html ("Permissions the panel uses"), the design system (CommandSearch,
 * Sidebar) and the HTML the server sends. Reference: every address the search returns is fetched as the same user
 * (it must open: 200, no 403, no refusal redirect); actions also need the permission the documentation names for
 * them; permission identifiers need the Check a permission screen. can()/explain() come from a spark command of this
 * test written into the throwaway copy ($APP/app/Commands/U3Explain.php).
 * Sections: REACH (never out of reach, complete), FRAG (the overlay's answer = the page), ID (identifiers, who can do
 * this), ACT (actions open, never act), RO (no writes, any method), HOS (hostile input, anonymous), LANG, LOG.
 */
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== SRCH: command search over HTTP (U3)\n";
serve8($APP, 'SRCH');
$S0 = count($GLOBALS['SEEN']);
const SRCH = 'rolewarden/search';
const XHR = ['X-Requested-With: XMLHttpRequest'];
// documentation/configuration.html: the permission each action needs beyond reaching its screen
const ACTPERM = ['rolewarden/users/create' => 'users.create', 'rolewarden/users/import' => 'users.import', 'rolewarden/roles/create' => 'roles.create'];

// ---------------------------------------------------------------- spark probe: can() and explain()
@mkdir("$APP/app/Commands", 0777, true);
file_put_contents("$APP/app/Commands/U3Explain.php", <<<'PHP'
<?php
// Test-only command of the U3 black-box run (lives only in the throwaway copy): can() and explain() for pairs.
namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;

class U3Explain extends BaseCommand
{
    protected $group = 'u3test';
    protected $name = 'u3:explain';
    protected $description = 'U3 test probe';

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
function rw_probe3(array $pairs): array
{
    global $APP, $WORK;
    static $n = 0; $n++;
    $in = "$WORK/u3probe-in-$n.json"; $out = "$WORK/u3probe-out-$n.json";
    file_put_contents($in, json_encode(array_values($pairs)));
    sp8($APP, ['cache:clear']);
    $o = sp8($APP, ['u3:explain', $in, $out]);
    $r = is_file($out) ? json_decode((string) file_get_contents($out), true) : null;
    if (! is_array($r) || count($r) !== count($pairs)) { info('probe failed: ' . short($o, 800)); return array_fill(0, count($pairs), ['probeError' => true]); }
    return $r;
}

// ---------------------------------------------------------------- reading the results (page or the overlay's fragment)
function sres(string $h, bool $page = true): array
{
    $xp = dom($h);
    $root = $page ? '//main' : '';
    $out = [];
    foreach ($xp->query($root . '//a[contains(concat(" ",normalize-space(@class)," ")," rw-cmd-opt ")]') as $i => $a) {
        $g = $a->parentNode; while ($g && ! ($g instanceof DOMElement && str_contains(' ' . $g->getAttribute('class') . ' ', ' rw-cmd-group '))) $g = $g->parentNode;
        $lab = $g ? $xp->query('.//*[contains(@class,"rw-cmd-label")]', $g)->item(0) : null;
        $t = $xp->query('.//*[contains(@class,"rw-cmd-text")]/span', $a)->item(0);
        $sm = $xp->query('.//small', $a)->item(0);
        $note = $xp->query('.//*[contains(@class,"rw-note")]', $a)->item(0);
        $code = $xp->query('.//code', $a)->item(0);
        $out[] = ['i' => $i, 'group' => $lab ? trim(preg_replace('/\s+/u', ' ', $lab->textContent)) : '', 'href' => u1path($a->getAttribute('href')), 'text' => $t ? trim(preg_replace('/\s+/u', ' ', $t->textContent)) : trim($a->textContent),
            'small' => $sm ? trim(preg_replace('/\s+/u', ' ', $sm->textContent)) : null, 'note' => $note ? trim($note->textContent) : null, 'code' => $code ? trim($code->textContent) : null, 'id' => $a->getAttribute('id'), 'method' => $a->getAttribute('data-method')];
    }
    return $out;
}
function ikey(array $r): string { return $r['code'] !== null ? 'perm:' . $r['code'] : $r['group'] . '|' . $r['text']; }
function srch(Client $c, string $q, string $tag, bool $xhr = false): array
{
    $c->req('GET', SRCH . '?' . http_build_query(['q' => $q]), null, $xhr ? XHR : []);
    seen($c, $tag);
    return ['http' => $c->status, 'loc' => $c->location, 'items' => sres($c->body, ! $xhr), 'body' => $c->body];
}
/** Opens an address as the user: [status, location]; cached per client. */
function opens(Client $c, string $path): array
{
    static $cache = [];
    $k = $c->name . ' ' . $path;
    if (! isset($cache[$k])) { G($c, $path, "open $path as {$c->name}"); $cache[$k] = [$c->status, $c->location]; }
    return $cache[$k];
}
function opens_ok(array $o): bool { return $o[0] === 200; }
function slug_of(string $href): ?string { parse_str((string) parse_url($href, PHP_URL_QUERY), $qq); return str_starts_with($href, 'rolewarden/permissions/check') && is_string($qq['permission'] ?? null) ? $qq['permission'] : null; }

// ---------------------------------------------------------------- data of the test
function mkperm3(string $slug): int
{
    $cols = array_column(q('SHOW COLUMNS FROM acl_permissions'), 'Field');
    $now = date('Y-m-d H:i:s');
    $v = array_intersect_key(['slug' => $slug, 'name' => ucfirst(str_replace('.', ' ', $slug)), 'area' => explode('.', $slug)[0], 'description' => 'U3 test permission', 'is_system' => 0, 'created_at' => $now, 'updated_at' => $now], array_flip($cols));
    q('INSERT INTO acl_permissions (`' . implode('`,`', array_keys($v)) . '`) VALUES (' . implode(',', array_fill(0, count($v), '?')) . ')', array_values($v));
    return (int) db()->insert_id;
}
function parent3(int $child, int $parent): void { q('UPDATE acl_roles SET parent_id=? WHERE id=?', [$parent, $child]); }
mkperm3('u3zone.read'); mkperm3('u3zone.write');
$R = [];
$R['uview'] = mkrole('u3-uview', ['users.view']);
$R['uviewov'] = mkrole('u3-uviewov', ['users.view', 'permissions.override']);
$R['ovonly'] = mkrole('u3-ovonly', ['permissions.override']);
$R['ucreate'] = mkrole('u3-ucreate', ['users.create']);
$R['uvcreate'] = mkrole('u3-uvcreate', ['users.view', 'users.create']);
$R['import'] = mkrole('u3-import', ['users.view', 'users.import']);
$R['importonly'] = mkrole('u3-importonly', ['users.import']);
$R['pv'] = mkrole('u3-pv', ['permissions.view']);
$R['rgrand'] = mkrole('u3-rgrand', ['roles.view', 'roles.create', 'permissions.view']);
$R['rparent'] = mkrole('u3-rparent', []); parent3($R['rparent'], $R['rgrand']);
$R['rkid'] = mkrole('u3-rkid', []); parent3($R['rkid'], $R['rparent']);
$R['rcreate'] = mkrole('u3-rcreate', ['roles.create']);
$R['sview'] = mkrole('u3-sview', ['settings.view']);
$R['supd'] = mkrole('u3-supd', ['settings.update']);
$R['act'] = mkrole('u3-act', ['activity.view']);
$R['app'] = mkrole('u3-app', ['appearance.update']);
$R['sess'] = mkrole('u3-sess', ['sessions.view', 'sessions.revoke']);
$R['doomed'] = mkrole('u3-doomed', ['users.view', 'roles.view', 'permissions.view', 'users.create']);
$R['revoke'] = mkrole('u3-revoke', ['users.view', 'permissions.view']);
// who can do this: u3zone.read through grandparent, parent, direct; denied and inactive holders
$R['zgrand'] = mkrole('u3-zgrand', ['u3zone.read']);
$R['zparent'] = mkrole('u3-zparent', []); parent3($R['zparent'], $R['zgrand']);
$R['zkid'] = mkrole('u3-zkid', []); parent3($R['zkid'], $R['zparent']);
$R['zdirect'] = mkrole('u3-zdirect', ['u3zone.read', 'u3zone.write']);
$R['zother'] = mkrole('u3-zother', []);

$A = [];   // accounts that search
$A['owner'] = $OWNER; $A['admin'] = $ADMIN; $A['plain'] = $PLAIN;
$A['norole'] = mkuser('u3-norole@example.com', '');
$A['off'] = mkuser('u3-off@example.com', 'admin', [], 0);
$A['supoff'] = mkuser('u3-supoff@example.com', 'super-admin', [], 0);
$A['supdeny'] = mkuser('u3-supdeny@example.com', 'super-admin', ['users.view' => 0, 'permissions.view' => 0, 'roles.create' => 0, 'users.import' => 0]);
$A['admindeny'] = mkuser('u3-admindeny@example.com', 'admin', ['permissions.override' => 0, 'users.create' => 0, 'settings.view' => 0, 'activity.view' => 0]);
foreach (['uview', 'uviewov', 'ovonly', 'ucreate', 'uvcreate', 'import', 'importonly', 'pv', 'rkid', 'rparent', 'rcreate', 'sview', 'supd', 'act', 'app', 'sess', 'doomed'] as $r) $A[$r] = mkuser("u3-a-$r@example.com", "u3-$r");
$A['grant'] = mkuser('u3-grant@example.com', 'user', ['users.view' => 1, 'roles.create' => 1, 'permissions.view' => 1]);
$A['grantdeny'] = mkuser('u3-grantdeny@example.com', 'u3-uvcreate', ['users.create' => 0, 'permissions.override' => 1]);
$A['two'] = mkuser('u3-two@example.com', 'u3-sview');
q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$A['two'], $R['import']]);
$A['revoke'] = mkuser('u3-revoke@example.com', 'u3-revoke');
$A['lateoff'] = mkuser('u3-lateoff@example.com', 'admin');
$Z = [];
foreach (['zgrand', 'zparent', 'zkid', 'zdirect', 'zother'] as $r) $Z[$r] = mkuser("u3-z-$r@example.com", "u3-$r");
$Z['ztwo'] = mkuser('u3-z-two@example.com', 'u3-zdirect'); q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$Z['ztwo'], $R['zkid']]);
$Z['zgranted'] = mkuser('u3-z-granted@example.com', 'u3-zother', ['u3zone.read' => 1]);
$Z['zdeny1'] = mkuser('u3-z-deny1@example.com', 'u3-zgrand', ['u3zone.read' => 0]);
$Z['zdeny2'] = mkuser('u3-z-deny2@example.com', 'u3-zdirect', ['u3zone.read' => 0]);
$Z['zdenyn'] = mkuser('u3-z-denyn@example.com', 'u3-zother', ['u3zone.read' => 0]);
$Z['zoff'] = mkuser('u3-z-off@example.com', 'u3-zgrand', [], 0);
$Z['zsupdeny'] = mkuser('u3-z-supdeny@example.com', 'super-admin', ['u3zone.read' => 0]);
$Z['zsoftdel'] = mkuser('u3-z-softdel@example.com', 'u3-zdirect');
q('UPDATE users SET deleted_at=NOW() WHERE id=?', [$Z['zsoftdel']]);
$EMAIL = fn (int $id) => (string) q1("SELECT secret FROM auth_identities WHERE type='email_password' AND user_id=?", [$id]);
$PW = fn (int $id) => in_array($id, [$OWNER, $ADMIN, $PLAIN], true) ? OPW : U1PW;

$own = c8('s-owner'); login8($own, 'owner@v8b.test', OPW);
postform($own, "rolewarden/roles/{$R['doomed']}", "#rolewarden/roles/{$R['doomed']}/delete$#", [], 'srch delete doomed role');
info('u3-doomed deleted through the panel: deleted_at=' . json_encode(q1('SELECT deleted_at FROM acl_roles WHERE id=?', [$R['doomed']])));

// ================================================================ REACH: the universe and what each account gets
echo "\n-- REACH\n";
$u0 = srch($own, '', 'reach owner empty');
$UNI = []; foreach ($u0['items'] as $it) $UNI[ikey($it)] = $it;
info('owner (super admin), empty query: ' . count($u0['items']) . ' results: ' . json_encode(array_map(fn ($i) => "{$i['group']}|{$i['text']} -> {$i['href']}" . ($i['note'] !== null ? " ({$i['note']})" : ''), $u0['items']), JSON_UNESCAPED_UNICODE));
$SLUGS = array_column(q('SELECT slug FROM acl_permissions ORDER BY slug'), 'slug');
$ownSide = side(G($own, 'rolewarden/users', 'reach owner sidebar'));
$goto = array_values(array_unique(array_map(fn ($i) => $i['href'], array_filter($u0['items'], fn ($i) => $i['group'] === 'Go to'))));
$missSide = array_values(array_diff(array_keys($ownSide['items']), $goto));
check('RE-0', 'Owner, empty query: every sidebar screen is under "Go to" (' . count($ownSide['items']) . '), with Profile and own sessions; the actions are under "Actions"; no permission identifier until one is typed',
    $missSide === [] && in_array('rolewarden/profile', $goto, true) && in_array('rolewarden/sessions', $goto, true) && array_filter($u0['items'], fn ($i) => $i['group'] === 'Actions') && ! array_filter($u0['items'], fn ($i) => $i['code'] !== null),
    json_encode(['missing' => $missSide, 'groups' => array_values(array_unique(array_column($u0['items'], 'group')))]));
// counts where they help: the same numbers as the sidebar counters
$badCnt = [];
foreach ($u0['items'] as $it) if ($it['note'] !== null && isset($ownSide['items'][$it['href']]) && $it['group'] === 'Go to' && (string) $ownSide['items'][$it['href']]['count'] !== $it['note']) $badCnt[] = "{$it['href']} search {$it['note']} sidebar " . json_encode($ownSide['items'][$it['href']]['count']);
check('RE-cnt', 'Counts shown next to screens equal the sidebar counters (users, roles, permissions)', $badCnt === [] && array_filter($u0['items'], fn ($i) => $i['note'] !== null), json_encode($badCnt));

// queries every account runs: empty, every label of the universe, every permission slug (full), partial identifiers
// full identifiers: one per area of the seed and the test's own (every slug for the owner is RE-ids below)
$IDS = array_values(array_intersect($SLUGS, ['users.delete', 'users.import', 'roles.create', 'permissions.view', 'permissions.override', 'settings.update', 'sessions.revoke', 'activity.view', 'appearance.update', 'security.alerts', 'u3zone.read']));
$QUERIES = array_merge([''], array_values(array_unique(array_map(fn ($i) => $i['text'], $UNI))), $IDS, ['users.del', 'roles.a', 'u3zone', 'users', 'role', 'e', 'perm', 'check', 'settings']);
info('queries per account: ' . count($QUERIES));
$exp_visible = function (Client $c, int $uid, array $it): bool {
    $o = opens($c, $it['href']);
    if (! opens_ok($o)) return false;
    if ($it['code'] !== null || slug_of($it['href']) !== null) return holds($uid, 'permissions.view');
    if (isset(NAVMAP[$it['href']]) && ! holds($uid, NAVMAP[$it['href']])) return false;   // a screen the model says the user may not see
    if ($it['group'] === 'Actions') {
        if (isset(ACTPERM[$it['href']])) return holds($uid, ACTPERM[$it['href']]);
        if (stripos($it['text'], 'override') !== false) return holds($uid, 'permissions.override');
    }
    return true;
};
$REACH = []; $OUT = []; $MISS = []; $LOGIN = []; $XDIFF = [];
foreach ($A as $name => $id) {
    $c = c8("s-$name");
    $ok = $name === 'owner' ? (bool) ($c = $own) : login8($c, $EMAIL($id), $PW($id));
    $LOGIN[$name] = $ok;
    if (! $ok) {
        $r = srch($c, 'users', "reach $name anon");
        $REACH[$name] = ['login' => false, 'http' => $r['http'], 'loc' => $r['loc'], 'items' => count($r['items'])];
        if ($r['items']) $OUT[] = "$name (not signed in): " . count($r['items']) . ' results';
        continue;
    }
    if ($name === 'lateoff') {   // deactivated while signed in (direct write); the panel's own deactivation is probed apart
        q('UPDATE users SET active=0 WHERE id=?', [$id]);
        G($c, 'rolewarden/users', 'lateoff users before cache:clear'); info("lateoff: users screen right after the direct write: {$c->status}");
        sp8($APP, ['cache:clear']);
    }
    $got = []; $n = 0;
    foreach ($QUERIES as $qq) {
        $r = srch($c, $qq, "reach $name q=$qq");
        if ($r['http'] !== 200) { $OUT[] = "$name q=" . json_encode($qq) . " http {$r['http']} {$r['loc']}"; continue; }
        foreach ($r['items'] as $it) {
            $n++;
            $got[ikey($it)] = $it;
            $o = opens($c, $it['href']);
            if (! opens_ok($o)) $OUT[] = "$name q=" . json_encode($qq) . ": {$it['group']}|{$it['text']} -> {$it['href']} opens " . json_encode($o);
            elseif (! $exp_visible($c, $id, $it)) $OUT[] = "$name q=" . json_encode($qq) . ": {$it['group']}|{$it['text']} -> {$it['href']}: screen opens but the action/identifier is not the user's (" . json_encode(ACTPERM[$it['href']] ?? ($it['code'] ? 'permissions.view' : 'permissions.override')) . ')';
        }
        // completeness: a query equal to a label (or a slug) brings that item when it is within reach
        $keys = array_map('ikey', $r['items']);
        foreach ($UNI as $k => $it) if ($it['text'] === $qq && ! in_array($k, $keys, true) && $exp_visible($c, $id, $it)) $MISS[] = "$name q=" . json_encode($qq) . ": $k missing";
        if (in_array($qq, $SLUGS, true)) {
            $want = holds($id, 'permissions.view') && opens_ok(opens($c, 'rolewarden/permissions/check?' . http_build_query(['permission' => $qq])));
            $has = (bool) array_filter($r['items'], fn ($x) => $x['code'] === $qq);
            if ($want && ! $has) $MISS[] = "$name q=$qq: identifier missing";
        }
        // the overlay's answer (results alone) carries the same results for a few queries
        if (in_array($qq, ['', 'users', 'e', 'users.del', 'role'], true)) {
            $x = srch($c, $qq, "reach $name xhr q=$qq", true);
            $a1 = array_map('ikey', $r['items']); $a2 = array_map('ikey', $x['items']);
            if ($a1 !== $a2 || $x['http'] !== 200 || stripos($x['body'], '<html') !== false) $XDIFF[] = "$name q=" . json_encode($qq) . ' page ' . count($a1) . ' fragment ' . count($a2) . " http {$x['http']}" . (stripos($x['body'], '<html') !== false ? ' (whole page)' : '');
        }
    }
    // the expected set of screens and actions for this account, from the universe
    $expect = array_keys(array_filter($UNI, fn ($it) => $exp_visible($c, $id, $it)));
    $gotSA = array_keys(array_filter($got, fn ($it) => $it['code'] === null));
    $REACH[$name] = ['login' => true, 'results' => $n, 'distinct' => count($got), 'screens+actions' => count($gotSA), 'expected' => count($expect), 'perms' => count(array_filter($got, fn ($it) => $it['code'] !== null)), 'pv' => holds($id, 'permissions.view')];
    $mx = array_values(array_diff($expect, $gotSA));
    if ($mx) $MISS[] = "$name: within reach but never returned: " . json_encode($mx);
    if ($name === 'owner') $OWNGOT = $got;
}
info('accounts: ' . json_encode($REACH));
check('RE-1', 'Never out of reach: for ' . count($A) . ' accounts (super admin, admin, no permissions, no role, deactivated, super admin and admin with denials, single permissions, inheritance, positive and negative overrides, two roles, deleted role, deactivated while signed in) and ' . count($QUERIES) . ' queries each, every address returned opens for that user (200, no 403, no refusal redirect), and actions and identifiers are the user\'s',
    $OUT === [], json_encode(array_slice($OUT, 0, 14), JSON_UNESCAPED_UNICODE));
check('RE-2', 'Complete: what the user can reach and matches the query is returned (every screen and action within reach, each label query brings its item, each full identifier with Check a permission)', $MISS === [], json_encode(array_slice($MISS, 0, 14), JSON_UNESCAPED_UNICODE));
check('RE-3', 'The overlay\'s answer (X-Requested-With, results alone) holds exactly the results of the page, in the same order, and not a whole page', $XDIFF === [], json_encode(array_slice($XDIFF, 0, 10)));
$noPv = array_filter($REACH, fn ($r) => ($r['login'] ?? false) && empty($r['pv']) && ($r['perms'] ?? 0) > 0);
check('RE-4', 'Without permissions.view no permission identifier is ever returned (it would open a screen the user may not see)', $noPv === [], json_encode(array_keys($noPv)));
check('RE-off', 'Deactivated accounts (admin, super admin, admin deactivated while signed in): cannot sign in, or get no identifier (and, by RE-1, nothing out of reach)', (! $LOGIN['off'] || ($REACH['off']['perms'] ?? 1) === 0) && (! $LOGIN['supoff'] || ($REACH['supoff']['perms'] ?? 1) === 0) && ($REACH['lateoff']['perms'] ?? 1) === 0, json_encode(['off' => $REACH['off'], 'supoff' => $REACH['supoff'], 'lateoff' => $REACH['lateoff']]));
// revocation takes effect on the next request (filtered per request on the server)
$rv = c8('s-revoke2'); login8($rv, $EMAIL($A['revoke']), U1PW);
$b1 = array_map('ikey', srch($rv, 'users', 'revoke before')['items']);
q('DELETE FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$R['revoke'], perm_id('users.view')]);
q('DELETE FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$R['revoke'], perm_id('permissions.view')]);
$b2s = array_map('ikey', srch($rv, 'users', 'revoke after, no cache:clear')['items']);
G($rv, 'rolewarden/users', 'revoke users screen'); $rvScreen = $rv->status;
sp8($APP, ['cache:clear']);   // a direct write skips the panel's own cache invalidation
$b2 = array_map('ikey', srch($rv, 'users', 'revoke after')['items']);
info('revoke by direct write: before cache:clear search ' . json_encode($b2s) . " users screen $rvScreen");
info("revoke: before " . json_encode($b1) . ' after ' . json_encode($b2));
check('RE-rev', 'A permission revoked while the user is signed in leaves the results on the next request (Users and the users.* identifiers gone)', in_array('Go to|Users', $b1, true) && ! in_array('Go to|Users', $b2, true) && ! preg_grep('/^perm:/', $b2), json_encode([$b1, $b2]));
$pc = c8('s-plain2'); login8($pc, 'plain@v8b.test', OPW);
$pr = srch($pc, '', 'plain empty');
info('plain user (no permissions), empty query: ' . json_encode(array_map(fn ($i) => "{$i['group']}|{$i['text']} -> {$i['href']}", $pr['items'])));
check('RE-5', 'A user with no permission gets only what needs no permission (own profile, own sessions, own password) and every one of them opens', $pr['http'] === 200 && ! array_filter($pr['items'], fn ($i) => ! in_array($i['href'], ['rolewarden/profile', 'rolewarden/sessions'], true)), json_encode($pr['items']));

// ================================================================ ID: identifiers and "who can do this"
echo "\n-- ID\n";
$noTop = [];
foreach ($SLUGS as $sl) { $t = srch($own, $sl, "id all $sl")['items'][0] ?? null; if (($t['code'] ?? null) !== $sl || $t['href'] !== 'rolewarden/permissions/check?' . http_build_query(['permission' => $sl])) $noTop[] = "$sl -> " . json_encode($t ? [$t['group'], $t['code'], $t['href']] : null); }
check('ID-all', 'Owner: every permission identifier (' . count($SLUGS) . ') typed in full is the top result and leads to its check', $noTop === [], json_encode(array_slice($noTop, 0, 10)));
$idq = ['users.delete' => 'users.delete', 'users.del' => 'users.delete', 'roles.a' => 'roles.assign', 'u3zone.read' => 'u3zone.read', 'u3zone.re' => 'u3zone.read', 'permissions.vi' => 'permissions.view', 'USERS.DELETE' => 'users.delete'];
$IDR = [];
foreach ($idq as $qq => $slug) {
    $r = srch($own, $qq, "id $qq");
    $top = $r['items'][0] ?? null;
    $IDR[$qq] = ['top' => $top ? [$top['group'], $top['code'], $top['href'], $top['small']] : null, 'n' => count($r['items'])];
    if ($qq === 'USERS.DELETE') continue;
    check("ID-$qq", "Typed \"$qq\": the top result is the permission $slug under \"Permission\", answers who can do this, and opens Check a permission for it",
        $top && $top['group'] === 'Permission' && $top['code'] === $slug && $top['href'] === 'rolewarden/permissions/check?' . http_build_query(['permission' => $slug]) && (string) $top['small'] !== '' && str_contains($top['text'], 'who can do this'), json_encode($top, JSON_UNESCAPED_UNICODE));
}
info('identifier queries (top result, results): ' . json_encode($IDR, JSON_UNESCAPED_UNICODE));
// the numbers of the answer: holders as can() says, as the Check screen counts
$all = array_map('intval', array_column(q('SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id'), 'id'));
$WHO = [];
foreach (['users.delete', 'roles.assign', 'u3zone.read', 'u3zone.write', 'permissions.view', 'settings.update'] as $slug) {
    $pairs = array_map(fn ($u) => [$u, $slug], $all);
    $pb = rw_probe3($pairs);
    $hold = []; $deny = []; $viaFirst = []; $viaLast = []; $viaAll = []; $rules = [];
    foreach ($pairs as $k => [$u]) {
        $ex = $pb[$k]['explain'] ?? [];
        $rules[$ex['rule'] ?? '?'] = ($rules[$ex['rule'] ?? '?'] ?? 0) + 1;
        if (($pb[$k]['can'] ?? null) === true) { $hold[] = $u; if (($ex['rule'] ?? '') === 'role') foreach ($ex['via'] ?? [] as $ch) { $viaFirst[$ch[0]] = 1; $viaLast[end($ch)] = 1; foreach ($ch as $x) $viaAll[$x] = 1; } }
        if (($ex['rule'] ?? '') === 'override_denied') $deny[] = $u;
    }
    $direct = (int) q1('SELECT COUNT(DISTINCT rp.role_id) FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id JOIN acl_roles r ON r.id=rp.role_id WHERE p.slug=? AND r.deleted_at IS NULL', [$slug]);
    $r = srch($own, $slug, "who $slug");
    $top = $r['items'][0] ?? null;
    $line = (string) ($top['small'] ?? '');
    preg_match('/(\d+)\s+users?/', $line, $mu); preg_match('/(\d+)\s+roles?/', $line, $mr); preg_match('/(\d+)\s+denied/', $line, $md);
    $chk = G($own, 'rolewarden/permissions/check?' . http_build_query(['permission' => $slug]), "who check $slug");
    $cx = dom($chk)->query('//main//div[contains(@class,"rw-section-title")]//span[contains(@class,"rw-fig-sm")]')->item(0);
    $WHO[$slug] = ['line' => $line, 'users' => isset($mu[1]) ? (int) $mu[1] : null, 'roles' => isset($mr[1]) ? (int) $mr[1] : null, 'denied' => isset($md[1]) ? (int) $md[1] : 0,
        'can' => count($hold), 'checkScreen' => $cx ? (int) trim($cx->textContent) : null, 'deniedOverride' => count($deny), 'rolesDirect' => $direct, 'viaFirst' => count($viaFirst), 'viaLast' => count($viaLast), 'viaAll' => count($viaAll), 'rules' => $rules];
}
info('who can do this (line vs can()/explain()/Check screen): ' . json_encode($WHO, JSON_UNESCAPED_UNICODE));
$badN = array_filter($WHO, fn ($w) => $w['users'] !== $w['can'] || $w['users'] !== $w['checkScreen']);
check('ID-N', 'The answer\'s number of users equals the holders by can() and the count of the Check a permission screen, for 6 permissions (super admin, override granted, role, grandparent, two roles; denied, deactivated and deleted users left out)', $badN === [], json_encode($badN, JSON_UNESCAPED_UNICODE));
$badD = array_filter($WHO, fn ($w) => $w['denied'] !== $w['deniedOverride']);
check('ID-D', 'The "denied by override" number equals the users whose check explain() decides by override_denied (0 when none and then not said)', $badD === [], json_encode($badD, JSON_UNESCAPED_UNICODE));
$defs = ['viaLast' => 'roles granting it directly, through which holders hold it', 'viaFirst' => 'roles assigned to holders that bring it', 'viaAll' => 'every role on the chains', 'rolesDirect' => 'roles granting it in the database'];
$fit = []; foreach ($defs as $k => $d) if (! array_filter($WHO, fn ($w) => $w['roles'] !== null && $w['roles'] !== $w[$k])) $fit[] = $k;
info('the roles number fits the definitions: ' . json_encode($fit));
check('ID-R', 'The answer\'s number of roles agrees with explain() under one definition for every permission (' . implode(', ', $fit) . ')', $fit !== [], json_encode($WHO));
// follow the top result: Check a permission opens on that permission
$top = srch($own, 'u3zone.re', 'id follow')['items'][0] ?? null;
$hc = $top ? G($own, $top['href'], 'id follow open') : '';
$selv = ($s = dom($hc)->query('//select[@name="permission"]//option[@selected]')->item(0)) ? $s->getAttribute('value') : null;
check('ID-open', 'Opening the identifier lands on Check a permission with that permission chosen (200)', $own->status === 200 && $selv === 'u3zone.read', json_encode([$own->status, $selv]));
// a user with permissions.view through inheritance gets identifiers; one without, none (also typed in full)
$kid = c8('s-kid'); login8($kid, $EMAIL($A['rkid']), U1PW);
$uv = c8('s-uv'); login8($uv, $EMAIL($A['uview']), U1PW);
$k1 = srch($kid, 'users.delete', 'id kid'); $k2 = srch($uv, 'users.delete', 'id uview');
check('ID-pv', 'permissions.view through grandparent: the identifier is the top result; with users.view only: no identifier, nothing pointing at Check a permission', ($k1['items'][0]['code'] ?? null) === 'users.delete' && ! array_filter($k2['items'], fn ($i) => $i['code'] !== null || str_starts_with($i['href'], 'rolewarden/permissions')), json_encode([$k1['items'][0] ?? null, $k2['items']]));

// ================================================================ ACT: actions open their screen and never act
echo "\n-- ACT\n";
$acts = array_values(array_filter($OWNGOT ?? [], fn ($i) => $i['group'] === 'Actions'));
$strip = function (array $st): array { foreach (['acl_sessions' => 'last_seen_at', 'users' => 'last_active'] as $t => $col) if (isset($st[$t])) foreach ($st[$t]['rows'] as &$r) unset($r[$col]); return $st; };
$before = $strip(fullstate());
$bad = [];
foreach ($acts as $a) {
    $h = G($own, $a['href'], "act open {$a['text']}");
    $hasForm = (bool) array_filter(forms($h), fn ($f) => ! preg_match('#/(sidebar|profile/language|appearance/mode|logout)$#', u1path($f['action'])));
    if ($own->status !== 200 || preg_match('#/(delete|revoke|deactivate|activate|forget|logout)\b#', $a['href']) || $a['method'] !== '') $bad[] = "{$a['text']} -> {$a['href']} {$own->status} method=" . json_encode($a['method']);
    $ACTS[] = "{$a['text']} -> {$a['href']} (" . h1($h) . ($hasForm ? ', with a form to fill' : '') . ')';
}
$after = $strip(fullstate());
info('actions: ' . json_encode($ACTS ?? [], JSON_UNESCAPED_UNICODE));
check('ACT-1', 'Every action (' . count($acts) . ') is a plain link (GET) that opens its screen (200): none points at an endpoint that acts', $acts !== [] && $bad === [], json_encode($bad));
check('ACT-2', 'Opening every action changes nothing in the database (Shield last_active and the session\'s last_seen_at aside)', statediff($before, $after) === [], json_encode(array_slice(statediff($before, $after), 0, 8)));
check('ACT-3', 'No settings toggle among the results (design system): no action switches something on or off by itself', ! array_filter($OWNGOT ?? [], fn ($i) => preg_match('/\b(turn|switch|enable|disable|toggle|collapse|expand|dark|light)\b/i', $i['text'])), json_encode(array_column($acts, 'text')));

// ================================================================ RO: the search writes nothing, with any method
echo "\n-- RO\n";
$ro = c8('s-ro'); login8($ro, 'admin@v8b.test', OPW);
$before = $strip(fullstate()); $R0 = count($GLOBALS['SEEN']);
foreach (['', 'users', 'users.delete', 'u3zone', 'zzz'] as $qq) { srch($ro, $qq, "ro $qq"); srch($ro, $qq, "ro xhr $qq", true); }
$tok = csrf_of(G($ro, SRCH, 'ro page'));
$methods = [];
foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $m) foreach ([[], XHR] as $hd) { $ro->req($m, SRCH . '?q=users', ['csrf_test_name' => $tok, 'q' => 'users'], $hd); $methods[$m . ($hd ? '+xhr' : '')] = $ro->status; seen($ro, "ro $m"); }
$after = $strip(fullstate());
info('other methods on the search: ' . json_encode($methods));
check('RO-1', 'Search answers (page and overlay, GET, POST, PUT, PATCH, DELETE with a valid token) leave the database unchanged (last_active, last_seen_at aside)', statediff($before, $after) === [], json_encode(array_slice(statediff($before, $after), 0, 8)));
check('RO-2', 'Other methods than GET do not reach a handler of the search (no 2xx)', ! array_filter($methods, fn ($s) => $s >= 200 && $s < 300), json_encode($methods));
$fs = array_values(array_filter(forms(G($ro, SRCH . '?q=users', 'ro forms')), fn ($f) => $f['method'] === 'post' && ! preg_match('#/(sidebar|profile/language|appearance/mode|logout)$#', u1path($f['action']))));
check('RO-3', 'The search page holds only its GET form (role="search", field q) besides the shell\'s forms', $fs === [] && (bool) array_filter(forms($ro->body), fn ($f) => $f['method'] === 'get' && array_key_exists('q', $f['types']) && u1path($f['action']) === SRCH), json_encode(array_map(fn ($f) => $f['action'], $fs)));

// ================================================================ HOS: hostile input, anonymous
echo "\n-- HOS\n";
$H0 = count($GLOBALS['SEEN']);
$hostile = [
    'arr' => 'q[]=users&q[]=roles', 'arr2' => 'q[a][b]=users', 'long' => 'q=' . str_repeat('a', 6000), 'longid' => 'q=' . str_repeat('u', 3000) . '.delete',
    'xss' => 'q=' . rawurlencode('"><script>alert(1)</script>'), 'img' => 'q=' . rawurlencode('<img src=x onerror=alert(3)>'), 'sql' => 'q=' . rawurlencode("users' OR '1'='1"), 'sql2' => 'q=' . rawurlencode('%" OR 1=1 -- '),
    'pct' => 'q=%25', 'us' => 'q=_', 'star' => 'q=users.*', 'nul' => 'q=users%00.delete', 'badutf' => 'q=%FF%FE', 'emoji' => 'q=' . rawurlencode('🔑 users'), 'space' => 'q=%20%20', 'nl' => 'q=users%0Adelete',
    'dots' => 'q=..', 'dot' => 'q=.', 'back' => 'q=' . rawurlencode('\\'), 'amp' => 'q=' . rawurlencode('&amp;<>"\''), 'extra' => 'q=users&page=-1&permission[]=x&user=1', 'none' => 'x=1',
];
$res = [];
foreach ($hostile as $k => $qs) foreach ([false, true] as $x) {
    $own->req('GET', SRCH . '?' . $qs, null, $x ? XHR : []); seen($own, "hos $k" . ($x ? ' xhr' : ''));
    $b = $own->body;
    $res[$k . ($x ? '+x' : '')] = [$own->status, count(sres($b, ! $x)), str_contains($b, '<script>alert(1)</script>') || str_contains($b, '<img src=x onerror=alert(3)>'), mb_check_encoding($b, 'UTF-8')];
}
info('hostile (status, results, raw echo, valid UTF-8): ' . json_encode($res));
check('H-1', 'Hostile queries (' . count($hostile) . ' x page and overlay: arrays, 6000 characters, markup, SQL, %, _, NUL, invalid UTF-8, emoji): no 5xx, no SQL or PHP error text', seen_clean($H0) === [], json_encode(seen_clean($H0)));
check('H-2', 'Crafted markup is never echoed raw', ! array_filter($res, fn ($r) => $r[2]), json_encode(array_keys(array_filter($res, fn ($r) => $r[2]))));
check('H-3', '% and _ are searched as text, not as wildcards (no result for a query made only of them)', $res['pct'][1] === 0 && $res['us'][1] === 0 && $res['pct+x'][1] === 0, json_encode([$res['pct'], $res['us']]));
$xsv = srch($own, '<img src=x onerror=alert(3)>', 'hos value');
$val = ($i = dom($xsv['body'])->query('//main//input[@name="q"]')->item(0)) ? $i->getAttribute('value') : null;
check('H-4', 'The query is kept in the page\'s field, escaped (the field value is the text typed)', $val === '<img src=x onerror=alert(3)>' && ! str_contains($xsv['body'], 'value="<img'), json_encode($val));
// every answer is valid UTF-8 (the middle dot of "· who can do this" included)
$enc = []; foreach ($GLOBALS['SEEN'] as $s) if (str_contains($s[2], SRCH) && $s[1] === 200 && ! mb_check_encoding($s[3], 'UTF-8')) $enc[] = $s[0];
$sample = srch($own, 'users.delete', 'hos enc', true)['body'];
preg_match('/<code[^>]*>users\.delete<\/code>\s*<span[^>]*>(.{0,8})who/su', $sample, $mdot);
info('bytes before "who can do this": ' . (isset($mdot[1]) ? bin2hex($mdot[1]) : 'n/a'));
check('H-utf8', 'Every search answer is valid UTF-8 (no broken character in the results)', $enc === [] && mb_check_encoding($sample, 'UTF-8'), json_encode(array_slice(array_unique($enc), 0, 10)) . ' bytes=' . (isset($mdot[1]) ? bin2hex($mdot[1]) : 'n/a'));
$an = c8('s-anon');
$an->req('GET', SRCH . '?q=users'); seen($an, 'anon page'); $ap = [$an->status, $an->location, count(sres($an->body))];
$an->req('GET', SRCH . '?q=users.delete', null, XHR); seen($an, 'anon xhr'); $ax = [$an->status, $an->location, count(sres($an->body, false)), str_contains($an->body, 'users.delete')];
check('H-anon', 'Anonymous: the page sends to sign in, the overlay\'s request gets no result and no identifier', in_array($ap[0], [302, 303], true) && str_contains($ap[1], 'login') && $ap[2] === 0 && ($ax[0] !== 200 || ($ax[2] === 0 && ! $ax[3])) && ! $ax[3], json_encode([$ap, $ax]));

// ================================================================ LANG: English and Italian
echo "\n-- LANG\n";
function lang_strings(string $h, bool $page): array
{
    $xp = dom($h); $o = [];
    foreach (($page ? ['h1' => '//main//h1', 'intro' => '//main//div[contains(@class,"rw-page-head")]//p', 'label' => '//main//form//label', 'button' => '//main//form//button'] : []) + ['group' => '//*[contains(@class,"rw-cmd-label")]', 'small' => '//small', 'muted' => '//a[contains(@class,"rw-cmd-opt")]//span[contains(@class,"rw-muted")]', 'empty' => '//*[contains(@class,"rw-cmd-empty")]|//*[@role="status"]'] as $k => $x)
        foreach ($xp->query(($page && $k !== 'h1' && $k !== 'intro' && $k !== 'label' && $k !== 'button' ? '//main' : '') . $x) as $i => $n) $o["$k$i"] = trim(preg_replace('/\s+/u', ' ', $n->textContent));
    foreach (sres($h, $page) as $i => $r) $o["opt$i"] = $r['text'];
    if ($page) {
        $o['title'] = preg_match('#<title>(.*?)</title>#s', $h, $m) ? trim(html_entity_decode($m[1])) : '';
        $sb = $xp->query('//aside//a[contains(@class,"rw-side-search")]//*[contains(@class,"rw-side-text")]')->item(0); $o['sidebtn'] = $sb ? trim($sb->textContent) : '';
        foreach ($xp->query('//dialog//*[contains(@class,"rw-cmd-foot")]/span') as $i => $n) $o["foot$i"] = trim($n->textContent);
        $d = $xp->query('//dialog')->item(0); $o['dialog'] = $d ? $d->getAttribute('aria-label') : '';
        $l = $xp->query('//dialog//label')->item(0); $o['dlabel'] = $l ? trim($l->textContent) : '';
        $lb = $xp->query('//dialog//*[@role="listbox"]')->item(0); $o['listbox'] = $lb ? $lb->getAttribute('aria-label') : '';
    }
    return $o;
}
$lng = c8('s-lang'); login8($lng, 'owner@v8b.test', OPW);
$LQ = ['', 'users.delete', 'u3zone.read', 'zzzqqq'];   // no English word: the Italian labels would not match it
set_lang3($lng, 'en');
$ENs = []; foreach ($LQ as $i => $qq) { $ENs["p$i"] = lang_strings(srch($lng, $qq, "lang en $i")['body'], true); $ENs["x$i"] = lang_strings(srch($lng, $qq, "lang en x $i", true)['body'], false); }
set_lang3($lng, 'it');
$ITs = []; $itHtml = ''; foreach ($LQ as $i => $qq) { $p = srch($lng, $qq, "lang it $i"); $x = srch($lng, $qq, "lang it x $i", true); $itHtml .= $p['body'] . $x['body']; $ITs["p$i"] = lang_strings($p['body'], true); $ITs["x$i"] = lang_strings($x['body'], false); }
$itItems = srch($lng, '', 'lang it items')['items'];
$byIt = [];
foreach ($itItems as $it) { $r = srch($lng, $it['text'], "lang it by label {$it['text']}"); if (! in_array(ikey($it), array_map('ikey', $r['items']), true)) $byIt[] = $it['text']; }
set_lang3($lng, 'en');
function set_lang3(Client $c, string $l): void { postform($c, 'rolewarden/profile', '#profile/language$#', ['language' => $l], "srch lang $l"); }
$same = []; $missing = []; $pairsL = [];
$norm = fn ($s) => preg_replace(['/\d+/', '/\b[a-z0-9-]+\.[a-z0-9-]+\b/'], ['#', 'SLUG'], $s);
foreach ($ENs as $i => $e) foreach ($e as $k => $v) {
    $w = $ITs[$i][$k] ?? null;
    if ($w === null) { $missing[] = "$i:$k"; continue; }
    $pairsL[$norm($v)] = $norm($w);
    if ($v !== '' && $norm($v) === $norm($w) && preg_match('/[A-Za-z]{3,}/', preg_replace('/SLUG/', '', $norm($v)))) $same[$norm($v)] = 1;
}
info('Italian strings (en => it): ' . json_encode($pairsL, JSON_UNESCAPED_UNICODE));
check('L-1', 'Every string of the search (title, heading, intro, label, button, sidebar button, dialog name, field label, listbox name, footer, groups, results, who can do this, empty state) has an Italian text different from the English one', $missing === [] && array_keys($same) === [], json_encode(['missing' => $missing, 'same' => array_keys($same)], JSON_UNESCAPED_UNICODE));
$enPh = []; foreach ($ENs as $e) foreach ($e as $v) if (mb_strlen($v) > 6 && preg_match('/[a-z]{3,} [a-z]{2,}/i', $v) && ! preg_match('/^[a-z0-9-]+\.[a-z0-9-]+$/', $v)) $enPh[$v] = 1;
$itText = text_of($itHtml);
$left = array_values(array_filter(array_keys($enPh), fn ($v) => str_contains($itText, $v)));
check('L-2', 'No English phrase of the search appears in the Italian answers (' . count($enPh) . ' phrases)', $left === [], json_encode($left));
check('L-3', 'In Italian every screen and action is found by its Italian label', $itItems !== [] && $byIt === [], json_encode($byIt, JSON_UNESCAPED_UNICODE));

// ================================================================ LOG
echo "\n-- LOG\n";
check('K-1', 'Every response of the phase (' . (count($GLOBALS['SEEN']) - $S0) . '): no 5xx, no SQL text, no PHP error', seen_clean($S0) === [], json_encode(array_slice(seen_clean($S0), 0, 20)));
$ll = bad_log_lines(logs_of($APP));
$csrf = array_filter($ll, fn ($l) => str_contains($l, 'SecurityException'));
info('application log lines WARNING or above: ' . count($ll) . ' ' . short(implode(' | ', array_slice($ll, 0, 10)), 2500));
check('K-2', 'Application log: no WARNING, ERROR or CRITICAL line (CSRF refusals of the deliberate non-GET requests aside: ' . count($csrf) . ')', array_values(array_diff($ll, $csrf)) === [], short(implode(' | ', array_slice(array_values(array_diff($ll, $csrf)), 0, 8)), 1500));
