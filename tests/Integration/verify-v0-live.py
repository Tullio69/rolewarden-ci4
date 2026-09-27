"""Collaudo V0 dal vivo: staging.rolewarden.com e demo.rolewarden.com sul VPS.

Gira SUL SERVER come utente rolewarden (python3 3.10), copiato in ~/tmp/v0live/ e rimosso a fine
collaudo. Fasi separate, stato in ~/tmp/v0live/state.json (senza password):
    python3 verify-v0-live.py pre tls deploy reset mail http ff envguard cron final
Tocca solo /home/rolewarden e i database rolewarden_staging e rolewarden_demo.
Credenziali del database lette dal .env della checkout, passate a mysql via MYSQL_PWD, mai stampate.
HTTP con curl --resolve verso 86.48.6.193, certificato verificato (mai -k). Dati sensibili
(cookie, corpi POST) passati a curl su stdin con -K, non sulla riga di comando.
"""
import hashlib
import html
import json
import os
import re
import subprocess
import sys
import threading
import time
from collections import Counter
from pathlib import Path
from urllib.parse import urlencode, urlsplit

HOME = Path.home()
WORK = HOME / 'tmp' / 'v0live'
STATE = WORK / 'state.json'
OUTPUT = WORK / 'verify-v0-live.output.json'
IP = '86.48.6.193'
COMMIT = '48971ef51799a261d903425824eb69e23c0ec12b'
ENVS = ('staging', 'demo')
BRANCH = {'staging': 'staging', 'demo': 'main'}
DBNAME = {'staging': 'rolewarden_staging', 'demo': 'rolewarden_demo'}
APP = {e: HOME / 'domains' / f'{e}.rolewarden.com' / 'app' for e in ENVS}
SINK = HOME / 'mail-sink'
CRON_LINE = ('0 * * * * /home/rolewarden/domains/demo.rolewarden.com/app/ops/reset.sh demo >> '
             '/home/rolewarden/domains/demo.rolewarden.com/logs/reset.log 2>&1')
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


# --- processi e database ------------------------------------------------------------------

def run(args, cwd=None, stdin=None, timeout=900, record=None, env=None):
    p = subprocess.run(args, cwd=cwd, input=stdin, capture_output=True, text=True,
                       encoding='utf-8', errors='replace', timeout=timeout,
                       env=dict(os.environ, **(env or {})))
    if record:
        tail = [l for l in (p.stdout + p.stderr).strip().splitlines()
                if not re.search(r'password|secret|token', l, re.I)][-6:]
        commands.append({'comando': record, 'exit': p.returncode, 'ultime_righe': tail})
    return p


def sh(cmd, **kw):
    return run(['bash', '-c', cmd], **kw)


def dotenv(env):
    out = {}
    for line in (APP[env] / '.env').read_text(encoding='utf-8').splitlines():
        m = re.match(r'\s*([A-Za-z_.]+)\s*=\s*(.*?)\s*$', line)
        if m and not line.lstrip().startswith('#'):
            out[m[1]] = m[2].strip('\'"')
    return out


CREDS = {}


def sql(env, query, db=None):
    """Query su rolewarden_<env> con le credenziali del .env di quella checkout."""
    if env not in CREDS:
        e = dotenv(env)
        assert e['database.default.database'] == DBNAME[env], 'il .env non nomina ' + DBNAME[env]
        CREDS[env] = e
    e = CREDS[env]
    db = db or DBNAME[env]
    assert db in DBNAME.values()
    p = run(['mysql', '-h', e.get('database.default.hostname', 'localhost'),
             '-P', e.get('database.default.port', '3306') or '3306',
             '-u', e['database.default.username'], '-N', '-B', db, '-e', query],
            env={'MYSQL_PWD': e['database.default.password']})
    if p.returncode:
        raise RuntimeError('query non riuscita su ' + db)
    return [line.split('\t') for line in p.stdout.splitlines()]


def databases():
    return {env: sorted(r[0] for r in sql(env, 'SHOW DATABASES')) for env in ENVS}


