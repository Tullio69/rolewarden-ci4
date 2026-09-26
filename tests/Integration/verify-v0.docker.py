"""Collaudo V0, parte Docker: python tests/Integration/verify-v0.docker.py

Complementa verify-v0.py (solo HTTP, Codex). Esegue in nero, sulla replica locale di
rolewarden-demo: deploy, cancellazioni via pannello, reset, 503, mail sink, rifiuti
fast-forward, guardia sul database del .env, scansione segreti, e il ripristino finale.

Fasi separate (per tenere basso il carico sull'host):
    python verify-v0.docker.py deploy mail reset ff envguard secrets final
senza argomenti le esegue tutte. Lo stato fra le fasi (impronte, home, sink) sta in
RW_V0_STATE (default: file nella cartella temporanea), mai le password degli account.

Richiede la variabile d'ambiente RW_REPLICA_DB_PASSWORD (password usa e getta della replica):
nessuna credenziale sta in questo file. Tocca solo i container rolewarden-demo-web e
rolewarden-demo-db e i database rolewarden_staging e rolewarden_demo.
Non modifica il repo rolewarden-demo sull'host (montato in sola lettura come /origin).
"""
import hashlib
import html
import http.client
import json
import os
import re
import subprocess
import sys
import threading
import time
import uuid
from collections import Counter
from pathlib import Path
from urllib.parse import urlencode, urlsplit

DEMO_REPO = Path(__file__).resolve().parents[3] / 'rolewarden-demo'
WEB, DB = 'rolewarden-demo-web', 'rolewarden-demo-db'
ENVS = ('staging', 'demo')
BRANCH = {'staging': 'staging', 'demo': 'main'}
DBNAME = {'staging': 'rolewarden_staging', 'demo': 'rolewarden_demo'}
DB_PASSWORD = os.environ.get('RW_REPLICA_DB_PASSWORD')
DIAG = re.compile(r'SQLSTATE\[|Fatal error:|Parse error:|Warning:|Notice:|Deprecated:|Uncaught\s|'
                  r'mysqli_sql_exception|Stack trace:|CodeIgniter\\Database\\Exceptions|'
                  r'DatabaseException|Whoops!', re.I)

results, responses, commands = [], [], []


def check(label, condition, detail=None):
    row = {'esito': 'PASS' if condition else 'FAIL', 'controllo': label}
    if detail is not None:
        row['dettaglio'] = detail
    results.append(row)
    print(f"[{row['esito']}] {label}" + (f' -- {detail}' if detail is not None and not condition else ''),
          flush=True)
    return condition


def info(label, detail):
    results.append({'esito': 'INFO', 'controllo': label, 'dettaglio': detail})
    print(f'[INFO] {label}: {detail}', flush=True)


# --- processi --------------------------------------------------------------------------

def run(args, cwd=None, stdin=None, timeout=900, record=None):
    env = dict(os.environ, MSYS_NO_PATHCONV='1',
               RW_SSH_KEY=os.path.expanduser('~/.ssh/id_ed25519_scolibro'))
    p = subprocess.run(args, cwd=cwd, input=stdin, capture_output=True, text=True,
                       encoding='utf-8', errors='replace', timeout=timeout, env=env)
    if record:
        tail = [l for l in (p.stdout + p.stderr).strip().splitlines()
                if not re.search(r'password|secret|token', l, re.I)][-6:]
        commands.append({'comando': record, 'exit': p.returncode, 'ultime_righe': tail})
    return p


def web(script, user='www-data', stdin=None, record=None):
    return run(['docker', 'exec', '-i', '-u', user, WEB, 'bash', '-c', script],
               stdin=stdin, record=record)


def script_ops(name, env):
    # Come dice ops/README.md, sezione Local replica.
    return run(['docker', 'compose', 'exec', '-T', '-u', 'www-data', 'web',
                f'/srv/rolewarden/{env}/ops/{name}.sh', env], cwd=DEMO_REPO,
               record=f'ops/{name}.sh {env}')


