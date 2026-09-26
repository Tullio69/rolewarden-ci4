# Collaudo indipendente V0 — Ambiente

Data: 26 settembre 2026. **Esito dei controlli eseguiti: PASS, 64 PASS / 0 FAIL su 22 risposte HTTP. Stato complessivo: BLOCCATO, V0 non approvata.** I conteggi descrivono solo la copertura HTTP: non sostituiscono le prove di deploy, reset e isolamento.

## Perimetro e riproduzione

Letti SPEC, sezioni App demo e Requisiti di pubblicazione; tappa V0 e voce demo della definizione di fatto in BRIEF-v1.0; registro AI e vincoli CLAUDE; i due README pubblici di rolewarden-demo. Nessuno script o file di implementazione vietato aperto. Le scansioni git grep hanno restituito solo nomi di file, mai righe di codice.

Repository demo sull'host: HEAD `fc75c2b7152201b649dd28be12ad62ae5c4ebf0c`, working tree pulito prima e dopo. Il commit effettivamente pubblicato nei container non è verificabile senza Docker. Nessun commit, branch o modifica al repository demo.

Eseguire dalla radice del modulo:

```powershell
python tests/Integration/verify-v0.py
```

Lo script usa curl verso `127.0.0.1:8090`, con Host esplicito. Ricava gli account dalla home e conserva password, CSRF e cookie soltanto in memoria. Salva esclusivamente esiti, status, header robots e impronte dei corpi in `verify-v0.output.json`. È un verificatore HTTP parziale, non un verificatore completo di V0.

## Risultati contro le otto richieste

| Richiesta | Esito | Evidenza e copertura mancante |
| --- | --- | --- |
| 1. Deploy e login | PARZIALE | Home 200 su entrambi i vhost; due account pubblici per ambiente, quattro login POST 303 e pannello utenti 200. Identità verificata nella riga contrassegnata “you”; accesso anonimo al pannello 302 verso login. Deploy non eseguiti. |
| 2. Cancellazioni HTTP e reset | NON ESEGUITO | Nessuna cancellazione avviata perché non è possibile garantire il reset finale. Mancano baseline dopo primo reset e confronto di ruoli, permessi, email e assegnazioni. |
| 3. HTTP 503 durante reset | NON VERIFICABILE | Reset non avviabile; non è un FAIL e non è una finestra di campionamento mancata. |
| 4. Email PHP e CI4 | NON ESEGUITO | CLI app e `/var/mail-sink` inaccessibili. Nessuna email di prova inviata. Firewall SMTP sul VPS non verificabile per definizione sulla replica. |
| 5. noindex e robots | PASS nel campione | Tutte le 22 risposte hanno `X-Robots-Tag: noindex, nofollow`, comprese 404, 302 anonimi e 303 dopo login. Entrambi i robots.txt vietano `/` a `*`. Non verificati 503 o ogni possibile errore del server. |
| 6. Due rifiuti fast-forward | NON ESEGUITO | Non creati origin temporanei né commit nei container. Mancano entrambe le prove di rifiuto e confronto commit/dati invariati. |
| 7. Rifiuto database estraneo | NON ESEGUITO | Nessun `.env` aperto o modificato; impossibile eseguire reset sulla copia prevista. |
| 8. Segreti e diagnostica | PARZIALE | Nessuna diagnostica PHP/SQL riconoscibile nel campione HTTP. Scansione nomi con password/passwd/secret/token restituisce 19 file; zero corrispondenze per intestazioni di chiavi private e formati comuni di token GitHub/AWS. Non certifica l'assenza di password reali arbitrarie: le occorrenze generiche non sono state aperte né classificate. |

Il pannello mostra otto utenti per ambiente. Non si tratta di un conteggio verificato direttamente sul database.

## Blocco dell'ambiente e stato finale

Il comando innocuo:

```powershell
docker inspect --format '{{.Name}} {{.State.Status}}' rolewarden-demo-web rolewarden-demo-db
```

