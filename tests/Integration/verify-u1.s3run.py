"""U1 runner (copy of verify-s3.run.py) (Collaudatore ad Hoc): one Docker scenario at a time for verify-u1.s3.php, on the
1.0.0 complete application zip; container, network and image named u1-*, port 8092; the module archive of RW_U1_COMMIT
(git archive) copied into the container as /tmp/mod-u1.tar (up83: swapped in by phase UPG; new83: swapped in before the installer).
S3 header: S3 runner (copy of verify-s2.run.py, final 1.0.0 zips): one Docker scenario at a time for verify-s3.php.
Usage: python verify-s3.run.py <scenario>      scenarios: see SCEN below
Needs RW_DB_USERNAME / RW_DB_PASSWORD in the process environment (never written) and RW_S3_WORK (scratchpad folder).
Before: Mailpit empty, snapshot of rolewarden_test (canonical dump of verify-v4.snapshot.php).
After, always: containers of the scenario removed (only s3-* ones it created), Mailpit emptied, rolewarden_test
restored exactly and compared by hash, line in verify-s3.environment.txt.
"""
import hashlib
import os
import subprocess as sp
import sys
import time
import urllib.request
from pathlib import Path

sys.stdout.reconfigure(encoding='utf-8', errors='replace')
HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
DOCK = HERE / 'verify-s3.docker'
WORK = Path(os.environ['RW_U1_S3WORK'])
ENV = os.environ.copy()
for key in ('RW_DB_USERNAME', 'RW_DB_PASSWORD'):
    if not ENV.get(key):
        raise SystemExit('Missing ' + key)
TAG = '1.0.0'
NET = 'u1-net'
PROXY_IP = '172.30.5.10'

ROOTB = 'http://localhost:8092/'
SUBB = 'http://localhost:8091/shop/admin/'
HTTPSB = 'https://localhost:8443/'
SCEN = {
    # 1.0.0 installed by its installer, then the module folder swapped for RW_U1_COMMIT and migrated (UPG), rolled back and
    # migrated again (RB), the 1.0 layout override (OV), then the S3 phases of the panel in use with that override in place
    'up83': dict(php='8.3', conf='root', base=ROOTB, env='development', phases='I,UPG,RB,OV,U,E,D,S,T,K,L'),
    # a new installation: the module folder swapped for RW_U1_COMMIT before the installer runs
    'new83': dict(php='8.3', conf='root', base=ROOTB, env='development', phases='I,NEW,U,E,D,S,T,K,L', premod=True),
}