def sql(db, query):
    assert db in DBNAME.values() or db == 'information_schema'
    p = run(['docker', 'exec', '-e', 'MYSQL_PWD=' + DB_PASSWORD, DB, 'mariadb', '-urolewarden',
             '-N', '-B', db, '-e', query])
    if p.returncode:
        raise RuntimeError('query non riuscita su ' + db)
    return [line.split('\t') for line in p.stdout.splitlines()]  # liste: restano uguali dopo il JSON dello stato


def databases():
    return sorted(r[0] for r in sql('information_schema', 'SELECT schema_name FROM schemata'))


def checksums():
    """Impronta esatta di entrambi i database: cambia con qualunque scrittura."""
    out = {'databases': databases()}
    for db in DBNAME.values():
        tables = [r[0] for r in sql(db, 'SHOW TABLES')]
        rows = sql(db, 'CHECKSUM TABLE ' + ', '.join(f'`{db}`.`{t}`' for t in tables)) if tables else []
        out[db] = dict((r[0], r[1]) for r in rows)
    return out


def fingerprint(db):
    """Le stesse righe, non gli stessi byte: chiavi naturali, niente hash ne' timestamp."""
    email = ("LEFT JOIN auth_identities i ON i.user_id = {0} AND i.type = 'email_password'")
    return {
        'ruoli': sql(db, 'SELECT r.slug, r.name, IFNULL(r.description,""), IFNULL(p.slug,""), '
                         'r.is_system, r.is_super_admin, r.deleted_at IS NULL FROM acl_roles r '
                         'LEFT JOIN acl_roles p ON p.id = r.parent_id ORDER BY 1'),
        'permessi': sql(db, 'SELECT slug, area, IFNULL(description,""), is_system '
                            'FROM acl_permissions ORDER BY 1'),
        'utenti': sql(db, 'SELECT IFNULL(i.secret,""), u.username, u.active, IFNULL(u.status,""), '
                          'u.deleted_at IS NULL FROM users u ' + email.format('u.id') + ' ORDER BY 1, 2'),
        'utente_ruolo': sql(db, 'SELECT IFNULL(i.secret,""), r.slug FROM acl_user_roles x '
                                + email.format('x.user_id') + ' JOIN acl_roles r ON r.id = x.role_id ORDER BY 1, 2'),
        'ruolo_permesso': sql(db, 'SELECT r.slug, p.slug FROM acl_role_permissions x JOIN acl_roles r '
                                  'ON r.id = x.role_id JOIN acl_permissions p ON p.id = x.permission_id ORDER BY 1, 2'),
        'utente_permesso': sql(db, 'SELECT IFNULL(i.secret,""), p.slug, x.granted FROM acl_user_permissions x '
                                   + email.format('x.user_id') + ' JOIN acl_permissions p ON p.id = x.permission_id ORDER BY 1, 2'),
        'gruppi_shield': sql(db, 'SELECT IFNULL(i.secret,""), x.`group` FROM auth_groups_users x '
                                 + email.format('x.user_id') + ' ORDER BY 1, 2'),
        'migrazioni': sql(db, 'SELECT class FROM migrations ORDER BY 1'),
    }


def head(env):
    return web(f'git -C /srv/rolewarden/{env} rev-parse HEAD').stdout.strip()


def worktree_clean(env):
    return web(f'git -C /srv/rolewarden/{env} status --porcelain --untracked-files=no').stdout.strip() == ''


# --- HTTP -------------------------------------------------------------------------------