fallisce per entrambi i container con `open //./pipe/dockerDesktopLinuxEngine: Access is denied.` Anche Git Bash fallisce all'avvio con `CreateFileMapping ... Win32 error 5`; il comando `MSYS_NO_PATHCONV=1 docker exec rolewarden-demo-web true` non raggiunge il container. La sessione non consente elevazione dei permessi. Non tentati canali alternativi per aggirare il blocco.

Non è stato possibile avviare la replica con compose, eseguire deploy iniziali o fare i due deploy di ripristino richiesti. Gli ambienti erano già raggiungibili. **Le home finali rispondono 200 e hanno corpi identici alle home iniziali**, ma questo non dimostra lo stato pulito dei database. I login possono aver aggiornato sessioni, registri di accesso e ultima attività: questi effetti non sono stati ripristinati. Nessuna cancellazione di utenti/ruoli, nessuna modifica a origin o `.env`.

Nessuna query diretta e nessun accesso ai container/database vietati. Nessuna credenziale salvata negli artefatti. Per completare serve una sessione autorizzata ad accedere al Docker engine; non basta rilanciare il verificatore HTTP.

## Difetti e ambiguità, separati

**Difetti V0 dimostrati: nessuno.** L'accesso negato al Docker engine è un limite dell'esecutore, non un difetto dell'applicazione. Durante la preparazione quattro asserzioni del verificatore richiedevano erroneamente un link logout, non previsto dal contratto; corretto l'oracolo verificando l'identità nel pannello, senza cambiare l'applicazione. I conteggi sopra si riferiscono all'esecuzione finale.

**Ambiguità A1 — confine V0/V8:** SPEC include modalità vetrina attiva nel reset della demo pubblica; BRIEF colloca app dimostrativa con tre ruoli e modalità vetrina in V8, mentre V0 riguarda l'ambiente. Il rapporto non decide se la modalità vetrina sia già criterio di accettazione V0 e non la classifica come difetto.

**Limite metodologico sui segreti:** cercare parole generiche senza esporre i file vietati individua candidati, ma non distingue una configurazione vuota da una credenziale reale. Il divieto di leggere l'implementazione è stato mantenuto; la verifica completa resta aperta.

---

# Collaudo V0, parte Docker: Collaudatore ad Hoc (Claude)

Data: 26 settembre 2026, in sostituzione di Codex, che dalla sua sandbox non raggiunge Docker. Stesso protocollo: letti solo SPEC, BRIEF-v1.0, `_AI-LOG.md` e i due README pubblici di rolewarden-demo, nessuno script o file di configurazione vietato aperto. Unica eccezione tecnica: letta la firma di `Boot::bootConsole` del framework CI4 in `vendor/` per invocare il servizio Email dal CLI. È codice del framework, non di V0.

**Esito: PARZIALE, V0 non approvata. 0 FAIL e nessun difetto dimostrato, ma i punti 6 e 7 non sono stati eseguiti.** L'host ha interrotto l'esecuzione di `verify-v0.docker.py` per memoria insufficiente, durante il punto 2 sulla demo. Non è un errore dello script né dell'applicazione. Per regola della sessione lo script non è stato rilanciato. L'interruzione è arrivata prima del blocco finale, quindi `verify-v0.docker.output.json` non è stato scritto: le evidenze vengono dal log della console di quell'esecuzione.

## Riproduzione

```sh
RW_REPLICA_DB_PASSWORD=<password usa e getta della replica> python tests/Integration/verify-v0.docker.py
```

- Script: `docker compose exec -T -u www-data web .../ops/<deploy|reset>.sh <env>`, come da `ops/README.md`.
- Query dirette solo su `rolewarden-demo-db`, database `rolewarden_staging` e `rolewarden_demo`.
- HTTP su `127.0.0.1:8090`.
- `verify-v0.mailprobe.php` è la sonda del servizio Email di CI4. Viene copiata in `/tmp` nel container e rimossa subito dopo.

Lo script copre anche i punti 6, 7 e 8 e il ripristino finale, ma quelle fasi non sono mai state eseguite. Su quelle parti lo script va considerato non collaudato.

## Esiti dell'esecuzione interrotta

