<?php
// Phase A (S1, third re-check, d665818): permission area names translated. SPEC "Esiti di S1" (A1 decided: area names
// are translated, areas.{area} in RoleWardenData, without a translation the slug with a capital letter, on the
// Permissions page and in the matrix rows); documentation/views.html "Role and permission names". The slug area.*
// stays as it is. Matrices are rendered by Alpine: read in Chrome (role and user, editable and read-only).
$flatA = function (array $a, string $p = '') use (&$flatA): array { $o = []; foreach ($a as $k => $v) is_array($v) ? $o += $flatA($v, "$p$k.") : $o["$p$k"] = (string) $v; return $o; };
$AEN = array_replace((require "$MOD/src/Language/en/RoleWardenData.php")['areas'] ?? [], (require "$MODFULL/demo/src/Language/en/RoleWardenData.php")['areas'] ?? []);
$AIT = array_replace((require "$MOD/src/Language/it/RoleWardenData.php")['areas'] ?? [], (require "$MODFULL/demo/src/Language/it/RoleWardenData.php")['areas'] ?? []);
check('A00', 'Data files: areas.{area} in English and Italian for the same areas (module and demo: ' . count($AEN) . ')', $AEN !== [] && array_keys($AEN) == array_keys($AIT) && ! array_filter($AIT, fn ($v) => trim($v) === ''), json_encode([$AEN, $AIT], JSON_UNESCAPED_UNICODE));
// the application's own areas: one translated as views.html says, one with markup, one without any translation
$now = date('Y-m-d H:i:s');
foreach (['articles.publish' => 'Publish articles', 'marked.view' => 'See marked things', 'warehouse.count' => 'Count the warehouse'] as $slug => $d) q('INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES (?,?,?,0,?,?)', [$slug, explode('.', $slug)[0], $d, $now, $now]);
@mkdir("$APP/app/Language/en", 0777, true); @mkdir("$APP/app/Language/it", 0777, true);
file_put_contents("$APP/app/Language/en/RoleWardenData.php", "<?php\n\nreturn [\n    'areas' => ['articles' => 'Articles', 'marked' => 'Marked'],\n    'permissions' => ['articles' => ['publish' => 'Publish articles'], 'marked' => ['view' => 'See marked things']],\n];\n");
file_put_contents("$APP/app/Language/it/RoleWardenData.php", "<?php\n\nreturn [\n    'areas' => ['articles' => 'Articoli', 'marked' => '<i>Marcata</i> & \"q\" <script>alert(71)</script>'],\n    'permissions' => ['articles' => ['publish' => 'Pubblicare articoli'], 'marked' => ['view' => 'Vedere le cose marcate']],\n];\n");
$VRA = (function () use ($now) { q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,?,?)', ['a-viewer', 'Viewer A', '', $now, $now]); $id = role_id('a-viewer'); foreach (['roles.view', 'users.view', 'permissions.view'] as $p) q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$id, perm_id($p)]); return $id; })();
make_owner('aviewer@s1.test', OPW, 's1aviewer', 'a-viewer');
sp8($APP, ['cache:clear']);
$AREAS = array_column(q('SELECT DISTINCT area FROM acl_permissions ORDER BY area'), 'area');
$wantIt = []; $wantEn = [];
foreach ($AREAS as $a) { $wantIt[$a] = $a === 'articles' ? 'Articoli' : ($a === 'marked' ? '<i>Marcata</i> & "q" <script>alert(71)</script>' : ($AIT[$a] ?? ucfirst($a))); $wantEn[$a] = $a === 'articles' ? 'Articles' : ($a === 'marked' ? 'Marked' : ($AEN[$a] ?? ucfirst($a))); }
info('expected Italian area names: ' . json_encode($wantIt, JSON_UNESCAPED_UNICODE));
$setLang = function (int $uid, ?string $l) use ($now) { q("DELETE FROM settings WHERE `key`='locale' AND context=?", ["user:$uid"]); if ($l) q("INSERT INTO settings (class, `key`, value, type, context, created_at, updated_at) VALUES ('RoleWarden\\\\Config\\\\RoleWarden','locale',?,'string',?,?,?)", [$l, "user:$uid", $now, $now]); };
$VID = uid('aviewer@s1.test');
$cA = serve8($APP, 'A');
$titles = function (string $h): array { preg_match_all('#<div class="rw-section-title">.*?<h2>(.*?)</h2>#s', $h, $m); return array_map(fn ($t) => html_entity_decode(trim($t), ENT_QUOTES | ENT_HTML5), $m[1]); };
$rawTitles = function (string $h): array { preg_match_all('#<div class="rw-section-title">.*?<h2>(.*?)</h2>#s', $h, $m); return $m[1]; };
foreach (['it' => $wantIt, 'en' => $wantEn] as $l => $want) {
    $setLang($OWNER, $l === 'it' ? 'it' : null);
    $c = c8("A-$l"); login8($c, 'owner@v8b.test', OPW);
    $h = G($c, 'rolewarden/permissions', "A permissions $l");
    $got = $titles($h);
    $exp = array_values($want); sort($exp); $g2 = $got; sort($g2);
    check("A01-$l", "[$l] Permissions page: every area title is the area name in that language (" . count($AREAS) . ' areas, module, demo, application; untranslated ones capitalised)', $g2 == $exp, json_encode(['got' => $got, 'expected' => array_values($want)], JSON_UNESCAPED_UNICODE));
    if ($l === 'it') {
        $raw = $rawTitles($h);
        check('A04', 'Italian area name with markup (application translation) is escaped in the Permissions title', ! str_contains($h, '<script>alert(71)</script>') && ! str_contains($h, '<i>Marcata</i>') && in_array($wantIt['marked'], $got, true), json_encode($raw, JSON_UNESCAPED_UNICODE));
        $en = array_values(array_filter($got, fn ($t) => in_array($t, array_values(array_diff_assoc($AEN, $AIT)), true)));
        check('A02', 'Italian Permissions page: no English area name of the data files among the titles', $en === [], json_encode($en));
    }
}
server_stop();
// matrices in Chrome
$ROWS = <<<'JS'
(() => { const t = document.querySelector('table.rw-matrix'); if (!t) return null;
  return [...t.querySelectorAll('tbody tr')].map((tr) => { const a = tr.querySelector('th.rw-m-area'); if (!a) return null; const b = a.querySelector('b'), c = a.querySelector('code'); const box = tr.querySelector('td.rw-m-all input');
    return { name: b ? b.textContent.trim() : null, nameHtml: b ? b.innerHTML : null, code: c ? c.textContent.trim() : null, aria: box ? box.getAttribute('aria-label') : null }; }).filter(Boolean); })()
JS;
$RR = role_id('demo-approver');
$pages = ['role' => "rolewarden/roles/$RR", 'user' => "rolewarden/users/$PLAIN"];
$res = [];
foreach (['it', 'en'] as $l) {
    $setLang($OWNER, $l === 'it' ? 'it' : null); $setLang($VID, $l === 'it' ? 'it' : null);
    $cA = serve8($APP, "A-$l");
    foreach (['owner' => 'owner@v8b.test', 'viewer' => 'aviewer@s1.test'] as $who => $em) {
        $st = [blogin($em, OPW)];
        foreach ($pages as $k => $u) { $st[] = ['op' => 'goto', 'url' => BASE . $u, 'tag' => "g $k"]; $st[] = ['op' => 'eval', 'expr' => $ROWS, 'tag' => "r $k"]; }
        $r = browser($st, "areas $l $who");
        foreach ($pages as $k => $u) $res[$l]["$who $k"] = bval($r, "r $k");
    }
    server_stop();
}
foreach (['it' => $wantIt, 'en' => $wantEn] as $l => $want) {
    $bad = []; $n = 0;
    foreach ($res[$l] as $key => $rows) {
        if (! is_array($rows) || $rows === []) { $bad[] = "$key: no matrix rows"; continue; }
        foreach ($rows as $row) {
            $n++;
            $area = preg_replace('/\.\*$/', '', (string) $row['code']);
            if ($row['code'] !== "$area.*" || ! isset($want[$area])) { $bad[] = "$key: code " . json_encode($row['code']); continue; }
            if ($row['name'] !== $want[$area]) $bad[] = "$key $area: name " . json_encode($row['name'], JSON_UNESCAPED_UNICODE) . ' expected ' . json_encode($want[$area], JSON_UNESCAPED_UNICODE);
            if ($row['aria'] !== null && ! str_contains($row['aria'], $want[$area])) $bad[] = "$key $area: aria-label " . json_encode($row['aria'], JSON_UNESCAPED_UNICODE);
            if ($area === 'marked' && $row['nameHtml'] !== null && (str_contains($row['nameHtml'], '<script') || str_contains($row['nameHtml'], '<i>'))) $bad[] = "$key marked: markup not escaped " . $row['nameHtml'];
        }
    }
    $aria = array_values(array_filter(array_map(fn ($r) => $r['aria'], array_merge(...array_values(array_filter($res[$l], 'is_array'))))));
    info("[$l] matrix rows read: $n; sample aria-labels " . json_encode(array_slice(array_unique($aria), 0, 4), JSON_UNESCAPED_UNICODE) . '; read-only matrices with a row box: ' . count(array_filter(array_merge($res[$l]['viewer role'] ?? [], $res[$l]['viewer user'] ?? []), fn ($r) => $r['aria'] !== null)));
    check("A03-$l", "[$l] Matrix rows (role and user, editable by the owner and read-only for a viewer): area name in that language, untranslated ones capitalised, the slug area.* unchanged, row-box aria-label with the same name, markup escaped", $bad === [] && $n > 0, implode(' | ', array_slice($bad, 0, 12)));
}
$setLang($OWNER, null); $setLang($VID, null);
@unlink("$APP/app/Language/en/RoleWardenData.php"); @unlink("$APP/app/Language/it/RoleWardenData.php");
q("DELETE FROM acl_permissions WHERE area IN ('articles','marked','warehouse')");
sp8($APP, ['cache:clear']);
