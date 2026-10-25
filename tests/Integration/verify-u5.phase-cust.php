<?php
/**
 * U5 phase CUST (Collaudatore ad Hoc, Claude): dashboard customization and blocks of the application (tappa U5 of 1.1)
 * in black box, over HTTP, direct queries on rolewarden_test and headless Chrome.
 * Sources of the assertions: docs/SPEC.md ("Personalizzazione e blocchi, decisi il 2026-10-25 (U5)", U4 details, A5),
 * docs/BRIEF-v1.1.md (U5, definition of done), src/Dashboard/Block.php, Config\RoleWarden::$dashboardBlocks,
 * documentation/api.html and configuration.html, CHANGELOG (Unreleased), components/Dashboard/README.md, the language
 * files (translation only) and the HTML/CSS/JS a browser receives. No controller, model, view, Layout/Dashboard class,
 * panel.js, CSS source, migration or unit test read.
 * Sections: DEF (defaults, panel), APP (application blocks), ME (own choices), GLB (global default), FORGE (forged
 * global), VIS (save touches only visible blocks), RST (back to the default), PIN (security notice first), HOS (hostile
 * POST), CSRF, ADD (block added after a save), BAD (invalid classes), A5, LANG, JS (browser), SCH (schema), LOG.
 */
require_once __DIR__ . '/verify-u1.lib.php';
require_once __DIR__ . '/verify-u5.blocks.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
date_default_timezone_set('UTC');   // appTimezone of the test application
echo "\n== CUST: dashboard customization and application blocks (U5)\n";
$S0 = count($GLOBALS['SEEN']);
const LAYOUT_URL = U1BASE . 'rolewarden/dashboard/layout';
const MODKEYS = ['security', 'figures', 'signins', 'roles', 'changes'];

function schema_now(): array
{
    $o = [];
    foreach (tables() as $t) {
        $c = q("SHOW CREATE TABLE `$t`")[0];
        $o[$t] = preg_replace('/ AUTO_INCREMENT=\d+/', '', array_values($c)[1]);
    }
    return $o;
}
$SCHEMA_START = schema_now();

