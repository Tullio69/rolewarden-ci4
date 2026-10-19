<?php
// U2 phase X (probe, exploration only, no checks): served HTML of the Permissions list, the sidebar and the Check a
// permission screen, saved to RW_U2_PROBE_OUT (scratchpad) to learn routes and markup before writing the assertions.
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
$OUT = getenv('RW_U2_PROBE_OUT') ?: $WORK;
@mkdir($OUT, 0777, true);
serve8($APP, 'X');
$own = c8('x-owner'); login8($own, 'owner@v8b.test', OPW);
$h = G($own, 'rolewarden/permissions', 'x perms');
file_put_contents("$OUT/perms.html", $h);
$s = side($h);
info('sidebar owner: ' . json_encode($s['groups']));
$links = [];
foreach (dom($h)->query('//a[@href]') as $a) $links[] = $a->getAttribute('href');
$chk = array_values(array_unique(array_filter($links, fn ($l) => preg_match('/check|explain|verif/i', $l))));
info('check links: ' . json_encode(array_slice($chk, 0, 10)) . ' total ' . count($chk));
$first = $chk[0] ?? null;
foreach ($s['items'] as $p => $it) if (preg_match('/check/i', $p)) $first = $first ?? $p;
foreach (array_slice($chk, 0, 1) as $i => $u) {
    $hh = G($own, html_entity_decode($u), 'x check');
    info("GET $u -> {$own->status} {$own->url}");
    file_put_contents("$OUT/check1.html", $hh);
    foreach (forms($hh) as $f) info('form: ' . json_encode(['m' => $f['method'], 'a' => $f['action'], 'f' => $f['fields'], 't' => $f['types']]));
}
foreach ($s['items'] as $p => $it) if (preg_match('/check/i', $p)) { $hh = G($own, $p, 'x side check'); info("side $p -> {$own->status}"); file_put_contents("$OUT/check0.html", $hh); foreach (forms($hh) as $f) info('form0: ' . json_encode(['m' => $f['method'], 'a' => $f['action'], 'f' => $f['fields'], 't' => $f['types']])); }
info('probe written to ' . $OUT);
// scenario for markup: grand -> parent -> child, users with overrides
$g = mkrole('x-grand', ['documents.view']); $p = mkrole('x-parent', []); $ch = mkrole('x-child', []);
q('UPDATE acl_roles SET parent_id=? WHERE id=?', [$g, $p]); q('UPDATE acl_roles SET parent_id=? WHERE id=?', [$p, $ch]);
$u1 = mkuser('x-child@example.com', 'x-child');
$u2 = mkuser('x-deny@example.com', 'x-child', ['documents.view' => 0]);
$u3 = mkuser('x-grant@example.com', 'user', ['documents.view' => 1]);
$u4 = mkuser('x-off@example.com', 'x-grand', [], 0);
$u5 = mkuser('x-super-deny@example.com', 'super-admin', ['documents.view' => 0]);
foreach (['permission=documents.view', 'permission=documents.view&user=' . $u1, 'permission=documents.view&user=x-deny@example.com', 'permission=documents.view&user=' . $u3, 'permission=documents.view&user=' . $u4, 'permission=documents.view&user=' . $u5, 'permission=documents.view&user=' . $OWNER, 'permission=documents.view&user=' . $PLAIN, 'permission=nope.nope', 'permission=documents.view&user=999999'] as $i => $qs) {
    $hh = G($own, "rolewarden/permissions/check?$qs", "x q$i");
    info("q$i $qs -> {$own->status}");
    $m = strpos($hh, '<main'); file_put_contents("$OUT/q$i.html", substr($hh, $m === false ? 0 : $m));
}