def run(args, check=True, quiet=False, inp=None, env=None):
    r = sp.run(args, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace', input=inp, env=env or ENV)
    if not quiet:
        print('$ ' + ' '.join(str(a) for a in args)[:200] + '  -> rc=%d %s' % (r.returncode, r.stdout.strip()[:300].replace(ENV['RW_DB_PASSWORD'], '[omesso]') if r.returncode else ''), flush=True)
    if check and r.returncode:
        raise SystemExit('command failed')
    return r


def dump():
    return sp.run(['php', HERE / 'verify-v4.snapshot.php'], env=ENV, check=True, stdout=sp.PIPE).stdout


def mailpit(method, path='messages'):
    with urllib.request.urlopen(urllib.request.Request('http://localhost:8026/api/v1/' + path, method=method), timeout=10) as r:
        return r.read().decode()


def wait_http(url, tries=60):
    import ssl
    ctx = ssl._create_unverified_context()
    for _ in range(tries):
        try:
            urllib.request.urlopen(url, timeout=3, context=ctx)
            return True
        except urllib.error.HTTPError:
            return True
        except Exception:
            time.sleep(0.5)
    return False


def main():
    name = sys.argv[1]
    sc = SCEN[name]
    label = sys.argv[2] if len(sys.argv) > 2 else name
    if '"messages_count":0' not in mailpit('GET'):
        raise SystemExit('ABORT: Mailpit not empty')
    left = run(['docker', 'ps', '-a', '--filter', 'name=^u1-', '--format', '{{.Names}}'], quiet=True).stdout.split()
    if left:
        raise SystemExit('ABORT: u1-* containers still there: %s' % left)
    before = dump()
    print('Snapshot SHA256:', hashlib.sha256(before).hexdigest(), flush=True)
    WORK.mkdir(parents=True, exist_ok=True)
    (WORK / ('snapshot-%s.sql' % label)).write_bytes(before)
    mine = []
    code = 1
    try:
        img = 'u1-col-php:' + sc['php']
        mine.append('u1-web')
        run(['docker', 'run', '-d', '--name', 'u1-web', '--network', NET, '-p', '127.0.0.1:8092:80', img])
        modtar = WORK / ('mod-u1-%s.tar' % os.environ['RW_U1_COMMIT'])
        if not modtar.exists():
            sp.run(['git', 'archive', '--format=tar', '-o', str(modtar), os.environ['RW_U1_COMMIT']], cwd=ROOT, check=True)
        run(['docker', 'cp', modtar.as_posix(), 'u1-web:/tmp/mod-u1.tar'])
        app_tar = WORK / ('app-%s.tar' % TAG)
        if sc.get('module'):
            run(['docker', 'cp', (ROOT / 'build' / 'rolewarden-module-1.0.0.zip').as_posix(), 'u1-web:/tmp/module.zip'])
            for f in ('module-app.sh', 'module-wire.php'):
                run(['docker', 'cp', (DOCK / f).as_posix(), 'u1-web:/tmp/' + f])
            run(['docker', 'cp', (DOCK / 'root.conf').as_posix(), 'u1-web:/etc/apache2/sites-available/000-default.conf'])
            r = run(['docker', 'exec', '-e', 'COMPOSER_ALLOW_SUPERUSER=1', '-e', 'DBH=host.docker.internal', '-e', 'DBP=3317', '-e', 'DBN=rolewarden_test', '-e', 'DBU=' + ENV['RW_DB_USERNAME'],
                     '-e', 'DBW=' + ENV['RW_DB_PASSWORD'], '-e', 'OPW=Quartz-Lantern-9183', 'u1-web', 'sh', '/tmp/module-app.sh', sc['module'], '/tmp/module.zip', sc['how']], check=False, quiet=True)
            mo = r.stdout.replace(ENV['RW_DB_PASSWORD'], '[omesso]')
            tail = run(['docker', 'exec', 'u1-web', 'tail', '-n', '25', '/tmp/s3-composer.log'], check=False, quiet=True).stdout
            (HERE / ('verify-u1.s3.%s.setup.txt' % label)).write_text(mo + '\n--- composer log tail\n' + tail, encoding='utf-8')
            print(mo[-1500:], flush=True)
            if r.returncode:
                raise SystemExit('module application setup failed')
            run(['docker', 'exec', 'u1-web', 'sh', '-c', 'chown -R www-data:www-data /var/www/app && apache2ctl -k graceful'])
        elif not app_tar.exists():
            import zipfile
            if not (WORK / 'zips/app/rolewarden-app-1.0.0').exists():
                with zipfile.ZipFile(ROOT / 'build' / 'rolewarden-app-1.0.0.zip') as zf:
                    zf.extractall(WORK / 'zips' / 'app')
            run(['tar', '-cf', app_tar.as_posix(), '-C', (WORK / 'zips/app/rolewarden-app-1.0.0').as_posix(), '.'])
        if not sc.get('module'):
            run(['docker', 'cp', app_tar.as_posix(), 'u1-web:/tmp/app.tar'])
            run(['docker', 'cp', (DOCK / (sc['conf'] + '.conf')).as_posix(), 'u1-web:/etc/apache2/sites-available/000-default.conf'])
        setup = 'mkdir -p /var/www/app && tar -xf /tmp/app.tar -C /var/www/app && rm /tmp/app.tar'
        if sc['conf'] == 'sub':
            setup += ' && mkdir -p /var/www/html/shop && ln -s /var/www/app/public /var/www/html/shop/admin'
        if sc['env'] == 'development':
            # an existing CI_ENVIRONMENT is kept by the installer (installation.html#installer)
            setup += " && printf 'CI_ENVIRONMENT = development\\n' > /var/www/app/.env"
        if sc.get('proxyips'):
            # installation.html#proxy: list the proxy in app/Config/App.php
            setup += " && sed -i \"s#public array \\$proxyIPs = \\[\\];#public array \\$proxyIPs = ['%s' => 'X-Forwarded-For'];#\" /var/www/app/app/Config/App.php && grep -n 'proxyIPs = ' /var/www/app/app/Config/App.php" % PROXY_IP
        if sc.get('premod'):
            setup += ' && rm -rf /var/www/app/modules/rolewarden && mkdir /var/www/app/modules/rolewarden && tar -xf /tmp/mod-u1.tar -C /var/www/app/modules/rolewarden'
        setup += ' && chown -R www-data:www-data /var/www/app && apache2ctl -k graceful'
        if not sc.get('module'):
            print(run(['docker', 'exec', 'u1-web', 'sh', '-c', setup]).stdout.strip()[-300:], flush=True)
        extra = []
        if sc.get('proxy'):
            mine.append('s3-proxy')
            cert = WORK / 'cert.pem'
            if not cert.exists():
                run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '2', '-subj', '/CN=localhost', '-addext', 'subjectAltName=DNS:localhost',
                     '-keyout', (WORK / 'key.pem').as_posix(), '-out', cert.as_posix()], quiet=True)
            run(['docker', 'run', '-d', '--name', 's3-proxy', '--network', NET, '--ip', PROXY_IP, '-p', '127.0.0.1:8443:443', 'nginx:alpine'])
            run(['docker', 'exec', 's3-proxy', 'mkdir', '-p', '/etc/nginx/s3'])
            for f in ('cert.pem', 'key.pem'):
                run(['docker', 'cp', (WORK / f).as_posix(), 's3-proxy:/etc/nginx/s3/' + f])
            run(['docker', 'cp', (DOCK / sc.get('nginx', 'nginx.conf')).as_posix(), 's3-proxy:/etc/nginx/conf.d/default.conf'])
            run(['docker', 'exec', 's3-proxy', 'nginx', '-s', 'reload'])
            extra += ['-d', 'curl.cainfo=' + cert.as_posix()]
        dbargs = []
        if sc.get('owndb'):
            mine.append('s3-db')
            run(['docker', 'run', '-d', '--name', 's3-db', '--network', NET, '-e', 'MARIADB_ROOT_PASSWORD=' + ENV['RW_DB_PASSWORD'], '-e', 'MARIADB_DATABASE=s3z', 'mariadb:11'], quiet=True)
            for _ in range(90):
                if run(['docker', 'exec', 's3-db', 'mariadb-admin', 'ping', '-uroot', '-p' + ENV['RW_DB_PASSWORD'], '--silent'], check=False, quiet=True).returncode == 0:
                    break
                time.sleep(1)
            time.sleep(3)
            dbargs = ['dbhost=s3-db', 'dbport=3306', 'dbname=s3z', 'dbct=s3-db', 'mailhost=host.docker.internal']
        wait_http(sc['base'] + 'rolewarden/install')
        args = ['php', '-d', 'error_reporting=-1', '-d', 'memory_limit=1G'] + extra + [str(HERE / 'verify-u1.s3.php'), 'label=' + label, 'base=' + sc['base'], 'ct=u1-web',
                'phases=' + sc['phases'], 'env=' + sc['env'], 'showcase=' + sc.get('showcase', '0'), 'direct=' + sc.get('direct', '')] + dbargs
        if sc.get('module'):
            args += ['mode=module', 'install=' + ('browser' if sc['how'] == 'installer' else 'spark')]
            if sc['how'] == 'installer':
                args[args.index('env=' + sc['env'])] = 'env=production'
                args.append('todev=1')
        for k in ('expectbase', 'submitbase'):
            if k in sc:
                args.append(k + '=' + sc[k])
        if 'rate' in sc:
            args.append('rate=' + sc['rate'])
        if sc.get('proxy'):
            args.append('extraenv=' + ('\ncookie.secure = true' if sc.get('proxyips') else ''))
        elif sc.get('extraenv'):
            args.append('extraenv=' + sc['extraenv'])
        out = HERE / ('verify-u1.s3.%s.output.txt' % label)
        e2 = ENV.copy()
        e2.update(TEMP=str(WORK), TMP=str(WORK))
        with out.open('w', encoding='utf-8') as log:
            log.write('S3 scenario %s: %s\n' % (name, {k: v for k, v in sc.items()}))
            p = sp.Popen(args, env=e2, stdout=sp.PIPE, stderr=sp.STDOUT, text=True, encoding='utf-8', errors='replace')
            for line in p.stdout:
                log.write(line)
                log.flush()
                print(line, end='', flush=True)
            code = p.wait()
            log.write('Test process exit: %d\n' % code)
    finally:
        try:
            r = run(['docker', 'logs', 'u1-web'], check=False, quiet=True)
            (HERE / ('verify-u1.s3.%s.container.log' % label)).write_text(r.stdout.replace(ENV['RW_DB_PASSWORD'], '[omesso]'), encoding='utf-8')
        except Exception as e:
            print('container log copy failed', e)
        for ct in mine:
            run(['docker', 'rm', '-f', '-v', ct], check=False, quiet=True)
        try:
            mailpit('DELETE')
        except Exception as e:
            print('Mailpit clear failed:', e)
        sp.run(['php', HERE / 'verify-v4.snapshot.php', 'restore', WORK / ('snapshot-%s.sql' % label)], env=ENV, check=True)
        after = dump()
        ev = os.environ['RW_U1_COMMIT'] + ' u1.s3.%s.%s: before=%s after=%s identical=%s mailpit_empty=%s containers_removed=%s' % (
            name, label, hashlib.sha256(before).hexdigest(), hashlib.sha256(after).hexdigest(), before == after, '"messages_count":0' in mailpit('GET'),
            not run(['docker', 'ps', '-a', '--filter', 'name=^u1-', '--format', '{{.Names}}'], quiet=True).stdout.strip())
        print(ev, flush=True)
        with (HERE / 'verify-u1.environment.txt').open('a', encoding='utf-8') as f:
            f.write(ev + '\n')
        if before != after:
            raise SystemExit('Snapshot mismatch')
    return code


if __name__ == '__main__':
    sys.exit(main())