def checksums():
    out = {'databases': databases()}
    for env in ENVS:
        db = DBNAME[env]
        tables = [r[0] for r in sql(env, 'SHOW TABLES')]
        rows = sql(env, 'CHECKSUM TABLE ' + ', '.join(f'`{db}`.`{t}`' for t in tables)) if tables else []
        out[db] = dict((r[0], r[1]) for r in rows)
    return out


def fingerprint(env):
    """Le stesse righe, non gli stessi byte: chiavi naturali, niente hash ne' timestamp."""
    q = lambda s: sql(env, s)
    email = "LEFT JOIN auth_identities i ON i.user_id = {0} AND i.type = 'email_password'"
    return {
        'ruoli': q('SELECT r.slug, r.name, IFNULL(r.description,""), IFNULL(p.slug,""), '
                   'r.is_system, r.is_super_admin, r.deleted_at IS NULL FROM acl_roles r '
                   'LEFT JOIN acl_roles p ON p.id = r.parent_id ORDER BY 1'),
        'permessi': q('SELECT slug, area, IFNULL(description,""), is_system FROM acl_permissions ORDER BY 1'),
        'utenti': q('SELECT IFNULL(i.secret,""), u.username, u.active, IFNULL(u.status,""), '
                    'u.deleted_at IS NULL FROM users u ' + email.format('u.id') + ' ORDER BY 1, 2'),
        'utente_ruolo': q('SELECT IFNULL(i.secret,""), r.slug FROM acl_user_roles x '
                          + email.format('x.user_id') + ' JOIN acl_roles r ON r.id = x.role_id ORDER BY 1, 2'),
        'ruolo_permesso': q('SELECT r.slug, p.slug FROM acl_role_permissions x JOIN acl_roles r '
                            'ON r.id = x.role_id JOIN acl_permissions p ON p.id = x.permission_id ORDER BY 1, 2'),
        'utente_permesso': q('SELECT IFNULL(i.secret,""), p.slug, x.granted FROM acl_user_permissions x '
                             + email.format('x.user_id') + ' JOIN acl_permissions p ON p.id = x.permission_id ORDER BY 1, 2'),
        'gruppi_shield': q('SELECT IFNULL(i.secret,""), x.`group` FROM auth_groups_users x '
                           + email.format('x.user_id') + ' ORDER BY 1, 2'),
        'migrazioni': q('SELECT class FROM migrations ORDER BY 1'),
    }


def git(env, *args):
    return run(['git', '-C', str(APP[env])] + list(args)).stdout.strip()


def head(env):
    return git(env, 'rev-parse', 'HEAD')


def worktree_clean(env):
    return git(env, 'status', '--porcelain') == ''


def script_ops(name, env):
    return run([str(APP[env] / 'ops' / f'{name}.sh'), env], cwd=str(APP[env]), record=f'ops/{name}.sh {env}')


def sha256(path):
    return hashlib.sha256(Path(path).read_bytes()).hexdigest()


def away_from_cron():
    """Il reset orario della demo gira al minuto 0: nessuna fase a cavallo."""
    while True:
        m = time.localtime().tm_min
        if 4 <= m <= 54:
            return
        print('[attesa] vicino al reset orario, minuto', m, flush=True)
        time.sleep(30)


# --- HTTP -------------------------------------------------------------------------------

def curl_config(pairs):
    out = []
    for k, v in pairs:
        assert '"' not in v and '\\' not in v and '\n' not in v
        out.append(f'{k} = "{v}"')
    return '\n'.join(out) + '\n'


