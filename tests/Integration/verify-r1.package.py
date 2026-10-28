"""R1 copy of verify-s2.package.py (Collaudatore ad Hoc): the three 1.1.0-rc.r1 zips built by bin/build-package in a separate
git worktree (RW_R1_BUILD = its build/ folder), checked as a buyer receives them. Changes from S2: version 1.1.0, zips from
RW_R1_BUILD, `.regista.json` among the development files, P06e/P08 read the versions from THIRD-PARTY-LICENSES instead of
hard-coding them, P07 also reports the CHANGELOG heading and the composer.json "version" field, P09 (installed.json matches lock).
S2 header: S2 point 4: the three 1.0.0-rc.s2 zips of build/, checked as a buyer receives them.
Usage: python verify-r1.package.py <commit> <tag>     (default 7e3730a 1.1.0-rc.r1)
Reads only the zips, `git archive <commit>` (with .gitattributes) and the credentials of the process
environment (RW_DB_PASSWORD, compared, never printed). Output on stdout.
"""
import hashlib
import io
import json
import os
import re
import subprocess as sp
import sys
import tarfile
import zipfile
from pathlib import Path

sys.stdout.reconfigure(encoding='utf-8', errors='replace')
ROOT = Path(__file__).resolve().parents[2]
COMMIT = sys.argv[1] if len(sys.argv) > 1 else '7e3730a'
TAG = sys.argv[2] if len(sys.argv) > 2 else '1.1.0-rc.r1'
BUILD = Path(os.environ['RW_R1_BUILD'])
RES = []


def check(cid, label, ok, detail=''):
    RES.append((cid, ok))
    print('[%s] %-5s %s%s' % ('PASS' if ok else 'FAIL', cid, label, '' if ok else '\n         -> ' + str(detail)[:1500]))


def info(s):
    print('[INFO] ' + s)


def zfiles(name):
    z = zipfile.ZipFile(BUILD / ('rolewarden-%s-%s.zip' % (name, TAG)))
    return {i.filename: z.read(i) for i in z.infolist() if not i.is_dir()}, z


def tarfiles(tree):
    data = sp.run(['git', 'archive', '--format=tar', tree], cwd=ROOT, check=True, stdout=sp.PIPE).stdout
    t = tarfile.open(fileobj=io.BytesIO(data))
    return {m.name: t.extractfile(m).read() for m in t.getmembers() if m.isfile()}


def strip(d, prefix):
    return {k[len(prefix):]: v for k, v in d.items() if k.startswith(prefix)}


def same(a, b):
    only_a = sorted(set(a) - set(b))
    only_b = sorted(set(b) - set(a))
    diff = sorted(k for k in set(a) & set(b) if a[k] != b[k])
    return (not only_a and not only_b and not diff), {'only_zip': only_a[:20], 'only_git': only_b[:20], 'different': diff[:20]}


mod, mz = zfiles('module')
demo, dz = zfiles('demo')
app, az = zfiles('app')
gmod = tarfiles(COMMIT)
gdemo = tarfiles(COMMIT + ':demo')
info('module zip %d files, demo zip %d, app zip %d; git archive %s: %d (module), %d (demo)' % (len(mod), len(demo), len(app), COMMIT, len(gmod), len(gdemo)))

# P01-P03: identical to git archive (export-ignore rules of .gitattributes apply)
ok, d = same(strip(mod, 'rolewarden/'), gmod)
check('P01', 'Module zip == git archive %s (one top folder rolewarden/)' % COMMIT, ok and all(k.startswith('rolewarden/') for k in mod), d)
ok, d = same(strip(demo, 'rolewarden-demo/'), gdemo)
check('P02', 'Demo zip == git archive %s:demo (top folder rolewarden-demo/)' % COMMIT, ok and all(k.startswith('rolewarden-demo/') for k in demo), d)
top = 'rolewarden-app-%s/' % TAG
check('P03a', 'Application zip has a single top folder %s' % top, all(k.startswith(top) for k in app))
appf = strip(app, top)
ok, d = same(strip(appf, 'modules/rolewarden/'), gmod)
check('P03b', 'Application zip: modules/rolewarden == git archive %s' % COMMIT, ok, d)
ok, d = same(strip(appf, 'modules/rolewarden-demo/'), gdemo)
check('P03c', 'Application zip: modules/rolewarden-demo == git archive %s:demo' % COMMIT, ok, d)
for f in ('CHANGELOG.md', 'THIRD-PARTY-LICENSES.md'):
    check('P03d-' + f, 'Application zip: %s equal to the module\'s' % f, appf.get(f) == gmod.get(f), 'differs' if f in appf else 'missing')