class Client:
    def __init__(self, env):
        self.host, self.cookies = f'{env}.localhost:8090', {}

    def req(self, path, data=None, label=None, quiet=False):
        url = urlsplit(path)
        if url.scheme:
            assert url.netloc == self.host
            path = url.path + ('?' + url.query if url.query else '')
        headers = {'Host': self.host}
        if self.cookies:
            headers['Cookie'] = '; '.join(k + '=' + v for k, v in self.cookies.items())
        body = None
        if data is not None:
            body = urlencode(data)
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        conn = http.client.HTTPConnection('127.0.0.1', 8090, timeout=30)
        conn.request('POST' if data is not None else 'GET', path, body, headers)
        r = conn.getresponse()
        text = r.read().decode('utf-8', 'replace')
        hdrs = {}
        for k, v in r.getheaders():
            hdrs[k.lower()] = v
            if k.lower() == 'set-cookie':
                name, value = v.split(';', 1)[0].split('=', 1)
                self.cookies[name] = value
        conn.close()
        tag = f'{self.host} {"POST" if data is not None else "GET"} {path} ({r.status})'
        if not quiet:
            responses.append({'richiesta': label or tag, 'status': r.status,
                              'x_robots_tag': hdrs.get('x-robots-tag'),
                              'sha256_corpo': hashlib.sha256(text.encode()).hexdigest()})
            check(tag + ': noindex', hdrs.get('x-robots-tag') == 'noindex, nofollow')
            check(tag + ': nessuna diagnostica PHP/SQL', DIAG.search(text) is None)
        return r.status, hdrs, text

    def follow(self, status, hdrs, text):
        for _ in range(5):
            if status not in (301, 302, 303, 307, 308):
                break
            status, hdrs, text = self.req(hdrs['location'])
        return status, hdrs, text

    def csrf(self, text):
        m = re.search(r'name="(csrf[^"]*)" value="([^"]+)"', text)
        return {m[1]: m[2]} if m else {}

    def login(self, email, password):
        _, _, page = self.req('/login')
        status, hdrs, text = self.req('/login', dict(email=email, password=password, **self.csrf(page)))
        status, _, text = self.follow(status, hdrs, text)
        own = next((row for row in re.findall(r'<tr>(.*?)</tr>', text, re.S) if '>you</span>' in row), '')
        return status == 200 and email in html.unescape(own)


def accounts(env):
    _, _, home = Client(env).req('/', quiet=True)
    return [(html.unescape(e), html.unescape(p), r) for e, p, r in re.findall(
        r'<tr><td><code>([^<]+)</code></td><td><code>([^<]+)</code></td><td>([^<]+)</td></tr>', home)]


class Poller(threading.Thread):
    """Campiona la home durante un reset per cogliere la pagina 503."""

    def __init__(self, env):
        super().__init__(daemon=True)
        self.client, self.stop, self.seen = Client(env), threading.Event(), []

    def run(self):
        while not self.stop.is_set():
            try:
                status, hdrs, text = self.client.req('/', quiet=True)
                self.seen.append((status, hdrs.get('x-robots-tag'), DIAG.search(text) is None,
                                  hashlib.sha256(text.encode()).hexdigest()))
            except Exception as error:  # connessione rifiutata durante il riavvio: annotata
                self.seen.append(('errore', type(error).__name__, True, ''))
            time.sleep(0.1)  # carico minimo: la finestra del reset dura secondi


def with_poller(env, action):
    poller = Poller(env)
    poller.start()
    result = action()
    time.sleep(0.3)
    poller.stop.set()
    poller.join()
    return result, poller.seen


def report_503(label, seen):
    counts = Counter(str(s[0]) for s in seen)
    info(label + ': campioni HTTP della home durante il reset', dict(counts))
    unavailable = [s for s in seen if s[0] == 503]
    if unavailable:
        check(label + ': 503 osservato durante il reset', True)
        check(label + ': ogni 503 ha X-Robots-Tag noindex, nofollow',
              all(s[1] == 'noindex, nofollow' for s in unavailable))
        check(label + ': ogni 503 senza diagnostica PHP/SQL', all(s[2] for s in unavailable))
    else:
        info(label + ': 503 non osservato (limite di campionamento, non FAIL)', len(seen))
    check(label + ': nessuna risposta 5xx diversa da 503 durante il reset',
          not any(isinstance(s[0], int) and s[0] >= 500 and s[0] != 503 for s in seen))
    return bool(unavailable)


# --- fasi -------------------------------------------------------------------------------

