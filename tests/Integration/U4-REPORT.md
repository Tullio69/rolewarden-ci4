# U4 — Dashboard (v1.1): collaudo indipendente

**Collaudatore ad Hoc (Claude), 2026-10-24, commit `cc26ffb` (main).**

## Esito

**FAIL. U4 non è approvata.**

- La dashboard fa ciò che la specifica chiede: ogni blocco solo col suo permesso, redirect senza nessun `dashboard.*`, cifre, grafici e tabelle uguali alle query dirette per 7, 30 e 90 giorni, avviso di sicurezza, colori solo da token, inglese e italiano, nessun errore.
- **Un difetto, D1, nella migrazione di aggiornamento** (punto 5): oltre ai cinque `dashboard.*`, la migrazione ridà ad `admin` i permessi di serie che l'acquirente gli aveva tolto. Il rollback non li toglie.
- Quattro ambiguità di specifica (A1–A4), da decidere. Non sono difetti.

Ho letto solo `CLAUDE.md` (protocollo), `docs/SPEC.md` (Pannello admin, decisioni v1.1 e U4), `docs/BRIEF-v1.1.md`, `docs/design-system/` (README, components/Dashboard), `_AI-LOG.md`, `CHANGELOG.md` (Unreleased), gli script e i report di `tests/Integration/`, i file di lingua (solo traduzioni) e HTML e CSS serviti. Del commit conosco solo il `--stat`. Non ho aperto `src/Dashboard/`, `src/Controllers/`, `src/Views/`, `src/Database/`, `src/Settings/` né `tests/Unit/`.

| Suite | development | production |
| --- | --- | --- |
| U4 DASH (blocchi, numeri, avviso, colori, lingue, redirect) | **238/238** | **239/239** (in più SQL-prod) |
| U4 UPG (seed, aggiornamento da 1.0.0, rollback) | **19/23** (FAIL = D1 ×4) | **19/23**, uguale |
| U3 SRCH (regressione) | 40/41 (L-1 = adattamento, vedi sotto) | 40/41, uguale |
| U2 CHK, ST (regressione) | **129/129** | **129/129** |
| U1 NAV…ESC (regressione) | 222/226 non adattata (4 = adattamento) | **226/226** adattata |

**Totale U4:** 515/523 nei due ambienti. Gli 8 FAIL sono tutti D1.

## Come

- **App di prova:** copia usa e getta di `../rolewarden-app-test`, col modulo da `git archive cc26ffb`. Server `php -S` sulla porta 8071, `error_reporting=-1`, log a soglia 9.
- **Database e posta:** solo `rolewarden_test` (127.0.0.1:3317) e Mailpit. Credenziali solo come variabili di processo. Ogni esecuzione fa lo snapshot all'inizio, poi ripristina e confronta l'hash (`verify-u4.environment.txt`: sempre `identical=True`).
- **Riferimento:** ogni numero è ricalcolato con query dirette su `auth_logins`, `users`, `acl_user_roles`/`acl_roles` e `acl_activity_log`, sul periodo della SPEC (N giorni di calendario fino ad adesso, oggi compreso; precedente: gli N giorni prima).
- **Script:**
  - `verify-u4.suite.py`, `verify-u4.harness.php`, `verify-u4.all.sh` (driver);
  - fasi `verify-u4.phase-dash.php`, `verify-u4.phase-upg.php`, `verify-u4.phase-x.php` (sonda, senza controlli);
  - copie adattate per la regressione: `verify-u4.u1lib.adapted.php`, `verify-u4.phase-{nav,rail,lang,srch}.adapted.php`.

## 1. Blocchi e permessi (PERM, NAV)