class Client:
    def __init__(self, env):
        self.host, self.cookies = f'{env}.rolewarden.com', {}

    def req(self, path, data=None, label=None, quiet=False):
        url = urlsplit(path)
        if url.scheme:
            assert url.netloc == self.host, url.netloc
            path = url.path + ('?' + url.query if url.query else '')
        cfg = [('url', f'https://{self.host}{path}')]
        if self.cookies:
            cfg.append(('header', 'Cookie: ' + '; '.join(k + '=' + v for k, v in self.cookies.items())))
        if data is not None:
            cfg.append(('header', 'Content-Type: application/x-www-form-urlencoded'))
            cfg.append(('data-binary', urlencode(data)))
        # Byte, non testo: il modo testo di subprocess trasformerebbe i \r\n delle intestazioni.
        p = subprocess.run(['curl', '-sS', '-i', '--max-time', '30', '--resolve', f'{self.host}:443:{IP}', '-K', '-'],
                           input=curl_config(cfg).encode(), capture_output=True, timeout=60)
        if p.returncode:
            raise RuntimeError(f'curl {self.host}{path}: exit {p.returncode} {p.stderr.decode()[:200]}')
        raw = p.stdout.decode('utf-8', 'replace')
        head_part, _, text = raw.partition('\r\n\r\n')
        lines = head_part.split('\r\n')
        status = int(lines[0].split()[1])
        hdrs, xpb = {}, False
        for line in lines[1:]:
            k, _, v = line.partition(':')
            k, v = k.strip().lower(), v.strip()
            hdrs[k] = v
            if k == 'x-powered-by':
                xpb = True
            if k == 'set-cookie':
                name, value = v.split(';', 1)[0].split('=', 1)
                self.cookies[name] = value
        tag = f'{self.host} {"POST" if data is not None else "GET"} {urlsplit(path).path} ({status})'
        if not quiet:
            responses.append({'richiesta': label or tag, 'status': status,
                              'x_robots_tag': hdrs.get('x-robots-tag'), 'x_powered_by': xpb,
                              'sha256_corpo': hashlib.sha256(text.encode()).hexdigest()})
            check(tag + ': X-Robots-Tag noindex', 'noindex' in (hdrs.get('x-robots-tag') or ''),
                  hdrs.get('x-robots-tag'))
            check(tag + ': nessun X-Powered-By', not xpb)
            check(tag + ': nessuna diagnostica PHP/SQL', DIAG.search(text) is None)
        return status, hdrs, text

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
        post_status = status
        status, _, text = self.follow(status, hdrs, text)
        if status == 200 and '/rolewarden/users' not in text and '>you</span>' not in text:
            status, _, text = self.req('/rolewarden/users')
        own = next((row for row in re.findall(r'<tr>(.*?)</tr>', text, re.S) if '>you</span>' in row), '')
        return status == 200 and email in html.unescape(own), post_status


def accounts(env):
    _, _, home = Client(env).req('/', quiet=True)
    return [(html.unescape(e), html.unescape(p), r) for e, p, r in re.findall(
        r'<tr><td><code>([^<]+)</code></td><td><code>([^<]+)</code></td><td>([^<]+)</td></tr>', home)]


class Poller(threading.Thread):
    """Campiona la home durante un reset. Polling leggero: una richiesta ogni 250 ms."""

    def __init__(self, env, interval=0.25):
        super().__init__(daemon=True)
        self.client, self.stop, self.seen, self.interval = Client(env), threading.Event(), [], interval

    def run(self):
        while not self.stop.is_set():
            try:
                status, hdrs, text = self.client.req('/', quiet=True)
                self.seen.append((status, hdrs.get('x-robots-tag'), DIAG.search(text) is None,
                                  'x-powered-by' not in hdrs, hdrs.get('retry-after')))
            except Exception as error:
                self.seen.append(('errore', type(error).__name__, True, True, None))
            time.sleep(self.interval)


def with_poller(env, action):
    poller = Poller(env)
    poller.start()
    result = action()
    time.sleep(0.5)
    poller.stop.set()
    poller.join()
    return result, poller.seen


def report_503(label, seen):
    info(label + ': campioni HTTP della home', dict(Counter(str(s[0]) for s in seen)))
    unavailable = [s for s in seen if s[0] == 503]
    if unavailable:
        check(label + ': 503 osservato', True)
        check(label + ': ogni 503 ha X-Robots-Tag noindex', all('noindex' in (s[1] or '') for s in unavailable))
        check(label + ': ogni 503 senza diagnostica PHP/SQL', all(s[2] for s in unavailable))
        check(label + ': ogni 503 senza X-Powered-By', all(s[3] for s in unavailable))
    else:
        info(label + ': 503 non osservato (limite di campionamento, non FAIL)', len(seen))
    check(label + ': nessun 5xx diverso da 503 e nessun errore di connessione',
          not any((isinstance(s[0], int) and s[0] >= 500 and s[0] != 503) or s[0] == 'errore' for s in seen))
    check(label + ': ogni campione ha noindex', all('noindex' in (s[1] or '') for s in seen if isinstance(s[0], int)))
    return bool(unavailable)


