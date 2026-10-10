"""V8 main suite runner (Collaudatore ad Hoc). Only rolewarden_test, 127.0.0.1:3317.
Usage: python tests/Integration/verify-v8.run.py <label> [phases]
Builds a throwaway work folder in the session scratchpad (RW_V8_WORK): the module at f06a29d (git archive,
which leaves demo/ out by export-ignore), demo/ at f06a29d (git archive of the subtree), the module with the
demo beside it, and the three zips of build/ extracted. Runs verify-v8.php, then empties Mailpit, restores
rolewarden_test exactly (hash of the canonical dump compared), deletes every .env of the work folder and the
folder itself (junctions first). Credentials only from RW_DB_USERNAME / RW_DB_PASSWORD, never written.
"""
import hashlib
import os
from pathlib import Path
import shutil
import socket
import subprocess as sp
import sys
import tarfile
import urllib.request
import zipfile

sys.stdout.reconfigure(encoding='utf-8', errors='replace')
HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
NEW = os.environ.get('RW_V8_NEW', 'f06a29d')
TAG = os.environ.get('RW_V8_TAG', 'rc.v8')
WORK = Path(os.environ['RW_V8_WORK'])
ENV = os.environ.copy()
for key in ('RW_DB_USERNAME', 'RW_DB_PASSWORD'):
    if not ENV.get(key):
        raise SystemExit('Missing ' + key)


def dump():
    return sp.run(['php', HERE / 'verify-v4.snapshot.php'], env=ENV, check=True, stdout=sp.PIPE).stdout


def mailpit(method, path='messages'):
    with urllib.request.urlopen(urllib.request.Request('http://localhost:8026/api/v1/' + path, method=method), timeout=10) as r:
        return r.read().decode()


def unjunction(top):
    for dirpath, dirnames, _ in os.walk(top):
        for d in list(dirnames):
            p = Path(dirpath) / d
            if p.is_junction() or p.is_symlink():
                os.rmdir(p)
                dirnames.remove(d)


def main():
    label = sys.argv[1] if len(sys.argv) > 1 else 'run'
    phases = sys.argv[2] if len(sys.argv) > 2 else 'P,A,B,C,D,E,F,G'
    for host in ('127.0.0.1', '::1'):
        try:
            with socket.create_connection((host, 8071), timeout=1):
                raise SystemExit('ABORT: port 8071 occupied')
        except OSError:
            pass
    if WORK.exists():
        raise SystemExit('ABORT: existing work directory')
    if '"messages_count":0' not in mailpit('GET'):
        raise SystemExit('ABORT: Mailpit not empty')
    before = dump()
    print('Snapshot SHA256:', hashlib.sha256(before).hexdigest(), flush=True)
    WORK.mkdir(parents=True)
    (WORK / 'snapshot.sql').write_bytes(before)
    code = 1
    try:
        sp.run(['git', 'archive', '--format=tar', '-o', WORK / 'mod.tar', NEW], cwd=ROOT, check=True)
        sp.run(['git', 'archive', '--format=tar', '-o', WORK / 'demo.tar', NEW + ':demo'], cwd=ROOT, check=True)
        for t, d in (('mod.tar', 'mod'), ('mod.tar', 'modfull'), ('demo.tar', 'demo'), ('demo.tar', 'modfull/demo')):
            with tarfile.open(WORK / t) as tf:
                tf.extractall(WORK / d, filter='data')
        for z in ('app', 'module', 'demo'):
            with zipfile.ZipFile(ROOT / 'build' / ('rolewarden-%s-1.0.0-%s.zip' % (z, TAG))) as zf:
                zf.extractall(WORK / 'zips' / z)
        ENV.update(TEMP=str(WORK), TMP=str(WORK))
        out = HERE / ('verify-v8.' + label + '.output.txt')
        args = ['php', '-d', 'error_reporting=-1', '-d', 'memory_limit=1G', str(HERE / 'verify-v8.php'), WORK.as_posix(), (WORK / 'mod').as_posix(), (WORK / 'modfull').as_posix(), (WORK / 'demo').as_posix(),
                (WORK / 'zips/app').as_posix(), (WORK / 'zips/module').as_posix(), (WORK / 'zips/demo').as_posix(), (ROOT.parent / 'rolewarden-app-test').as_posix(), phases]
        with out.open('w', encoding='utf-8') as log:
            p = sp.Popen(args, env=ENV, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
            for line in p.stdout:
                log.write(line)
                log.flush()
                print(line, end='', flush=True)
            code = p.wait()
            log.write('Test process exit: %d\n' % code)
    finally:
        for f in list(WORK.glob('verify-v8.applog-*.log')) + list(WORK.glob('server-*.log')):
            shutil.copyfile(f, HERE / ('verify-v8.' + label + '.' + f.name.replace('verify-v8.', '')))
        try:
            mailpit('DELETE')
        except Exception as e:
            print('Mailpit clear failed:', e)
        sp.run(['php', HERE / 'verify-v4.snapshot.php', 'restore', WORK / 'snapshot.sql'], env=ENV, check=True)
        after = dump()
        ev = '%s %s: before=%s after=%s identical=%s mailpit_empty=%s' % (NEW, label, hashlib.sha256(before).hexdigest(), hashlib.sha256(after).hexdigest(), before == after, '"messages_count":0' in mailpit('GET'))
        print(ev, flush=True)
        with (HERE / 'verify-v8.environment.txt').open('a', encoding='utf-8') as f:
            f.write(ev + '\n')
        if before != after:
            raise SystemExit('Snapshot mismatch: work directory preserved')
        unjunction(WORK)
        for d, _, files in os.walk(WORK):
            for fn in files:
                os.chmod(Path(d) / fn, 0o666)
        envs = [p for p in WORK.rglob('.env')]
        for p in envs:
            p.unlink()
        shutil.rmtree(WORK)
        print('Work folder removed (%d .env files deleted first)' % len(envs), flush=True)
    return code


if __name__ == '__main__':
    sys.exit(main())
