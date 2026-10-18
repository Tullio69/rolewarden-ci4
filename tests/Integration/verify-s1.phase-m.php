<?php
// S1 copy of verify-v8b.phase-m.php (7560117): the matrix rows now show the translated area name (A1 of S1, decided:
// SPEC "Esiti di S1"), so the area of a row is read from its slug (<code>area.*</code>) instead of the name. Nothing else changed.
// Phase M: permission matrices (design system "Permission marks", PermissionMatrix README, CHANGELOG "the role
// matrix marks every permission not yet granted and says how to grant it"). Rendered by Alpine: measured in Chrome.
// Fixtures written in the database (roles, grants, overrides), then the cache cleared with spark.
$now = date('Y-m-d H:i:s');
$mk = function (string $slug, string $name, ?int $parent, array $perms) use ($now): int {
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,?,0,0,?,?)', [$slug, $name, '', $parent, $now, $now]);
    $id = role_id($slug);
    foreach ($perms as $p) q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$id, perm_id($p)]);
    return $id;
};
$GP = $mk('m-gp', 'Grandparent M', null, ['users.view']);
$PA = $mk('m-par', 'Parent M', $GP, ['users.update']);
$KID = $mk('m-kid', 'Kid M', $PA, ['users.create', 'roles.delete']);
$U1 = make_owner('kid1@v8b.test', OPW, 'v8bkid1', 'm-kid');
$U2 = make_owner('kid2@v8b.test', OPW, 'v8bkid2', 'm-kid');
q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,1,?,?)', [$U1, perm_id('activity.view'), $now, $now]);
q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,0,?,?)', [$U2, perm_id('users.create'), $now, $now]);
// read-only viewer: roles.view and users.view, nothing that edits
$VR = $mk('m-viewer', 'Viewer M', null, ['roles.view', 'users.view']);
$V = make_owner('viewer@v8b.test', OPW, 'v8bviewer', 'm-viewer');
sp8($APP, ['cache:clear']);
$ALLP = array_column(q('SELECT slug FROM acl_permissions'), 'slug');

$MAT = <<<'JS'
(() => {
  const t = document.querySelector('table.rw-matrix'); if (!t) return null;
  const acts = [...t.querySelectorAll('thead th.rw-m-act')].map((th) => (th.querySelector('.rw-header') || th).textContent.trim());
  const cells = {}, empties = [];
  for (const tr of t.querySelectorAll('tbody tr')) {
    const a = tr.querySelector('th.rw-m-area code'); if (!a) continue; const area = a.textContent.trim().replace('.*', '');
    [...tr.querySelectorAll('td.rw-m-act')].forEach((td, i) => {
      const k = area + '.' + acts[i]; const b = td.querySelector('.rw-cell');
      if (!b) { empties.push({ k, text: td.textContent.trim(), marks: td.querySelectorAll('svg, .rw-mark-blank').length }); return; }
      const m = b.querySelector('svg.rw-mark, .rw-mark-blank');
      let mark = 'none', dashed = null;
      if (m) { if (m.classList.contains('rw-mark-blank')) { mark = 'blank'; const cs = getComputedStyle(m); const r = m.getBoundingClientRect(); dashed = { style: cs.borderTopStyle, w: r.width, h: r.height, color: cs.borderTopColor }; } else mark = ([...m.classList].find((c) => c.startsWith('rw-mark--')) || 'rw-mark--?').slice(9); }
      cells[k] = { mark, dashed, tag: b.tagName.toLowerCase(), title: b.title, pressed: b.getAttribute('aria-pressed'), disabled: b.getAttribute('aria-disabled'), changed: b.classList.contains('rw-cell--changed') };
    });
  }
  const help = document.querySelector('.rw-matrix-help'); const helpBefore = help ? !!(help.compareDocumentPosition(t) & Node.DOCUMENT_POSITION_FOLLOWING) : null;
  const btns = [...document.querySelectorAll('main button')].map((b) => ({ t: b.textContent.trim(), d: b.disabled }));
  const legend = [...document.querySelectorAll('.rw-legend li')].map((li) => li.textContent.trim());
  const faint = getComputedStyle(document.documentElement).getPropertyValue('--ink-faint').trim();
  const hint = [...document.querySelectorAll('main .rw-note, main p, main span')].map((e) => e.textContent.trim()).filter((s) => /^(Click|Fai clic)/.test(s));
  return { acts, cells, empties, help: help ? help.textContent.trim() : null, helpBefore, btns, legend, hint, faint, rows: t.querySelectorAll('tbody tr').length };
})()
JS;
$FIND = "const cell = (k) => { const t = document.querySelector('table.rw-matrix'); const acts = [...t.querySelectorAll('thead th.rw-m-act')].map((th) => (th.querySelector('.rw-header') || th).textContent.trim()); const [area, act] = k.split('.'); for (const tr of t.querySelectorAll('tbody tr')) { const a = tr.querySelector('th.rw-m-area code'); if (a && a.textContent.trim().replace('.*', '') === area) return tr.querySelectorAll('td.rw-m-act')[acts.indexOf(act)].querySelector('button.rw-cell'); } return null; }; const btn = (re) => [...document.querySelectorAll('main button')].find((b) => re.test(b.textContent.trim()));";
$click = fn (string $k, string $tag) => ['op' => 'eval', 'expr' => "(() => { $FIND const b = cell(" . json_encode($k) . "); if (!b) return 'nocell'; b.click(); return 'ok'; })()", 'tag' => $tag, 'wait' => 300];
$save = fn (string $tag) => ['op' => 'eval', 'expr' => "(() => { $FIND const b = btn(/^(Save changes|Save overrides|Salva\b.*)$/); if (!b) return 'nobtn'; if (b.disabled) return 'disabled'; b.click(); return 'ok'; })()", 'tag' => $tag, 'wait' => 2500];
$rolePerms = fn (int $rid) => array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? ORDER BY p.slug', [$rid]), 'slug');