ra = gmod['README.md'].replace(b'\r\n', b'\n').splitlines()
rb = appf.get('README.md', b'').replace(b'\r\n', b'\n').splitlines()
rd = [(x, y) for x, y in zip(ra, rb) if x != y]
check('P03d-README', 'Application zip: README.md is the module\'s, only the documentation path adapted to modules/rolewarden/', len(ra) == len(rb) and all(y == x.replace(b'`documentation/', b'`modules/rolewarden/documentation/') for x, y in rd), rd[:5])

# P04: no development files
allp = [('module', k) for k in mod] + [('demo', k) for k in demo] + [('app', k) for k in app]
devpat = re.compile(r'(^|/)(\.env(\..*)?|\.regista\.json|_AI-LOG\.md|CLAUDE\.md|AGENTS\.md|\.git/|\.github/|docs/|docker/|docker-compose\.ya?ml|tests?/Integration|phpunit\.xml(\.dist)?|phpstan\.neon(\.dist)?|\.php-cs-fixer(\.dist)?\.php|tailwindcss(\.exe)?|resources/|bin/tailwind|node_modules/|package(-lock)?\.json|\.idea/|\.vscode/|\.DS_Store|Thumbs\.db|[^/]*\.(log|sql|sqlite|zip|tar|gz|bak|swp|orig))$|(^|/)\.env$|(^|/)\.env/')
dev = [p for p in allp if devpat.search(p[1])]
# vendor packages may legitimately ship their own tests/ or docs; report RoleWarden-owned paths and app root
own = [p for p in dev if '/vendor/' not in p[1]]
check('P04a', 'No .env, log, dump, AI/agent notes, internal docs, build tools or IDE files outside vendor/', not own, own[:30])
info('matches inside vendor/ (third-party, informative): %d %s' % (len(dev) - len(own), [p[1].split('/vendor/')[1] for p in dev if '/vendor/' in p[1]][:15]))
tests_dirs = sorted({k.split('/tests/')[0] for _, k in allp if '/tests/' in k and '/vendor/' not in k})
check('P04b', 'No tests/ folder outside vendor/', not tests_dirs, tests_dirs)
w = sorted(k[len('writable/'):] for k in appf if k.startswith('writable/'))
check('P04c', 'writable/ holds only the starter placeholders (no session, log, cache, debugbar dump, install code or lock)',
      all(re.fullmatch(r'(\.htaccess|index\.html|(cache|debugbar|logs|session|uploads)/index\.html)', x) for x in w), w)
envtpl = appf.get('env', b'').decode('utf-8', 'replace')
check('P04d', 'The `env` template keeps every setting commented out (no live value)', all(l.strip() == '' or l.lstrip().startswith('#') for l in envtpl.splitlines()),
      [l for l in envtpl.splitlines() if l.strip() and not l.lstrip().startswith('#')][:10])
lock = json.loads(appf['composer.lock'])
devpk = [p['name'] for p in lock.get('packages-dev', [])]
inst = json.loads(appf['vendor/composer/installed.json'])
instpk = {p['name']: p for p in inst['packages']}
check('P04e', 'vendor/ without development packages (installed.json dev=false, no dev-package-names)', not inst.get('dev', True) and not inst.get('dev-package-names'),
      'dev=%s dev-package-names=%s packages-dev in lock=%s' % (inst.get('dev'), inst.get('dev-package-names'), devpk))
