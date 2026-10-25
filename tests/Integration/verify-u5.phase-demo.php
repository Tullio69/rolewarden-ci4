<?php
/**
 * U5 phase DEMO (Collaudatore ad Hoc, Claude): the example dashboard block of the demo package (commit 0c136e6), in black box.
 * Sources: demo/README.md ("Install", "Dashboard block", "Remove"), documentation/api.html#dashboard-blocks, CHANGELOG,
 * docs/SPEC.md (U5) and what the server sends. demo/src and the module code are not read.
 * The copy app is wired as the README says (autoload + routes, as ../rolewarden-app-test already is), $dashboardBlocks
 * untouched. Sections: PRE (before demo:install), VIS (who sees it), NUM (by status, written in the period), ACT (after
 * creating and approving from the demo), CUS (hide, reorder), LANG, ESC, UNI (after demo:uninstall), LOG.
 */
require_once __DIR__ . '/verify-u1.lib.php';
date_default_timezone_set('UTC');
echo "\n== DEMO: the demo dashboard block (U5)\n";
$S0 = count($GLOBALS['SEEN']);
const LAYOUT_URL = U1BASE . 'rolewarden/dashboard/layout';
const DKEY = 'demo-documents';

function norm5(string $s): string { return trim(preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'))); }
function render_order(string $h): array
{
    $p = strpos($h, 'rw-dash-customize');
    if ($p !== false) { $e = strpos($h, '</details>', $p); $h = substr($h, $e === false ? $p : $e); }
    $pos = [];
    foreach (['security' => 'class="rw-dash-notice"', 'figures' => 'rw-dash-strip', 'signins' => 'aria-labelledby="rw-dash-signins"', 'roles' => 'aria-labelledby="rw-dash-roles"', 'changes' => 'aria-labelledby="rw-dash-changes"'] as $k => $s) { $x = strpos($h, $s); if ($x !== false) $pos[$k] = $x; }
    preg_match_all('/aria-labelledby="rw-dash-app-([^"]+)"/', $h, $mm, PREG_OFFSET_CAPTURE);
    foreach ($mm[1] as [$k, $x]) $pos[html_entity_decode($k)] = $x;
    asort($pos);
    return array_keys($pos);
}
function cust_keys(string $h): array { $xp = dom($h); $o = []; foreach ($xp->query('//details[contains(@class,"rw-dash-customize")]//ol/li') as $li) { $i = $xp->query('.//input[@name="order[]"]', $li)->item(0); $c = $xp->query('.//input[@name="shown[]"]', $li)->item(0); $o[] = [$i?->getAttribute('value'), $c && $c->hasAttribute('checked'), norm5((string) $xp->query('.//label/span', $li)->item(0)?->textContent)]; } return $o; }
/** The demo block: its section html, title, the status figures [label => n] and the note. */
function dblock(string $h): ?array
{
    if (! preg_match('/<section[^>]*aria-labelledby="rw-dash-app-' . DKEY . '"[^>]*>(.*?)<\/section>/s', $h, $m)) return null;
    $xp = dom('<div>' . $m[1] . '</div>');
    $f = [];
    foreach ($xp->query('//dl//div') as $d) $f[norm5((string) $xp->query('./dt', $d)->item(0)?->textContent)] = (int) norm5((string) $xp->query('./dd', $d)->item(0)?->textContent);
    $note = $xp->query('//p[contains(@class,"rw-note")]')->item(0);
    return ['html' => $m[0], 'title' => norm5((string) $xp->query('//h2')->item(0)?->textContent), 'letter' => norm5((string) $xp->query('//*[contains(@class,"rw-section-n")]')->item(0)?->textContent), 'figs' => $f, 'note' => $note ? norm5($note->textContent) : null, 'links' => array_map(fn ($a) => $a->getAttribute('href'), iterator_to_array($xp->query('//a'))), 'text' => norm5($xp->query('//div')->item(0)->textContent)];
}
function lay(Client $c, array $order, array $shown, $period, string $scope, string $tag): Client
{
    $h = G($c, 'rolewarden', "page before $tag");
    $pairs = ['csrf_test_name=' . rawurlencode(csrf_of($h))];
    foreach ($order as $k) $pairs[] = 'order%5B%5D=' . rawurlencode($k);
    foreach ($shown as $k) $pairs[] = 'shown%5B%5D=' . rawurlencode($k);
    if ($period !== null) $pairs[] = 'period=' . rawurlencode((string) $period);
    $pairs[] = 'scope=' . rawurlencode($scope);
    $c->req('POST', LAYOUT_URL, implode('&', $pairs), ['Content-Type: application/x-www-form-urlencoded']);
    seen($c, "POST layout $tag");
    return $c;
}
function first_n(string $s): ?int { return preg_match('/(\d+)/', $s, $m) ? (int) $m[1] : null; }
function written(int $n): int { $st = date('Y-m-d H:i:s', strtotime('-' . ($n - 1) . ' days', strtotime(date('Y-m-d 00:00:00')))); return (int) q1('SELECT COUNT(*) FROM demo_documents WHERE created_at >= ? AND created_at <= ?', [$st, date('Y-m-d H:i:s')]); }
function bystatus(): array { $o = []; foreach (q('SELECT status, COUNT(*) n FROM demo_documents GROUP BY status') as $r) $o[$r['status']] = (int) $r['n']; return $o; }
function badlog(string $app, int $from): array { return array_values(array_filter(array_slice(bad_log_lines(logs_of($app)), $from), fn ($l) => ! str_contains($l, 'SecurityException'))); }

// ================================================================= PRE: wiring and the dashboard before demo:install
$auto = (string) file_get_contents("$APP/app/Config/Autoload.php"); $routes = (string) file_get_contents("$APP/app/Config/Routes.php");
check('PRE-wire', 'The copy app is wired as the README says: RoleWardenDemo in $psr4 and DemoRoutes::register, no app/Config/RoleWarden.php, no app Registrar', str_contains($auto, "'RoleWardenDemo' =>") && str_contains($routes, 'RoleWardenDemo\Config\DemoRoutes::register($routes);') && ! is_file("$APP/app/Config/RoleWarden.php") && ! is_file("$APP/app/Config/Registrar.php"), '');
// the harness installed the demo already (S02): demo:uninstall brings back the state before demo:install (README "Remove")
$wr = function () use ($APP) { $o = []; $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$APP/writable", FilesystemIterator::SKIP_DOTS)); foreach ($it as $f) { $n = strtr( substr($f->getPathname(), strlen("$APP/writable/")), chr(92), '/'); if (! preg_match('#^(logs|session|cache|debugbar)/#', $n)) $o[] = $n; } sort($o); return $o; };
info('PRE writable files with the demo installed: ' . json_encode($wr()));
$o = sp8($APP, ['demo:uninstall']);
check('PRE-uni', 'demo:uninstall of the harness demo (exit 0)', rc($o) === 0, short($o));
info('PRE writable files after demo:uninstall: ' . json_encode($wr()));
sp8($APP, ['cache:clear']);
check('PRE-clean', 'Fresh installation without the demo: no demo_documents table, no documents.* permission', ! in_array('demo_documents', tables(), true) && (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'documents.%'") === 0, '');
$L0 = count(bad_log_lines(logs_of($APP)));
serve8($APP, 'DEMO');
[$own, $ok] = u1login('d-own', 'owner@v8b.test', OPW);
$h = G($own, 'rolewarden', 'pre own');
$b = dblock($h);
check('PRE-200', 'Before demo:install the super admin opens the dashboard: 200, no error text, no SQL text', $own->status === 200 && ! phperr(text_of($h)) && ! leaks_sql($h), (string) $own->status);
check('PRE-log', 'Before demo:install: no WARNING/ERROR line in the log (no query on a missing table)', badlog($APP, $L0) === [], json_encode(array_slice(badlog($APP, $L0), 0, 4)));
info('PRE the super admin sees the block: ' . json_encode($b ? ['title' => $b['title'], 'text' => $b['text']] : null));
check('PRE-block', 'Before demo:install the block, if shown, holds no figures read from a table (no status figures)', $b === null || $b['figs'] === [], json_encode($b['figs'] ?? null));
[$adm, $ok] = u1login('d-adm', 'admin@v8b.test', OPW);
$ha = G($adm, 'rolewarden', 'pre adm');
info('PRE admin (documents.view does not exist yet): block ' . json_encode(dblock($ha) ? dblock($ha)['text'] : null));
check('PRE-adm', 'Before demo:install admin opens the dashboard (200) without the block, documents.view not being held', $adm->status === 200 && dblock($ha) === null, '');
server_stop();

// ================================================================= demo:install
$o = sp8($APP, ['demo:install'], ['demo.password' => DPW]);
check('INS', 'php spark demo:install (exit 0) creates demo_documents and the documents.* permissions', rc($o) === 0 && in_array('demo_documents', tables(), true) && (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'documents.%'") === 5, short($o));
sp8($APP, ['cache:clear']);
// documents of various ages (created_at moved straight in the table): 1 fresh, then 20, 60 and 100 days old
$ids = array_map('intval', array_column(q('SELECT id FROM demo_documents ORDER BY id'), 'id'));
foreach ([1 => 20, 2 => 60, 3 => 100] as $i => $age) if (isset($ids[$i])) { $t = date('Y-m-d H:i:s', time() - $age * 86400); q('UPDATE demo_documents SET created_at=?, updated_at=? WHERE id=?', [$t, $t, $ids[$i]]); }
serve8($APP, 'DEMO2');
$ACC = ['owner' => ['owner@v8b.test', OPW], 'admin' => ['admin@v8b.test', OPW], 'plain' => ['plain@v8b.test', OPW], 'reader' => ['reader@example.com', DPW], 'author' => ['author@example.com', DPW], 'approver' => ['approver@example.com', DPW]];
$CL = [];
foreach ($ACC as $n => [$e, $pw]) { [$CL[$n], $ok] = u1login("d2-$n", $e, $pw); if (! $ok) info("login $n failed {$CL[$n]->status} {$CL[$n]->location}"); }
// a user with dashboard.figures but not documents.view, to see the dashboard without the block
$RF = mkrole('u5-figs', ['dashboard.figures']);
$FU = mkuser('figs@u5.test', 'u5-figs');
[$CL['figs'], $ok] = u1login('d2-figs', 'figs@u5.test');

// ================================================================= VIS: who sees it
foreach (['owner', 'reader', 'author', 'approver', 'admin', 'plain', 'figs'] as $n) {
    $uid = uid($ACC[$n][0] ?? 'figs@u5.test');
    $hv = holds($uid, 'documents.view');
    $h = G($CL[$n], 'rolewarden', "vis $n");
    $st = $CL[$n]->status;
    $b = $st === 200 ? dblock($h) : null;
    check("VIS-$n", "$n (documents.view " . ($hv ? 'held' : 'not held') . '): the block ' . ($hv ? 'shows' : 'does not show') . ' (no 5xx)', $st < 500 && (($b !== null) === $hv), json_encode([$st, $CL[$n]->location, $b ? $b['title'] : null]));
    if ($n === 'reader' || $n === 'figs') info("VIS $n dashboard: $st " . $CL[$n]->location . ' order ' . json_encode($st === 200 ? render_order($h) : null));
}
check('VIS-model', 'Model: Reader, Author and Approver hold documents.view; admin, plain and the figures-only user do not', holds(uid('reader@example.com'), 'documents.view') && holds(uid('author@example.com'), 'documents.view') && holds(uid('approver@example.com'), 'documents.view') && ! holds($ADMIN, 'documents.view') && ! holds($PLAIN, 'documents.view') && ! holds($FU, 'documents.view'), '');
$h = G($CL['reader'], 'rolewarden', 'vis reader again');
$b = dblock($h);
check('VIS-sec', 'The block sits in a module section with a letter and the title "Demo documents"', $b && $b['title'] === 'Demo documents' && preg_match('/^[A-Z]$/', $b['letter']), json_encode($b ? [$b['title'], $b['letter']] : null));
check('VIS-cust', 'It is a row of the reader Customize panel', in_array(DKEY, array_column(cust_keys($h), 0), true), json_encode(cust_keys($h)));

// ================================================================= NUM: by status and written in the period, 7/30/90
$labels = ['draft' => 'Draft', 'submitted' => 'Submitted', 'approved' => 'Approved'];
$num = function (string $tag) use ($CL, $labels) {
    $bs = bystatus();
    info("NUM $tag table by status: " . json_encode($bs) . ', written 7/30/90: ' . json_encode([written(7), written(30), written(90)]));
    foreach ([7, 30, 90] as $n) {
        $h = G($CL['reader'], 'rolewarden?period=' . $n, "num $tag $n");
        $b = dblock($h);
        $exp = []; foreach ($labels as $k => $l) $exp[$l] = $bs[$k] ?? 0;
        $other = array_diff_key($bs, $labels);
        check("NUM-$tag-$n-st", "$tag, $n days: documents by status = the table (" . json_encode($exp) . ')', $b && array_intersect_key($b['figs'], $exp) == $exp && $other === [], json_encode([$b['figs'] ?? null, $bs]));
        check("NUM-$tag-$n-w", "$tag, $n days: the note gives the documents written in the period (" . written($n) . ')', $b && first_n((string) $b['note']) === written($n) && str_contains((string) $b['note'], (string) $n), json_encode([$b['note'] ?? null, written($n)]));
    }
};
$num('seed');
// ================================================================= ACT: create and approve from the demo
$hc = G($CL['author'], 'demo/create', 'author create form');
$f = null; foreach (forms($hc) as $ff) if ($ff['method'] === 'post' && ! str_contains($ff['action'], 'language') && ! str_contains($ff['action'], 'logout') && ! str_contains($ff['action'], 'mode') && ! str_contains($ff['action'], 'sidebar')) $f = $ff;
info('ACT create form: ' . json_encode($f ? ['action' => $f['action'], 'fields' => array_keys($f['types'])] : null));
$before = (int) q1('SELECT COUNT(*) FROM demo_documents');
if ($f) { $v = $f['fields']; foreach (array_keys($f['types']) as $k) if (in_array($k, ['title'], true)) $v[$k] = 'U5 demo doc <b>x</b>'; elseif (in_array($k, ['body'], true)) $v[$k] = 'Written by the U5 test.'; P($CL['author'], $f['action'], $v, 'author create'); }
check('ACT-create', 'The author creates a document from the demo (one more row)', (int) q1('SELECT COUNT(*) FROM demo_documents') === $before + 1, (string) $CL['author']->status);
$sub = q1("SELECT id FROM demo_documents WHERE status='submitted' ORDER BY id LIMIT 1");
$hd = G($CL['approver'], 'demo', 'approver list');
$P = null; foreach (forms($hd) as $ff) if (preg_match('#demo/' . $sub . '/approve$#', $ff['action'])) $P = $ff;
if ($P) P($CL['approver'], $P['action'], $P['fields'], 'approver approve');
check('ACT-approve', 'The approver approves the submitted document from the demo', $sub && q1('SELECT status FROM demo_documents WHERE id=?', [$sub]) === 'approved', json_encode([$sub, $P ? $P['action'] : null, $CL['approver']->status]));
info('ACT approved doc: ' . json_encode(q('SELECT id, status, created_at, approved_at, updated_at FROM demo_documents WHERE id=?', [$sub])));
$num('after');

// ================================================================= CUS: hide and reorder from Customize
$h = G($CL['owner'], 'rolewarden', 'cus own');
$keys = array_column(cust_keys($h), 0);
$ord = array_merge(['security', DKEY], array_values(array_diff($keys, ['security', DKEY])));
lay($CL['owner'], $ord, $ord, 30, 'me', 'cus first');
$o = render_order(G($CL['owner'], 'rolewarden', 'cus after first'));
check('CUS-order', 'Moved up in Customize, the demo block renders first (right after the notice, when there is one)', ($o[0] ?? '') === DKEY || (($o[0] ?? '') === 'security' && ($o[1] ?? '') === DKEY), json_encode($o));
lay($CL['owner'], $ord, array_values(array_diff($ord, [DKEY])), 30, 'me', 'cus hide');
$h = G($CL['owner'], 'rolewarden', 'cus after hide');
check('CUS-hide', 'Unchecked, the demo block is not rendered and stays in the panel unchecked', dblock($h) === null && in_array([DKEY, false], array_map(fn ($r) => [$r[0], $r[1]], cust_keys($h)), true), json_encode(cust_keys($h)));
lay($CL['owner'], [], [], null, 'reset', 'cus reset');
$ro = render_order(G($CL['owner'], 'rolewarden', 'cus after reset'));
check('CUS-reset', 'Back to the default: the demo block shows again, after the module blocks', dblock($CL['owner']->body) !== null && end($ro) === DKEY, json_encode($ro));

// ================================================================= LANG: English and Italian
$b = dblock(G($CL['reader'], 'rolewarden', 'lang en'));
$rawKey = fn (string $t) => (bool) preg_match('/\b(Demo|RoleWarden|App)\.[a-zA-Z]+(\.[a-zA-Z]+)*\b/', $t);
check('LANG-en', 'In English: title, status labels and note, no raw language key', $b && ! $rawKey($b['text']) && array_keys($b['figs']) === array_values($labels), json_encode($b ? [$b['text'], array_keys($b['figs'])] : null));
postform($CL['reader'], 'rolewarden/profile', '#profile/language$#', ['language' => 'it'], 'reader it');
$bi = dblock(G($CL['reader'], 'rolewarden', 'lang it'));
$enWords = ['Demo documents', 'Draft', 'Submitted', 'Approved', 'documents written', 'Open the documents', 'in the last'];
$left = $bi ? array_values(array_filter($enWords, fn ($w) => str_contains($bi['text'], $w))) : ['no block'];
check('LANG-it', 'In Italian: the block reads in Italian (no English word of the block, no raw key) with the same numbers', $bi && $left === [] && ! $rawKey($bi['text']) && array_values($bi['figs']) === array_values($b['figs'] ?? []), json_encode([$bi['text'] ?? null, $left]));
info('LANG it text: ' . ($bi['text'] ?? ''));
check('LANG-it-cust', 'In Italian the panel row of the block is the Italian title', $bi && in_array($bi['title'], array_column(cust_keys($CL['reader']->body), 2), true) && $bi['title'] !== 'Demo documents', '');
postform($CL['reader'], 'rolewarden/profile', '#profile/language$#', ['language' => 'en'], 'reader en');

// ================================================================= ESC: nothing out of escaping
q("INSERT INTO demo_documents (title, body, status, owner_id, created_at, updated_at) VALUES (?,?,?,?,?,?)", ['<script>alert(1)</script>', 'x', '<b>x</b>&"', uid('author@example.com'), date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);
$h = G($CL['reader'], 'rolewarden', 'esc');
$b = dblock($h);
check('ESC-1', 'A row with a hostile status and title: the page opens, nothing of it unescaped in the block', $CL['reader']->status === 200 && $b && ! str_contains($b['html'], '<b>x</b>') && ! str_contains($b['html'], '<script>alert'), short($b['html'] ?? '', 600));
info('ESC block with a hostile status: figs ' . json_encode($b['figs'] ?? null) . ' note ' . ($b['note'] ?? ''));
check('ESC-2', 'No hostile string unescaped anywhere on the dashboard', ! str_contains($h, '<b>x</b>') && ! str_contains($h, '<script>alert(1)'), '');
q("DELETE FROM demo_documents WHERE status='<b>x</b>&\"'");
server_stop();

// ================================================================= UNI: after demo:uninstall
$o = sp8($APP, ['demo:uninstall']);
check('UNI-0', 'php spark demo:uninstall (exit 0): table and documents.* gone', rc($o) === 0 && ! in_array('demo_documents', tables(), true) && (int) q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'documents.%'") === 0, short($o));
sp8($APP, ['cache:clear']);
$L1 = count(bad_log_lines(logs_of($APP)));
serve8($APP, 'DEMO3');
[$own, $ok] = u1login('d3-own', 'owner@v8b.test', OPW);
$h = G($own, 'rolewarden', 'uni own');
$b = dblock($h);
check('UNI-200', 'After demo:uninstall the super admin opens the dashboard: 200, no error text, no SQL text', $own->status === 200 && ! phperr(text_of($h)) && ! leaks_sql($h), (string) $own->status);
check('UNI-log', 'After demo:uninstall: no WARNING/ERROR line in the log', badlog($APP, $L1) === [], json_encode(array_slice(badlog($APP, $L1), 0, 4)));
check('UNI-nofig', 'After demo:uninstall the block, if still shown, reads no table (no status figures)', $b === null || $b['figs'] === [], json_encode($b['figs'] ?? null));
info('UNI super admin block after uninstall: ' . json_encode($b ? ['title' => $b['title'], 'text' => $b['text'], 'inPanel' => in_array(DKEY, array_column(cust_keys($h), 0), true)] : null));
[$adm, $ok] = u1login('d3-adm', 'admin@v8b.test', OPW);
$ha = G($adm, 'rolewarden', 'uni adm');
check('UNI-adm', 'After demo:uninstall admin opens the dashboard without the block', $adm->status === 200 && dblock($ha) === null, '');
server_stop();

// ================================================================= ROB: the demo marked installed but its table gone (e.g. a database restored without it)
$o = sp8($APP, ['demo:install'], ['demo.password' => DPW]);
q('DROP TABLE IF EXISTS demo_documents');
sp8($APP, ['cache:clear']);
$L2 = count(bad_log_lines(logs_of($APP)));
serve8($APP, 'DEMO4');
[$own, $ok] = u1login('d4-own', 'owner@v8b.test', OPW);
$h = G($own, 'rolewarden', 'rob own');
$robLog = badlog($APP, $L2);
check('ROB-1', 'Demo installed, demo_documents missing: the super admin still opens the dashboard (no 5xx, no SQL text)', $own->status === 200 && ! leaks_sql($h), json_encode([$own->status, array_slice($robLog, 0, 2)]));
check('ROB-2', 'Demo installed, demo_documents missing: the block does not query the missing table (no SQL error in the log)', ! preg_grep('/demo_documents/', $robLog), json_encode(array_slice($robLog, 0, 2)));
server_stop();
$o = sp8($APP, ['demo:uninstall']);
info('ROB demo:uninstall with the table gone: ' . short($o, 300));
// the rows written in ROB are not part of the LOG checks below
$GLOBALS['SEEN'] = array_values(array_filter($GLOBALS['SEEN'], fn ($x) => ! str_starts_with($x[0], 'rob ')));
$ROBLINES = $robLog;

// ================================================================= LOG
$bad = seen_clean($S0);
check('LOG-5xx', 'No answer >= 500, no SQL text, no PHP error on any page of the phase', $bad === [], json_encode(array_slice($bad, 0, 8)));
$srv = '';
foreach (['DEMO', 'DEMO2', 'DEMO3'] as $t) $srv .= (string) @file_get_contents("$WORK/server-$t.log");
check('LOG-srv', 'No PHP warning in the server logs', ! preg_match('/PHP (Warning|Notice|Deprecated|Fatal)/', $srv), '');
$rest = array_values(array_diff(badlog($APP, 0), $ROBLINES));
check('LOG-app', 'No WARNING/ERROR line in the application log over the phase (CSRF refusals and the ROB case aside)', $rest === [], json_encode(array_slice($rest, 0, 6)));
