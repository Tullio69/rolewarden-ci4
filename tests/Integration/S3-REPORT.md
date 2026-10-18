# S3 — Passo 3 della roadmap di pubblicazione (versione 1.0.0 definitiva): collaudo indipendente

**Collaudatore ad Hoc (Claude), 2026-10-18, commit `26784b6` (main) e zip `build/rolewarden-{app,module,demo}-1.0.0.zip`.**

## Esito

**PASS. S3 approvata per il collaudo.**

- I pacchetti definitivi sono quelli collaudati in S2, con il numero di versione 1.0.0 e nessun residuo «rc».
- Le aggiunte alla documentazione dopo S2 sono state provate e funzionano come scritto:
  - indirizzi senza `index.php`;
  - righe nginx per il proxy;
  - voce sul 500 in development;
  - comando Composer `^1.0`.
- Nessun difetto. Nessuna ambiguità di specifica nuova. Due osservazioni non contate (O1, O2).

| # | Punto | Esito | Totali |
| --- | --- | --- | --- |
| 1 | Versione: CHANGELOG datato, 1.0.0 coerente, nessun residuo rc | **PASS** | V01-V08 8/8 (in `verify-s3.package.py`) |
| 2 | Differenze da rc.s2c (`09e9df8` → `26784b6`) | **PASS** | solo file attesi; le aggiunte provate al punto 4 |
| 3 | Pacchetti come S2 punto 4 | **PASS** | P01-P08 30/30; pacchetti V7 15/16 (P04 superato da V8, come in V8b) |
| 4 | Sugli zip veri | **PASS** | V8 P/E 36/36; V7 181/181 (1 AMB già decisa); app PHP 8.3 63/63, PHP 8.5 63/63; modulo CI 4.7.4 / PHP 8.3 con `^1.0` 33/33; `indexPage` 45/45; proxy 43/43 |

Totale controlli contati: **517 PASS, 0 FAIL**, più P04 V7 noto e l'AMB A1 di V7 già decisa. La sensibilità del proxy (39/43, quattro FAIL attesi) non è contata.

## Ambiente e regole

**Letto.**
- `docs/SPEC.md`, `docs/BRIEF-v1.0.md`, `_AI-LOG.md`, README, CHANGELOG, THIRD-PARTY-LICENSES, `documentation/`, `composer.json`, `S2-REPORT.md`.
- Gli script di `tests/Integration/`.
- Il contenuto degli zip e l'HTML servito.

**Non aperto.** Nessun controller, model, vista PHP, seeder, `src/Settings`, `src/Models`, test unitario, né il repo `../rolewarden-demo`. Del diff tra `09e9df8` e `26784b6` ho letto solo i file di documentazione, CHANGELOG, composer.json e SPEC; il resto lo conosco solo dall'elenco (`--stat`).

**Database.**
- Solo `rolewarden_test` su 127.0.0.1:3317, raggiunto dai container come `host.docker.internal:3317`.
- Credenziali solo come variabili di processo; nelle uscite la parola «rolewarden» (coincide con la password) compare come `[omesso]`, come in S2.
- Snapshot e ripristino a ogni esecuzione con database: 10 righe in `verify-s3.environment.txt`, tutte `identical=True` con l'hash `f7f37c04…e21e64`. Verificato a mano anche alla fine.

**Docker.**
- Immagini mie `s3-col-php:8.3` (PHP 8.3.35) e `s3-col-php:8.5` (PHP 8.5.11), da `php:<v>-apache` ufficiali con `verify-s3.docker/Dockerfile` (copia di quello di S2).
- `s3-proxy`: `nginx:alpine`. Rete `s3-net` (172.30.5.0/24).
- Un gruppo di container alla volta. Tutto rimosso alla fine.

**Email.** Solo Mailpit, indirizzi `@s3.test` ed `@example.com`; Mailpit vuoto alla fine.

**Browser.** Chrome headless non è servito: nessuna suite di questo giro lo usa.

## 1. Versione (`verify-s3.package.py`, V01-V08)