def phase_deploy(state):
    for env in ENVS:
        (p, seen) = with_poller(env, lambda: script_ops('deploy', env))
        check(f'1 {env}: deploy.sh esce con 0', p.returncode == 0, p.returncode)
        state['503_deploy_' + env] = report_503(f'3 {env} deploy', seen)
        origin = web(f'git -C /origin rev-parse {BRANCH[env]}').stdout.strip()
        check(f'1 {env}: pubblicato il commit di origin/{BRANCH[env]}', head(env) == origin, head(env))
        check(f'1 {env}: working tree pulito', worktree_clean(env))
        state['baseline_' + env] = fingerprint(DBNAME[env])
        client = Client(env)
        status, _, home = client.req('/')
        check(f'1 {env}: home 200 dopo il deploy', status == 200)
        state['home_' + env] = home
        found = accounts(env)
        check(f'1 {env}: account pubblici sulla home', len(found) >= 2, len(found))
        text = html.unescape(re.sub(r'<[^>]+>', ' ', home))
        check(f'A3 {env}: la home non promette un reset orario',
              re.search(r'hour|hourly|every \d+ ?min', text, re.I) is None,
              re.findall(r'[^.]*reset[^.]*\.', text, re.I))
        state['accounts_' + env] = found
        for email, password, role in found:
            check(f'1 {env}: login {role} ({email}) arriva al pannello', Client(env).login(email, password))
    base = state['baseline_staging']
    info('1 righe dopo il primo reset (staging)', {k: len(v) for k, v in base.items()})
    check('1 staging e demo hanno le stesse righe dopo il deploy',
          state['baseline_staging'] == state['baseline_demo'])


def phase_replica_cron(state):
    procs = web('ps -eo comm=', user='root').stdout.split()
    info('A2 processi nel container web', dict(Counter(procs)))
    readme = (DEMO_REPO / 'ops' / 'README.md').read_text(encoding='utf-8')
    says = re.search(r'Two things need the real VPS:.*?(?:\n\n|\Z)', readme, re.S)
    check('A2 il runbook dichiara che il reset orario richiede il VPS (la replica non ha cron)',
          says is not None and 'cron' in says[0] and 'hourly reset' in says[0], says and says[0])
    check('A2 nella replica non gira cron, coerente col runbook', not any('cron' in x for x in procs))


def ensure_accounts(state):
    for env in ENVS:
        if 'accounts_' + env not in state:
            state['accounts_' + env] = accounts(env)


def phase_mail(state):
    before = set(web('ls -A /var/mail-sink').stdout.split())
    state['sink_before'] = before
    token = 'v0-' + uuid.uuid4().hex[:12]
    p = web(f"php -r 'var_export(mail(\"v0-php@example.com\", \"v0 php {token}\", \"v0 probe\"));'")
    check('4 mail() di PHP (CLI, www-data) ritorna true', p.stdout.strip() == 'true', p.stdout.strip())
    found = web(f'grep -l -- "{token}" /var/mail-sink/* 2>/dev/null').stdout.split()
    check('4 mail() di PHP finisce come file in /var/mail-sink', len(found) == 1, found)

    token2 = 'v0-' + uuid.uuid4().hex[:12]
    probe = (Path(__file__).with_name('verify-v0.mailprobe.php')).read_text(encoding='utf-8')
    p = web(f'cat > /tmp/v0mail.php && php /tmp/v0mail.php staging {token2}; rm -f /tmp/v0mail.php',
            stdin=probe)
    info('4 servizio Email di CI4 dal CLI dell\'app (staging)', p.stdout.strip() or p.returncode)
    found = web(f'grep -l -- "{token2}" /var/mail-sink/* 2>/dev/null').stdout.split()
    check('4 servizio Email di CI4 finisce come file in /var/mail-sink', len(found) == 1, found)

    # Via Apache, con una richiesta HTTP vera: il link magico di Shield spedisce un'email.
    for env in ENVS:
        count = len(web('ls -A /var/mail-sink').stdout.split())
        client = Client(env)
        _, _, page = client.req('/login/magic-link')
        email = state['accounts_' + env][-1][0]
        status, hdrs, text = client.req('/login/magic-link', dict(email=email, **client.csrf(page)))
        client.follow(status, hdrs, text)
        new = web('ls -A /var/mail-sink').stdout.split()
        added = [f for f in new if f not in before]
        hit = web(f'grep -l -i -- "{email}" ' + ' '.join('/var/mail-sink/' + f for f in added)
                  + ' 2>/dev/null').stdout.split() if added else []
        check(f'4 {env}: email del link magico (Apache) finisce in /var/mail-sink',
              len(new) == count + 1 and len(hit) >= 1, {'prima': count, 'dopo': len(new)})
    info('4 sendmail_path del PHP CLI', web('php -r "echo ini_get(\'sendmail_path\');"').stdout.strip())
    info('4 blocco firewall SMTP', 'non verificabile sulla replica: esiste solo sul VPS (ops/README.md)')