# --- fasi -------------------------------------------------------------------------------

def phase_pre(state):
    for env in ENVS:
        state['origin_url_' + env] = git(env, 'remote', 'get-url', 'origin')
        state['env_sha_' + env] = sha256(APP[env] / '.env')
        check(f'pre {env}: commit pubblicato 48971ef', head(env) == COMMIT, head(env))
        check(f'pre {env}: working tree pulito', worktree_clean(env))
        check(f'pre {env}: branch {BRANCH[env]}', git(env, 'branch', '--show-current') == BRANCH[env])
        check(f'pre {env}: .env nomina {DBNAME[env]}', dotenv(env)['database.default.database'] == DBNAME[env])
    state['sink_before'] = sorted(os.listdir(SINK))
    state['tmp_before'] = sorted(x for x in os.listdir(HOME / 'tmp') if x != 'v0live')
    info('pre: mail sink e ~/tmp prima', {'sink': state['sink_before'], 'tmp': state['tmp_before']})
    state['databases_before'] = databases()


def phase_tls(state):
    for env in ENVS:
        host = f'{env}.rolewarden.com'
        p = run(['curl', '-sS', '-v', '-o', '/dev/null', '--max-time', '30', '--resolve', f'{host}:443:{IP}',
                 f'https://{host}/'])
        lines = [l.strip('* ').strip() for l in p.stderr.splitlines()
                 if re.search(r'subject:|issuer:|expire date:|start date:|subjectAltName|verify ok|SSL connection', l)]
        check(f'1 {env}: HTTPS con certificato verificato (curl senza -k, exit 0)', p.returncode == 0, p.returncode)
        check(f'1 {env}: curl dichiara il certificato valido', any('verify ok' in l for l in lines), lines)
        info(f'1 {env}: certificato', lines)
        http = run(['curl', '-sS', '-o', '/dev/null', '-w', '%{http_code} %{redirect_url}', '--max-time', '30',
                    '--resolve', f'{host}:80:{IP}', f'http://{host}/'])
        info(f'1 {env}: richiesta HTTP in chiaro', http.stdout.strip() or http.stderr.strip()[:200])


def phase_deploy(state):
    away_from_cron()
    for env in ENVS:
        p, seen = with_poller(env, lambda: script_ops('deploy', env))
        check(f'1 {env}: deploy.sh esce con 0', p.returncode == 0, p.returncode)
        report_503(f'3 {env} deploy', seen)
        check(f'1 {env}: pubblicato 48971ef', head(env) == COMMIT, head(env))
        check(f'1 {env}: working tree pulito', worktree_clean(env))
        state['baseline_' + env] = fingerprint(env)
        status, _, home = Client(env).req('/')
        check(f'1 {env}: home 200', status == 200, status)
        state['home_' + env] = home
        found = accounts(env)
        check(f'1 {env}: account pubblici sulla home', len(found) >= 2, len(found))
        for email, password, role in found:
            ok, post = Client(env).login(email, password)
            check(f'1 {env}: login {role} ({email}) arriva al pannello', ok)
            check(f'5 {env}: POST di login risponde 303', post == 303, post)
    info('1 righe dopo il deploy (staging)', {k: len(v) for k, v in state['baseline_staging'].items()})
    check('1 staging e demo hanno le stesse righe dopo il deploy', state['baseline_staging'] == state['baseline_demo'])