Prima dell'interruzione: 0 FAIL. Ogni risposta HTTP della sessione aveva `X-Robots-Tag: noindex, nofollow` e nessuna diagnostica PHP/SQL riconoscibile.

| Punto | Esito | Evidenza |
| --- | --- | --- |
| 1. Deploy e login | PASS | `deploy.sh staging` e `deploy.sh demo` escono con 0. Pubblicato il commit di `origin/staging` e di `origin/main` (`fc75c2b`), working tree pulito, home 200. Login di admin@example.com e viewer@example.com fino al pannello su entrambi. Righe dopo il deploy: 4 ruoli, 12 permessi, 8 utenti, 8 utente-ruolo, 15 ruolo-permesso, 0 override, 10 migrazioni. Staging e demo hanno righe identiche. |
| 2. Cancellazioni e reset | PASS su staging, INTERROTTO su demo | Staging: con un POST dal pannello cancellati il ruolo non di sistema `viewer` e l'utente 8, e le righe cambiano. `reset.sh staging` esce con 0. Dopo il reset sono identici al primo reset: ruoli, permessi, utenti (email), utente-ruolo, ruolo-permesso, override utente, gruppi Shield e migrazioni. La sessione aperta prima del reset rimanda al login. Demo: cancellato via HTTP il ruolo `viewer`, poi l'esecuzione si è interrotta (ripristino più sotto). |
| 3. 503 durante il reset | PASS | Osservato in tutti e tre i reset campionati: 162 risposte 503 su 228 (deploy staging), 154 su 209 (deploy demo), 153 su 176 (reset staging). Ogni 503 ha noindex e nessuna diagnostica. Nessun altro 5xx. |
| 4. Email | PASS, firewall non verificabile | Tutti e tre i canali provati lasciano un `.eml` in `/var/mail-sink`: `mail()` da PHP CLI come www-data (ritorna true), il servizio Email di CI4 dal CLI dell'app (protocollo `mail`), e via Apache una richiesta vera a `/login/magic-link` di Shield su entrambi i vhost. `sendmail_path` = `/usr/local/bin/rolewarden-mail-sink`. Il blocco SMTP esiste solo sul VPS. Che il sink non inoltri nulla lo dice il contratto, non è dimostrato. |
| 5. noindex | PASS | Oltre al campione di Codex, verificato anche sulle pagine 503 del reset. |
| 6. Fast-forward | NON ESEGUITO | Interrotto prima di questa fase. Nessun origin temporaneo creato: verificato che l'origin di entrambe le checkout è ancora `/origin`. |
| 7. Guardia `.env` | NON ESEGUITO | Interrotto prima di questa fase. Nessun `.env` modificato: verificato che in entrambi la riga del database è quella attesa. |
| 8. Segreti e diagnostica | PARZIALE | Valori sulle righe con parole sensibili classificati a mano su tutta la storia del repo demo, senza leggere la logica. Mai committati `.env`, chiavi o `auth.json`: c'è solo `env`, il modello di CI4, con valori commentati. `.env.example` ha valori vuoti. `docker-compose.yml` contiene solo password usa e getta della replica: `rolewarden-local` e una root dello stesso tipo. `ops/README.md` usa il segnaposto `<password>`. Le password del seeder non compaiono come letterali sulle righe sensibili. Nessun formato noto di chiave o token, come già trovato da Codex. Nessuna diagnostica in nessuna risposta. |

## Ripristino e stato finale

Dopo l'interruzione la demo aveva il ruolo `viewer` cancellato e 4 `.eml` di prova nel sink. Ripristino eseguito:
- `deploy.sh demo` come da README: exit 0, `deploy demo: fc75c2b`.
- Rimossi i 4 `.eml` di prova: il sink è vuoto, come all'inizio.
- Staging non è stato più toccato: era già tornato allo stato iniziale con il reset del punto 2.

Stato finale:
- Commit `fc75c2b7152201b649dd28be12ad62ae5c4ebf0c` su staging e demo, origin `/origin` su entrambe, `.env` intatti.
- Demo: 4 ruoli attivi, 8 utenti attivi, 8 utente-ruolo, 15 ruolo-permesso.
- Staging: righe identiche al primo reset, confronto completo.
- Home 200 su entrambi i vhost.