- **21 account:**
  - super admin, admin, `user`;
  - un ruolo per ciascuno dei cinque permessi, e un ruolo con due permessi;
  - ereditarietà da un ruolo padre;
  - override positivo su `user`; admin con `figures` e `security` negati; super admin con `changes` negato (l'override vince sul super admin); figlio con negazione più override positivo;
  - admin e super admin disattivati, senza ruolo;
  - sette account senza `dashboard.*` ma con un solo permesso di schermata.
- **P-\* PASS:** con l'avviso presente, `/rolewarden` rende esattamente i blocchi dei permessi posseduti. `signins` porta sia il grafico nel tempo sia la mappa di calore. Le etichette dei blocchi assenti non arrivano nella pagina.
- **Senza nessun `dashboard.*` PASS:** 302 alla prima voce della barra di quell'utente (users, roles, permissions, activity, settings, appearance; con settings+roles va su roles), altrimenti al profilo. Disattivati e senza permessi vanno al profilo, e la schermata si apre (200).
- **Revoca a sessione aperta PASS:** il blocco sparisce alla richiesta successiva; tolto anche l'ultimo `dashboard.*`, la pagina reindirizza. Anonimo → login.
- **NAV PASS:** la voce Dashboard compare nel gruppo Overview, prima voce, solo con almeno uno dei cinque, e su `/rolewarden` ha `aria-current="page"`.

## 2. Numeri (NUM), per 7, 30 e 90 giorni

- **Dati:**
  - 914 righe di `auth_logins` su 200 giorni (anche `id_type` diversi da email_password);
  - righe al primo secondo di ogni periodo e del precedente, e al secondo prima;
  - 54 utenti, con attivi, disattivati, cancellati ed età di creazione varie;
  - 7 ruoli di prova con conteggi distinti, un ruolo cancellato con 6 assegnazioni, un utente con due ruoli;
  - 170 voci di log sparse su 120 giorni: le sette azioni di modifica e altre azioni, autore nullo compreso.
- **Cifre PASS:**
  - utenti attivi sul totale dei non cancellati;
  - variazione = account creati nel periodo;
  - accessi riusciti e falliti, con variazione sul periodo precedente;
  - modifiche ai permessi con il numero degli override nella nota;
  - picco nella nota degli accessi, falliti del periodo precedente nella nota dei falliti.
  - Variazioni con segno: `+N`, `−N` (U+2212) e `0`, tutte e tre osservate. Solo la prima cifra è in `accent`.
- **Accessi nel tempo PASS:**
  - tabella con gli N giorni, dal più vecchio, riusciti e falliti uguali alle query;
  - totali nella legenda;
  - SVG `role="img"` con etichetta: un punto della linea per giorno, altezza proporzionale (lo zero sta sulla base);
  - una barra per ogni giorno con falliti, proporzionale, sulla stessa scala della linea;
  - numeri in un `<details>`.
- **Mappa di calore PASS:**
  - tabella 7×24 (lun–dom) uguale alle query;
  - ogni cella ha livello `ceil(4 × n / massimo)` (0 se vuota) e il conteggio nel `title`;
  - `role="img"` con etichetta, numeri in un `<details>`.
- **Utenti per ruolo PASS:** assegnazioni dirette di utenti non cancellati a ruoli non cancellati, in ordine decrescente, primi quattro più «Other roles» = somma delle altre. L'utente con due ruoli conta in entrambi; il ruolo cancellato e gli utenti cancellati non contano.
- **Ultime modifiche PASS:** le cinque più recenti del periodo, autore e soggetto giusti, «The system» per l'autore nullo. «View all N» ha N = modifiche del periodo e apre `activity?from=<inizio>&to=<oggi>` (200).
- **Periodo PASS:**
  - senza `?period=` vale 30;
  - l'intestazione dice le date;
  - con `app.appTimezone = Pacific/Kiritimati` l'ultimo giorno è l'oggi di quel fuso (TZ-1).

## 3. Avviso di sicurezza (SEC), tutto PASS

- **Compare:**
  - un IP con 5 falliti (`lockAttempts`) in un giorno di 3 giorni fa, nei periodi 7, 30 e 90;
  - 12 giorni fa: compare in 30 e 90, non in 7;
  - soglia portata a 3 dalle Impostazioni: 3 falliti bastano; riportata a 5, l'avviso sparisce;
  - email bloccata adesso (5 falliti da 5 IP diversi; la schermata di accesso conferma il blocco).
- **Non compare:**
  - con 4 falliti;
  - con 4 falliti un giorno e 1 il giorno dopo;
  - con accessi riusciti dallo stesso IP;
  - per un'email il cui blocco di 15 minuti è scaduto;
  - per un'email con 5 falliti seguiti da un riuscito;
  - nei dati di base.
- **Azione:**
  - per l'IP: `activity?action=auth.failed&from=G&to=G`, con G il giorno della soglia;
  - per la sola email bloccata: oggi;
  - si apre (200). Una frase, un'azione, nessun `denied`.

## 4. Tabelle, colori, lingue, errori

- **CLR PASS:**
  - nessun colore letterale nel markup della dashboard;
  - nessun `granted`/`denied` nei grafici;
  - nel CSS servito ogni regola `rw-dash*`, `rw-heat*`, `rw-share*`, `rw-legend` colora solo con variabili;
  - `rw-heat-0..4` → `heat-0..4`; linea `accent`, area `accent-tint`, barre `ink`, mezzeria `rule-strong`, base `ink`.
- **LANG PASS:**
  - stesse chiavi `panel.dashboard` in inglese e in italiano, nessuna vuota, nessuna rimasta in inglese;
  - la pagina in italiano non contiene testo inglese della dashboard (giorni e mesi compresi).
- **HOS PASS:**
  - `period` = 0, 1, 14, 365, -7, abc, 7.5, « 7», 07, 7abc, 1e1, NUL, byte non UTF-8, array, valori ripetuti: sempre 200, con 7, 30 o 90, nessun errore;
  - POST, PUT e DELETE su `/rolewarden`: mai 2xx.
- **SQL-prod PASS:** in production, con `acl_activity_log` assente, nessun testo SQL né traccia a schermo.
- **LOG PASS:** nessuna risposta ≥ 500, nessuna riga WARNING/ERROR/CRITICAL nel log a soglia 9, nessun warning PHP nel server.

## 5. Aggiornamento da 1.0.0 (UPG)

- **Installazione nuova PASS:** il seeder crea i cinque `dashboard.*` e li concede solo ad `admin`.
- **Percorso A, 1.0.0 → `cc26ffb` con un solo migrate.**
  - **PASS:** i cinque permessi vengono creati e concessi ad `admin` e a `u4-auditor` (che ha `activity.view` diretto). Non li riceve `u4-auditor-kid`, che lo eredita (ma vede la dashboard per eredità), né `u4-plainrole`. Dopo l'aggiornamento il pannello è coerente: 200 per auditor, figlio, admin e super admin; redirect per il ruolo semplice.
  - **FAIL UPG-A3/A4:** vedi D1.
- **Percorso B, 1.0.0 → `8dc8a49` (U3) → `cc26ffb`.** La migrazione U4 sta da sola nel proprio lotto.
  - **PASS:** `admin` senza `activity.view` riceve comunque i cinque permessi, come dice il CHANGELOG.
  - **PASS:** dopo il rollback non resta nessun `dashboard.*`: né permessi, né concessioni (anche quella data a mano a un altro ruolo), né override (positivi e negativi).
  - **PASS:** l'override non correlato, scritto tra migrazione e rollback, resta.
  - **PASS:** rifacendo il migrate tornano le stesse concessioni.
  - **FAIL UPG-B3/B5:** vedi D1.

## Difetti

**D1 — La migrazione `AddDashboardPermissions` ridà ad `admin` i permessi di serie revocati dall'acquirente. Il rollback non li toglie.**

- **Riproduzione:** installare la 1.0.0, togliere ad `admin` `users.delete` (e, nel percorso B, `activity.view`), poi lanciare `php spark migrate -n RoleWarden` con la 1.1.
- **Cosa succede:** oltre alle concessioni dei cinque `dashboard.*`, compaiono `admin:users.delete` e `admin:activity.view`.
  - Lo si vede nel solo lotto U4 (percorso B, S1 → S2): `grants added besides dashboard.*: ["admin:activity.view","admin:users.delete"]`.
  - Dopo `migrate:rollback` il database è quello di prima più le righe `acl_role_permissions` (2,4) e (2,18) (UPG-B5).
  - Nome del ruolo, permesso dell'acquirente e override restano intatti; il difetto tocca solo le concessioni di serie di `admin`.
- **Perché è un difetto:**
  - BRIEF: «Aggiornamento senza sorprese».
  - Definizione di fatto: «il rollback della migrazione toglie solo quelli».
  - SPEC U4: «il rollback cancella i cinque permessi … nient'altro».
  - Prompt del collaudo: la migrazione «li concede ad admin e a ogni ruolo con `activity.view`».
  - Una revoca deliberata dell'acquirente viene annullata, e in modo permanente.
- **Uscite:** `verify-u4.console.upg.{development,production}.txt`.

## Ambiguità di specifica (da decidere, non decise qui)

- **A1 — Mappa di calore in HTML.** La SPEC dice «Grafici. SVG generati lato server dai token», il design system «Charts are server-side SVG». La mappa di calore è invece una griglia HTML (`<i class="rw-heat-n">` dentro un `div role="img"`). È disegnata lato server, sui token e senza JavaScript, ma non è SVG. Va bene così?
- **A2 — Numeri degli utenti per ruolo fuori da `<details>`.** La SPEC U4 dice «Tabella dei numeri: sotto ogni grafico, in un `<details>`». La barra divisa degli utenti per ruolo ha invece i numeri in un elenco a vista, la «ledger list» del design system, e nessun `<details>`. Le due fonti dicono cose diverse.
- **A3 — Ruolo cancellato.** Un ruolo cancellato (`deleted_at`) con `activity.view` diretto riceve anch'esso i cinque `dashboard.*` dalla migrazione. È innocuo, ma la SPEC dice «ogni ruolo».
- **A4 — Più motivi di avviso insieme.** Con due IP alla soglia in due giorni, oppure con un IP alla soglia e un'email bloccata, l'avviso nomina solo l'IP del giorno più recente e il link apre quel giorno. L'email bloccata non è citata. La SPEC non dice quale motivo scegliere.

**Osservazioni, non bloccanti:**

- `?period=7abc`, `7.5`, ` 7` valgono 7, perché l'intero si legge con tolleranza; un array vale 30.
- Dopo l'accesso partito da `/rolewarden?period=7` la query si perde (comportamento di Shield).
- Il README del design system parla di «defaults per role» per la dashboard, che la SPEC esclude dalla 1.1.

## Regressione e adattamenti

- **Adattamenti dei test, non regressioni.** La voce Dashboard è un elemento in più, e prima della U4 i test lo escludevano per costruzione.
  - **U1, quattro FAIL di attesa nella versione non adattata:**
    - N-grp: «nessun gruppo Overview»;
    - N-mid, R-5: conteggi fissi di 7 voci;
    - L-1: «Dashboard» uguale in italiano.
  - **U3 L-1:** «Dashboard» uguale in italiano.
  - **Le copie adattate:**
    - aggiungono `rolewarden` a NAVMAP (i suoi account hanno tutti e cinque i permessi o nessuno);
    - portano i conteggi a 8;
    - ammettono il prestito «Dashboard», come già Paper/Ink/Charcoal.
  - **Esito:** U1 production adattata 226/226. Uscite non adattate in `verify-u4.console.u1.development.unadapted.txt` e `verify-u4.console.u3.*.unadapted.txt`.
- **Riesecuzione interrotta dall'host.** La riesecuzione adattata di U3 in development (`*.adapted-interrupted.*`) è stata fermata per memoria insufficiente: i processi spark uscivano con `rc=-1073741502`. Quell'uscita non vale. Il database l'ho ripristinato a mano dallo snapshot della corsa (hash identico, riga in `verify-u4.environment.txt`). Non ho rilanciato U3 production adattata e U1 development adattata.
- U2 passa senza adattamenti in entrambi gli ambienti.

## Riverifica su c998ac8

**Collaudatore ad Hoc (Claude), 2026-10-24, commit `c998ac8` (correzione di D1). Esito: PASS. D1 è chiuso. U4 è approvata per quanto riguarda i difetti; A1–A4 restano aperte e vanno decise.**

Stesse regole di lettura del primo giro. Ho aperto solo il report U4 e i runner `verify-u4.*`. Driver: `verify-u4.all.sh` con `RW_V8_NEW=c998ac8`, una suite alla volta.

| Suite | development | production |
| --- | --- | --- |
| U4 UPG (seed, aggiornamento da 1.0.0, rollback) | **27/27** | **27/27** |
| U4 DASH | **238/238** | **239/239** |
| U3 SRCH (copia adattata) | **41/41** | **41/41** |
| U2 CHK, ST | **129/129** | **129/129** |
| U1 NAV…ESC (copie adattate) | **226/226** | **226/226** |

- **Controlli nuovi in `verify-u4.phase-upg.php`:**
  - UPG-fresh-3/4: installazione nuova senza demo. Ad `admin` non manca nessun permesso di serie più che su una 1.0.0 nuova, e ha i cinque `dashboard.*`. Su tutte e due le versioni mancano ad `admin` gli stessi cinque permessi: `admin.access`, `admin.settings`, `beta.access`, `users.edit`, `users.manage-admins`. È lo stato della 1.0.0, che la SPEC non contraddice, quindi non è un difetto.
  - UPG-A6/A7: percorso A. Il `migrate:rollback` dell'unico lotto 1.1 riporta il database esattamente allo stato 1.0.0 personalizzato: ogni tabella, ogni riga, diff vuoto. Rifacendo il migrate tornano le stesse concessioni e nessun'altra.
- **D1 chiuso:**
  - UPG-A3/A4: dopo l'aggiornamento diretto da 1.0.0 nessuna concessione in più oltre ai `dashboard.*`, e `admin` continua a non avere `users.delete`.
  - UPG-B3: il solo lotto U4 aggiunge i cinque permessi, le loro concessioni e la sua riga in `migrations`, nient'altro. `admin` resta senza `users.delete` e `activity.view`.
  - UPG-B5: dopo il rollback il database è quello di prima, più l'override non correlato scritto nel frattempo. Concessioni aggiunte o mancanti: `[]`.
- **Regressioni completate** (nel primo giro erano rimaste interrotte o non lanciate):
  - U3 development e production adattate: 41/41, nessuna uscita `rc=-1073741502`.
  - U1 development adattata: 226/226.
- **Ambiguità:** A1–A4 restano aperte. Il commit non le tocca, e A3 si osserva ancora: il ruolo cancellato `u4-gone-auditor` riceve i cinque permessi.
- **Ambiente:**
  - Database identico allo snapshot `f7f37c04…e21e64` prima e dopo ogni esecuzione (`verify-u4.environment.txt`), Mailpit vuoto, nessun processo residuo.
  - Resta da cancellare a mano un profilo Chrome bloccato, in `scratchpad/u4r-u2-development` e `scratchpad/old-u4r-u2-development`.

## Seconda riverifica su 29a5df6

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `29a5df6` (decisioni dell'utente su A1–A4). Esito: PASS. U4 è approvata. A1–A4 sono chiuse come decise; resta una nuova ambiguità minore, A5, non bloccante.**

Stesse regole di lettura dei giri precedenti. Ho aperto in più solo `git show 29a5df6 -- docs CHANGELOG.md`; del resto del commit conosco solo il `--stat`. Driver `verify-u4.all.sh` con `RW_V8_NEW=29a5df6`, una suite alla volta.

| Suite | development | production |
| --- | --- | --- |
| U4 DASH (con i controlli A1, A2, A4) | **256/256** | **257/257** (in più SQL-prod) |
| U4 UPG (con i controlli A3) | **29/29** | **29/29** |
| U3 SRCH (copia adattata) | **41/41** | **41/41** |
| U2 CHK, ST | **129/129** | **129/129** |
| U1 NAV…ESC (copie adattate) | **226/226** | **226/226** |

**Totale:** 1363/1363. Il numero dei controlli `N*-neg-*` (segno meno osservato) dipende dai dati del momento: 256 contro i 238 del giro precedente sono +18 controlli nuovi e un `neg` in meno.

### Controlli adattati e nuovi

- **A1, mappa di calore in SVG** (`verify-u4.phase-dash.php`):
  - N*-H4 (nuovo): un `svg` con `role="img"` e `aria-label`, 168 `rect` (7 × 24), nessuna griglia HTML di celle.
  - N*-H2 (adattato): il livello si legge dalla classe `rw-heat-n` di ogni `rect`, il conteggio dal suo `<title>` («N sign-ins, Mon 0:00»), e il giorno dal titolo stesso, non dall'ordine nel markup. Le celle si leggono dall'HTML grezzo, perché il parser HTML di libxml scarta un `<title>` dentro un `svg` in linea. Livello `ceil(4n/max)` di ogni cella: PASS in 7, 30 e 90 giorni.
  - H1 e H3 invariati (tabella in `<details>` uguale alle query): PASS. CLR-4 (`rw-heat-0..4` → `heat-0..4`): PASS.
- **A2, numeri degli utenti per ruolo:**
  - N*-R3 (nuovo): la tabella sta in un `<details>` sotto la barra, una riga per quota, nessuna riga fuori.
  - N*-R4 (nuovo): a vista solo la legenda dei nomi, nello stesso ordine delle quote, senza numeri.
- **A3, ruolo cancellato:**
  - UPG-A2 e UPG-B2 ora esigono esattamente `admin` più i ruoli non cancellati con `activity.view` diretto: niente più tolleranza per `u4-gone-auditor`.
  - UPG-A2-gone e UPG-B2-gone (nuovi): il ruolo cancellato non riceve nessuno dei cinque, sia nell'aggiornamento diretto da 1.0.0 sia nel solo lotto U4.
- **A4, avviso con più motivi:** le righe dell'avviso sono gli `<li>` del riquadro. Al posto del vecchio SEC-n:
  - SEC-m-lines, SEC-m-link: due IP in due giorni (5 e 2 giorni fa) danno due righe, prima il più recente; link da 5 a 2 giorni fa.
  - SEC-n-lines, SEC-n-link, SEC-n-one: in più un'email bloccata. Tre righe, l'email per ultima; link dal giorno più vecchio a oggi; sempre un solo avviso e una sola azione.
  - SEC-o30, SEC-o7: lo stesso IP alla soglia in due giorni (8 e 3 giorni fa) dà una riga per giorno. In 7 giorni resta solo il giorno dentro il periodo, e il link è quel giorno.
  - SEC-p: due IP nello stesso giorno danno due righe, e il link è quel giorno.
  - SEC-q: due email bloccate da sole. Tutte e due citate, nessuna riga di IP, link oggi.
  - SEC-r30, SEC-r90, SEC-r-open: un IP alla soglia 40 giorni fa più le email bloccate. In 30 giorni l'IP non è citato e il link è oggi. In 90 giorni prima l'IP, poi le email, e il link va da 40 giorni fa a oggi. Il link si apre (200).
  - I casi con un solo motivo (SEC-a, SEC-g) restano invariati e passano: per l'IP il link è il suo giorno, per la sola email è oggi.
- **Sensibilità:** le stesse suite su `c998ac8` falliscono proprio sui controlli nuovi. UPG 25/29: A2, A2-gone, B2, B2-gone. DASH development 235/256: H2, H4, R3 e R4 nei tre periodi, e tutti i SEC A4 tranne `n-one`, `r30` e `r-open`. Uscite in `verify-u4.console.sens.*.c998ac8.development.txt`.
- **Nessuna regressione su D1:** UPG-A3, A4, A6, A7 e B3–B6 passano invariati in tutti e due gli ambienti.

### Difetti

Nessuno.

### Ambiguità nuove (da decidere, non decise qui)

- **A5 — Più email bloccate: una riga sola.** Con due email bloccate l'avviso usa una sola riga: «2 emails are locked after too many failed sign-ins: locked3@u4.test, locked4@u4.test». Ogni IP alla soglia ha invece la sua riga. La SPEC dice «una riga ciascuno: ogni IP … poi tutte le email bloccate». Il README del componente dice «one line per reason (every address at the lock threshold, then the locked emails)». Non è chiaro se le email bloccate siano un solo motivo o una riga ciascuna. Il comportamento osservato è coerente con la lettura «le email bloccate sono un motivo». Non è bloccante.

### Ambiente

- **Database:** solo `rolewarden_test` (127.0.0.1:3317). Snapshot identico prima e dopo in ogni esecuzione (`f7f37c04…e21e64`, `verify-u4.environment.txt`), Mailpit vuoto, nessun processo residuo.
- **Sonda X** su `29a5df6` per leggere il markup servito. I dump HTML sono nello scratchpad, non nel repository.
- **Primo `u2.development`:** si è fermato all'avvio con «ABORT: existing work directory». È il profilo Chrome bloccato del giro precedente, `scratchpad/u4r-u2-development`. L'ho rilanciato con la cartella di lavoro `u4r2-u2-development`: 129/129. Il profilo bloccato resta da cancellare a mano (`old-u4r-u2-development` invece è stato rimosso).
