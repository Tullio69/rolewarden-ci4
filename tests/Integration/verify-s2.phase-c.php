<?php
// Phase C (S2, second re-verification, 09e9df8): matrix columns translated. SPEC "Esiti di S2, decisi dall'utente" (action
// names, the matrix columns, actions.{action} in RoleWardenData, without a translation the slug; slugs in code and
// database unchanged); documentation/views.html "Role and permission names". Run by verify-s2.s1.php (S1 harness).
// Four matrices (role and user, editable by the owner and read-only for a viewer), read in Chrome, in Italian and English.
$now = date('Y-m-d H:i:s');
$CEN = array_replace((require "$MOD/src/Language/en/RoleWardenData.php")['actions'] ?? [], (require "$MODFULL/demo/src/Language/en/RoleWardenData.php")['actions'] ?? []);
$CIT = array_replace((require "$MOD/src/Language/it/RoleWardenData.php")['actions'] ?? [], (require "$MODFULL/demo/src/Language/it/RoleWardenData.php")['actions'] ?? []);
$MARK = '<i>Pubblica</i> & "q" <script>alert(72)</script>';
check('C00', 'Data files: actions.{action} in English and Italian for the same actions (module and demo: ' . count($CEN) . ')', $CEN !== [] && array_keys($CEN) == array_keys($CIT) && ! array_filter($CIT, fn ($v) => trim($v) === ''), json_encode([$CEN, $CIT], JSON_UNESCAPED_UNICODE));
// application permissions: an action translated by the application (with markup in Italian), one without any translation
foreach (['articles.publish' => 'Publish articles', 'warehouse.recount' => 'Recount the warehouse'] as $slug => $d) q('INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES (?,?,?,0,?,?)', [$slug, explode('.', $slug)[0], $d, $now, $now]);
@mkdir("$APP/app/Language/en", 0777, true); @mkdir("$APP/app/Language/it", 0777, true);
file_put_contents("$APP/app/Language/en/RoleWardenData.php", "<?php\n\nreturn [\n    'actions' => ['publish' => 'publish'],\n];\n");
file_put_contents("$APP/app/Language/it/RoleWardenData.php", "<?php\n\nreturn [\n    'actions' => ['publish' => " . var_export($MARK, true) . "],\n];\n");
$cr = function (string $slug, string $name, array $perms) use ($now): int { q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,?,?)', [$slug, $name, '', $now, $now]); $id = role_id($slug); foreach ($perms as $p) q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$id, perm_id($p)]); return $id; };
$CR = $cr('c-editors', 'Editors C', ['users.view']);
$CV = $cr('c-viewer', 'Viewer C', ['roles.view', 'users.view', 'permissions.view']);
$CU = make_owner('cuser@s2.test', OPW, 's2cuser', 'c-editors');
make_owner('cviewer@s2.test', OPW, 's2cviewer', 'c-viewer');
$VIDC = uid('cviewer@s2.test');
sp8($APP, ['cache:clear']);
$ACTS = array_column(q("SELECT DISTINCT SUBSTRING_INDEX(slug, '.', -1) AS a FROM acl_permissions ORDER BY a"), 'a');
$want = ['it' => [], 'en' => []];
foreach ($ACTS as $a) { $want['it'][$a] = $a === 'publish' ? $MARK : ($CIT[$a] ?? $a); $want['en'][$a] = $a === 'publish' ? 'publish' : ($CEN[$a] ?? $a); }
info('expected Italian column names: ' . json_encode($want['it'], JSON_UNESCAPED_UNICODE));
$setLangC = function (int $uid, ?string $l) use ($now) { q("DELETE FROM settings WHERE `key`='locale' AND context=?", ["user:$uid"]); if ($l) q("INSERT INTO settings (class, `key`, value, type, context, created_at, updated_at) VALUES ('RoleWarden\\\\Config\\\\RoleWarden','locale',?,'string',?,?,?)", [$l, "user:$uid", $now, $now]); };
// every column head (text, markup, the box's aria-label) and every attribute of the cells and boxes except labels
$COLS = <<<'JS'
(() => { const t = document.querySelector('table.rw-matrix'); if (!t) return null;
  const heads = [...t.querySelectorAll('thead th.rw-m-act')].map((th) => { const h = th.querySelector('.rw-header') || th; const box = th.querySelector('input');
    return { text: h.textContent.trim(), html: h.innerHTML.trim(), aria: box ? box.getAttribute('aria-label') : null }; });
  const attrs = [...t.querySelectorAll('input, button, .rw-cell, [data-slug], [data-permission]')].filter((e) => e.name !== 'csrf_test_name').map((e) => [...e.attributes].filter((a) => !['aria-label', 'title', 'class', 'aria-pressed', 'style'].includes(a.name) && !a.name.startsWith('x-') && !a.name.startsWith(':') && !a.name.startsWith('@')).map((a) => a.name + '=' + a.value).join(' '));
  const rows = [...t.querySelectorAll('tbody tr')].map((tr) => { const c = tr.querySelector('th.rw-m-area code'); return c ? c.textContent.trim() : null; }).filter(Boolean);
  return { heads, attrs, rows, scripts: [...document.querySelectorAll('script')].map((s) => s.textContent).join('\n').length }; })()