vend_dirs = sorted({'/'.join(k.split('/')[1:3]) for k in appf if k.startswith('vendor/') and k.count('/') > 2 and not k.startswith('vendor/composer/')})
check('P04f', 'vendor/ folders are exactly the installed packages', set(vend_dirs) == set(instpk), {'dirs': vend_dirs, 'installed': sorted(instpk)})

# P05: credentials, keys, personal URLs and paths
pw = os.environ.get('RW_DB_PASSWORD', '')
pats = {
    'private key': re.compile(rb'-----BEGIN [A-Z ]*PRIVATE KEY-----'),
    'encryption key value': re.compile(rb'encryption\.key\s*=\s*[\'"]?(hex2bin|base64):[A-Za-z0-9+/=]{16,}'),
    'Windows user path': re.compile(rb'[A-Za-z]:\\\\?(Users|progetti_lavoro|laragon|xampp)', re.I),
    'personal names': re.compile(rb'fabio|gulotta|fbgulotta|Tullio69|scolibro|qrart|tavolia', re.I),
    'local ports of this machine': re.compile(rb'(localhost|127\.0\.0\.1):(3317|3307|1026|8026|8025|8090|3310)\b'),
    'git@ remote': re.compile(rb'git@github\.com:'),
    'Mailpit/Adminer': re.compile(rb'mailpit|adminer', re.I),
    'AWS/GitHub/Stripe style token': re.compile(rb'(AKIA[0-9A-Z]{16}|ghp_[A-Za-z0-9]{36}|sk_live_[A-Za-z0-9]{20,}|xox[bp]-[A-Za-z0-9-]{20,})'),
}
hits = {k: [] for k in pats}
pwhits = []
for where, files, pref in (('module', mod, ''), ('demo', demo, ''), ('app', app, '')):
    for k, v in files.items():
        for n, p in pats.items():
            if p.search(v):
                hits[n].append(where + ':' + k)
        if pw and re.search(rb'(password|pass|pwd)[\'"]?\s*(=>|=|:)\s*[\'"]?' + re.escape(pw.encode()) + rb'[\'"]?\s*($|[,;\n])', v, re.I | re.M):
            pwhits.append(where + ':' + k)
own_hits = {n: [x for x in h if '/vendor/' not in x] for n, h in hits.items()}
check('P05a', 'No private key, encryption key value or cloud token anywhere (vendor/ included)',
      not hits['private key'] and not hits['encryption key value'] and not hits['AWS/GitHub/Stripe style token'],
      {n: hits[n][:10] for n in ('private key', 'encryption key value', 'AWS/GitHub/Stripe style token')})
check('P05b', 'The database password of this machine appears in no file as a password value', not pwhits, pwhits[:10])
personal = {n: own_hits[n] for n in ('Windows user path', 'personal names', 'local ports of this machine', 'git@ remote', 'Mailpit/Adminer') if own_hits[n]}
check('P05c', 'No personal path, name, local port, git remote or local tool of the author outside vendor/', not personal, personal)
info('vendor/ matches (third-party, informative): ' + json.dumps({n: [x.split('/vendor/')[1] for x in h if '/vendor/' in x][:5] for n, h in hits.items() if any('/vendor/' in x for x in h)}))
urlre = re.compile(rb'https?://([A-Za-z0-9.-]+)')
hosts = {}
for where, files in (('module', mod), ('demo', demo), ('app', {k: v for k, v in app.items() if k[len(top):] in ('README.md', 'composer.json', 'app/Config/Autoload.php', 'app/Config/Routes.php', 'app/Config/Auth.php', 'app/Config/Filters.php')})):
    for k, v in files.items():
        for h in set(urlre.findall(v)):
            hosts.setdefault(h.decode(), set()).add(where + ':' + k.split('/', 1)[1])
starter = {}
for k, v in app.items():
    if '/vendor/' not in k and '/modules/' not in k:
        for h in set(urlre.findall(v)):
            starter.setdefault(h.decode(), set()).add(k.split('/', 1)[1])
