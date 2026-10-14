<?php
// Phase P: Permissions list, column "Granted by" (CHANGELOG: "shows the roles that grant each permission"; intro:
// "the roles that grant it, on their own or through a parent. Super admin roles have them all").
// Runs after M (roles m-gp > m-par > m-kid exist). Adds a 4-level chain, a soft-deleted role, a rename and markup.
$now = date('Y-m-d H:i:s');
$mk = function (string $slug, string $name, ?int $parent, array $perms) use ($now): int {
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,?,0,0,?,?)', [$slug, $name, '', $parent, $now, $now]);
    $id = role_id($slug);
    foreach ($perms as $p) q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$id, perm_id($p)]);
    return $id;
};
$P1 = $mk('p-l1', 'Level One', null, ['permissions.override']);
$P2 = $mk('p-l2', 'Level Two', $P1, []);
$P3 = $mk('p-l3', 'Level Three', $P2, []);
$P4 = $mk('p-l4', 'Level Four', $P3, ['users.manage-admins']);
$DEL = $mk('p-gone', 'Gone Role', null, ['users.edit', 'beta.access']);
q('UPDATE acl_roles SET deleted_at=? WHERE id=?', [$now, $DEL]);
$MARK = $mk('p-mark', '<b>Bold</b> & "Q" <script>alert(8)</script>', null, ['security.alerts']);
q("INSERT INTO acl_permissions (slug, area, description, is_system, created_at, updated_at) VALUES ('lonely.view','lonely','Granted by nobody',0,?,?)", [$now, $now]);
sp8($APP, ['cache:clear']);