/** The blocks of a served dashboard in the order they render (signins = the sign-ins chart and the heat map). */
function render_order(string $h): array
{
    $p = strpos($h, 'rw-dash-customize');
    if ($p !== false) { $e = strpos($h, '</details>', $p); $h = substr($h, $e === false ? $p : $e); }
    $m = strrpos($h, '</main>'); if ($m !== false) $h = substr($h, 0, $m);
    $pos = [];
    $mk = ['security' => 'class="rw-dash-notice"', 'figures' => 'rw-dash-strip', 'signins' => 'aria-labelledby="rw-dash-signins"', 'roles' => 'aria-labelledby="rw-dash-roles"', 'changes' => 'aria-labelledby="rw-dash-changes"'];
    foreach ($mk as $k => $s) { $x = strpos($h, $s); if ($x !== false) $pos[$k] = $x; }
    preg_match_all('/aria-labelledby="rw-dash-app-([^"]+)"/', $h, $mm, PREG_OFFSET_CAPTURE);
    foreach ($mm[1] as [$k, $x]) $pos[html_entity_decode($k)] = $x;
    asort($pos);
    return array_keys($pos);
}
function pressed(string $h): ?int { $xp = dom($h); foreach ($xp->query('//form[contains(@class,"rw-dash-period")]//button') as $b) if ($b->getAttribute('aria-pressed') === 'true') return (int) $b->getAttribute('value'); return null; }
/** The Customize panel: rows [key, shown, label, pinned, moves], the selected period, the scope buttons. */
function cust(string $h): ?array
{
    $xp = dom($h);
    $d = $xp->query('//details[contains(@class,"rw-dash-customize")]')->item(0);
    if (! $d) return null;
    $rows = [];
    foreach ($xp->query('.//ol[contains(@class,"rw-dash-order")]/li', $d) as $li) {
        $k = $xp->query('.//input[@name="order[]"]', $li)->item(0);
        $cb = $xp->query('.//input[@name="shown[]"]', $li)->item(0);
        $lab = $xp->query('.//label/span', $li)->item(0);
        $rows[] = ['key' => $k ? $k->getAttribute('value') : null, 'shown' => $cb ? $cb->hasAttribute('checked') : null, 'cbval' => $cb ? $cb->getAttribute('value') : null, 'label' => $lab ? norm5($lab->textContent) : '', 'pinned' => $li->hasAttribute('data-pinned'), 'moves' => $xp->query('.//button', $li)->length];
    }
    $sel = null;
    foreach ($xp->query('.//select[@name="period"]/option', $d) as $o) if ($o->hasAttribute('selected')) $sel = (int) $o->getAttribute('value');
    $scopes = [];
    foreach ($xp->query('.//button[@name="scope"]', $d) as $b) $scopes[] = $b->getAttribute('value');
    $f = $xp->query('.//form', $d)->item(0);
    return ['rows' => $rows, 'keys' => array_column($rows, 'key'), 'period' => $sel, 'scopes' => $scopes, 'method' => $f ? strtolower($f->getAttribute('method')) : '', 'action' => $f ? $f->getAttribute('action') : '', 'summary' => norm5((string) $xp->query('./summary', $d)->item(0)?->textContent)];
}
function served_css(string $h): string { $o = ''; preg_match_all('/<link[^>]+rel="stylesheet"[^>]+href="([^"]+)"/', $h, $m); $c = c8('css'); foreach ($m[1] as $u) { $c->get(html_entity_decode($u)); $o .= $c->body; } return $o; }
function norm5(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }
function shownk(?array $c): array { return $c ? array_values(array_map(fn ($r) => $r['key'], array_filter($c['rows'], fn ($r) => $r['shown']))) : []; }
/** The probe element of an application block view. */
function probe(string $h, string $tag): ?array
{
    $xp = dom($h);
    $n = $xp->query('//div[@class="u5-probe" and @data-tag="' . $tag . '"]')->item(0);
    if (! $n) return null;
    $o = [];
    foreach (['days', 'from', 'to', 'prevfrom', 'ndates', 'user', 'marker', 'gotuser', 'gotdays', 'vars'] as $a) $o[$a] = $n->getAttribute("data-$a");
    // section, letter and title drawn by the module around the body
    $s = $n->parentNode;
    while ($s && $s->nodeName !== 'section') $s = $s->parentNode;
    $o['section'] = $s ? $s->getAttribute('aria-labelledby') : null;
    $o['letter'] = $s ? norm5((string) $xp->query('.//*[contains(@class,"rw-section-n")]', $s)->item(0)?->textContent) : null;
    $o['title'] = $s ? norm5((string) $xp->query('.//h2', $s)->item(0)?->textContent) : null;
    $o['titleId'] = $s ? (string) $xp->query('.//h2', $s)->item(0)?->getAttribute('id') : null;
    return $o;
}
function letters(string $h): array { $xp = dom($h); $o = []; foreach ($xp->query('//div[contains(@class,"rw-dash")]//section//*[contains(@class,"rw-section-n")]') as $n) $o[] = norm5($n->textContent); return $o; }
/** POST of the Customize form as a browser builds it (order[] and shown[] repeated), CSRF token from a fresh page. */
function lay(Client $c, array $order, array $shown, $period, string $scope, string $tag, bool $csrf = true, ?string $raw = null): Client
{
    $h = G($c, 'rolewarden', "page before $tag");
    $pairs = [];
    if ($csrf) $pairs[] = 'csrf_test_name=' . rawurlencode(csrf_of($h));
    if ($raw !== null) $pairs[] = $raw;
    else {
        foreach ($order as $k) $pairs[] = 'order%5B%5D=' . rawurlencode($k);
        foreach ($shown as $k) $pairs[] = 'shown%5B%5D=' . rawurlencode($k);
        if ($period !== null) $pairs[] = 'period=' . rawurlencode((string) $period);
        $pairs[] = 'scope=' . rawurlencode($scope);
    }
    $c->req('POST', LAYOUT_URL, implode('&', $pairs), ['Content-Type: application/x-www-form-urlencoded']);
    seen($c, "POST layout $tag");
    return $c;
}
function rows_layout(): array { return q("SELECT `class`, `key`, `value`, `type`, context FROM settings WHERE `key` LIKE '%ashboard%' ORDER BY context"); }
function row_for(?string $ctx): ?array { foreach (rows_layout() as $r) if ($r['context'] === $ctx) return $r; return null; }
function n_updated(): int { return (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action='settings.updated'"); }
function pos_of(array $o, string $k): int { $i = array_search($k, $o, true); return $i === false ? -1 : $i; }
function dget(Client $c, string $tag, string $q = ''): string { return G($c, 'rolewarden' . $q, $tag); }
function toast_has(string $h, string $msg): bool { return str_contains($h, $msg) || str_contains($h, substr(json_encode($msg), 1, -1)) || str_contains(html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8'), $msg) || str_contains(html_entity_decode($h, ENT_QUOTES | ENT_HTML5, 'UTF-8'), substr(json_encode($msg), 1, -1)); }

// ================================================================= setup: permissions and blocks of the application
u5_migration($APP);
$o = sp8($APP, ['migrate', '-n', 'App']);
check('SET-mig', 'The application adds u5orders.view and u5stock.view with a migration as documented (exit 0, both present)', rc($o) === 0 && perm_id('u5orders.view') > 0 && perm_id('u5stock.view') > 0, short($o));
u5_write_blocks($APP, ['App\Dashboard\U5OrdersBlock', 'App\Dashboard\U5StockBlock']);
$RV = mkrole('u5-viewer', ['dashboard.figures', 'dashboard.roles', 'dashboard.changes', 'dashboard.security', 'u5orders.view']);
$RG = mkrole('u5-global', ['dashboard.figures', 'dashboard.signins', 'settings.view', 'settings.update']);
$V1 = mkuser('v1@u5.test', 'u5-viewer'); $V2 = mkuser('v2@u5.test', 'u5-viewer'); $V3 = mkuser('v3@u5.test', 'u5-viewer');
$W = mkuser('w@u5.test', 'u5-viewer', ['u5orders.view' => 0]);
$GU = mkuser('g@u5.test', 'u5-global');
check('SET-adm', 'Model: admin holds settings.update and the five dashboard.*, not the application permissions', holds($ADMIN, 'settings.update') && array_product(array_map(fn ($p) => (int) holds($ADMIN, $p), ['dashboard.figures', 'dashboard.signins', 'dashboard.security', 'dashboard.roles', 'dashboard.changes'])) === 1 && ! holds($ADMIN, 'u5orders.view') && ! holds($ADMIN, 'u5stock.view'), '');
check('SET-v1', 'Model: u5-viewer has no settings.update', ! holds($V1, 'settings.update') && ! holds($W, 'u5orders.view'), '');
// the security notice: one address with lockAttempts (5) failures today, five different emails (no email lock)
q('DELETE FROM auth_logins');
$NOW = time();
for ($i = 0; $i < 5; $i++) q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', ['203.0.113.50', 'u5 sec', 'email_password', "nobody$i@u5.test", null, date('Y-m-d H:i:s', max(strtotime(date('Y-m-d 00:00:01', $NOW)), $NOW - 1800 + $i * 60)), 0]);
sp8($APP, ['cache:clear']);
serve8($APP, 'CUST');
$CL = []; $LOGGED = 0;
foreach (['own' => ['owner@v8b.test', OPW], 'adm' => ['admin@v8b.test', OPW], 'v1' => ['v1@u5.test', U1PW], 'v2' => ['v2@u5.test', U1PW], 'v3' => ['v3@u5.test', U1PW], 'w' => ['w@u5.test', U1PW], 'g' => ['g@u5.test', U1PW]] as $n => [$e, $pw]) {
    [$CL[$n], $ok] = u1login("u5-$n", $e, $pw);
    if (! $ok) info("login $n failed: {$CL[$n]->status} {$CL[$n]->location}"); else $LOGGED++;
}
check('SET-login', 'All seven accounts sign in', $LOGGED === 7, (string) $LOGGED);
q("DELETE FROM auth_logins WHERE user_agent <> 'u5 sec'");
$DEF_ALL = ['security', 'figures', 'signins', 'roles', 'changes', 'u5-orders', 'u5stock2'];
$DEF_V = ['security', 'figures', 'roles', 'changes', 'u5-orders'];

// ================================================================= DEF: defaults and the panel
$h = dget($CL['own'], 'def own');
check('DEF-own', 'Nobody has chosen: the super admin sees the stock order, application blocks after the five (' . implode(',', $DEF_ALL) . '), 30 days', render_order($h) === $DEF_ALL && pressed($h) === 30, json_encode([render_order($h), pressed($h)]));
$c = cust($h);
check('DEF-panel', 'Customize is a secondary button (summary) next to the period, opening a POST form', $c && $c['summary'] === 'Customize' && $c['method'] === 'post' && str_contains($h, 'rw-btn rw-btn--secondary">Customize') && strpos($h, 'rw-dash-period') < strpos($h, 'rw-dash-customize'), json_encode($c ? [$c['summary'], $c['method'], $c['action']] : null));
check('DEF-rows', 'One row per block the super admin sees, in the page order, all checked', $c && $c['keys'] === $DEF_ALL && shownk($c) === $DEF_ALL && array_column($c['rows'], 'cbval') === $DEF_ALL, json_encode($c['rows'] ?? null));
check('DEF-pin', 'The security notice row is pinned: no up/down buttons, the others have two', $c && $c['rows'][0]['pinned'] && $c['rows'][0]['moves'] === 0 && array_sum(array_map(fn ($r) => (int) ($r['moves'] === 2), array_slice($c['rows'], 1))) === count($c['rows']) - 1, json_encode(array_map(fn ($r) => [$r['key'], $r['pinned'], $r['moves']], $c['rows'] ?? [])));
check('DEF-scope-own', 'Three actions for settings.update: Save for me, Save as default for everyone, Back to the default', $c && $c['scopes'] === ['me', 'global', 'reset'], json_encode($c['scopes'] ?? null));
check('DEF-period', 'The panel ends with the opening period, 7/30/90, 30 selected', $c && $c['period'] === 30 && preg_match_all('/<option value="(7|30|90)"/', substr($h, strpos($h, 'name="period"')), $mm) === 3, (string) ($c['period'] ?? ''));
check('DEF-labels', 'Rows are labelled with the block names (EN) and the application titles', $c && array_column($c['rows'], 'label') === ['Security notice', 'Key figures', 'Sign-ins', 'Who holds which role', 'Latest permission changes', 'U5 Orders block', U5_STOCK_TITLE], json_encode(array_column($c['rows'] ?? [], 'label')));
$hv = dget($CL['v1'], 'def v1');
$cv = cust($hv);
check('DEF-v1', 'u5-viewer: the blocks of its permissions only, stock order, and the panel lists only those', render_order($hv) === $DEF_V && $cv && $cv['keys'] === $DEF_V, json_encode([render_order($hv), $cv['keys'] ?? null]));
check('DEF-scope-v1', 'Without settings.update the global action is not rendered (only me and reset)', $cv && $cv['scopes'] === ['me', 'reset'] && ! str_contains($hv, 'Save as default for everyone'), json_encode($cv['scopes'] ?? null));
$hw = dget($CL['w'], 'def w');
check('DEF-w', 'Override negative on u5orders.view: no Orders block, not in the panel', render_order($hw) === ['security', 'figures', 'roles', 'changes'] && ! in_array('u5-orders', cust($hw)['keys'] ?? ['u5-orders'], true) && ! str_contains($hw, 'U5 Orders block'), json_encode([render_order($hw), cust($hw)['keys'] ?? null]));
$hg = dget($CL['g'], 'def g');
check('DEF-g', 'u5-global (figures, signins, settings.update): only those two, no security row, global action rendered', render_order($hg) === ['figures', 'signins'] && (cust($hg)['keys'] ?? null) === ['figures', 'signins'] && (cust($hg)['scopes'] ?? null) === ['me', 'global', 'reset'], json_encode([render_order($hg), cust($hg)]));
$ha = dget($CL['adm'], 'def adm');
check('DEF-adm', 'admin: the five module blocks, no application block', render_order($ha) === MODKEYS && (cust($ha)['keys'] ?? null) === MODKEYS, json_encode(render_order($ha)));

// ================================================================= APP: application blocks
$po = probe($hv, 'orders');
$st = strtotime('-29 days', strtotime(date('Y-m-d 00:00:00')));
check('APP-sec', 'The application block sits in a section drawn by the module, with a letter and its title()', $po && $po['section'] === 'rw-dash-app-u5-orders' && preg_match('/^[A-Z]$/', (string) $po['letter']) && $po['title'] === 'U5 Orders block' && $po['titleId'] === 'rw-dash-app-u5-orders', json_encode($po));
check('APP-data', 'The view receives what data() returns (marker, userId and period it got)', $po && $po['marker'] === 'ORD-DATA' && $po['gotuser'] === (string) $V1 && $po['gotdays'] === '30', json_encode($po));
check('APP-uid', 'The view receives $userId = the signed-in user (v1 = ' . $V1 . ')', $po && $po['user'] === (string) $V1, (string) ($po['user'] ?? ''));
$okFrom = $po && $po['from'] === date('Y-m-d H:i:s', $st) && $po['prevfrom'] === date('Y-m-d H:i:s', strtotime('-30 days', $st)) && abs(strtotime($po['to']) - time()) < 300 && $po['ndates'] === '30' && $po['days'] === '30';
check('APP-period', 'The view receives $period: days 30, from 00:00 of 29 days ago, to now, prevFrom 30 days earlier, 30 dates', $okFrom, json_encode($po));
$ps = probe($h, 'stock');
check('APP-stock', 'A block without data(): its view still gets $period and $userId (owner = ' . $OWNER . ')', $ps && $ps['user'] === (string) $OWNER && $ps['days'] === '30', json_encode($ps));
check('APP-isol', 'A block without data() receives no data of another block (contract: data() plus $period and $userId)', $ps && $ps['marker'] === 'none' && $ps['gotuser'] === 'none', json_encode(['marker' => $ps['marker'] ?? null, 'gotUser' => $ps['gotuser'] ?? null, 'vars' => $ps['vars'] ?? null]));
info('APP vars seen by the Orders view: ' . ($po['vars'] ?? '') . ' | by the Stock view: ' . ($ps['vars'] ?? ''));
$stH = substr($h, (int) strpos($h, 'rw-dash-app-u5stock2'), 400);
check('APP-esc', 'The module escapes the title() of an application block in the section heading', str_contains($stH, 'U5 Stock &lt;b&gt;block&lt;/b&gt; &amp; co') && ! str_contains($stH, '<b>block</b>'), short($stH, 300));
$L = letters($h);
check('APP-letters', 'Letters run A, B, C... over the sections in render order, the application blocks included', $L === array_slice(range('A', 'Z'), 0, count($L)) && count($L) >= 6, json_encode($L));
check('APP-perm-own', 'The super admin sees both application blocks; admin (no permission) sees none of their titles', str_contains($h, 'U5 Orders block') && probe($h, 'stock') && ! str_contains($ha, 'U5 Orders block') && ! str_contains($ha, 'U5 Stock'), '');

// ================================================================= ME: own choices
$n0 = n_updated();
$O1 = ['security', 'changes', 'u5-orders', 'figures', 'roles'];
lay($CL['v1'], $O1, ['security', 'changes', 'u5-orders', 'figures'], 7, 'me', 'v1 me');
$st1 = $CL['v1']->status; $loc1 = $CL['v1']->location;
$hb = $CL['v1']->go(abs_url(LAYOUT_URL, $loc1 ?: 'rolewarden'));
check('ME-1', 'Save for me: a redirect back to the dashboard with the toast "Your dashboard is saved."', in_array($st1, [302, 303], true) && toast_has($hb, 'Your dashboard is saved.'), "$st1 $loc1");
$h1 = dget($CL['v1'], 'me v1');
check('ME-2', 'v1 now sees its order without Who holds which role, and 7 days without ?period=', render_order($h1) === ['security', 'changes', 'u5-orders', 'figures'] && pressed($h1) === 7 && (probe($h1, 'orders')['days'] ?? '') === '7', json_encode([render_order($h1), pressed($h1)]));
$c1 = cust($h1);
check('ME-3', 'v1 panel shows its choice: order, roles unchecked, period 7', $c1 && $c1['keys'] === $O1 && shownk($c1) === ['security', 'changes', 'u5-orders', 'figures'] && $c1['period'] === 7, json_encode($c1));
$h2 = dget($CL['v2'], 'me v2');
check('ME-4', 'v2 (same role, no choice) keeps the stock dashboard, 30 days', render_order($h2) === $DEF_V && pressed($h2) === 30, json_encode([render_order($h2), pressed($h2)]));
check('ME-5', 'The super admin keeps the stock dashboard', render_order(dget($CL['own'], 'me own')) === $DEF_ALL, '');
$r1 = row_for("user:$V1");
check('ME-6', 'Stored with Settings in context user:{id} (key dashboardLayout), no global row', $r1 !== null && str_contains($r1['key'], 'dashboardLayout') && row_for(null) === null, json_encode(rows_layout()));
info('ME stored: ' . json_encode($r1));
info('ME settings.updated entries after a save for me: ' . (n_updated() - $n0));
foreach ([90, 30] as $p) { $hp = dget($CL['v1'], "me v1 ?$p", "?period=$p"); check("ME-q$p", "?period=$p wins over the saved 7 (page and block data)", pressed($hp) === $p && (probe($hp, 'orders')['days'] ?? '') === (string) $p, (string) pressed($hp)); }
foreach (['365', 'abc', '-7', ''] as $p) { $hp = dget($CL['v1'], "me v1 ?$p", '?period=' . rawurlencode($p)); check('ME-qh-' . md5($p), "?period=" . json_encode($p) . ': 200 with a period of 7, 30 or 90', $CL['v1']->status === 200 && in_array(pressed($hp), [7, 30, 90], true), json_encode([$CL['v1']->status, pressed($hp)])); info("?period=" . json_encode($p) . ' with a saved 7 gives ' . pressed($hp)); }

// ================================================================= GLB: global default (admin)
$n0 = n_updated();
lay($CL['adm'], ['security', 'roles', 'figures', 'signins', 'changes'], ['security', 'roles', 'figures', 'changes'], 90, 'global', 'adm global');
$stg = $CL['adm']->status;
$hb = $CL['adm']->go(abs_url(LAYOUT_URL, $CL['adm']->location ?: 'rolewarden'));
check('GLB-1', 'Save as default for everyone by admin: redirect and toast', in_array($stg, [302, 303], true) && toast_has($hb, 'The default dashboard is saved.'), (string) $stg);
check('GLB-row', 'Stored with Settings, global (context NULL)', row_for(null) !== null, json_encode(rows_layout()));
info('GLB stored: ' . json_encode(row_for(null)));
$lg = q("SELECT actor_id, action, subject_type, subject_label, details FROM acl_activity_log WHERE action='settings.updated' ORDER BY id DESC LIMIT 1");
check('GLB-log', 'The global save is one activity entry settings.updated by admin', n_updated() - $n0 === 1 && (int) ($lg[0]['actor_id'] ?? 0) === $ADMIN, json_encode($lg));
$hl = G($CL['own'], 'rolewarden/activity?action=settings.updated', 'activity settings.updated');
check('GLB-logview', 'The activity log shows it (200, no raw field names)', $CL['own']->status === 200 && ! preg_match('/dashboard_(order|hidden|period)/', text_of($hl)), short(text_of($hl), 300));
info('GLB activity text: ' . short(substr(text_of($hl), (int) strpos(text_of($hl), 'settings')), 600));
$h2 = dget($CL['v2'], 'glb v2');
check('GLB-v2', 'v2 (no choice) follows the global default: its blocks in the global order, signins n/a, Orders at the tail, 90 days', render_order($h2) === ['security', 'roles', 'figures', 'changes', 'u5-orders'] && pressed($h2) === 90, json_encode([render_order($h2), pressed($h2)]));
$ho = dget($CL['own'], 'glb own');
check('GLB-own', 'The super admin (no choice) follows it: signins hidden, application blocks at the tail in their order, 90 days', render_order($ho) === ['security', 'roles', 'figures', 'changes', 'u5-orders', 'u5stock2'] && pressed($ho) === 90, json_encode([render_order($ho), pressed($ho)]));
check('GLB-own-panel', 'Hidden by the default, signins stays in the super admin panel, unchecked, so it can be turned back on', in_array('signins', cust($ho)['keys'] ?? [], true) && ! in_array('signins', shownk(cust($ho)), true), json_encode(cust($ho)['rows'] ?? null));
check('GLB-v1', 'v1 keeps its own choice', render_order(dget($CL['v1'], 'glb v1')) === ['security', 'changes', 'u5-orders', 'figures'], '');
$hgg = dget($CL['g'], 'glb g');
check('GLB-g', 'g (figures, signins) follows it: figures only, 90 days', render_order($hgg) === ['figures'] && pressed($hgg) === 90, json_encode([render_order($hgg), pressed($hgg)]));
check('GLB-adm', 'admin itself has no own row and follows the default', row_for("user:$ADMIN") === null && render_order(dget($CL['adm'], 'glb adm')) === ['security', 'roles', 'figures', 'changes'], json_encode(rows_layout()));

// ================================================================= FORGE: global without settings.update
$g0 = row_for(null); $n0 = n_updated(); $v10 = row_for("user:$V1");
foreach (['v1', 'w'] as $who) {
    lay($CL[$who], ['figures'], ['figures'], 7, 'global', "forge $who");
    check("FORGE-$who", "Forged scope=global by $who (no settings.update): refused without a 5xx, the global default unchanged, no settings.updated", $CL[$who]->status < 500 && row_for(null) == $g0 && n_updated() === $n0, json_encode([$CL[$who]->status, $CL[$who]->location]));
    info("FORGE $who answer: {$CL[$who]->status} {$CL[$who]->location}");
}
check('FORGE-own', 'The forged request did not touch v1 own row either', row_for("user:$V1") == $v10, json_encode(row_for("user:$V1")));
check('FORGE-v2', 'v2 still sees the global default of admin', render_order(dget($CL['v2'], 'forge v2')) === ['security', 'roles', 'figures', 'changes', 'u5-orders'] && pressed($CL['v2']->body) === 90, '');

// ================================================================= VIS: a global save touches only the blocks the saver sees
lay($CL['g'], ['signins', 'figures'], ['signins', 'figures'], 30, 'global', 'g global 1');
check('VIS-1st', 'g (settings.update) saves a global default', in_array($CL['g']->status, [302, 303], true), (string) $CL['g']->status);
$h2 = dget($CL['v2'], 'vis v2');
$o2 = render_order($h2);
check('VIS-v2', 'Blocks g cannot see keep their state: v2 still has security first, roles before changes, Orders last; 30 days', $o2 && $o2[0] === 'security' && pos_of($o2, 'roles') >= 0 && pos_of($o2, 'roles') < pos_of($o2, 'changes') && end($o2) === 'u5-orders' && count($o2) === 5 && pressed($h2) === 30, json_encode([$o2, pressed($h2)]));
$ho = dget($CL['own'], 'vis own'); $oo = render_order($ho);
check('VIS-own', 'What g saw changed: signins shown again and before figures (super admin view)', pos_of($oo, 'signins') >= 0 && pos_of($oo, 'signins') < pos_of($oo, 'figures'), json_encode($oo));
info('VIS global order after g, as the super admin sees it: ' . json_encode($oo));
lay($CL['g'], ['signins', 'figures'], ['signins'], 30, 'global', 'g global 2');
$o2 = render_order(dget($CL['v2'], 'vis v2 b'));
check('VIS-hide', 'g hides figures for everyone; roles, changes, security and Orders (not seen by g) stay shown for v2', $o2 === array_values(array_diff($o2, ['figures'])) && ! in_array('figures', $o2, true) && count(array_intersect(['security', 'roles', 'changes', 'u5-orders'], $o2)) === 4, json_encode($o2));

// ================================================================= RST: back to the default
lay($CL['v1'], [], [], null, 'reset', 'v1 reset');
$str = $CL['v1']->status;
$hb = $CL['v1']->go(abs_url(LAYOUT_URL, $CL['v1']->location ?: 'rolewarden'));
check('RST-1', 'Back to the default: redirect and toast "Your dashboard follows the default again."', in_array($str, [302, 303], true) && toast_has($hb, 'Your dashboard follows the default again.'), (string) $str);
$h1 = dget($CL['v1'], 'rst v1'); $o1 = render_order($h1);
check('RST-2', 'v1 follows the global default again (no figures, security first, roles before changes, Orders last, 30 days)', ! in_array('figures', $o1, true) && $o1[0] === 'security' && pos_of($o1, 'roles') < pos_of($o1, 'changes') && end($o1) === 'u5-orders' && pressed($h1) === 30, json_encode([$o1, pressed($h1)]));
$rr = row_for("user:$V1");
check('RST-3', 'v1 own row removed or emptied', $rr === null || in_array(trim((string) $rr['value']), ['', 'null', 'a:0:{}', '[]', 'N;'], true), json_encode($rr));
check('RST-4', 'Back to the default does not touch the global default', row_for(null) !== null, '');

// ================================================================= PIN: the security notice stays first
foreach ([['figures', 'signins', 'security', 'roles', 'changes', 'u5-orders', 'u5stock2'], ['figures', 'signins', 'roles', 'changes', 'u5-orders', 'u5stock2', 'security'], ['u5stock2', 'figures', 'signins', 'roles', 'changes', 'u5-orders']] as $i => $ord) {
    lay($CL['own'], $ord, $DEF_ALL, 30, 'me', "own pin $i");
    $oo = render_order(dget($CL['own'], "pin own $i"));
    check("PIN-me$i", 'Forged order ' . json_encode($ord) . ': the notice still renders first', ($oo[0] ?? '') === 'security' && count($oo) === 7, json_encode($oo));
}
lay($CL['own'], $DEF_ALL, array_values(array_diff($DEF_ALL, ['security'])), 30, 'me', 'own hide security');
$ho = dget($CL['own'], 'pin hidden');
check('PIN-hide', 'The notice can be hidden: not rendered, its row unchecked and still listed', ! in_array('security', render_order($ho), true) && ! str_contains($ho, 'rw-dash-notice') && in_array('security', cust($ho)['keys'] ?? [], true) && ! in_array('security', shownk(cust($ho)), true), json_encode(render_order($ho)));
lay($CL['own'], $DEF_ALL, $DEF_ALL, 30, 'me', 'own show security');
check('PIN-show', 'Shown again', (render_order(dget($CL['own'], 'pin shown'))[0] ?? '') === 'security', '');
lay($CL['adm'], ['figures', 'signins', 'security', 'roles', 'changes'], MODKEYS, 30, 'global', 'adm global pin');
$o2 = render_order(dget($CL['v2'], 'pin v2'));
check('PIN-glb', 'A global default with the notice in the middle: it still renders first for v2', ($o2[0] ?? '') === 'security', json_encode($o2));
$cp = cust(dget($CL['own'], 'pin own panel'));
check('PIN-panel', 'In the panel the pinned row stays first whatever was saved', ($cp['keys'][0] ?? '') === 'security' && $cp['rows'][0]['pinned'], json_encode($cp['keys'] ?? null));

// ================================================================= VIS2: own save with blocks the user does not see
lay($CL['v2'], ['u5stock2', 'figures', 'security', 'roles', 'changes', 'u5-orders'], ['u5stock2', 'figures', 'security', 'roles', 'changes', 'u5-orders'], 30, 'me', 'v2 unseen first');
lay($CL['v3'], ['u5stock2', 'figures', 'security', 'roles', 'changes', 'u5-orders'], ['figures', 'security', 'roles', 'changes', 'u5-orders'], 30, 'me', 'v3 unseen hidden');
info('VIS2 stored rows: ' . json_encode([row_for("user:$V2"), row_for("user:$V3")]));
$now5 = date('Y-m-d H:i:s');
foreach ([$V2, $V3] as $u) q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,?,?,?)', [$u, perm_id('u5stock.view'), 1, $now5, $now5]);
sp8($APP, ['cache:clear']);   // the override is written straight to the table: the permission cache is cleared as an admin save would
$o2 = render_order(dget($CL['v2'], 'vis2 v2')); $o3 = render_order(dget($CL['v3'], 'vis2 v3'));
check('VIS2-v2', 'Once v2 may see Stock, it comes at the tail, not where the forged order put it', end($o2) === 'u5stock2' && ($o2[0] ?? '') === 'security', json_encode($o2));
check('VIS2-v3', 'Once v3 may see Stock, it is shown (the forged hiding was not stored), at the tail', end($o3) === 'u5stock2', json_encode($o3));

// ================================================================= HOS: hostile POST
$snap = fn () => json_encode(rows_layout());
$base = $snap();
$hos = [
    'unknown keys' => [['nope-key-u5', '<script>alert(1)</script>', '../../x', 'figures', 'security', 'roles', 'changes', 'u5-orders', 'u5stock2'], ['nope-key-u5', '<script>alert(1)</script>', 'figures', 'security', 'roles', 'changes', 'u5-orders', 'u5stock2'], 7, 'me', null],
    'period 365' => [['figures'], ['figures'], 365, 'me', null],
    'period abc' => [['figures'], ['figures'], 'abc', 'me', null],
    'period -7' => [['figures'], ['figures'], '-7', 'me', null],
    'period array' => [[], [], null, 'me', 'order%5B%5D=figures&shown%5B%5D=figures&period%5B%5D=7&scope=me'],
    'order string' => [[], [], null, 'me', 'order=figures&shown=figures&period=30&scope=me'],
    'nested arrays' => [[], [], null, 'me', 'order%5Ba%5D%5Bb%5D=figures&shown%5Bx%5D%5B%5D=roles&period=30&scope=me'],
    'no fields' => [[], [], null, 'me', 'scope=me'],
    'scope evil' => [['figures'], ['figures'], 30, 'evil', null],
    'scope missing' => [[], [], null, 'me', 'order%5B%5D=figures&shown%5B%5D=figures&period=30'],
    'huge' => [array_merge(array_map(fn ($i) => "k$i", range(1, 400)), ['figures']), array_map(fn ($i) => "k$i", range(1, 400)), 30, 'me', null],
    'nul byte' => [[], [], null, 'me', 'order%5B%5D=fig%00ures&shown%5B%5D=%FF%FE&period=30&scope=me'],
];
foreach ($hos as $label => [$ord, $sh, $per, $scope, $raw]) {
    lay($CL['v2'], $ord, $sh, $per, $scope, "hos $label", true, $raw);
    $s = $CL['v2']->status;
    $hh = dget($CL['v2'], "hos page $label");
    $val = (string) (row_for("user:$V2")['value'] ?? '');
    check('HOS-' . md5($label), "Hostile POST ($label): no 5xx, the dashboard opens with 7/30/90, nothing hostile stored", $s < 500 && $CL['v2']->status === 200 && in_array(pressed($hh), [7, 30, 90], true) && ! preg_match('/nope-key|<script|\.\.\/|k399|365|abc/', $val), json_encode([$s, $CL['v2']->status, pressed($hh), short($val, 200)]));
    info("HOS $label: answer $s " . $CL['v2']->location . ' -> stored ' . short($val, 160) . ' period ' . pressed($hh) . ' order ' . json_encode(render_order($hh)));
}
$lg = logs_of($APP); foreach ($lg as $i => $l) if (str_contains($l, 'Array to string')) { info('HOS log: ' . short(implode(' | ', array_slice($lg, $i, 8)), 1500)); break; }
// unknown keys ignored, known ones kept: the first case asked figures first
$b1 = $snap();
lay($CL['v2'], ['figures'], ['figures'], 7, 'evil', 'hos evil again');
check('HOS-evil', 'scope=evil (repeated) leaves every layout row as it was', $snap() === $b1 && $CL['v2']->status < 500, (string) $CL['v2']->status);
lay($CL['v2'], ['figures'], ['figures'], 7, 'global', 'hos global v2');
check('HOS-glob', 'v2 forging scope=global changes nothing global', $snap() === $b1, '');

// ================================================================= CSRF and access
$b1 = $snap(); $n0 = n_updated();
lay($CL['adm'], ['figures'], ['figures'], 7, 'global', 'csrf adm', false);
check('CSRF-glb', 'POST without the CSRF token (admin, global) is refused and saves nothing', in_array($CL['adm']->status, CSRF_REFUSED, true) && $snap() === $b1 && n_updated() === $n0, (string) $CL['adm']->status);
lay($CL['v2'], ['figures'], ['figures'], 7, 'me', 'csrf v2', false);
check('CSRF-me', 'POST without the CSRF token (v2, me) is refused and saves nothing', in_array($CL['v2']->status, CSRF_REFUSED, true) && $snap() === $b1, (string) $CL['v2']->status);
$anon = c8('u5-anon');
$ha = G($anon, 'login', 'anon login page');
$anon->req('POST', LAYOUT_URL, 'csrf_test_name=' . rawurlencode(csrf_of($ha)) . '&order%5B%5D=figures&shown%5B%5D=figures&period=7&scope=global', ['Content-Type: application/x-www-form-urlencoded']);
seen($anon, 'anon POST');
check('ACC-anon', 'A guest POST is sent to sign in and saves nothing', in_array($anon->status, [302, 303], true) && str_contains($anon->location, 'login') && $snap() === $b1, "{$anon->status} {$anon->location}");
G($CL['v2'], LAYOUT_URL, 'GET layout');
check('ACC-get', 'GET on the save address is not a page (no 2xx, no 5xx)', $CL['v2']->status >= 300 && $CL['v2']->status < 500, (string) $CL['v2']->status);
$nod = mkuser('nodash@u5.test', 'user');
[$cn, $okn] = u1login('u5-nodash', 'nodash@u5.test');
lay($cn, ['figures'], ['figures'], 7, 'me', 'nodash me');
info('ACC user without dashboard.*: POST me -> ' . $cn->status . ' ' . $cn->location . ' row=' . json_encode(row_for("user:$nod")));
check('ACC-nodash', 'A user without any dashboard.* posting: no 5xx', $cn->status < 500, (string) $cn->status);

// ================================================================= ADD: a block registered after a save comes at the tail
u5_set_list($APP, ['App\Dashboard\U5OrdersBlock']);
lay($CL['own'], ['security', 'u5-orders', 'figures', 'signins', 'roles', 'changes'], ['security', 'u5-orders', 'figures', 'signins', 'roles', 'changes'], 30, 'me', 'own before add');
$o = render_order(dget($CL['own'], 'add before'));
check('ADD-0', 'Only Orders registered: the super admin saved Orders second', $o === ['security', 'u5-orders', 'figures', 'signins', 'roles', 'changes'], json_encode($o));
u5_set_list($APP, ['App\Dashboard\U5OrdersBlock', 'App\Dashboard\U5StockBlock']);
$ho = dget($CL['own'], 'add after');
check('ADD-1', 'Stock registered after the save: it renders at the tail, shown', render_order($ho) === ['security', 'u5-orders', 'figures', 'signins', 'roles', 'changes', 'u5stock2'], json_encode(render_order($ho)));
check('ADD-2', 'And it comes last in the panel, checked', (cust($ho)['keys'] ?? null) === ['security', 'u5-orders', 'figures', 'signins', 'roles', 'changes', 'u5stock2'] && in_array('u5stock2', shownk(cust($ho)), true), json_encode(cust($ho)['keys'] ?? null));
u5_set_list($APP, ['App\Dashboard\U5StockBlock', 'App\Dashboard\U5OrdersBlock']);
info('ADD registration order swapped (Stock, Orders): super admin order ' . json_encode(render_order(dget($CL['own'], 'add swapped'))));
u5_set_list($APP, ['App\Dashboard\U5OrdersBlock', 'App\Dashboard\U5StockBlock']);
lay($CL['own'], [], [], null, 'reset', 'own reset add');

// ================================================================= BAD: invalid classes are left out and logged
$logBefore = count(logs_of($APP));
u5_set_list($APP, array_merge(['App\Dashboard\U5OrdersBlock', 'App\Dashboard\U5StockBlock'], U5_ALL_INVALID));
$ho = dget($CL['own'], 'bad own');
$titles = ['U5 Not a block', 'U5 Duplicate block', 'U5 Module key block', 'U5 Security key block', 'U5 Bad key block', 'U5 Upper key block', 'U5 Empty key block', 'U5 Space key block'];
check('BAD-1', 'With nine invalid registrations the dashboard still opens (200, no error text)', $CL['own']->status === 200 && ! phperr(text_of($ho)) && ! leaks_sql($ho), (string) $CL['own']->status);
check('BAD-2', 'The valid blocks render; none of the invalid ones (titles absent from page and panel)', count(array_filter($titles, fn ($t) => str_contains($ho, $t))) === 0 && probe($ho, 'orders') && probe($ho, 'stock'), json_encode(array_values(array_filter($titles, fn ($t) => str_contains($ho, $t)))));
check('BAD-3', 'The module blocks are untouched: one figures strip, one notice, the order as before', substr_count($ho, 'rw-dash-strip') === 1 && render_order($ho) === render_order(dget($CL['own'], 'bad own 2')) && (render_order($ho)[0] ?? '') === 'security' && in_array('figures', render_order($ho), true), json_encode(render_order($ho)));
check('BAD-4', 'The first registration of key u5-orders keeps it (the duplicate is the one left out)', (probe($ho, 'orders')['title'] ?? '') === 'U5 Orders block', '');
$newLog = array_slice(logs_of($APP), $logBefore);
$missing = [];
foreach (U5_ALL_INVALID as $cls) if (! preg_grep('/' . preg_quote($cls, '/') . '\b/', $newLog)) $missing[] = $cls;
check('BAD-log', 'Every invalid class is written to the log by name', $missing === [], json_encode(['missing' => $missing, 'sample' => array_slice(array_values(preg_grep('/dashboard block/', $newLog)), 0, 3)]));
$hv = dget($CL['v2'], 'bad v2');
check('BAD-v2', 'For another user too the page opens with the valid blocks only', $CL['v2']->status === 200 && count(array_filter($titles, fn ($t) => str_contains($hv, $t))) === 0, '');
lay($CL['own'], array_merge($DEF_ALL, ['u5-notablock', 'Bad_Key!']), array_merge($DEF_ALL, ['u5-notablock']), 30, 'me', 'bad save');
check('BAD-save', 'Saving with invalid blocks registered works (no 5xx) and names none of them', $CL['own']->status < 500 && ! str_contains((string) (row_for("user:$OWNER")['value'] ?? ''), 'notablock'), json_encode([$CL['own']->status, row_for("user:$OWNER")]));
u5_set_list($APP, ['App\Dashboard\U5OrdersBlock', 'App\Dashboard\U5StockBlock']);
lay($CL['own'], [], [], null, 'reset', 'own reset bad');

// ================================================================= A5: several locked emails, one line
foreach (['locked1@u5.test', 'locked2@u5.test', 'locked3@u5.test'] as $j => $em) for ($i = 0; $i < 5; $i++) q('INSERT INTO auth_logins (ip_address, user_agent, id_type, identifier, user_id, date, success) VALUES (?,?,?,?,?,?,?)', ['198.51.100.' . (10 * $j + $i + 1), 'u5 sec', 'email_password', $em, null, date('Y-m-d H:i:s', time() - 240 + $i * 30), 0]);
$ho = dget($CL['own'], 'a5 own');
$xp = dom($ho);
$lines = [];
foreach ($xp->query('//div[contains(@class,"rw-dash-notice")]//li') as $li) $lines[] = norm5($li->textContent);
$withMail = array_values(array_filter($lines, fn ($l) => str_contains($l, '@u5.test')));
check('A5-1', 'Three locked emails: one line names all three, the address line stays apart (two lines)', count($withMail) === 1 && str_contains($withMail[0], 'locked1@u5.test') && str_contains($withMail[0], 'locked2@u5.test') && str_contains($withMail[0], 'locked3@u5.test') && count($lines) === 2, json_encode($lines));
check('A5-2', 'The address line comes first, the emails after', count($lines) === 2 && str_contains($lines[0], '203.0.113.50') && str_contains($lines[1], 'locked'), json_encode($lines));

// ================================================================= LANG: new strings in English and Italian
$find = function (array $a, string $key) use (&$find) { foreach ($a as $k => $v) { if ($k === $key && is_array($v)) return $v; if (is_array($v)) { $r = $find($v, $key); if ($r !== null) return $r; } } return null; };
$flat = function (array $a, string $p = '') use (&$flat): array { $o = []; foreach ($a as $k => $v) { if (is_array($v)) $o += $flat($v, "$p$k."); else $o["$p$k"] = (string) $v; } return $o; };
$ce = $flat($find($EN, 'customize') ?? []); $ci = $flat($find($IT, 'customize') ?? []);
$fe = $find($EN, 'fields') ?? []; $fi = $find($IT, 'fields') ?? [];
$empty = array_keys(array_filter($ci, fn ($v) => trim($v) === ''));
$same = array_keys(array_filter($ci, fn ($v, $k) => ($ce[$k] ?? null) === $v, ARRAY_FILTER_USE_BOTH));
check('LANG-keys', 'The customize strings have the same keys in en and it (' . count($ce) . '), none empty, none left in English', $ce !== [] && array_keys($ce) === array_keys($ci) && $empty === [] && $same === [], json_encode(['onlyEn' => array_diff(array_keys($ce), array_keys($ci)), 'onlyIt' => array_diff(array_keys($ci), array_keys($ce)), 'empty' => $empty, 'same' => $same]));
$nf = ['dashboard_order', 'dashboard_hidden', 'dashboard_period'];
check('LANG-fields', 'The activity field names of the layout exist in both languages, translated', array_product(array_map(fn ($k) => (int) (($fe[$k] ?? '') !== '' && ($fi[$k] ?? '') !== '' && $fe[$k] !== $fi[$k]), $nf)) === 1, json_encode([array_intersect_key($fe, array_flip($nf)), array_intersect_key($fi, array_flip($nf))]));
postform($CL['own'], 'rolewarden/profile', '#profile/language$#', ['language' => 'it'], 'lang it');
$hi = dget($CL['own'], 'lang it page');
$ci2 = cust($hi);
$enLeft = array_values(array_filter(array_merge(array_values($ce)), fn ($s) => ! str_contains($s, '{') && str_contains(text_of($hi), $s)));
check('LANG-it', 'In Italian the panel reads Personalizza, Salva per me, Salva come predefinito per tutti, Torna al predefinito, with the block names in Italian', $ci2 && $ci2['summary'] === $ci['open'] && str_contains(text_of($hi), $ci['saveMine']) && str_contains(text_of($hi), $ci['saveGlobal']) && str_contains(text_of($hi), $ci['restore']) && array_slice(array_column($ci2['rows'], 'label'), 0, 5) === [$ci['blocks.security'], $ci['blocks.figures'], $ci['blocks.signins'], $ci['blocks.roles'], $ci['blocks.changes']], json_encode([$ci2['summary'] ?? null, array_column($ci2['rows'] ?? [], 'label')]));
check('LANG-it-en', 'No English customize string left on the Italian page', $enLeft === [], json_encode($enLeft));
check('LANG-it-aria', 'Up/down labels in Italian', str_contains(html_entity_decode($hi, ENT_QUOTES | ENT_HTML5, 'UTF-8'), str_replace('{0}', $ci['blocks.figures'], $ci['up'])), '');
lay($CL['own'], $DEF_ALL, $DEF_ALL, 30, 'me', 'it save');
$hb = $CL['own']->go(abs_url(LAYOUT_URL, $CL['own']->location ?: 'rolewarden'));
check('LANG-it-toast', 'The toast of a save is in Italian', toast_has($hb, $ci['saved']), '');
lay($CL['own'], [], [], null, 'reset', 'it reset');
$hb = $CL['own']->go(abs_url(LAYOUT_URL, $CL['own']->location ?: 'rolewarden'));
check('LANG-it-toast2', 'The toast of Back to the default is in Italian', toast_has($hb, $ci['reset']), '');
postform($CL['own'], 'rolewarden/profile', '#profile/language$#', ['language' => 'en'], 'lang en');

// ================================================================= NOJS: the form as served, no script
$hv = dget($CL['v1'], 'nojs v1');
check('NOJS-cloak', 'Without JavaScript the up/down buttons stay hidden (x-cloak on their wrapper, a [x-cloak] rule in the served CSS)', preg_match_all('/<span class="rw-dash-move" x-cloak>/', $hv) === count(cust($hv)['rows'] ?? []) - 1 && (bool) preg_match('/\[x-cloak\][^{]*\{[^}]*display:\s*none/', served_css($hv)), '');
// a browser without JS sends every hidden order[] in page order, the checked shown[], the select and the clicked button
$cv = cust($hv);
$ord = $cv['keys']; $sh = array_values(array_diff(shownk($cv), ['changes']));
lay($CL['v1'], $ord, $sh, 90, 'me', 'nojs save');
$h1 = dget($CL['v1'], 'nojs after');
check('NOJS-save', 'The no-JS submission saves show/hide and the period (changes hidden, 90 days)', ! in_array('changes', render_order($h1), true) && pressed($h1) === 90, json_encode([render_order($h1), pressed($h1)]));
lay($CL['v1'], [], [], null, 'reset', 'nojs reset');
// every box unchecked: a browser sends the order[] fields and no shown[]
$cv = cust(dget($CL['v1'], 'none page'));
lay($CL['v1'], $cv['keys'], [], 30, 'me', 'none hidden');
$sn = $CL['v1']->status;
$hn = dget($CL['v1'], 'none after');
check('NOJS-none', 'Every block unchecked: no 5xx, the page opens and Customize stays reachable to undo it', $sn < 500 && $CL['v1']->status === 200 && cust($hn) !== null, json_encode([$sn, $CL['v1']->status, render_order($hn)]));
info('NOJS every block unchecked renders ' . json_encode(render_order($hn)) . ', panel ' . json_encode(shownk(cust($hn))));
lay($CL['v1'], [], [], null, 'reset', 'none reset');

// ================================================================= JS: headless Chrome, up/down and focus
$jsPrep = <<<'JS'
(() => { const d = document.querySelector('details.rw-dash-customize'); d.open = true; return [...d.querySelectorAll('ol.rw-dash-order > li')].map(li => li.querySelector('input[name="order[]"]').value); })()
JS;
$jsState = <<<'JS'
(() => { const d = document.querySelector('details.rw-dash-customize'); const lis = [...d.querySelectorAll('ol.rw-dash-order > li')];
  return { keys: lis.map(li => li.querySelector('input[name="order[]"]').value),
    buttons: lis.map(li => [...li.querySelectorAll('.rw-dash-move button')].map(b => ({ disabled: b.disabled, visible: !!(b.offsetWidth || b.offsetHeight) }))) }; })()
JS;
$jsMove = fn (string $key, string $dir) => "(async () => { const li = [...document.querySelectorAll('ol.rw-dash-order > li')].find(l => l.querySelector('input[name=\"order[]\"]').value === " . json_encode($key) . "); const b = li.querySelectorAll('.rw-dash-move button')[" . ($dir === 'up' ? 0 : 1) . "]; b.focus(); b.click(); await new Promise(r => setTimeout(r, 300)); const a = document.activeElement; const cs = getComputedStyle(a);
  return { keys: [...document.querySelectorAll('ol.rw-dash-order > li')].map(l => l.querySelector('input[name=\"order[]\"]').value), active: a.tagName + '|' + (a.getAttribute('aria-label') || ''), sameButton: a === b, focusVisible: a.matches(':focus-visible'), outline: cs.outlineStyle + ' ' + cs.outlineWidth + ' ' + cs.outlineColor, shadow: cs.boxShadow, disabled: b.disabled }; })()";
$r = u1browser([
    blogin('v1@u5.test', U1PW),
    ['op' => 'goto', 'url' => U1BASE . 'rolewarden', 'tag' => 'dash'],
    ['op' => 'eval', 'expr' => $jsPrep, 'tag' => 'prep'],
    ['op' => 'eval', 'expr' => $jsState, 'tag' => 'state0'],
    ['op' => 'eval', 'expr' => $jsMove('figures', 'down'), 'tag' => 'm1'],
    ['op' => 'eval', 'expr' => $jsMove('u5-orders', 'up'), 'tag' => 'm2'],
    ['op' => 'eval', 'expr' => $jsMove('u5-orders', 'down'), 'tag' => 'm3'],
    ['op' => 'eval', 'expr' => $jsState, 'tag' => 'state1'],
    ['op' => 'eval', 'expr' => "(() => { document.querySelector('input[name=\"shown[]\"][value=\"roles\"]').checked = false; document.querySelector('select[name=period]').value = '7'; return true; })()", 'tag' => 'uncheck'],
    ['op' => 'click', 'selector' => 'details.rw-dash-customize button[name=scope][value=me]', 'nav' => true, 'tag' => 'save'],
    ['op' => 'eval', 'expr' => "[...document.querySelectorAll('section[aria-labelledby]')].map(s => s.getAttribute('aria-labelledby'))", 'tag' => 'after'],
], 'js v1');
$s0 = bval($r, 'state0'); $m1 = bval($r, 'm1'); $m2 = bval($r, 'm2'); $m3 = bval($r, 'm3'); $s1 = bval($r, 'state1');
info('JS: ' . json_encode(['state0' => $s0, 'm1' => $m1, 'm2' => $m2, 'm3' => $m3, 'state1' => $s1, 'after' => bval($r, 'after'), 'console' => $r['consoleErrors'] ?? null, 'errors' => $r['errors'] ?? null]));
$k0 = $s0['keys'] ?? [];
check('JS-0', 'With JavaScript the up/down buttons show; the first movable row cannot go up, the last cannot go down', $s0 && ($s0['buttons'][1][0]['disabled'] ?? null) === true && ($s0['buttons'][count($k0) - 1][1]['disabled'] ?? null) === true && ($s0['buttons'][1][0]['visible'] ?? false) && ($s0['buttons'][2][1]['disabled'] ?? null) === false, json_encode($s0));
$exp1 = $k0; if ($k0) { $i = array_search('figures', $exp1, true); [$exp1[$i], $exp1[$i + 1]] = [$exp1[$i + 1], $exp1[$i]]; }
check('JS-down', 'Down moves the row one place down', ($m1['keys'] ?? null) === $exp1, json_encode([$m1['keys'] ?? null, $exp1]));
check('JS-focus', 'After a move the focus stays on the pressed button and is visible', ($m1['sameButton'] ?? false) && ($m1['focusVisible'] ?? false) && (! str_starts_with((string) ($m1['outline'] ?? 'none'), 'none') || ($m1['shadow'] ?? 'none') !== 'none'), json_encode($m1));
$exp2 = $exp1; $i = array_search('u5-orders', $exp2, true); if ($i > 1) [$exp2[$i], $exp2[$i - 1]] = [$exp2[$i - 1], $exp2[$i]];
check('JS-up', 'Up moves the row one place up', ($m2['keys'] ?? null) === $exp2 && ($m2['sameButton'] ?? false), json_encode([$m2['keys'] ?? null, $exp2]));
check('JS-edge', 'Moving a row to the last place: the focus is not lost (it stays on a visible control of the panel)', $m3 && $m3['active'] !== 'BODY|' && ($m3['focusVisible'] ?? false), json_encode($m3));
check('JS-pin', 'The pinned security row never moves', ($s1['keys'][0] ?? '') === 'security', json_encode($s1['keys'] ?? null));
$after = bval($r, 'after') ?? [];
$h1 = dget($CL['v1'], 'js after http');
$expSaved = array_values(array_filter($s1['keys'] ?? [], fn ($k) => $k !== 'roles'));
check('JS-save', 'Save for me after the moves: the page renders the new order, roles hidden, 7 days', render_order($h1) === $expSaved && pressed($h1) === 7, json_encode([render_order($h1), $expSaved, pressed($h1)]));
check('JS-console', 'No JavaScript error in the console', ($r['consoleErrors'] ?? ['?']) === [] && ($r['errors'] ?? ['?']) === [], json_encode([$r['consoleErrors'] ?? null, $r['errors'] ?? null]));
lay($CL['v1'], [], [], null, 'reset', 'js reset');

// ================================================================= LOG and errors
$bad = seen_clean($S0);
check('LOG-5xx', 'No answer >= 500, no SQL text, no PHP error on any page of the phase', $bad === [], json_encode(array_slice($bad, 0, 8)));
$bl = bad_log_lines(logs_of($APP));
$other = array_values(array_filter($bl, fn ($l) => ! preg_match('/RoleWarden: dashboard block App\\\\Dashboard\\\\U5\w+ |SecurityException: The action you requested is not allowed/', $l)));
check('LOG-app', 'The application log has no WARNING/ERROR line other than the invalid blocks and the CSRF refusals of this phase', $other === [], json_encode(array_slice($other, 0, 6)));
$srv = (string) @file_get_contents("$WORK/server-CUST.log");
check('LOG-srv', 'No PHP warning in the server log', ! preg_match('/PHP (Warning|Notice|Deprecated|Fatal)/', $srv), short($srv, 300));

// ================================================================= SCH: no new table
$SCHEMA_END = schema_now();
check('SCH-1', 'No table appeared or changed during the phase', $SCHEMA_END === $SCHEMA_START, json_encode(array_keys(array_diff_assoc($SCHEMA_END, $SCHEMA_START) + array_diff_key($SCHEMA_START, $SCHEMA_END))));
server_stop();
u5_remove_blocks($APP);
// the schema of the previous step (U4, 29a5df6) against this one, both freshly installed
$ROOT = getenv('RW_U4_ROOT');
$oldTar = "$WORK/old.tar";
exec('git -C ' . escapeshellarg($ROOT) . ' archive --format=tar -o ' . escapeshellarg($oldTar) . ' 29a5df6 2>&1', $go, $grc);
if ($grc === 0) {
    @mkdir("$WORK/oldmod");
    (new PharData($oldTar))->extractTo("$WORK/oldmod", null, true);
    $OLDAPP = app_copy('oldapp', "$WORK/oldmod", false);
    $okI = base_install($OLDAPP);
    $SCH_OLD = schema_now();
    $okN = base_install($APP);
    $SCH_NEW = schema_now();
    check('SCH-2', 'Fresh installs of 29a5df6 (U4) and 09b9159 (U5) have the same tables and the same CREATE TABLE', $okI && $okN && $SCH_OLD === $SCH_NEW, json_encode(['onlyNew' => array_keys(array_diff_key($SCH_NEW, $SCH_OLD)), 'onlyOld' => array_keys(array_diff_key($SCH_OLD, $SCH_NEW)), 'changed' => array_keys(array_diff_assoc(array_intersect_key($SCH_NEW, $SCH_OLD), $SCH_OLD))]));
    info('SCH tables: ' . implode(',', array_keys($SCH_NEW)));
} else check('SCH-2', 'git archive of 29a5df6', false, implode(' ', $go));