Non toccati: container `rolewarden-db` (non in esecuzione) e i suoi database, container `scolibro_*`, repo host `rolewarden-demo`. Il repo host è montato in sola lettura come `/origin`: HEAD `fc75c2b`, working tree pulito.

## Difetti

Nessuno dimostrato.

## Ambiguità (non decise)

- **A1, modalità vetrina nel reset: SPEC contro BRIEF V8.** Confermo quanto rilevato da Codex. La presenza della modalità vetrina non è stata verificata.
- **A2, reset orario nella replica.** Secondo `ops/README.md` la replica "covers everything else" tranne il firewall SMTP. Nel container web però non gira nessun demone cron, solo Apache, quindi il reset orario della demo non si può osservare sulla replica. Non è chiaro se debba coprirlo.
- **A3, testo della home di staging.** La home di staging dice "All data is reset every hour". `ops/README.md` invece indica per staging "reset on every deploy" e riserva il reset orario alla demo. Quale delle due fonti vada corretta è da decidere.

## Limiti

- Punti 6 e 7 non eseguiti.
- Punto 2 sulla demo a metà.
- Firewall SMTP non verificabile.
- Reset orario non osservabile.
- Password del seeder non confrontate con la home senza leggerlo.

Per chiudere V0 serve rilanciare `verify-v0.docker.py` per intero quando la macchina ha memoria sufficiente.

---

# Collaudo V0 completo, riesecuzione: Collaudatore ad Hoc (Claude)

Data: 26 settembre 2026, sera. Il collaudo sostituisce Codex, la cui sandbox non raggiunge Docker, con lo stesso protocollo. Ho letto solo SPEC ("App demo", "Requisiti di pubblicazione"), BRIEF-v1.0 (V0 e definizione di fatto), `_AI-LOG.md`, i due README di rolewarden-demo e le risposte HTTP. Non ho aperto nessun file vietato (`ops/*.sh`, `ops/server|php|apache|cron`, `docker/**`, `docker-compose.yml`, `app/**`). Dove la scansione dei segreti tocca quei file, ho usato solo nomi di file e impronte, mai il contenuto. Versione pubblicata: `ab9348f` su staging e demo.

**Esito: PASS. 330 PASS / 0 FAIL, 38 INFO, 92 risposte HTTP. V0 approvata per quanto si può verificare sulla replica.** I limiti sono elencati più sotto. A1 non è più un'ambiguità: la vetrina nasce in V8, come deciso nella SPEC.

## Riproduzione

Lo script si esegue a fasi separate per tenere basso il carico sull'host. Lo stato fra una fase e l'altra sta nel file indicato da `RW_V0_STATE`, fuori dal repo e senza password:

```sh
export RW_REPLICA_DB_PASSWORD=<password usa e getta della replica> RW_V0_STATE=<file temporaneo>
python tests/Integration/verify-v0.docker.py deploy cron   # punti 1, 3, A2, A3
python tests/Integration/verify-v0.docker.py mail reset    # punti 4, 2, 3
python tests/Integration/verify-v0.docker.py ff            # punto 6
python tests/Integration/verify-v0.docker.py envguard      # punto 7
python tests/Integration/verify-v0.docker.py secrets final # punto 8 e ripristino
```

Senza argomenti lo script esegue tutte le fasi. Rispetto alla versione precedente ho cambiato quattro cose:
- fasi separate e stato persistito;
- poller della home ogni 100 ms invece di 20 ms;
- controlli A2 e A3;
- nel punto 6, il campionamento della home durante il deploy rifiutato, per vedere che non compaia nemmeno la pagina 503.

Output cumulativo in `verify-v0.docker.output.json`.