/** Expected: non-deleted roles whose own grants, or those of any ancestor, include the permission. */
function expected_granted(): array
{
    $roles = []; foreach (q('SELECT id, name, parent_id, deleted_at, is_super_admin FROM acl_roles') as $r) $roles[(int) $r['id']] = $r;
    $own = []; foreach (q('SELECT rp.role_id, p.slug FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id') as $r) $own[(int) $r['role_id']][] = $r['slug'];
    $exp = [];
    foreach (array_column(q('SELECT slug FROM acl_permissions'), 'slug') as $p) $exp[$p] = [];
    foreach ($roles as $id => $r) {
        if ($r['deleted_at'] !== null) continue;
        $seen = []; $cur = $id; $set = [];
        while ($cur && isset($roles[$cur]) && ! isset($seen[$cur])) { $seen[$cur] = 1; $set = array_merge($set, $own[$cur] ?? []); $cur = (int) $roles[$cur]['parent_id']; }
        foreach (array_unique($set) as $p) $exp[$p][] = $r['name'];
    }
    foreach ($exp as &$v) sort($v); unset($v);
    return $exp;
}
/** Shown: per slug, the role names (text) and the links of the "Granted by" cell. */
function shown_granted(string $h): array
{
    $xp = dom($h); $out = [];
    foreach ($xp->query('//table[contains(@class,"rw-t-permissions")]/tbody/tr') as $tr) {
        $code = $xp->query('.//code', $tr)->item(0);
        $cell = $xp->query('.//td[contains(@class,"rw-c-roles")]', $tr)->item(0);
        if (! $code || ! $cell) continue;
        $links = []; foreach ($xp->query('.//a', $cell) as $a) $links[] = [trim($a->textContent), $a->getAttribute('href')];
        $text = trim(preg_replace('/\s+/u', ' ', $cell->textContent));
        $names = $links ? array_column($links, 0) : ($text === '' ? [] : array_map('trim', explode(',', $text)));
        sort($names);
        $out[trim($code->textContent)] = ['names' => $names, 'links' => $links, 'text' => $text];
    }
    return $out;
}
$cP = serve8($APP, 'perms');
$own = c8('owner');
login8($own, 'owner@v8b.test', OPW);
$h = G($own, 'rolewarden/permissions');
check('P00', 'Permissions list answers 200 without SQL or PHP error', $own->status === 200 && ! leaks_sql($h) && ! phperr($h), "status {$own->status}");
$exp = expected_granted();
$sh = shown_granted($h);
$bad = [];
foreach ($exp as $slug => $names) {
    if (! isset($sh[$slug])) { $bad[] = "$slug missing"; continue; }
    $got = $sh[$slug];
    if ($names === []) { if ($got['links'] !== [] || $got['text'] !== 'no role') $bad[] = "$slug: want 'no role', got " . json_encode($got['text']); continue; }
    if ($got['names'] !== $names) $bad[] = "$slug: " . json_encode($got['names']) . ' want ' . json_encode($names);
}
check('P01', '"Granted by" equals the database for every permission (' . count($exp) . '): own grants and inherited ones through every level, "no role" when none', $bad === [], implode(' | ', array_slice($bad, 0, 10)));
check('P02', 'Four-level chain: users.manage-admins (granted at the bottom) not listed for the three upper levels; permissions.override (granted at the top) listed for all four', in_array('Level Four', $sh['users.manage-admins']['names'] ?? [], true) && ! array_intersect(['Level One', 'Level Two', 'Level Three'], $sh['users.manage-admins']['names'] ?? []) && array_diff(['Level Four', 'Level One', 'Level Three', 'Level Two'], $sh['permissions.override']['names'] ?? []) === [], json_encode([$sh['users.manage-admins'] ?? null, $sh['permissions.override'] ?? null]));
check('P03', 'Three-level chain of phase M: users.view from Grandparent M, Parent M, Kid M', array_diff(['Grandparent M', 'Kid M', 'Parent M'], $sh['users.view']['names'] ?? []) === [], json_encode($sh['users.view']['names'] ?? null));
check('P04', 'A soft-deleted role is never listed (Gone Role granted users.edit and beta.access)', ! str_contains($h, 'Gone Role') && ! in_array('Gone Role', array_merge($sh['users.edit']['names'] ?? [], $sh['beta.access']['names'] ?? []), true) && isset($sh['users.edit']), json_encode([$sh['users.edit'] ?? null]));
check('P04b', 'A permission no role grants shows "no role" (lonely.view)', ($sh['lonely.view']['text'] ?? '') === 'no role' && ($sh['lonely.view']['links'] ?? null) === [], json_encode($sh['lonely.view'] ?? null));
$sa = q1("SELECT name FROM acl_roles WHERE is_super_admin=1 AND slug='super-admin'");
check('P05', 'The super admin role (no rows of its own) is not listed per permission; the intro says super admin roles have them all', ! in_array($sa, array_merge(...array_column($sh, 'names')), true) && str_contains(text_of($h), 'Super admin roles have them all'), (string) $sa);
$ids = []; foreach (q('SELECT id, name FROM acl_roles WHERE deleted_at IS NULL') as $r) $ids[$r['name']] = (int) $r['id'];
$badl = [];
foreach ($sh as $slug => $g) foreach ($g['links'] as [$n, $href]) if (! preg_match('#/rolewarden/roles/' . ($ids[$n] ?? -1) . '$#', $href)) $badl[] = "$slug $n -> $href";
check('P06', 'Every role name links to that role\'s page (rolewarden/roles/{id})', $badl === [] && array_filter($sh, fn ($g) => $g['links'] !== []) !== [], implode(' | ', array_slice($badl, 0, 5)));
// markup in a role name
check('P07', 'Markup in a role name is escaped in "Granted by" (no raw <b> or <script>, escaped text present)', ! str_contains($h, '<b>Bold</b>') && ! str_contains($h, '<script>alert(8)') && str_contains($h, '&lt;b&gt;Bold&lt;/b&gt;') && in_array('<b>Bold</b> & "Q" <script>alert(8)</script>', $sh['security.alerts']['names'] ?? [], true), json_encode($sh['security.alerts'] ?? null));
// rename through the panel form
$he = G($own, "rolewarden/roles/$P1/edit");
$f = form_with($he, 'name');
$ok = false;
if ($f) {
    $v = $f['fields']; $v['name'] = 'Level Uno';
    P($own, $f['action'], $v, 'P08 rename');
    $ok = in_array($own->status, [302, 303], true);
}
$h = G($own, 'rolewarden/permissions');
$sh = shown_granted($h);
check('P08', 'A role renamed from its edit form appears with the new name at once (permissions.override: Level Uno, no "Level One")', $ok && in_array('Level Uno', $sh['permissions.override']['names'] ?? [], true) && ! str_contains($h, '>Level One<'), json_encode([$ok, $sh['permissions.override']['names'] ?? null]));
// a grant removed from a parent disappears from the children too
q('DELETE FROM acl_role_permissions WHERE role_id=? AND permission_id=?', [$P1, perm_id('permissions.override')]);
sp8($APP, ['cache:clear']);
$sh = shown_granted(G($own, 'rolewarden/permissions'));
$exp = expected_granted();
check('P09', 'After removing the grant from the top of the chain, permissions.override is no longer listed on any of the four levels', ! array_intersect(['Level Uno', 'Level Two', 'Level Three', 'Level Four'], $sh['permissions.override']['names'] ?? []) && ($sh['permissions.override']['names'] ?? null) === $exp['permissions.override'], json_encode($sh['permissions.override'] ?? null));
// a deleted role in the middle of a chain: report what happens (SPEC does not say)
q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$P1, perm_id('permissions.override')]);
q('UPDATE acl_roles SET deleted_at=? WHERE id=?', [$now, $P2]);
sp8($APP, ['cache:clear']);
$sh = shown_granted(G($own, 'rolewarden/permissions'));
info('chain with Level Two soft-deleted: permissions.override granted by ' . json_encode($sh['permissions.override']['names'] ?? null));
q('UPDATE acl_roles SET deleted_at=NULL WHERE id=?', [$P2]);
sp8($APP, ['cache:clear']);
// Italian page: header and "no role"
$tok = csrf_of(G($own, 'rolewarden/profile'));
$own->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => $tok, 'language' => 'it'], ['Referer: ' . BASE . 'index.php/rolewarden/permissions']);
$h = G($own, 'rolewarden/permissions');
$sh = shown_granted($h);
$none = array_filter($sh, fn ($g) => $g['links'] === []);
check('P10', 'Italian: column "Concesso da", intro in Italian, "nessun ruolo" where no role grants', str_contains($h, '>Concesso da<') && $none && ! array_filter($none, fn ($g) => $g['text'] !== 'nessun ruolo'), json_encode(array_slice($none, 0, 2)));
$own->req('POST', 'rolewarden/profile/language', ['csrf_test_name' => csrf_of($h), 'language' => 'en'], ['Referer: ' . BASE . 'index.php/rolewarden/permissions']);
// who may see the list, and whether the links make sense
$pv = $mk('p-pview', 'Perm viewer', null, ['permissions.view']);
$pu = make_owner('pview@v8b.test', OPW, 'v8bpview', 'p-pview');
sp8($APP, ['cache:clear']);
$cv = c8('pview'); login8($cv, 'pview@v8b.test', OPW);
$h = G($cv, 'rolewarden/permissions');
$sh = $cv->status === 200 ? shown_granted($h) : [];
$links = array_merge(...array_values(array_map(fn ($g) => $g['links'], $sh ?: [['links' => []]])));
G($cv, 'rolewarden/roles/' . role_id('admin'));
$roleStatus = $cv->status;
info("permissions.view only: list status " . (int) ($sh ? 200 : 0) . ", links " . count($links) . ", a role page answers $roleStatus");
check('P11', 'A user with permissions.view but not roles.view: the list opens, names shown, and no link to a role page he cannot open', $sh !== [] && ($links === [] || $roleStatus === 200), 'links=' . count($links) . " role page=$roleStatus");
$vw = c8('viewer'); login8($vw, 'viewer@v8b.test', OPW);
$h = G($vw, 'rolewarden/permissions');
info('roles.view + users.view (no permissions.view): permissions list answers ' . $vw->status);
// G07-like: no SQL anywhere, also with a huge role count in one cell
check('P12', 'No 500, SQL or PHP error on any request of this phase', ! array_filter($GLOBALS['SEEN'], fn ($s) => $s[1] >= 500 || leaks_sql($s[3]) || phperr($s[3])), json_encode(array_map(fn ($s) => [$s[0], $s[1]], array_filter($GLOBALS['SEEN'], fn ($s) => $s[1] >= 500 || leaks_sql($s[3]) || phperr($s[3])))));
server_stop();