$cM = serve8($APP, 'matrix');
$steps = [blogin('owner@v8b.test', OPW),
    ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$KID", 'tag' => 'g kid'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'kid0'],
    ['op' => 'screenshot', 'path' => "$WORK/verify-v8b.matrix-role.png", 'full' => true, 'tag' => 'shot kid'],
    $click('users.view', 'click inherited'),
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'kid-inh'],
    $click('roles.view', 'click blank'),
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'kid-clicked'],
    $save('save1'),
    ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$KID", 'tag' => 'g kid2'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'kid-saved'],
    // a granted cell clicked off, then Discard
    $click('roles.delete', 'click off'),
    ['op' => 'eval', 'expr' => "(() => { $FIND const b = btn(/^(Discard|Scarta)$/); if (!b) return 'nobtn'; b.click(); return 'ok'; })()", 'tag' => 'discard', 'wait' => 300],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'kid-discarded'],
    // row: the Row box of the sessions area
    ['op' => 'eval', 'expr' => "(() => { const t = document.querySelector('table.rw-matrix'); for (const tr of t.querySelectorAll('tbody tr')) { const a = tr.querySelector('th.rw-m-area code'); if (a && a.textContent.trim().replace('.*', '') === 'sessions') { const c = tr.querySelector('td.rw-m-all input'); c.click(); return 'ok'; } } return 'norow'; })()", 'tag' => 'row', 'wait' => 300],
    // column: the head box of the delete column
    ['op' => 'eval', 'expr' => "(() => { const t = document.querySelector('table.rw-matrix'); for (const th of t.querySelectorAll('thead th.rw-m-act')) if (th.textContent.trim() === 'delete') { th.querySelector('input').click(); return 'ok'; } return 'nocol'; })()", 'tag' => 'col', 'wait' => 300],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'kid-rowcol'],
    $save('save2'),
    ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$GP", 'tag' => 'g gp'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'gp'],
    ['op' => 'goto', 'url' => BASE . "rolewarden/users/$U1", 'tag' => 'g u1'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'u1'],
    ['op' => 'screenshot', 'path' => "$WORK/verify-v8b.matrix-user.png", 'full' => true, 'tag' => 'shot u1'],
    $click('roles.create', 'u1 click blank'),
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'u1-clicked'],
    $save('u1 save'),
    ['op' => 'goto', 'url' => BASE . "rolewarden/users/$U1", 'tag' => 'g u1b'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'u1-saved'],
    ['op' => 'goto', 'url' => BASE . "rolewarden/users/$U2", 'tag' => 'g u2'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'u2'],
    ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$KID", 'tag' => 'g kid final'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'kid-final'],
];
$r = browser($steps, 'matrix-owner', 600000);
@copy("$WORK/verify-v8b.matrix-role.png", __DIR__ . '/verify-s1.v8b.matrix-role.png');
@copy("$WORK/verify-v8b.matrix-user.png", __DIR__ . '/verify-s1.v8b.matrix-user.png');
file_put_contents(__DIR__ . "/verify-s1.v8b.$LABEL.matrix.json", json_encode($r['steps'], JSON_PRETTY_PRINT));
check('M00', 'Chrome ran the owner\'s matrix steps without driver errors or page exceptions', $r['errors'] === [] && ($r['consoleErrors'] ?? []) === [], json_encode([$r['errors'], $r['consoleErrors'] ?? []]));