def phase_reset(state):
    for env in ENVS:
        db = DBNAME[env]
        admin = next(a for a in state['accounts_' + env] if a[2] == 'admin')
        client = Client(env)
        check(f'2 {env}: login admin', client.login(admin[0], admin[1]))
        # Cancella un ruolo non di sistema dal pannello.
        _, _, page = client.req('/rolewarden/roles')
        form = re.search(r'<form method="post" action="([^"]*/roles/(\d+)/delete)">\s*'
                         r'<input type="hidden" name="(csrf[^"]*)" value="([^"]+)">', page)
        check(f'2 {env}: il pannello offre la cancellazione di un ruolo non di sistema', form is not None)
        role_id = form[2]
        slug = sql(db, f'SELECT slug, is_system FROM acl_roles WHERE id = {int(role_id)}')[0]
        check(f'2 {env}: il ruolo offerto non e\' di sistema', slug[1] == '0', slug)
        status, hdrs, text = client.req(form[1], {form[3]: form[4]})
        client.follow(status, hdrs, text)
        gone = sql(db, f'SELECT COUNT(*) FROM acl_roles WHERE id = {int(role_id)} AND deleted_at IS NULL')[0][0]
        check(f'2 {env}: ruolo {slug[0]} cancellato via HTTP', gone == '0', gone)
        # Cancella un utente che non e' l'admin collegato.
        _, _, page = client.req('/rolewarden/users')
        ids = [int(i) for i in re.findall(r'/rolewarden/users/(\d+)"', page)]
        own = int(sql(db, "SELECT user_id FROM auth_identities WHERE type = 'email_password' "
                          f"AND secret = '{admin[0]}'")[0][0])
        victim = max(i for i in ids if i != own)
        _, _, page = client.req(f'/rolewarden/users/{victim}')
        form = re.search(r'<form method="post" action="([^"]*/users/\d+/delete)">\s*'
                         r'<input type="hidden" name="(csrf[^"]*)" value="([^"]+)">', page)
        check(f'2 {env}: il pannello offre la cancellazione di un utente', form is not None)
        status, hdrs, text = client.req(form[1], {form[2]: form[3]})
        client.follow(status, hdrs, text)
        alive = sql(db, f'SELECT COUNT(*) FROM users WHERE id = {victim} AND deleted_at IS NULL')[0][0]
        check(f'2 {env}: utente {victim} cancellato via HTTP', alive == '0', alive)
        dirty = fingerprint(db)
        check(f'2 {env}: le cancellazioni cambiano le righe', dirty != state['baseline_' + env])
        # Reset.
        p, seen = with_poller(env, lambda: script_ops('reset', env))
        check(f'2 {env}: reset.sh esce con 0', p.returncode == 0, p.returncode)
        state['503_reset_' + env] = report_503(f'3 {env} reset', seen)
        after = fingerprint(db)
        for key in after:
            check(f'2 {env}: dopo il reset {key} identici al primo reset',
                  after[key] == state['baseline_' + env][key],
                  {'attese': len(state['baseline_' + env][key]), 'trovate': len(after[key])})
        status, hdrs, _ = client.req('/rolewarden/users')
        check(f'2 {env}: la sessione precedente al reset non vale piu\'',
              status in (302, 303) and urlsplit(hdrs.get('location', '')).path == '/login', status)
        status, _, _ = Client(env).req('/')
        check(f'2 {env}: home 200 dopo il reset', status == 200)


FF_SETUP = r'''set -e
rm -rf /tmp/v0ff && mkdir /tmp/v0ff
git clone -q --bare /origin /tmp/v0ff/origin.git
git clone -q /tmp/v0ff/origin.git /tmp/v0ff/work
cd /tmp/v0ff/work
G="git -c user.name=v0-collaudo -c user.email=v0@example.invalid"
case "$1" in
  main-ahead)
    git checkout -q main
    $G commit -q --allow-empty -m "v0: main ahead of staging"
    git push -q origin main ;;
  diverged)
    git checkout -q -b v0 "origin/$2~1"
    $G commit -q --allow-empty -m "v0: not a fast-forward"
    for b in $3; do git push -q -f origin HEAD:$b; done ;;
esac
'''


