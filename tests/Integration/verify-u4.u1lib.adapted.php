<?php
// U4 copy of verify-u3.u1lib.adapted.php (Collaudatore ad Hoc): NAVMAP gains the Dashboard item (ADAPTED U4).
// U3 copy of verify-u2.u1lib.adapted.php (Collaudatore ad Hoc): side() skips the wrapper of the command search button (ADAPTED U3).
// U2 copy of verify-u1.lib.php (Collaudatore ad Hoc), adapted: see the ADAPTED comments.
/**
 * U1 helpers (Collaudatore ad Hoc, Claude), loaded by the U1 phases inside verify-u1.harness.php (which already has
 * verify-v8.lib.php, the copy app $APP on 127.0.0.1:8071 and the accounts owner/admin/plain @v8b.test).
 * Assertions only from docs/SPEC.md (v1.1 decisions), docs/BRIEF-v1.1.md, docs/design-system/README.md and
 * components/Sidebar/README.md, CHANGELOG (Unreleased), documentation/ and the HTML/CSS a browser receives.
 */
declare(strict_types=1);

const U1PW = 'Harbour-Quill-6402';
const U1BASE = BASE . 'index.php/';
// documentation/configuration.html "Permissions the panel uses": the screen each sidebar item opens and its permission
const NAVMAP = [
    'rolewarden/users' => 'users.view', 'rolewarden/roles' => 'roles.view', 'rolewarden/permissions' => 'permissions.view',
    'rolewarden/activity' => 'activity.view', 'rolewarden/settings' => 'settings.view', 'rolewarden/appearance' => 'appearance.update',
    // ADAPTED (U2): the sidebar item "Check a permission" (CHANGELOG Unreleased, SPEC v1.1: screen with permissions.view)
    'rolewarden/permissions/check' => 'permissions.view',
    // ADAPTED (U4): the sidebar item Dashboard (SPEC U4: in Overview with at least one of the five dashboard.*). The
    // accounts of the U1-U3 suites hold either all five (admin, super admin) or none, so dashboard.figures stands for them
    'rolewarden' => 'dashboard.figures',
];
const GROUPS_EN = ['Overview', 'Access', 'Security', 'System'];   // SPEC v1.1: Panoramica, Accesso, Sicurezza, Sistema

function u1path(string $href): string
{
    $href = html_entity_decode($href);
    foreach ([U1BASE, BASE] as $b) if (str_starts_with($href, $b)) return rtrim(substr($href, strlen($b)), '/');
    return $href;
}
function cls(DOMElement $e): array { return preg_split('/\s+/', trim($e->getAttribute('class'))) ?: []; }
/** The sidebar as served: style, rail, groups with items, Today panel, toggle form, oddities. */
function side(string $h): array
{
    $xp = dom($h);
    $as = $xp->query('//aside[contains(concat(" ",normalize-space(@class)," ")," rw-side ")]')->item(0);
    $shell = $xp->query('//*[contains(concat(" ",normalize-space(@class)," ")," rw-shell ")]')->item(0);
    $o = ['found' => (bool) $as, 'style' => $as ? $as->getAttribute('data-sidebar') : null, 'aria' => $as ? $as->getAttribute('aria-label') : null,
        'rail' => $shell && in_array('rw-shell--rail', cls($shell), true), 'groups' => [], 'items' => [], 'today' => null, 'toggle' => null, 'odd' => [], 'current' => []];
    if (! $as) return $o;
    foreach ($xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," rw-side-group ")]', $as) as $g) {
        $lab = $xp->query('.//*[contains(@class,"rw-side-label")]', $g)->item(0);
        // ADAPTED (U3): the command search button sits in a wrapper of its own with the same class, no header and no
        // navigation item (design system Sidebar: name, command search button, then the groups); it is not a nav group
        if (! $lab && $xp->query('.//li', $g)->length === 0 && $xp->query('.//*[contains(@class,"rw-side-search")]', $g)->length > 0) { $o['search'] = trim(preg_replace('/\s+/u', ' ', $g->textContent)); continue; }
        $grp = ['label' => $lab ? trim($lab->textContent) : '', 'items' => []];
        foreach ($xp->query('.//li', $g) as $li) {
            $a = $xp->query('.//a', $li)->item(0);
            if (! $a || trim($a->getAttribute('href')) === '') { $o['odd'][] = 'li without link: ' . trim($li->textContent); continue; }
            $t = $xp->query('.//*[contains(@class,"rw-side-text")]', $a)->item(0);
            $cnt = $xp->query('.//*[contains(@class,"rw-side-count")]', $a)->item(0);
            $it = ['path' => u1path($a->getAttribute('href')), 'text' => $t ? trim(preg_replace('/\s+/u', ' ', $t->textContent)) : trim($a->textContent),
                'count' => $cnt ? trim(preg_replace('/\s+/u', ' ', $cnt->textContent)) : null, 'countClass' => $cnt ? $cnt->getAttribute('class') : null,
                'current' => $a->getAttribute('aria-current'), 'title' => $a->getAttribute('title'), 'aria' => $a->getAttribute('aria-label')];
            foreach (['aria-disabled', 'disabled', 'tabindex'] as $at) if ($a->hasAttribute($at) || $li->hasAttribute($at)) $o['odd'][] = "{$it['path']} has $at";
            if (preg_grep('/disabled/i', array_merge(cls($a), cls($li)))) $o['odd'][] = "{$it['path']} disabled class";
            $grp['items'][] = $it;
            $o['items'][$it['path']] = $it + ['group' => $grp['label']];
            if ($it['current'] !== '') $o['current'][] = $it['path'] . '=' . $it['current'];
        }
        $o['groups'][] = $grp;
    }
    $td = $xp->query('.//*[contains(concat(" ",normalize-space(@class)," ")," rw-today ")]', $as)->item(0);
    if ($td) {
        $b = $xp->query('.//b', $td)->item(0);
        $svg = $xp->query('.//*[local-name()="svg"]', $td)->item(0);
        $pl = $xp->query('.//*[local-name()="polyline"]', $td)->item(0);
        $pts = [];
        if ($pl) foreach (preg_split('/\s+/', trim($pl->getAttribute('points'))) as $p) { [$x, $y] = array_map('floatval', explode(',', $p)); $pts[] = [$x, $y]; }
        $o['today'] = ['text' => trim(preg_replace('/\s+/u', ' ', $td->textContent)), 'b' => $b ? trim($b->textContent) : null,
            'svgRole' => $svg ? $svg->getAttribute('role') : null, 'svgAria' => $svg ? $svg->getAttribute('aria-label') : null, 'svgView' => $svg ? $svg->getAttribute('viewBox') : null, 'points' => $pts];
    }
    foreach ($xp->query('.//form', $as) as $f) {
        $fs = forms($f->ownerDocument->saveHTML($f))[0] ?? null;
        if ($fs && preg_match('#rolewarden/sidebar$#', u1path($fs['action']))) {
            $btn = $xp->query('.//button', $f)->item(0);
            $o['toggle'] = $fs + ['btnAria' => $btn ? $btn->getAttribute('aria-label') : null, 'btnTitle' => $btn ? $btn->getAttribute('title') : null, 'btnText' => $btn ? trim($btn->textContent) : null];
        }
    }
    return $o;
}
function paths(array $s): array { $p = array_keys($s['items']); sort($p); return $p; }

