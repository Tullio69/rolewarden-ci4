# S2 — Passo 2 della roadmap di pubblicazione (verifiche tecniche): collaudo indipendente

## Riverifica f5027a3 — 2026-10-18, Collaudatore ad Hoc (Claude), commit `f5027a3` (main; correzione di D1 in `c60264b`) e zip `build/*-1.0.0-rc.s2b.zip`

**Esito: PASS. S2 approvata per il collaudo.**
- D1 è corretto.
- La regressione richiesta è a zero fallimenti, con un adattamento in più autorizzato dall'autore (P06d) e uno reso necessario dalla correzione stessa (V4, vedi sotto).
- Le ambiguità A1-A4 restano aperte e non decise: le porta l'autore all'utente. Le osservazioni non cambiano.

Stesso protocollo e stesso perimetro di lettura del primo giro. Dei file cambiati ho solo l'elenco dato dall'autore (`src/Account/Sessions.php`, `src/Account/SecurityMail.php` e un test unitario), senza averli aperti.

| Suite su `f5027a3` / rc.s2b | development | production |
| --- | ---: | ---: |
| Pacchetti (`verify-s2.package.py f5027a3 rc.s2b`) | **30/30** | |
| App completa da zip, fasi I, U, E, T, **K** (nuova), L: PHP 8.3 / 8.4 / 8.5 | **53/53** ×3, 2 AMB (A1, A2) | |
| V8 P/E sugli zip, `verify-s2.v8adapted.php` (P06d adattato) | **36/36** | |
| V7 adattata (`verify-v8b.v7adapted.php`) sugli zip | 181/181, 1 AMB (A1 di V7, già decisa) | |
| S1 fasi N, Q, Y | **85/85** | **85/85** |
| V2 principale / extra / adapt | 151/151 + 41 + 16 | 169/169 + 54 + 16 |
| V4 adattata, con `verify-s2.v4adapted-d1.recheck.php` | **232/232** | **232/232** |

Uscite:
- `verify-s2.k{83,84,85}.f5027a3.*`, `verify-s2.package.f5027a3.rc.s2b.output.txt`;
- `verify-s2.v8zip.f5027a3.rc.s2b.adapted.*`, `verify-s2.v7.f5027a3.rc.s2b.adapted.*`, `verify-s2.s1.f5027a3.NQY.*`;
- `verify-s2.{test,extra,adapt,v4}.{development,production}.f5027a3.*`;
- il primo giro di V4 con il vecchio adattamento: `verify-s2.v4.*.f5027a3.adapted-728d7a7.*`.

V2, V4 e S1 girano sul modulo `git archive f5027a3`, identico allo zip per P01.

### D1 (T01 e fase K)

**T01** passa su PHP 8.3, 8.4 e 8.5. «Unknown Platform» non compare più.

**Fase K, nuova.** Due account, uno in inglese e uno in italiano. Ognuno entra prima da un browser normale, poi con lo User-Agent `S2-Agent-A` (nessun sistema riconoscibile) e infine con uno User-Agent vuoto. I testi di riserva di CodeIgniter (`Unknown Platform`, `Unknown Browser`, `Unknown Mobile`, `Unknown Robot`, `Unidentified User Agent`) si cercano con le maiuscole esatte del framework.

| Controllo | Esito ×3 versioni |
| --- | --- |
| K01: email «nuovo dispositivo» (en e it, User-Agent senza sistema e vuoto), nessun testo di riserva di CodeIgniter | PASS |
| K02: pagina delle proprie sessioni dell'utente, en e it | PASS |
| K03: pagina utente vista dall'admin in inglese e in italiano, per i due account | PASS |
| K04: elenco sessioni ed email italiane senza parole che esistono solo nei file inglesi | PASS |

Cosa si vede ora:
- **Pannello.** «Browser sconosciuto su sistema sconosciuto» in italiano, «Unknown browser on unknown system» in inglese (testo del modulo). Lo stesso testo compare sia con lo User-Agent senza sistema sia con quello vuoto.
- **Email con User-Agent senza sistema.** «Device: S2-Agent-A» / «Dispositivo: S2-Agent-A», cioè lo User-Agent così com'è.
- **Email con User-Agent vuoto.** La riga è vuota: «Device:» / «Dispositivo:». È l'osservazione O7 qui sotto.

