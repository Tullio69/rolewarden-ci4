<?php

/**
 * V2 Account independent black-box verification; reuses the V1M1 harness.
 * Started by Codex (blocked by its sandbox), completed and extended by the Collaudatore ad Hoc
 * (Claude) on 5c7bfc5: verifiable session choice (D05), toast checks (T*), listing of a
 * re-entered session (E05), negative controls (E00, H02b, H07), user-agent escape (J07).
 * Written against docs/SPEC.md (Pannello admin, V1 decisions, Toast level), docs/BRIEF-v1.0.md,
 * README (Updating the module, Wiring, Settings and the default role) and the design-system
 * READMEs (Toast, Settings, Button). Field names and formats are read from the served HTML.
 *
 * Usage: php verify-v1-m1.php <app-copy> <module-3631fd2> <module-86f6935>
 * DB credentials only from RW_DB_USERNAME / RW_DB_PASSWORD (passed to CI4 as process env);
 * host and port forced to 127.0.0.1:3307; refuses anything but rolewarden_test.
 */

declare(strict_types=1);

[$_, $APP, $MOD_OLD, $MOD_NEW] = $argv + [null, null, null, null];
putenv('RW_DB_HOSTNAME=127.0.0.1');
putenv('RW_DB_PORT=3307');
putenv('RW_V1_APP=' . str_replace('\\', '/', $APP));

require __DIR__ . '/verify-v1.lib.php';
require __DIR__ . '/verify-v1.fixtures.php';

if (! preg_match('/^database\.default\.database = rolewarden_test$/m', (string) file_get_contents("$APP/.env"))) {
    exit("ABORT: copy .env does not name rolewarden_test\n");
}

const PORT = 8070;
$SRV = null;

function ci_env(): array
{
    $e = getenv();
    $e['database.default.username'] = getenv('RW_DB_USERNAME');
    $e['database.default.password'] = getenv('RW_DB_PASSWORD');
    return $e;
}

function redact(string $s): string
{
    return str_replace([getenv('RW_DB_PASSWORD'), getenv('RW_DB_USERNAME')], '[omesso]', $s);
}

function spark(string ...$args): string
{
    global $APP;
    $cmd = array_merge(['php', '-d', 'error_reporting=-1', 'spark'], $args);
    $p = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $APP, ci_env(), ['bypass_shell' => true]);
    $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    $rc = proc_close($p);
    $out = redact($out);
    echo "   spark " . implode(' ', $args) . " (rc=$rc): " . trim(preg_replace('/\s+/', ' ', substr($out, -300))) . "\n";
    return "rc=$rc\n" . $out;
}

