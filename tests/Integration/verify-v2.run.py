"""Independent V2 runner. Credentials stay in process environments, never files.
Run: RW_DB_USERNAME=... RW_DB_PASSWORD=... python tests/Integration/verify-v2.run.py [test|extra|regression]
Only rolewarden_test at 127.0.0.1:3307; snapshot restored even on failure.
Written by Codex; retargeted to 5c7bfc5 and given a regression mode that runs the V1 scripts
unchanged (verify-v1.sh through Git Bash, verify-v1-m1.php from a copy so it cannot append to
the committed server log) by the Collaudatore ad Hoc (Claude).
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
WORK = HERE / 'verify-v2.work'
APP = WORK / 'app'
NEW = '37bb47f'  # re-verification of D1 (was 5c7bfc5)
BASE_V1 = '35f058f'
M1_OLD = '3631fd2'
GIT_BASH = r'C:\Program Files\Git\bin\bash.exe'
ENV = os.environ.copy()
for key in ('RW_DB_USERNAME', 'RW_DB_PASSWORD'):
    if not ENV.get(key):
        raise SystemExit('Missing ' + key)
ENV.update(RW_DB_HOSTNAME='127.0.0.1', RW_DB_PORT='3307', RW_V1_APP=APP.as_posix())
ENV.update({'database.default.username': ENV['RW_DB_USERNAME'], 'database.default.password': ENV['RW_DB_PASSWORD']})


def run(args, **kw):
    return sp.run([str(a) for a in args], env=ENV, check=True, **kw)


def dump():
    return run(['php', HERE / 'verify-v2.snapshot.php'], stdout=sp.PIPE, stderr=sp.PIPE).stdout


def stream(args, output, cwd=ROOT):
    with (HERE / output).open('w', encoding='utf-8') as log:
        proc = sp.Popen([str(a) for a in args], cwd=cwd, env=ENV, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
        for line in proc.stdout:
            log.write(line)
            log.flush()
            print(line, end='', flush=True)
        return proc.wait()


def junction(target):
    link = APP / 'vendor' / 'rolewarden' / 'codeigniter4-rolewarden'
    if link.is_junction() or link.is_symlink():
        os.rmdir(link)
    sp.run(['cmd', '/c', 'mklink', '/J', str(link), str(target)], check=True, stdout=sp.DEVNULL)
    assert (link / 'src').is_dir()


def main():
    mode = sys.argv[1] if len(sys.argv) > 1 else 'test'
    for host in ('127.0.0.1', '::1'):
        try:
            with socket.create_connection((host, 8070), timeout=1):
                raise SystemExit('ABORT: port 8070 already occupied')
        except OSError:
            pass
    if WORK.exists():
        raise SystemExit('Existing work directory: preserve and inspect before running')
    WORK.mkdir()
    before = dump()
    (WORK / 'snapshot.sql').write_bytes(before)
    print('Snapshot SHA256:', hashlib.sha256(before).hexdigest(), flush=True)
    try:
        for rev in (BASE_V1, NEW, M1_OLD):
            archive = WORK / (rev + '.tar')
            run(['git', 'archive', '--format=tar', '-o', archive, rev], cwd=ROOT)
            dest = WORK / rev
            dest.mkdir()
            with tarfile.open(archive) as tf:
                tf.extractall(dest, filter='data')
        cp = sp.run(['robocopy', str(ROOT.parent / 'rolewarden-app-test'), str(APP), '/E', '/XJ', '/XF', '.env', '/XD', '.git', 'writable', '/NFL', '/NDL', '/NJH', '/NJS', '/NP'], stdout=sp.PIPE)
        if cp.returncode >= 8:
            raise RuntimeError('app copy failed')
        for d in ('cache', 'session', 'logs', 'uploads', 'debugbar'):
            (APP / 'writable' / d).mkdir(parents=True, exist_ok=True)
        (APP / '.env').write_text('''CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8070/'
database.default.hostname = 127.0.0.1
database.default.port = 3307
database.default.database = rolewarden_test
database.default.DBDriver = MySQLi
logger.threshold = 9
''', encoding='utf-8', newline='\n')
        if mode == 'regression':
            junction(WORK / NEW)
            rc1 = stream([GIT_BASH, (HERE / 'verify-v1.sh').as_posix(), APP.as_posix()], 'verify-v2.regression-v1.output.txt')
            for envname in ('development', 'production'):
                src = HERE / ('verify-v1.server-' + envname + '.log')
                if src.exists():
                    shutil.move(src, HERE / ('verify-v2.regression-v1.server-' + envname + '.log'))
            # verify-v1.sh leaves the copy on production; the V1M1 script expects development.
            env_file = APP / '.env'
            env_file.write_text(env_file.read_text(encoding='utf-8').replace('CI_ENVIRONMENT = production', 'CI_ENVIRONMENT = development'), encoding='utf-8', newline='\n')
            for f in (APP / 'writable' / 'cache').glob('*'):
                if f.is_file() and f.name != 'index.html':
                    f.unlink()
            regression = WORK / 'regression'
            regression.mkdir()
            for name in ('verify-v1-m1.php', 'verify-v1.lib.php', 'verify-v1.fixtures.php'):
                shutil.copyfile(HERE / name, regression / name)
            rc2 = stream(['php', '-d', 'error_reporting=-1', regression / 'verify-v1-m1.php', APP.as_posix(), (WORK / M1_OLD).as_posix(), (WORK / NEW).as_posix()], 'verify-v2.regression-m1.output.txt')
            if (regression / 'verify-v1-m1.server.log').exists():
                shutil.copyfile(regression / 'verify-v1-m1.server.log', HERE / 'verify-v2.regression-m1.server.log')
            print('regression exit codes v1.sh=%s m1=%s' % (rc1, rc2), flush=True)
        else:
            log = HERE / ('verify-v2.server.log' if mode == 'test' else 'verify-v2.' + mode + '.server.log')
            if log.exists():
                log.unlink()
            output = 'verify-v2.output.txt' if mode == 'test' else 'verify-v2.' + mode + '.output.txt'
            rc = stream(['php', '-d', 'error_reporting=-1', HERE / 'verify-v2.php', APP, WORK / BASE_V1, WORK / NEW, mode], output)
            if rc != 0:
                print('V2 test process exit code', rc, flush=True)
    finally:
        run(['php', HERE / 'verify-v2.snapshot.php', 'restore', WORK / 'snapshot.sql'], stdout=sp.PIPE, stderr=sp.PIPE)
        after = dump()
        same = before == after
        print('Restored SHA256:', hashlib.sha256(after).hexdigest(), 'IDENTICAL=', same, flush=True)
        with (HERE / 'verify-v2.environment.txt').open('a', encoding='utf-8') as log:
            log.write('Collaudatore ad Hoc, mode ' + mode + ': restored SHA256 ' + hashlib.sha256(after).hexdigest() + ' identical=' + str(same) + '\n')
        if not same:
            raise RuntimeError('Snapshot mismatch: preserved work directory')
        # Only our resolved workspace subtree. Junction is removed separately, never traversed.
        assert WORK.resolve().parent == HERE
        link = APP / 'vendor' / 'rolewarden' / 'codeigniter4-rolewarden'
        if link.is_junction() or link.is_symlink():
            os.rmdir(link)
        shutil.rmtree(WORK)


if __name__ == '__main__':
    main()