info('URL hosts in the rest of the application (CodeIgniter app starter and composer.lock metadata, informative): ' + ', '.join(sorted(set(starter) - set(hosts))))
info('URL hosts in RoleWarden files (module, demo, README and wiring of the application): ' + json.dumps({h: sorted(s)[:4] + (['...%d' % len(s)] if len(s) > 4 else []) for h, s in sorted(hosts.items())}))
neutral = re.compile(r'^(([a-z0-9-]+\.)*(example\.(com|org|net)|[a-z0-9-]+\.test|localhost)|rolewarden\.com|([a-z0-9-]+\.)?rolewarden\.com|www\.w3\.org|github\.com|getcomposer\.org|codeigniter\.com|([a-z]+\.)?codeigniter4?\.(com|org)|alpinejs\.dev|tailwindcss\.com|opensource\.org|scripts\.sil\.org|openfontlicense\.org|forum\.codeigniter\.com|codeigniter4\.github\.io|codeigniterchat\.slack\.com|packagist\.org|www\.php\.net|php\.net|keepachangelog\.com|semver\.org|codecanyon\.net|fonts\.googleapis\.com|ibm\.com|www\.ibm\.com|developer\.mozilla\.org|www\.apache\.org|httpd\.apache\.org|nginx\.org|tools\.ietf\.org|datatracker\.ietf\.org|en\.wikipedia\.org|docs\.github\.com)$', re.I)
odd = {h: sorted(s)[:5] for h, s in hosts.items() if not neutral.match(h) and not re.fullmatch(r'[0-9.]+', h)}
check('P05d', 'Every URL host in RoleWarden files is the product site, an example/test domain or a public reference', not odd, odd)

# P06: third-party licences against the content of the zips
tpl = gmod['THIRD-PARTY-LICENSES.md'].decode()
missing = []
for name, p in instpk.items():
    ver = p['version'].lstrip('v')
    row = [l for l in tpl.splitlines() if '[' + name + ']' in l]
    if not row or ver not in row[0]:
        missing.append('%s %s -> %s' % (name, ver, row[0][:120] if row else 'not listed'))
    lic = [k for k in appf if re.match(r'vendor/' + re.escape(name) + r'/(LICEN[CS]E|COPYING)(\.(md|txt))?$', k, re.I)]
    if not lic:
        missing.append(name + ': no licence file in vendor/')
    if p.get('license') and not any(l in (row[0] if row else '') for l in p['license']):
        missing.append('%s: licence %s not in the row' % (name, p['license']))
