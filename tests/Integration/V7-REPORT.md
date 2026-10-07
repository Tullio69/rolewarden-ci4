# V7 — Confezione: collaudo indipendente

## Riverifica — 2026-10-07, Collaudatore ad Hoc (Claude), commit `2314cfb`, zip `build/*-1.0.0-rc.v7c.zip`

**Esito: PASS. V7 approvata per il collaudo.** Ho verificato una per una, contro il comportamento servito, le correzioni dichiarate dall'autore (D1-D6), non contro il diff.

| Suite | Risultato |
| --- | ---: |
| Suite V7 | 181/181, più l'ambiguità A1 non contata |
| Pacchetti | 16/16 |
| Lingue | 499/499 chiavi, 0 mancanti, nessun plurale italiano sbagliato al singolare |

La regressione V2-V6 non ha difetti nuovi: 989/1015 in development e 1012/1038 in production. I FAIL rimasti sono solo aspettative superate:
- V2 G07, già superato da V5;
- 25 controlli della riverifica V4 sulle email (`verify-v4.recheck.php`), superati dalla correzione D6 di V7.

Le ambiguità A1 e A2 restano aperte e non decise.

Mandato, perimetro e regole sono gli stessi del collaudo: nessuna implementazione e nessun diff aperti. Della correzione ho letto solo SPEC e README nella forma attuale. Il modulo viene da `git archive 2314cfb`; gli zip sono `rolewarden-app-1.0.0-rc.v7c.zip` (sha256 `045ab726…91e6`) e `rolewarden-module-1.0.0-rc.v7c.zip` (sha256 `73588ae8…1ca6`). Script, runner e regole sono quelli del collaudo, con queste aggiunte:
- **Controlli nuovi.** Variante development estesa a tutto il percorso (C3b2-C3f). B01c, E12a-E12d, E09d per D6, P14-P16 per D5. Plurali ICU formattati con 1 e 2 in `verify-v7.lang.php`. `verify-v7.maildiff.php` per la regressione V4.
- **F06 più severo.** Ora conta ogni riga WARNING, ERROR o CRITICAL del log, tranne quelle delle connessioni fallite che il collaudo chiede apposta.

### Totali della riverifica

| Suite | Risultato |
| --- | ---: |
| V7, `verify-v7.v7.2314cfb.output.txt` | 181/181, 1 AMB |
| Pacchetti, `verify-v7.package.2314cfb.output.txt` | 16/16 |
| Lingue, `verify-v7.lang.2314cfb.output.json` | 499/499 chiavi, segnaposto e indici ICU uguali, `singular_errors` vuoto |

| Regressione su `2314cfb` | development | production |
| --- | ---: | ---: |
| V6 | 151/151 | 151/151 |
| V5 | 177/177 | 177/177 |
| V4 | 207/232 (25 superati da D6) | 207/232 (25 superati da D6) |
| V2 `test` / `extra` / `adapt` | 142/143 + 33 + 16 | 156/157 + 42 + 16 |
| V3 principale / supplementare / filtri array | 139 + 69 + 55 | 139 + 69 + 55 |
| **Totale** | **989/1015** | **1012/1038** |

**Aspettative superate da V7, segnalate e non modificate.** In entrambi gli ambienti falliscono gli stessi 25 controlli V4, cioè `R-TZ`, `R-CONTENT`, `R-BODY`, `R-D2-TEXT` e `R-D2-HTML` per le cinque email che contengono una data: `new-device`, `new-ip`, `markup`, `password-profile`, `password-admin`.
- **Cosa cercano.** La data nel formato ISO `2026-10-07 18:52 UTC`, e un testo identico, a capo compresi, a quello catturato in V4.
- **Cosa è cambiato.** Ora la data è localizzata nella lingua del destinatario («7 Oct 2026, 20:11 UTC», «7 ott 2026, 20:49 UTC»), come chiedeva D6. La data più lunga sposta anche gli a capo del testo semplice.
- **Prova.** `verify-v7.maildiff.php` confronta le stesse sette email catturate su `2836697` e su `2314cfb` normalizzando solo data, spazi ed entità. Oggetto, mittente, destinatari, testo e HTML risultano identici in entrambi gli ambienti, 7/7 (`verify-v7.maildiff.{development,production}.2314cfb.output.txt`). Il fuso orario resta esplicito ("UTC").
- **Cosa resta da fare.** Il formato della data delle email resta una decisione dell'autore. Le aspettative V4 vanno aggiornate da chi possiede quella suite.