| Controllo | Esito |
| --- | --- |
| V01: in `build/` ci sono esattamente `rolewarden-{app,module,demo}-1.0.0.zip` | PASS |
| V02: cartelle radice `rolewarden/`, `rolewarden-demo/`, `rolewarden-app-1.0.0/` | PASS |
| V03: la prima voce del CHANGELOG è `## [1.0.0] - 2026-10-18`, la parola «unreleased» non c'è più | PASS |
| V04: CHANGELOG del modulo, della radice dell'app e di `modules/rolewarden` identici | PASS |
| V05: `"version": "1.0.0"` nel `composer.json` del modulo, nello zip e nell'app completa | PASS |
| V06: `installation.html#module` dà `composer require rolewarden/codeigniter4-rolewarden:^1.0`, nessun `@dev` | PASS |
| V07: nessun residuo pre-release (`1.0.0-rc`, `rc.sN`, `rc.vN`, «unreleased», «release candidate») in nomi o contenuti dei tre zip, fuori da `vendor/` | PASS |
| V08: il commento dello zip del modulo è il commit `26784b6…` | PASS |

Il `composer.json` dell'app completa è quello dell'app starter (`codeigniter4/appstarter`), senza versione: è normale. La documentazione nomina gli zip in forma generica (`rolewarden-app-x.y.z.zip`).