def phase_reset(state):
    for env in ENVS:
        away_from_cron()
        found = accounts(env)
        admin = next(a for a in found if a[2] == 'admin')
        client = Client(env)
        check(f'2 {env}: login admin', client.login(admin[0], admin[1])[0])
        _, _, page = client.req('/rolewarden/roles')
        form = re.search(r'<form method="post" action="([^"]*/roles/(\d+)/delete)">\s*'
                         r'<input type="hidden" name="(csrf[^"]*)" value="([^"]+)">', page)
        if not check(f'2 {env}: il pannello offre la cancellazione di un ruolo', form is not None):
            continue
        role_id = int(form[2])
        role = sql(env, f'SELECT slug, is_system FROM acl_roles WHERE id = {role_id}')[0]
        check(f'2 {env}: il ruolo offerto ({role[0]}) non e\' di sistema', role[1] == '0', role)
        status, hdrs, text = client.req(form[1], {form[3]: form[4]})
        client.follow(status, hdrs, text)
        gone = sql(env, f'SELECT COUNT(*) FROM acl_roles WHERE id = {role_id} AND deleted_at IS NULL')[0][0]
        check(f'2 {env}: ruolo {role[0]} cancellato via HTTP', gone == '0', gone)
        _, _, page = client.req('/rolewarden/users')
        ids = [int(i) for i in re.findall(r'/rolewarden/users/(\d+)"', page)]
        assert re.fullmatch(r'[\w.+-]+@[\w.-]+', admin[0])
        own = int(sql(env, "SELECT user_id FROM auth_identities WHERE type = 'email_password' "
                           f"AND secret = '{admin[0]}'")[0][0])
        victim = max(i for i in ids if i != own)
        _, _, page = client.req(f'/rolewarden/users/{victim}')
        form = re.search(r'<form method="post" action="([^"]*/users/\d+/delete)">\s*'
                         r'<input type="hidden" name="(csrf[^"]*)" value="([^"]+)">', page)
        if not check(f'2 {env}: il pannello offre la cancellazione di un utente', form is not None):
            continue
        status, hdrs, text = client.req(form[1], {form[2]: form[3]})
        client.follow(status, hdrs, text)
        alive = sql(env, f'SELECT COUNT(*) FROM users WHERE id = {victim} AND deleted_at IS NULL')[0][0]
        check(f'2 {env}: utente {victim} cancellato via HTTP', alive == '0', alive)
        check(f'2 {env}: le cancellazioni cambiano le righe', fingerprint(env) != state['baseline_' + env])
        p, seen = with_poller(env, lambda: script_ops('reset', env))
        check(f'2 {env}: reset.sh esce con 0', p.returncode == 0, p.returncode)
        state['503_reset_' + env] = report_503(f'3 {env} reset', seen)
        after = fingerprint(env)
        for key in after:
            check(f'2 {env}: dopo il reset {key} identici al primo reset', after[key] == state['baseline_' + env][key],
                  {'attese': len(state['baseline_' + env][key]), 'trovate': len(after[key])})
        status, hdrs, _ = client.req('/rolewarden/users')
        check(f'2 {env}: la sessione precedente al reset non vale piu\'',
              status in (302, 303) and urlsplit(hdrs.get('location', '')).path == '/login', status)
        status, _, _ = Client(env).req('/')
        check(f'2 {env}: home 200 dopo il reset', status == 200, status)


def phase_mail(state):
    before = set(state['sink_before'])
    for env in ENVS:
        count = len(os.listdir(SINK))
        client = Client(env)
        status, _, page = client.req('/login/magic-link')
        check(f'4 {env}: pagina del link magico 200', status == 200, status)
        email = accounts(env)[-1][0]
        status, hdrs, text = client.req('/login/magic-link', dict(email=email, **client.csrf(page)))
        client.follow(status, hdrs, text)
        time.sleep(1)
        now = os.listdir(SINK)
        added = [f for f in now if f not in before]
        hit = [f for f in added if email in (SINK / f).read_text(encoding='utf-8', errors='replace')]
        check(f'4 {env}: email del link magico (richiesta HTTP) finisce come file in ~/mail-sink',
              len(now) == count + 1 and len(hit) >= 1, {'prima': count, 'dopo': len(now)})
        before |= set(added)
    p = sh('timeout 5 bash -c "</dev/tcp/smtp.gmail.com/587" && echo OPEN || echo BLOCKED')
    info('4 connessione dal server a smtp.gmail.com:587 (regola firewall facoltativa)', p.stdout.strip())
    state['smtp_587'] = p.stdout.strip()


