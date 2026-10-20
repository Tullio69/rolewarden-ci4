"""U3 copy of verify-u2.suite.py (outputs verify-u3.*, harness verify-u3.harness.php, evidence verify-u3.environment.txt). U2 copy of verify-u1.suite.py (outputs verify-u3.*, harness verify-u3.harness.php, evidence verify-u3.environment.txt). U1 copy of verify-s2.suite.py (outputs verify-u1.*, suite s1 runs verify-u1.harness.php, v8 gets build/rolewarden-app-1.0.0.zip). S2 copy of verify-s1.run.py (outputs verify-u1.*, suite s1 runs verify-u1.s1.php; v8zip on the rc.s2 zips). S1 header: S1 runner (Collaudatore ad Hoc), copy of verify-v8b.run.py: suites s1 (verify-s1.php), v8b (verify-v8b.php), v8 (verify-v8.php)
on RW_V8_NEW; for s1 it also extracts the baseline RW_S1_BASE (default b1cca14, module with demo) into <work>/basefull.
The v8 suite gets the application zip of RW_V8_TAG (default rc.v8c). Outputs verify-s1.*. V8b header follows:
V8b runner (Collaudatore ad Hoc). Only rolewarden_test, 127.0.0.1:3317.
Usage: python tests/Integration/verify-v8b.run.py v8b <label> [phases]   -> verify-v8b.php (the changes after V8)
       python tests/Integration/verify-v8b.run.py v8  <label> [phases]   -> verify-v8.php (V8 suite, regression)
Builds a throwaway work folder RW_V8B_WORK (session scratchpad): the module at RW_V8_NEW (git archive, which leaves
demo/ out by export-ignore), demo/ (git archive of the subtree) and the module with the demo beside it. For the V8
suite the module and demo "zips" are those same git archives (P01/P02 of V8 proved zip == git archive); the
application zip is the old rc.v8b one and is only used by phases P and E, which this regression leaves out because
build/ was not rebuilt after be64b53. Then empties Mailpit, restores rolewarden_test exactly (hash of the canonical
dump compared), deletes every .env and the folder (junctions first). Credentials only from RW_DB_USERNAME /
RW_DB_PASSWORD, never written.
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
NEW = os.environ.get('RW_V8_NEW', '6d21875')
WORK = Path(os.environ['RW_V8B_WORK'])
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
    suite = sys.argv[1]
    label = sys.argv[2] if len(sys.argv) > 2 else 'run'
    phases = sys.argv[3] if len(sys.argv) > 3 else ('A,B,C,D,F,G' if suite == 'v8' else ('N,Y,T,D,G' if suite == 's1' else 'L,M,P,Y,I,G'))
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
        if suite == 's1':
            base = os.environ.get('RW_S1_BASE', 'b1cca14')
            sp.run(['git', 'archive', '--format=tar', '-o', WORK / 'base.tar', base], cwd=ROOT, check=True)
            sp.run(['git', 'archive', '--format=tar', '-o', WORK / 'basedemo.tar', base + ':demo'], cwd=ROOT, check=True)
            for t, d in (('base.tar', 'basefull'), ('basedemo.tar', 'basefull/demo')):
                with tarfile.open(WORK / t) as tf:
                    tf.extractall(WORK / d, filter='data')
        ENV.update(TEMP=str(WORK), TMP=str(WORK))
        if suite == 'v8zip':
            # the three real zips of build/ (RW_V8_TAG), extracted as verify-v8.run.py does
            for z in ('app', 'module', 'demo'):
                with zipfile.ZipFile(ROOT / 'build' / ('rolewarden-%s-1.0.0-%s.zip' % (z, os.environ['RW_V8_TAG']))) as zf:
                    zf.extractall(WORK / 'zips' / z)
        if suite == 'v8':
            for t, d in (('mod.tar', 'zips/module'), ('demo.tar', 'zips/demo')):
                with tarfile.open(WORK / t) as tf:
                    tf.extractall(WORK / d, filter='data')
            with zipfile.ZipFile(ROOT / 'build' / ('rolewarden-app-1.0.0.zip')) as zf:
                zf.extractall(WORK / 'zips' / 'app')
        if suite in ('v8', 'v8zip'):
            out = HERE / ('verify-u3.' + suite + '.' + label + '.output.txt')
            args = ['php', '-d', 'error_reporting=-1', '-d', 'memory_limit=1G', str(HERE / os.environ.get('RW_S2_V8SCRIPT', 'verify-v8.php')), WORK.as_posix(), (WORK / 'mod').as_posix(), (WORK / 'modfull').as_posix(), (WORK / 'demo').as_posix(),
                    (WORK / 'zips/app').as_posix(), (WORK / 'zips/module').as_posix(), (WORK / 'zips/demo').as_posix(), (ROOT.parent / 'rolewarden-app-test').as_posix(), phases]
        else:
            out = HERE / ('verify-u3.' + suite + '.' + label + '.output.txt')
            args = ['php', '-d', 'error_reporting=-1', '-d', 'memory_limit=1G', str(HERE / ('verify-u3.harness.php' if suite == 's1' else 'verify-v8b.php')), WORK.as_posix(), (WORK / 'mod').as_posix(), (WORK / 'modfull').as_posix(), (ROOT.parent / 'rolewarden-app-test').as_posix(), phases, label]
        with out.open('w', encoding='utf-8') as log:
            p = sp.Popen(args, env=ENV, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
            for line in p.stdout:
                log.write(line)
                log.flush()
                print(line, end='', flush=True)
            code = p.wait()
            log.write('Test process exit: %d\n' % code)
    finally:
        pre = 'verify-u3.' + suite + '.' + label + '.'
        for f in list(WORK.glob('verify-*.applog-*.log')) + list(WORK.glob('server-*.log')):
            shutil.copyfile(f, HERE / (pre + f.name.replace('verify-v8b.', '').replace('verify-v8.', '').replace('verify-s1.', '')))
        try:
            mailpit('DELETE')
        except Exception as e:
            print('Mailpit clear failed:', e)
        sp.run(['php', HERE / 'verify-v4.snapshot.php', 'restore', WORK / 'snapshot.sql'], env=ENV, check=True)
        after = dump()
        ev = '%s %s.%s: before=%s after=%s identical=%s mailpit_empty=%s' % (NEW, suite, label, hashlib.sha256(before).hexdigest(), hashlib.sha256(after).hexdigest(), before == after, '"messages_count":0' in mailpit('GET'))
        print(ev, flush=True)
        with (HERE / 'verify-u3.environment.txt').open('a', encoding='utf-8') as f:
            f.write(ev + '\n')
        if before != after:
            raise SystemExit('Snapshot mismatch: work directory preserved')
        unjunction(WORK)
        for d, _, files in os.walk(WORK):
            for fn in files:
                try:
                    os.chmod(Path(d) / fn, 0o666)
                except OSError:
                    pass
        envs = [p for p in WORK.rglob('.env')]
        for p in envs:
            p.unlink()
        shutil.rmtree(WORK, ignore_errors=True)
        print('Work folder removed=%s (%d .env files deleted first)' % (not WORK.exists(), len(envs)), flush=True)
    return code


if __name__ == '__main__':
    sys.exit(main())