$m = bval($r, 'kid0');
$exists = function (array $m) use ($ALLP) {
    $bad = [];
    foreach ($m['cells'] as $k => $c) if (! in_array($k, $ALLP, true)) $bad[] = "button on non-existent $k";
    foreach ($m['empties'] as $e) { if (in_array($e['k'], $ALLP, true)) $bad[] = "no button on existing {$e['k']}"; if ($e['marks'] || $e['text'] !== '') $bad[] = "mark in empty {$e['k']}"; }
    return $bad;
};
check('M01', 'Role matrix: every existing area.action has a cell, every combination that does not exist stays empty (no mark)', $m && $exists($m) === [] && count($m['empties']) > 0, $m ? implode(' | ', array_slice($exists($m), 0, 8)) . ' empties=' . count($m['empties']) : 'no matrix');
$exp = ['users.create' => 'deny', 'roles.delete' => 'role', 'users.view' => 'inherit', 'users.update' => 'inherit', 'activity.view' => 'grant'];
$badm = [];
foreach ($m['cells'] ?? [] as $k => $c) { $want = $exp[$k] ?? 'blank'; if ($c['mark'] !== $want) $badm[] = "$k: {$c['mark']} (want $want)"; }
check('M02', 'Role matrix marks: own grant ✓, inherited 〃 (both levels), override grant (✓), override denial on a role grant, every other existing cell the dashed "Not granted" box', $m && $badm === [], implode(' | ', $badm));
$blank = array_filter($m['cells'] ?? [], fn ($c) => $c['mark'] === 'blank');
$bb = array_filter($blank, fn ($c) => ($c['dashed']['style'] ?? '') !== 'dashed' || ($c['dashed']['w'] ?? 0) < 4 || ! str_ends_with($c['title'], '· Not granted'));
check('M03', 'Every "Not granted" box is drawn dashed and visible, with the tooltip "<slug> · Not granted" (' . count($blank) . ' cells)', $blank !== [] && $bb === [], json_encode(array_slice($bb, 0, 3)));
check('M03b', 'Not-granted cells are editable buttons: aria-pressed="false", not aria-disabled', ! array_filter($blank, fn ($c) => $c['pressed'] !== 'false' || $c['disabled'] === 'true'), json_encode(array_slice(array_filter($blank, fn ($c) => $c['pressed'] !== 'false' || $c['disabled'] === 'true'), 0, 3)));
$cv = $m['cells']['users.view'] ?? []; $cu = $m['cells']['users.update'] ?? [];
check('M04', 'Inherited cells locked (aria-disabled) and say where they come from (users.view from Grandparent M, users.update from Parent M)', ($cv['disabled'] ?? '') === 'true' && ($cu['disabled'] ?? '') === 'true' && str_contains($cv['title'] ?? '', 'Grandparent M') && str_contains($cu['title'] ?? '', 'Parent M'), json_encode([$cv, $cu]));
check('M04b', 'Denied override tooltip names the role grant and the user ("Granted by Kid M, denied for 1 user: v8bkid2")', str_contains($m['cells']['users.create']['title'] ?? '', 'Kid M') && str_contains($m['cells']['users.create']['title'] ?? '', 'v8bkid2'), $m['cells']['users.create']['title'] ?? '');
check('M05', 'Instruction line above the editable matrix, from the language file', ($m['help'] ?? null) === L($EN, 'panel.roles.matrixHelp') && $m['helpBefore'] === true, json_encode([$m['help'] ?? null, $m['helpBefore'] ?? null]));
check('M05b', 'Legend shows the five marks, "Not granted" included', count($m['legend'] ?? []) === 5 && in_array('Not granted', $m['legend'] ?? [], true), json_encode($m['legend'] ?? null));
$mi = bval($r, 'kid-inh');
check('M06', 'Clicking an inherited cell changes nothing (mark, pressed state, no unsaved change)', $mi && $mi['cells']['users.view'] == $cv && ! array_filter($mi['cells'], fn ($c) => $c['changed']), json_encode($mi['cells']['users.view'] ?? null));
$mc = bval($r, 'kid-clicked');
check('M07', 'Clicking a "Not granted" cell turns it into a role grant, pressed, marked as changed', ($mc['cells']['roles.view']['mark'] ?? '') === 'role' && ($mc['cells']['roles.view']['pressed'] ?? '') === 'true' && ($mc['cells']['roles.view']['changed'] ?? false), json_encode($mc['cells']['roles.view'] ?? null));
check('M07b', 'Save answered (save button clicked)', bval($r, 'save1') === 'ok', (string) bval($r, 'save1'));
$ms = bval($r, 'kid-saved');
check('M08', 'After saving and reloading, roles.view is a role grant in the matrix', ($ms['cells']['roles.view']['mark'] ?? '') === 'role', json_encode($ms['cells']['roles.view'] ?? null));
$md = bval($r, 'kid-discarded');
check('M09', 'A granted cell clicked off and then Discard: back to the saved state, nothing changed', $md && $md['cells'] == $ms['cells'], '');
$mrc = bval($r, 'kid-rowcol');
$rowk = array_filter(array_keys($mrc['cells'] ?? []), fn ($k) => str_starts_with($k, 'sessions.'));
$colk = array_filter(array_keys($mrc['cells'] ?? []), fn ($k) => str_ends_with($k, '.delete'));
check('M10', 'Row box of an area and head box of a column grant every editable cell of that row/column; locked cells untouched', bval($r, 'row') === 'ok' && bval($r, 'col') === 'ok' && $rowk && $colk && ! array_filter(array_merge($rowk, $colk), fn ($k) => ! in_array($mrc['cells'][$k]['mark'], ['role', 'deny'], true)) && $mrc['cells']['users.view']['mark'] === 'inherit', json_encode(array_intersect_key($mrc['cells'] ?? [], array_flip(array_merge($rowk, $colk)))));
info('save2: ' . json_encode(bval($r, 'save2')));
$want = array_values(array_unique(array_merge(['roles.delete', 'roles.view', 'users.create'], array_values(array_filter($ALLP, fn ($p) => str_starts_with($p, 'sessions.') || str_ends_with($p, '.delete'))))));
sort($want);
check('M11', 'Database after saving: Kid M has exactly its own grants + roles.view + every sessions.* and *.delete (inherited ones not copied)', $rolePerms($KID) === $want, json_encode([$rolePerms($KID), $want]));
check('M11b', 'Parents untouched: Grandparent M {users.view}, Parent M {users.update}', $rolePerms($GP) === ['users.view'] && $rolePerms($PA) === ['users.update'], json_encode([$rolePerms($GP), $rolePerms($PA)]));
$mg = bval($r, 'gp');
check('M12', 'Grandparent matrix: its own grant ✓ and every other existing cell "Not granted"', ($mg['cells']['users.view']['mark'] ?? '') === 'role' && ! array_filter($mg['cells'] ?? [], fn ($c, $k) => $k !== 'users.view' && $c['mark'] !== 'blank', ARRAY_FILTER_USE_BOTH) && $exists($mg) === [], '');

