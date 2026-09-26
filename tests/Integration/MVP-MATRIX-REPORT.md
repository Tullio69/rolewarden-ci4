# MVP - matrice di versioni

Voce della definizione di fatto (`docs/BRIEF-MVP.md`): "Funziona su PHP 8.3 e sull'ultima
versione collaudata, su CI4 4.5 e sull'ultima 4.x". Fino al 2026-09-25 tutto era collaudato
solo su PHP 8.3.11 + CI4 4.7.4 + Shield 1.4.1. Ogni combinazione ha la sua sezione.

## PHP 8.3 + CI4 4.5

Collaudatore ad Hoc A (Claude), 2026-09-25, in sostituzione di Codex, stesso protocollo:
letti solo SPEC, BRIEF, `_AI-LOG.md`, README, `src/Authorization/Contracts/`,
`docs/design-system/` e i file di `tests/Integration/`; mai aperti controller, model, view,
asset, helper, `RouteRegistrar`, `Guard.php`, migrazioni/seeder ne' `tests/Unit/`. Letto invece
il sorgente del framework (`system/View/View.php`) per ricontrollare l'unico FAIL.

### Esito

**FAIL: 1 difetto.** Tutto il resto passa.

| Fase | Script | PASS | FAIL |
| --- | --- | --- | --- |
| development | `verify-v1.php` (suite v1.0 completa) | 170 | 1 (X05) |
| development | `verify-v1-anno.php` | 24 | 0 |
| development | `verify-v1-a1a4.php` | 50 | 0 (+1 ambiguita' nota) |
| production | `verify-v1-anno.php` | 24 | 0 |
| production | `verify-v1-a1a4.php` | 50 | 0 (+1 ambiguita' nota) |
| production | `verify-v1.prod.php` | 12 | 0 |
| innesto | `verify-matrix-ci45-graft.php` (tutti i passi, dev + prod) | 58 | 0 |
| **Totale** | | **388** | **1** |

Log del server e `writable/logs` (soglia 9, E_ALL anche in production): 0 warning, 0 notice,
0 deprecation, 0 diagnostici PHP su stderr in tutte le 7 fasi HTTP. Unica voce sopra warning:
6 `CRITICAL SecurityException` nella fase development di `verify-v1.php`, cioe' i POST senza
token CSRF che la suite manda apposta (in production il rifiuto CSRF e' un redirect e non
viene loggato). Dettaglio in `verify-matrix-ci45.logcheck.txt`.

### Versioni

- `php -v`: PHP 8.3.11 (cli) (built: Aug 27 2024 21:28:35) (NTS Visual C++ 2019 x64), `C:\php\php8.3`
- CodeIgniter 4.5.8 (ultima patch 4.5), appstarter 4.5.8
- Shield 1.4.1, Settings 2.4.0 (risolti da Composer con `^1.4`, nessun conflitto con CI4 4.5)
- Modulo: HEAD `e6a20d3`, nessuna modifica non committata sotto `src/`, via repository path
  (junction creata da Composer)
- MariaDB 11.8.9 in Docker (`rolewarden-db`), solo `rolewarden_test`

### Ambiente

La copia di `../rolewarden-app-test` con `codeigniter4/framework` portato a `4.5.*` (tentata per
prima, come da mandato) non parte: lo scheletro `app/` e' quello della 4.7 e `system/` della
4.5 muore prima di caricare codice del modulo con `Undefined property: Config\Kint::$richSort`.
E' un'incompatibilita' fra scheletro 4.7 e framework 4.5, non del modulo, e nessun acquirente
su 4.5 ha quella combinazione. L'app e' stata quindi ricostruita come la costruirebbe un
acquirente su 4.5 (tutto in `verify-matrix-ci45.sh`, `build_app`):

1. `composer create-project codeigniter4/appstarter:4.5.*` e framework fissato a `4.5.*`
   (l'appstarter 4.5.8 chiede `^4.0` e altrimenti installa la 4.7.4);
2. modulo via repository path, `php spark shield:setup` di Shield;
3. wiring del README (riga di `RouteRegistrar`, vista di login) piu' le due impostazioni
   host che l'app sorella ha e il README non elenca (`$userProvider`, redirect dopo il login):
   `app/Config/Auth.php` risultante identico a quello dell'app sorella;
4. `.env` preso dalla sorella, su `rolewarden_test`, porta 8070, nessuna password; credenziali
   solo da `RW_DB_*` come override di processo;
5. `Config\Logger::$threshold = 9` e `error_reporting(E_ALL)` nel Boot production della copia
   (display resta spento), `php -S` con `-d error_reporting=E_ALL -d log_errors=1`.

Installazione da README su `rolewarden_test` ricreato prima di ogni fase, un solo `php -S`
alla volta chiuso a fine fase, `writable/cache` e `writable/session` svuotati. Le suite v1
sono state riusate senza modifiche (output in `verify-matrix-ci45.*.output.txt`).

### Innesto su app esistente (`verify-matrix-ci45-graft.php`)

Stesso scenario di `verify-m6-reverify.php`, ma dal vivo: ogni valore letto dal database e
via HTTP, non trascritto. App "esistente" con il wiring di Shield di serie, `AuthGroups.php` di
serie (superadmin, admin, developer, user, beta) piu' un gruppo `editor` non in collisione;
due utenti creati con `shield:user create/activate/addgroup` (adminuser in `admin`, editoruser
in `editor`, entrambi anche nel gruppo di default `user`). Poi wiring del README e
installazione: `migrate -n RoleWarden`, `db:seed`.

- Ruoli di sistema presenti, `superadmin`/`developer`/`beta`/`editor` importati come ruoli
  ordinari con la matrice espansa sui `$permissions` di `AuthGroups.php`; `admin` e `user` in
  collisione saltati: `admin` ha esattamente i 12 permessi del seed, le loro assegnazioni non
  sono importate (solo editoruser -> editor); `users.create`/`users.delete` restano di sistema.
- RoleWarden in un batch suo subito dopo Shield; il secondo `db:seed` non cambia nulla.
- Tabelle di Shield (utenti, identita', gruppi, permessi utente) identiche a prima.
- HTTP, development e production: i due utenti preesistenti fanno login con la password di
  prima; editoruser 200 su `users/create`, respinto su users/roles/permissions; adminuser
  respinto finche' non riceve `super-admin` con la SQL del README ("Creating the first super
  admin"), poi 200 ovunque; il pannello ruoli mostra i ruoli importati.
- `inGroup()` (rotta sonda nell'app ospite, solo API pubblica): editoruser `editor` true,
  `admin` false; `super-admin` true per chi ha il ruolo.
- `migrate:rollback -b <batch prima di RoleWarden>`: nessuna tabella `acl_*`, nessuna riga
  RoleWarden in `migrations`, insieme delle tabelle e dati Shield identici a prima
  dell'installazione. `-b 0`: resta solo `migrations`, vuota.

### Difetto

**D1 (alto) - l'override delle view non funziona su CI4 4.5 (ne' 4.6).** Voce della
definizione di fatto "Una view sovrascritta nell'applicazione ospite ha precedenza su quella
del modulo", promessa di vendita "ogni schermata del pannello sovrascrivibile" (SPEC), README
"Wiring the admin panel": copiare la view in
`app/Views/overrides/RoleWarden/Views/<view>.php`.

Riproduzione (X05 di `verify-v1.php`, development): `GET rolewarden/roles/{id}` da super
admin, la pagina e' resa da `src/Views/roles/show.php` (commento DEBUG-VIEW); scrivere
`app/Views/overrides/RoleWarden/Views/roles/show.php` con un marcatore; ripetere la GET: il
marcatore non compare, la pagina e' ancora quella del modulo. Stesso controllo PASS su CI4
4.7.4.

Ricontrollato contro un errore dello script: la cartella `overrides` e' un meccanismo del
framework (`Config\View::$appOverridesFolder`), assente in `system/View/View.php` di v4.5.8,
v4.6.0 e v4.6.4 e presente da v4.7.0 (verificato sui sorgenti dei tag). Con il README attuale
un acquirente su CI4 4.5 o 4.6 non ha modo di sovrascrivere una view senza toccare il modulo.
Il rimedio (alzare il minimo a 4.7, o un meccanismo che funzioni anche prima) e' una decisione
di specifica, non presa qui.

### Ambiguita' e osservazioni (non decise)

- A2.DENY, gia' nota: invariata su 4.5.
- Il README ("Wiring the admin panel") non elenca `Config\Auth::$userProvider =
  \RoleWarden\Models\UserModel::class`, decisione di M3 presente nel log e nell'app sorella;
  `V1-REPORT.md` scrive che il README lo dice, ma non e' cosi'. Qui applicata come nell'app sorella,
  non collaudato l'effetto della sua assenza. Documentazione, non specifico della 4.5.
- `composer audit` segnala 6 advisory di sicurezza su `codeigniter4/framework` 4.5.8: il minimo
  dichiarato lascia l'acquirente su un framework con falle note. Informativo.
- README "Requirements" dice ancora "supported range fixed during M0" per Shield, mentre SPEC e
  BRIEF fissano `^1.4`.

### Pulizia

`rolewarden_test` ripristinato dallo snapshot iniziale (0 tabelle), md5 del dump
`b98a95c14161f0186fb51fab93a6e114` identico prima e dopo, verificato a ogni esecuzione. Mai
toccato `rolewarden`. 0 `php.exe`, copia temporanea rimossa (prima la junction), cookie jar e
file di stato cancellati. Nessun commit.

## PHP 8.5 + CI4 4.7.4

Collaudatore ad Hoc B (Claude), 2026-09-25, in sostituzione di Codex, stesso protocollo:
letti solo SPEC, BRIEF, `_AI-LOG.md`, README, `src/Authorization/Contracts/`,
`docs/design-system/` e i file di `tests/Integration/`; mai aperti controller, model, view,
asset, helper, `RouteRegistrar`, `Guard.php`, migrazioni/seeder ne' `tests/Unit/`. Su `src/`
solo `php -l` e il caricamento delle classi, senza leggerne il codice.

### Esito

**PASS: nessun difetto del modulo su PHP 8.5.** L'unico FAIL registrato (U03) e' un falso
positivo dello script dovuto ai tempi, ricontrollato sotto. Resta l'ambiguita' A2.DENY gia'
nota, piu' un'osservazione nuova non decisa.

| Fase | Script | PASS | FAIL |
| --- | --- | --- | --- |
| statica, senza DB | `php -l` su 46 file di `src/`, caricamento di 33 classi, spark `list`/`routes`/`namespaces`/`filter:check` | tutto | 0 |
| development | `verify-v1.php` (suite v1.0 completa) | 170 | 1 (U03, falso positivo) |
| development | `verify-v1-anno.php` | 24 | 0 |
| development | `verify-v1-a1a4.php` | 50 | 0 (+1 ambiguita' nota) |
| production | `verify-v1-anno.php` | 24 | 0 |
| production | `verify-v1-a1a4.php` | 50 | 0 (+1 ambiguita' nota) |
| production | `verify-v1.prod.php` | 12 | 0 |
| innesto (development) | `verify-matrix-php85-m6.php`: installed 8, reseeded 19 (con HTTP), rolledback 3, rolledback0 1 | 31 | 0 |
| **Totale** | | **361** | **1 (falso positivo)** |

X05 (una view sovrascritta nell'app ospite prevale su quella del modulo): **PASS** su PHP 8.5 +
CI4 4.7.4. Il difetto D1 della sezione precedente riguarda solo CI4 4.5/4.6.

Log del server `php -S` e `writable/logs` (soglia 9 ed `error_reporting(E_ALL)` anche nel
Boot production, come nella sezione di A): 0 deprecation, 0 warning, 0 notice, 0 diagnostici
PHP in tutte le fasi. L'unica voce sopra warning sono 6 `CRITICAL SecurityException` nella
fase development di `verify-v1.php`, cioe' i POST senza token CSRF che la suite manda apposta.
Nessun testo SQL a schermo (controlli Z01/SQL/M6H.06).

### Versioni

- Server e spark: PHP **8.5.11** (cli, built Sep 22 2026, NTS Visual C++ 2022 x64), zip
  ufficiale `php-8.5.11-nts-Win32-vs17-x64.zip` (sha256 verificato), portable nello
  scratchpad. `php.ini` da `php.ini-development` con `error_reporting=E_ALL` e le estensioni
  intl, mbstring, mysqli, openssl, curl, fileinfo, sodium, zip. Rimosso a fine collaudo.
- Client curl degli script (non sotto collaudo): PHP 8.3.11 di sistema. Motivo:
  `verify-v1.lib.php` chiama `curl_close()`, deprecata in 8.5, e sporcherebbe l'output. Sotto
  8.5 girano solo app, server e spark, cioe' il modulo.
- CodeIgniter **4.7.4**, Shield **1.4.1**, Settings **2.4.0**. Sono le ultime su Packagist il
  2026-09-25 e le ha risolte `composer update` (Composer 2.10.3) eseguito con PHP 8.5, senza
  conflitti. `check-platform-reqs` passa tutto.
- Modulo: HEAD `e6a20d3`, nessuna modifica non committata sotto `src/`.
- MariaDB 11.8.9 in Docker (`rolewarden-db`), solo `rolewarden_test`.

### Ambiente

Copia di `../rolewarden-app-test` nello scratchpad fatta con robocopy `/XJ`. La junction verso
il modulo l'ho ricreata a mano. Nel `composer.json` della copia l'url del repository path e'
assoluto, perche' `../rolewarden-ci4` dallo scratchpad non si risolveva. Il `.env` punta a
`rolewarden_test`, porta 8085, senza password; le credenziali arrivano solo da `RW_DB_*`.
Tutto in `verify-matrix-php85.sh` (`static` | `full` | `probe`):
- installazione da README su `rolewarden_test` ricreato prima di ogni fase, con lo spark di
  PHP 8.5;
- un solo `php -S` alla volta;
- le suite v1 non sono modificate: il driver ne genera a runtime copie con porta 8085;
- output in `verify-matrix-php85.*.output.txt`.

Innesto (`verify-matrix-php85-m6.php`), scenario di `verify-m6-reverify.php` eseguito dal vivo:
- Settings e Shield migrati per primi. `AuthGroups.php` di serie piu' un gruppo `editor`
  (`editor.publish`, `users.*`).
- adminuser nel gruppo `admin`, che collide con un ruolo di sistema; editoruser in `editor`.
  Entrambi creati con `shield:user create`, quindi finiscono anche nel gruppo di default `user`.
- Poi `migrate -n RoleWarden`, due `db:seed`, rollback al batch precedente e infine `-b 0`.

Risultati:
- `admin` resta di sistema con esattamente i 12 permessi del seed.
- `users.create` e `users.delete` sono di sistema.
- `editor` viene importato come ruolo ordinario, con `users.*` espanso sui `$permissions` di
  `AuthGroups.php`.
- L'unica assegnazione importata e' editoruser -> editor.
- Utenti e `auth_groups_users` di Shield sono identici a prima dell'installazione e anche
  dopo il rollback.
- Via HTTP entrambi fanno login con la password di prima. editoruser riceve 200 su
  `users/create` ed e' respinto su users, roles e permissions; adminuser e' respinto ovunque.
- Il rollback del batch di RoleWarden non lascia tabelle `acl_*` ne' righe in `migrations`.
- `-b 0` lascia solo `migrations`.

### Falso positivo U03 (ricontrollato)

U03 si aspetta il proprietario in cima alla lista utenti. E' fallito in due esecuzioni
complete di fila, sempre con `[3,1,6]` al posto di `[1,3,6]`, cioe' Jonas (G02) sopra il
proprietario (U00). Ricontrollato con sonde (`verify-matrix-php85.sh probe`):
- la colonna/ordinamento "Last login" del pannello segue la data di ultima attivita' di
  Shield: un utente appena entrato ma senza richieste successive non sale;
- U03 invece calcola l'atteso da `auth_logins`;
- i due dati hanno risoluzione al secondo. Se l'ultima richiesta di Jonas e il login del
  proprietario cadono nello stesso secondo, i due sono pari e l'ordine e' arbitrario.

La stessa suite rilanciata con una riga diagnostica dopo U03 ha dato **171 PASS / 0 FAIL**
(ultima attivita' di Jonas 20:53:10, proprietario 20:53:11). Il FAIL dipende dai tempi dello
script, non da PHP 8.5.

### Ambiguita' e osservazioni (non decise)

- A2.DENY, gia' nota: invariata su PHP 8.5.
- Nuova, da decidere: il README UsersList parla di "sorting by last login", ma il pannello
  ordina per ultima attivita' (data di Shield). Un login senza richieste successive non cambia
  la posizione, e una sessione attiva sale anche senza un nuovo login. Non e' specifico di
  PHP 8.5. Rende U03 sensibile ai tempi finche' non si decide quale data valga.
- Informativo, non del modulo:
  - il Composer 2.7.9 di sistema sotto PHP 8.5 stampa deprecation proprie (`curl_close()`,
    `E_STRICT`, `case ;`); il 2.10.3 no;
  - `spark shield:user activate` con stdin chiuso termina con
    `TypeError: CodeIgniter\CLI\InputOutput::input(): Return value must be of type string,
    false returned`. Errore dello script nel primo giro (nessuna risposta al prompt),
    corretto passando `y`.

### Script: errori miei corretti tra il primo e il secondo giro

- Il confronto su `auth_groups_users` non contava il gruppo di default che
  `shield:user create` aggiunge: M6I.08, M6R.08 e M6B.03 fallivano per questo. Ora si
  confronta con lo stato fotografato prima dell'installazione.
- La fase `rolledback0` cercava gli utenti dopo che `-b 0` aveva gia' tolto le tabelle.
- Il prompt di `shield:user activate` restava senza risposta.
- Ho aggiunto la soglia di log 9 ed E_ALL anche in production, per parita' con la sezione
  di A.

Nessuna di queste correzioni tocca un controllo sul modulo.

### Pulizia

`rolewarden_test` ripristinato dallo snapshot iniziale (0 tabelle). md5 del dump
`b98a95c14161f0186fb51fab93a6e114` identico prima e dopo, verificato a ogni esecuzione (due
giri completi e tre sonde). Mai toccato `rolewarden`.

Nella fase 1, senza DB, una prova HTTP ha causato 5 tentativi di connessione a MariaDB come
root senza password, tutti rifiutati ("Access denied"), nessun dato letto o scritto. L'override
dell'host via variabile di processo non vince sul `.env` in CI4.

A fine lavoro: copia dell'app rimossa (prima la junction), PHP portable e Composer rimossi, 0
`php.exe` attivi. Nessun commit.

## PHP 8.3 + CI4 4.7.0

Collaudatore ad Hoc C (Claude), 2026-09-26, in sostituzione di Codex, stesso protocollo:
letti solo SPEC, BRIEF, `_AI-LOG.md`, README, `src/Authorization/Contracts/`,
`docs/design-system/` e i file di `tests/Integration/`; mai aperti controller, model, view,
asset, helper, `RouteRegistrar`, `Guard.php`, migrazioni/seeder, `tests/Unit/` ne' il diff di
`src/`. Oggetto: il nuovo minimo dichiarato dopo D1 (`codeigniter4/framework: ^4.7` in
`require` del `composer.json` del modulo, working tree non committato).

### Esito

**PASS: nessun difetto.** Il minimo 4.7.0 funziona, il README basta, Composer rifiuta la 4.6.

| Fase | Script | PASS | FAIL |
| --- | --- | --- | --- |
| rifiuto 4.6 | appstarter 4.6.*, framework fissato 4.6.*, `composer require` del modulo, senza e con `-W` | 2 rifiuti su 2 | 0 |
| development | `verify-v1.php` (suite v1.0 completa) | 171 | 0 |
| development | `verify-v1-anno.php` | 24 | 0 |
| development | `verify-v1-a1a4.php` | 50 | 0 (+1 ambiguita' nota) |
| production | `verify-v1-anno.php` | 24 | 0 |
| production | `verify-v1-a1a4.php` | 50 | 0 (+1 ambiguita' nota) |
| production | `verify-v1.prod.php` | 12 | 0 |
| innesto | `verify-matrix-ci45-graft.php` di A, invariato (pre 3, migrated 12, seeded 12, http dev 13, http prod 12, rolledback 5, rolledback0 1) | 58 | 0 |
| **Totale suite** | | **389** | **0** |

X05 (view sovrascritta in `app/Views/overrides/RoleWarden/Views/` prevale): **PASS** su 4.7.0,
quindi D1 e' chiuso anche sul minimo. U03 PASS al primo giro (nessun pareggio al secondo).

Log `php -S` e `writable/logs` (soglia 9, `error_reporting(E_ALL)` anche nel Boot production):
0 warning, 0 notice, 0 deprecation, 0 diagnostici PHP su stderr in tutte le 7 fasi HTTP.
Uniche voci sopra warning: 6 `CRITICAL SecurityException` nella fase development di
`verify-v1.php`, i POST senza token CSRF che la suite manda apposta. Dettaglio in
`verify-matrix-ci470.logcheck.txt`.

### Versioni

- `php -v`: PHP 8.3.11 (cli) (built: Aug 27 2024 21:28:35) (NTS Visual C++ 2019 x64), di sistema
- Composer 2.7.9 di sistema
- CodeIgniter **4.7.0** esatta (`CI_VERSION = '4.7.0'`), appstarter 4.7.0
- Shield 1.4.1, Settings 2.4.0, risolti da Composer senza conflitti
- Modulo: HEAD `e6a20d3` piu' il working tree (0 file modificati sotto `src/`; `composer.json`
  con `codeigniter4/framework: ^4.7` in `require`), via repository path, versione `dev-master`,
  junction creata da Composer
- MariaDB 11.8.9 in Docker (`rolewarden-db`), solo `rolewarden_test`

### Ambiente

Tutto in `verify-matrix-ci470.sh`, derivato da `verify-matrix-ci45.sh` di A (stesse fasi, stesse
suite non modificate, porta 8070, un solo `php -S` alla volta, `writable/cache` e
`writable/session` svuotati, installazione da README su `rolewarden_test` ricreato a ogni fase).
Differenze rispetto ad A:

1. `composer create-project codeigniter4/appstarter:4.7.0` (dentro 4.7.*, e' lo scheletro che ha
   un acquirente sul minimo), poi `composer config repositories.rolewarden path <modulo>` e
   `composer require codeigniter4/framework:4.7.0 rolewarden/codeigniter4-rolewarden:@dev -W`:
   risolto al primo colpo (framework portato da 4.7.4 a 4.7.0, Shield/Settings bloccati,
   modulo in junction). Output in `verify-matrix-ci470.build.output.txt`.
2. Wiring **solo** come scritto nel README attuale (`verify-matrix-ci470.wire.php`, ogni modifica
   deve combaciare esattamente una volta): la riga `$userProvider`, la riga
   `RouteRegistrar::register($routes)` e la vista di login. Nient'altro: il redirect dopo il
   login che l'app sorella e la build di A impostano (`rolewarden/users`) qui **non** e' applicato.
   Il diff risultante rispetto allo scheletro di `shield:setup` sono esattamente quelle tre righe.
3. Oltre al wiring, solo impostazioni del banco di prova: mittente mail, soglia di log 9,
   `error_reporting(E_ALL)` nel Boot production.

### Rifiuto su CI4 4.6 (`verify-matrix-ci470.reject46.output.txt`)

Appstarter 4.6.* con `codeigniter4/framework` fissato a `4.6.*` in `composer.json`, stesso
repository path. `composer require rolewarden/codeigniter4-rolewarden:@dev`:

- senza `-W`: exit 2, "rolewarden/codeigniter4-rolewarden dev-master requires
  codeigniter4/framework ^4.7 -> found codeigniter4/framework[v4.7.0, ..., v4.7.4] but it
  conflicts with your root composer.json require (4.6.*)", `composer.json` e `composer.lock`
  ripristinati da Composer, nessun modulo in `vendor/`;
- con `-W` (il suggerimento che Composer stampa): stesso rifiuto, exit 2, nulla installato.

La protezione voluta funziona. App 4.6 rimossa subito dopo.

### Ambiguita' e osservazioni (non decise)

- A2.DENY, gia' decisa dall'autore: invariata su 4.7.0.
- UsersList "last login" contro ultima attivita' (sezione di B): ancora aperta; su questo giro
  U03 e' passato, il che non la chiude.
- Nuova, informativa: con il solo wiring del README, dopo il login Shield rimanda alla sua
  destinazione di serie (`$redirects['login'] = '/'`, la pagina di benvenuto di CI4), non al
  pannello; l'app sorella imposta `rolewarden/users`. Nessun controllo fallisce (L09 verifica
  solo che non si torni al login) e SPEC/BRIEF non dicono dove si atterra: se il README debba
  suggerirlo e' una scelta dell'autore. Dedotto dalla configurazione applicata, non misurato
  via HTTP.
- Informativa, non del modulo: `composer audit` su framework 4.7.0 segnala 5 advisory, tutte
  corrette in 4.7.4 (CVE-2026-63220/63221/63222/63223, e una `ext_in` corretta in 4.7.2, fra cui
  una SQL injection in `deleteBatch()`). Il minimo dichiarato lascia possibile un framework con
  falle note, come gia' osservato da A sulla 4.5.

### Pulizia

`rolewarden_test` ripristinato dallo snapshot iniziale (0 tabelle), md5 del dump
`b98a95c14161f0186fb51fab93a6e114` identico prima e dopo, verificato in entrambe le esecuzioni
(build + rifiuto, poi suite). Mai toccato `rolewarden`. Credenziali solo da `RW_DB_*`. 0
`php.exe`, app 4.7.0 e app 4.6 rimosse (prima le junction), cookie jar e file di stato
cancellati, cartella di lavoro nello scratchpad eliminata. Nessun commit, `_AI-LOG.md` non
toccato.
