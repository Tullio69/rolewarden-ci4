"""V7 package inspection (Collaudatore ad Hoc): the two zips of build/ opened as a buyer would.
Usage: python tests/Integration/verify-v7.package.py  -> prints [PASS]/[FAIL]/[INFO] lines.
No database, no server. Extracts into a throwaway folder next to this file and deletes it.
"""
import hashlib
import os
from pathlib import Path
import re
import shutil
import subprocess as sp
import sys
import zipfile

sys.stdout.reconfigure(encoding='utf-8', errors='replace')
HERE = Path(__file__).resolve().parent
ROOT = HERE.parent.parent
WORK = HERE / 'verify-v7.package.work'
APPZIP = ROOT / 'build' / 'rolewarden-app-1.0.0-rc.v7.zip'
MODZIP = ROOT / 'build' / 'rolewarden-module-1.0.0-rc.v7.zip'
res = []


def check(i, label, ok, detail=''):
    res.append(ok)
    print('[%s] %-5s %s%s' % ('PASS' if ok else 'FAIL', i, label, '' if ok else '\n         -> ' + str(detail)[:1500]))


def files(top):
    return sorted(p.relative_to(top).as_posix() for p in top.rglob('*') if p.is_file())


def main():
    if WORK.exists():
        raise SystemExit('ABORT: work folder exists')
    WORK.mkdir()
    try:
        for z in (APPZIP, MODZIP):
            print('[INFO] %s %d bytes sha256 %s' % (z.name, z.stat().st_size, hashlib.sha256(z.read_bytes()).hexdigest()))
        zipfile.ZipFile(APPZIP).extractall(WORK / 'app')
        zipfile.ZipFile(MODZIP).extractall(WORK / 'mod')
        app = WORK / 'app' / 'rolewarden-app-1.0.0-rc.v7'
        mod = WORK / 'mod' / 'rolewarden'
        fa, fm = files(app), files(mod)
        print('[INFO] application zip: %d files; module zip: %d files' % (len(fa), len(fm)))
        # 1. secrets, environment, runtime files
        bad = [f for f in fa + ['mod/' + f for f in fm] if re.search(r'(^|/)\.env($|\.)|\.log$|(^|/)ci_session|/writable/(cache|session|logs|debugbar|uploads)/(?!index\.html$|\.htaccess$)|\.sql$|\.pem$|\.key$|(^|/)\.git/|install-code\.txt|installed\.lock|theme\.json$', f)]
        bad = [f for f in bad if not f.endswith('.env.example')]
        check('P01', 'No .env, log, session, cache, lock, setup code, SQL dump or key file in either zip', bad == [], bad)
        wr = [f for f in fa if f.startswith('writable/')]
        check('P02', 'writable/ of the application holds only the starter placeholders', all(re.match(r'writable/([a-z]+/)?(index\.html|\.htaccess)$', f) for f in wr), wr)
        pat = re.compile(r'fabio|gulotta|tullio|progetti_lavoro|[A-Z]:[\\/]Users[\\/]|E:[\\/]|rolewarden_test|3317|8026|1026|scolibro|qrart|rolewarden-app-test|_AI-LOG|password `rolewarden`|hex2bin:[0-9a-f]{64}|base64:[A-Za-z0-9+/=]{40}', re.I)
        hits = {}
        for top, prefix in ((app, ''), (mod, 'mod/')):
            for f in files(top):
                try:
                    t = (top / f).read_text(encoding='utf-8', errors='ignore')
                except OSError:
                    continue
                for m in pat.finditer(t):
                    line = t[:m.start()].count('\n') + 1
                    hits.setdefault(prefix + f, []).append('%d:%s' % (line, m.group(0)))
        vendor = {k: v for k, v in hits.items() if '/vendor/' in '/' + k}
        own = {k: v for k, v in hits.items() if k not in vendor}
        print('[INFO] matches inside third-party vendor/ (documentation examples, reviewed by hand): ' + str({k: v[:3] for k, v in vendor.items()}))
        check('P03', 'No credential, key, personal path, local port or development-only reference outside vendor/', own == {}, {k: v[:8] for k, v in own.items()})
        # 2. module zip contents
        top = sorted({f.split('/')[0] for f in fm})
        check('P04', 'Module zip without tests, internal docs and tooling (only README, composer.json, documentation/, src/)', top == ['README.md', 'composer.json', 'documentation', 'src'], top)
        sp.run(['git', 'archive', '--format=zip', '-o', str(WORK / 'ref.zip'), '2836697'], cwd=ROOT, check=True)
        zipfile.ZipFile(WORK / 'ref.zip').extractall(WORK / 'ref')
        check('P05', 'Module zip identical to the module at commit 2836697 (git archive)', files(WORK / 'ref') == fm and all((WORK / 'ref' / f).read_bytes() == (mod / f).read_bytes() for f in fm), set(files(WORK / 'ref')) ^ set(fm))
        appmod = app / 'modules' / 'rolewarden'
        check('P06', 'modules/rolewarden of the application identical to the module zip', files(appmod) == fm and all((appmod / f).read_bytes() == (mod / f).read_bytes() for f in fm))
        # 3. application zip: no Composer needed
        need = ['vendor/autoload.php', 'vendor/codeigniter4/framework/system/CodeIgniter.php', 'vendor/codeigniter4/shield/src/Auth.php', 'vendor/codeigniter4/settings/src/Settings.php', 'vendor/codeigniter4/translations/Language/it/Validation.php', 'public/index.php', 'spark']
        check('P07', 'Application zip includes vendor/ (CodeIgniter, Shield, Settings, translations): no Composer needed', all((app / n).exists() for n in need), [n for n in need if not (app / n).exists()])
        inst = (app / 'vendor/composer/installed.json').read_text(encoding='utf-8')
        vers = dict(re.findall(r'"name": "([^"]+)",\s*"version": "([^"]+)"', inst))
        print('[INFO] bundled packages: ' + str(vers))
        dev = [n for n in vers if n.startswith(('phpunit/', 'fakerphp/', 'mikey179/', 'friendsofphp/', 'phpstan/'))]
        check('P08', 'No development package bundled in vendor/', dev == [], dev)
        auto = (app / 'app/Config/Autoload.php').read_text(encoding='utf-8')
        check('P09', 'RoleWarden loaded from modules/rolewarden by app/Config/Autoload.php', "'RoleWarden'" in auto and "modules/rolewarden/src" in auto)
        groups = (app / 'app/Config/AuthGroups.php').read_text(encoding='utf-8')
        g = re.search(r'public array \$groups = \[(.*?)\];', groups, re.S).group(1)
        check('P10', "Shield keeps only its default 'user' group (no sample groups or permissions)", re.findall(r"'([a-z-]+)'\s*=>\s*\[", g) == ['user'] and re.search(r'public array \$permissions = \[\s*\];', groups) is not None, g)
        cfgapp = (app / 'app/Config/App.php').read_text(encoding='utf-8')
        check('P11', 'Browser chooses between English and Italian (negotiateLocale, supportedLocales en/it)', 'public bool $negotiateLocale = true;' in cfgapp and "public array $supportedLocales = ['en', 'it'];" in cfgapp)
        filt = (app / 'app/Config/Filters.php').read_text(encoding='utf-8')
        check('P12', 'CSRF enabled globally', re.search(r"\$globals = \[\s*'before' => \[[^\]]*^\s*'csrf',", filt, re.M) is not None)
        readme = (mod / 'README.md').read_text(encoding='utf-8')
        devsec = [s for s in ('## Local development', 'docker compose', 'password `rolewarden`', 'rolewarden-app-test', '_AI-LOG.md', 'Currently building the MVP', 'bin/build-css', 'bin/build-package') if s in readme]
        check('P13', 'README shipped to buyers carries no internal development notes (local stack, its password, the test app, build tooling, work log)', devsec == [], devsec)
        appreadme = (app / 'README.md').read_text(encoding='utf-8')
        print('[INFO] application README first line: ' + appreadme.splitlines()[0])
        lic = [f for f in fa if re.search(r'(^|/)(LICENSE|COPYING)', f, re.I) and f.count('/') <= 3]
        print('[INFO] licence files in the application zip: ' + str(lic[:20]))
        plat = (app / 'vendor/composer/platform_check.php').read_text(encoding='utf-8')
        print('[INFO] vendor/composer/platform_check.php minimum: ' + str(re.findall(r'PHP_VERSION_ID >= (\d+)', plat)) + '; app composer.json php: ' + str(re.findall(r'"php": "([^"]+)"', (app / 'composer.json').read_text(encoding='utf-8'))))
    finally:
        shutil.rmtree(WORK)
    print('TOTAL: %d PASS, %d FAIL' % (sum(res), len(res) - sum(res)))


if __name__ == '__main__':
    main()
