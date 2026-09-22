"""Independent black-box M5 runner. Reads no module implementation or existing tests."""
import os, sys, json, shutil, subprocess, time, hashlib, secrets, re, winreg
from pathlib import Path
import requests
from html.parser import HTMLParser

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'tests/Integration'
APP = OUT / 'verify-m5.app'
HOST = ROOT.parent / 'rolewarden-app-test'
BASE = 'http://127.0.0.1:18975'
RESULTS = []
def check(name, ok, detail=''):
    RESULTS.append({'name':name,'pass':bool(ok),'detail':detail})
    print(('PASS ' if ok else 'FAIL ') + name + (' | '+str(detail) if detail else ''), flush=True)
def write(path, data):
    path.parent.mkdir(parents=True, exist_ok=True); path.write_text(data, encoding='utf-8')
def env_setup():
    env = os.environ.copy()
    with winreg.OpenKey(winreg.HKEY_CURRENT_USER, 'Environment') as key:
        for name in ('RW_DB_HOSTNAME','RW_DB_USERNAME','RW_DB_PASSWORD','RW_DB_PORT'):
            if not env.get(name):
                env[name] = str(winreg.QueryValueEx(key,name)[0])
    env['CI_ENVIRONMENT']='development'
    env['RW_M5_PASSWORD']=secrets.token_urlsafe(25)+'Aa9!'
    env['RW_M5_BASE']=BASE+'/'
    return env