// user page matrix (overrides)
$u = bval($r, 'u1');
check('M13', 'User matrix: existing cells only, non-existent combinations empty', $u && $exists($u) === [], $u ? implode(' | ', array_slice($exists($u), 0, 5)) : 'no matrix');
$mu = []; foreach (['activity.view' => 'grant', 'roles.view' => 'role', 'users.view' => 'role', 'users.update' => 'role', 'users.create' => 'role', 'roles.create' => 'blank', 'settings.view' => 'blank'] as $k => $w) $mu[$k] = $u['cells'][$k]['mark'] ?? null;
info('user matrix marks (selection): ' . json_encode($mu) . ' / users.view title ' . ($u['cells']['users.view']['title'] ?? ''));
check('M14', 'User matrix: override grant (✓) on activity.view, not-granted cells show the dashed box with "· Not granted"', $mu['activity.view'] === 'grant' && $mu['roles.create'] === 'blank' && $mu['settings.view'] === 'blank' && ! array_filter($u['cells'], fn ($c) => $c['mark'] === 'blank' && (! str_ends_with($c['title'], '· Not granted') || ($c['dashed']['style'] ?? '') !== 'dashed')), json_encode($mu));
check('M14b', 'User matrix: what the user\'s roles grant (own role or through parents) is shown as granted, not as "Not granted"', ! in_array($mu['users.view'], ['blank', 'none'], true) && ! in_array($mu['users.update'], ['blank', 'none'], true) && ! in_array($mu['users.create'], ['blank', 'none'], true), json_encode($mu));
check('M15', 'User matrix: instruction line for the editable matrix, from the language file (names the user)', in_array(sprintf(L($EN, 'panel.users.matrixHint'), 'v8bkid1'), $u['hint'] ?? [], true), json_encode($u['hint'] ?? null));
$uc = bval($r, 'u1-clicked');
info('user matrix after click on roles.create: ' . json_encode($uc['cells']['roles.create'] ?? null) . ' save=' . json_encode(bval($r, 'u1 save')));
$ov = q('SELECT p.slug, up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=? ORDER BY p.slug', [$U1]);
check('M16', 'Clicking a "Not granted" cell on the user page adds a grant override that persists (database and reloaded page)', in_array(['slug' => 'roles.create', 'granted' => 1], $ov) && (bval($r, 'u1-saved')['cells']['roles.create']['mark'] ?? '') === 'grant', json_encode([$ov, bval($r, 'u1-saved')['cells']['roles.create'] ?? null]));
$u2 = bval($r, 'u2');
check('M17', 'User matrix: a denial override on a role grant shows the struck mark', ($u2['cells']['users.create']['mark'] ?? '') === 'deny', json_encode($u2['cells']['users.create'] ?? null));