### Difetti del collaudo: stato

| Difetto | Stato | Evidenza |
| --- | --- | --- |
| D1, CSS dell'installer 500 prima dell'installazione | **Corretto** | A03d e C2b, alla radice e in sottocartella: `panel.css` 200 `text/css` prima dell'installazione; C3b3 in development; A15c e A15d dopo |
| D2, development: 500 con dettaglio del driver | **Corretto** | C3a-C3f. Con `CI_ENVIRONMENT = development` già nel `.env`, home e installer si aprono senza toolbar né dettagli di debug. Una URL inesistente non mostra backtrace né percorsi. Le connessioni fallite (password, database inesistente, host sconosciuto) danno il messaggio generico. Admin senza codice e admin prima delle tabelle: nessun SQL a schermo. L'ambiente `development` resta nel `.env`, senza duplicati. Installazione completa, accesso, 15 schermate a 200 |
| D3, righe ERROR in un'installazione normale e connessione prima della configurazione | **Corretto** | F06 su tutte e cinque le applicazioni. In A, C1, C2 e B06 il log non ha nessuna riga WARNING, ERROR o CRITICAL oltre alle 5 connessioni fallite chieste dal collaudo, due righe ciascuna. In C3 (development) idem, 3 connessioni fallite chieste. Prima del `.env` nessun tentativo di connessione. Prima delle tabelle nessun «Table … doesn't exist» |
| D4, inglese nel codice del pannello | **Corretto** | E06, E06b, E12d. Barra delle modifiche non salvate, aria-label della riga, letterali Alpine, tooltip delle matrici di ruolo e utente («… · Concesso da Admin») e prefisso degli errori («Errore:») vengono ora dai file di lingua. Nessun letterale inglese nelle 19 pagine italiane scansionate |
| D5, README spedito con note di sviluppo e credenziale | **Corretto** | P03, P13-P16. README del modulo senza sezione di sviluppo, credenziale, `_AI-LOG`, app di prova o strumenti di build. README alla radice dell'applicazione è quello di RoleWarden. `composer.json` dell'app e `platform_check.php` richiedono PHP 8.3 |
| D6, testo italiano | **Corretto** | Plurali ICU: «1 utente perderà», «1 collegato», «1 permesso proprio + 1 ereditato». «permesso di sistema» sulla pagina Permessi (E12a). Overline «Autorizzazioni» (E12b). «Browser non più ricordato» (E12c). Email: forma neutra «Se l'accesso era tuo…» e data localizzata «il 7 ott 2026, 20:49 UTC» (E09d). La pagina «già installato» non mostra più il contatore dei passi (B01c) |

Tutti i controlli del primo giro passano ancora. In particolare: percorso principale e rifiuto di ripartire (A, B, B06), `.env` non scrivibile (C1), `.env` con altre chiavi e sottocartella con salti di passo e CSRF (C2), lingue ed email nella lingua del destinatario (E), assenza di risorse esterne e di testo d'errore PHP (F02-F04).

**Correzione dello strumento, non del modulo.** Alla prima esecuzione della riverifica F06A ha dato 1 FAIL (`verify-v7.v7.2314cfb.first-run.output.txt`). La riga era quella della porta chiusa che il collaudo chiede apposta, con il messaggio di Windows in italiano («Rifiuto persistente del computer di destinazione»), che il mio filtro non riconosceva. Ho aggiunto quella frase al filtro e rieseguito: 181/181.

### Ambiguità aperte (invariate, non decise)

- **A1.** Ruoli e permessi di sistema, e intestazioni delle aree, sono seminati in inglese e restano inglesi nel pannello italiano.
- **A2.** Senza file di blocco e con l'unico super admin inattivo, l'installer riparte.

