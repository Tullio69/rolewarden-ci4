<?php
// Independent HTTP tests against the permitted specification; no implementation reads.
require __DIR__ . '/verify-v3.lib.php';
if (in_array($argv[4]??'',['v3','edges','arrays'],true)) {
    require __DIR__.'/verify-v3.cases.php';
    try {if($argv[4]==='edges')edges3();elseif($argv[4]==='arrays')arrays3();else run3();} finally {server_stop();}
    $pass=count(array_filter($GLOBALS['results'],fn($r)=>$r[2]));
    echo 'V3 RESULT '.count($GLOBALS['results']).' checks '.$pass.' PASS '.(count($GLOBALS['results'])-$pass)." FAIL\n";
    exit($pass===count($GLOBALS['results'])?0:1);
}
try {
    reset_db(); use_module($MOD_NEW); readme_install();
    $F=make_fixtures(); $U=$F['u']; $pw=$F['pw'];
    server_start();
    $adm=login2('v3-admin',$U['admin2']['email'],$pw);
    $adm=login2('v3-owner',$U['owner']['email'],$pw);
    foreach (['rolewarden/users/create','rolewarden/users/'.$U['subject']['id'].'/edit','rolewarden/roles/create','rolewarden/roles/7','rolewarden/permissions','rolewarden/profile'] as $url) {
        $h=page($adm,$url);
        echo '[HTML] '.$url.' status='.$adm->status."\n";
        echo '[FORMS] '.json_encode(forms($h))."\n";
        preg_match_all('/<a[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>/s',$h,$links,PREG_SET_ORDER);
        echo '[LINKS] '.json_encode(array_map(fn($l)=>[$l[1],strip_tags($l[2])],$links))."\n";
        preg_match_all('/x-data="([^"]*)"/s',$h,$data);
        echo '[DATA] '.json_encode(array_map(fn($s)=>html_entity_decode($s,ENT_QUOTES|ENT_HTML5),$data[1]))."\n";
    }
} finally { server_stop(); }
