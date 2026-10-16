"""S1 copy of verify-v8b.suite7.py (outputs verify-s1.v7.*, evidence in verify-s1.environment.txt). The zips of build/ are
still rc.v8c (b1cca14): this copy builds stand-ins at RW_V8_NEW in the work folder (RW_S1_WORK7): the rc.v8c application zip
with modules/rolewarden and modules/rolewarden-demo replaced by git archive RW_V8_NEW and RW_V8_NEW:demo and its CHANGELOG
from RW_V8_NEW; the module zip replaced by git archive RW_V8_NEW (V8 P01 proved module zip == git archive). V8b header:
V8b copy of verify-v8.suite7.py (outputs verify-v8b.v7.*, evidence in verify-v8b.environment.txt). V8 copy of verify-v7.suite.py: the V7 suite (verify-v7.php, unchanged) on the rc.v8 zips and f06a29d. The module given to B06 carries demo/ beside it, because the test application now wires the demo. Outputs verify-v8.v7.*. V7 header follows:
V7 main suite runner (Collaudatore ad Hoc). Only rolewarden_test, 127.0.0.1:3317.
Usage: python tests/Integration/verify-v7.suite.py <label>
Extracts the two zips of build/ and the module at RW_V7_NEW (default 2314cfb; first round 2836697) (git archive) into a throwaway work folder,
runs verify-v7.php, then restores rolewarden_test exactly (hash of the canonical dump compared), empties
Mailpit and deletes the work folder (junctions first). Credentials only from RW_DB_USERNAME/RW_DB_PASSWORD.
"""
import hashlib
import os
from pathlib import Path
import shutil
import subprocess as sp
import sys
import tarfile
import urllib.request
import zipfile

sys.stdout.reconfigure(encoding='utf-8', errors='replace')

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
WORK = Path(os.environ['RW_S1_WORK7'])
NEW = os.environ.get('RW_V8_NEW', 'f06a29d')
TAG = os.environ.get('RW_V8_TAG', 'rc.v8')
ENV = os.environ.copy()
for key in ('RW_DB_USERNAME', 'RW_DB_PASSWORD'):
    if not ENV.get(key):
        raise SystemExit('Missing ' + key)


def dump():
    return sp.run(['php', HERE / 'verify-v4.snapshot.php'], env=ENV, check=True, stdout=sp.PIPE).stdout


def unjunction(top):
    for dirpath, dirnames, _ in os.walk(top):
        for d in list(dirnames):
            p = Path(dirpath) / d
            if p.is_junction() or p.is_symlink():
                os.rmdir(p)
                dirnames.remove(d)


def main():
    label = sys.argv[1] if len(sys.argv) > 1 else 'run'
    if WORK.exists():
        raise SystemExit('ABORT: existing work directory')
    before = dump()
    print('Snapshot SHA256:', hashlib.sha256(before).hexdigest(), flush=True)
    WORK.mkdir()
    (WORK / 'snapshot.sql').write_bytes(before)
    code = 1
    try:
        for name in ('rolewarden-app-1.0.0-' + TAG + '.zip', 'rolewarden-module-1.0.0-' + TAG + '.zip'):
            with zipfile.ZipFile(ROOT / 'build' / name) as z:
                z.extractall(WORK / ('zip-' + name.split('-')[1]))
        sp.run(['git', 'archive', '--format=tar', '-o', WORK / 'mod.tar', NEW], cwd=ROOT, check=True)
        with tarfile.open(WORK / 'mod.tar') as tf:
            tf.extractall(WORK / 'mod', filter='data')
        sp.run(['git', 'archive', '--format=tar', '-o', WORK / 'demo.tar', NEW + ':demo'], cwd=ROOT, check=True)
        with tarfile.open(WORK / 'demo.tar') as tf:
            tf.extractall(WORK / 'mod' / 'demo', filter='data')
        appzip = WORK / 'zip-app' / ('rolewarden-app-1.0.0-' + TAG)
        modzip = WORK / 'zip-module' / 'rolewarden'
        # stand-ins at NEW
        for d in (appzip / 'modules' / 'rolewarden', appzip / 'modules' / 'rolewarden-demo', modzip):
            shutil.rmtree(d)
        for t, d in (('mod.tar', appzip / 'modules' / 'rolewarden'), ('demo.tar', appzip / 'modules' / 'rolewarden-demo'), ('mod.tar', modzip)):
            with tarfile.open(WORK / t) as tf:
                tf.extractall(d, filter='data')
        shutil.copyfile(modzip / 'CHANGELOG.md', appzip / 'CHANGELOG.md')
        print('[S1] stand-in zips at', NEW, 'built from the', TAG, 'application zip', flush=True)
        ENV.update(TEMP=str(WORK), TMP=str(WORK))
        out = HERE / ('verify-s1.v7.' + label + '.output.txt')
        with out.open('w', encoding='utf-8') as log:
            p = sp.Popen(['php', '-d', 'error_reporting=-1', str(HERE / os.environ.get('RW_V8B_V7SCRIPT', 'verify-v7.php')), WORK.as_posix(), appzip.as_posix(), modzip.as_posix(), (WORK / 'mod').as_posix(), (ROOT.parent / 'rolewarden-app-test').as_posix()],
                         env=ENV, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
            for line in p.stdout:
                log.write(line)
                log.flush()
                print(line, end='', flush=True)
            code = p.wait()
            log.write('Test process exit: %d\n' % code)
    finally:
        for f in WORK.glob('verify-v7.applog-*.log'):
            shutil.copyfile(f, HERE / ('verify-s1.v7.' + label + '.' + f.name.replace('verify-v7.', '')))
        for f in list(WORK.glob('server-*.log')) + list(WORK.glob('page-*.html')):
            shutil.copyfile(f, HERE / ('verify-s1.v7.' + label + '.' + f.name))
        try:
            urllib.request.urlopen(urllib.request.Request('http://localhost:8026/api/v1/messages', method='DELETE'), timeout=10).read()
        except Exception as e:
            print('Mailpit clear failed:', e)
        sp.run(['php', HERE / 'verify-v4.snapshot.php', 'restore', WORK / 'snapshot.sql'], env=ENV, check=True)
        after = dump()
        with urllib.request.urlopen('http://localhost:8026/api/v1/messages', timeout=10) as r:
            mp = r.read().decode()
        ev = '%s %s: before=%s after=%s identical=%s mailpit=%s' % (NEW, label, hashlib.sha256(before).hexdigest(), hashlib.sha256(after).hexdigest(), before == after, '"messages_count":0' in mp)
        print(ev, flush=True)
        with (HERE / 'verify-s1.environment.txt').open('a', encoding='utf-8') as f:
            f.write(ev + '\n')
        if before != after:
            raise SystemExit('Snapshot mismatch: work directory preserved')
        unjunction(WORK)
        for d, _, files in os.walk(WORK):
            for fn in files:
                os.chmod(Path(d) / fn, 0o666)
        shutil.rmtree(WORK)
    return code


if __name__ == '__main__':
    sys.exit(main())