def phase_http(state):
    for env in ENVS:
        c = Client(env)
        status, _, _ = c.req('/')
        check(f'5 {env}: home 200', status == 200, status)
        status, hdrs, _ = c.req('/rolewarden/users')
        check(f'5 {env}: pannello anonimo 302 verso /login',
              status == 302 and urlsplit(hdrs.get('location', '')).path == '/login', status)
        status, _, _ = c.req('/verify-v0-live-inesistente')
        check(f'5 {env}: pagina inesistente 404', status == 404, status)
        status, _, body = c.req('/robots.txt')
        check(f'5 {env}: robots.txt vieta tutto',
              status == 200 and re.search(r'User-agent:\s*\*\s+Disallow:\s*/\s*$', body, re.M) is not None, body[:200])
        status, _, _ = c.req('/login')
        check(f'5 {env}: pagina di login 200', status == 200, status)


FF_SETUP = r'''set -e
W="$HOME/tmp/v0live/ff"
rm -rf "$W" && mkdir -p "$W"
git clone -q --bare "$ORIGIN" "$W/origin.git"
git clone -q "$W/origin.git" "$W/work"
cd "$W/work"
G="git -c user.name=v0-collaudo -c user.email=v0@example.invalid"
case "$CASE" in
  main-ahead)
    git checkout -q main
    $G commit -q --allow-empty -m "v0: main ahead of staging"
    git push -q origin main ;;
  diverged)
    git checkout -q -b v0 "origin/$BASE~1"
    $G commit -q --allow-empty -m "v0: not a fast-forward"
    for b in $TARGETS; do git push -q -f origin HEAD:$b; done ;;
esac
'''


def restore_origin(state):
    for env in ENVS:
        url = state['origin_url_' + env]
        run(['git', '-C', str(APP[env]), 'remote', 'set-url', 'origin', url])
        run(['git', '-C', str(APP[env]), 'fetch', '-q', '--prune', 'origin'])
    sh('rm -rf "$HOME/tmp/v0live/ff"')


def refused(state, env, label, case, base='', targets=''):
    away_from_cron()
    p = sh(FF_SETUP, env={'ORIGIN': state['origin_url_staging'], 'CASE': case, 'BASE': base, 'TARGETS': targets})
    if not check(f'6 {label}: origin temporaneo preparato in ~/tmp', p.returncode == 0, p.stderr[-300:]):
        return
    run(['git', '-C', str(APP[env]), 'remote', 'set-url', 'origin', str(WORK / 'ff' / 'origin.git')])
    admin = next(a for a in accounts(env) if a[2] == 'admin')
    client = Client(env)
    client.login(admin[0], admin[1])
    before_head, before_db = head(env), checksums()
    p, seen = with_poller(env, lambda: script_ops('deploy', env))
    after_head, after_db = head(env), checksums()
    check(f'6 {label}: deploy.sh {env} si rifiuta (exit diverso da 0)', p.returncode != 0, p.returncode)
    info(f'6 {label}: campioni HTTP della home durante il deploy rifiutato', dict(Counter(str(s[0]) for s in seen)))
    check(f'6 {label}: nessun 503 durante il deploy rifiutato', all(s[0] == 200 for s in seen))
    check(f'6 {label}: stesso commit pubblicato', before_head == after_head == COMMIT, [before_head, after_head])
    check(f'6 {label}: working tree pulito', worktree_clean(env))
    check(f'6 {label}: stessi dati (CHECKSUM TABLE dei due database, elenco database)', before_db == after_db)
    status, _, _ = client.req('/rolewarden/users')
    check(f'6 {label}: la sessione aperta prima resta valida (nessun reset)', status == 200, status)
    status, _, _ = Client(env).req('/')
    check(f'6 {label}: home 200', status == 200, status)
    info(f'6 {label}: messaggio di rifiuto', commands[-1]['ultime_righe'][-3:])
    check(f'6 {label}: il messaggio dice che nulla e\' cambiato',
          any('nothing was changed' in l.lower() for l in commands[-1]['ultime_righe']))


