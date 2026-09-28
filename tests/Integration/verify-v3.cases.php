<?php
// Assertions originate from SPEC V3, BRIEF and README. Form fields/actions from probe HTML.
function rows3(string $html): array {
    if(trim($html)==="")return []; // empty body (e.g. production 403): no rows
    $d=new DOMDocument(); @$d->loadHTML($html); $xp=new DOMXPath($d); $out=[];
    foreach($xp->query('//tbody/tr') as $tr) {
        $cells=[]; foreach($tr->getElementsByTagName('td') as $td) $cells[]=trim(preg_replace('/\s+/',' ',$td->textContent));
        if(count($cells)===7) $out[]=$cells;
    }
    return $out;
}
function activity3(Client $c,array $filter=[]): array {
    $h=page($c,'rolewarden/activity'.($filter?'?'.http_build_query($filter):''));
    return rows3($h);
}
function nav3(string $h): bool {return (bool)preg_match('#<ul class="rw-nav">(?:(?!</ul>).)*/rolewarden/activity"#s',$h);}
function text3(string $h): string {return trim(preg_replace('/\s+/',' ',html_entity_decode(strip_tags($h),ENT_QUOTES|ENT_HTML5)));}
function action3(Client $c,string $url,string $button,array $values=[]): void {
    $f=matching(forms(page($c,$url)),$button); submit($c,$f,$values);
    if(in_array($c->status,[302,303],true)) follow($c);
}
function event3(string $id,Client $reader,string $action,int $actor,string $subject,callable $perform): ?array {
    $before=(int)q1('SELECT COALESCE(MAX(id),0) FROM acl_activity_log');
    $perform();
    $events=q('SELECT * FROM acl_activity_log WHERE id>? AND action=? ORDER BY id',[$before,$action]);
    check($id.'a','Event '.$action.' recorded once',count($events)===1,'new rows='.json_encode($events,JSON_UNESCAPED_UNICODE));
    if(count($events)!==1)return null;
    $e=$events[0];
    check($id.'b','Event has author, object, time and address',(int)$e['actor_id']===$actor && $e['actor_label']!=='' && str_contains($e['subject_label'],$subject) && abs(time()-strtotime($e['created_at']))<120 && $e['ip_address']!=='',json_encode($e,JSON_UNESCAPED_UNICODE));
    $filters=['action'=>$action,'from'=>substr($e['created_at'],0,10),'to'=>substr($e['created_at'],0,10),'user'=>$e['actor_label']];
    $rs=activity3($reader,$filters);
    $found=false;
    foreach($rs as $r)if(str_contains($r[2],$e['actor_label'])&&str_contains($r[4],$subject)&&$r[1]!=='')$found=true;
    check($id.'c','Combined user/action/inclusive date filters find '.$action,$reader->status===200&&$found,json_encode($rs,JSON_UNESCAPED_UNICODE));
    return $e;
}
function cleanlogs3(string $id): void {
    global $APP;
    $bad=[];
    foreach(glob("$APP/writable/logs/*.log") as $f)foreach(file($f) as $line)
        if(preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /',$line)&&!str_contains($line,'SecurityException')&&!str_contains($line,'The action you requested is not allowed'))$bad[]=trim($line);
    check($id,'Application logs at maximum threshold: no unexpected errors/warnings',$bad===[],implode("\n",$bad));
}
function matrix3(string $h): array {
    preg_match('/x-data="rw(?:User)?Matrix\((.*?)\)"/s',$h,$m);
    return json_decode(html_entity_decode($m[1]??'',ENT_QUOTES|ENT_HTML5),true)??[];
}

function run3(): void {
    global $APP,$MOD_NEW,$MOD_OLD,$SEEN;
    echo "== V3 upgrade and fresh install ==\n";
    reset_db();use_module($MOD_OLD);readme_install();
    $F=make_fixtures();$U=$F['u'];$pw=$F['pw'];
    $before=acl_state();$batch=(int)q1('SELECT MAX(batch) FROM migrations');
    server_start();$adm=login2('upgrade-admin',$U['admin2']['email'],$pw);
    check('U01','V2 baseline has no activity permission/menu',!in_array('activity.view',role_perms('admin'),true)&&!nav3(page($adm,'rolewarden/users')));
    server_stop();use_module($MOD_NEW);server_start();
    $h=page($adm,'rolewarden/users');
    check('U02','Folder replaced before migrate: signed-in panel remains usable',$adm->status===200&&!leaks_sql($h),'HTTP '.$adm->status);
    spark('migrate','-n','RoleWarden');
    check('U03','Upgrade grants activity.view to admin',in_array('activity.view',role_perms('admin'),true));
    check('U04','Upgraded admin sees Activity menu and list',nav3(page($adm,'rolewarden/users'))&&count(activity3($adm))>0&&$adm->status===200);
    server_stop();spark('migrate:rollback','-b',(string)$batch,'-f');
    check('U05','Rollback restores ACL/migrations exactly and removes log table',acl_state()===$before&&q("SHOW TABLES LIKE 'acl_activity_log'")===[]);
    use_module($MOD_OLD);server_start();
    check('U06','Restored V2 folder works after rollback',page($adm,'rolewarden/users')!==''&&$adm->status===200);
    server_stop();use_module($MOD_NEW);spark('migrate','-n','RoleWarden');
    check('U07','V3 migration applies again',in_array('activity.view',role_perms('admin'),true)&&count(q("SHOW TABLES LIKE 'acl_activity_log'"))===1);
    reset_db();readme_install();
    check('U08','Fresh install grants activity.view',in_array('activity.view',role_perms('admin'),true));
    $F=make_fixtures();$U=$F['u'];$pw=$F['pw'];
    server_start();$adm=login2('admin',$U['admin2']['email'],$pw);$aid=$U['admin2']['id'];
    $initial=defaults($adm);
    check('C01','Default retention is one year',$initial['activity_retention']==='365');
    settings($adm,['sign_in_rate'=>'100']);cache_clear();
    $lim=login2('limited',$U['limited']['email'],$pw);
    $h=page($lim,'rolewarden/users/'.$U['subject']['id']);
    check('P01','Without activity.view no menu or See activity link',!nav3($h)&&!str_contains($h,'See activity'));
    activity3($lim);check('P02','Direct activity GET without permission denied',$lim->status===403||in_array($lim->status,[302,303],true),'HTTP '.$lim->status);
    $anon=new Client('v3-anon');activity3($anon);check('P03','Anonymous activity GET denied',in_array($anon->status,[302,303,401,403],true));
    $h=page($adm,'rolewarden/users/'.$U['subject']['id']);
    check('P04','With activity.view See activity link present',str_contains($h,'See activity'));
    add_role('v3-auditor',['activity.view']);add_user('AuditorV3','auditor@v3.test','v3-auditor',$pw);
    $reader=login2('auditor','auditor@v3.test',$pw);activity3($reader);
    check('P05','activity.view alone allows the list',$reader->status===200);

    echo "== Panel events ==\n";
    $roleName='Ledger <b>R&D</b> "V3"';
    $created=event3('E01',$reader,'role.created',$aid,$roleName,function()use($adm,$roleName){
        $f=matching(forms(page($adm,'rolewarden/roles/create')),'Save');unset($f['fields']['is_super_admin']);
        submit($adm,$f,['slug'=>'v3-ledger','name'=>$roleName,'description'=>'Before & <tag>']);follow($adm);
    });
    $rid=(int)q1("SELECT id FROM acl_roles WHERE slug='v3-ledger'");
    $roleUrl='rolewarden/roles/'.$rid;
    event3('E02',$reader,'role.updated',$aid,'Ledger renamed',fn()=>action3($adm,$roleUrl.'/edit','Save',['name'=>'Ledger renamed','description'=>'After & <tag>']));
    event3('E03',$reader,'user.created',$aid,'V3subject',fn()=>action3($adm,'rolewarden/users/create','Save',['username'=>'V3subject','email'=>'subject_v3@v3.test','password'=>$pw]));
    $uid=(int)q1("SELECT id FROM users WHERE username='V3subject'");$url='rolewarden/users/'.$uid;
    event3('E04',$reader,'user.updated',$aid,'V3renamed',fn()=>action3($adm,$url.'/edit','Save',['username'=>'V3renamed']));
    $newPw='Z8!'.bin2hex(random_bytes(9)).'Ab';
    event3('E05',$reader,'user.password_set',$aid,'V3renamed',fn()=>action3($adm,$url.'/edit','Save',['password'=>$newPw]));
    event3('E06',$reader,'user.deactivated',$aid,'V3renamed',fn()=>action3($adm,$url,'Deactivate'));
    event3('E07',$reader,'user.activated',$aid,'V3renamed',fn()=>action3($adm,$url,'Activate'));
    event3('E08',$reader,'user.role_assigned',$aid,'V3renamed',fn()=>action3($adm,$url,'Add',['role_id'=>(string)$rid]));
    event3('E09',$reader,'user.role_revoked',$aid,'V3renamed',function()use($adm,$url,$rid){
        foreach(forms(page($adm,$url)) as $f)if(str_ends_with($f['action'],'/roles/'.$rid.'/revoke')){submit($adm,$f);follow($adm);return;}
        throw new RuntimeException('Role revocation form missing');
    });
    // Matrix request shapes are reused from the existing independent Integration suite.
    matrix_events3($adm,$reader,$aid,$rid,$uid);
    event3('E15',$reader,'settings.updated',$aid,'',fn()=>settings($adm,['lock_minutes'=>'16']));
    $subject=login2('subject','subject_v3@v3.test',$newPw);
    $second=login2('subject2','subject_v3@v3.test',$newPw,true);
    event3('E16',$reader,'session.revoked',$aid,'V3renamed',fn()=>action3($adm,$url,'Sign out'));
    $third=login2('subject3','subject_v3@v3.test',$newPw);
    event3('E17',$reader,'session.all_revoked',$aid,'V3renamed',fn()=>action3($adm,$url,'Sign out everywhere else'));
    $subject=login2('profile','subject_v3@v3.test',$newPw);
    $pw2='X9!'.bin2hex(random_bytes(10)).'Ab';
    event3('E18',$reader,'account.password_changed',$uid,'V3renamed',fn()=>action3($subject,'rolewarden/profile','Change password',['current_password'=>$newPw,'password'=>$pw2,'password_confirm'=>$pw2]));
    $prev=(int)q1('SELECT COALESCE(MAX(id),0) FROM acl_activity_log');
    $reg=register('V3registered','registered@v3.test',$pw);
    $regid=(int)q1("SELECT id FROM users WHERE username='V3registered'");
    $r=q('SELECT * FROM acl_activity_log WHERE id>? AND action=?',[$prev,'user.registered']);
    check('E19a','Shield registration writes a registration event',$regid>0&&count($r)===1,json_encode($r));
    $rs=activity3($reader,['user'=>'registered@v3.test','action'=>'user.registered','from'=>date('Y-m-d'),'to'=>date('Y-m-d')]);
    check('E19b','Registration found with combined filters',count($rs)===1,json_encode($rs));
    // Shield logout route already used by the permitted V2 client/fixture environment.
    event3('E20',$reader,'auth.logout',$uid,'V3renamed',fn()=>page($subject,'logout'));
    event3('E21',$reader,'user.deleted',$aid,'V3renamed',fn()=>action3($adm,$url,'Delete'));
    event3('E22',$reader,'role.deleted',$aid,'Ledger renamed',fn()=>action3($adm,$roleUrl,'Delete'));
    if($created){
        $rs=activity3($reader,['action'=>'role.created','user'=>$U['admin2']['username']]);
        check('S01','Role creation retains old object label after rename/delete',count(array_filter($rs,fn($r)=>str_contains($r[4],$roleName)))===1,json_encode($rs,JSON_UNESCAPED_SLASHES));
        check('S02','Object markup escaped in activity HTML',!str_contains($reader->body,$roleName)&&str_contains($reader->body,'&lt;b&gt;'));
    }
    $rs=activity3($reader,['user'=>'subject_v3@v3.test','action'=>'user.created']);
    check('S03','Deleted subject searchable by original email',count($rs)===1,json_encode($rs));
    $rs=activity3($reader,['user'=>'V3subject','action'=>'user.created']);
    check('S04','Deleted subject searchable by original name',count($rs)===1,json_encode($rs));
    access_filters3($adm,$reader,$U,$pw);
    history3($adm,$reader,$U,$pw);
    retention3($adm,$reader);
    publication3($adm,$reader,$U,$pw);
    server_stop();
    cleanlogs3('Z01');
    $bad=[];foreach($SEEN as [$u,$s,$h])if(leaks_sql($h)||preg_match('/PHP (Warning|Notice|Fatal)|ErrorException|Uncaught /',clean_html($h)))$bad[]=$u.' '.$s;
    check('Z02','Captured HTTP responses do not expose SQL/PHP errors',$bad===[],implode(';',$bad));
    $bad=[];foreach(glob(__DIR__.'/verify-v3.*server.log') as $f)if(preg_match('/PHP (Warning|Notice|Deprecated|Fatal)/',file_get_contents($f)))$bad[]=basename($f);
    check('Z03','PHP server logs have no warnings/errors',$bad===[],implode(',',$bad));
}

function matrix_post3(Client $c,string $url,string $slug,?int $granted): void {
    $cfg=matrix3(page($c,$url));$pid=null;
    foreach($cfg['rows']??[] as $row)foreach($row['cells'] as $cell)if($row['area'].'.'.$cell['action']===$slug)$pid=$cell['permissionId'];
    if(!$pid||empty($cfg['saveUrl']))throw new RuntimeException('Matrix target not exposed: '.$slug);
    $values=['permission_id'=>$pid,$cfg['csrfName']=>$cfg['csrfHash']];if($granted!==null)$values['granted']=$granted;
    $c->req('POST',$cfg['saveUrl'],$values,['X-Requested-With: XMLHttpRequest','Accept: application/json']);
    $GLOBALS['SEEN'][]=[$cfg['saveUrl'],$c->status,$c->body];
    echo '[MATRIX] '.json_encode(['url'=>$cfg['saveUrl'],'status'=>$c->status,'response'=>json_decode($c->body,true)])."\n";
}
function matrix_events3(Client $adm,Client $reader,int $aid,int $rid,int $uid): void {
    $rurl='rolewarden/roles/'.$rid;$uurl='rolewarden/users/'.$uid;
    event3('E10',$reader,'role.permission_granted',$aid,'Ledger renamed',fn()=>matrix_post3($adm,$rurl,'users.view',1));
    event3('E11',$reader,'role.permission_revoked',$aid,'Ledger renamed',fn()=>matrix_post3($adm,$rurl,'users.view',0));
    event3('E12',$reader,'user.override_granted',$aid,'V3renamed',fn()=>matrix_post3($adm,$uurl,'users.view',1));
    // A denial is meaningful over a role grant; assign the fixture role with users.view.
    $limited=(int)q1("SELECT id FROM acl_roles WHERE slug='v1-limited'");
    action3($adm,$uurl,'Add',['role_id'=>$limited]);
    event3('E13',$reader,'user.override_denied',$aid,'V3renamed',fn()=>matrix_post3($adm,$uurl,'users.view',0));
    event3('E14',$reader,'user.override_cleared',$aid,'V3renamed',fn()=>matrix_post3($adm,$uurl,'users.view',null));
}
function access_filters3(Client $adm,Client $reader,array $U,string $pw): void {
    echo "== Accesses and filters ==\n";cache_clear();
    $before=q('SELECT * FROM acl_activity_log ORDER BY id');
    $beforeId=(int)q1('SELECT COALESCE(MAX(id),0) FROM auth_logins');
    $known=$U['subject']['email'];$unknown='unregistered@v3.test';
    login2('access-ok',$known,$pw);login2('access-bad',$known,'wrong');login2('access-unknown',$unknown,'wrong');
    $new=q('SELECT * FROM auth_logins WHERE id>? ORDER BY id',[$beforeId]);
    check('L01','Shield records one success and two failures',count($new)===3&&array_map('intval',array_column($new,'success'))===[1,0,0],json_encode($new));
    check('L02','Sign-ins do not create copies in module log',q('SELECT * FROM acl_activity_log ORDER BY id')===$before);
    foreach([['L03',$known,'auth.login'],['L04',$known,'auth.failed'],['L05',$unknown,'auth.failed']] as [$id,$mail,$act]) {
        $rs=activity3($reader,['user'=>$mail,'action'=>$act,'from'=>date('Y-m-d'),'to'=>date('Y-m-d')]);
        check($id,'Sign-in retrievable with all filters: '.$mail.' '.$act,count($rs)>=1&&count(array_filter($rs,fn($r)=>str_contains($r[2],$mail)))===count($rs),json_encode($rs));
    }
    $rs=activity3($reader,['user'=>$U['subject']['username'],'action'=>'auth.login']);
    check('L06','Known sign-in searchable by username',count($rs)>=1,json_encode($rs));
    add_user('Literal%_V3','literal%_v3@v3.test','admin',$pw);cache_clear();
    $special=login2('literal','literal%_v3@v3.test',$pw);
    $f=matching(forms(page($special,'rolewarden/roles/create')),'Save');unset($f['fields']['is_super_admin']);
    submit($special,$f,['slug'=>'literal-v3','name'=>'Literal object']);follow($special);
    foreach(['%','_','Literal%_V3','literal%_v3@v3.test'] as $query) {
        $rs=activity3($reader,['user'=>$query,'action'=>'role.created']);
        check('F01-'.$query,'User search treats literal characters as text: '.$query,count($rs)===1&&str_contains($rs[0][2],'Literal%_V3'),json_encode($rs));
    }
    $rs=activity3($reader,['user'=>'no-such-v3-filter']);
    check('F02','Empty state has explanatory text and no event rows',$rs===[]&&preg_match('/No .*match|No activity|No entries/i',$reader->body),text3($reader->body));
    // Controlled fixtures clone a real event, changing only labels/time for exact boundary and paging checks.
    $seed=q("SELECT * FROM acl_activity_log WHERE action='role.created' ORDER BY id LIMIT 1")[0];
    $day=date('Y-m-d',strtotime('-2 days'));$prev=date('Y-m-d',strtotime('-3 days'));$next=date('Y-m-d',strtotime('-1 day'));
    foreach([$prev.' 23:59:59',$day.' 00:00:00',$day.' 23:59:59',$next.' 00:00:00'] as $i=>$at)
        fixture_event3($seed,'BoundaryV3','boundary-'.$i,$at);
    $rs=activity3($reader,['user'=>'BoundaryV3','action'=>'role.created','from'=>$day,'to'=>$day]);
    check('F03','Date endpoints include midnight and 23:59:59, exclude adjacent days',count($rs)===2&&str_contains(json_encode($rs),'boundary-1')&&str_contains(json_encode($rs),'boundary-2'),json_encode($rs));
    for($i=0;$i<61;$i++)fixture_event3($seed,'PagingV3','page-item-'.$i,$day.' 12:'.sprintf('%02d',intdiv($i,60)).':'.sprintf('%02d',$i%60));
    $filters=['user'=>'PagingV3','action'=>'role.created','from'=>$day,'to'=>$day];
    $first=activity3($reader,$filters);$h=$reader->body;
    $d=new DOMDocument();@$d->loadHTML($h);$links=[];
    foreach($d->getElementsByTagName('a') as $a){$href=$a->getAttribute('href');parse_str(parse_url($href,PHP_URL_QUERY)??'',$query);if(isset($query['page'])&&(int)$query['page']>1)$links[$href]=$query;}
    check('F04','Pagination exposes a later page',count($links)>0&&count($first)>0&&count($first)<61,'rows='.count($first).' links='.json_encode($links));
    $ok=true;foreach($links as $query)foreach($filters as $k=>$v)if(($query[$k]??null)!==$v)$ok=false;
    check('F05','Every pagination link preserves all filters',count($links)>0&&$ok);
    if($links){$url=array_key_first($links);$second=rows3(page($reader,$url));
        check('F06','Following next page returns distinct matching events',count($second)>0&&array_intersect(array_column($first,4),array_column($second,4))===[]&&count(array_filter($second,fn($r)=>str_contains($r[2],'PagingV3')))===count($second),json_encode($second));}
    echo '[OBSERVATION] Permission catalogue forms: '.json_encode(forms(page($adm,'rolewarden/permissions')))."\n";
}
function fixture_event3(array $seed,string $actor,string $subject,string $at): int {
    q('INSERT INTO acl_activity_log(actor_id,actor_label,action,subject_type,subject_id,subject_label,details,ip_address,created_at) VALUES (?,?,?,?,?,?,?,?,?)',[$seed['actor_id'],$actor,$seed['action'],$seed['subject_type'],$seed['subject_id'],$subject,$seed['details'],$seed['ip_address'],$at]);
    return (int)db()->insert_id;
}
function history3(Client $adm,Client $reader,array $U,string $pw): void {
    echo "== Author history ==\n";
    $uid=add_user('OldAuthorV3','oldauthor@v3.test','admin',$pw);cache_clear();
    $old=login2('history','oldauthor@v3.test',$pw);
    $f=matching(forms(page($old,'rolewarden/roles/create')),'Save');unset($f['fields']['is_super_admin']);
    submit($old,$f,['slug'=>'author-history-v3','name'=>'History object']);follow($old);
    $snapshot=q('SELECT * FROM acl_activity_log WHERE actor_id=? ORDER BY id',[$uid]);
    action3($adm,'rolewarden/users/'.$uid.'/edit','Save',['username'=>'NewAuthorV3','email'=>'newauthor@v3.test']);
    $rs=activity3($reader,['user'=>'OldAuthorV3','action'=>'role.created']);
    check('S05','Actor snapshot remains readable after rename',count($rs)===1&&str_contains($rs[0][2],'OldAuthorV3'),json_encode($rs));
    action3($adm,'rolewarden/users/'.$uid,'Delete');
    foreach(['OldAuthorV3','newauthor@v3.test'] as $query){
        $rs=activity3($reader,['user'=>$query,'action'=>'role.created']);
        check('S06-'.$query,'Deleted actor searchable by historical name/email',count($rs)===1&&str_contains($rs[0][2],'OldAuthorV3'),json_encode($rs));
    }
    $rs=activity3($reader,['user'=>'oldauthor@v3.test','action'=>'role.created']);
    echo '[AMBIGUITY A1] Former email after email change and panel deletion: '.json_encode($rs)."\n";
    // Physical deletion additionally checks the documented ON DELETE SET NULL relationship.
    q('DELETE FROM users WHERE id=?',[$uid]);
    $event=q('SELECT * FROM acl_activity_log WHERE id=?',[$snapshot[0]['id']??0]);
    check('S07','Physical deletion preserves event and nulls actor_id',count($event)===1&&$event[0]['actor_id']===null&&$event[0]['actor_label']===$snapshot[0]['actor_label'],json_encode($event));
    $rs=activity3($reader,['user'=>'OldAuthorV3','action'=>'role.created']);
    check('S08','Historic author name remains searchable after physical deletion',count($rs)===1,json_encode($rs));
    $rs=activity3($reader,['user'=>'newauthor@v3.test','action'=>'role.created']);
    echo '[AMBIGUITY A1] Last email after physical deletion: '.json_encode($rs)."\n";
}
function retention3(Client $adm,Client $reader): void {
    echo "== Retention ==\n";
    $f=matching(forms(page($adm,'rolewarden/settings')),'Save settings');
    preg_match('/<select[^>]+name="activity_retention"[^>]*>(.*?)<\/select>/s',$f['html'],$m);
    preg_match_all('/<option value="([^"]*)"/',$m[1]??'',$opts);
    check('C02','Exactly 90 days, one year and forever offered',($opts[1]??[])===['90','365','0']);
    foreach(['89','366','-1','bad','90.5',"' OR 1=1 --"] as $v){$prior=stored();$h=settings($adm,['activity_retention'=>$v]);
        check('C03-'.$v,'Invalid retention refused below control without changing settings',stored()===$prior&&field_error($h,'activity_retention')&&no_toast($h));}
    $seed=q("SELECT * FROM acl_activity_log WHERE action='role.created' ORDER BY id LIMIT 1")[0];
    foreach(['90','365','0'] as $v){
        settings($adm,['activity_retention'=>$v]);check('C04-'.$v,'Retention option roundtrips',defaults($adm)['activity_retention']===$v);
        $ids=[];foreach([89,91,364,366,10000] as $age)$ids[$age]=fixture_event3($seed,'RetentionV3','retention-'.$v.'-'.$age,date('Y-m-d H:i:s',time()-86400*$age));
        $auth=q('SELECT * FROM auth_logins ORDER BY id');
        // The settings row name/value was observed via SQL in the isolated DB (edges run), not in source.
        // Age the recorded last-cleanup date to obtain a real first-write opportunity without changing host time.
        q('UPDATE settings SET value=? WHERE `key`=?',[date('Y-m-d',strtotime('-1 day')),'activityPrunedOn']);
        cache_clear();
        activity3($reader,['user'=>'RetentionV3']);
        check('C07-'.$v,'Reading the log alone does not purge aged entries',(int)q1('SELECT COUNT(*) FROM acl_activity_log WHERE id=?',[$ids[10000]])===1);
        settings($adm,['lock_minutes'=>$v==='365'?'18':'17']);
        $actual=[];foreach($ids as $age=>$id)$actual[$age]=(int)q1('SELECT COUNT(*) FROM acl_activity_log WHERE id=?',[$id]);
        $expected=[];foreach($ids as $age=>$id)$expected[$age]=($v==='0'||$age<(int)$v)?1:0;
        check('C05-'.$v,'First write purges expired module rows only',$actual===$expected,'actual='.json_encode($actual).' expected='.json_encode($expected));
        check('C06-'.$v,'Retention never changes auth_logins',q('SELECT * FROM auth_logins ORDER BY id')===$auth);
        $later=fixture_event3($seed,'RetentionV3','second-write-'.$v,date('Y-m-d H:i:s',time()-86400*10000));
        settings($adm,['lock_minutes'=>'21']);
        check('C08-'.$v,'Second write the same day does not run cleanup again',(int)q1('SELECT COUNT(*) FROM acl_activity_log WHERE id=?',[$later])===1);
    }
}
function publication3(Client $adm,Client $reader,array $U,string $pw): void {
    global $APP;
    echo "== Publication in development and production ==\n";
    foreach(['development','production'] as $env){
        server_stop();file_put_contents("$APP/.env",preg_replace('/CI_ENVIRONMENT = \w+/','CI_ENVIRONMENT = '.$env,file_get_contents("$APP/.env")));cache_clear();server_start();
        foreach([['user'=>"<script>alert('V3')</script>"],['action'=>"' OR 1=1 --"],['from'=>'bad','to'=>'bad'],['from'=>'2026-09-28','to'=>'2026-01-01'],['page'=>'-1'],['user'=>['_'=>1]],['action'=>['x']],['from'=>['x']]] as $i=>$filter){
            activity3($reader,$filter);
            check('Z10-'.$env.'-'.$i,'Malformed/hostile activity filter has no SQL/PHP error',$reader->status<500&&!leaks_sql($reader->body),'HTTP '.$reader->status);
            if($i===0)check('Z11-'.$env,'Search text is escaped',!str_contains($reader->body,$filter['user']));
        }
        $f=matching(forms(page($adm,'rolewarden/settings')),'Save settings');$before=stored();$count=q1('SELECT COUNT(*) FROM acl_activity_log');
        unset($f['fields']['csrf_test_name']);submit($adm,$f,['activity_retention'=>'90']);
        $status=$adm->status;$h=in_array($status,[302,303],true)?follow($adm):$adm->body;
        check('Z12-'.$env,'Missing CSRF cannot change retention or record success',stored()===$before&&q1('SELECT COUNT(*) FROM acl_activity_log')===$count&&($status===403||str_contains($h,'The action you requested is not allowed')),'HTTP '.$status);
        // Hostile stored author label is captured by a real HTTP write; no direct log insertion here.
        $payload='<svg onload="alert(3)"> & "V3"';$orig=q1('SELECT username FROM users WHERE id=?',[$U['admin2']['id']]);
        q('UPDATE users SET username=? WHERE id=?',[$payload,$U['admin2']['id']]);
        settings($adm,['lock_minutes'=>$env==='development'?'19':'20']);
        activity3($reader,['action'=>'settings.updated','user'=>$payload]);
        check('Z13-'.$env,'Stored author markup escaped in activity',$reader->status===200&&!str_contains($reader->body,$payload)&&str_contains($reader->body,'&lt;svg'));
        q('UPDATE users SET username=? WHERE id=?',[$orig,$U['admin2']['id']]);
    }
}

function edges3(): void {
    global $MOD_NEW;
    reset_db();use_module($MOD_NEW);readme_install();$F=make_fixtures();$U=$F['u'];$pw=$F['pw'];
    server_start();$adm=login2('edges-admin',$U['admin2']['email'],$pw);$aid=$U['admin2']['id'];
    settings($adm,['sign_in_rate'=>'100']);
    echo '[STORAGE] '.stored()."\n";
    $uid=add_user('DeletedActorV3','deletedactor@v3.test','admin',$pw);cache_clear();
    $actor=login2('edges-actor','deletedactor@v3.test',$pw);
    $f=matching(forms(page($actor,'rolewarden/roles/create')),'Save');unset($f['fields']['is_super_admin']);
    submit($actor,$f,['slug'=>'deleted-actor-role','name'=>'Deleted actor object']);follow($actor);
    $r=activity3($adm,['user'=>'deletedactor@v3.test','action'=>'role.created']);
    check('D1a','Actor email finds event before deletion, without any rename',count($r)===1,json_encode($r));
    action3($adm,'rolewarden/users/'.$uid,'Delete');
    $r=activity3($adm,['user'=>'deletedactor@v3.test','action'=>'role.created']);
    check('D1b','Unchanged actor email finds event after panel deletion',count($r)===1,json_encode($r));
    $r=activity3($adm,['user'=>'DeletedActorV3','action'=>'role.created']);
    check('D1c','Actor name still finds the same event after deletion',count($r)===1,json_encode($r));
    $r=activity3($adm,['user'=>'DeletedActorV3','action'=>'auth.login']);
    check('D1d','Deleted actor login remains searchable by username',count($r)===1,json_encode($r));
    echo '[HISTORY] '.json_encode(q("SELECT actor_id,actor_label,action,subject_label,details FROM acl_activity_log WHERE action='role.created'"))."\n";
    echo '[STORAGE] '.stored()."\n";
    $uid=$U['subject']['id'];$url='rolewarden/users/'.$uid;
    add_user('Percent%_Login','percent%_login@v3.test','user',$pw);cache_clear();
    login2('edge-literal-login','percent%_login@v3.test',$pw);
    foreach(['%','_'] as $literal){
        $r=activity3($adm,['user'=>$literal,'action'=>'auth.login']);
        check('LF-'.$literal,'Shield login search escapes literal '.$literal,count($r)===1&&str_contains($r[0][2],'percent%_login@v3.test'),json_encode($r));
    }
    $day=date('Y-m-d',strtotime('-2 days'));$prev=date('Y-m-d',strtotime('-3 days'));$next=date('Y-m-d',strtotime('-1 day'));
    foreach([$prev.' 23:59:59',$day.' 00:00:00',$day.' 23:59:59',$next.' 00:00:00'] as $at)
        q('INSERT INTO auth_logins(ip_address,user_agent,id_type,identifier,user_id,date,success) VALUES (?,?,?,?,?,?,1)',['127.0.0.1','fixture','email_password','boundarylogin@v3.test',$uid,$at]);
    $filters=['user'=>'boundarylogin@v3.test','from'=>$day,'to'=>$day];
    $r=activity3($adm,$filters+['action'=>'auth.login']);
    check('LF-dates','Shield date endpoints include both whole-day boundaries',count($r)===2&&str_contains($r[0][1],'23:59')&&str_contains($r[1][1],'00:00'),json_encode($r));
    $seed=q("SELECT * FROM acl_activity_log WHERE action='role.created' ORDER BY id LIMIT 1")[0];
    fixture_event3($seed,'boundarylogin@v3.test','Mixed source object',$day.' 12:00:00');
    $r=activity3($adm,$filters);
    check('LF-merge','Merged sources ordered newest first',count($r)===3&&str_contains($r[0][1],'23:59')&&str_contains($r[1][4],'Mixed source object')&&str_contains($r[2][1],'00:00'),json_encode($r));
    $r=activity3($adm,['user'=>'boundarylogin@v3.test']);
    check('LF-user','User-only filter covers both sources',count($r)===5,json_encode($r));
    $r=activity3($adm,['from'=>$day,'to'=>$day]);
    check('LF-period','Period-only filter covers both sources',count($r)===3,json_encode($r));
    $r=activity3($adm,['action'=>'role.created']);
    check('LF-action','Action-only filter excludes sign-ins',count($r)===2&&count(array_filter($r,fn($row)=>$row[3]==='Role created'))===2,json_encode($r));
    $h=page($adm,$url);preg_match('#href="([^"]*)"[^>]*>See activity</a>#',$h,$link);
    $r=isset($link[1])?rows3(page($adm,html_entity_decode($link[1],ENT_QUOTES|ENT_HTML5))):[];
    check('LF-link','See activity link opens a populated user-filtered list',isset($link[1])&&$adm->status===200&&count($r)>0);
    $subject=login2('edge-own',$U['subject']['email'],$pw);
    $other=login2('edge-other',$U['subject']['email'],$pw);
    event3('E23',$adm,'session.revoked',$uid,$U['subject']['username'],fn()=>action3($subject,'rolewarden/sessions','Sign out'));
    $other=login2('edge-other2',$U['subject']['email'],$pw,true);
    event3('E24',$adm,'session.all_revoked',$uid,$U['subject']['username'],fn()=>action3($subject,'rolewarden/sessions','Sign out everywhere else'));
    foreach(['own','admin'] as $who){
        $orphan=login2('edge-orphan-'.$who,$U['subject']['email'],$pw,true);
        q('DELETE FROM acl_sessions WHERE user_id=? AND remember_selector IS NOT NULL',[$uid]);
        $c=$who==='own'?$subject:$adm;$target=$who==='own'?'rolewarden/sessions':$url;
        event3('E25-'.$who,$adm,'session.remembered_forgotten',$who==='own'?$uid:$aid,$U['subject']['username'],fn()=>action3($c,$target,'Forget'));
    }
    $role=(int)q1("SELECT id FROM acl_roles WHERE slug='deleted-actor-role'");
    $event=event3('E26',$adm,'role.updated',$aid,'Edge renamed object',fn()=>action3($adm,'rolewarden/roles/'.$role.'/edit','Save',['name'=>'Edge renamed object']));
    $details=$event?json_decode($event['details'],true):null;
    check('DETAILS-role','Role edit JSON records both old and new values',is_array($details)&&str_contains(json_encode($details),'Deleted actor object')&&str_contains(json_encode($details),'Edge renamed object'),json_encode($details));
    $newPw='T9!'.bin2hex(random_bytes(10)).'Ab';
    $event=event3('E27',$adm,'user.password_set',$aid,$U['subject']['username'],fn()=>action3($adm,$url.'/edit','Save',['password'=>$newPw]));
    $secrets=q('SELECT details FROM acl_activity_log');
    $hash=q1("SELECT secret2 FROM auth_identities WHERE user_id=? AND type='email_password'",[$uid]);
    check('DETAILS-secret','Activity details contain neither password nor its authentication hash',!str_contains(json_encode($secrets),$newPw)&&!str_contains(json_encode($secrets),$hash));
    foreach(['rolewarden/users/create',$url.'/edit',$url,'rolewarden/roles/create','rolewarden/roles/'.$role.'/edit','rolewarden/roles/'.$role] as $url2){
        foreach(forms(page($adm,$url2)) as $i=>$form){
            $before=(int)q1('SELECT COUNT(*) FROM acl_activity_log');$acl=acl_state();
            check('CSRF-'.$url2.'-'.$i,'Panel mutation form exposes CSRF',!empty($form['fields']['csrf_test_name']));
            unset($form['fields']['csrf_test_name']);submit($adm,$form);
            $status=$adm->status;
            $refused=$status===403||(in_array($status,[302,303],true)&&str_contains(follow($adm),'The action you requested is not allowed'));
            check('CSRF-reject-'.$url2.'-'.$i,'Missing CSRF cannot mutate ACL or create successful log entry',$refused&&$before===(int)q1('SELECT COUNT(*) FROM acl_activity_log')&&acl_state()===$acl,'HTTP '.$status);
        }
    }
    foreach(['rolewarden/roles/'.$role,'rolewarden/users/'.$uid] as $url2){
        $cfg=matrix3(page($adm,$url2));$before=(int)q1('SELECT COUNT(*) FROM acl_activity_log');$acl=acl_state();
        $adm->req('POST',$cfg['saveUrl'],['permission_id'=>1,'granted'=>1],['Accept: application/json','X-Requested-With: XMLHttpRequest']);
        check('CSRF-matrix-'.$url2,'AJAX matrix missing CSRF refused without successful event',$adm->status===403&&$before===(int)q1('SELECT COUNT(*) FROM acl_activity_log')&&acl_state()===$acl);
    }
    cleanlogs3('EDGE-LOG');
    server_stop();
}

function arrays3(): void {
    global $APP,$MOD_NEW;
    reset_db();use_module($MOD_NEW);readme_install();
    $F=make_fixtures();server_start();
    $adm=login2('arrays-admin',$F['u']['admin2']['email'],$F['pw']);
    foreach(['activity'=>['user','action','from','to','page'],'users'=>['q','role','status','sort','page']] as $route=>$keys){
        page($adm,'rolewarden/'.$route);
        check('ARRAY-baseline-'.$route,'Authenticated baseline responds 200',$adm->status===200);
        $queries=[];
        foreach($keys as $key)foreach(['[]=x','[0]=x','[_]=1','[nested][0]=x'] as $shape)$queries[]=$key.$shape;
        $queries[]=implode('&',array_map(fn($key)=>$key.'[]=x',$keys));
        $queries[]=$keys[0].'[]=x&page[]=2&'.$keys[1].'=invalid';
        $queries[]=$keys[0].'=scalar&'.$keys[0].'[]=x';
        $queries[]=$keys[0].'[]=x&'.$keys[0].'=scalar';
        foreach($queries as $i=>$query){
            $url='rolewarden/'.$route.'?'.$query;$h=page($adm,$url);
            echo '[ARRAY URL] /index.php/'.$url.' HTTP '.$adm->status."\n";
            check('ARRAY-'.$route.'-'.$i,'Array filter handled without server error or PHP/SQL diagnostics',
                $adm->status>=200&&$adm->status<500&&!leaks_sql($h)&&!preg_match('/PHP (Warning|Notice|Deprecated|Fatal)|ErrorException|Uncaught |Array to string conversion/',clean_html($h)),
                $url.' HTTP '.$adm->status);
        }
    }
    // Scalar filters must keep working after the array hardening (added by Collaudatore ad Hoc).
    $h=page($adm,'rolewarden/users?q=priya');
    check('SCALAR-users-q','Scalar q still filters the user list',$adm->status===200&&str_contains($h,'priya@northwind-studio.test')&&!str_contains($h,'mara@northwind-studio.test'));
    $r=activity3($adm,['action'=>'auth.login','user'=>'priya']);
    check('SCALAR-activity','Scalar action+user still filter the activity list',$adm->status===200&&count($r)>=1&&count(array_filter($r,fn($row)=>stripos(implode(' ',$row),'priya')!==false))===count($r),json_encode($r));
    $r=activity3($adm,['user'=>'no-such-user-xyz']);
    check('SCALAR-activity-empty','Scalar user with no match yields empty list',$adm->status===200&&count($r)===0,json_encode($r));
    server_stop();
    $bad=[];
    foreach(glob("$APP/writable/logs/*.log") as $f)foreach(file($f) as $line)
        if(preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /',$line))$bad[]=trim($line);
    check('ARRAY-app-log','Threshold 9: no errors or warnings, without exclusions',$bad===[],implode("\n",$bad));
    $bad=[];
    foreach(glob(__DIR__.'/verify-v3.*server.log') as $f)
        if(preg_match('/PHP (Warning|Notice|Deprecated|Fatal)|Uncaught /',file_get_contents($f)))$bad[]=basename($f);
    check('ARRAY-server-log','PHP server log has no errors or warnings',$bad===[],implode(',',$bad));
}