Hash SHA-256 degli zip collaudati (nell'uscita):
- app `db5cadd4…ee87c21`;
- modulo `0af9e322…15a0229`;
- demo `fae0fdd7…960f463a3`.

## 2. Differenze da rc.s2c (`verify-s3.diff-09e9df8-26784b6.txt`)

Commit `5465c64`, `5974036`, `26784b6`. Fuori da `tests/Integration/` cambiano solo:
- `CHANGELOG.md`: la data al posto di «unreleased»;
- `composer.json`: `"version": "1.0.0"`;
- `documentation/installation.html`: sezione `#index-page`, `composer require …:^1.0`, le due righe nginx in `#proxy`;
- `documentation/troubleshooting.html`: voce sul 500 in development prima delle tabelle;
- `docs/SPEC.md`: gli esiti di S2, A3 e A4;
- `docs/COMMERCIALE.md`: nuovo, interno, escluso dallo zip (P04a);
- `_AI-LOG.md`.

Nessun file di `src/` è cambiato: sono tutti file attesi. Le aggiunte sono provate al punto 4.

## 3. Pacchetti

**`verify-s3.package.py`, copia di quello di S2: P01-P08 30/30.**
- **Identità.** Modulo = `git archive 26784b6` file per file; demo = `git archive 26784b6:demo`. Nell'app completa `modules/rolewarden` e `modules/rolewarden-demo` sono identici agli archivi; CHANGELOG, THIRD-PARTY-LICENSES e README sono come in S2.
- **Pulizia.** Nessun `.env`, log, dump, nota AI, `docs/`, `tests/`, strumento di build o file di IDE. `writable/` ha solo i segnaposto, il file `env` è tutto commentato, `vendor/` è senza pacchetti di sviluppo.
- **Segreti e dati personali.** Nessuna chiave o token; la password del database non compare come valore. Nessun percorso, nome, porta locale o remote dell'autore. Gli URL sono solo quelli ammessi.
- **Licenze.** Complete:
  - ogni pacchetto di `vendor/` con versione, licenza e file LICENSE;
  - Alpine 3.17.4 MIT, IBM Plex OFL 1.1, Tailwind 4.3.3;
  - BSD-3 di laminas;
  - LICENSE dell'app starter.
- **Requisiti.** `php ^8.3`, framework `^4.7.4`.

**Pacchetti V7 (`verify-s3.v7package.py`, copia di `verify-v7.package.py` con i nomi senza tag): 15/16.** P04 chiede nel modulo solo README, composer.json, `documentation/` e `src/`: ci sono anche CHANGELOG e THIRD-PARTY-LICENSES, richiesti dalla V8. È lo stesso FAIL noto e superato di V8 e V8b.

## 4. Sugli zip veri

### V8 fasi P ed E, suite V7

| Suite | Esito |
| --- | --- |
| V8 P/E (`verify-s2.v8adapted.php`, adattamento P06d di S2) sui tre zip 1.0.0, tramite `verify-s3.suite.py v8zip` | **36/36** |
| V7 (`verify-v8b.v7adapted.php`) su app e modulo 1.0.0, tramite `verify-s3.suite7.py` | **181/181**, 1 AMB (A1 di V7, già decisa) |

### Applicazione completa con l'installer (`verify-s3.php`, scenari `a83`, `a85`)

Fasi I, U, E, D, S, T, K, L di S2, invariate. In sintesi:
- **Installazione.** Installer via HTTP fino al primo super admin, poi rifiuto di una seconda esecuzione.
- **Pannello.** 15 schermate e asset; utente, ruolo, matrice, assegnazione, override, disattivazione, Settings, registro, lingua.
- **Email e demo.** Email di sicurezza; demo installata, usata e rimossa.
- **Controlli di sicurezza e di lingua.** CSRF, escape, hash, SQL a schermo; stringhe italiane; sessioni ed email con User-Agent senza sistema o vuoto.
- **Log** puliti, con `error_reporting -1`.

| Giro | PHP | Esito |
| --- | --- | --- |
| `a83` | 8.3.35 | **63/63** |
| `a85` | 8.5.11 | **63/63** |

### Modulo in un'app CI 4.7.4 nuova (`m474p83`, PHP 8.3)

`verify-s3.docker/module-app.sh` è la copia di quello di S2, con il comando della documentazione 1.0.0:
- `composer create-project codeigniter4/appstarter:4.7.4`, framework fissato a 4.7.4, Shield e `shield:setup`;
- lo zip in `modules/rolewarden` come repository path (`symlink: false`);
- **`composer require rolewarden/codeigniter4-rolewarden:^1.0`**, più `codeigniter4/translations`;
- il wiring di `#wiring`.

Composer risolve **`rolewarden/codeigniter4-rolewarden 1.0.0`** («Mirroring from modules/rolewarden»), senza bisogno di `minimum-stability`. Vedi `verify-s3.m474p83.26784b6.setup.txt`.

**Fase W (voce nuova di `troubleshooting.html`), prima delle tabelle:**
- **W01.** In development `/rolewarden/install` e la home rispondono 500, come la voce descrive; il log dice `Table '…settings' doesn't exist`.
- **W02.** In production l'installer si apre con il suo form, come la voce consiglia.

Le righe di log della sonda sono messe da parte in `verify-s3.m474p83.26784b6.w-applog.txt`: sono il sintomo documentato. Poi seguono installer (con il passo database saltato perché l'app è già collegata), pannello, email e log: **33/33**.

### Indirizzi senza `index.php` (`ip83`, PHP 8.3)

Configurazione: Apache con `mod_rewrite`, la `public/.htaccess` di CodeIgniter e `app.indexPage = ''` in `.env`, come dice `installation.html#index-page`. Fasi I, U, E, D, più la nuova N:

| Controllo | Esito |
| --- | --- |
| N00: configurazione come documentata (`indexPage` vuoto, `RewriteEngine On`, `rewrite_module`) | PASS |
| N01: 22 pagine rispondono: 15 schermate, login, register, magic link, demo del lettore e dell'autore, form della demo. L'installer risponde «Already installed», come previsto | PASS |
| N02: nessun link, asset, `action`, `formaction`, `data-*` o URL negli script contiene `index.php` | PASS |
| N03: tutti i 42 indirizzi distinti di link e asset rispondono senza 4xx/5xx (CSS, JS, font compresi; logout escluso) | PASS |
| N04: nessuno dei 30 redirect del giro va a un indirizzo con `index.php` | PASS |
| N05: i link delle email di sicurezza sono senza `index.php` e funzionano | PASS |

Anche i form del pannello e della demo inviati nelle fasi U e D funzionano senza `index.php`. Esito **45/45**.

**Sensibilità di N02 e N05.** Nel giro `a83`, senza `indexPage`, i link delle email sono `…/index.php/rolewarden/…`.

### Proxy con le righe nginx 1.0.0 (`x2`, PHP 8.3)

Configurazione:
- nginx HTTPS davanti ad Apache con `proxy_set_header X-Forwarded-For $remote_addr;` e `proxy_set_header X-Forwarded-Proto $scheme;`, le righe di `installation.html#proxy`, più `Host $http_host` (vedi O2);
- `$proxyIPs = ['172.30.5.10' => 'X-Forwarded-For']` scritto prima dell'installer;
- `cookie.secure = true`.

Ogni visitatore manda il proprio `X-Forwarded-For` falso; per nginx tutti i client di questa macchina sono 172.30.5.1.

| Controllo | Esito |
| --- | --- |
| X201: l'installer scrive `app.baseURL` in `https://` | PASS |
| X202: login attraverso il proxy, redirect https dentro l'app | PASS |
| X203: cookie di sessione e remember-me `Secure` e `HttpOnly` | PASS |
| X204: il login è registrato con l'indirizzo visto da nginx (172.30.5.1), non con quello scritto dal visitatore (203.0.113.10) né con quello del proxy | PASS |
| X205: nessun indirizzo scritto dal visitatore, né quello del proxy, in `auth_logins`, `acl_sessions`, `acl_activity_log` o nella pagina delle sessioni (31 valori, tutti 172.30.5.1) | PASS |
| X206: l'email «nuovo dispositivo» nomina l'indirizzo vero, non quello falso | PASS |
| X207: limite per IP a 5 dalla schermata Settings. Sei tentativi falliti, ognuno con un `X-Forwarded-For` diverso, poi la password giusta con un altro indirizzo falso: rifiutata, e i fallimenti sono registrati tutti con 172.30.5.1. Il limite non si aggira | PASS |
| X208: un `X-Forwarded-For` mandato direttamente ad Apache, da un client che non è un proxy elencato, è ignorato | PASS |

Esito **43/43**.

**Sensibilità (`x2append`, non contata).** Lo stesso giro con l'inoltro di S2 (`$proxy_add_x_forwarded_for`, che aggiunge all'intestazione del visitatore) dà 39/43:
- X204-X207 falliscono, con gli indirizzi falsi 203.0.113.10 e 203.0.113.77 registrati;
- la password giusta passa dopo sei fallimenti.

Le righe nuove della documentazione sono quindi quelle che chiudono A4.

### Messe a punto (non contate)

Ho rifatto due giri, per errori miei:
- `ip83`, primo giro: N01 contava come rotta l'installer che, dopo l'installazione, risponde «Already installed».
- `x2`, primo giro: X208 girava dopo il test del limite, quando l'indirizzo dell'host era già bloccato.

Le uscite sono nello scratchpad (`s3tries/`); le loro righe restano in `verify-s3.environment.txt`, quelle duplicate di `ip83` e `x2`.

## Difetti

Nessuno.

## Ambiguità di specifica

Nessuna nuova. A3 e A4 di S2 sono decise (SPEC, «Esiti di S2») e la documentazione le riflette: per A4 vedi X201-X208.

## Osservazioni (non contate)

- **O1. Avviso di Composer.** `composer validate` sul `composer.json` del modulo dice: «The version field is present, it is recommended to leave it out if the package is published on Packagist». Il modulo non va su Packagist, e il campo serve proprio perché `^1.0` funzioni dal repository path. Non è un difetto.
- **O2. Riga `Host` nell'esempio nginx.** L'esempio di `installation.html#proxy` mostra solo le due righe `X-Forwarded-*`. Ho tenuto `proxy_set_header Host $http_host;`, come fa un reverse proxy normale e come in S2. Non ho provato una configurazione con le sole due righe, dove nginx manderebbe come `Host` il nome dell'upstream. Potrebbe valere una riga nella documentazione.

## Cosa non ho potuto provare

- **Controllo visivo in un browser vero**, dovuto all'autore.
- nginx senza Apache, con `try_files` (citato in `#index-page`); hosting condiviso, PHP-FPM, IIS.
- Catene di più proxy, load balancer, IPv6.
- L'altro rimedio della voce di troubleshooting («run the migrations first»). La migrazione a mano l'aveva già provata S2 (`m474p83`).
- Il modulo da solo su PHP 8.5 e su CI 4.7.5: non richiesto qui, provato in S2.

## Pulizia

- **Database.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` in tutte le 10 esecuzioni e alla fine.
- **Mailpit** vuoto.
- **Docker.** Container `s3-*`, immagini `s3-col-php:8.3/8.5` e rete `s3-net` rimossi. Nessun container o immagine d'altri toccato.
- **Processi.** Nessun processo mio attivo. I Chrome aperti sulla macchina non sono miei.
- **File.** Certificato e chiave del proxy, tar dell'app, snapshot e cartelle di lavoro cancellati.
- **Script e uscite.** Tutto in `tests/Integration/verify-s3.*` e `verify-s3.docker/`; driver `verify-s3.all.sh`.

Nessun commit, nessun push.
