<?php
/** U3 probe X2 (Collaudatore ad Hoc): exploration only. Deactivation and revocation through the panel vs a direct write; markup of Check a permission with a permission chosen; the odd UTF-8 query in this environment. */
require_once __DIR__ . '/verify-u1.lib.php';
$GLOBALS['U1DIR'] = dirname(__DIR__ . '/verify-u1.lib.php');
serve8($APP, 'X2');
$own = c8('x2-owner'); login8($own, 'owner@v8b.test', OPW);
$a = mkuser('u3-x2a@example.com', 'admin'); $b = mkuser('u3-x2b@example.com', 'admin');
$ca = c8('x2-a'); info('login a ' . json_encode(login8($ca, 'u3-x2a@example.com', U1PW)));
$cb = c8('x2-b'); info('login b ' . json_encode(login8($cb, 'u3-x2b@example.com', U1PW)));
// a: deactivated through the panel
$h = G($own, "rolewarden/users/$a", 'x2 user a');
foreach (forms($h) as $f) if ($f['method'] === 'post') info('form on user page: ' . u1path($f['action']) . ' ' . json_encode(array_keys($f['fields'])) . ' ' . short($f['text'], 80));
foreach (forms($h) as $f) if ($f['method'] === 'post' && preg_match('#/(deactivate|toggle|status|activate)#', u1path($f['action']))) { P($own, $f['action'], $f['fields'], 'x2 deactivate'); info('deactivate: ' . $own->status . ' active=' . q1('SELECT active FROM users WHERE id=?', [$a])); break; }
G($ca, 'rolewarden/users', 'x2 a users'); info("a after panel deactivation: users {$ca->status} {$ca->location}");
G($ca, 'rolewarden/search?q=users', 'x2 a search'); info("a search {$ca->status} {$ca->location} results " . substr_count($ca->body, 'rw-cmd-opt'));
// b: direct write, then cache:clear
q('UPDATE users SET active=0 WHERE id=?', [$b]);
G($cb, 'rolewarden/users', 'x2 b users'); info("b after direct write: users {$cb->status}");
sp8($APP, ['cache:clear']);
G($cb, 'rolewarden/users', 'x2 b users2'); info("b after cache:clear: users {$cb->status} {$cb->location}");
G($cb, 'rolewarden/search?q=users', 'x2 b search'); info("b search {$cb->status} results " . substr_count($cb->body, 'rw-cmd-opt'));
$h = G($own, 'rolewarden/permissions/check?permission=users.view', 'x2 check');
$m = dom($h)->query('//main//form')->item(0);
info('check form: ' . short($m ? $m->ownerDocument->saveHTML($m) : 'none', 3000));
foreach (['development'] as $e) { $own->req('GET', 'rolewarden/search?q=%FF%FE'); info("badutf page {$own->status}"); $own->req('GET', 'rolewarden/search?q=%C3%28'); info("badutf2 {$own->status}"); $own->req('GET', 'rolewarden/permissions/check?permission=%FF'); info("check badutf {$own->status}"); $own->req('GET', 'rolewarden/users?q=%FF%FE'); info("users list badutf {$own->status}"); }