**Incidente del collaudatore, non dell'applicazione.** Alla prima esecuzione della fase `reset`, 12 confronti del punto 2 sono risultati FAIL con conteggi identici. La causa era nello script: l'impronta salvata nello stato JSON diventa una lista di liste, mentre quella appena letta era una lista di tuple. Ho verificato la causa confrontando le impronte normalizzate: tutte uguali. Poi ho corretto `sql()` in modo che restituisca liste, ho rimosso dallo stato le righe di quella fase e ho rilanciato `reset` per intero, con nuove cancellazioni HTTP e nuovi reset. I conteggi qui sopra vengono dalla riesecuzione.

## Risultati per punto

| Punto | Esito | PASS | Evidenza |
| --- | --- | --- | --- |
| 1. Deploy e login | PASS | 15 | `deploy.sh staging` e `deploy.sh demo` escono con 0. HEAD = `origin/staging` e `origin/main` = `ab9348f`, working tree pulito, home 200, due account pubblici sulla home. Login admin e viewer fino al pannello su entrambi. Righe dopo il primo reset: 4 ruoli, 12 permessi, 8 utenti, 8 utente-ruolo, 15 ruolo-permesso, 0 override, 10 migrazioni. Staging e demo sono identici. |
| 2. Cancellazioni e reset | PASS su entrambi | 36 | Su ogni ambiente: POST dal pannello che cancella il ruolo non di sistema `viewer` (id 4, `is_system` 0) e l'utente 8, e le righe cambiano. `reset.sh` esce con 0. Dopo il reset sono identici al primo reset ruoli, permessi, utenti (email, username, stato), utente-ruolo, ruolo-permesso, override, gruppi Shield e migrazioni. La sessione aperta prima del reset rimanda a `/login`, home 200. |
| 3. 503 durante il reset | PASS | 16 | Il 503 compare in tutti e quattro i reset campionati: deploy staging 41/60, deploy demo 39/55, reset staging 38/44, reset demo 38/44. Ogni 503 ha noindex e nessuna diagnostica, nessun altro 5xx. |
| 4. Email | PASS, firewall non verificabile | 5 | Un file `.eml` in `/var/mail-sink` per ciascun canale: `mail()` da PHP CLI come www-data (true), il servizio Email di CI4 dal CLI dell'app (`protocol=mail sent=true`) e il link magico di Shield via Apache su entrambi i vhost. `sendmail_path` = `/usr/local/bin/rolewarden-mail-sink`. Il blocco SMTP esiste solo sul VPS. |
| 5. noindex e robots | PASS | 184 (+ i 503 del punto 3) | Tutte le 92 risposte hanno `X-Robots-Tag: noindex, nofollow`: 65 risposte 200, 19 redirect 303, 6 redirect 302 e 2 errori 404, più tutte le pagine 503. Il `robots.txt` di entrambi i vhost vieta `/` a `*`. |
| 6. Fast-forward | PASS | 26 | La divergenza è creata solo dentro il container, con un clone bare in `/tmp/v0ff` usato come origin della checkout. Tre casi. (a) main avanti a staging: la demo rifiuta con "ABORT: origin/main has commits that are not on origin/staging ... Nothing was changed.". (b) staging riscritto, non fast-forward: staging rifiuta con "origin/staging is not a fast-forward of the deployed commit ab9348f. Nothing was changed.". (c) main = staging, entrambi riscritti: la demo rifiuta con lo stesso motivo. In tutti e tre i casi: exit diverso da 0, stesso HEAD, working tree pulito, CHECKSUM TABLE di tutte le tabelle di entrambi i database e l'elenco dei database identici, nessun 503 durante il tentativo (solo 200), la sessione aperta prima resta valida, home 200. Alla fine l'origin di entrambe le checkout è di nuovo `/origin` e `/tmp/v0ff` è rimosso. |
| 7. Guardia `.env` | PASS | 15 | Tre casi: staging → `rolewarden_demo`, demo → `rolewarden_staging`, staging → `rolewarden_v0_estraneo`. Ogni volta `reset.sh` rifiuta con "ABORT: .env names database '...', expected 'rolewarden_<env>'. Nothing was changed.". Checksum dei database e l'elenco dei database invariati (nessun database creato), sito non lasciato in 503, `.env` ripristinato con sha256 identico all'originale. |
| 8. Segreti e diagnostica | PASS | 2 (+ la diagnostica sulle 92 risposte) | Nessun formato noto di chiave o token in tutta la storia del repo, nessun `.env`, chiave o `auth.json` mai committato. Un solo letterale non classificato, in `app/Config/Security.php`. L'ho riconosciuto dall'impronta, senza aprire il file: è `X-CSRF-TOKEN`, il nome di header di serie di CI4, non un segreto. Per nome di file, le password pubbliche compaiono solo dove ci si aspetta: le password demo della home in `DemoSeeder.php`, la password usa e getta `rolewarden-local` in `docker-compose.yml` e in `docker/db/init.sql`. Così si chiude anche il limite del collaudo precedente sulle password del seeder. Nessuna diagnostica PHP/SQL in nessuna risposta, 503 compresi. |
| A2 | Chiusa | 2 | `ops/README.md` ora dice che il reset orario richiede il VPS ("the replica runs no cron"). Nel container web girano solo i processi `apache2`, nessun cron: il runbook e la replica sono coerenti. |
| A3 | Chiusa | 2 | La home di staging, e anche quella della demo, dice solo "Anything you change is wiped at the next reset", senza promettere un reset orario. È coerente con il runbook. |
| Ripristino finale | PASS | 14 | `deploy.sh staging` e `deploy.sh demo` escono con 0. Commit = origin, righe identiche al primo reset, home identica byte per byte a quella dopo il primo deploy, `robots.txt` corretto, pannello anonimo 302 verso `/login`, 404 sulle pagine inesistenti. Il mail sink è tornato vuoto come all'inizio. |

