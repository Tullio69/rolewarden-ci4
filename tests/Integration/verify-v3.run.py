"""Independent V3 black-box runner. Only rolewarden_test, 127.0.0.1:3317.
Usage: python tests/Integration/verify-v3.run.py [probe|test|extra|adapt|v3|edges]
Regression copies change transport/output paths; assertion adaptations documented below.
Credentials inherited exclusively from RW_DB_USERNAME/RW_DB_PASSWORD, never written.
"""
import hashlib
import os
from pathlib import Path
import shutil
import socket
import subprocess as sp
import sys
import tarfile

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
WORK = HERE / 'verify-v3.work'
APP = WORK / 'app'
OLD, NEW = '162ccd4', os.environ.get('RW_V3_NEW', 'a602071')  # override only for the sensitivity check against 0e3d6f6
ENV = os.environ.copy()
for key in ('RW_DB_USERNAME', 'RW_DB_PASSWORD'):
    if not ENV.get(key):
        raise SystemExit('Missing ' + key)
ENV.update(RW_DB_HOSTNAME='127.0.0.1', RW_DB_PORT='3317', RW_V1_APP=APP.as_posix())
ENV.update({'database.default.username': ENV['RW_DB_USERNAME'], 'database.default.password': ENV['RW_DB_PASSWORD']})

def run(args, **kw):
    return sp.run([str(a) for a in args], env=ENV, check=True, **kw)

def dump():
    return run(['php', HERE / 'verify-v3.snapshot.php'], stdout=sp.PIPE, stderr=sp.PIPE).stdout