def phase_ff(state):
    try:
        refused(state, 'demo', 'demo con main avanti a staging', 'main-ahead')
        restore_origin(state)
        refused(state, 'staging', 'staging non fast-forward', 'diverged', 'staging', 'staging')
        restore_origin(state)
        refused(state, 'demo', 'demo non fast-forward (main = staging, entrambi riscritti)', 'diverged', 'main',
                'staging main')
    finally:
        restore_origin(state)
    for env in ENVS:
        url = git(env, 'remote', 'get-url', 'origin')
        check(f'6 {env}: origin ripristinato', url == state['origin_url_' + env], url)
        refs = [git(env, 'rev-parse', 'origin/' + b) for b in ('main', 'staging')]
        check(f'6 {env}: origin/main e origin/staging di nuovo 48971ef', refs == [COMMIT, COMMIT], refs)
    check('6 ~/tmp/v0live/ff rimosso', not (WORK / 'ff').exists())


def env_guard(state, env, wrong):
    away_from_cron()
    path = APP[env] / '.env'
    backup = WORK / f'env.{env}.bak'
    sh(f'cp -p "{path}" "{backup}"')
    try:
        text = path.read_text(encoding='utf-8')
        new, n = re.subn(r'^(\s*database\.default\.database\s*=\s*).*$', r'\g<1>' + wrong, text, flags=re.M)
        if not check(f'7 {env} -> {wrong}: .env modificato (una riga)', n == 1, n):
            return
        before = checksums()
        path.write_text(new, encoding='utf-8')
        p = script_ops('reset', env)
        sh(f'cp -p "{backup}" "{path}"')
        after = checksums()
        check(f'7 {env} -> {wrong}: reset.sh si rifiuta', p.returncode != 0, p.returncode)
        check(f'7 {env} -> {wrong}: database invariati e nessun database creato', before == after)
        info(f'7 {env} -> {wrong}: messaggio di rifiuto', commands[-1]['ultime_righe'][-2:])
        status, _, _ = Client(env).req('/')
        check(f'7 {env} -> {wrong}: sito non lasciato in 503', status == 200, status)
    finally:
        sh(f'cp -p "{backup}" "{path}"')
        backup.unlink(missing_ok=True)
        check(f'7 {env}: .env ripristinato (sha256 identico)', sha256(path) == state['env_sha_' + env])


def phase_envguard(state):
    existing = databases()
    for name in ('rolewarden_v0_estraneo',):
        check(f'7 {name} non esiste (destinazione mai esistente)', all(name not in v for v in existing.values()))
    env_guard(state, 'staging', 'rolewarden_demo')
    env_guard(state, 'demo', 'rolewarden_staging')
    env_guard(state, 'staging', 'rolewarden_v0_estraneo')


def phase_cron(state):
    p = run(['crontab', '-l'])
    lines = [l for l in p.stdout.splitlines() if l.strip() and not l.startswith('#')]
    check('8 crontab: riga del reset orario della demo identica al README', CRON_LINE in lines, lines)
    log = HOME / 'domains/demo.rolewarden.com/logs/reset.log'
    info('8 reset.log', {'esiste': log.exists(), 'ultime_righe': log.read_text(errors='replace').splitlines()[-6:]
                         if log.exists() else []})


