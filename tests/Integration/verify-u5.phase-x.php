<?php
/**
 * U5 probe X (Collaudatore ad Hoc, Claude): exploration only, no checks. Registers the test blocks in the copy app,
 * dumps the served dashboard and its Customize forms, and the Settings rows, to RW_U5_OUT.
 */
require_once __DIR__ . '/verify-u1.lib.php';
require_once __DIR__ . '/verify-u5.blocks.php';
$OUT = getenv('RW_U5_OUT');
echo "\n== X: probe (U5)\n";
u5_migration($APP);
info('migrate App: ' . short(sp8($APP, ['migrate', '-n', 'App'])));
u5_write_blocks($APP, array_merge(['App\Dashboard\U5OrdersBlock', 'App\Dashboard\U5StockBlock'], U5_ALL_INVALID));
q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [role_id('admin'), perm_id('u5orders.view')]);
serve8($APP, 'X');
[$own, $ok] = u1login('x-own', 'owner@v8b.test', OPW);
info('login owner ok=' . json_encode($ok));
foreach (['rolewarden' => 'dash', 'rolewarden?period=7' => 'dash7'] as $u => $n) {
    $h = G($own, $u, "x $u");
    info("$u -> {$own->status} " . $own->location);
    file_put_contents("$OUT/verify-u5.x.$n.html", $h);
    foreach (forms($h) as $f) info("form on $u: " . json_encode(['action' => $f['action'], 'method' => $f['method'], 'fields' => $f['fields'], 'types' => $f['types']]));
}
[$adm, $ok] = u1login('x-adm', 'admin@v8b.test', OPW);
$h = G($adm, 'rolewarden', 'x admin');
file_put_contents("$OUT/verify-u5.x.admin.html", $h);
foreach (forms($h) as $f) info('admin form: ' . json_encode(['action' => $f['action'], 'fields' => $f['fields'], 'types' => $f['types']]));
// a plain save for me with the form as served
$f = null; foreach (forms($h) as $ff) if ($ff['method'] === 'post' && str_contains($ff['action'], 'dashboard')) $f = $ff;
if ($f) {
    P($adm, $f['action'], $f['fields'], 'x save');
    info("save as served -> {$adm->status} {$adm->location}");
    info('settings: ' . json_encode(q("SELECT `class`, `key`, `value`, `type`, context FROM settings")));
    $h2 = $adm->go('rolewarden');
    file_put_contents("$OUT/verify-u5.x.admin2.html", $h2);
}
info('logs: ' . short(implode("\n", array_slice(logs_of($APP), 0, 40)), 4000));
u5_remove_blocks($APP);