function server_start(): void
{
    global $APP, $SRV;
    $cmd = ['php', '-d', 'error_reporting=-1', '-S', 'localhost:' . PORT, '-t', 'public', 'vendor/codeigniter4/framework/system/rewrite.php'];
    $mode = $GLOBALS['argv'][4] ?? 'test';
    $log = __DIR__ . '/verify-v2.' . ($mode === 'test' ? '' : $mode . '.') . 'server.log';
    $SRV = proc_open($cmd, [0 => ['file', 'NUL', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, $APP, ci_env(), ['bypass_shell' => true]);
    for ($i = 0; $i < 20; $i++) {
        usleep(500000);
        $c = curl_init('http://localhost:' . PORT . '/index.php/login');
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
        curl_exec($c);
        if (curl_getinfo($c, CURLINFO_RESPONSE_CODE) > 0) {
            return;
        }
    }
    server_stop();
    exit("server did not start\n");
}

function server_stop(): void
{
    global $SRV;
    if ($SRV) {
        $pid = proc_get_status($SRV)['pid'];
        echo "[INFO] stopping server pid=$pid\n";
        $stopped = proc_terminate($SRV);
        echo '[INFO] termination requested='.json_encode($stopped)."\n";
        proc_close($SRV);
        echo "[INFO] server closed\n";
        $SRV = null;
    }
}
register_shutdown_function('server_stop');

function use_module(string $dir): void
{
    global $APP;
    $j = "$APP/vendor/rolewarden/codeigniter4-rolewarden";
    if (is_dir($j) || is_link($j)) { rmdir($j); }
    $cmd = ['powershell', '-NoProfile', '-Command', "New-Item -ItemType Junction -Path '$j' -Target '$dir' | Out-Null"];
    $p = proc_open($cmd, [1 => ['pipe','w'], 2 => ['pipe','w']], $pipes);
    $out = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    if (proc_close($p) !== 0 || !is_dir("$j/src")) { throw new RuntimeException('junction failed'); }
    foreach (glob("$APP/writable/cache/*") as $f) { if (is_file($f)) unlink($f); }
    echo 'module: '.basename($dir)."\n";
}

function reset_db(): void
{
    db()->query('DROP DATABASE rolewarden_test');
    db()->query('CREATE DATABASE rolewarden_test');
    db()->select_db('rolewarden_test');
}

function readme_install(): void
{
    spark('migrate', '-n', 'CodeIgniter\Settings');
    spark('migrate', '-n', 'CodeIgniter\Shield');
    spark('migrate', '-n', 'RoleWarden');
    spark('db:seed', 'RoleWarden\Database\Seeds\RoleWardenSeeder');
}

/** Perms granted to a role slug, as slugs. */
function role_perms(string $slug): array
{
    return array_column(q('SELECT p.slug FROM acl_role_permissions rp JOIN acl_roles r ON r.id=rp.role_id JOIN acl_permissions p ON p.id=rp.permission_id WHERE r.slug=? ORDER BY p.slug', [$slug]), 'slug');
}

function acl_state(): string
{
    $out = '';
    foreach (['acl_roles', 'acl_permissions', 'acl_role_permissions', 'acl_user_roles', 'acl_user_permissions'] as $t) {
        $rows = q("SELECT * FROM $t");
        $out .= "$t:" . json_encode($rows) . "\n";
    }
    $out .= 'migrations:' . json_encode(q('SELECT class, namespace, batch FROM migrations ORDER BY id')) . "\n";
    return $out;
}

/** Toasts as served without JavaScript (the <noscript> static stack). */
function static_toasts(string $html): array
{
    $out = [];
    if (preg_match('/<div class="rw-toasts rw-toasts--static">(.*?)<\/div>\s*<\/noscript>/s', $html, $m)) {
        preg_match_all('/<div class="rw-toast rw-toast--(\w+)"([^>]*)>\s*<p><span class="rw-toast__kind">([^<]*)<\/span>\s*(.*?)<\/p>/s', $m[1], $ts, PREG_SET_ORDER);
        foreach ($ts as $t) {
            $out[] = ['type' => $t[1], 'alert' => str_contains($t[2], 'role="alert"'), 'kind' => $t[3], 'msg' => trim(html_entity_decode(strip_tags($t[4]))), 'close' => str_contains($m[1], 'rw-toast__close')];
        }
    }
    return $out;
}

/** Messages handed to the Alpine stack (x-data JSON). */
function js_toasts(string $html): array
{
    if (! preg_match('/<div class="rw-toasts" aria-live="polite" x-data="rwToasts\(([^"]*)\)"/', $html, $m)) {
        return [];
    }
    return json_decode(html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5), true)['messages'] ?? [];
}

function toast_ok(array $ts, string $type): bool
{
    $want = ['success' => 'Saved', 'warning' => 'Warning:', 'error' => 'Error:'][$type];
    foreach ($ts as $t) {
        if ($t['type'] === $type && $t['kind'] === $want && $t['msg'] !== '' && ($type !== 'error' || $t['alert']) && ! $t['close']) {
            return true;
        }
    }
    return false;
}

function after(Client $c): Client
{
    $path = preg_replace('#^.*?index\.php/?#', '', $c->location);
    return $c->get($path === '' ? 'rolewarden/users' : $path);
}

/** The page reached after a form POST shows a toast of $type (static + JS), never in granted colour. */
function action_toast(string $id, string $label, Client $c, string $type): void
{
    $was = $c->status;
    $page = after($c);
    $GLOBALS['SEEN'][] = [$label, $page->status, $page->body];
    $st = static_toasts($page->body);
    $js = js_toasts($page->body);
    check($id, "$label: $type toast (HTTP $was then {$page->status})",
        in_array($was, [302, 303], true) && toast_ok($st, $type) && count($js) >= 1 && $js[0]['type'] === $type && ! preg_match('/rw-toast[^"]*granted|granted[^"]*rw-toast/', $page->body),
        'static=' . json_encode($st) . ' js=' . json_encode($js));
}

function nav_settings(string $html): bool
{
    return (bool) preg_match('#<ul class="rw-nav">(?:(?!</ul>).)*/rolewarden/settings"#s', $html);
}

function selected_default(string $html): ?string
{
    if (! preg_match('#<select[^>]*name="default_role"[^>]*>(.*?)</select>#s', $html, $m)) {
        return null;
    }
    return preg_match('/<option value="([^"]*)" selected/', $m[1], $o) ? $o[1] : '(none selected)';
}

function options_default(string $html): array
{
    preg_match('#<select[^>]*name="default_role"[^>]*>(.*?)</select>#s', $html, $m);
    preg_match_all('/<option value="([^"]*)"/', $m[1] ?? '', $o);
    return $o[1];
}

function facts(string $html): array
{
    preg_match('#<dt>Last changed</dt><dd>(.*?)</dd>#s', $html, $a);
    preg_match('#<dt>By</dt><dd>(.*?)</dd>#s', $html, $b);
    return [trim(html_entity_decode($a[1] ?? '?')), trim(html_entity_decode($b[1] ?? '?'))];
}

function add_user(string $username, string $email, ?string $roleSlug, string $pw): int
{
    q('INSERT INTO users (username, active, created_at, updated_at) VALUES (?,1,NOW(),NOW())', [$username]);
    $id = (int) db()->insert_id;
    q('INSERT INTO auth_identities (user_id, type, secret, secret2, force_reset, created_at, updated_at) VALUES (?,?,?,?,0,NOW(),NOW())', [$id, 'email_password', $email, password_hash($pw, PASSWORD_DEFAULT)]);
    if ($roleSlug !== null) {
        q('INSERT INTO acl_user_roles (user_id, role_id) SELECT ?, id FROM acl_roles WHERE slug=?', [$id, $roleSlug]);
    }
    return $id;
}

function add_role(string $slug, array $perms, int $super = 0): int
{
    q('INSERT INTO acl_roles (slug, name, description, parent_id, is_system, is_super_admin, created_at) VALUES (?,?,?,NULL,0,?,NOW())', [$slug, strtoupper($slug), 'V1-M1 test role', $super]);
    $id = (int) db()->insert_id;
    foreach ($perms as $p) {
        q('INSERT INTO acl_role_permissions (role_id, permission_id) SELECT ?, id FROM acl_permissions WHERE slug=?', [$id, $p]);
    }
    return $id;
}

function roles_of(string $email): array
{
    return array_column(q('SELECT r.slug FROM acl_user_roles ur JOIN acl_roles r ON r.id=ur.role_id JOIN auth_identities i ON i.user_id=ur.user_id WHERE i.secret=? ORDER BY r.slug', [$email]), 'slug');
}

function user_exists(string $email): bool
{
    return (int) q1('SELECT COUNT(*) FROM auth_identities WHERE secret=?', [$email]) === 1;
}

function stored(): string
{
    return json_encode(q('SELECT class, `key`, value FROM settings ORDER BY id'));
}

function register(string $u, string $email, string $pw): Client
{
    $c = new Client('m1-reg-' . $u);
    $t = $c->token('register');
    $c->req('POST', 'register', ['csrf_test_name' => $t, 'email' => $email, 'username' => $u, 'password' => $pw, 'password_confirm' => $pw]);
    $GLOBALS['SEEN'][] = ["register $u", $c->status, $c->body];
    return $c;
}

function panel_create(Client $adm, string $u, string $email, string $pw): Client
{
    return $adm->post('rolewarden/users', ['username' => $u, 'email' => $email, 'password' => $pw], 'rolewarden/users/create');
}

function set_default(Client $adm, string $v): void
{
    $adm->post('rolewarden/settings', ['default_role' => $v], 'rolewarden/settings');
}

// Everything below is derived from the permitted specification and served HTML.
$SEEN = [];
function page(Client $c, string $url): string {
    $c->get($url); $GLOBALS['SEEN'][] = [$url, $c->status, $c->body]; return $c->body;
}
function forms(string $h): array {
    $d = new DOMDocument(); @$d->loadHTML($h); $out=[];
    foreach ($d->getElementsByTagName('form') as $f) {
        $fields=[];
        foreach ($f->getElementsByTagName('input') as $i) if ($i->getAttribute('name')) $fields[$i->getAttribute('name')]=$i->getAttribute('value');
        foreach ($f->getElementsByTagName('select') as $i) {
            $v=null; foreach ($i->getElementsByTagName('option') as $o) if ($v===null || $o->hasAttribute('selected')) $v=$o->getAttribute('value');
            $fields[$i->getAttribute('name')]=$v;
        }
        $out[]=['action'=>$f->getAttribute('action'),'fields'=>$fields,'text'=>trim(preg_replace('/\s+/', ' ', $f->textContent)), 'html'=>$d->saveHTML($f)];
    }
    return $out;
}
function matching(array $fs, string $text): array {
    foreach ($fs as $f) if (str_contains($f['text'],$text)) return $f;
    throw new RuntimeException('Form not found: '.$text);
}
function submit(Client $c, array $f, array $values=[]): void {
    $c->req('POST',$f['action'],array_replace($f['fields'],$values));
    $GLOBALS['SEEN'][]=[$f['action'],$c->status,$c->body];
}
function follow(Client $c): string {
    return page($c, $c->location ?: 'rolewarden/profile');
}
function cache_clear(): void {
    global $APP;
    foreach (glob("$APP/writable/cache/*") as $f) if(is_file($f)) unlink($f);
}
function login2(string $name,string $email,string $pw,bool $remember=false): Client {
    $c=new Client('v2-'.$name); $f=matching(forms(page($c,'login')),'Login');
    $v=['email'=>$email,'password'=>$pw];
    if($remember) $v['remember']='on'; else unset($f['fields']['remember']);
    submit($c,$f,$v); page($c,'rolewarden/profile'); return $c;
}
function signed(Client $c): bool { page($c,'rolewarden/profile'); return $c->status===200 && str_contains($c->body,'current_password'); }
function settings(Client $c,array $v): string {
    $f=matching(forms(page($c,'rolewarden/settings')),'Save settings'); submit($c,$f,$v); return follow($c);
}
function defaults(Client $c): array { return matching(forms(page($c,'rolewarden/settings')),'Save settings')['fields']; }
function no_session_cookie(Client $c): void {
    $lines=file($c->jar); file_put_contents($c->jar, implode('',array_filter($lines,fn($l)=>!str_contains($l,"\tci_session\t"))));
}
function sessions(int $id): int { return (int)q1('SELECT COUNT(*) FROM acl_sessions WHERE user_id=?',[$id]); }
function tokens(int $id): int { return (int)q1('SELECT COUNT(*) FROM auth_remember_tokens WHERE user_id=?',[$id]); }
function session_cookie(Client $c): string {preg_match('/\tci_session\t([^\s]+)/',(string)file_get_contents($c->jar),$m);return $m[1]??'';}
function own_action(Client $c,string $text): array { return matching(forms(page($c,'rolewarden/sessions')),$text); }
function field_error(string $h,string $name): bool {
    $d=new DOMDocument(); @$d->loadHTML($h); $xp=new DOMXPath($d);
    $node=$xp->query('//*[@name="'.$name.'"]')->item(0); if(!$node) return false;
    for($p=$node->parentNode,$i=0;$p && $i<3;$p=$p->parentNode,$i++) {
        if (preg_match('/Error:/i',$p->textContent) && strpos($d->saveHTML($p),'Error:') > strpos($d->saveHTML($p),'name="'.$name.'"')) return true;
    } return false;
}
/** acl_sessions ids of a user. */
function sids(int $id): array { return array_map('intval',array_column(q('SELECT id FROM acl_sessions WHERE user_id=? ORDER BY id',[$id]),'id')); }
/** login2 plus the acl_sessions ids that the login created for $uid (should be exactly one). */
function login3(string $name,string $email,string $pw,bool $remember,int $uid): array {
    $before=sids($uid); $c=login2($name,$email,$pw,$remember); return [$c,array_values(array_diff(sids($uid),$before))];
}
/** Neither the static nor the Alpine toast stack carries a message. */
function no_toast(string $h): bool { return static_toasts($h)===[] && js_toasts($h)===[]; }
/** The form whose action targets exactly session $sid. */
function form_for(array $fs,string $sid): ?array { foreach($fs as $f) if(preg_match('#/sessions/'.$sid.'/revoke$#',$f['action'])) return $f; return null; }
register_shutdown_function(function () { foreach(glob(sys_get_temp_dir().'/rwv1-*-'.getmypid().'.jar') as $f) @unlink($f); });

if (($argv[4] ?? '') === 'extra') {require __DIR__.'/verify-v2.extra.php';exit;}
if (($argv[4] ?? '') === 'adapt') {require __DIR__.'/verify-v2.adapt.php';exit;}
if (($argv[4] ?? '') === 'regression-v1') {
    // Equivalent commands from verify-v1.sh. Original PHP test assertions unchanged.
    reset_db();use_module($MOD_NEW);readme_install();
    $dir=dirname($APP).'/regression';
    foreach(['development'=>'verify-v1.php','production'=>'verify-v1.prod.php'] as $environment=>$script){
        $env=file_get_contents("$APP/.env");file_put_contents("$APP/.env",preg_replace('/CI_ENVIRONMENT = \w+/','CI_ENVIRONMENT = '.$environment,$env));
        cache_clear();server_start();
        try {
            $log=__DIR__.'/verify-v2.regression-'.$environment.'.output.txt';
            $p=proc_open(['php','-d','error_reporting=-1',$dir.'/'.$script],[1=>['file',$log,'w'],2=>['file',$log,'a']],$pipes,$APP,ci_env(),['bypass_shell'=>true]);
            echo 'Regression '.$environment.' rc='.proc_close($p)."\n";
        }finally{server_stop();}
    }
    exit;
}
function alerts(string $h): string {
    $d=new DOMDocument();@$d->loadHTML($h);$xp=new DOMXPath($d);$texts=[];
    foreach($xp->query('//*[@role="alert"]') as $n) $texts[]=trim(preg_replace('/\s+/',' ',$n->textContent));
    return implode(' | ',$texts);
}

try {
echo "== 1 Upgrade, rollback and fresh install ==\n";
reset_db(); use_module($MOD_OLD); readme_install();
$before=acl_state(); $batch=(int)q1('SELECT MAX(batch) FROM migrations');
check('A01','V1 installed without sessions permissions',(int)q1("SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'sessions.%'")===0);
use_module($MOD_NEW); spark('migrate','-n','RoleWarden');
check('A02','Upgrade grants both sessions permissions to admin',count(array_intersect(['sessions.view','sessions.revoke'],role_perms('admin')))===2);
check('A03','Upgrade creates sessions table',count(q("SHOW TABLES LIKE 'acl_sessions'"))===1);
spark('migrate:rollback','-b',(string)$batch,'-f');
check('A04','Rollback returns ACL and migration rows to baseline',acl_state()===$before && q("SHOW TABLES LIKE 'acl_sessions'")===[]);
spark('migrate','-n','RoleWarden');
check('A05','Upgrade reapplies',count(array_intersect(['sessions.view','sessions.revoke'],role_perms('admin')))===2);
reset_db();readme_install();
check('A06','Fresh install grants sessions permissions',count(array_intersect(['sessions.view','sessions.revoke'],role_perms('admin')))===2);
spark('migrate:rollback','-b','2','-f');
check('A07','Fresh rollback removes module tables',q("SHOW TABLES LIKE 'acl_%'")===[]);
spark('migrate','-n','RoleWarden');spark('db:seed','RoleWarden\\Database\\Seeds\\RoleWardenSeeder');
$F=make_fixtures();$U=$F['u'];$pw=$F['pw'];$uid=$U['subject']['id'];$email=$U['subject']['email'];
server_start();
$adm=login2('admin',$U['admin2']['email'],$pw);
check('A08','Admin opens V2 settings',str_contains(page($adm,'rolewarden/settings'),'name="sign_in_rate"'));
$initial=defaults($adm);
check('G01','Documented initial session/remember values',$initial['session_lifetime']==='7200' && $initial['remember_length']==='2592000');
settings($adm,['sign_in_rate'=>'100']);cache_clear();

echo "== 2 Own sessions and remembered revocation ==\n";
[$a,$aIds]=login3('a',$email,$pw,false,$uid);[$b,$bIds]=login3('b',$email,$pw,true,$uid);[$c,$cIds]=login3('c',$email,$pw,false,$uid);
check('B00','Each sign-in creates exactly one tracked session row',count($aIds)===1&&count($bIds)===1&&count($cIds)===1,json_encode([$aIds,$bIds,$cIds]));
check('B01','Ordinary user accesses profile and sessions',signed($a) && str_contains(page($a,'rolewarden/sessions'),'this browser'));
check('B02','Three independent browsers are tracked',sessions($uid)===3 && tokens($uid)===1, 'sessions='.sessions($uid).' tokens='.tokens($uid));
$fs=forms(page($a,'rolewarden/sessions'));
echo '[INFO] Session forms '.json_encode(array_map(fn($f)=>[$f['action'],$f['text']],$fs))."\n";
check('B01b','Own list offers a Sign out for each other browser and none for this one',form_for($fs,(string)$bIds[0])!==null&&form_for($fs,(string)$cIds[0])!==null&&form_for($fs,(string)$aIds[0])===null);
// b's row is identified by the id its own sign-in created, not by its position in the list.
$bid=(string)$bIds[0];$bf=form_for($fs,$bid);
if(!$bf) throw new RuntimeException('Cannot locate served session revoke action');
submit($a,$bf);action_toast('T01','Own single sign out',$a,'success');
check('B02b','Only the chosen row is gone',sids($uid)===[$aIds[0],$cIds[0]],json_encode(sids($uid)));
check('B03','Single revocation refuses remembered browser on NEXT request',!signed($b), 'status='.$b->status);
check('B04','Revoked remembered browser cannot silently return',!signed($b) && tokens($uid)===0);
check('B05','Single revocation preserves actor and other session',signed($a)&&signed($c));
$b=login2('b2',$email,$pw,true);
submit($a,own_action($a,'Sign out everywhere else'));action_toast('T02','Own sign out everywhere else',$a,'success');
check('B06','Everywhere else preserves requester',signed($a));
check('B07','Everywhere else refuses all others next request',!signed($b)&&!signed($c) && sessions($uid)===1 && tokens($uid)===0);

echo "== 3 Remembered browser with no open session ==\n";
$b=login2('orphan',$email,$pw,true);
q('DELETE FROM acl_sessions WHERE user_id=? AND remember_selector IS NOT NULL',[$uid]);no_session_cookie($b);
$h=page($a,'rolewarden/sessions');$fs=forms($h);
echo '[INFO] Orphan forms '.json_encode(array_map(fn($f)=>[$f['action'],$f['text']],$fs))."\n";
$forget=matching($fs,'Forget');submit($a,$forget);action_toast('T03','Forget remembered browser',$a,'success');
check('C01','Forgotten browser without session no longer returns',tokens($uid)===0 && !signed($b));
$b=login2('remember-linked',$email,$pw,true);
$tid=(string)q1('SELECT id FROM auth_remember_tokens WHERE user_id=? ORDER BY id DESC LIMIT 1',[$uid]);
$forget['action']=preg_replace('#/remembered/\d+/#','/remembered/'.$tid.'/',$forget['action']);
$forget['fields']['csrf_test_name']=csrf_of(page($a,'rolewarden/sessions'));submit($a,$forget);
check('C02','Revoking remember token also closes its open session on next request',!signed($b)&&tokens($uid)===0);

echo "== 4 Other users, direct POST permissions ==\n";
[$b,$tbIds]=login3('target',$email,$pw,true,$uid);[$tc,$tcIds]=login3('target-other',$email,$pw,false,$uid);$detail='rolewarden/users/'.$uid;
$h=page($adm,$detail);$fs=forms($h);echo '[INFO] Admin session forms '.json_encode(array_map(fn($f)=>[$f['action'],$f['text']],array_filter($fs,fn($f)=>str_contains($f['action'],'session')||str_contains($f['action'],'remember'))))."\n";
check('D01','Admin can see another users sessions',str_contains($h,'Sessions')&&str_contains($h,'Sign out'));
add_role('v2-view',['users.view','sessions.view']);add_user('v2viewer','viewer@v2.test','v2-view',$pw);
$vw=login2('viewer','viewer@v2.test',$pw);$lim=login2('limited',$U['limited']['email'],$pw);
$vh=page($vw,$detail);$lh=page($lim,$detail);
check('D02','sessions.view sees sessions without revoke buttons',str_contains($vh,'Sessions') && !preg_match('#<form[^>]+action="[^"]*(sessions|remember)#',$vh));
check('D03','Without sessions.view session section absent',!preg_match('#<h2>Sessions</h2>#',$lh));
$rf=null; foreach($fs as $f) if(str_contains($f['action'],'session')&&str_contains($f['text'],'Sign out')) {$rf=$f;break;}
if(!$rf) throw new RuntimeException('Admin revoke form missing');
foreach(['viewer'=>$vw,'limited'=>$lim] as $key=>$actor) {
    $n=sessions($uid);$rf['fields']['csrf_test_name']=csrf_of(page($actor,'rolewarden/profile'));submit($actor,$rf);
    check('D04-'.$key,'Direct POST without revoke permission denied beyond CSRF',sessions($uid)===$n && !csrf_refused($actor) && $actor->status!==200,'HTTP '.$actor->status);
}
// D05 (Collaudatore): pick the target's remembered browser by the session id its sign-in created.
$rf=form_for(forms(page($adm,$detail)),(string)$tbIds[0]);
check('D05a','Admin detail lists a Sign out for the chosen session',$rf!==null&&count($tbIds)===1);
$tokBefore=tokens($uid);submit($adm,$rf);action_toast('T04','Admin single sign out of another user',$adm,'success');
check('D05','Admin revocation takes effect on target next request',!signed($b));
check('D05b','Only the chosen session and its remember token are gone; the other browser stays',!in_array($tbIds[0],sids($uid),true)&&in_array($tcIds[0],sids($uid),true)&&tokens($uid)===$tokBefore-1&&signed($tc),json_encode(sids($uid)).' tokens '.$tokBefore.'->'.tokens($uid));
// A revoke-only actor must not read the sessions section; permission split is tested independently.
add_role('v2-revoke',['users.view','sessions.revoke']);add_user('v2revoker','revoker@v2.test','v2-revoke',$pw);
$rv=login2('revoker','revoker@v2.test',$pw);
check('D06','sessions.revoke does not grant sessions.view',!str_contains(page($rv,$detail),'<h2>Sessions</h2>'));
// Observation only: can a revoke-only actor sign a session out by direct POST? Spec pairs view+revoke.
[$probe,$pIds]=login3('revoke-only-target',$email,$pw,false,$uid);$pf=form_for(forms(page($adm,$detail)),(string)($pIds[0]??-1));
if($pf){$pf['fields']['csrf_test_name']=csrf_of(page($rv,'rolewarden/profile'));submit($rv,$pf);echo '[INFO] D07 revoke-only direct POST HTTP '.$rv->status.' location='.$rv->location.' session still present='.json_encode(in_array($pIds[0],sids($uid),true))."\n";}
$rf=matching(forms(page($adm,$detail)),'Sign out everywhere else');submit($adm,$rf);action_toast('T05','Admin sign out everywhere for another user',$adm,'success');
check('D08','Admin total revocation leaves the target with no sessions and no tokens',sids($uid)===[]&&tokens($uid)===0&&!signed($tc),json_encode(sids($uid)));

echo "== 5 Inactivity ==\n";cache_clear();
$a=login2('idle-normal',$email,$pw);$b=login2('idle-remember',$email,$pw,true);
q('UPDATE acl_sessions SET last_seen_at=DATE_SUB(NOW(),INTERVAL 100 MINUTE) WHERE user_id=?',[$uid]);
check('E00','Session idle for less than the 2 hour default stays signed in',signed($a)&&signed($b));
$oldCookie=session_cookie($b);
$oldIds=array_column(q('SELECT id FROM acl_sessions WHERE user_id=?',[$uid]),'id');
q('UPDATE acl_sessions SET last_seen_at=DATE_SUB(NOW(),INTERVAL 3 HOUR) WHERE user_id=?',[$uid]);
check('E01','Idle nonremembered session expires next request',!signed($a));
check('E02','Idle remembered browser enters again',signed($b));
$newIds=array_column(q('SELECT id FROM acl_sessions WHERE user_id=?',[$uid]),'id');
echo '[INFO] idle session ids old='.json_encode($oldIds).' next='.json_encode($newIds)."\n";
check('E03','Remembered re-entry uses a NEW PHP session',session_cookie($b)!==''&&session_cookie($b)!==$oldCookie);
$fresh=array_values(array_diff($newIds,$oldIds));
check('E05','Re-entered session is tracked within its own request and listed on the admin detail',count($fresh)===1&&form_for(forms(page($adm,$detail)),(string)($fresh[0]??-1))!==null,'new rows '.json_encode($fresh));
// Interleave revocation between automatic re-entry and its next request.
$rf=matching(forms(page($adm,$detail)),'Sign out everywhere else');submit($adm,$rf);
check('E04','Revocation immediately after automatic re-entry applies next request',!signed($b),'HTTP '.$b->status.' tracked='.sessions($uid).' tokens='.tokens($uid));
echo '[INFO] ids after further request='.json_encode(array_column(q('SELECT id FROM acl_sessions WHERE user_id=?',[$uid]),'id'))."\n";

echo "== 6 Password profile ==\n";cache_clear();
$a=login2('password-a',$email,$pw);$b=login2('password-b',$email,$pw,true);$c=login2('password-c',$email,$pw);$newPw='Q7!'.bin2hex(random_bytes(10)).'Az';
foreach([
 ['F01','Wrong current password','wrong',$newPw,$newPw,'current_password'],
 ['F02','Weak new password',$pw,'123','123','password'],
 ['F03','Missing confirmation',$pw,$newPw,'','password_confirm'],
 ['F04','Mismatched confirmation',$pw,$newPw,$newPw.'x','password_confirm'],
] as [$id,$label,$current,$new,$confirm,$field]) {
    $f=matching(forms(page($a,'rolewarden/profile')),'password');submit($a,$f,['current_password'=>$current,'password'=>$new,'password_confirm'=>$confirm]);$h=follow($a);
    check($id,$label.' rejected with error under field and no toast',field_error($h,$field) && no_toast($h),substr(preg_replace('/\s+/',' ',strip_tags(clean_html($h))),0,600));
    check($id.'b','Rejected change leaves password unchanged',password_verify($pw,q1("SELECT secret2 FROM auth_identities WHERE user_id=? AND type='email_password'",[$uid])));
}
$f=matching(forms(page($a,'rolewarden/profile')),'password');submit($a,$f,['current_password'=>$pw,'password'=>$newPw,'password_confirm'=>$newPw]);action_toast('T06','Password changed',$a,'success');
check('F05','Password change keeps requesting browser',signed($a));
check('F06','Password change revokes other remembered browser next request',!signed($b));
check('F06b','Password change revokes other plain browser; only the requester remains',!signed($c)&&count(sids($uid))===1&&tokens($uid)===0,json_encode(sids($uid)).' tokens='.tokens($uid));
$newLogin=login2('new-password',$email,$newPw);$oldLogin=login2('old-password',$email,$pw);
check('F07','New password works and old does not',signed($newLogin)&&!signed($oldLogin));

echo "== 7 Sign-in settings validation and persistence ==\n";cache_clear();
foreach(['session_lifetime'=>['1800','7200','28800','86400'],'remember_length'=>['0','604800','2592000','7776000']] as $field=>$values) foreach($values as $v) {
    $h=settings($adm,[$field=>$v]);$actual=defaults($adm);
    check('G02-'.$field.'-'.$v,'Allowed value persists',$actual[$field]===$v);
    if($field==='remember_length') {
        $anon=new Client('v2-login-'.$v);$lh=page($anon,'login');
        check('G03-'.$v,'Login remember option reflects setting',$v==='0'?!str_contains($lh,'name="remember"'):str_contains($lh,'Remember me for '.((int)$v/86400).' days'));
        if($v!=='0') {
            cache_clear();$t=login2('duration-'.$v,$email,$newPw,true);
            $expires=q1('SELECT expires FROM auth_remember_tokens WHERE user_id=? ORDER BY id DESC LIMIT 1',[$uid]);
            $seconds=strtotime((string)$expires)-time();
            check('G04-'.$v,'Remember token lifetime matches selected duration',abs($seconds-(int)$v)<90,'delta='.$seconds);
        } else {
            // Collaudatore: with Remember me off, a forged remember=on must not create a token.
            cache_clear();$n=tokens($uid);$t=login2('duration-off',$email,$newPw,true);
            check('G04-0','Remember me off: a forced remember=on creates no remember token',signed($t)&&tokens($uid)===$n,'tokens '.$n.'->'.tokens($uid));
        }
    }
}
$f=matching(forms(page($adm,'rolewarden/settings')),'Save settings');submit($adm,$f,['lock_minutes'=>'15']);action_toast('T07','Settings saved',$adm,'success');
foreach(['lock_attempts'=>['3','20'],'lock_minutes'=>['1','1440'],'sign_in_rate'=>['5','100']] as $field=>$vs) foreach($vs as $v){settings($adm,[$field=>$v]);check('G05-'.$field.'-'.$v,'Numeric boundary persists',defaults($adm)[$field]===$v);}
foreach(['session_lifetime'=>['1799','bad'],'remember_length'=>['1','-1'],'lock_attempts'=>['2','21','3.5'],'lock_minutes'=>['0','1441','x'],'sign_in_rate'=>['4','101','5.5']] as $field=>$vs) foreach($vs as $v){
    $prior=stored();$h=settings($adm,[$field=>$v]);
    check('G06-'.$field.'-'.$v,'Out of range rejected inline without writes/toast',stored()===$prior&&field_error($h,$field)&&no_toast($h),substr(preg_replace('/\s+/',' ',strip_tags(clean_html($h))),0,400));
}
add_role('v2-settings-view',['settings.view']);add_user('v2settings','settings@v2.test','v2-settings-view',$pw);
$sv=login2('settings-view','settings@v2.test',$pw);$h=page($sv,'rolewarden/settings');
check('G07','View-only settings has no save button', $sv->status===200&&!str_contains($h,'type="submit"'));
$prior=stored();$f=matching(forms(page($adm,'rolewarden/settings')),'Save settings');$f['fields']['csrf_test_name']=csrf_of(page($sv,'rolewarden/profile'));submit($sv,$f,['sign_in_rate'=>'99']);
check('G08','Only settings.update saves via direct POST',stored()===$prior&&!csrf_refused($sv));

echo "== 8 Email lock and IP rate ==\n";
settings($adm,['session_lifetime'=>'7200','remember_length'=>'2592000','lock_attempts'=>'3','lock_minutes'=>'1','sign_in_rate'=>'100']);cache_clear();
$messages=[];
foreach(['known'=>$email,'unknown'=>'absent@v2.test'] as $kind=>$mail) {
    q('DELETE FROM auth_logins WHERE identifier=?',[$mail]);
    for($i=1;$i<=3;$i++) {$t=login2('lock-'.$kind.'-'.$i,$mail,'wrong');check('H01-'.$kind.'-'.$i,'Failure does not authenticate',!signed($t));}
    $t=new Client('v2-locked-'.$kind);$f=matching(forms(page($t,'login')),'Login');unset($f['fields']['remember']);submit($t,$f,['email'=>$mail,'password'=>$kind==='known'?$newPw:'wrong']);
    $status=$t->status;$h=in_array($status,[302,303],true)?follow($t):$t->body;
    $messages[$kind]=alerts($h);
    echo '[INFO] lock '.$kind.' HTTP '.$status.' alert='.$messages[$kind]."\n";
    check('H02-'.$kind,'Email is locked at attempt beyond threshold',!signed($t)&&preg_match('/(try again|too many|locked|wait)/i',$messages[$kind]),'HTTP '.$status.' '.$messages[$kind]);
    if($kind==='known'){
        q('UPDATE auth_logins SET date=DATE_SUB(date,INTERVAL 30 SECOND) WHERE identifier=?',[$mail]);
        $t=new Client('v2-still-'.$kind);$f=matching(forms(page($t,'login')),'Login');unset($f['fields']['remember']);submit($t,$f,['email'=>$mail,'password'=>$newPw]);
        check('H02b','Still locked before the configured minute has passed',!signed($t));
    }
    q('UPDATE auth_logins SET date=DATE_SUB(NOW(),INTERVAL 2 MINUTE) WHERE identifier=?',[$mail]);
    $t=new Client('v2-unlock-'.$kind);$f=matching(forms(page($t,'login')),'Login');unset($f['fields']['remember']);
    submit($t,$f,['email'=>$mail,'password'=>$kind==='known'?$newPw:'wrong']);
    if($kind==='known')check('H03','Registered email unlocks after duration',signed($t));
    else {$h=follow($t);$msg=alerts($h);echo '[INFO] unlocked unknown alert='.$msg."\n";check('H04','Unknown email returns normal failure after lock expires',$msg!==''&&!preg_match('/(too many|locked|wait.*minute)/i',$msg),$msg);}
}
check('H05','Same locked response for registered and unknown email',$messages['known']===$messages['unknown']);
// Failures are counted since the last successful sign-in: 2 fails, success, 2 fails -> not locked at N=3.
for($i=1;$i<=2;$i++) login2('reset-fail-a'.$i,$email,'wrong');
$ok=login2('reset-ok',$email,$newPw);
for($i=1;$i<=2;$i++) login2('reset-fail-b'.$i,$email,'wrong');
check('H07','A successful sign-in restarts the failure count',signed($ok)&&signed(login2('reset-after',$email,$newPw)));
settings($adm,['sign_in_rate'=>'5','lock_attempts'=>'20']);cache_clear();
$statuses=[];$attemptRows=[];$rateMessages=[];
for($i=1;$i<=6;$i++){
    $t=new Client('v2-rate-'.$i);$h=page($t,'login'); echo '[INFO] rate GET '.$i.' status='.$t->status."\n";
    if($t->status!==200){$statuses[]=$t->status;continue;}
    $f=matching(forms($h),'Login');unset($f['fields']['remember']);submit($t,$f,['email'=>'rate'.$i.'@v2.test','password'=>'wrong']);$statuses[]=$t->status;
    $rateMessages[]=alerts(follow($t));$attemptRows[]=(int)q1('SELECT COUNT(*) FROM auth_logins WHERE identifier=?',['rate'.$i.'@v2.test']);
    echo '[INFO] rate POST '.$i.' status='.end($statuses).' logins='.end($attemptRows).' alert='.end($rateMessages)."\n";
}
check('H06','Configured IP limit: five attempts reach Shield, sixth blocked',$attemptRows===[1,1,1,1,1,0]&&preg_match('/(too many|try again|wait)/i',$rateMessages[5]??''),json_encode([$statuses,$attemptRows,$rateMessages]));
cache_clear();settings($adm,['sign_in_rate'=>'100']);cache_clear();

echo "== 10 HTTP publication checks in both environments ==\n";
foreach(['development','production'] as $environment){
    server_stop();$env=file_get_contents("$APP/.env");file_put_contents("$APP/.env",preg_replace('/CI_ENVIRONMENT = \w+/','CI_ENVIRONMENT = '.$environment,$env));server_start();
    foreach(['rolewarden/profile','rolewarden/sessions','rolewarden/settings','rolewarden/users/'.$uid] as $url){
        $h=page($adm,$url);$fs=forms($h);
        foreach($fs as $f) if(str_contains($f['action'],'session')||str_contains($f['action'],'remember')||str_contains($f['action'],'profile')||str_contains($f['action'],'settings')){
            check('J01-'.$environment,'New form has CSRF',!empty($f['fields']['csrf_test_name']),$f['action']);
            unset($f['fields']['csrf_test_name']);submit($adm,$f);
            $st=$adm->status;$loc=$adm->location;$back=in_array($st,[302,303],true)?follow($adm):$adm->body;
            check('J02-'.$environment,'Missing CSRF is refused',$st===403 || (in_array($st,[302,303],true)&&!str_contains($loc,'password')), 'HTTP '.$st.' '.$f['action']);
            if($st!==403) check('T08-'.$environment,'CSRF refusal redirected back shows an error toast',toast_ok(static_toasts($back),'error')&&count(js_toasts($back))>=1,$f['action'].' '.json_encode(static_toasts($back)));
        }
    }
    // Stored username is deliberately hostile; inspect rendered profile, not source.
    $original=q1('SELECT username FROM users WHERE id=?',[$U['admin2']['id']]);$payload='<script>alert("V2")</script>';
    q('UPDATE users SET username=? WHERE id=?',[$payload,$U['admin2']['id']]);$h=page($adm,'rolewarden/profile');
    check('J03-'.$environment,'Stored profile name escaped',!str_contains($h,$payload)&&str_contains($h,'&lt;script&gt;'));
    q('UPDATE users SET username=? WHERE id=?',[$original,$U['admin2']['id']]);
    // Hostile user agent stored by the session tracker, rendered on both session lists.
    $ua='<script>alert("UA")</script>';$ev=new Client('v2-ua-'.$environment);$f=matching(forms(page($ev,'login')),'Login');unset($f['fields']['remember']);
    $ev->req('POST',$f['action'],array_replace($f['fields'],['email'=>$email,'password'=>$newPw]),['User-Agent: '.$ua]);$ev->req('GET','rolewarden/sessions',null,['User-Agent: '.$ua]);
    $h1=$ev->body;$h2=page($adm,'rolewarden/users/'.$uid);
    check('J07-'.$environment,'Hostile user agent never rendered raw on own and admin session lists',$ev->status===200&&!str_contains($h1,$ua)&&!str_contains($h2,$ua),'escaped shown own='.json_encode(str_contains($h1,'&lt;script&gt;')).' admin='.json_encode(str_contains($h2,'&lt;script&gt;')));
    echo '[INFO] J07 escaped UA text shown: own='.json_encode(str_contains($h1,'&lt;script&gt;')).' admin='.json_encode(str_contains($h2,'&lt;script&gt;'))."\n";
    foreach(['rolewarden/sessions/abc/revoke','rolewarden/sessions/remembered/abc/revoke','rolewarden/users/'.$uid.'/sessions/1e9/revoke'] as $bogus){
        $adm->req('POST',$bogus,['csrf_test_name'=>csrf_of(page($adm,'rolewarden/profile'))]);$GLOBALS['SEEN'][]=[$bogus,$adm->status,$adm->body];
        check('J08-'.$environment,'Malformed id refused without SQL text',$adm->status!==200&&!leaks_sql($adm->body),$bogus.' HTTP '.$adm->status);
    }
    $h=page($adm,'rolewarden/settings');$f=matching(forms($h),'Save settings');submit($adm,$f,['lock_attempts'=>"' OR 1=1 --"]);follow($adm);
}
server_stop();
$bad=[];foreach($SEEN as [$url,$status,$body])if(leaks_sql($body)||preg_match('/PHP (Warning|Notice|Fatal)|ErrorException|Uncaught /',clean_html($body)))$bad[]=$url.' '.$status;
check('J04','No SQL/PHP error text on all captured responses',$bad===[],implode(';',$bad));
$bad=[];foreach(glob("$APP/writable/logs/*.log") as $f)foreach(file($f) as $line)if(preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /',$line)&&!str_contains($line,'SecurityException')&&!str_contains($line,'The action you requested is not allowed'))$bad[]=trim($line);
check('J05','Max-level app log clean except deliberately rejected CSRF',$bad===[],implode("\n",$bad));
check('J06','PHP server log has no warnings/errors',!preg_match('/PHP (Warning|Notice|Deprecated|Fatal)/',file_get_contents(__DIR__.'/verify-v2.server.log')));
} catch (Throwable $e) {echo '[HARNESS ERROR] '.$e->getMessage().' at line '.$e->getLine()."\n"; throw $e;} finally {server_stop();}
$pass=count(array_filter($GLOBALS['results'],fn($r)=>$r[2]));
echo 'RESULT '.count($GLOBALS['results']).' checks '.$pass.' PASS '.(count($GLOBALS['results'])-$pass)." FAIL\n";