Gli altri 13 PASS sono controlli di contorno: replica in esecuzione e repo host invariato, verificati a ogni fase.

## Difetti

Nessuno.

## Ambiguità (non decise)

Nessuna nuova. A1 è decisa nella SPEC; A2 e A3 sono chiuse dalla versione `ab9348f`, verificata qui sopra.

Due osservazioni informative, fuori dai criteri di V0 e non classificate come difetti:
- Le risposte espongono `X-Powered-By: PHP/8.3.35`. La specifica non ne parla.
- Un deploy rifiutato esegue comunque `git fetch`, quindi i riferimenti remoti della checkout si aggiornano. Commit pubblicato, dati e sito restano invariati, quindi "Nothing was changed" regge nel senso del runbook.

## Limiti del collaudo

- Il blocco firewall SMTP esiste solo sul VPS e qui non è verificabile. Che il sink non inoltri i messaggi lo dice il contratto: l'ho constatato solo come assenza di errori e presenza del file.
- Il reset orario non è osservabile sulla replica, per contratto: la replica non ha cron. `reset.sh demo` a mano è verificato.
- Nel punto 7 la guardia si prova modificando in posto il `.env` della checkout, con una copia di riserva e il ripristino verificato con sha256, perché `reset.sh` legge quel file. Per pochi secondi il vhost ha letto un `.env` che nominava l'altro database. Nessuna richiesta HTTP ne ha scritto: i checksum sono invariati.
- La scansione dei segreti si ferma ai letterali sulle righe con parole sensibili e ai formati noti. Una credenziale arbitraria su una riga senza quelle parole non verrebbe rilevata senza leggere i file vietati.

## Stato finale degli ambienti

- Staging e demo: commit `ab9348f3163170d8afcae6e7997489af4947e03b`, origin `/origin`, working tree pulito, `.env` intatti (una riga `database.default.database = rolewarden_<env>`).
- Righe per ambiente, identiche al primo reset: 4 ruoli, 12 permessi, 8 utenti, 8 utente-ruolo, 15 ruolo-permesso, 0 override utente, 0 gruppi Shield, 10 migrazioni.
- `/var/mail-sink` vuoto e `/tmp` del container vuoto.

Non toccati: il container `rolewarden-db` e i database `rolewarden` e `rolewarden_test`, i container `scolibro_*` e il repo host `rolewarden-demo` (HEAD `ab9348f`, pulito prima e dopo, verificato a ogni fase). Nessun commit. Nessuna credenziale nei file del collaudo: lo script e l'output non contengono le password.