### Non verificato (invariato)

- Ultima PHP 8.x e ultima CodeIgniter 4.x: qui ci sono solo 8.3.11 e 4.7.4.
- Reverse proxy e Apache.
- Valori con virgolette nel `.env`: servirebbe un secondo utente MySQL.
- Il controllo visivo in un browser vero resta dovuto all'autore.

### Pulizia

- **Database e Mailpit.** Snapshot `f7f37c04…` identico dopo le 18 regressioni e le 2 esecuzioni della suite, e Mailpit vuoto a ogni fine.
- **Processi e cartelle.** Nessun processo lasciato; cartelle di lavoro rimosse, junction per prime.
- **Da rimuovere a mano.** I profili Chrome della regressione in `scratchpad/chrome7c`, oltre a quelli di `scratchpad/chrome7`: Windows nega l'accesso.
- **Commit.** Nessun commit.

## Collaudo — 2026-10-07, Collaudatore ad Hoc (Claude), commit `2836697`, zip `build/*-1.0.0-rc.v7.zip`

**Esito: FAIL. V7 non approvata.** Suite V7 151/162 (11 FAIL, 1 ambiguità non contata), ispezione dei pacchetti 11/13, completezza delle lingue 482/482 chiavi. La regressione è pulita: 1014/1015 in development e 1037/1038 in production, con il solo V2 G07 già superato da V5. Il percorso principale funziona da capo a fondo: si parte da un database vuoto e si arriva a un primo super admin funzionante, e dopo l'installazione l'installer si rifiuta di ripartire in ogni variante provata. Bloccano l'approvazione due difetti gravi, D1 e D2, e tre medi, D3-D5. I difetti di testo dell'italiano sono raccolti in D6.

Il collaudo segue il protocollo del brief. Ho letto SPEC (Modello dati, Pannello admin con le decisioni V7 del 2026-10-07, Design system), BRIEF v1.0 (V7, definizione di fatto, Pubblicazione, protocollo), README (Two ways to install, The browser installer, Building the zips, Languages), `_AI-LOG.md`, `src/Authorization/Contracts/` e i file di lingua `en`/`it`, che ho usato solo per completezza e qualità del testo. Ho ispezionato i due zip come farebbe un acquirente, e il CSS e il JS così come il pannello li serve al browser. Non ho aperto controller, model, view, `src/Install`, `src/Settings`, `src/Account`, `src/Config`, `src/Database`, helper, `bin/build-package`, `resources/`, test unitari né il diff. I nomi di classe che compaiono sotto vengono dalle tracce del log applicativo, non dal codice.

Ambiente:
- PHP 8.3.11 CLI su Windows 11, server `php -S` con un router mio (`verify-v7.router.php`). Il router emula una sottocartella con una junction `<docroot>/sub -> app/public`.
- Database solo `rolewarden_test` su 127.0.0.1:3317. Le credenziali passano solo come variabili di processo `RW_DB_*`. L'applicazione sotto collaudo le riceve soltanto dal modulo dell'installer, tranne la copia dell'app di prova di B06, configurata come nelle regressioni precedenti.
- Email solo verso Mailpit (smtp 127.0.0.1:1026), `mail()` disabilitata, solo indirizzi `@example.test` e `@rolewarden.test`.
- `logger.threshold = 9` ed `error_reporting = -1`.
- Ai server dell'installer passo le variabili `database.default.hostname = 127.0.0.1` e `database.default.port = 3317`. Così le richieste fatte prima della configurazione, che si collegano da sole al database predefinito (vedi D3), non toccano la 3306 di un altro progetto. Per lo stesso motivo il modulo dell'installer qui propone 127.0.0.1/3317. Senza variabili, nella prima esplorazione, proponeva `localhost`/`3306`.

### Totali

| Suite | Risultato |
| --- | ---: |
| V7 installer, applicazione completa, lingue, pubblicazione (`verify-v7.php` via `verify-v7.suite.py`, output `verify-v7.v7.output.txt`) | 151/162, 1 AMB |
| Pacchetti (`verify-v7.package.py`, output `verify-v7.package.output.txt`) | 11/13 |
| Lingue, stesse chiavi e stessi segnaposto (`verify-v7.lang.php`, output `verify-v7.lang.output.json`) | 482/482 chiavi, 0 mancanti, 0 in più, segnaposto ICU identici |