def refused(env, label, setup_args):
    p = web(FF_SETUP.replace('$1', setup_args[0]).replace('$2', setup_args[1]).replace('$3', setup_args[2]))
    if not check(f'6 {label}: origin temporaneo preparato', p.returncode == 0, p.stderr[-300:]):
        return
    web(f'git -C /srv/rolewarden/{env} remote set-url origin /tmp/v0ff/origin.git')
    admin = next(a for a in accounts(env) if a[2] == 'admin')
    client = Client(env)
    client.login(admin[0], admin[1])
    before_head, before_db = head(env), checksums()
    p, seen = with_poller(env, lambda: script_ops('deploy', env))
    after_head, after_db = head(env), checksums()
    check(f'6 {label}: deploy.sh {env} si rifiuta (exit diverso da 0)', p.returncode != 0, p.returncode)
    info(f'6 {label}: campioni HTTP della home durante il deploy rifiutato', dict(Counter(str(s[0]) for s in seen)))
    check(f'6 {label}: nessuna pagina 503 durante il deploy rifiutato', all(s[0] == 200 for s in seen))
    check(f'6 {label}: stesso commit pubblicato', before_head == after_head, [before_head, after_head])
    check(f'6 {label}: working tree pulito', worktree_clean(env))
    check(f'6 {label}: stessi dati (CHECKSUM TABLE di entrambi i database e elenco database)',
          before_db == after_db)
    status, _, _ = client.req('/rolewarden/users')
    check(f'6 {label}: la sessione aperta prima resta valida (nessun reset)', status == 200, status)
    status, _, _ = Client(env).req('/')
    check(f'6 {label}: home 200', status == 200, status)
    info(f'6 {label}: messaggio di rifiuto', commands[-1]['ultime_righe'][-2:])


def restore_origin():
    for env in ENVS:
        web(f'git -C /srv/rolewarden/{env} remote set-url origin /origin && '
            f'git -C /srv/rolewarden/{env} fetch -q --prune origin')
    web('rm -rf /tmp/v0ff')


def phase_fast_forward(state):
    try:
        refused('demo', 'demo con main avanti a staging', ('main-ahead', '', ''))
        restore_origin()
        refused('staging', 'staging non fast-forward', ('diverged', 'staging', 'staging'))
        restore_origin()
        refused('demo', 'demo non fast-forward (main = staging, entrambi riscritti)',
                ('diverged', 'main', 'staging main'))
    finally:
        restore_origin()
    for env in ENVS:
        url = web(f'git -C /srv/rolewarden/{env} remote get-url origin').stdout.strip()
        check(f'6 {env}: origin ripristinato a /origin', url == '/origin', url)


def env_guard(env, wrong):
    path = f'/srv/rolewarden/{env}/.env'
    good = f'database.default.database = {DBNAME[env]}'
    web(f'cp -p {path} /tmp/v0env.bak && sha256sum {path} | cut -c1-64 > /tmp/v0env.sha')
    try:
        p = web(f"sed -i 's/^{good}$/database.default.database = {wrong}/' {path} && "
                f"grep -c '^database.default.database = {wrong}$' {path}")
        if not check(f'7 {env} -> {wrong}: copia del .env modificata', p.stdout.strip() == '1'):
            return
        before = checksums()
        p = script_ops('reset', env)
        after = checksums()
        check(f'7 {env} -> {wrong}: reset.sh si rifiuta', p.returncode != 0, p.returncode)
        check(f'7 {env} -> {wrong}: database invariati e nessun database creato', before == after)
        status, _, _ = Client(env).req('/')
        check(f'7 {env} -> {wrong}: sito non lasciato in 503', status == 200, status)
        info(f'7 {env} -> {wrong}: messaggio di rifiuto', commands[-1]['ultime_righe'][-2:])
    finally:
        web(f'cp -p /tmp/v0env.bak {path}')
        same = web(f'[ "$(sha256sum {path} | cut -c1-64)" = "$(cat /tmp/v0env.sha)" ] && echo ok').stdout.strip()
        web('rm -f /tmp/v0env.bak /tmp/v0env.sha')
        check(f'7 {env}: .env originale ripristinato (sha256 identico)', same == 'ok')


