<?php
// U1 phase X (exploration only, no check): the HTML and CSS a browser receives and the database schema, saved in the
// scratchpad folder RW_U1_PROBE (outside the repository) to locate the sidebar's elements.
serve8($APP, 'X');
$dir = getenv('RW_U1_PROBE');
@mkdir($dir, 0777, true);
foreach (['acl_roles', 'acl_permissions', 'acl_role_permissions', 'acl_user_permissions', 'acl_user_roles', 'acl_activity_log', 'settings', 'auth_logins', 'users'] as $t) info("$t: " . json_encode(array_column(q("SHOW COLUMNS FROM `$t`"), 'Field')));
info('settings rows: ' . json_encode(q('SELECT class, `key`, value, type, context FROM settings')));
info('migrations: ' . json_encode(q('SELECT version, class, namespace, batch FROM migrations')));
info('users: ' . json_encode(q('SELECT id, username, active, deleted_at FROM users')));
$c = c8('x-owner');
login8($c, 'owner@v8b.test', OPW);
$h = G($c, 'rolewarden/users');
preg_match('/href="([^"]*panel\.css[^"]*)"/', $h, $m);
$css = c8('x-css'); G($css, html_entity_decode($m[1]));
file_put_contents("$dir/panel.css", $css->body);
$f = null; foreach (forms($h) as $ff) if (str_ends_with($ff['action'], 'rolewarden/sidebar')) $f = $ff;
P($c, $f['action'], $f['fields']);
info("collapse POST -> {$c->status} {$c->location}");
file_put_contents("$dir/owner-rail.html", G($c, 'rolewarden/roles'));
P($c, $f['action'], ['collapsed' => '0'] + $f['fields']);
info("collapse POST without fresh token -> {$c->status} {$c->location}");
G($c, 'rolewarden/activity');
file_put_contents("$dir/owner-after-activity.html", G($c, 'rolewarden/users'));
info('settings rows after: ' . json_encode(q('SELECT class, `key`, value, type, context FROM settings')));
$it = forms(G($c, 'rolewarden/users'));
foreach ($it as $ff) if (str_ends_with($ff['action'], 'profile/language')) { P($c, $ff['action'], $ff['fields'] + ['language' => 'it']); }
file_put_contents("$dir/owner-it.html", G($c, 'rolewarden/users'));
