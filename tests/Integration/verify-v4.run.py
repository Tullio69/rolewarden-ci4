"""Independent V4 black-box runner (Collaudatore ad Hoc). Only rolewarden_test, 127.0.0.1:3317.
Usage: python tests/Integration/verify-v4.run.py <mode> [development|production]
  mode: v4 (security emails) | test | extra | adapt (V2 suites) | v3 | edges | arrays (V3 suites)
Mail: every run points the app copy at Mailpit (smtp 127.0.0.1:1026, fromEmail noreply@rolewarden.test)
and refuses to start the PHP server otherwise; mail() is disabled in the server. Mailpit must be empty
at start; at the end every captured message is checked to be addressed to *.test and then deleted.
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
WORK = HERE / 'verify-v4.work'
APP = WORK / 'app'
OLD, NEW = '3805640', os.environ.get('RW_V4_NEW', '2028ea2')
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

# Test-only listener in the COPY's app/Config/Events.php (README "Security emails": rolewarden.mail).
# Records every payload; returns false only while the flag file exists.
LISTENER = r"""

// --- verify-v4 (Collaudatore ad Hoc): test-only listener, lives only in the throwaway copy ---
Events::on('rolewarden.mail', static function (...$args) {
    @file_put_contents(WRITEPATH . 'rw-v4-mail-events.jsonl', json_encode($args, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n", FILE_APPEND);
    if (is_file(WRITEPATH . 'rw-v4-block-mail')) {
        return false;
    }
});
"""


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
    need = ['email.protocol = smtp', 'email.SMTPHost = 127.0.0.1', 'email.SMTPPort = 1026', "email.SMTPCrypto = ''"]
    lines = [l.strip() for l in text.splitlines()]
    for n in need:
        if n not in lines:
            raise SystemExit('ABORT: .env does not point mail at Mailpit: missing ' + n)


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else 'v4'
    environment = sys.argv[2] if len(sys.argv) > 2 else 'development'
    if environment not in ('development', 'production'):
        raise SystemExit('Expected development or production')
    label = mode + '.' + environment + ('' if NEW == '2028ea2' else '.' + NEW)
    print('Verification:', NEW, label, flush=True)
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
        # Reuse the permitted V1/V2 helpers and assertions, without modifying their originals.
        for name in ('verify-v1.lib.php', 'verify-v1.fixtures.php', 'verify-v2.php', 'verify-v2.extra.php', 'verify-v2.adapt.php'):
            content = (HERE / name).read_text(encoding='utf-8').replace('3307', '3317')
            content = content.replace("['development','production']", "['" + environment + "']").replace("['development', 'production']", "['" + environment + "']")
            content = content.replace("set_env('development')", "set_env('" + environment + "')")
            content = content.replace('verify-v2.', 'verify-v4.regression.').replace('verify-v1.', 'verify-v4.legacy.')
            content = content.replace("'-d', 'error_reporting=-1', '-S', 'localhost:' . PORT", "'-d', 'error_reporting=-1', '-d', 'disable_functions=mail', '-S', ($GLOBALS['RW_BIND'] ?? 'localhost') . ':' . PORT")
            content = content.replace("function server_start(): void\n{\n    global $APP, $SRV;", "function server_start(): void\n{\n    global $APP, $SRV; mail_guard();")
            if name == 'verify-v2.php':
                if 'mail_guard();' not in content or "disable_functions=mail" not in content:
                    raise RuntimeError('could not install the mail guard in server_start')
                # Baseline is now V3 (3805640): sessions and activity already exist; V4 adds only security.alerts.
                content = content.replace("'V1 installed without sessions permissions',(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug LIKE 'sessions.%'\")===0", "'V3 baseline lacks security.alerts (adapted V4)',(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug='security.alerts'\")===0")
                content = content.replace("q(\"SHOW TABLES LIKE 'acl_sessions'\")===[]", "(int)q1(\"SELECT COUNT(*) FROM acl_permissions WHERE slug='security.alerts'\")===0")
                content += "\nfunction mail_guard(): void {\n    global $APP;\n    $e = (string) file_get_contents(\"$APP/.env\");\n    foreach (['email.protocol = smtp', 'email.SMTPHost = 127.0.0.1', 'email.SMTPPort = 1026', \"email.SMTPCrypto = ''\"] as $l) {\n        if (! preg_match('/^' . preg_quote($l, '/') . '$/m', $e)) { exit(\"ABORT: .env mail settings not on Mailpit ($l)\\n\"); }\n    }\n}\n"
                library = content.split("if (($argv[4] ?? '') === 'extra')")[0]
                (WORK / 'verify-v3.lib.php').write_text(library + "\nfunction mail_guard(): void {\n    global $APP;\n    $e = (string) file_get_contents(\"$APP/.env\");\n    foreach (['email.protocol = smtp', 'email.SMTPHost = 127.0.0.1', 'email.SMTPPort = 1026', \"email.SMTPCrypto = ''\"] as $l) {\n        if (! preg_match('/^' . preg_quote($l, '/') . '$/m', $e)) { exit(\"ABORT: .env mail settings not on Mailpit ($l)\\n\"); }\n    }\n}\n", encoding='utf-8')
            dest = name.replace('verify-v2.', 'verify-v4.regression.').replace('verify-v1.', 'verify-v4.legacy.')
            (WORK / dest).write_text(content, encoding='utf-8')
        script = WORK / 'verify-v4.regression.php'
        if mode in ('v3', 'edges', 'arrays'):
            script = WORK / 'verify-v3.php'
            shutil.copyfile(HERE / 'verify-v3.php', script)
            cases = (HERE / 'verify-v3.cases.php').read_text(encoding='utf-8')
            cases = cases.replace("['development','production']", "['" + environment + "']")
            # Baseline is now V3: the V2->V3 upgrade expectations are superseded; the same checks now
            # follow the permission V4 adds (security.alerts). Documented in V4-REPORT.md.
            head, sep, tail = cases.partition("$F=make_fixtures();$U=$F['u'];$pw=$F['pw'];\n    server_start();$adm=login2('admin'")
            if not sep:
                raise RuntimeError('V3 upgrade block not found')
            head = head.replace("!in_array('activity.view',role_perms('admin'),true)&&!nav3(page($adm,'rolewarden/users'))", "!in_array('security.alerts',role_perms('admin'),true)")
            head = head.replace("q(\"SHOW TABLES LIKE 'acl_activity_log'\")===[]", "!in_array('security.alerts',role_perms('admin'),true)")
            head = head.replace("in_array('activity.view',role_perms('admin'),true)", "in_array('security.alerts',role_perms('admin'),true)")
            cases = head + sep + tail
            (WORK / 'verify-v3.cases.php').write_text(cases, encoding='utf-8')
        elif mode == 'v4':
            script = WORK / 'verify-v4.php'
            shutil.copyfile(HERE / 'verify-v4.php', script)
        with (HERE / ('verify-v4.' + label + '.output.txt')).open('w', encoding='utf-8') as log:
            proc = sp.Popen(['php', '-d', 'error_reporting=-1', str(script), str(APP), str(WORK / OLD), str(WORK / NEW), mode, environment], env=ENV, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
            for line in proc.stdout:
                has_fail = has_fail or line.startswith('[FAIL]')
                log.write(line)
                log.flush()
                print(line, end='', flush=True)
            result_code = proc.wait()
            print('Test process exit:', result_code, flush=True)
            # Mail safety evidence: every captured message went to a *.test address from the test sender.
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
    finally:
        for f in WORK.glob('verify-v4.*'):
            if f.suffix in ('.log', '.json', '.html'):
                shutil.copyfile(f, HERE / ('verify-v4.' + label + '.' + f.name))
        for f in (APP / 'writable/logs').glob('*.log'):
            shutil.copyfile(f, HERE / ('verify-v4.' + label + '.app.' + f.name))
        try:
            mailpit('DELETE', 'messages')
        except Exception as e:  # reported, cleanup continues
            print('Mailpit clear failed:', e, flush=True)
        run(['php', HERE / 'verify-v4.snapshot.php', 'restore', WORK / 'snapshot.sql'], stdout=sp.PIPE, stderr=sp.PIPE)
        after = dump()
        same = before == after
        evidence = NEW + ' ' + label + ': before=' + hashlib.sha256(before).hexdigest() + ' after=' + hashlib.sha256(after).hexdigest() + ' identical=' + str(same) + ' mailpit_after=' + str(mailpit('GET', 'messages')['messages_count'])
        print(evidence, flush=True)
        with (HERE / 'verify-v4.environment.txt').open('a', encoding='utf-8') as log:
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
