# W2: report di collaudo (wizard `/rolewarden/setup`, piano 2)

- Data: 2026-11-08. Collaudatore ad Hoc (subagente Claude), collaudo in nero.
- Commit collaudato: `e7ebbef` su `main`. Il modulo è stato esportato con `git archive HEAD` (rispetta gli `export-ignore`, come lo zip `module`), copiato in `modules/rolewarden` e caricato con la riga di Autoload del README.
- Documenti letti: spec `2026-11-07-installer-grafico-design.md`, piano `2026-11-08-wizard-installazione.md`, `_AI-LOG.md`, README (sezione wizard), CHANGELOG, sezione `setup` di `src/Language/en|it/RoleWarden.php`, `src/Config/Routes.php`, `src/Config/RouteRegistrar.php`. Controller, viste, `src/Install/` e test esistenti non sono stati aperti.
- Ambiente: app nuove `composer create-project codeigniter4/appstarter` (CI 4.7.6) + `codeigniter4/shield` ^1.4 (config pubblicate da `shield:setup`, migrazioni con `migrate --all`), PHP 8.3.11, `php spark serve`, MariaDB 11.8 del container `rolewarden-db`, solo database `rolewarden_test`. Le app stanno nella scratchpad, credenziali solo nel loro `.env`.
- Strumenti: `verify-w2.php` + `verify-w2-lib.php` (client HTTP con cookie jar, CSRF e lettura dei form; query con `docker exec ... mariadb`). Uscite: `verify-w2-*.out.txt`, `verify-w2-manifest-*.json`, `verify-w2-odd-stop.html`, `verify-w2-s1-applog.txt`.

## Host di prova