def phase_env_guard(state):
    env_guard('staging', 'rolewarden_demo')
    env_guard('demo', 'rolewarden_staging')
    env_guard('staging', 'rolewarden_v0_estraneo')


def phase_secrets(state):
    git = lambda *a: subprocess.run(['git', '-C', str(DEMO_REPO)] + list(a), capture_output=True,
                                    text=True, encoding='utf-8', errors='replace').stdout
    revs = git('rev-list', '--all').split()
    strong = (r'BEGIN (RSA |OPENSSH |EC |DSA )?PRIVATE KEY|gh[pousr]_[A-Za-z0-9]{30,}|'
              r'github_pat_[A-Za-z0-9_]{30,}|AKIA[A-Z0-9]{16}|xox[baprs]-[A-Za-z0-9-]{10,}|'
              r'hex2bin:[0-9a-fA-F]{32,}|base64:[A-Za-z0-9+/]{20,}|sk_live_[A-Za-z0-9]{10,}')
    hits = git('grep', '-I', '-l', '-E', strong, *revs)
    check('8 nessun formato di chiave o token noto in tutta la storia del repo demo', hits.strip() == '',
          sorted({h.split(':', 1)[1] for h in hits.split()}))
    tracked = [f for f in git('log', '--all', '--name-only', '--format=').split()
               if re.search(r'(^|/)\.env$|\.pem$|\.key$|(^|/)id_(rsa|ed25519|ecdsa)|auth\.json$', f)]
    check('8 nessun .env, chiave o auth.json mai committato', not tracked, sorted(set(tracked)))
    # Valori assegnati sulle righe con parole sensibili: solo classificati, mai salvati.
    known = {p for env in ENVS for _, p, _ in state.get('accounts_' + env, [])} | {DB_PASSWORD}
    out = git('grep', '-I', '-n', '-i', '-E', r'password|passwd|secret|token|api[_-]?key', *revs)
    unknown = set()
    for line in out.splitlines():
        _, f, _, content = line.split(':', 3)
        if f == 'composer.lock':
            continue
        for m in re.findall(r"(?:=|=>|:|IDENTIFIED BY)\s*['\"]([^'\"\s]{8,})['\"]", content):
            if m not in known and not re.fullmatch(r'<[^>]+>|\$\{[^}]+\}|[a-z_.]+|[A-Z][a-z]+', m):
                unknown.add((f, hashlib.sha256(m.encode()).hexdigest()[:12]))
    info('8 valori letterali non classificati come pubblici (file, sha256 troncato)', sorted(unknown))
    public = sum(1 for v in known if v and v in out)
    info('8 password pubbliche o usa e getta presenti nelle righe sensibili del repo', public)


def phase_final(state):
    for env in ENVS:
        p = script_ops('deploy', env)
        check(f'final {env}: deploy.sh esce con 0', p.returncode == 0, p.returncode)
        origin = web(f'git -C /origin rev-parse {BRANCH[env]}').stdout.strip()
        state['final_head_' + env] = head(env)
        check(f'final {env}: commit pubblicato = origin/{BRANCH[env]}', head(env) == origin, head(env))
        check(f'final {env}: stesse righe del primo reset',
              fingerprint(DBNAME[env]) == state.get('baseline_' + env))
        client = Client(env)
        status, _, home = client.req('/')
        check(f'final {env}: home 200 identica a quella dopo il primo deploy',
              status == 200 and home == state.get('home_' + env))
        status, _, body = client.req('/robots.txt')
        check(f'final {env}: robots.txt vieta tutto',
              status == 200 and re.search(r'User-agent:\s*\*\s+Disallow:\s*/\s*$', body, re.M) is not None)
        status, hdrs, _ = client.req('/rolewarden/users')
        check(f'final {env}: pannello anonimo redirige al login',
              status in (302, 303) and urlsplit(hdrs.get('location', '')).path == '/login')
        status, _, _ = client.req('/verify-v0-inesistente')
        check(f'final {env}: 404 sulle pagine inesistenti', status == 404)
        state['final_rows_' + env] = {k: len(v) for k, v in fingerprint(DBNAME[env]).items()}
    # Mail sink riportato com'era: si tolgono solo i file creati dal collaudo.
    now = web('ls -A /var/mail-sink').stdout.split()
    extra = [f for f in now if f not in state.get('sink_before', set())]
    if extra:
        web('rm -f ' + ' '.join('/var/mail-sink/' + f for f in extra if re.fullmatch(r'[\w.-]+', f)))
    left = web('ls -A /var/mail-sink').stdout.split()
    check('final: mail sink riportato allo stato iniziale', sorted(left) == sorted(state.get('sink_before', [])))


