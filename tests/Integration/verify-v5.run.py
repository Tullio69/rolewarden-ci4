"""Independent V5 black-box runner (Collaudatore ad Hoc). Only rolewarden_test, 127.0.0.1:3317.
Usage: python tests/Integration/verify-v5.run.py <mode> [development|production]
  mode: v5 (themes, main suite; baseline bd7488e)
        v4 (security emails) | test | extra | adapt (V2 suites) | v3 | edges | arrays (V3 suites):
        the V4 regression set, unchanged assertions, run on the V5 commit (baseline 3805640 as in V4).
Derived from verify-v4.run.py (same mail guard, same snapshot/restore, same cleanup).
Mail: the app copy points at Mailpit (smtp 127.0.0.1:1026) before any request; mail() disabled in the server.
Credentials inherited exclusively from RW_DB_USERNAME/RW_DB_PASSWORD, never written.
"""
import hashlib
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess as sp
import sys
import tarfile
import urllib.request

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
WORK = HERE / os.environ.get('RW_V5_WORKNAME', 'verify-v5.work')
APP = WORK / 'app'
NEW = os.environ.get('RW_V5_NEW', 'd3c7dbb')
MAILPIT = 'http://localhost:8026/api/v1/'
ENV = os.environ.copy()
for key in ('RW_DB_USERNAME', 'RW_DB_PASSWORD'):
    if not ENV.get(key):
        raise SystemExit('Missing ' + key)
ENV.update(RW_DB_HOSTNAME='127.0.0.1', RW_DB_PORT='3317', RW_V1_APP=APP.as_posix())
ENV.update({'database.default.username': ENV['RW_DB_USERNAME'], 'database.default.password': ENV['RW_DB_PASSWORD']})

MAIL_ENV = """email.protocol = smtp
email.SMTPHost = 127.0.0.1
email.SMTPPort = 1026
email.SMTPCrypto = ''
email.fromEmail = noreply@rolewarden.test
email.fromName = 'RoleWarden Test'
"""

LISTENER = r"""

// --- verify-v4/v5 (Collaudatore ad Hoc): test-only listener, lives only in the throwaway copy ---
Events::on('rolewarden.mail', static function (...$args) {
    @file_put_contents(WRITEPATH . 'rw-v4-mail-events.jsonl', json_encode($args, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    if (is_file(WRITEPATH . 'rw-v4-block-mail')) {
        return false;
    }
});
"""

MAIL_GUARD = "\nfunction mail_guard(): void {\n    global $APP;\n    $e = (string) file_get_contents(\"$APP/.env\");\n    foreach (['email.protocol = smtp', 'email.SMTPHost = 127.0.0.1', 'email.SMTPPort = 1026', \"email.SMTPCrypto = ''\"] as $l) {\n        if (! preg_match('/^' . preg_quote($l, '/') . '$/m', $e)) { exit(\"ABORT: .env mail settings not on Mailpit ($l)\\n\"); }\n    }\n}\n"


def run(args, **kw):
    return sp.run([str(a) for a in args], env=ENV, check=True, **kw)


def dump():
    return run(['php', HERE / 'verify-v4.snapshot.php'], stdout=sp.PIPE, stderr=sp.PIPE).stdout


def mailpit(method, path):
    req = urllib.request.Request(MAILPIT + path, method=method)
    with urllib.request.urlopen(req, timeout=10) as r:
        body = r.read()
    try:
        return json.loads(body) if body else None
    except ValueError:
        return body.decode('utf-8', 'replace')