// Italian: instruction lines translated
$ci = c8('it');
login8($ci, 'owner@v8b.test', OPW);
$tok = csrf_of(G($ci, 'rolewarden/profile'));
$ci->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'it'], ['Referer: ' . BASE . 'index.php/rolewarden/profile']);
$h = G($ci, "rolewarden/roles/$KID");
$hu = G($ci, "rolewarden/users/$U1");
check('M18', 'Italian: role matrix instruction and user matrix hint come in Italian; legend "Non concesso" style label translated', str_contains($h, L($IT, 'panel.roles.matrixHelp')) && str_contains(text_of($hu), sprintf(L($IT, 'panel.users.matrixHint'), 'v8bkid1')) && str_contains($h, L($IT, 'panel.roles.legendNone')) && L($IT, 'panel.roles.legendNone') !== 'Not granted', json_encode([str_contains($h, L($IT, 'panel.roles.matrixHelp')), str_contains(text_of($hu), sprintf(L($IT, 'panel.users.matrixHint'), 'v8bkid1')), str_contains($h, L($IT, 'panel.roles.legendNone')), html_lang($h)]));
check('M18b', 'Italian: the "Not granted" tooltip is translated in the matrix data (Non concesso)', str_contains(html_entity_decode($h), '· Non concesso') || str_contains(html_entity_decode($h), '\u00b7 Non concesso'), '');
$tok = csrf_of($h);
$ci->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'en'], ['Referer: ' . BASE . 'index.php/rolewarden/profile']);