/** A role with exactly the given permissions (written straight to the tables, as data of the test). */
function mkrole(string $slug, array $perms, bool $super = false): int
{
    $now = date('Y-m-d H:i:s');
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at, updated_at) VALUES (?,?,?,NULL,0,?,?,?)', [$slug, ucfirst($slug), 'U1 test role', $super ? 1 : 0, $now, $now]);
    $id = (int) db()->insert_id;
    foreach ($perms as $p) { $pid = perm_id($p); if (! $pid) throw new RuntimeException("no permission $p"); q('INSERT INTO acl_role_permissions (role_id, permission_id) VALUES (?,?)', [$id, $pid]); }
    return $id;
}
/** A user (Shield format) with one role and per-user overrides [slug => 1 granted | 0 denied]. */
function mkuser(string $email, string $role, array $over = [], int $active = 1): int
{
    $id = make_owner($email, U1PW, 'u1-' . substr(md5($email), 0, 8), $role, $active);
    $now = date('Y-m-d H:i:s');
    foreach ($over as $p => $g) q('INSERT INTO acl_user_permissions (user_id, permission_id, granted, created_at, updated_at) VALUES (?,?,?,?,?)', [$id, perm_id($p), $g, $now, $now]);
    return $id;
}
/** The precedence of CLAUDE.md rule 6 / SPEC, computed here from the tables: the model the sidebar is checked against. */
function holds(int $uid, string $perm): bool
{
    if ((int) q1('SELECT active FROM users WHERE id=?', [$uid]) !== 1) return false;
    $ov = q1('SELECT up.granted FROM acl_user_permissions up JOIN acl_permissions p ON p.id=up.permission_id WHERE up.user_id=? AND p.slug=?', [$uid, $perm]);
    if ($ov !== null && (int) $ov === 0) return false;
    if ($ov !== null && (int) $ov === 1) return true;
    if ((int) q1('SELECT COUNT(*) FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.is_super_admin=1 AND r.deleted_at IS NULL', [$uid]) > 0) return true;
    // a role and its ancestors (parent_id), not deleted
    $roles = array_map('intval', array_column(q('SELECT ur.role_id FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.deleted_at IS NULL', [$uid]), 'role_id'));
    $seen = [];
    while ($roles) {
        $r = array_pop($roles);
        if (isset($seen[$r])) continue;
        $seen[$r] = 1;
        if ((int) q1('SELECT COUNT(*) FROM acl_role_permissions rp JOIN acl_permissions p ON p.id=rp.permission_id WHERE rp.role_id=? AND p.slug=?', [$r, $perm]) > 0) return true;
        $par = q1('SELECT parent_id FROM acl_roles WHERE id=? AND deleted_at IS NULL', [$r]);
        if ($par) $roles[] = (int) $par;
    }
    return false;
}
function expected_items(int $uid): array { $o = []; foreach (NAVMAP as $p => $perm) if (holds($uid, $perm)) $o[] = $p; sort($o); return $o; }
function u1login(string $name, string $email, string $pw = U1PW): array { $c = c8($name); $ok = login8($c, $email, $pw); return [$c, $ok]; }
/** Fresh form of the page, then a plain POST of it with $over (CSRF token from the page). */
function postform(Client $c, string $page, string $actionRe, array $over, string $tag, bool $csrf = true): string
{
    $h = G($c, $page, "form page $page ($tag)");
    $f = null;
    foreach (forms($h) as $ff) if ($ff['method'] === 'post' && preg_match($actionRe, u1path($ff['action'] ?: $c->url))) { $f = $ff; break; }
    if (! $f) { info("$tag: no form $actionRe on $page"); return ''; }
    $v = array_replace($f['fields'], $over);
    if (! $csrf) unset($v['csrf_test_name']);
    return P($c, $f['action'] ?: $c->url, $v, $tag);
}
// the harness .env sets rolewarden.signInRate = 1000 (above the screen's 5-100): every save sends 100 so the form validates
function settings_save(Client $c, array $over, string $tag, bool $csrf = true): string { return postform($c, 'rolewarden/settings', '#rolewarden/settings$#', $over + ['sign_in_rate' => '100'], $tag, $csrf); }
/** The Activity item's counter: false when the item is absent, null when it has no counter. */
function actcount(array $s) { return array_key_exists('rolewarden/activity', $s['items']) ? $s['items']['rolewarden/activity']['count'] : false; }
function theme_save(Client $c, array $over, string $tag, bool $csrf = true): string
{
    $h = G($c, 'rolewarden/appearance', "appearance ($tag)");
    $f = null;
    foreach (forms($h) as $ff) if ($ff['method'] === 'post' && str_contains($ff['text'], 'Save theme') || ($ff['method'] === 'post' && array_key_exists('sidebar', $ff['types']))) { $f = $ff; break; }
    if (! $f) { info("$tag: no appearance form"); return ''; }
    $v = $f['fields'];
    foreach (array_keys($v) as $k) if (str_starts_with($k, 'colors[')) $v[$k] = '';
    $v = array_replace($v, ['radius' => '', 'density' => ''], $over);
    if (! $csrf) unset($v['csrf_test_name']);
    return P($c, $f['action'] ?: 'rolewarden/appearance', $v, $tag);
}
function theme_file_u1(): string { global $APP; return "$APP/writable/rolewarden/theme.json"; }
function theme_json(): ?array { clearstatcache(); $f = theme_file_u1(); return is_file($f) ? json_decode((string) file_get_contents($f), true) : null; }
function setting_row(string $key, ?string $context): ?array
{
    $r = $context === null ? q('SELECT * FROM settings WHERE `key`=? AND context IS NULL', [$key]) : q('SELECT * FROM settings WHERE `key`=? AND context=?', [$key, $context]);
    return $r[0] ?? null;
}
function u1browser(array $steps, string $tag, int $timeout = 300000): array
{
    global $WORK;
    static $n = 0; $n++;
    $job = ['profile' => "$WORK/chrome-u1-$n", 'steps' => $steps, 'timeout' => $timeout, 'settle' => 400];
    $jf = "$WORK/u1job-$n.json";
    file_put_contents($jf, json_encode($job));
    $p = proc_open(['node', $GLOBALS['U1DIR'] . '/verify-u1.browser.mjs', $jf], [0 => ['file', 'NUL', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
    $o = stream_get_contents($pipes[1]); $e = stream_get_contents($pipes[2]);
    proc_close($p);
    $r = json_decode(trim((string) $o), true);
    if (! is_array($r)) { info("browser $tag: no result: " . short($o . $e, 600)); return ['steps' => [], 'errors' => ['no result'], 'profileRemoved' => false]; }
    if ($r['errors']) info("browser $tag errors: " . short(implode(' | ', $r['errors']), 800));
    if (empty($r['profileRemoved'])) info("browser $tag: profile not removed " . ($r['cleanupError'] ?? ''));
    return $r;
}
/** Every body seen so far: no SQL text, no PHP error. */
function seen_clean(int $from = 0): array
{
    $bad = [];
    foreach (array_slice($GLOBALS['SEEN'], $from) as $s) if ($s[1] >= 500 || leaks_sql($s[3]) || phperr(text_of($s[3]))) $bad[] = "{$s[0]} {$s[1]}";
    return $bad;
}
// CodeIgniter answers a missing CSRF token with 403, or in production (Config\Security::$redirect) with a redirect back:
// either is a refusal; each check also proves that nothing was saved
define('CSRF_REFUSED', (getenv('RW_V8B_ENV') ?: 'development') === 'production' ? [303, 302, 403] : [403]);