check('P06a', 'THIRD-PARTY-LICENSES lists every package of vendor/ with its exact version and licence, and each has its LICENSE file', not missing, missing)
listed = re.findall(r'\[([a-z0-9-]+/[a-z0-9-]+)\]', tpl)
extra = [n for n in listed if n not in instpk]
check('P06b', 'Every package listed as "included in the complete application" is really in vendor/', not extra, extra)
assets = sorted(k[len('src/Assets/'):] for k in gmod if k.startswith('src/Assets/'))
info('src/Assets: ' + ', '.join(assets))
ok = 'src/Assets/js/vendor/alpine.min.js' in gmod and 'src/Assets/js/vendor/LICENSE-alpine.md' in gmod and b'Permission is hereby granted' in gmod['src/Assets/js/vendor/LICENSE-alpine.md']
alp = re.search(rb'Alpine[^\n]{0,40}?v?(\d+\.\d+\.\d+)', gmod.get('src/Assets/js/vendor/alpine.min.js', b'')) or re.search(rb'version:"(\d+\.\d+\.\d+)"', gmod.get('src/Assets/js/vendor/alpine.min.js', b''))
check('P06c', 'Alpine.js: file, MIT text beside it, version in the file = listed 3.17.4', ok and bool(alp) and alp.group(1) == b'3.17.4', alp.group(1) if alp else 'version not found')
fonts = [a for a in assets if a.startswith('fonts/') and a.endswith('.woff2')]
ofl = gmod.get('src/Assets/fonts/LICENSE.txt', b'')
check('P06d', 'IBM Plex fonts: SIL OFL 1.1 full text beside them, with the IBM copyright', b'SIL OPEN FONT LICENSE' in ofl.upper() and b'Version 1.1' in ofl and b'IBM' in ofl and len(fonts) == 6, (len(fonts), ofl[:200]))
css = gmod.get('src/Assets/css/panel.css', b'')
twv = re.search(r'\[Tailwind CSS\][^|]*\|\s*([0-9.]+)', tpl).group(1)
check('P06e', 'panel.css keeps the Tailwind CSS notice (version listed %s, MIT)' % twv, bool(re.search(rb'tailwindcss v' + re.escape(twv.encode()), css[:600], re.I)) and b'MIT' in css[:600], css[:300])
others = [a for a in assets if not (a.startswith('fonts/') or a in ('css/panel.css', 'js/panel.js', 'js/vendor/alpine.min.js', 'js/vendor/LICENSE-alpine.md'))]
check('P06f', 'No other third-party asset in src/Assets beyond those listed', all(a in ('fonts/LICENSE.txt', 'fonts/NOTICE.md', 'js/vendor/NOTICE.md', 'css/.gitkeep', 'js/.gitkeep') for a in others), others)
mitrows = [l for l in tpl.splitlines() if '| MIT |' in l]
check('P06g', 'The MIT text is in the file (shared terms, each row with its own copyright line)', 'Permission is hereby granted' in tpl and 'THE SOFTWARE IS PROVIDED "AS IS"' in tpl and all('©' in l for l in mitrows), len(mitrows))
bsd = appf.get('vendor/laminas/laminas-escaper/LICENSE.md', b'')
check('P06h', 'laminas-escaper BSD-3-Clause text present where the file says', b'Redistribution and use in source and binary forms' in bsd)
check('P06i', 'App starter (MIT): LICENSE at the root of the complete application', b'Permission is hereby granted' in appf.get('LICENSE', b'') and b'MIT' in appf.get('LICENSE', b'MIT'), appf.get('LICENSE', b'')[:200])
fav = appf.get('public/favicon.ico')
info('public/favicon.ico present=%s sha1=%s (the app starter\'s icon)' % (fav is not None, hashlib.sha1(fav).hexdigest() if fav else '-'))
demo3 = [k for k in demo if re.search(r'\.(js|css|woff2?|ttf|png|jpe?g|svg|gif)$', k)]
check('P06j', 'Demo zip ships no third-party asset (only PHP and its README)', not demo3, demo3)
cl = gmod['CHANGELOG.md'].decode()
info('CHANGELOG headings: %s; composer.json version field: %s' % (re.findall(r'^## \[[^\]]+\].*$', cl, re.M)[:4], json.loads(gmod['composer.json']).get('version')))
check('P07', 'CHANGELOG.md starts from the MVP and has the 1.0.0 entry (the 1.1 entry: see the INFO line above)', bool(re.search(r'1\.0\.0', cl)) and bool(re.search(r'0\.1\.0|MVP', cl, re.I)), cl[:300])
mj = json.loads(gmod['composer.json'])
aj = json.loads(appf['composer.json'])
check('P08', 'Declared requirements: php ^8.3, framework ^4.7.4 (module); application locked on what THIRD-PARTY-LICENSES says',
      mj['require'].get('php') == '^8.3' and mj['require'].get('codeigniter4/framework') == '^4.7.4' and instpk['codeigniter4/framework']['version'].lstrip('v') == re.search(r'\[codeigniter4/framework\][^|]*\|\s*([0-9.]+)', tpl).group(1),
      (mj['require'], aj.get('require')))
lk = {p['name']: p['version'] for p in lock['packages']}
check('P09', 'vendor/composer/installed.json and composer.lock name the same packages and versions', lk == {n: p['version'] for n, p in instpk.items()}, (lk, {n: p['version'] for n, p in instpk.items()}))
info('installed in the application zip: ' + ', '.join('%s %s (%s)' % (n, p['version'], '/'.join(p.get('license', []))) for n, p in sorted(instpk.items())))
print('TOTAL %d/%d' % (sum(1 for _, o in RES if o), len(RES)))