### Adattamenti di questa riverifica

- **P06d di V8**, in `verify-s2.v8adapted.php` (copia di `verify-v8.php`; cambia solo P06d).
  - L'autore conferma che l'aspettativa è superata dalla decisione O7 di V8: la frase «open it in a browser from the module folder» del README alla radice dell'app completa è corretta.
  - Un file nominato in quella frase è accettato solo se esiste in `modules/rolewarden/`, e `documentation/design-guide.html` c'è.
  - Ora la P/E è 36/36.
- **I 4 controlli V4 sull'email «markup»** (R-CONTENT, R-BODY, R-D2-TEXT, R-D2-HTML), in `verify-s2.v4adapted-d1.recheck.php`, copia dell'adattamento del primo giro.
  - Con il vecchio adattamento davano 228/232 nei due ambienti.
  - L'unica differenza dalla cattura di riferimento è la riga del dispositivo: era «Device: Opera / Unknown Platform», ora è «Device: Opera». È proprio la correzione di D1: la cattura conteneva il difetto.
  - La copia toglie solo « / Unknown Platform» da entrambi i lati; ogni altra parola resta confrontata.
  - Ora la V4 è 232/232.

### Osservazione nuova (non contata)

- **O7.** Con uno User-Agent vuoto, l'email «nuovo dispositivo» ha la riga del dispositivo vuota («Device:» / «Dispositivo:»), mentre il pannello mostra «sistema sconosciuto». Non contiene testo inglese di CodeIgniter. La SPEC non dice cosa debba mostrare l'email in questo caso.

### Pulizia

- **Database.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` nelle 19 esecuzioni su `f5027a3`, registrate in `verify-s2.environment.txt`.
- **Mailpit** vuoto.
- **Docker.** Immagini `s2-col-php:*` ricostruite per la riverifica e poi rimosse, con la rete `s2-net` e i container `s2-*`.
- **Processi.** Nessun processo mio attivo.
- **File.** Cancellati gli zip estratti e il tar.
- **Da cancellare a mano.** Restano profili Chrome non cancellabili in `scratchpad/s2reg/` (`reg-v5-*`, `reg-v6-*`, `s1-*`, `s1f-*`, `v8b-*`).

Nessun commit, nessun push.

---

## Primo giro — 728d7a7

**Collaudatore ad Hoc (Claude), 2026-10-18, commit `728d7a7` (main) e zip `build/rolewarden-{app,module,demo}-1.0.0-rc.s2.zip`.**

## Esito

**FAIL. S2 non approvata per il collaudo, per un solo difetto minore (D1).**

Tutto il resto delle sei verifiche passa:
- applicazione completa su PHP 8.3, 8.4 e 8.5;
- modulo da solo in un'app CI4 nuova, con CI 4.7.4 e 4.7.5;
- sottocartella, con link simbolico e con Alias;
- reverse proxy HTTPS, con e senza `$proxyIPs`;
- pacchetti;
- CSRF, escape, hash delle password e assenza di SQL a schermo in production, anche con il database spento;
- regressione.

Quattro ambiguità di specifica sono riportate a parte (A1-A4), non decise e non contate come difetti. Due punti della regressione non sono a zero fallimenti per ragioni già note, spiegate sotto: P06d di V8 (O7) e la Z01 in development (A3).

| # | Punto | Esito | Totali |
| --- | --- | --- | --- |
| 1 | Versioni PHP 8.3 / 8.4 / 8.5, modulo con CI 4.7.4 e 4.7.5 su PHP 8.3 e 8.5 | **PASS** | app: 48/49 ×3 (l'unico FAIL è T01 = D1, punto 5), 45/45 in production; modulo: 24/24, 31/31, 31/31, 24/24 |
| 2 | Sottocartella `/shop/admin/` | **PASS** | link simbolico 40/40, Alias 30/30 |
| 3 | Reverse proxy (nginx HTTPS → Apache) | **PASS** (più A4) | senza `$proxyIPs` 19/19, con `$proxyIPs` 43/43 |
| 4 | Pacchetti | **PASS** | 30/30 |
| 5 | Verifiche della SPEC (SQL, CSRF, escape, hash, stringhe) | **FAIL** (D1); A1, A2, A3 | S01-S05b PASS su 3 versioni; Z01 production PASS, development vedi A3; T01 FAIL ×3 |
| 6 | Regressione | **PASS**, salvo P06d (noto, O7, non adattato) | tabella sotto |

## Ambiente e regole

**Letto.**
- SPEC (verifiche tecniche, decisioni del 2026-10-16), BRIEF v1.0, `_AI-LOG.md`, README, CHANGELOG, THIRD-PARTY-LICENSES.
- `documentation/` (in particolare `installation.html#subfolder-proxy`, `configuration.html`, `troubleshooting.html`) e `composer.json`.
- Gli script e i report di `tests/Integration/`.
- Il contenuto degli zip e l'HTML/CSS/JS serviti.
- I file di lingua, solo come dizionario per il controllo delle stringhe.

