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
