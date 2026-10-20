<?php
/**
 * U3 phase X (Collaudatore ad Hoc, Claude): exploration probe of the command search, no checks.
 * Dumps the served sidebar, the search page for a few queries and what Chrome sees of the overlay (dialog markup,
 * requests it makes) into verify-u3.x.<label>.txt, to write the real phases against the served HTML only.
 */
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
echo "\n== X: probe\n";
serve8($APP, 'X');
$out = '';
$own = c8('x-owner'); login8($own, 'owner@v8b.test', OPW);
$h = G($own, 'rolewarden/users', 'x users');
$xp = dom($h);
$as = $xp->query('//aside')->item(0);
$out .= "=== SIDEBAR\n" . ($as ? $as->ownerDocument->saveHTML($as) : 'none') . "\n";
foreach ($xp->query('//dialog') as $d) $out .= "=== DIALOG\n" . $d->ownerDocument->saveHTML($d) . "\n";
foreach ($xp->query('//script') as $s) $out .= "=== SCRIPT src=" . $s->getAttribute('src') . "\n";
foreach (['rolewarden/search', 'rolewarden/search?q=users', 'rolewarden/search?q=users.del', 'rolewarden/search?q=role', 'rolewarden/search?q=check'] as $u) {
    $b = G($own, $u, "x $u");
    $m = dom($b)->query('//main')->item(0);
    $out .= "=== GET $u -> {$own->status} " . json_encode($own->headers) . "\n" . ($m ? $m->ownerDocument->saveHTML($m) : substr($b, 0, 3000)) . "\n";
}
$steps = [blogin('owner@v8b.test', OPW), ['op' => 'goto', 'url' => U1BASE . 'rolewarden/users', 'tag' => 'g'], ['op' => 'requests', 'tag' => 'r0'],
    ['op' => 'key', 'key' => 'k', 'code' => 'KeyK', 'keyCode' => 75, 'modifiers' => 2, 'tag' => 'ck'],
    ['op' => 'eval', 'tag' => 'after-ck', 'expr' => '(() => ({ active: document.activeElement ? document.activeElement.outerHTML.slice(0, 400) : null, dialogs: [...document.querySelectorAll("dialog")].map((d) => ({ open: d.open, html: d.outerHTML.slice(0, 4000) })) }))()'],
    ['op' => 'type', 'text' => 'users.del', 'tag' => 't'], ['op' => 'wait', 'ms' => 800],
    ['op' => 'eval', 'tag' => 'after-type', 'expr' => '(() => ({ active: document.activeElement ? document.activeElement.outerHTML.slice(0, 600) : null, dialogs: [...document.querySelectorAll("dialog")].map((d) => ({ open: d.open, html: d.outerHTML.slice(0, 8000) })) }))()'],
    ['op' => 'requests', 'tag' => 'r1'],
    ['op' => 'key', 'key' => 'ArrowDown', 'code' => 'ArrowDown', 'keyCode' => 40, 'tag' => 'down'],
    ['op' => 'eval', 'tag' => 'after-down', 'expr' => '(() => ({ active: document.activeElement ? document.activeElement.outerHTML.slice(0, 600) : null, sel: [...document.querySelectorAll("[aria-selected=true]")].map((e) => e.outerHTML.slice(0, 400)) }))()'],
    ['op' => 'key', 'key' => 'Escape', 'code' => 'Escape', 'keyCode' => 27, 'tag' => 'esc'],
    ['op' => 'eval', 'tag' => 'after-esc', 'expr' => '(() => ({ active: document.activeElement ? document.activeElement.outerHTML.slice(0, 300) : null, open: [...document.querySelectorAll("dialog")].map((d) => d.open) }))()'],
];
$job = ['profile' => "$WORK/chrome-x", 'steps' => $steps, 'timeout' => 120000, 'settle' => 400];
file_put_contents("$WORK/xjob.json", json_encode($job));
$p = proc_open(['node', __DIR__ . '/verify-u3.browser.mjs', "$WORK/xjob.json"], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
$o = stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); proc_close($p);
$out .= "=== BROWSER\n" . json_encode(json_decode(trim($o), true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
file_put_contents(__DIR__ . '/verify-u3.x.' . ($GLOBALS['LABEL'] ?? 'run') . '.txt', $out);
info('probe written');