**Non aperto.** Nessun controller, model, vista PHP, seeder, `src/Settings`, `src/Models`, test unitario o diff. Non ho aperto il repo `../rolewarden-demo`.

**Database.**
- Solo `rolewarden_test` su 127.0.0.1:3317, raggiunto dai container come `host.docker.internal:3317`.
- Credenziali solo come variabili di processo. La password del database coincide con la parola «rolewarden»: per questo nelle uscite redatte compare `[omesso]` al posto di «rolewarden» (anche negli URL).
- La prova a database spento usa un MariaDB mio (`s2-db`).
- Snapshot e ripristino a ogni esecuzione: 63 esecuzioni registrate in `verify-s2.environment.txt`, tutte `identical=True` con l'hash `f7f37c04…e21e64`. In più tre esplorazioni manuali, ripristinate e verificate a mano.

**Container.**
- `s2-web`: immagini mie `s2-col-php:8.3/8.4/8.5`, costruite da `php:<v>-apache` ufficiali con `verify-s2.docker/Dockerfile` (intl, mysqli, mod_rewrite, Composer). In `php.ini`: `error_reporting=-1`, `log_errors=On` su file.
- `s2-proxy`: `nginx:alpine` con certificato autofirmato generato al volo nello scratchpad e poi cancellato.
- `s2-db`: `mariadb:11`.
- Rete `s2-net`.
- Un gruppo di container alla volta. Tutti rimossi, con immagini e rete.

**Email.** Solo Mailpit, indirizzi `@s2.test` ed `@example.com`. Mailpit vuoto alla fine.

**Configurazione di prova.** `rolewarden.signInRate = 100` in `.env`, per non far scattare il limite per IP con le decine di accessi della suite (la fase X lo riporta a 5 dalla schermata Settings).

**Script.**
- `verify-s2.php`: fasi I, U, E, D, S, T, L, X0, X1, Z e M.
- `verify-s2.run.py`: gli scenari Docker.
- `verify-s2.package.py`: i pacchetti.
- `verify-s2.docker/`: Dockerfile, virtual host, nginx, `module-app.sh` e `module-wire.php`, che costruiscono l'app CI4 nuova seguendo `installation.html#module` e `#wiring`.
- Regressione: `verify-s2.regression-suites.sh`, `verify-s2.suite.py`, `verify-s2.s1.php`, `verify-s2.suite7.py`, `verify-s2.regress.py`, `verify-s2.regression-all.sh` e le tre copie adattate `verify-s2.{v2,v5}adapted.php`, `verify-s2.v4adapted.recheck.php`.

## 1. Versioni

