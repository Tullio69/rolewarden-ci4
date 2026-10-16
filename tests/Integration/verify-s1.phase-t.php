<?php
// Phase T (S1, point 3): seeded roles and permissions shown in the reader's language. SPEC 2026-10-16 ("Il testo
// salvato resta quello inglese del seed; nome e descrizione si mostrano nella lingua di chi legge ... finche' il testo
// salvato e' quello del seed. Un testo cambiato da un admin si mostra com'e' scritto in ogni lingua. Un modulo di
// modifica salvato con il testo tradotto invariato non rinomina il ruolo. Vale anche per i ruoli e i permessi della
// demo ... Il registro attivita' conserva le etichette com'erano al momento dell'azione"); documentation/views.html
// "Role and permission names". Language files read for the expected strings only.
$ENVT = getenv('RW_V8B_ENV') ?: 'development';
$flatT = function (array $a, string $p = '') use (&$flatT): array { $o = []; foreach ($a as $k => $v) is_array($v) ? $o += $flatT($v, "$p$k.") : $o["$p$k"] = (string) $v; return $o; };
$DEN = $flatT(array_replace_recursive(require "$MOD/src/Language/en/RoleWardenData.php", require "$MODFULL/demo/src/Language/en/RoleWardenData.php"));
$DIT = $flatT(array_replace_recursive(require "$MOD/src/Language/it/RoleWardenData.php", require "$MODFULL/demo/src/Language/it/RoleWardenData.php"));
check('T00', 'Language data files: every English key has an Italian translation, and only those keys (' . count($DEN) . ' keys, module and demo)', array_keys($DEN) == array_keys($DIT) && ! array_filter($DIT, fn ($v) => trim($v) === ''), json_encode([array_diff_key($DEN, $DIT), array_diff_key($DIT, $DEN)]));
// stored seed text = the English data file (SPEC: "Il testo salvato resta quello inglese del seed")
$stored = [];
foreach (q('SELECT slug, name, description FROM acl_roles WHERE deleted_at IS NULL') as $r) { $stored["roles.{$r['slug']}.name"] = $r['name']; $stored["roles.{$r['slug']}.description"] = (string) $r['description']; }
foreach (q('SELECT slug, description FROM acl_permissions') as $r) $stored['permissions.' . $r['slug']] = (string) $r['description'];
$mism = []; foreach ($DEN as $k => $v) if (($stored[$k] ?? null) !== $v) $mism[] = "$k: stored " . json_encode($stored[$k] ?? null) . ' vs file ' . json_encode($v);
check('T00b', 'The database holds exactly the English text of the data files for every seeded role and permission (module and demo)', $mism === [], implode(' | ', $mism));
$ENONLY = array_filter($DEN, fn ($v, $k) => $DIT[$k] !== $v, ARRAY_FILTER_USE_BOTH);

/** Leaf strings of a page: text of every element without element children, attribute values a person reads, input values. */
function leaves(string $h): array
{
    $xp = dom($h);
    foreach ($xp->query('//script|//style|//noscript|//*[@id="debug-bar"]|//*[contains(@class,"kint")]') as $n) $n->parentNode?->removeChild($n);
    $o = [];
    foreach ($xp->query('//body//*[not(*)]') as $n) { $t = trim(preg_replace('/\s+/u', ' ', $n->textContent)); if ($t !== '') $o[] = $t; }
    foreach ($xp->query('//body//*[*]') as $n) foreach ($n->childNodes as $c) if ($c->nodeType === XML_TEXT_NODE && trim($c->textContent) !== '') $o[] = trim(preg_replace('/\s+/u', ' ', $c->textContent));
    foreach ($xp->query('//body//@title|//body//@aria-label|//body//@placeholder|//body//@data-tip|//body//input[@type="text"]/@value|//body//input[not(@type)]/@value') as $a) if (trim($a->nodeValue) !== '') $o[] = trim($a->nodeValue);
    return array_values(array_unique($o));
}
function vis(string $h): string { return implode(' | ', leaves($h)); }
/** English-only seeded strings found on a page: names as whole leaves, descriptions as substrings. */
function english_left(string $h, array $enonly): array
{
    $lv = leaves($h); $all = ' ' . implode(' | ', $lv) . ' ';
    $f = [];
    foreach ($enonly as $k => $v) {
        $isName = str_ends_with($k, '.name');
        if ($isName ? in_array($v, $lv, true) : str_contains($all, $v)) $f[] = "$k=" . $v;
        // data the page script renders (x-data, JSON): descriptions only, they are long enough to be unambiguous
        elseif (! $isName && strlen($v) > 12 && (str_contains(html_entity_decode($h, ENT_QUOTES | ENT_HTML5), $v) || str_contains($h, substr(json_encode($v), 1, -1)))) $f[] = "$k (in page data)=" . $v;
    }
    return $f;
}
/** The Permissions page titles each area with its capitalised slug: kept apart (ambiguity, not a seeded name). */
function no_area_titles(string $h): string
{
    $areas = array_map(fn ($a) => ucfirst($a), array_column(q('SELECT DISTINCT area FROM acl_permissions'), 'area'));
    return preg_replace_callback('#<h2>([^<]*)</h2>#', fn ($m) => in_array(trim($m[1]), $areas, true) ? '<h2></h2>' : $m[0], $h);
}
function missing(string $h, array $want): array { $all = ' ' . vis($h) . ' '; return array_values(array_filter($want, fn ($w) => ! str_contains($all, $w))); }

