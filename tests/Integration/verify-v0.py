"""Collaudo HTTP V0 parziale: python tests/Integration/verify-v0.py.

Usa curl, nessun file di cookie o credenziali. Non esegue deploy/reset:
le prove distruttive richiedono accesso Docker e ripristino garantito.
"""
import hashlib
import html
import json
import re
import subprocess
import sys
from collections import Counter
from pathlib import Path
from urllib.parse import urlencode, urlsplit

results = []
requests = []


def check(label, condition):
    results.append({'esito': 'PASS' if condition else 'FAIL', 'controllo': label})
    print(f"[{results[-1]['esito']}] {label}")


def request(host, path, cookies=None, data=None):
    assert host in ('demo.localhost:8090', 'staging.localhost:8090')
    if path.startswith('http'):
        url = urlsplit(path)
        assert url.netloc == host
        path = url.path + ('?' + url.query if url.query else '')
    args = ['curl.exe', '--noproxy', '*', '-sS', '--max-time', '15', '-i',
            '-H', 'Host: ' + host]
    if cookies:
        args += ['-H', 'Cookie: ' + '; '.join(k + '=' + v for k, v in cookies.items())]
    if data is not None:
        args += ['--data-binary', '@-', '-H', 'Content-Type: application/x-www-form-urlencoded']
    args += ['http://127.0.0.1:8090' + path]
    run = subprocess.run(args, input=urlencode(data) if data is not None else None,
                         capture_output=True, text=True, encoding='utf-8')
    if run.returncode:
        raise RuntimeError('curl non riuscito, codice ' + str(run.returncode))
    headers, body = run.stdout.split('\n\n', 1)
    status = int(headers.splitlines()[0].split()[1])
    fields = {}
    for line in headers.splitlines()[1:]:
        key, _, value = line.partition(':')
        fields[key.lower()] = value.strip()
        if cookies is not None and key.lower() == 'set-cookie':
            name, value = value.strip().split(';', 1)[0].split('=', 1)
            cookies[name] = value
    label = f'{host} {"POST" if data is not None else "GET"} {path} ({status})'
    check(label + ': noindex', fields.get('x-robots-tag') == 'noindex, nofollow')
    error = re.search(r'SQLSTATE\[|Fatal error:|Parse error:|Warning:|Uncaught\s|mysqli_sql_exception|Stack trace:|CodeIgniter\\Database\\Exceptions', body, re.I)
    check(label + ': nessuna diagnostica PHP/SQL riconoscibile', error is None)
    requests.append({'richiesta': label, 'status': status,
                     'x_robots_tag': fields.get('x-robots-tag'),
                     'sha256_corpo': hashlib.sha256(body.encode()).hexdigest()})
    return status, fields, body


def main():
    for host in ('staging.localhost:8090', 'demo.localhost:8090'):
        status, _, home = request(host, '/')
        check(host + ': home disponibile', status == 200)
        accounts = re.findall(r'<tr><td><code>([^<]+)</code></td><td><code>([^<]+)</code></td><td>([^<]+)</td></tr>', home)
        check(host + ': account pubblici presenti', len(accounts) >= 2)
        status, _, body = request(host, '/robots.txt')
        check(host + ': robots vieta tutti i percorsi', status == 200 and bool(re.search(r'User-agent:\s*\*\s+Disallow:\s*/\s*(?:$|\n)', body, re.I)))
        status, _, _ = request(host, '/verify-v0-pagina-inesistente')
        check(host + ': pagina inesistente restituisce 404', status == 404)
        status, headers, _ = request(host, '/rolewarden/users')
        check(host + ': pannello anonimo redirige al login',
              status in (302, 303) and urlsplit(headers.get('location', '')).path == '/login')
        for email, password, role in accounts:
            cookies = {}
            status, _, body = request(host, '/login', cookies)
            token = re.search(r'name="(csrf[^"]*)" value="([^"]+)"', body)
            if not token:
                check(host + ': token CSRF del login disponibile', False)
                continue
            status, headers, body = request(host, '/login', cookies,
                {'email': html.unescape(email), 'password': html.unescape(password),
                 token[1]: token[2]})
            check(host + ' account ' + role + ': login redirige', status in (302, 303))
            destination = headers.get('location', '/')
            for _ in range(5):
                status, headers, body = request(host, destination, cookies)
                if status not in (301, 302, 303, 307, 308):
                    break
                destination = headers['location']
            own_row = next((row for row in re.findall(r'<tr>(.*?)</tr>', body, re.S)
                            if '>you</span>' in row), '')
            check(host + ' account ' + role + ': accesso autenticato al pannello',
                  status == 200 and '<h1>Users</h1>' in body and html.unescape(email) in html.unescape(own_row))
            links = re.findall(r'href="([^"]+)"', body)
            logout = next((html.unescape(link) for link in links if urlsplit(html.unescape(link)).path == '/logout'), None)
            if logout:
                status, _, _ = request(host, logout, cookies)
                check(host + ' account ' + role + ': logout', status in (302, 303))
        status, _, final_home = request(host, '/')
        check(host + ': home finale identica a quella iniziale', status == 200 and final_home == home)


try:
    main()
except Exception as error:
    # Non stampare contenuti HTTP, cookie o argomenti con credenziali.
    results.append({'esito': 'BLOCCATO', 'controllo': 'Esecuzione interrotta: ' + type(error).__name__})
finally:
    output = {'conteggi': dict(Counter(row['esito'] for row in results)),
              'numero_risposte_http': len(requests), 'controlli': results, 'risposte': requests,
              'limite': 'Solo HTTP; Docker non accessibile nella sessione di collaudo.'}
    Path(__file__).with_suffix('.output.json').write_text(json.dumps(output, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')
    print(json.dumps(output['conteggi']), 'risposte HTTP:', len(requests))
sys.exit(1 if any(row['esito'] != 'PASS' for row in results) else 0)