def phase_cronwatch(state):
    """Osserva il reset orario vero della demo: dal secondo 50 del minuto 59 per due minuti."""
    log = HOME / 'domains/demo.rolewarden.com/logs/reset.log'
    size = log.stat().st_size if log.exists() else 0
    t = time.localtime()
    wait = ((59 - t.tm_min) % 60) * 60 + 50 - t.tm_sec
    assert 0 <= wait <= 40 * 60, 'troppo lontano dal minuto 0: ' + str(wait)
    print('[attesa] secondi al reset orario:', wait, flush=True)
    time.sleep(wait)
    poller = Poller('demo', 0.5)
    poller.start()
    time.sleep(130)
    poller.stop.set()
    poller.join()
    report_503('8 demo reset orario (cron)', poller.seen)
    check('8 reset.log scritto dal cron', log.exists() and log.stat().st_size > size)
    info('8 reset.log, righe nuove', log.read_bytes()[size:].decode(errors='replace').splitlines()[-6:]
         if log.exists() else [])
    fp = fingerprint('demo')
    check('8 demo dopo il reset orario: stesse righe del primo deploy', fp == state['baseline_demo'])
    check('8 demo dopo il reset orario: commit 48971ef', head('demo') == COMMIT, head('demo'))
    status, _, home = Client('demo').req('/')
    check('8 demo dopo il reset orario: home 200 identica', status == 200 and home == state['home_demo'])


def phase_final(state):
    restore_origin(state)
    away_from_cron()
    for env in ENVS:
        p = script_ops('deploy', env)
        check(f'final {env}: deploy.sh esce con 0', p.returncode == 0, p.returncode)
        check(f'final {env}: commit pubblicato 48971ef', head(env) == COMMIT, head(env))
        check(f'final {env}: working tree pulito', worktree_clean(env))
        check(f'final {env}: origin invariato', git(env, 'remote', 'get-url', 'origin') == state['origin_url_' + env])
        check(f'final {env}: .env invariato (sha256)', sha256(APP[env] / '.env') == state['env_sha_' + env])
        fp = fingerprint(env)
        check(f'final {env}: stesse righe del primo deploy', fp == state.get('baseline_' + env))
        state['final_rows_' + env] = {k: len(v) for k, v in fp.items()}
        status, _, home = Client(env).req('/')
        check(f'final {env}: home 200 identica a quella dopo il primo deploy',
              status == 200 and home == state.get('home_' + env))
    extra = [f for f in os.listdir(SINK) if f not in state['sink_before']]
    for f in extra:
        (SINK / f).unlink()
    check('final: mail sink riportato allo stato iniziale', sorted(os.listdir(SINK)) == state['sink_before'],
          {'rimossi': len(extra)})
    check('final: databases visibili invariati', databases() == state['databases_before'])
    info('stato finale', {'commit': {e: head(e) for e in ENVS},
                          'righe': {e: state.get('final_rows_' + e) for e in ENVS}})


PHASES = {'pre': phase_pre, 'tls': phase_tls, 'deploy': phase_deploy, 'reset': phase_reset, 'mail': phase_mail,
          'http': phase_http, 'ff': phase_ff, 'envguard': phase_envguard, 'cron': phase_cron, 'final': phase_final, 'cronwatch': phase_cronwatch}


def main():
    wanted = sys.argv[1:] or list(PHASES)
    assert all(w in PHASES for w in wanted), wanted
    state = {}
    if wanted[0] != 'pre' and STATE.exists():
        state = json.loads(STATE.read_text(encoding='utf-8'))
        results.extend(state.pop('_results', []))
        responses.extend(state.pop('_responses', []))
        commands.extend(state.pop('_commands', []))
    try:
        for w in wanted:
            print('== fase', w, time.strftime('%H:%M:%S'), flush=True)
            PHASES[w](state)
    except Exception as error:
        results.append({'esito': 'BLOCCATO', 'controllo': 'Esecuzione interrotta: '
                        + type(error).__name__ + ': ' + str(error)[:300]})
        print(results[-1], flush=True)
        if 'origin_url_staging' in state:
            restore_origin(state)
    finally:
        output = {'conteggi': dict(Counter(r['esito'] for r in results)),
                  'numero_risposte_http': len(responses), 'controlli': results,
                  'comandi': commands, 'risposte': responses}
        state.update(_results=results, _responses=responses, _commands=commands)
        STATE.write_text(json.dumps(state), encoding='utf-8')
        OUTPUT.write_text(json.dumps(output, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
        print(json.dumps(output['conteggi']), 'risposte HTTP:', len(responses), flush=True)
    return 1 if any(r['esito'] in ('FAIL', 'BLOCCATO') for r in results) else 0


if __name__ == '__main__':
    sys.exit(main())