write_env($APP, $ENVT, "demo.showcase = true\n");
$EO = make_owner('enowner@s1.test', OPW, 's1enowner', 'super-admin');
$cT = serve8($APP, 'T');
$it = c8('T-it'); check('T01a', 'Owner signs in and chooses Italian from the profile', login8($it, 'owner@v8b.test', OPW) && (function () use ($it) { $f = null; foreach (forms(G($it, 'rolewarden/profile')) as $ff) if (($ff['types']['language'] ?? '') === 'select') $f = $ff; if (! $f) return false; P($it, $f['action'], array_replace($f['fields'], ['language' => 'it']), 'T owner it'); return html_lang(G($it, 'rolewarden/roles')) === 'it'; })());
$en = c8('T-en'); login8($en, 'enowner@s1.test', OPW);
$RA = role_id('admin'); $RU = role_id('user'); $RR = role_id('demo-reader');
$roleNames = fn (array $d) => array_map(fn ($s) => $d["roles.$s.name"], ['super-admin', 'admin', 'user', 'demo-reader', 'demo-author', 'demo-approver']);
$roleDescs = fn (array $d) => array_map(fn ($s) => $d["roles.$s.description"], ['super-admin', 'admin', 'user', 'demo-reader', 'demo-author', 'demo-approver']);
$permDescs = fn (array $d) => array_values(array_filter($d, fn ($k) => str_starts_with($k, 'permissions.'), ARRAY_FILTER_USE_KEY));
$PAGES = [
    'roles list' => ['rolewarden/roles', fn ($d) => array_merge($roleNames($d), $roleDescs($d))],
    'role page (admin)' => ["rolewarden/roles/$RA", fn ($d) => [$d['roles.admin.name'], $d['roles.admin.description']]],
    'role page (demo Reader)' => ["rolewarden/roles/$RR", fn ($d) => [$d['roles.demo-reader.name'], $d['roles.demo-reader.description']]],
    'Permissions' => ['rolewarden/permissions', fn ($d) => array_merge($permDescs($d), [$d['roles.admin.name'], $d['roles.demo-approver.name'], $d['roles.demo-reader.name']])],
    'users list' => ['rolewarden/users', fn ($d) => [$d['roles.admin.name'], $d['roles.user.name'], $d['roles.super-admin.name'], $d['roles.demo-author.name']]],
    'user page (admin)' => ["rolewarden/users/$ADMIN", fn ($d) => [$d['roles.admin.name'], $d['roles.user.name']]],
    'user page (plain)' => ["rolewarden/users/$PLAIN", fn ($d) => [$d['roles.user.name']]],
    'Settings' => ['rolewarden/settings', fn ($d) => [$d['roles.admin.name'], $d['roles.user.name'], $d['roles.demo-reader.name']]],
    'role edit form (admin)' => ["rolewarden/roles/$RA/edit", fn ($d) => [$d['roles.admin.name'], $d['roles.admin.description'], $d['roles.user.name']]],
    'role create form' => ['rolewarden/roles/create', fn ($d) => [$d['roles.admin.name'], $d['roles.demo-author.name']]],
    'user create form' => ['rolewarden/users/create', fn ($d) => []],
    'Activity' => ['rolewarden/activity', fn ($d) => []],
    'demo list' => ['demo', fn ($d) => $GLOBALS['ENVT'] === 'production' ? [] : [$d['roles.demo-reader.name'], $d['roles.demo-author.name'], $d['roles.demo-approver.name']]],
];
$survey = function (Client $c, array $d, bool $italian) use ($PAGES, $ENONLY): array {
    $bad = [];
    foreach ($PAGES as $name => [$u, $want]) {
        $h = G($c, $u, "T {$c->name} $u");
        $h = no_area_titles($h);
        if ($c->status !== 200) { $bad[] = "$name: status {$c->status}"; continue; }
        $miss = missing($h, $want($d));
        if ($miss) $bad[] = "$name: missing " . json_encode($miss, JSON_UNESCAPED_UNICODE);
        if ($italian && ($left = english_left($h, $ENONLY))) $bad[] = "$name: English left " . json_encode($left, JSON_UNESCAPED_UNICODE);
    }
    return $bad;
};
$b = $survey($it, $DIT, true);
check('T01', 'Italian reader: seeded role names/descriptions and permission descriptions in Italian on roles list, role pages, Permissions (with Granted by), users list, user pages, Settings default role, role edit and create forms, Activity, demo showcase bar; no English seed text left', $b === [], implode(' || ', $b));
$hp = G($it, 'rolewarden/permissions');
preg_match_all('#<div class="rw-section-title">.*?<h2>([^<]*)</h2>#s', $hp, $areaT);
$GLOBALS['T_AREAS'] = $areaT[1];
info('Area titles on the Italian Permissions page: ' . json_encode($areaT[1]));
$hp = no_area_titles($hp);
check('T01b', 'Italian Permissions page: the "Concesso da" column names Amministratore, never Admin', str_contains($hp, 'Concesso da') && in_array($DIT['roles.admin.name'], leaves($hp), true) && ! in_array('Admin', leaves($hp), true), implode(' | ', array_map(fn ($l) => trim(preg_replace('/\s+/', ' ', $l)), preg_grep('/>\s*Admin\s*</', explode("
", $hp)))));
$he = G($it, "rolewarden/roles/$RA/edit");
$fe = form_with($he, 'name');
check('T01c', 'Italian role edit form: name and description fields hold the Italian text', ($fe['fields']['name'] ?? '') === $DIT['roles.admin.name'] && trim($fe['fields']['description'] ?? '') === $DIT['roles.admin.description'], json_encode($fe['fields'] ?? null, JSON_UNESCAPED_UNICODE));
$hd = G($it, 'demo');
check('T01d', 'Italian demo list: column "Proprietario" (was Owner)', in_array('Proprietario', leaves($hd), true) && ! in_array('Owner', leaves($hd), true));
$b = $survey($en, $DEN, false);
check('T02', 'English reader: the same pages show the English seed text as before', $b === [], implode(' || ', $b));

// ---- saving the edit form in Italian without touching the fields
$roleRow = fn (int $id) => q('SELECT * FROM acl_roles WHERE id=?', [$id])[0];
$acts = fn () => (int) q1("SELECT COUNT(*) FROM acl_activity_log WHERE action LIKE 'role.%'");
$aclstate = function () { $s = fullstate(); return array_intersect_key($s, array_flip(['acl_roles', 'acl_permissions', 'acl_role_permissions', 'acl_user_roles', 'acl_user_permissions'])); };
foreach (['admin' => $RA, 'user' => $RU, 'demo-reader' => $RR] as $slug => $rid) {
    $h = G($it, "rolewarden/roles/$rid/edit", "T edit $slug it");
    $f = form_with($h, 'name');
    $row0 = $roleRow($rid); $a0 = $acts(); $s0 = $aclstate();
    P($it, $f['action'], $f['fields'], "T save $slug unchanged it");
    $st = $it->status;
    $row1 = $roleRow($rid); $a1 = $acts(); $s1 = $aclstate();
    $diff = array_keys(array_diff_assoc($row1, $row0));
    $GLOBALS['T_UPD'][$slug] = in_array('updated_at', $diff, true);
    $diff = array_values(array_diff($diff, ['updated_at']));
    $strip = function (array $st) { foreach ($st['acl_roles']['rows'] as &$r) unset($r['updated_at']); unset($r); return $st; };
    $s0 = $strip($s0); $s1 = $strip($s1);
    check("T03-$slug", "Italian: saving the edit form of $slug untouched leaves the database unchanged (role row apart from updated_at, grants, assignments) and writes no role.* activity entry", in_array($st, [302, 303], true) && $diff === [] && statediff($s0, $s1) === [] && $a1 === $a0, json_encode(['status' => $st, 'changed columns' => $diff, 'row after' => array_intersect_key($row1, array_flip(['name', 'description', 'updated_at'])), 'activity' => [$a0, $a1], 'diff' => array_slice(statediff($s0, $s1), 0, 3)], JSON_UNESCAPED_UNICODE));
}
$h = G($en, "rolewarden/roles/$RA/edit", 'T edit admin en'); $f = form_with($h, 'name'); $u0 = $roleRow($RA)['updated_at']; sleep(1);
P($en, $f['action'], $f['fields'], 'T save admin unchanged en');
$GLOBALS['T_UPD']['admin (English form)'] = $roleRow($RA)['updated_at'] !== $u0;
info('updated_at changed by an untouched save: ' . json_encode($GLOBALS['T_UPD']));
// description changed in Italian, name left as shown (translated)
$h = G($it, "rolewarden/roles/$RA/edit");
$f = form_with($h, 'name');
$a0 = $acts();
P($it, $f['action'], array_replace($f['fields'], ['description' => 'Descrizione cambiata S1']), 'T admin description it');
$row = $roleRow($RA);
check('T04', 'Italian: description changed, name untouched: the new description is saved as written and the name stays the stored English "Admin"', $row['name'] === 'Admin' && $row['description'] === 'Descrizione cambiata S1' && $acts() === $a0 + 1, json_encode([$row['name'], $row['description'], $acts() - $a0], JSON_UNESCAPED_UNICODE));
$hi = G($it, 'rolewarden/roles'); $hen = G($en, 'rolewarden/roles');
check('T05', 'Changed description shown as written in both languages; the untouched name still translated (Amministratore / Admin)', str_contains(vis($hi), 'Descrizione cambiata S1') && str_contains(vis($hen), 'Descrizione cambiata S1') && in_array('Amministratore', leaves($hi), true) && in_array('Admin', leaves($hen), true) && ! str_contains(vis($hi), $DIT['roles.admin.description']), '');
// name changed by an admin in English: shown as written in Italian too; description of that role still translated
$h = G($en, "rolewarden/roles/$RU/edit");
$f = form_with($h, 'name');
P($en, $f['action'], array_replace($f['fields'], ['name' => 'Member S1']), 'T user renamed en');
$hi = G($it, 'rolewarden/roles'); $hu = G($it, 'rolewarden/users'); $hs = G($it, 'rolewarden/settings');
check('T06', 'A name changed by an admin ("Member S1") is shown as written to the Italian reader (roles list, users list, Settings); its untouched description stays translated', q1('SELECT name FROM acl_roles WHERE id=?', [$RU]) === 'Member S1' && in_array('Member S1', leaves($hi), true) && in_array('Member S1', leaves($hu), true) && in_array('Member S1', leaves($hs), true) && ! in_array('Utente', leaves($hi), true) && str_contains(vis($hi), $DIT['roles.user.description']), json_encode(q1('SELECT name FROM acl_roles WHERE id=?', [$RU])));
// a demo role renamed in Italian (name typed by the admin)
$h = G($it, "rolewarden/roles/$RR/edit");
$f = form_with($h, 'name');
P($it, $f['action'], array_replace($f['fields'], ['name' => 'Lettori scelti']), 'T reader renamed it');
check('T07', 'Italian: a name typed by the admin (Lettori scelti) is saved as written and shown as written in English too', q1('SELECT name FROM acl_roles WHERE id=?', [$RR]) === 'Lettori scelti' && in_array('Lettori scelti', leaves(G($en, 'rolewarden/roles')), true));
// back to the seed text: translated again
$h = G($en, "rolewarden/roles/$RU/edit");
$f = form_with($h, 'name');
P($en, $f['action'], array_replace($f['fields'], ['name' => 'User']), 'T user back to seed en');
check('T08', 'Name set back to the seed text ("User"): the Italian reader sees "Utente" again', in_array('Utente', leaves(G($it, 'rolewarden/roles')), true));
// activity keeps the labels of the moment
$rowsA = q("SELECT action, subject_label FROM acl_activity_log WHERE action LIKE 'role.%' AND subject_id=? ORDER BY id", [$RU]);
$ha = G($it, 'rolewarden/activity');
info('Activity rows on the role user: ' . json_encode($rowsA, JSON_UNESCAPED_UNICODE));
check('T09', 'Activity log keeps the labels of the moment: the rename rows of the role user still carry their own labels after the role was renamed again', count($rowsA) >= 2 && in_array('Member S1', array_column($rowsA, 'subject_label'), true) && str_contains(vis($ha), 'Member S1'), json_encode($rowsA, JSON_UNESCAPED_UNICODE));
// ---- the application's own roles and permissions, translated as documented
$now = date('Y-m-d H:i:s');
q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,?,?)', ['s1-editor', 'Editor', 'Edits the articles.', $now, $now]);
q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,?,?)', ['s1-mark', 'Marked', 'Marked role.', $now, $now]);
q("INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES ('articles.publish','articles','Publish articles',0,?,?)", [$now, $now]);
q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [role_id('s1-editor'), perm_id('articles.publish')]);
q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$PLAIN, role_id('s1-editor')]);
@mkdir("$APP/app/Language/en", 0777, true); @mkdir("$APP/app/Language/it", 0777, true);
file_put_contents("$APP/app/Language/en/RoleWardenData.php", "<?php\n\nreturn [\n    'roles' => [\n        's1-editor' => ['name' => 'Editor', 'description' => 'Edits the articles.'],\n        's1-mark' => ['name' => 'Marked', 'description' => 'Marked role.'],\n    ],\n    'permissions' => [\n        'articles' => ['publish' => 'Publish articles'],\n    ],\n];\n");
file_put_contents("$APP/app/Language/it/RoleWardenData.php", "<?php\n\nreturn [\n    'roles' => [\n        's1-editor' => ['name' => 'Redattore', 'description' => 'Modifica gli articoli.'],\n        's1-mark' => ['name' => 'Marcato <b>91</b> & \"q\"', 'description' => '<img src=x onerror=alert(93)>'],\n    ],\n    'permissions' => [\n        'articles' => ['publish' => 'Pubblicare articoli'],\n    ],\n];\n");
sp8($APP, ['cache:clear']);
$hi = G($it, 'rolewarden/roles'); $hp = G($it, 'rolewarden/permissions'); $hu = G($it, 'rolewarden/users'); $hpl = G($it, "rolewarden/users/$PLAIN");
check('T10', 'Application role and permission translated as views.html says (app/Language/{en,it}/RoleWardenData.php): Redattore, its description, Pubblicare articoli, on roles list, Permissions, users list and user page', in_array('Redattore', leaves($hi), true) && str_contains(vis($hi), 'Modifica gli articoli.') && str_contains(vis($hp), 'Pubblicare articoli') && in_array('Redattore', leaves($hu), true) && in_array('Redattore', leaves($hpl), true), json_encode([in_array('Redattore', leaves($hi), true), str_contains(vis($hp), 'Pubblicare articoli'), in_array('Redattore', leaves($hu), true)]));
$hen = G($en, 'rolewarden/roles');
check('T10b', 'The same application role in English: Editor, Edits the articles.', in_array('Editor', leaves($hen), true) && str_contains(vis($hen), 'Edits the articles.') && str_contains(vis(G($en, 'rolewarden/permissions')), 'Publish articles'));
// ---- escaping: markup in a translation and in an admin-typed name
q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,0,?,?)', ['s1-xss', '<img src=x onerror=alert(91)>', '<script>alert(92)</script>', $now, $now]);
q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$PLAIN, role_id('s1-xss')]);
q('INSERT INTO acl_user_roles (user_id, role_id) VALUES (?,?)', [$PLAIN, role_id('s1-mark')]);
sp8($APP, ['cache:clear']);
$raw = [];
foreach (['rolewarden/roles', 'rolewarden/permissions', 'rolewarden/users', "rolewarden/users/$PLAIN", 'rolewarden/settings', 'rolewarden/roles/create', "rolewarden/roles/$RA/edit", 'rolewarden/roles/' . role_id('s1-mark'), 'rolewarden/roles/' . role_id('s1-xss')] as $u) foreach (['it' => $it, 'en' => $en] as $l => $c) {
    $h = G($c, $u, "T escape $l $u");
    foreach (['<img src=x onerror=alert(91)>', '<script>alert(92)</script>', '<b>91</b>', '<img src=x onerror=alert(93)>'] as $m) if (str_contains($h, $m)) $raw[] = "$l $u: $m";
}
$hm = G($it, 'rolewarden/roles');
check('T11', 'Markup in an admin-typed role name and description, and in an application translation, is escaped on every page in both languages (and the escaped text is shown)', $raw === [] && in_array('Marcato <b>91</b> & "q"', leaves($hm), true) && in_array('<img src=x onerror=alert(91)>', leaves($hm), true), implode(' | ', $raw));
server_stop();
@unlink("$APP/app/Language/en/RoleWardenData.php"); @unlink("$APP/app/Language/it/RoleWardenData.php");
q("DELETE FROM settings WHERE `key`='locale' AND context=?", ['user:' . $OWNER]);