**Applicazione completa dallo zip** (`v83`, `v84`, `v85`; `CI_ENVIRONMENT = development` scritto prima dell'installer, che lo conserva come documentato). Ogni giro fa queste cose:
- installer via HTTP (codice letto dal server, database, tabelle, primo super admin), lock scritto e codice cancellato, rifiuto di una seconda esecuzione;
- le 15 schermate del pannello e gli asset (CSS, JS, font);
- utente creato dal pannello; ruolo creato; permesso concesso nella matrice;
- ruolo assegnato; override negativo che nega la schermata e la restituisce quando viene tolto;
- disattivazione che nega a partire dalla richiesta successiva, poi riattivazione;
- un valore cambiato in Settings; voci nel registro attività; selettore della lingua con ritorno alla stessa pagina;
- email di sicurezza: password cambiata, nuovo dispositivo, blocco per troppi tentativi (all'utente e a chi ha `security.alerts`);
- `demo:install`, la demo usata, `demo:uninstall`;
- le fasi S e T del punto 5;
- log.

| Giro | PHP | Esito | Log |
| --- | --- | --- | --- |
| `v83` | 8.3.35 | 48/49, 2 AMB | L01-L05 PASS: log dell'app a soglia 9 senza WARNING né ERROR, `php-errors.log` vuoto, output di Apache e del container puliti, nessun 500 |
| `v84` | 8.4.26 | 48/49, 2 AMB | come sopra |
| `v85` | 8.5.11 | 48/49, 2 AMB | come sopra |
| `v85prod` | 8.5.11, production lasciato dall'installer | 45/45 | come sopra |

Il FAIL è sempre T01, cioè D1 (punto 5). CodeIgniter 4.7.5 e Shield 1.4.1 sono quelli dello zip.

**Modulo da solo** (`installation.html#module`), in un'app nuova creata con `composer create-project codeigniter4/appstarter:<v>`, framework fissato con `composer require codeigniter4/framework:<v>`. Poi:
- Shield e `shield:setup`;
- lo zip del modulo in `modules/rolewarden` come repository di tipo path, più `codeigniter4/translations`;
- il wiring di `#wiring`: `userProvider`, vista e redirect del login, `RouteRegistrar`, `csrf` nei filtri globali.

| Giro | CI | PHP | Come | Esito |
| --- | --- | --- | --- | --- |
| `m474p83` | 4.7.4 | 8.3 | migrazioni a mano in tre passi, seed, `shield:user create/activate` e l'INSERT documentato | 24/24 |
| `m474p85` | 4.7.4 | 8.5 | installer, con il passo database saltato perché l'app è già collegata | 31/31 |
| `m475p83` | 4.7.5 | 8.3 | installer | 31/31 |
| `m475p85` | 4.7.5 | 8.5 | a mano | 24/24 |

Tutti i giri del modulo passano anche L01-L05.

Nota sui giri con l'installer: l'installer si apre in production e poi l'app torna in development (osservazione O1).

## 2. Sottocartella

`http://localhost:8091/shop/admin/`, come dice `installation.html#subfolder`:
- `sub`: link simbolico a `public/`, development, vetrina accesa;
- `alias`: `Alias /shop/admin` di Apache.

Risultati:
- **Installer.** Propone `http://localhost:8091/shop/admin/` e lo scrive in `app.baseURL` (I05, I06).
- **Pannello.** Login e redirect di Shield, 15 schermate, CSS, JS e font, form, toast, selettore della lingua (campo `back`): tutto dentro la sottocartella. U03 controlla link, asset, `action`, `formaction`, attributi `data-*` e URL negli script, che non escono mai.
- **Email.** I link delle email puntano a `…/shop/admin/index.php/rolewarden/…`.
- **Demo e vetrina.** Il cambio di account resta dentro e cambia l'account (D04).
- **Redirect.** L05 ne conta 28 in `sub` e 14 in `alias`, tutti dentro la sottocartella.
- **Esiti.** `sub` 40/40, `alias` 30/30 (I, U, L).

Gli URL contengono `index.php/` perché l'app starter ha `indexPage = 'index.php'`: per questo funziona anche senza `RewriteBase`, con l'Alias.

## 3. Reverse proxy

nginx su `https://localhost:8443/` termina HTTPS e inoltra ad Apache con queste intestazioni:
- `X-Forwarded-For $proxy_add_x_forwarded_for`;
- `X-Forwarded-Proto $scheme`;
- `Host $http_host`.

Il proxy ha l'indirizzo fisso 172.30.5.10. I «visitatori» sono distinti dall'`X-Forwarded-For` che mandano, come farebbe un proxy a monte. Un client senza intestazione è l'host Docker, cioè 172.30.5.1 visto dal proxy.

**Senza `$proxyIPs` (`x0`, 19/19): cosa succede, come lo documenta `installation.html#proxy`.**
- L'installer propone `http://localhost:8443/`, perché CodeIgniter non crede a `X-Forwarded-Proto`. Con quell'indirizzo il browser finisce su HTTP verso la porta HTTPS: errore 400 di nginx.
- Ho inserito `https://` a mano, come dice la documentazione («Keep app.baseURL on https://»).
- Ogni accesso, sessione e voce risulta da 172.30.5.10, l'indirizzo del proxy (X001).
- Con il limite a 5 tentativi al minuto, il visitatore A si blocca e con lui anche il visitatore B, che ha un altro indirizzo (X002).
- I cookie non hanno `Secure` (`cookie.secure` non è impostato).

**Con `$proxyIPs = ['172.30.5.10' => 'X-Forwarded-For']` (`x1`, 43/43), scritto in `app/Config/App.php` prima dell'installer, più `cookie.secure = true`.**

| Controllo | Esito |
| --- | --- |
| X101: l'installer scrive `app.baseURL = 'https://localhost:8443/'` | PASS |
| X102: il redirect dopo il login è in https, dentro l'app | PASS |
| X103: cookie di sessione e di remember-me con `secure; HttpOnly; SameSite=Lax`, `path=/` | PASS |
| X104, X105: login, sessioni (anche nella pagina) e registro attività con l'indirizzo vero (203.0.113.10). 172.30.5.10 non compare mai, nemmeno in `auth_logins`, `acl_sessions` o `acl_activity_log` | PASS |
| X106: il visitatore A si ferma al limite, il B con un altro indirizzo entra | PASS |
| X107: le email di sicurezza nominano l'indirizzo del visitatore, mai quello del proxy | PASS |
| X108: un `X-Forwarded-For: 192.0.2.77` mandato direttamente ad Apache (client non proxy) è ignorato | PASS |

Vedi anche l'ambiguità A4.

## 4. Pacchetti (`verify-s2.package.py`, 30/30)

**Identità con il repository.**
- Modulo = `git archive 728d7a7`, file per file (P01).
- Demo = `git archive 728d7a7:demo` (P02).
- Nell'applicazione completa, `modules/rolewarden` e `modules/rolewarden-demo` sono identici agli archivi.
- CHANGELOG e THIRD-PARTY-LICENSES sono uguali a quelli del modulo.
- Il README dell'app è quello del modulo con il solo percorso `modules/rolewarden/documentation/` adattato.

**Niente file di sviluppo o personali.**
- Nessun `.env`, log, dump, `_AI-LOG.md`, `CLAUDE.md`, `AGENTS.md`, `docs/`, `docker/`, `tests/`, binario di Tailwind o file di IDE.
- `writable/` contiene solo i segnaposto. Il file `env` ha ogni riga commentata.
- `vendor/` è senza pacchetti di sviluppo, e le sue cartelle coincidono con `installed.json`.
- Nessuna chiave privata, chiave di cifratura o token.
- La password del database non compare come valore.
- Nessun percorso, nome, porta locale, remote git o strumento dell'autore.
- Negli URL dei file RoleWarden ci sono solo il sito del prodotto, domini di esempio e riferimenti pubblici. Quelli dell'app starter e di `composer.lock` sono elencati nell'uscita.

**Licenze.** THIRD-PARTY-LICENSES elenca ogni pacchetto di `vendor/` con versione e licenza esatte (framework 4.7.5, shield 1.4.1, settings 2.4.0, translations 4.7.4, laminas-escaper 2.18.0, psr/log 3.0.2, autoloader di Composer), e ognuno ha il proprio LICENSE. Per gli asset:
- Alpine 3.17.4, con il testo MIT accanto;
- IBM Plex, 6 file woff2, con il testo OFL 1.1 accanto;
- l'avviso di Tailwind 4.3.3 in `panel.css`;
- nessun altro asset di terzi;
- il testo MIT presente;
- il BSD-3 di laminas presente;
- il LICENSE dell'app starter alla radice.

**Requisiti.** `php ^8.3`, `codeigniter4/framework ^4.7.4`.

**Note (non difetti).**
- Alcuni file del repository hanno fine riga CRLF.
- Il `composer.json` dell'applicazione completa chiede `codeigniter4/framework ^4.7`, mentre il modulo chiede `^4.7.4`. Il lock è su 4.7.5.

## 5. Verifiche della SPEC

**SQL a schermo (S05, S05b, Z, L04).**
- 39 percorsi con input strano: virgolette, id enormi, negativi o testuali, array nei parametri, path traversal sugli asset, POST con id falsi alle rotte AJAX. Nessun testo SQL, errore PHP o valore di configurazione a schermo, nessun 500, su PHP 8.3, 8.4 e 8.5 in development e su 8.5 in production.
- Lo stesso vale per le 330 risposte circa di ciascun giro (L04).
- Il `BadRequestException` di CodeIgniter per «disallowed characters» (`/users/1'`) finisce nel log come CRITICAL: provocato dalla suite, lo rifiuta il framework.

**Database spento a metà uso (`zdev`, `zprod`).** Login fatto, raccolti 15 form, poi `docker stop s2-db`. Seguono 60 richieste: 15 schermate, home, login, register, installer, demo, logout, ogni form in POST e un login, sia autenticato sia anonimo.
- **production:** pagine 500 generiche di CodeIgniter, nessun dettaglio (Z01 PASS). Il motivo va in `writable/logs` (Z02). Riacceso il database, l'app riparte da sola (Z03). 21/21.
- **development:** è la pagina di errore di sviluppo di CodeIgniter a mostrare `DatabaseException: Unable to connect to the database. Main connection [MySQLi]: …` (Z01 FAIL). Vedi A3.

**CSRF (S01, S02).**
- 28 form POST distinti visti tra pannello, demo, login, register, magic link e installer: tutti hanno il token.
- Ognuno inviato senza token e con un token sbagliato è rifiutato (403 in development) e non cambia nulla. Il confronto è sullo stato completo delle tabelle (colonne volatili escluse) più i file di `writable/rolewarden`.
- Lo stesso per le rotte AJAX della matrice, degli override e delle assegnazioni.

**Escape (S03).**
- Markup inserito in nome e descrizione di un ruolo (`<script>`, `"><img onerror>`, `'><svg onload>`), nello username dell'utente (accettato, vedi O4) e in titolo e testo di un documento della demo.
- Nessuna occorrenza grezza nelle pagine del pannello viste in inglese e in italiano, registro attività e demo comprese.

**Password (S04).** Tutte le password salvate (installer, pannello, profilo, account della demo) sono bcrypt con costo 12. È l'hash di Shield (`Config\Auth::$hashAlgorithm = PASSWORD_DEFAULT`, `$hashCost = 12`), e `password_verify` lo conferma.

**Stringhe (T01-T04).** Il dizionario sono le parole che compaiono solo nei file di lingua inglesi del modulo e della demo: 510 su 581 stringhe. Ho cercato quelle parole nel pannello italiano: 18 pagine, la demo, il login, l'installer e gli attributi `title`, `aria-label`, `placeholder` e `alt`. In più ho cercato 40 parole inglesi comuni d'interfaccia e ho controllato un'email di sicurezza italiana. Sono esclusi gli slug, gli indirizzi, i dati scritti dalla suite, i documenti di esempio (contenuto, SPEC 2026-10-16) e le voci del registro scritte quando il pannello era in inglese (SPEC: il registro conserva le etichette del momento).
- **T02**, nessuna delle 40 parole comuni: PASS.
- **T03**, email italiana: PASS.
- **T04**, voci scritte in italiano: PASS, a parte le chiavi dei campi (A2).
- **T01**: FAIL per **D1**. L'unico resto, le intestazioni delle colonne della matrice, è riportato come A1.

## 6. Regressione

Una suite alla volta, sugli zip veri dove previsto.

| Suite su `728d7a7` | development | production |
| --- | ---: | ---: |
| S1 completa (`verify-s2.s1.php` = `verify-s1.php` con le uscite rinominate; fasi N, Q, Y, A, T, D, O, W, B, G) | **141/141** | **141/141** |
| V8b, fasi L, M (copia adattata di S1), P, I, G | 116/116, 1 AMB (LA1, già superata) | 116/116, 1 AMB |
| V8 A-D, F, G (l'ambiente lo sceglie la suite) | 195/195, 2 AMB (A15c, G03, già decise) | |
| V8 P/E sugli zip rc.s2 | 35/36: P06d (O7) | |
| V7 (`verify-v8b.v7adapted.php`) sugli zip rc.s2 | 181/181, 1 AMB (A1 di V7, già decisa) | |
| V6 | 151/151 | 151/151 |
| V5 adattata | **177/177** | **177/177** |
| V4 adattata | **232/232** | **232/232** |
| V2 adattata / extra / adapt | **151/151** + 41 + 16 | **169/169** + 54 + 16 |
| V3 principale / supplementare / filtri array | 139 + 81 + 55 | 139 + 81 + 55 |

**Adattamenti (file nuovi; nessun altro controllo toccato).**
- **V4: i 25 controlli R-TZ, R-CONTENT, R-BODY, R-D2-TEXT e R-D2-HTML** (5 email × 5 controlli), in `verify-s2.v4adapted.recheck.php`.
  - Cercavano la data ISO (`2026-10-18 10:46 UTC`). Sono superati dalla decisione di V7: le date nelle email sono nella lingua del lettore («18 Oct 2026, 10:46 UTC»).
  - L'adattamento accetta le due forme della data e la normalizza nello stesso segnaposto.
  - Per R-D2-TEXT riduce gli spazi a uno solo, perché la data più lunga sposta gli a capo del testo semplice. Parole e ordine devono restare identici.
  - Ora passano tutti, nei due ambienti.
- **G07 di V2**, in `verify-s2.v2adapted.php`. «Nessun pulsante di invio nella pagina Settings in sola lettura» contava ogni `type="submit"` della pagina. È superato dai selettori della barra in alto: chiaro/scuro (V5) e lingua (V8b). L'adattamento esclude solo i form `appearance/mode` e `profile/language`.
- **G07v5 di V5**, in `verify-s2.v5adapted.php`. Escludeva già il form `appearance/mode`. Superato dal selettore della lingua della V8b, che ora è escluso allo stesso modo.

**Non adattato: P06d di V8.**
- «Il README alla radice dell'app completa nomina solo file che esistono da lì»: nomina `documentation/design-guide.html` con la frase «from the module folder».
- La V8 l'aveva già giudicata corretta (O7), e da allora fallisce in ogni giro sugli zip, come in V8b rc.v8c.
- Non è nell'elenco degli adattamenti autorizzati, quindi resta FAIL. Se l'autore conferma O7, va aggiunto all'elenco.

## Difetti

**D1, minore. Testo inglese di CodeIgniter nel pannello italiano: «Browser sconosciuto su Unknown Platform»** (T01, su PHP 8.3, 8.4 e 8.5).

Riproduzione:
1. Accedere con uno User-Agent senza una piattaforma riconoscibile, per esempio `S2-Agent-A` (succede con client a riga di comando e bot).
2. Come admin in italiano, aprire la pagina dell'utente (`/rolewarden/users/{id}`, elenco delle sessioni).
3. Il dispositivo appare come «Browser sconosciuto su Unknown Platform».

«Browser sconosciuto» viene dal file di lingua; «Unknown Platform» è il valore di riserva di `UserAgent::getPlatform()` di CodeIgniter, mostrato così com'è. La SPEC chiede tutte le stringhe dell'interfaccia nei file di lingua. Con un browser vero la piattaforma è riconosciuta e il problema non si vede.

## Ambiguità di specifica (non decise)

- **A1. Intestazioni delle colonne della matrice in italiano.**
  - Le colonne si chiamano con la parte azione dello slug: `view`, `create`, `update`, `delete`, `approve`, `override`, `assign`, `activate`, `import`, `alerts`, `revoke`.
  - È uno «slug tecnico», ammesso dalla SPEC, o una stringa d'interfaccia da tradurre come i nomi delle aree (decisione S1)?
- **A2. Colonna «dettagli» del registro attività.**
  - Le voci mostrano `chiave: vecchio → nuovo` con le chiavi del codice, anche per azioni fatte con il pannello in italiano: `lock_minutes: 15 → 16`, `permission: activity.view`, `username: …`.
  - La SPEC dice che il registro conserva le etichette del momento, ma non dice se queste chiavi siano etichette o identificatori tecnici.
- **A3. Errori SQL a schermo «in nessun ambiente» e database spento in development.**
  - Con il database irraggiungibile, in development, la pagina d'errore di sviluppo di CodeIgniter (non una pagina del modulo) mostra `DatabaseException` con il messaggio del driver.
  - In production la pagina è generica. Va deciso se il vincolo copre anche la pagina di debug del framework in development.
- **A4. Fiducia nell'`X-Forwarded-For` dietro il proxy.**
  - Con `$proxyIPs` come in `installation.html#proxy` e l'inoltro standard di nginx (`$proxy_add_x_forwarded_for`), CodeIgniter prende il primo indirizzo della catena, cioè quello che il visitatore stesso può scrivere.
  - Un client può quindi presentarsi con indirizzi sempre diversi e aggirare il limite per indirizzo. X106 lo dimostra in senso inverso: due «visitatori» distinti solo dall'intestazione che mandano.
  - La documentazione non dice che il proxy più esterno deve sostituire l'intestazione (`$remote_addr`, o `real_ip`). Va deciso se aggiungerlo.

## Osservazioni (non contate)

- **O1. Modulo in un'app esistente in development, prima delle migrazioni.** Ogni pagina, installer compreso, risponde 500 (`Table 'settings' doesn't exist`). Viene dal collector della debug toolbar di Shield (`Shield\Collectors\Auth` → `setting()`), non dal modulo. In production l'installer si apre e funziona. Potrebbe valere una riga in `troubleshooting.html`.
- **O2.** Senza `$proxyIPs` l'installer dietro proxy HTTPS propone `http://`. È coerente con la documentazione, che chiede di tenere `https://`.
- **O3. La schermata Settings rifiuta il salvataggio** se un valore da `.env` è fuori dal suo intervallo (`signInRate = 1000`, ammessi 5-100). Il messaggio sta accanto al campo. L'ho visto con la configurazione di prova della suite.
- **O4.** Lo username modificato dal pannello accetta `'><svg onload=alert(33)>` (regole di Shield). È mostrato escapato ovunque.
- **O5.** L'oggetto delle email usa l'host di `app.baseURL` («on localhost»). Il testo semplice delle email non contiene i link, l'HTML sì.

## Cosa non ho potuto provare

- **Controllo visivo in un browser vero**, dovuto all'autore.
- Hosting reali: hosting condiviso, PHP-FPM, nginx senza Apache, IIS.
- Catene di più proxy e load balancer, IPv6.
- SMTP vero con TLS.
- Versioni CI fra 4.7.4 e 4.7.5 diverse da queste due; il modulo da solo su PHP 8.4 (richiesti 8.3 e 8.5).
- I font e l'aspetto con Chrome headless li ha verificati solo la regressione S1 (fase Y).

## Pulizia

- **Database.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` alla fine e in tutte le esecuzioni (`verify-s2.environment.txt`).
- **Mailpit** vuoto.
- **Docker.** Container `s2-*`, immagini `s2-col-php:*` e rete `s2-net` rimossi. Restano accesi solo i container che c'erano: `rolewarden-db`, `rolewarden-mail` e gli altri progetti, mai toccati. Le immagini `s2-apache:8.5` e `s2-php:*` c'erano già prima del collaudo: non sono mie e non le ho toccate.
- **Processi.** Nessun processo mio attivo.
- **File.** Il certificato e la chiave del proxy sono cancellati, come gli `.env` temporanei e le copie degli zip.
- **Da cancellare a mano.** In `scratchpad/s2reg/` restano profili Chrome che Windows non lascia cancellare: `reg-v5-*`, `reg-v6-*`, `s1-*`, `v8b-*`.
- **Uscite.** Tutte in `tests/Integration/verify-s2.*`. I giri di messa a punto (`try1`-`try3`) sono stati spostati nello scratchpad, ma le loro righe restano in `verify-s2.environment.txt`.

Nessun commit, nessun push.