ENV=env_setup()
def prepare():
    if APP.exists(): raise RuntimeError('Temporary app already exists; preserve for diagnosis')
    APP.mkdir()
    # Copy host application opaquely, excluding all runtime state, credentials and tests.
    shutil.copytree(HOST/'app',APP/'app')
    for d in ['cache','logs','session','uploads','debugbar']:
        (APP/'writable'/d).mkdir(parents=True, exist_ok=True)
    (APP/'public').mkdir()
    vendor = (HOST/'vendor').as_posix()
    paths = f'''<?php namespace Config;
class Paths {{
 public string $systemDirectory = '{vendor}/codeigniter4/framework/system';
 public string $appDirectory = __DIR__ . '/..';
 public string $writableDirectory = __DIR__ . '/../../writable';
 public string $testsDirectory = __DIR__ . '/../../tests';
 public string $viewDirectory = __DIR__ . '/../Views';
}}
'''
    write(APP/'app/Config/Paths.php',paths)
    write(APP/'composer-bootstrap.php', f'''<?php
$loader = require '{vendor}/autoload.php';
$map = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__.'/app', FilesystemIterator::SKIP_DOTS)) as $file) {{
 if ($file->getExtension() !== 'php') continue;
 $relative = substr($file->getPathname(), strlen(__DIR__.'/app/'));
 $class = str_replace(['/','\\\\'], '\\\\', substr($relative,0,-4));
 $map[str_starts_with($class,'Config\\\\') ? $class : 'App\\\\'.$class] = $file->getPathname();
}}
$loader->addClassMap($map);
$loader->setPsr4('App\\\\', __DIR__.'/app');
$loader->setPsr4('Config\\\\', __DIR__.'/app/Config');
''')
    write(APP/'public/index.php', '''<?php
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR); chdir(FCPATH);
require __DIR__.'/../composer-bootstrap.php';
require __DIR__.'/../app/Config/Paths.php';
$paths = new Config\\Paths(); require $paths->systemDirectory.'/Boot.php';
exit(CodeIgniter\\Boot::bootWeb($paths));
''')
    write(APP/'spark', '''<?php
define('FCPATH', __DIR__ . '/public/'); chdir(__DIR__);
require __DIR__.'/composer-bootstrap.php';
require __DIR__.'/app/Config/Paths.php';
$paths = new Config\\Paths(); require $paths->systemDirectory.'/Boot.php';
exit(CodeIgniter\\Boot::bootSpark($paths));
''')
    write(APP/'app/Config/Database.php', '''<?php namespace Config;
class Database extends \\CodeIgniter\\Database\\Config {
 public string $filesPath = APPPATH.'Database'.DIRECTORY_SEPARATOR;
 public string $defaultGroup = 'default';
 public array $default = []; public array $tests = [];
 public function __construct() {
  parent::__construct();
  $this->default = ['DSN'=>'','hostname'=>getenv('RW_DB_HOSTNAME'),'username'=>getenv('RW_DB_USERNAME'),'password'=>getenv('RW_DB_PASSWORD'),'database'=>'rolewarden_test','DBDriver'=>'MySQLi','DBPrefix'=>'','pConnect'=>false,'DBDebug'=>true,'charset'=>'utf8mb4','DBCollat'=>'utf8mb4_general_ci','swapPre'=>'','encrypt'=>false,'compress'=>false,'strictOn'=>true,'failover'=>[],'port'=>(int)getenv('RW_DB_PORT'),'dateFormat'=>['date'=>'Y-m-d','datetime'=>'Y-m-d H:i:s','time'=>'H:i:s']];
  $this->tests=$this->default;
 }
}
''')
    write(APP/'app/Config/Auth.php', '''<?php namespace Config;
class Auth extends \\CodeIgniter\\Shield\\Config\\Auth {
 public string $userProvider = \\RoleWarden\\Models\\UserModel::class;
 public array $actions = ['register'=>null,'login'=>null];
}
''')
    write(APP/'app/Config/Filters.php', '''<?php namespace Config;
class Filters extends \\CodeIgniter\\Config\\Filters {
 public array $aliases = ['csrf'=>\\CodeIgniter\\Filters\\CSRF::class,'session'=>\\CodeIgniter\\Shield\\Filters\\SessionAuth::class,'can'=>\\RoleWarden\\Filters\\PermissionFilter::class];
 public array $required = ['before'=>[], 'after'=>[]];
 public array $globals = ['before'=>['csrf'], 'after'=>[]];
 public array $methods=[]; public array $filters=[];
}
''')
    write(APP/'app/Config/Routes.php', '''<?php
service('auth')->routes($routes);
\\RoleWarden\\Config\\RouteRegistrar::register($routes);
$routes->get('verify-m5/status', static function() {
 $u=auth()->user(); $db=db_connect();
 return service('response')->setJSON(['db'=>$db->query('SELECT DATABASE() d')->getRow()->d,'user'=>$u?->id,'can'=>$u?->can('users.view'),'php'=>PHP_VERSION,'ci'=>\\CodeIgniter\\CodeIgniter::CI_VERSION]);
});
''')
    # Non-secret runtime settings. Database credentials exist only in inherited environment.
    write(APP/'.env', "CI_ENVIRONMENT = development\napp.baseURL = '"+BASE+"/'\napp.indexPage = ''\nsecurity.csrfProtection = 'session'\nsecurity.redirect = false\ncache.handler = 'file'\n")
    write(APP/'app/Commands/M5Fixture.php', '''<?php namespace App\\Commands;
class M5Fixture extends \\CodeIgniter\\CLI\\BaseCommand {
 protected $group='Test'; protected $name='verify:m5-fixture'; protected $description='Independent M5 fixtures';
 public function run(array $params) {
  $db=db_connect(); if ($db->query('SELECT DATABASE() d')->getRow()->d !== 'rolewarden_test') throw new \\RuntimeException('DB guard');
  $model=model(\\RoleWarden\\Models\\UserModel::class);
  foreach (['root','reader','none','subject'] as $name) {
   $u=new \\RoleWarden\\Entities\\User(['username'=>'m5_'.$name,'email'=>'m5_'.$name.'@example.test','password'=>getenv('RW_M5_PASSWORD'),'active'=>1]);
   if (!$model->save($u)) throw new \\RuntimeException('Fixture user failed');
   $id=$model->getInsertID();
   if($name==='root') $db->table('acl_user_roles')->insert(['user_id'=>$id,'role_id'=>$db->table('acl_roles')->where('slug','super-admin')->get()->getRow()->id]);
   if($name==='reader') foreach(['users.view','roles.view','permissions.view'] as $slug) $db->table('acl_user_permissions')->insert(['user_id'=>$id,'permission_id'=>$db->table('acl_permissions')->where('slug',$slug)->get()->getRow()->id,'granted'=>1]);
  }
  echo "M5_FIXTURE_READY\\n";
 }
}
''')
def spark(*args):
    p=subprocess.run(['php',str(APP/'spark'),*args],cwd=APP,env=ENV,capture_output=True,text=True)
    # Output never includes environment; hide DB diagnostic strings on harness failure.
    if p.returncode or re.search(r'Exception|Error|Fatal|Warning',p.stdout+p.stderr):
        print('SPARK DIAGNOSTIC '+(p.stdout+p.stderr)[:6000],flush=True)
        raise RuntimeError('Spark failed: '+' '.join(args))
    return p.stdout