| Regressione su `2836697` (`verify-v7.run.py`, runner V6 con gli output rinominati) | development | production |
| --- | ---: | ---: |
| V6 (baseline `6c9fd04`) | 151/151 | 151/151 |
| V5 (baseline `bd7488e`) | 177/177 | 177/177 |
| V4 (baseline `3805640`) | 232/232 | 232/232 |
| V2 `test` / `extra` / `adapt` | 142/143 + 33 + 16 | 156/157 + 42 + 16 |
| V3 principale / supplementare / filtri array | 139 + 69 + 55 | 139 + 69 + 55 |
| **Totale** | **1014/1015** | **1037/1038** |

L'unico FAIL di regressione è V2 G07, aspettativa già superata da V5 e segnalata così dalla V5 in poi. Nessuna aspettativa precedente risulta superata da V7. Le suite precedenti girano sulla copia dell'app di prova, che non include `codeigniter4/translations`, e lì non vedono differenze di lingua.

### Esito per punto del mandato

| # | Punto | Esito | Controlli |
| --- | --- | --- | --- |
| 1 | Installer, percorso completo da zip su database vuoto | **FAIL** (D1) | A00-A16. Tutto passa tranne A03d: il CSS dell'installer risponde 500 finché il database non è configurato |
| 2 | Rifiuto di ripartire | PASS | B01-B03c, B06. Dopo l'installazione nessun GET o POST, anonimo o da super admin, con CSRF valido, cambia database, `.env` o file; senza file di blocco ma con un super admin attivo rifiuta lo stesso, anche sulla copia dell'app di prova; codice di installazione cancellato (A14) |
| 3 | Varianti dell'installer | **FAIL** (D1, D2) | C1 (`.env` non scrivibile) PASS; C2 (`.env` con altre chiavi, sottocartella, salti di passo, CSRF) PASS tranne C2b (= D1); C3 (`.env` con `CI_ENVIRONMENT = development`) FAIL |
| 4 | Applicazione completa | PASS | A01, A16, A04, D02, D01, A12, A12b, P10, D03-D03c |
| 5 | Pacchetti | **FAIL** (D5) | P01-P12 PASS; P03 e P13 FAIL: il README spedito contiene note di sviluppo interne, compresa la credenziale dello stack locale |
| 6 | Lingue | **FAIL** (D4, D6) | E00-E11c PASS tranne E06 ed E06b (inglese scritto nel codice del pannello); AMB A1; difetti di testo in D6 |
| 7 | Regressione | PASS | tabella sopra |
| 8 | Pubblicazione: escape, SQL, log, richieste esterne | **FAIL** (D2, D3) | F02, F03, F04, F05* PASS; F01 FAIL (= D2); F06A/C1/C2/C3 FAIL (= D3) |

### Cosa funziona (sintesi dei PASS)

- **Installer, percorso principale (A).**
  - `/` porta all'installer.
  - La prima visita mostra i requisiti (PHP 8.3, intl, mbstring, json, mysqli, `writable/`, `.env`) e crea `writable/rolewarden/install-code.txt` senza stamparne il codice nella pagina. Il codice non cambia alle visite successive.
  - Codice sbagliato, vuoto o con markup: rifiutato, senza eco del markup. Codice giusto senza CSRF o con CSRF sbagliato: rifiutato. Codice giusto: si passa al database, con l'indirizzo proposto corretto (`http://127.0.0.1:8071/`, e `/sub/` in sottocartella) e il campo password vuoto.
  - Errori di validazione su ogni campo.
  - Connessione fallita in cinque modi: password sbagliata, porta chiusa, host inesistente, database inesistente, markup e virgolette nei campi. Ogni volta compare un messaggio generico senza dettagli del driver, non viene scritto nessun `.env`, il database inesistente non viene creato, il motivo finisce nel log e il markup reinserito nei campi è escapato.
  - A connessione riuscita il `.env` contiene indirizzo, database, `encryption.key = hex2bin:` a 64 cifre e `CI_ENVIRONMENT = production`. Lo legge anche `spark migrate:status` senza nessuna variabile del collaudo.
  - Le tabelle di Settings, Shield e RoleWarden finiscono in tre batch distinti, con i soli ruoli e permessi di sistema e nessun gruppo di esempio di Shield. Il passo delle tabelle ripetuto non cambia nulla.
  - Il super admin passa per le regole di Shield: rifiutate password corta, comune, "personale" o diversa dalla conferma, email e username non validi, campi vuoti e la richiesta senza CSRF. Viene creato attivo, con il ruolo `super-admin` e un hash che verifica la password.
  - A fine installazione: file di blocco scritto, codice cancellato, accesso riuscito, 15 schermate a 200, CSS e JS del pannello a 200, home che porta al pannello.