def main():
    if not DB_PASSWORD:
        sys.exit('Imposta RW_REPLICA_DB_PASSWORD con la password usa e getta della replica.')
    state_file = Path(os.environ.get('RW_V0_STATE') or
                      Path(os.environ.get('TEMP', '/tmp')) / 'verify-v0.docker.state.json')
    names = {'deploy': phase_deploy, 'cron': phase_replica_cron, 'mail': phase_mail,
             'reset': phase_reset, 'ff': phase_fast_forward, 'envguard': phase_env_guard,
             'secrets': phase_secrets, 'final': None}
    wanted = sys.argv[1:] or list(names)
    assert all(w in names for w in wanted), wanted
    state = {}
    if wanted[0] != 'deploy' and state_file.exists():
        state = json.loads(state_file.read_text(encoding='utf-8'))
        results.extend(state.pop('_results', []))
        responses.extend(state.pop('_responses', []))
        commands.extend(state.pop('_commands', []))
        state['sink_before'] = set(state.get('sink_before', []))
    host_head = run(['git', '-C', str(DEMO_REPO), 'rev-parse', 'HEAD']).stdout.strip()
    host_status = run(['git', '-C', str(DEMO_REPO), 'status', '--porcelain']).stdout
    info('repo demo sull\'host prima', {'HEAD': host_head, 'pulito': host_status == ''})
    ps = run(['docker', 'ps', '--format', '{{.Names}}']).stdout.split()
    check('replica in esecuzione', WEB in ps and DB in ps, ps)
    info('commit pubblicati prima del collaudo', {env: head(env) for env in ENVS})
    failed = False
    try:
        for w in wanted:
            if names[w]:
                if w != 'deploy':
                    ensure_accounts(state)
                names[w](state)
    except Exception as error:
        failed = True
        results.append({'esito': 'BLOCCATO', 'controllo': 'Esecuzione interrotta: '
                        + type(error).__name__ + ': ' + str(error)[:200]})
        print(results[-1], flush=True)
    finally:
        restore_origin()
        if failed or 'final' in wanted:
            phase_final(state)
        after_head = run(['git', '-C', str(DEMO_REPO), 'rev-parse', 'HEAD']).stdout.strip()
        after_status = run(['git', '-C', str(DEMO_REPO), 'status', '--porcelain']).stdout
        check('repo demo sull\'host invariato (HEAD e working tree)',
              after_head == host_head and after_status == host_status)
        stato = {'commit': {env: state.get('final_head_' + env) for env in ENVS},
                 'righe': {env: state.get('final_rows_' + env) for env in ENVS}}
        info('stato finale', stato)
        output = {'conteggi': dict(Counter(r['esito'] for r in results)),
                  'numero_risposte_http': len(responses), 'controlli': results,
                  'comandi': commands, 'risposte': responses}
        keep = {k: v for k, v in state.items() if not k.startswith('accounts_')}
        keep.update(_results=results, _responses=responses, _commands=commands)
        state_file.write_text(json.dumps(keep, default=list), encoding='utf-8')
        Path(__file__).with_name('verify-v0.docker.output.json').write_text(
            json.dumps(output, indent=2, ensure_ascii=False, default=list) + '\n', encoding='utf-8')
        print(json.dumps(output['conteggi']), 'risposte HTTP:', len(responses))
    return 1 if any(r['esito'] in ('FAIL', 'BLOCCATO') for r in results) else 0


if __name__ == '__main__':
    sys.exit(main())