// read-only versions: roles.view + users.view only
$before = [$rolePerms($KID), q('SELECT * FROM acl_user_permissions ORDER BY user_id, permission_id')];
$r2 = browser([blogin('viewer@v8b.test', OPW),
    ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$KID", 'tag' => 'g'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'ro'],
    ['op' => 'eval', 'expr' => "(document.querySelector('main') || document.body).outerHTML.slice(0, 400000)", 'tag' => 'ro-html'],
    $click('settings.view', 'ro click'),
    $save('ro save'),
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'ro2'],
    ['op' => 'screenshot', 'path' => "$WORK/verify-v8b.matrix-readonly.png", 'full' => true, 'tag' => 'shot'],
    ['op' => 'goto', 'url' => BASE . "rolewarden/users/$U1", 'tag' => 'gu'],
    ['op' => 'eval', 'expr' => $MAT, 'tag' => 'rou'],
    ['op' => 'eval', 'expr' => "(document.querySelector('main') || document.body).outerHTML.slice(0, 400000)", 'tag' => 'rou-html'],
    $click('settings.view', 'rou click'),
    $save('rou save'),
], 'matrix-viewer');
@copy("$WORK/verify-v8b.matrix-readonly.png", __DIR__ . '/verify-s1.v8b.matrix-readonly.png');
file_put_contents("$WORK/../v8b-ro-role.html", (string) bval($r2, 'ro-html')); file_put_contents("$WORK/../v8b-ro-user.html", (string) bval($r2, 'rou-html'));
file_put_contents(__DIR__ . "/verify-s1.v8b.$LABEL.matrix-readonly.json", json_encode(array_filter($r2['steps'], fn ($s) => ! str_ends_with($s['tag'] ?? '', '-html')), JSON_PRETTY_PRINT));
$ro = bval($r2, 'ro');
$kf = bval($r, 'kid-final');
$u1s = bval($r, 'u1-saved');
$badro = [];
foreach ($kf['cells'] ?? [] as $k => $c) { $g = $ro['cells'][$k]['mark'] ?? 'missing'; if ($g !== $c['mark']) $badro[] = "$k: read-only $g, editable {$c['mark']} (" . ($ro['cells'][$k]['title'] ?? '') . ')'; }
info('owner editable role matrix at the end of the owner steps: activity.view ' . ($kf['cells']['activity.view']['mark'] ?? '?') . ', roles.create ' . ($kf['cells']['roles.create']['mark'] ?? '?'));
check('M19', 'Read-only role matrix (roles.view without roles.update) draws every cell with the same mark as the editable one, "Not granted" boxes included', $ro && $kf && $badro === [] && $exists($ro) === [], implode(' | ', $badro));
check('M19a', 'Read-only role matrix: "Not granted" boxes present and dashed on existing cells, empty where no permission exists', $ro && $exists($ro) === [] && array_filter($ro['cells'], fn ($c) => $c['mark'] === 'blank' && ($c['dashed']['style'] ?? '') === 'dashed') !== [], '');
check('M19b', 'Read-only role matrix: no cell is a button (nothing to click), no instruction line to click and save, no Save', $ro && ! array_filter($ro['cells'], fn ($c) => $c['tag'] === 'button' && $c['disabled'] !== 'true') && $ro['help'] === null && ! array_filter($ro['btns'], fn ($b) => preg_match('/^(Save|Salva)/', $b['t'])), json_encode([$ro['help'] ?? null, $ro['btns'] ?? null]));
$rou = bval($r2, 'rou');
$roErr = array_values(array_filter($rou ? $exists($rou) : ['no matrix'], fn ($e) => ! str_starts_with($e, 'no button on existing')));
check('M19c', 'Read-only user page (users.view only): no hint to click, no clickable cell, no mark on combinations that do not exist', $rou && $rou['hint'] === [] && ! array_filter($rou['cells'], fn ($c) => $c['tag'] === 'button') && $roErr === [], json_encode([$rou['hint'] ?? null, $roErr]));
$badu = [];
foreach ($u1s['cells'] ?? [] as $k => $c) { if ($c['mark'] === 'blank') continue; $g = $rou['cells'][$k]['mark'] ?? 'missing'; if ($g !== $c['mark']) $badu[] = "$k: read-only $g, editable {$c['mark']} (" . ($rou['cells'][$k]['title'] ?? '') . ')'; }
check('M19e', 'Read-only user page: granted cells carry the same mark as on the editable page (override grants as (✓), inherited as 〃)', $rou && $u1s && $badu === [], implode(' | ', $badu));
$nb = array_filter($u1s['cells'] ?? [], fn ($c) => $c['mark'] === 'blank');
$robl = array_filter($rou['cells'] ?? [], fn ($c) => $c['mark'] === 'blank');
info('read-only user page: ' . count($robl) . ' "Not granted" boxes; the editable page of the same user has ' . count($nb));
$miss = array_keys(array_diff_key($nb, $robl)); $extraBl = array_keys(array_diff_key($robl, $nb));
check('M19f', 'Read-only user page: the dashed "Not granted" box on every existing permission the user does not have (as on the editable page), nothing where the permission does not exist (V8b A2 recheck)', $nb !== [] && $miss === [] && $extraBl === [] && ! array_filter($robl, fn ($c) => ($c['dashed']['style'] ?? '') !== 'dashed' || ! str_ends_with($c['title'], '· Not granted')) && $roErr === [], json_encode(['missing' => array_slice($miss, 0, 8), 'extra' => $extraBl, 'empties' => $roErr]));
check('M19d', 'Read-only: clicks and Save attempts change nothing in the database', [$rolePerms($KID), q('SELECT * FROM acl_user_permissions ORDER BY user_id, permission_id')] === $before, json_encode([bval($r2, 'ro save'), bval($r2, 'rou save')]));
server_stop();
