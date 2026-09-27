<?php
// Included by verify-v2.php extra: targeted reproduction and remaining negative paths.
// Codex original; the Collaudatore ad Hoc added X06t and X07t (toasts on refused actions).
try {
reset_db();use_module($MOD_NEW);readme_install();
$F=make_fixtures();$U=$F['u'];$pw=$F['pw'];$uid=$U['subject']['id'];$email=$U['subject']['email'];
add_role('v2-extra-view',['users.view','sessions.view']);add_user('v2extraview','extraview@v2.test','v2-extra-view',$pw);
foreach(['development','production'] as $environment){
    $env=file_get_contents("$APP/.env");file_put_contents("$APP/.env",preg_replace('/CI_ENVIRONMENT = \w+/','CI_ENVIRONMENT = '.$environment,$env));
    cache_clear();server_start();
    $adm=login2('extra-admin-'.$environment,$U['admin2']['email'],$pw);
    settings($adm,['sign_in_rate'=>'100','session_lifetime'=>'1800']);cache_clear();
    $b=login2('extra-victim-'.$environment,$email,$pw,true);
    q('UPDATE acl_sessions SET last_seen_at=DATE_SUB(NOW(),INTERVAL 31 MINUTE) WHERE user_id=?',[$uid]);
    $old=session_cookie($b);
    check('X01-'.$environment,'Remembered browser re-enters after configured 30 minute expiry',signed($b)&&session_cookie($b)!==$old);
    echo '[EVIDENCE] after auto-entry sessions='.sessions($uid).' tokens='.tokens($uid)."\n";
    $detail='rolewarden/users/'.$uid;
    $f=matching(forms(page($adm,$detail)),'Sign out everywhere else');submit($adm,$f);
    echo '[EVIDENCE] admin revocation HTTP '.$adm->status.' sessions='.sessions($uid).' tokens='.tokens($uid)."\n";
    check('X02-'.$environment,'Admin total revocation denies first request after automatic re-entry',!signed($b),'HTTP '.$b->status.' sessions='.sessions($uid).' tokens='.tokens($uid));
    check('X03-'.$environment,'Revoked re-entry remains denied on second request',!signed($b),'HTTP '.$b->status);

    // Target sessions + orphan remember token expose all three admin POST actions in HTML.
    $live=login2('extra-live-'.$environment,$email,$pw);
    $orphan=login2('extra-orphan-'.$environment,$email,$pw,true);
    q('DELETE FROM acl_sessions WHERE user_id=? AND remember_selector IS NOT NULL',[$uid]);no_session_cookie($orphan);
    $targetForms=array_values(array_filter(forms(page($adm,$detail)),fn($f)=>str_contains($f['action'],'sessions')||str_contains($f['action'],'remembered')));
    $viewer=login2('extra-viewer-'.$environment,'extraview@v2.test',$pw);
    $limited=login2('extra-limited-'.$environment,$U['limited']['email'],$pw);
    foreach(['view-only'=>$viewer,'no-session-perms'=>$limited] as $label=>$actor)foreach($targetForms as $i=>$f){
        $state=[sessions($uid),tokens($uid)];$f['fields']['csrf_test_name']=csrf_of(page($actor,'rolewarden/profile'));submit($actor,$f);
        check('X04-'.$environment.'-'.$label.'-'.$i,'All admin session POSTs require sessions.revoke',!csrf_refused($actor)&&[sessions($uid),tokens($uid)]===$state && $actor->status!==200,$f['action'].' HTTP '.$actor->status);
    }
    // Own single-session, remembered and total forms, plus profile and settings: reject absent CSRF.
    $other=login2('extra-own-other-'.$environment,$U['admin2']['email'],$pw);
    $ownOrphan=login2('extra-own-orphan-'.$environment,$U['admin2']['email'],$pw,true);
    q('DELETE FROM acl_sessions WHERE user_id=? AND remember_selector IS NOT NULL',[$U['admin2']['id']]);
    foreach(['rolewarden/sessions','rolewarden/profile','rolewarden/settings',$detail] as $url){
        $fs=forms(page($adm,$url));foreach($fs as $i=>$f){
            if(!preg_match('#/(sessions|remembered|profile|settings)(/|$)#',$f['action']))continue;
            $state=[q1('SELECT COUNT(*) FROM acl_sessions'),q1('SELECT COUNT(*) FROM auth_remember_tokens'),stored()];
            check('X05-'.$environment.'-'.$i,'Served new form includes CSRF',!empty($f['fields']['csrf_test_name']),$f['action']);
            unset($f['fields']['csrf_test_name']);submit($adm,$f);
            $status=$adm->status;$msg=$status===403?$adm->body:follow($adm);
            $same=$state===[q1('SELECT COUNT(*) FROM acl_sessions'),q1('SELECT COUNT(*) FROM auth_remember_tokens'),stored()];
            check('X06-'.$environment.'-'.$i,'Absent CSRF rejected without session/token/settings mutation',$same&&($status===403||str_contains($msg,'The action you requested is not allowed')),$f['action'].' HTTP '.$status);
            // Collaudatore: a redirected refusal must reach the user as an error toast (point 9).
            if($status!==403) check('X06t-'.$environment.'-'.$i,'Redirected CSRF refusal shown as an error toast',toast_ok(static_toasts($msg),'error')&&count(js_toasts($msg))>=1&&js_toasts($msg)[0]['type']==='error',$f['action'].' '.json_encode(static_toasts($msg)));
        }
    }
    // Ownership and nonexistent IDs use the endpoint format observed in the HTML.
    $spare=login2('extra-spare-'.$environment,$email,$pw);
    $f=matching(forms(page($live,'rolewarden/sessions')),'Sign out');
    if(!preg_match('#/sessions/\d+/revoke$#',$f['action']))throw new RuntimeException('Expected a served single-session form');
    $adminSession=(string)q1('SELECT id FROM acl_sessions WHERE user_id=? ORDER BY id LIMIT 1',[$U['admin2']['id']]);
    foreach([$adminSession,'999999999'] as $id){
        $f['action']=preg_replace('#/sessions/\d+/revoke$#','/sessions/'.$id.'/revoke',$f['action']);
        $f['fields']['csrf_test_name']=csrf_of(page($live,'rolewarden/profile'));
        $state=q('SELECT id,user_id FROM acl_sessions ORDER BY id');submit($live,$f);
        check('X07-'.$environment.'-'.$id,'Own session endpoint refuses foreign/nonexistent session without SQL leak',!csrf_refused($live)&&q('SELECT id,user_id FROM acl_sessions ORDER BY id')===$state&&!leaks_sql($live->body), 'HTTP '.$live->status);
        $xs=$live->status;$xh=in_array($xs,[302,303],true)?follow($live):$live->body;
        echo '[INFO] X07 '.$environment.' id='.$id.' HTTP '.$xs.' static toasts='.json_encode(static_toasts($xh))."\n";
        check('X07t-'.$environment.'-'.$id,'Refused foreign/nonexistent session produces an error toast',toast_ok(static_toasts($xh),'error'),'HTTP '.$xs.' '.json_encode(static_toasts($xh)));
    }
    server_stop();
}
$bad=[];foreach($SEEN as [$url,$status,$body])if(leaks_sql($body)||preg_match('/PHP (Warning|Notice|Fatal)|ErrorException|Uncaught /',clean_html($body)))$bad[]=$url.' '.$status;
check('X08','Extra responses contain no SQL/PHP errors',$bad===[],implode(';',$bad));
$bad=[];foreach(glob("$APP/writable/logs/*.log") as $f)foreach(file($f) as $line)if(preg_match('/^(WARNING|NOTICE|DEPRECATED|ERROR|CRITICAL|ALERT|EMERGENCY) /',$line)&&!str_contains($line,'SecurityException')&&!str_contains($line,'The action you requested is not allowed'))$bad[]=trim($line);
check('X09','Extra max-level logs clean except CSRF refusals',$bad===[],implode("\n",$bad));
}finally{server_stop();}
$pass=count(array_filter($GLOBALS['results'],fn($r)=>$r[2]));
echo 'EXTRA RESULT '.count($GLOBALS['results']).' checks '.$pass.' PASS '.(count($GLOBALS['results'])-$pass)." FAIL\n";