class DB:
    def __init__(self):
        self.p=subprocess.Popen(['php',str(OUT/'verify-m5.db.php')],env=ENV,stdin=subprocess.PIPE,stdout=subprocess.PIPE,stderr=subprocess.PIPE,text=True)
        self.ready=json.loads(self.p.stdout.readline()); print('SNAPSHOT '+json.dumps(self.ready),flush=True)
    def op(self, op, **kw):
        self.p.stdin.write(json.dumps(dict(op=op,**kw))+'\n'); self.p.stdin.flush()
        reply=json.loads(self.p.stdout.readline())
        if not reply.get('ok'): raise RuntimeError(str(reply))
        return reply['data']
    def q(self,sql,*args): return self.op('query',sql=sql,args=args)
    def restore(self):
        self.p.stdin.write('{"op":"restore"}\n'); self.p.stdin.flush()
        reply=json.loads(self.p.stdout.readline()); self.p.wait(timeout=30)
        check('Snapshot ripristinato esattamente (DDL, righe, contatori)',reply.get('restored') and reply['hash']==self.ready['hash'])

class HTML(HTMLParser):
    def __init__(self, text):
        super().__init__(); self.forms=[]; self.links=[]; self.fields=[]; self.form=None; self.feed(text)
    def handle_starttag(self,tag,attrs):
        a=dict(attrs)
        if tag=='form': self.form=dict(a,fields=[]); self.forms.append(self.form)
        if tag in ('input','select','textarea','button'):
            a['tag']=tag; self.fields.append(a)
            if self.form is not None: self.form['fields'].append(a)
        if tag=='a': self.links.append(a)
    def handle_endtag(self,tag):
        if tag=='form': self.form=None
class Client:
    def __init__(self): self.s=requests.Session()
    def get(self,path): return self.s.get(BASE+path,timeout=15)
    def token(self,path='/login'):
        r=self.get(path)
        f=next((f for f in HTML(r.text).fields if 'csrf' in f.get('name','')),None)
        if not f: raise RuntimeError('CSRF token missing on '+path+' status '+str(r.status_code))
        return {f['name']:f['value']}
    def post(self,path,data=None,source='/login',csrf=True,json_body=False):
        token=self.token(source) if csrf else {}
        return self.s.post(BASE+path, json={**(data or {}),**token} if json_body else None, data=None if json_body else {**(data or {}),**token}, headers={'X-Requested-With':'XMLHttpRequest'} if json_body else {}, timeout=15)
    def login(self,name):
        r=self.post('/login',{'email':'m5_'+name+'@example.test','password':ENV['RW_M5_PASSWORD']})
        r=self.get('/verify-m5/status'); info=r.json(); check('Login Shield '+name, bool(info['user']) and info['db']=='rolewarden_test'); return info