| Host | Configurazione |
|---|---|
| **custom** (S1, S2) | Gruppi `admin` (`tickets.*`, `admin.*` = 8 permessi), `operatore` (4), `cliente` (2), `user` (1); `$defaultGroup = 'cliente'`; filtro globale `'session' => ['except' => ['login*','register','auth/a/*','logout']]`. Utenti: alice (admin, id 1), bob (operatore, 2), carla (cliente, 3), dario (cliente+user, 4), eva (disattivata, 5), frank (bannato, 6), gina (cancellata `deleted_at`, 7), hank (admin + permesso diretto `admin.reports`, 8), ivan (user, 9). Rotta host `/probe` che restituisce in JSON classe dell'utente, provider e alcuni `can()`. |
| **stock** (S3) | `AuthGroups` di serie di Shield (`superadmin`, `admin`, `developer`, `user`, `beta`), nessun filtro globale, stessi utenti su gruppi di serie. |
| **odd** (S4) | Come custom, ma: `$userProvider = \App\Models\AppUserModel::class` (modello dell'host), `Routes.php` con la riga `// \RoleWarden\Config\RouteRegistrar::register($routes);` commentata, `except` del filtro `session` come stringa (`'login*'`). |
| **empty** (S5) | Come stock, ma `$groups = []` e `$matrix = []`. |

## Esito per caso

| # | Caso | Esito | Evidenza |
|---|---|---|---|
| 1 | Senza codice il wizard non si apre, anche da loggati | PASS | Da loggata (alice) `GET /rolewarden/setup` mostra solo il passo `code`. `POST step=roles` senza codice resta sul passo `code` (`code:skip-ahead-refused`). |
| 2 | Codice errato rifiutato | PASS | `POST step=code&code=wrongcode` → 200, "That is not the setup code. Copy it again from the file." |
| 3 | CSRF obbligatorio su ogni POST | PASS | POST senza token → 403 `SecurityException` (S1, e di nuovo dopo il completamento). Il token si rigenera a ogni POST: un token vecchio viene rifiutato. |
| 4 | Focus 5: host con `session` globale, l'ospite va al login dell'host; la pagina del codice lo spiega | PASS | Ospite: `GET /rolewarden/setup` → 302 `/login`. La pagina del codice dice "If the application asked you to sign in before showing this page, sign in with any existing account: the setup code is needed all the same." |
| 5 | Domande 1–6 senza scritture | PASS (S2, S3, S4) | Elenco delle tabelle uguale prima e dopo, hash di tutti i file di `app/` e `public/` uguali, nessuna riga RoleWarden in `settings`. In `writable/rolewarden/` ci sono solo `install-code.txt` (creato alla prima visita, è il meccanismo del codice) e `install-manifest.json`. |
| 6 | Focus 1: host con soli gruppi di serie → propone "Importa" e non si blocca | PASS | S3: `mode=import` preselezionato, poi passo collisioni (`admin`, `user`). S5 (nessun gruppo): "Your application has no groups of its own.", Import "recommended", si passa a newUsers. |
| 7 | Host con gruppi propri → propone "Adatta" | PASS | S2: `mode=adapt` preselezionato, "recommended". |
| 8 | Collisioni: tre opzioni, avviso del Salta, validazione | PASS | Scheda per `admin` e per `user` con Unisci / Importa con un altro nome (precompilato `app-admin`, `app-user`) / Salta. Salta: "The group "admin" is not imported: 2 users lose 8 permissions." Rifiutati: nessuna scelta, `action=bogus`, rename a `operatore`, `user`, `super-admin`, `Bad Slug`, e due rename allo stesso slug. |
| 9 | Esito nel DB coerente con la scelta | PASS | **S1** (admin=Unisci, user=Salta): `admin` 33 permessi (25 del modulo + 8 dell'host), membri 1 e 8; `user` di sistema senza membri e 0 permessi; ivan perde `tickets.view` (probe `false`). **S3** (admin=rename `app-admin`, user=Unisci): `app-admin` con 5 permessi e membri 1 e 8; `admin` di sistema 25 permessi e nessun membro; `user` con membri 3,4,5,6,7,9. **S2** (Adatta, panelRole=admin): `admin` 33 permessi, setting `panelRole=admin`, `user` solo come gruppo dell'host (1 permesso, membri 4 e 9). |
| 10 | Focus 4: elenco solo utenti attivi; id manomesso, disattivato o bannato rifiutato | PASS | Elenco: id 1,2,3,4,8,9 (mancano 5 disattivato, 6 bannato, 7 cancellato). POST `kind=existing` con id 5, 6, 7, 999, 0, -1 e `1 OR 1=1` → resta su superAdmin con "Choose one of the options offered." |
| 11 | Nuovo super admin: password mai in chiaro nel manifest | PASS | Il manifest contiene solo `passwordHash` (bcrypt) finché l'account non esiste. Dopo la creazione l'hash viene tolto e resta `id: 13`. `grep` della password nei manifest: nessun risultato. |
| 12 | Nuovo super admin: validazione con le regole di Shield | **FAIL** (D1) | Password corta, email non valida e conferma diversa: rifiutate. Username già in uso (`alice`) ed email già in uso (`alice@host.test`): **accettati**. L'applicazione poi si ferma al passo "Super admin" (log: `Duplicate entry 'alice' for key 'username'`, poi "Another user already has the new super admin's email."). |
| 13 | Conferma e applica: ordine, batch separato, backup, registro | PASS | Migrazioni RoleWarden in un batch a sé (batch 2, 16 migrazioni). `done` = migrations, roles, superAdmin, filters, wiring. Modifiche registrate in ordine Filters.php, Routes.php, Auth.php, ciascuna con `path`, `before`, `after`, `at` (ISO 8601) e `checked: true`. I tre `.rolewarden-bak` sono identici byte per byte agli originali. I diff contengono solo le righe mostrate in anteprima. Con visibility=all anche la vista e il redirect di login (S2). |
| 14 | L'host funziona in ogni momento | PASS (per quanto osservabile) | Negli stati intermedi reali (S1 dopo il fallimento su filters; S4 con Routes cablato ma Auth no) login e `/probe` dell'host funzionano. Il server di sviluppo è a thread singolo, quindi gli istanti dentro la singola richiesta di apply non sono osservabili via HTTP. |
| 15 | Focus 3: ripresa dopo un file non scrivibile | PASS, con difetto D2 | `Filters.php` in sola lettura (`attrib +R`): l'apply si ferma su "Filter exception" con il testo da incollare; `done` = migrations, roles, superAdmin; `edits` vuoto; host invariato. "I edited the files, continue" senza modifiche si ferma di nuovo. Tolta la sola lettura e riapplicato: completa. Nessun duplicato in `acl_user_roles`, `acl_role_permissions` e `acl_roles` (query GROUP BY … HAVING count>1 vuote); 5 ruoli, gli stessi di prima. Le risposte sono bloccate dopo l'avvio ("The installation has already started, so the answers can no longer change"); il super admin resta modificabile finché il suo passo non è fatto, come dice il README. |
| 16 | File con forma non riconosciuta | PASS | S4: Auth.php con il provider dell'host è segnalato in anteprima come non riconosciuto. L'apply si ferma a "Wiring" con il testo da incollare ("Set public string $userProvider = …; … make it extend \RoleWarden\Models\UserModel"); il file non viene scritto e non ha `.bak`. "Continue" senza modifica: resta fermo. Dopo aver fatto estendere `AppUserModel` da `\RoleWarden\Models\UserModel`: completa, wizard 403, `can()` di bob passa per `RoleWarden\Entities\User`. `except` come stringa diventa un array. |
| 17 | Dopo il completamento | PASS | Wizard 403 per ospite e per la vecchia sessione, anche in POST con token valido. Codice monouso cancellato. Login dell'host 200; `/rolewarden/assets/css/panel.css` e `/rolewarden/theme.css` raggiungibili dall'ospite (200). Il ruolo del pannello (alice, hank) apre `/rolewarden` (200); bob e carla no (302). `can()` dell'host passa per RoleWarden (provider `RoleWarden\Models\UserModel`, entità `RoleWarden\Entities\User`, permessi coerenti con i ruoli). Un nuovo iscritto (`/register`) riceve `cliente`. Il nuovo super admin (newroot) entra e apre il pannello. |
| 18 | Lingua | PASS | `Accept-Language: it-IT` → pagina in italiano ("Configura RoleWarden", "passo 1 di 7"); il selettore `locale=en` passa all'inglese; `choices.locale` salvato. |
| 19 | Nessun dettaglio SQL a schermo | PASS | Nessuna risposta del wizard contiene SQLSTATE, Duplicate entry, nomi di tabella e simili, nemmeno nei fallimenti causati dal DB (S1): "the details are in the application log". |

## Difetti

**D1. Nuovo super admin con username o email già in uso accettato alle domande.**
- Passi: passo Super admin, "A new user" con username `alice` (esistente) oppure email `alice@host.test` (esistente), password valida → Continue → Confirm and apply.
- Atteso (README: "a new account checked with Shield's own username, email and password rules"; piano Task 6: "con le regole di Shield, come `InstallController::createAdmin`"): rifiuto al passo Super admin, come Shield alla registrazione (unicità di username ed email).
- Ottenuto: la risposta viene accettata. L'apply esegue migrazioni e ruoli, poi si ferma su "Super admin" con un messaggio generico. Si recupera tornando indietro e scegliendo un altro utente; nessun utente orfano creato.

**D2. Dopo un apply interrotto, la pagina di conferma dice "Nothing has been written yet."**
- Passi: apply che si ferma (S1 su "Super admin" o su "Filter exception"; S4 su "Wiring"), poi un nuovo GET di `/rolewarden/setup`.
- Atteso (spec, anti-obiettivo "nessuna decisione presa in silenzio"; piano Task 7: si mostrano il passo e un messaggio): la pagina non deve dire che non è stato scritto niente quando migrazioni, ruoli o file sono già stati applicati.
- Ottenuto: con il GET la pagina mostra di nuovo "Check your choices and the changes to your files. Nothing has been written yet." e il pulsante "Confirm and apply", senza dire quali passi sono già fatti. Anche nella risposta con l'errore la frase compare accanto a "The steps before it are done". In S4 l'anteprima dei file mostra "Routes.php: no change needed" perché la modifica è già stata fatta, ma non lo dice.

## Ambiguità di specifica (non decise)

1. **Conteggio del "Salta".** "N utenti perdono M permessi" conta i membri del gruppo e i suoi permessi, non quello che ciascuno perde davvero. Nel caso del gruppo `user`, dario conserva `tickets.view` tramite `cliente`, ma il messaggio dice comunque "2 users lose 1 permission". La spec dice solo "quanti utenti perdono quanti permessi".
2. **Riga `RouteRegistrar` commentata.** Il prompt del collaudo la cita come forma non riconosciuta, ma il wizard la tratta come assente e aggiunge una riga attiva sopra il commento. Il risultato funziona, ma la spec non dice se una riga commentata debba portare alle istruzioni a mano.
3. **Controlli dei requisiti.** Il passo Requisiti controlla PHP, le estensioni e `writable/`, non le versioni di CodeIgniter e Shield, né la scrittura di `app/Config/` e `modules/`. Secondo la spec questi controlli stanno nella fase 1 (piano 3) e il passo 1 della fase 2 ne fa il riepilogo. Il README dice "the same checks as the browser installer, except the .env one". Non è chiaro se la fase 2, usata da sola con lo zip `module`, debba già verificare le versioni.
4. **Numerazione.** "step 1 of 7" compare sia sul passo del codice sia su Requisiti; Collisioni condivide "step 3 of 7" con Ruoli. La spec non fissa la numerazione.
5. **Membri non attivi importati.** I membri disattivati, bannati e cancellati (`deleted_at`) di un gruppo sono contati e importati nel ruolo (`cliente`: 3,4,5,6,7). Questo è coerente con "i suoi membri", ma la spec non dice se gli utenti cancellati vadano esclusi.

## Note

- S1 è stato eseguito in esplorazione manuale con `explore.php` (scratchpad), non con lo script. Un errore del mio script ha confermato due volte di troppo il passo finale con il super admin non valido (D1): sono le righe ripetute "step superAdmin failed" in `verify-w2-s1-applog.txt`. Questo ha anche mostrato che apply ripetuti dopo un fallimento non duplicano niente.
- Le pagine di errore CSRF in ambiente `development` sono quelle di CodeIgniter (traccia JSON), non del modulo, e non contengono SQL.
- `rolewarden_test`: snapshot `mariadb-dump` prima del collaudo, ripristinato alla fine. Il dump dopo il ripristino ha lo stesso SHA-256 di quello iniziale (`adfa3514…2533`) e i `CHECKSUM TABLE … EXTENDED` delle 16 tabelle sono identici.

## Esito complessivo

**FAIL**: 2 difetti, D1 (validazione del nuovo super admin) e D2 (pagina di conferma fuorviante dopo un'interruzione). Tutti i Review Focus 1–5 passano. Non approvato finché D1 e D2 non sono corretti e riverificati.

## Riverifica su 610bca9

- Data: 2026-11-08, stesso collaudatore e stesse regole (in nero, solo `rolewarden_test` con snapshot e ripristino esatto). Il modulo è stato ripreso con `git archive 610bca9`, un'esportazione nuova che sostituisce `modules/rolewarden` negli host di prova. Gli host sono ricostruiti da zero dai template e dal dump del database base.
- Della correzione ho letto solo i messaggi dei due commit (`9e0a4ae`, `610bca9`) e le stringhe nuove della sezione `setup` nei file di lingua (`fileApplied`, `confirmIntroResume`, `appliedTitle`, `appliedDone`, `continueInstall`). Nessun file d'implementazione aperto.
- Script: `verify-w2.php`, nuovo scenario `recheck` (import con collisioni, D1, D2, ripresa) e scenario `adapt` rieseguito. Uscite: `verify-w2-recheck.out.txt`, `verify-w2-adapt.out.txt` (la prima esecuzione, su `e7ebbef`, è in `verify-w2-adapt-e7ebbef.out.txt`), `verify-w2-manifest-r-import.json`, `verify-w2-manifest-r-adapt.json`.

### D1: CORRETTO

Stessi passi del report (Super admin → "A new user"), più due varianti:

| Caso | Esito | Messaggio |
|---|---|---|
| username `alice` (esistente) | PASS, resta su Super admin | "The Username field must contain a unique value." |
| email `alice@host.test` (esistente) | PASS | "The Email Address field must contain a unique value." |
| username `ALICE` (maiuscole) | PASS | stesso messaggio sullo username |
| `bob` / `BOB@HOST.TEST` | PASS | messaggi su entrambi i campi |
| `newroot` / `newroot@host.test` valido | PASS, si passa a Visibility | |

L'apply successivo crea un solo utente nuovo (`newroot`) ed è l'unico membro di `super-admin`. Nel log non compare più nessun `Duplicate entry`.

### D2: CORRETTO

Stessi passi del report (`Filters.php` in sola lettura, apply, poi un nuovo GET):
- risposta del POST: "Part of the installation is already applied. Check your choices and the changes still to make, then continue." insieme all'errore "Setup stopped at "Filter exception"…" e al testo da incollare. La frase "Nothing has been written yet" non compare più;
- GET successivo: stessa introduzione, sezione "Already applied" con "Database tables done, Roles done, Super admin done", poi le scelte e le modifiche ai file ancora da fare;
- prima di qualunque passo applicato, la conferma dice ancora "Nothing has been written yet.", che in quel momento è vero (scenario `adapt`).

### Regressione rapida

| Caso | Esito |
|---|---|
| Ospite su host con `session` globale → 302 al login dell'host | PASS |
| Codice richiesto anche da loggati; codice errato rifiutato; POST `step=roles` senza codice resta al codice | PASS |
| POST senza token CSRF → 403 | PASS |
| Domande 1–6 senza scritture: tabelle, hash dei file dell'host, settings, `writable/rolewarden` (solo codice e manifest), in import e in adatta | PASS |
| Import con collisioni: admin=Unisci (33 permessi, membri 1 e 8), user=Salta (0 membri); avviso "2 users lose 8 permissions" | PASS |
| Focus 4: id 5, 6, 7, 999 rifiutati | PASS |
| Password mai in chiaro nel manifest; l'hash viene tolto dopo la creazione (resta `id`) | PASS |
| Ripresa: blocco su Filters con `done` = migrations, roles, superAdmin, nessuna modifica nel registro, file dell'host invariati; host funzionante a metà; "continua" senza correzione resta fermo; risposte bloccate dopo l'avvio; tolta la sola lettura completa, con registro Filters, Routes, Auth e nessun duplicato (0/0/0 su `acl_user_roles`, `acl_role_permissions`, `acl_roles`) | PASS |
| Adatta completo: panelRole=admin, alice super admin, visibility=all; 3 modifiche, backup, codice cancellato, vista e redirect di login | PASS |
| Fine flusso: wizard 403; il ruolo del pannello e il nuovo super admin aprono `/rolewarden`; `can()` dell'host passa per RoleWarden (ivan, saltato, perde `tickets.view`) | PASS |
| Nessun dettaglio SQL a schermo (controllato su ogni risposta del wizard) | PASS |

Nella prima esecuzione di `recheck` è fallito il controllo `resume:no-duplicates`. Era un errore di quoting della query nel mio script, non del modulo: la stessa query lanciata a mano dava 0/0/0. Ho corretto lo script, ricostruito l'host e rieseguito da zero: tutti i controlli PASS.

Le ambiguità di specifica 1–5 del report restano aperte, non decise.

`rolewarden_test`: snapshot fatto prima della riverifica (SHA-256 `08c20bed…bd0`, diverso dal primo perché nel frattempo il database era cambiato), ripristinato alla fine. Il dump è identico allo snapshot e i CHECKSUM EXTENDED delle 16 tabelle sono uguali.

### Esito della riverifica

**PASS**: D1 e D2 sono corretti e la regressione non mostra difetti. Tappa W2 **approvata dal collaudo**, con le 5 ambiguità di specifica da decidere a parte.