def env_guard(text):
    need = ['email.protocol = smtp', 'email.SMTPHost = 127.0.0.1', 'email.SMTPPort = 1026', "email.SMTPCrypto = ''", 'email.fromEmail = noreply@rolewarden.test']
    lines = [l.strip() for l in text.splitlines()]
    for n in need:
        if n not in lines:
            raise SystemExit('ABORT: .env does not point mail at Mailpit: missing ' + n)


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else 'v5'
    environment = sys.argv[2] if len(sys.argv) > 2 else 'development'
    if environment not in ('development', 'production'):
        raise SystemExit('Expected development or production')
    OLD = 'bd7488e' if mode in ('v5', 'probe') else '3805640'
    label = mode + '.' + environment + '.' + NEW
    print('Verification:', NEW, label, 'baseline', OLD, flush=True)
    for host in ('127.0.0.1', '::1'):
        try:
            with socket.create_connection((host, 8070), timeout=1):
                raise SystemExit('ABORT: port 8070 occupied')
        except OSError:
            pass
    if WORK.exists():
        raise SystemExit('ABORT: existing work directory; inspect before retry')
    box = mailpit('GET', 'messages')
    if box['messages_count'] != 0:
        raise SystemExit('ABORT: Mailpit not empty at start')
    before = dump()
    WORK.mkdir()
    (WORK / 'snapshot.sql').write_bytes(before)
    print('Snapshot SHA256:', hashlib.sha256(before).hexdigest(), flush=True)
    result_code = 1
    has_fail = False
    has_blocked = False
    try:
        for rev in (OLD, NEW):
            archive = WORK / (rev + '.tar')
            run(['git', 'archive', '--format=tar', '-o', archive, rev], cwd=ROOT)
            dest = WORK / rev
            dest.mkdir()
            with tarfile.open(archive) as tf:
                tf.extractall(dest, filter='data')
        if mode == 'v5':
            # A second, independent extraction of NEW: "replace the module's folder" again after saving a theme.
            dest = WORK / (NEW + '-again')
            dest.mkdir()
            with tarfile.open(WORK / (NEW + '.tar')) as tf:
                tf.extractall(dest, filter='data')
        cp = sp.run(['robocopy', str(ROOT.parent / 'rolewarden-app-test'), str(APP), '/E', '/XJ', '/XF', '.env', '/XD', '.git', 'writable', '/NFL', '/NDL', '/NJH', '/NJS', '/NP'], stdout=sp.PIPE)
        if cp.returncode >= 8:
            raise RuntimeError('app copy failed')
        for d in ('cache', 'session', 'logs', 'uploads', 'debugbar', 'tmp'):
            (APP / 'writable' / d).mkdir(parents=True, exist_ok=True)
        writable_before = sorted(p.relative_to(APP / 'writable').as_posix() for p in (APP / 'writable').rglob('*'))
        ENV.update(TEMP=str(APP / 'writable/tmp'), TMP=str(APP / 'writable/tmp'))
        env_text = ("CI_ENVIRONMENT = " + environment + """
app.baseURL = 'http://localhost:8070/'
database.default.hostname = 127.0.0.1
database.default.port = 3317
database.default.database = rolewarden_test
database.default.DBDriver = MySQLi
logger.threshold = 9
""" + MAIL_ENV)
        (APP / '.env').write_text(env_text, encoding='utf-8', newline='\n')
        env_guard((APP / '.env').read_text(encoding='utf-8'))
        events = APP / 'app/Config/Events.php'
        events.write_text(events.read_text(encoding='utf-8') + LISTENER, encoding='utf-8')
        for name in ('verify-v1.lib.php', 'verify-v1.fixtures.php', 'verify-v2.php', 'verify-v2.extra.php', 'verify-v2.adapt.php'):
            content = (HERE / name).read_text(encoding='utf-8').replace('3307', '3317')
            content = content.replace("['development','production']", "['" + environment + "']").replace("['development', 'production']", "['" + environment + "']")
            content = content.replace("set_env('development')", "set_env('" + environment + "')")
            content = content.replace('verify-v2.', 'verify-v5.regression.').replace('verify-v1.', 'verify-v5.legacy.')
            content = content.replace("'-d', 'error_reporting=-1', '-S', 'localhost:' . PORT", "'-d', 'error_reporting=-1', '-d', 'disable_functions=mail', '-S', ($GLOBALS['RW_BIND'] ?? 'localhost') . ':' . PORT")
            content = content.replace("function server_start(): void\n{\n    global $APP, $SRV;", "function server_start(): void\n{\n    global $APP, $SRV; mail_guard();")
            if name == 'verify-v2.php':
                if 'mail_guard();' not in content or "disable_functions=mail" not in content:
                    raise RuntimeError('could not install the mail guard in server_start')
                # Same adaptation as V4 (baseline 3805640): V4 adds security.alerts.
                content = content.replace("'V1 installed without sessions permissions',(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'sessions.%'\")===0", "'V3 baseline lacks security.alerts (adapted V4)',(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug='security.alerts'\")===0")
                content = content.replace("q(\"SHOW TABLES LIKE 'acl_sessions'\")===[]", "(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug='security.alerts'\")===0")
                content += MAIL_GUARD
                library = content.split("if (($argv[4] ?? '') === 'extra')")[0]
                (WORK / 'verify-v3.lib.php').write_text(library + MAIL_GUARD, encoding='utf-8')
            dest = name.replace('verify-v2.', 'verify-v5.regression.').replace('verify-v1.', 'verify-v5.legacy.')
            (WORK / dest).write_text(content, encoding='utf-8')
        script = WORK / 'verify-v5.regression.php'
        if mode in ('v3', 'edges', 'arrays'):
            script = WORK / 'verify-v3.php'
            shutil.copyfile(HERE / 'verify-v3.php', script)
            cases = (HERE / 'verify-v3.cases.php').read_text(encoding='utf-8')
            cases = cases.replace("['development','production']", "['" + environment + "']")
            head, sep, tail = cases.partition("$F=make_fixtures();$U=$F['u'];$pw=$F['pw'];\n    server_start();$adm=login2('admin'")
            if not sep:
                raise RuntimeError('V3 upgrade block not found')
            head = head.replace("!in_array('activity.view',role_perms('admin'),true)&&!nav3(page($adm,'rolewarden/users'))", "!in_array('security.alerts',role_perms('admin'),true)")
            head = head.replace("q(\"SHOW TABLES LIKE 'acl_activity_log'\")===[]", "!in_array('security.alerts',role_perms('admin'),true)")
            head = head.replace("in_array('activity.view',role_perms('admin'),true)", "in_array('security.alerts',role_perms('admin'),true)")
            cases = head + sep + tail
            # Instrument fix (also latent in the V4 runner): the PHP server logs are named
            # verify-v5.regression.<mode>.server.log, so the V3 log checks must glob that name.
            if cases.count("__DIR__.'/verify-v3.*server.log'") != 2:
                raise RuntimeError('V3 server-log glob not found')
            cases = cases.replace("__DIR__.'/verify-v3.*server.log'", "__DIR__.'/verify-v5.regression.*server.log'")
            (WORK / 'verify-v3.cases.php').write_text(cases, encoding='utf-8')
        elif mode == 'v4':
            script = WORK / 'verify-v4.php'
            v4 = (HERE / 'verify-v4.php').read_text(encoding='utf-8')
            if 'verify-v4.regression.v4.server.log' not in v4:
                raise RuntimeError('V4 server-log name not found')
            script.write_text(v4.replace('verify-v4.regression.v4.server.log', 'verify-v5.regression.v4.server.log'), encoding='utf-8')
            shutil.copyfile(HERE / 'verify-v4.recheck.php', WORK / 'verify-v4.recheck.php')
        elif mode in ('v5', 'probe'):
            script = WORK / 'verify-v5.php'
            shutil.copyfile(os.environ['RW_V5_PROBE_SCRIPT'] if mode == 'probe' else HERE / 'verify-v5.php', script)
            shutil.copyfile(HERE / 'verify-v5.browser.mjs', WORK / 'verify-v5.browser.mjs')
        args = ['php', '-d', 'error_reporting=-1', str(script), str(APP), str(WORK / OLD), str(WORK / NEW), mode, environment]
        with (HERE / ('verify-v5.' + label + '.output.txt')).open('w', encoding='utf-8') as log:
            proc = sp.Popen(args, env=ENV, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
            for line in proc.stdout:
                has_fail = has_fail or line.startswith('[FAIL]')
                has_blocked = has_blocked or line.startswith('[BLOCKED]')
                log.write(line)
                log.flush()
                print(line, end='', flush=True)
            result_code = proc.wait()
            print('Test process exit:', result_code, flush=True)
            box = mailpit('GET', 'messages?limit=1000')
            bad = []
            for m in box['messages']:
                rcpt = [a['Address'] for a in (m.get('To') or []) + (m.get('Cc') or []) + (m.get('Bcc') or [])]
                if not rcpt or not all(a.lower().endswith('.test') for a in rcpt) or m['From']['Address'] != 'noreply@rolewarden.test':
                    bad.append([m['From']['Address'], rcpt, m['Subject']])
            line = '[MAIL] captured=%d non-test=%s' % (box['messages_count'], json.dumps(bad))
            log.write(line + '\n')
            print(line, flush=True)
            has_fail = has_fail or bool(bad)
            # writable/ of the copy: everything the run created is listed, then removed, and the tree compared.
            created = sorted(p.relative_to(APP / 'writable').as_posix() for p in (APP / 'writable').rglob('*'))
            extra = [p for p in created if p not in writable_before]
            line = '[WRITABLE] entries created during the run (logs/sessions/cache/theme): %d' % len(extra)
            log.write(line + '\n')
            print(line, flush=True)
    finally:
        for f in WORK.glob('verify-v*'):
            if f.suffix in ('.log', '.json', '.html', '.png', '.css') and not f.name.startswith('verify-v5.legacy'):
                shutil.copyfile(f, HERE / ('verify-v5.' + label + '.' + f.name.replace('verify-v5.', '')))
        for f in (APP / 'writable/logs').glob('*.log') if APP.exists() else []:
            shutil.copyfile(f, HERE / ('verify-v5.' + label + '.app.' + f.name))
        try:
            mailpit('DELETE', 'messages')
        except Exception as e:
            print('Mailpit clear failed:', e, flush=True)
        run(['php', HERE / 'verify-v4.snapshot.php', 'restore', WORK / 'snapshot.sql'], stdout=sp.PIPE, stderr=sp.PIPE)
        after = dump()
        same = before == after
        evidence = NEW + ' ' + label + ': before=' + hashlib.sha256(before).hexdigest() + ' after=' + hashlib.sha256(after).hexdigest() + ' identical=' + str(same) + ' mailpit_after=' + str(mailpit('GET', 'messages')['messages_count'])
        print(evidence, flush=True)
        with (HERE / 'verify-v5.environment.txt').open('a', encoding='utf-8') as log:
            log.write(evidence + '\n')
        if not same:
            raise RuntimeError('Snapshot mismatch: work directory preserved')
        assert WORK.resolve().parent == HERE
        link = APP / 'vendor/rolewarden/codeigniter4-rolewarden'
        if link.is_junction() or link.is_symlink():
            os.rmdir(link)
        shutil.rmtree(WORK)
    return result_code or int(has_fail) or (2 if has_blocked else 0)


if __name__ == '__main__':
    sys.exit(main())