- **Salti di passo (A11s, C2s1-C2s6).** Database senza codice, tabelle senza codice, admin senza codice, tabelle prima del database, admin prima del database, admin prima delle tabelle: nessuna scrittura e nessun SQL a schermo.
- **CSRF mancante o sbagliato su ogni passo** (A06, A06b, A11c, A13c, C2c1, C2c2, C2c2b, C2c3, C2c4): rifiutato, nessun effetto.
- **`.env` non scrivibile (C1).** Il requisito è segnato "by hand" e si prosegue. Dopo la connessione compare il contenuto da copiare, con indirizzo, database, chiave e ambiente, e la pagina è inviata `no-store`. Salvato a mano quel contenuto, l'installazione si completa e l'accesso riesce.
- **`.env` già presente (C2).** Commento, chiave personalizzata e impostazioni email restano. Chiave di cifratura e `CI_ENVIRONMENT` esistenti non vengono sostituiti né duplicati.
- **Sottocartella (C2).** Redirect, indirizzo proposto, link e moduli dell'installer, poi accesso e 15 schermate: tutto resta sotto `/sub/`.
- **Applicazione completa (D).** CSRF attivo sul login: senza token l'accesso è rifiutato. Lingua scelta dal browser: `it` dà l'italiano, `de` dà l'inglese. Aggiornamento del modulo sostituendo `modules/rolewarden` con lo zip del modulo e `php spark migrate -n RoleWarden`: rc 0, database invariato, accesso e schermate a 200.
- **Lingue (E).**
  - Il selettore del profilo offre "Same as the application" (in italiano «Come l'applicazione»), English e Italiano.
  - La scelta è salvata per utente e indipendente fra tre utenti, sopravvive a un nuovo accesso, e cinque valori fuori elenco (`xx`, `de`, `../en`, `<b>`, `IT`) vengono ignorati.
  - In italiano: toast «Lingua salvata.», `<html lang="it">`, messaggi di validazione di CodeIgniter in italiano («Il campo "Nome utente" è obbligatorio.»), paginazione («Precedente / Successiva», aria «Paginazione»), date «7 ott 2026, 18:54» e «Adesso».
  - Le email vanno nella lingua del destinatario: un admin in italiano cambia la password di un utente inglese e l'email parte in inglese; un admin in inglese cambia la password di un utente italiano e l'email parte in italiano, con il corpo interamente in italiano.
  - Senza scelta vale la lingua negoziata dal browser.
  - `app/Language/fr/RoleWarden.php` parziale compare nel selettore come «Français»: le righe tradotte escono in francese, le altre ricadono sull'inglese, senza chiavi grezze.
- **Pubblicazione.** Nessuna pagina carica risorse esterne (F02). Nessun markup non escapato né testo di errore PHP nelle pagine HTML (F03). Nessun warning, notice o deprecation PHP nell'output dei server (F04). Nessuna riga WARNING o superiore nei log oltre a quelle legate al database non ancora configurato (F05).

### Difetti

**D1 — grave. Prima dell'installazione il foglio di stile dell'installer risponde 500: l'installer si presenta senza stile in ogni passo fino alla creazione delle tabelle.** (A03d, C2b)
- Riproduzione: estrarre `rolewarden-app-1.0.0-rc.v7.zip`, servire `public/`, aprire `/rolewarden/install`, poi richiedere il foglio di stile della pagina, `/rolewarden/assets/css/panel.css` (in sottocartella `/sub/rolewarden/assets/css/panel.css`).
- Risposta: HTTP 500 `text/html`. Il log riporta `CRITICAL ... DatabaseException: Unable to connect to the database ... [Route: rolewarden/assets/css/panel.css]`. La traccia passa dal controllo di Shield sull'utente collegato (`Auth->loggedIn` → `setting('Auth.sessionConfig')` → tabella `settings`), che la rotta degli asset esegue anche quando non c'è ancora nessun database.
- Accade sia alla radice sia in sottocartella, con o senza `.env`. Dopo l'installazione gli stessi asset rispondono 200 (A15c, A15d).
- Il controllo visivo dell'autore lo vedrebbe al primo colpo: è la prima schermata che un acquirente apre.

**D2 — grave. Con un `.env` che ha già `CI_ENVIRONMENT = development`, la home e l'installer rispondono 500 con la pagina d'eccezione di CodeIgniter, che mostra il dettaglio del driver; l'installazione è impossibile.** (C3a, C3b, F01)
- Riproduzione: zip estratto, `.env` contenente solo `CI_ENVIRONMENT = development` (più le impostazioni email), database vuoto; `GET /` e `GET /rolewarden/install`.
- Risposta: HTTP 500, `CodeIgniter\Database\Exceptions\DatabaseException Unable to connect to the database. Main connection [MySQLi]: Access denied for user ''@'172.25.0.1' (using password: NO)`, con traccia e percorsi del server. Evidenze: `verify-v7.v7.page-c3-home.html`, `verify-v7.v7.page-c3-install.html`.
- Il README promette che un `CI_ENVIRONMENT` esistente viene mantenuto. CLAUDE.md e la Pubblicazione chiedono di non mostrare mai errori SQL a schermo, in nessun ambiente.
- In production lo stesso stato non solleva eccezioni: è il caso A, dove l'installer funziona.

**D3 — medio. Un'installazione normale scrive righe ERROR nel log, e prima della configurazione ogni richiesta tenta una connessione al database predefinito.** (F06A, F06C1, F06C2, F06C3)
- Prima che esista un `.env`, ogni richiesta, compresi CSS e installer, tenta di collegarsi al database di `Config\Database`. Nell'applicazione completa quel database è `localhost:3306` con utente vuoto. Nel log restano due righe `ERROR - Error connecting to the database: mysqli_sql_exception: Access denied for user ''@...` con traccia completa, dal tracciamento delle sessioni in `pre_system` e `post_system`.
- Dopo la scrittura del `.env` e prima delle tabelle compaiono `ERROR - Table 'rolewarden_test.acl_user_roles' doesn't exist` e `... migrations doesn't exist`.
- Nel percorso A sono 47 righe ERROR estranee alle connessioni sbagliate che ho chiesto io. La Pubblicazione chiede "nessun errore o warning con il report degli errori al massimo livello".
- Su un hosting condiviso il tentativo arriva a qualunque server MySQL in ascolto su `localhost:3306`. Su questa macchina, nella prima esplorazione e prima di impostare le variabili, sono arrivati due tentativi con utente vuoto, rifiutati, alla 3306 di un altro progetto: lo dichiaro.

**D4 — medio. Testo inglese scritto nel codice del pannello: compare anche con l'interfaccia in italiano.** (E06, E06b)
- Barra di salvataggio di matrice ruolo e override utente (`roles/{id}`, `users/{id}`): `x-text="changes().length + ' unsaved ' + (... 'changes' : 'change')"` seguito da «to Admin» oppure «to eva7’s overrides». In italiano si legge «2 unsaved changes to eva7’s overrides».
- `roles/{id}`: `:aria-label="'Grant every action on ' + row.area"` e il letterale `' changed'`.
- Tooltip delle caselle della matrice, sia ruolo sia utente: «users.view · Granted by Admin», «… · Not granted».
- Riepilogo degli errori del modulo utente (creazione e modifica): `<b>Error:</b>` davanti a messaggi italiani. Il toast usa invece «Errore:».
- Evidenze: `verify-v7.v7.page-it-rolewarden-roles-2.html`, `…-users-2.html`, `…-users-1.html`, `verify-v7.v7.page-it-validation.html`.
- La definizione di fatto chiede che ogni stringa dell'interfaccia esista in inglese e in italiano.

**D5 — medio. Il README spedito in entrambi gli zip (`rolewarden-module…zip:/README.md` e `modules/rolewarden/README.md` dell'applicazione) contiene note interne di sviluppo, compresa una credenziale.** (P03, P13)
- «Status: Pre-release. Currently building the MVP; see `_AI-LOG.md`», file che non è nel pacchetto.
- La sezione «Local development»: `docker compose`, Adminer con utente e password dello stack locale scritti in chiaro, i database `rolewarden` e `rolewarden_test`, Mailpit sulle porte 1026 e 8026, `../rolewarden-app-test`, `bin/build-css`, `resources/css/panel.css`, `bin/build-package`. Nessuno di questi file è nel pacchetto.
- La Pubblicazione chiede "nessuna credenziale, chiave o URL personale nel pacchetto". Lo strumento per costruire gli zip (`Building the zips`) è descritto a chi compra.
- Il resto dei pacchetti è pulito (P01, P02, P04-P12): niente `.env`, log, sessioni, cache, lock o codice, `writable/` con i soli segnaposto, modulo identico a `git archive 2836697`, `vendor/` completo senza pacchetti di sviluppo.

**D6 — minore. Difetti di testo dell'italiano** (chiave → problema)
- `panel.roles.confirmDeleteUsers` / `confirmDeleteCount`: «%d utenti perderanno questo ruolo» dà «1 utenti perderanno». L'inglese ripiega su "user(s)", l'italiano non ha nessun ripiego.
- `panel.account.activeCount`: «%d collegati» dà «1 collegati» (profilo, riquadro Sessioni).
- `panel.users.permissionsSummary`: «%d permessi propri + %d ereditati» dà «1 permessi propri».
- `panel.roles.systemNote` usata anche nella pagina Permessi: ogni permesso porta la nota «ruolo di sistema · non si può eliminare». Il sostantivo è sbagliato anche in inglese ("system role" su un permesso).
- `panel.activity.actions.session_remembered_forgotten`: «Browser ricordato dimenticato», poco naturale. Proposta: «Browser non più ricordato».
- Termine incoerente: «Accessi» (`panel.roles.overline`, pagine Ruoli e Permessi, nel senso di controllo degli accessi) contro «Accesso» / «Accesso fallito» (sign-in in Impostazioni e Attività).
- `mail.passwordChanged.body` / `byAdmin`: «il 2026-10-07 18:52 UTC», data ISO nell'email italiana, mentre il pannello scrive «7 ott 2026, 18:54».
- Stile: `mail.newSignIn.notYou` «Se sei stato tu… Se non sei stato tu» usa il maschile. Una forma neutra («Se l'accesso era tuo…») eviterebbe il genere.

### Ambiguità di specifica (non decise, non contate)

- **A1 — Contenuti di sistema scritti in inglese nel database.** In italiano restano inglesi i nomi e le descrizioni dei ruoli di sistema («Admin — Manages users, roles and permissions.», «Super admin — Bypasses role checks…»), le descrizioni dei permessi («View users», «Receive security alert emails…») e le intestazioni delle aree nella pagina Permessi (Activity, Appearance, Users…). Sono righe di `acl_roles` e `acl_permissions`, non righe di lingua. La definizione di fatto dice "ogni stringa dell'interfaccia esiste in inglese e in italiano", ma la SPEC non dice se i record seminati contano come stringhe dell'interfaccia.
- **A2 — Super admin inattivo e nessun file di blocco.** Se il file di blocco viene tolto e l'unico super admin è disattivato, l'installer riparte: mostra il passo del codice e crea un nuovo `install-code.txt`. Il README parla di "active super admin". La SPEC dice "considera installata anche un'applicazione che ha già un super admin", senza "attivo". Così chi può leggere i file del server potrebbe creare un secondo super admin. Sta all'autore decidere se è il comportamento voluto.

### Osservazioni (non difetti)

- Il `README.md` alla radice dell'applicazione completa è quello dell'app starter di CodeIgniter («CodeIgniter 4 Application Starter … composer create-project»): nessuna istruzione RoleWarden alla radice.
- `composer.json` dell'applicazione e `vendor/composer/platform_check.php` dichiarano PHP ≥ 8.2, mentre il modulo e l'installer richiedono 8.3. L'installer lo controlla comunque.
- La pagina "Already installed" porta l'intestazione «Installation · step 4 of 4» e risponde 403.
- Nella documentazione di Shield dentro `vendor/` ci sono chiavi HMAC d'esempio (`docs/references/authentication/hmac.md`): sono di terzi e stanno lì per documentazione.

### Non verificato

- **PHP:** solo 8.3.11; l'ultima 8.x non è installata su questa macchina. **CodeIgniter:** lo zip contiene solo 4.7.4; l'ultima 4.x non è stata provata. **Reverse proxy:** non disponibile.
- **Apache e `.htaccess`:** il servizio usa il server integrato di PHP, con un router che emula la sottocartella.
- **`.env` non scrivibile:** provato con l'attributo di sola lettura di Windows, non con i permessi Unix.
- **Valori con virgolette, `#` o `$` nel `.env`** (per esempio la password del database): non verificabili. Servirebbero un secondo utente MySQL o un altro database, esclusi dal mandato. Sono verificati soltanto l'escape a schermo dei valori con markup e virgolette e la lettura del `.env` scritto con valori semplici (A10f, C1g).
- **Controllo visivo in un browser vero:** resta dovuto all'autore, in particolare per D1 e per le schermate dell'installer in italiano e in sottocartella.

### Pulizia

- **Snapshot.** Snapshot di `rolewarden_test` (database vuoto) `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`. Il ripristino è risultato identico dopo ognuna delle 18 regressioni e delle 8 esecuzioni della suite V7 (`verify-v7.environment.txt`), e dopo le due esplorazioni iniziali.
- **Mailpit.** All'avvio conteneva un messaggio rimasto da una sessione precedente dell'autore (`v7probe@example.test`, «La tua password di localhost è stata cambiata»): l'ho cancellato per partire da vuoto. A fine collaudo è vuoto.
- **Processi.** Nessun processo PHP o Chrome lasciato: ho chiuso solo i miei, per PID. La porta 8000 non è stata toccata.
- **Cartelle.**
  - `verify-v7.work` e `verify-v7.package.work` sono rimosse, le junction per prime.
  - Rimossi anche i file temporanei nella scratchpad (zip estratti, sonde).
  - Le copie dell'applicazione con il `.env` scritto dall'installer stavano solo dentro `verify-v7.work` e sono cancellate.
  - Restano da cancellare a mano i profili Chrome della regressione V5/V6 in `scratchpad/chrome7`: Windows nega l'accesso, come in V6.
- **Commit.** Nessun commit, nessun push.

### File

- `verify-v7.php`: suite V7.
- `verify-v7.lib.php`: client HTTP, avvio e arresto dei server per PID, junction.
- `verify-v7.router.php`: router per la sottocartella.
- `verify-v7.suite.py`: estrazione degli zip, snapshot, ripristino e pulizia.
- `verify-v7.package.py`: ispezione dei pacchetti.
- `verify-v7.lang.php`: confronto delle chiavi di lingua.
- `verify-v7.run.py` e `verify-v7.regression.sh`: regressione.
- Output ed evidenze: `verify-v7.v7.*` (output, log dell'applicazione e dei server, pagine italiane salvate, JS servito), `verify-v7.package.output.txt`, `verify-v7.lang.output.json`, `verify-v7.<suite>.<ambiente>.2836697.*`, `verify-v7.regression.*.console.txt`, `verify-v7.environment.txt`.