JS;
$pagesC = ['role' => "rolewarden/roles/$CR", 'user' => "rolewarden/users/$CU"];
$resC = [];
foreach (['it', 'en'] as $l) {
    $setLangC($OWNER, $l === 'it' ? 'it' : null); $setLangC($VIDC, $l === 'it' ? 'it' : null);
    serve8($APP, "C-$l");
    foreach (['owner' => 'owner@v8b.test', 'viewer' => 'cviewer@s2.test'] as $who => $em) {
        $st = [blogin($em, OPW)];
        foreach ($pagesC as $k => $u) { $st[] = ['op' => 'goto', 'url' => BASE . $u, 'tag' => "g $k"]; $st[] = ['op' => 'eval', 'expr' => $COLS, 'tag' => "c $k"]; }
        $r = browser($st, "columns $l $who");
        foreach ($pagesC as $k => $u) $resC[$l]["$who $k"] = bval($r, "c $k");
    }
    // the raw HTML the server sends (markup check outside Alpine)
    $cc = c8("C-raw-$l"); login8($cc, 'owner@v8b.test', OPW);
    $resC["raw-$l"] = G($cc, "rolewarden/roles/$CR", "C raw $l") . G($cc, "rolewarden/users/$CU", "C raw user $l");
    server_stop();
}
foreach (['it', 'en'] as $l) {
    $bad = []; $n = 0; $aria = [];
    foreach ($resC[$l] as $key => $m) {
        if (! is_array($m) || ! $m['heads']) { $bad[] = "$key: no matrix"; continue; }
        $names = array_map(fn ($h) => $h['text'], $m['heads']);
        $exp = array_values($want[$l]); sort($exp); $got = $names; sort($got);
        if ($got != $exp) $bad[] = "$key: columns " . json_encode($names, JSON_UNESCAPED_UNICODE);
        foreach ($m['heads'] as $h) {
            $n++;
            if ($h['aria'] !== null) { $aria[] = $h['aria']; if (! str_contains($h['aria'], $h['text'])) $bad[] = "$key: aria-label " . json_encode($h['aria'], JSON_UNESCAPED_UNICODE) . ' without ' . json_encode($h['text'], JSON_UNESCAPED_UNICODE); }
            if (str_contains($h['html'], '<script') || str_contains($h['html'], '<i>')) $bad[] = "$key: markup not escaped " . $h['html'];
        }
        if ($key === 'owner role' && ! array_filter($m['heads'], fn ($h) => $h['aria'] !== null)) $bad[] = "$key: editable matrix without column boxes";
    }
    info("[$l] column heads read: $n; aria-labels " . json_encode(array_slice(array_values(array_unique($aria)), 0, 5), JSON_UNESCAPED_UNICODE));
    check("C01-$l", "[$l] Column heads of the four matrices (role and user, editable and read-only): action name in that language, the application's translation, the slug without one; column box aria-label with the same name; markup escaped", $bad === [] && $n > 0, implode(' | ', array_slice($bad, 0, 10)));
}
check('C02', 'Italian page source: the application translation with markup is escaped (no raw <script> or <i>)', ! str_contains($resC['raw-it'], '<script>alert(72)') && ! str_contains($resC['raw-it'], '<i>Pubblica</i>'), '');
$same = true; $diff = [];
foreach (['owner role', 'owner user', 'viewer role', 'viewer user'] as $k) if (($resC['it'][$k]['attrs'] ?? 1) !== ($resC['en'][$k]['attrs'] ?? 2) || ($resC['it'][$k]['rows'] ?? 1) !== ($resC['en'][$k]['rows'] ?? 2)) { $same = false; $a1 = $resC['it'][$k]['attrs'] ?? []; $a2 = $resC['en'][$k]['attrs'] ?? []; $diff[] = [$k, count($a1), count($a2), array_slice(array_values(array_diff($a1, $a2)), 0, 3), array_slice(array_values(array_diff($a2, $a1)), 0, 3)]; }
check('C03', 'The matrices carry the same slugs and values in Italian and English: every attribute of cells and boxes (labels aside) and every row slug identical', $same, json_encode($diff));
// saving in Italian: a role matrix cell and a user override, by clicking as a user would
$FINDC = "const cellAt = (area, colText) => { const t = document.querySelector('table.rw-matrix'); const heads = [...t.querySelectorAll('thead th.rw-m-act')].map((th) => (th.querySelector('.rw-header') || th).textContent.trim()); const i = heads.indexOf(colText); if (i < 0) return null; for (const tr of t.querySelectorAll('tbody tr')) { const c = tr.querySelector('th.rw-m-area code'); if (c && c.textContent.trim() === area + '.*') return tr.querySelectorAll('td.rw-m-act')[i].querySelector('.rw-cell'); } return null; }; const btn = (re) => [...document.querySelectorAll('main button')].find((b) => re.test(b.textContent.trim()));";
$clickC = fn (string $area, string $col, string $tag) => ['op' => 'eval', 'expr' => "(() => { $FINDC const b = cellAt(" . json_encode($area) . ', ' . json_encode($col) . "); if (!b) return 'nocell'; b.click(); return 'ok'; })()", 'tag' => $tag, 'wait' => 300];
$saveC = fn (string $tag) => ['op' => 'eval', 'expr' => "(() => { $FINDC const b = btn(/^Salva\\b/); if (!b) return 'nobtn'; if (b.disabled) return 'disabled'; b.click(); return 'ok'; })()", 'tag' => $tag, 'wait' => 2500];
$setLangC($OWNER, 'it');
serve8($APP, 'C-save');
$r = browser([blogin('owner@v8b.test', OPW),
    ['op' => 'goto', 'url' => BASE . "rolewarden/roles/$CR", 'tag' => 'g r'], $clickC('articles', html_entity_decode(htmlspecialchars($MARK)), 'click role'), $clickC('users', $CIT['delete'], 'click role 2'), $saveC('save role'),
    ['op' => 'goto', 'url' => BASE . "rolewarden/users/$CU", 'tag' => 'g u'], $clickC('warehouse', 'recount', 'click user'), $saveC('save user'),
], 'columns save it');
server_stop();
$rp = array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? ORDER BY p.slug', [$CR]), 'slug');
$up = q('SELECT p.slug, up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=?', [$CU]);
info('save clicks: ' . json_encode(array_map(fn ($s) => [$s['tag'] ?? '', $s['value'] ?? null], $r['steps'])));
check('C04', 'Italian role matrix: clicking the translated columns (application «Pubblica», «eliminare») and saving stores articles.publish and users.delete', $rp == ['articles.publish', 'users.delete', 'users.view'], json_encode($rp));
check('C05', 'Italian user matrix: a click in the untranslated column «recount» and saving stores the override warehouse.recount', count($up) === 1 && $up[0]['slug'] === 'warehouse.recount', json_encode($up));
$setLangC($OWNER, null); $setLangC($VIDC, null);
@unlink("$APP/app/Language/en/RoleWardenData.php"); @unlink("$APP/app/Language/it/RoleWardenData.php");
q('DELETE FROM acl_user_permissions WHERE user_id=?', [$CU]);
q("DELETE FROM acl_role_permissions WHERE permission_id IN (SELECT id FROM acl_permissions WHERE area IN ('articles','warehouse'))");
q("DELETE FROM acl_permissions WHERE area IN ('articles','warehouse')");
sp8($APP, ['cache:clear']);