def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else 'v3'
    environment = sys.argv[2] if len(sys.argv) > 2 else 'development'
    if environment not in ('development', 'production'):
        raise SystemExit('Expected development or production')
    label = mode + '.' + environment + ('' if NEW == 'a602071' else '.' + NEW)
    print('Reverification:', NEW, label, flush=True)
    for host in ('127.0.0.1', '::1'):
        try:
            with socket.create_connection((host, 8070), timeout=1):
                raise SystemExit('ABORT: port 8070 occupied')
        except OSError:
            pass
    if WORK.exists():
        raise SystemExit('ABORT: existing work directory; inspect before retry')
    before = dump()  # fail closed: existing V2 adapter requires an empty database
    WORK.mkdir()
    (WORK / 'snapshot.sql').write_bytes(before)
    print('Snapshot SHA256:', hashlib.sha256(before).hexdigest(), flush=True)
    try:
        for rev in (OLD, NEW):
            archive = WORK / (rev + '.tar')
            run(['git', 'archive', '--format=tar', '-o', archive, rev], cwd=ROOT)
            dest = WORK / rev
            dest.mkdir()
            with tarfile.open(archive) as tf:
                tf.extractall(dest, filter='data')
        cp = sp.run(['robocopy', str(ROOT.parent / 'rolewarden-app-test'), str(APP), '/E', '/XJ', '/XF', '.env', '/XD', '.git', 'writable', '/NFL', '/NDL', '/NJH', '/NJS', '/NP'], stdout=sp.PIPE)
        if cp.returncode >= 8:
            raise RuntimeError('app copy failed')
        for d in ('cache', 'session', 'logs', 'uploads', 'debugbar', 'tmp'):
            (APP / 'writable' / d).mkdir(parents=True, exist_ok=True)
        ENV.update(TEMP=str(APP / 'writable/tmp'), TMP=str(APP / 'writable/tmp'))
        (APP / '.env').write_text("""CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8070/'
database.default.hostname = 127.0.0.1
database.default.port = 3317
database.default.database = rolewarden_test
database.default.DBDriver = MySQLi
logger.threshold = 9
email.protocol = smtp
email.SMTPHost = 127.0.0.1
email.SMTPPort = 9
""".replace('CI_ENVIRONMENT = development', 'CI_ENVIRONMENT = ' + environment), encoding='utf-8', newline='\n')
        # Reuse the permitted V2 helpers and assertions, without modifying their originals.
        for name in ('verify-v1.lib.php', 'verify-v1.fixtures.php', 'verify-v2.php', 'verify-v2.extra.php', 'verify-v2.adapt.php'):
            content = (HERE / name).read_text(encoding='utf-8').replace('3307', '3317')
            content = content.replace("['development','production']", "['" + environment + "']").replace("['development', 'production']", "['" + environment + "']")
            content = content.replace("set_env('development')", "set_env('" + environment + "')")
            content = content.replace('verify-v2.', 'verify-v3.regression.').replace('verify-v1.', 'verify-v3.legacy.')
            content = content.replace("'-d', 'error_reporting=-1', '-S'", "'-d', 'error_reporting=-1', '-d', 'disable_functions=mail', '-S'")
            if name == 'verify-v2.php':
                # V2 baseline now already has sessions: A01/A04's missing-sessions expectation is obsolete.
                content = content.replace("'V1 installed without sessions permissions',(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'sessions.%'\")===0", "'V2 baseline already has sessions; lacks activity.view',(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug='activity.view'\")===0")
                content = content.replace("q(\"SHOW TABLES LIKE 'acl_sessions'\")===[]", "q(\"SHOW TABLES LIKE 'acl_activity_log'\")===[]")
                library = content.split("if (($argv[4] ?? '') === 'extra')")[0]
                (WORK / 'verify-v3.lib.php').write_text(library, encoding='utf-8')
            dest = name.replace('verify-v2.', 'verify-v3.regression.').replace('verify-v1.', 'verify-v3.legacy.')
            (WORK / dest).write_text(content, encoding='utf-8')
        script = WORK / 'verify-v3.regression.php'
        if mode in ('probe', 'v3', 'edges', 'arrays'):
            script = WORK / 'verify-v3.php'
            shutil.copyfile(HERE / 'verify-v3.php', script)
            cases = (HERE / 'verify-v3.cases.php').read_text(encoding='utf-8')
            cases = cases.replace("['development','production']", "['" + environment + "']")
            (WORK / 'verify-v3.cases.php').write_text(cases, encoding='utf-8')
        has_fail = False
        with (HERE / ('verify-v3.recheck.' + label + '.output.txt')).open('w', encoding='utf-8') as log:
            proc = sp.Popen(['php', '-d', 'error_reporting=-1', str(script), str(APP), str(WORK / OLD), str(WORK / NEW), mode], env=ENV, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
            for line in proc.stdout:
                has_fail = has_fail or line.startswith('[FAIL]')
                log.write(line)
                log.flush()
                print(line, end='', flush=True)
            result_code = proc.wait()
            print('Test process exit:', result_code, flush=True)
    finally:
        for f in WORK.glob('verify-v3.*'):
            if f.suffix in ('.log', '.json', '.html'):
                shutil.copyfile(f, HERE / ('verify-v3.recheck.' + label + '.' + f.name))
        for f in (APP / 'writable/logs').glob('*.log'):
            shutil.copyfile(f, HERE / ('verify-v3.recheck.' + label + '.app.' + f.name))
        run(['php', HERE / 'verify-v3.snapshot.php', 'restore', WORK / 'snapshot.sql'], stdout=sp.PIPE, stderr=sp.PIPE)
        after = dump()
        same = before == after
        evidence = NEW + ' ' + label + ': before=' + hashlib.sha256(before).hexdigest() + ' after=' + hashlib.sha256(after).hexdigest() + ' identical=' + str(same)
        print(evidence, flush=True)
        with (HERE / 'verify-v3.environment.txt').open('a', encoding='utf-8') as log:
            log.write(evidence + '\n')
        if not same:
            raise RuntimeError('Snapshot mismatch: work directory preserved')
        assert WORK.resolve().parent == HERE
        link = APP / 'vendor/rolewarden/codeigniter4-rolewarden'
        if link.is_junction() or link.is_symlink():
            os.rmdir(link)
        shutil.rmtree(WORK)
    return result_code or int(has_fail)

if __name__ == '__main__':
    sys.exit(main())