def main():
    db=None; server=None; hosthash=hashlib.sha256((HOST/'.env').read_bytes()).hexdigest()
    try:
        check('HEAD richiesto',subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()=='670dd97730bb121d06f2c355034ecec0fef96089')
        prepare(); db=DB(); db.op('clear')
        for ns in ['CodeIgniter\\Settings','CodeIgniter\\Shield','RoleWarden']: spark('migrate','-n',ns)
        spark('db:seed','RoleWarden\\Database\\Seeds\\RoleWardenSeeder'); spark('verify:m5-fixture')
        log=open(OUT/'verify-m5.server-output.txt','w',encoding='utf-8')
        server=subprocess.Popen(['php','-d','error_reporting=32767','-S','127.0.0.1:18975','-t',str(APP/'public'),str(APP/'public/index.php')],cwd=APP,env=ENV,stdout=log,stderr=log)
        for _ in range(40):
            try:
                r=requests.get(BASE+'/verify-m5/status',timeout=1)
                if r.status_code==200: break
                print('BOOT HTTP '+str(r.status_code)+' '+re.sub('<[^>]+>',' ',r.text)[:2500],flush=True); break
            except requests.RequestException: time.sleep(.25)
        check('HTTP usa esclusivamente rolewarden_test',r.status_code==200 and r.json()['db']=='rolewarden_test')
        root=Client(); root.login('root')
        # Runtime route table is a public interface, no source inspection.
        write(OUT/'verify-m5.routes.txt',spark('routes'))
        r=root.get('/admin/users'); print('DISCOVERY '+str(r.status_code)+' '+r.url,flush=True)
        print(json.dumps({'forms':HTML(r.text).forms,'links':HTML(r.text).links}),flush=True)
        if '--interactive' in sys.argv:
            # External browser/HTTP steps execute against the same isolated process.
            print('M5_INTERACTIVE_READY',flush=True)
            write(OUT/'verify-m5.runtime.json',{'unused':0}.__str__())
            while True:
                marker=OUT/'verify-m5.stop'
                if marker.exists(): marker.unlink(); break
                command=OUT/'verify-m5.command.json'
                if command.exists():
                    req=json.loads(command.read_text()); command.unlink()
                    try:
                        if req['op']=='query': result=db.q(req['sql'],*req.get('args',[]))
                        elif req['op']=='http':
                            c=root
                            if req.get('user') and req['user']!='root':
                                c=clients.setdefault(req['user'],Client())
                                if not c.s.cookies: c.login(req['user'])
                            rr=c.post(req['path'],req.get('data'),req.get('source','/login'),req.get('csrf',True),req.get('json',False)) if req.get('method')=='POST' else c.get(req['path'])
                            result={'status':rr.status_code,'url':rr.url,'body':rr.text,'forms':HTML(rr.text).forms,'links':HTML(rr.text).links}
                        elif req['op']=='suite':
                            exec(compile((OUT/'verify-m5.cases.py').read_text(encoding='utf-8'),str(OUT/'verify-m5.cases.py'),'exec'),globals(),locals()); result=RESULTS
                        else: raise RuntimeError('Unknown op')
                        write(OUT/'verify-m5.reply.json',json.dumps({'ok':True,'result':result}))
                    except Exception as e: write(OUT/'verify-m5.reply.json',json.dumps({'ok':False,'error':str(e)}))
                time.sleep(.2)
    finally:
        if server: server.terminate(); server.wait(timeout=20)
        if db: db.restore()
        check('Host .env invariato',hosthash==hashlib.sha256((HOST/'.env').read_bytes()).hexdigest())
        if APP.exists():
            if APP.resolve().parent!=OUT.resolve() or APP.name!='verify-m5.app': raise RuntimeError('Cleanup path guard')
            shutil.rmtree(APP)
        check('App temporanea rimossa',not APP.exists())
        write(OUT/'verify-m5.results.json',json.dumps(RESULTS,indent=2))
        print('TOTAL '+str(sum(r['pass'] for r in RESULTS))+' PASS / '+str(sum(not r['pass'] for r in RESULTS))+' FAIL',flush=True)
clients={}
if __name__=='__main__': main()
