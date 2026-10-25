# U5 — Personalizzazione e blocchi (v1.1): collaudo indipendente

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `09b9159` (main).**

## Esito

**FAIL. U5 non è approvata.**

- La personalizzazione fa quasi tutto ciò che la SPEC chiede: scelte per utente indipendenti, predefinito globale solo con `settings.update` e registrato come `settings.updated`, "Torna al predefinito", `?period=` che vince, avviso sempre in cima, salvataggio che tocca solo i blocchi visibili, CSRF, pannello senza JavaScript, su/giù con JavaScript, stringhe in inglese e italiano, A5, nessuna tabella nuova.
- **Tre difetti:**
  - D1: la view di un blocco dell'applicazione riceve anche i dati degli altri blocchi;
  - D2: una POST con array annidati dà un errore 500;
  - D3: il fuoco si perde quando una riga arriva in fondo.
- Tre ambiguità di specifica (A1–A3), da decidere.
- **La regressione è incompleta.** U4 DASH e UPG passano in development. Il resto è stato fermato dall'host per memoria insufficiente (vedi "Regressione").

Ho letto solo:
- `CLAUDE.md`, `docs/SPEC.md` (U4, U5, A5), `docs/BRIEF-v1.1.md`, `_AI-LOG.md`;
- i contratti pubblici: `src/Dashboard/Block.php`, `$dashboardBlocks` in `src/Config/RoleWarden.php`, `documentation/api.html` e `configuration.html`, `CHANGELOG.md`, `docs/design-system/components/Dashboard/README.md`;
- il diff dei file di lingua, solo per le traduzioni;
- i miei strumenti di `tests/Integration/`.

Non ho aperto controller, model, view, `Layout.php`, `Dashboard.php`, `panel.js`, i sorgenti CSS, le migrazioni né i test unitari. Il CSS l'ho letto solo come lo serve il server.

| Suite | development | production |
| --- | --- | --- |
| U5 CUST | **119/124** (FAIL: D1, D2 ×3, D3) | **119/124**, uguale |
| U4 DASH (regressione) | **258/258** | non eseguita (host) |
| U4 UPG (regressione) | **29/29** | non eseguita (host) |
| U3 SRCH, U2, U1 (regressione) | non valide o non eseguite (host) | non eseguite (host) |

## Come

- **App di prova:** copia usa e getta di `../rolewarden-app-test` nella cartella di lavoro dello scratchpad, col modulo da `git archive 09b9159`. Server `php -S` sulla 8071, log a soglia 9. Solo `rolewarden_test` (127.0.0.1:3317) e Mailpit; credenziali solo come variabili di processo.
- **Snapshot:** ogni esecuzione fa lo snapshot all'inizio e alla fine ripristina e confronta l'hash. È sempre `f7f37c04…e21e64`, identico (`verify-u5.environment.txt`).
- **Blocchi dell'applicazione**, solo nella copia dell'app e rimossi alla fine:
  - una migrazione come in api.html con `u5orders.view` e `u5stock.view`;
  - `app/Config/RoleWarden.php` che estende la config del modulo;
  - due blocchi validi: `u5-orders` con `data()`, e `u5stock2` senza `data()` e con un titolo ostile `U5 Stock <b>block</b> & co`;
  - nove registrazioni non valide: classe inesistente, classe che non estende `Block`, chiave doppia, `figures`, `security`, `Bad_Key!`, `U5Upper`, chiave vuota, `u5 space`.
- **Account:**
  - super admin e admin;
  - `u5-viewer` (figures, roles, changes, security, `u5orders.view`) per v1, v2 e v3;
  - w, come v1 ma con override negativo su `u5orders.view`;
  - g, con `u5-global` (figures, signins, settings.view, settings.update);
  - un utente senza `dashboard.*`.
- **Script:** `verify-u5.all.sh`, `verify-u5.suite.py`, `verify-u5.harness.php`, fasi `verify-u5.phase-cust.php` e `verify-u5.phase-x.php` (sonda), aiuti `verify-u5.blocks.php`. Uscite `verify-u5.console.*` e `verify-u5.s1.*`.

## Cosa passa (in tutti e due gli ambienti)

1. **Indipendenza (ME, GLB, RST):**
   - v1 salva per sé ordine, un blocco nascosto e 7 giorni; v2, che ha lo stesso ruolo, e il super admin restano come prima.
   - La riga sta in Settings, chiave `dashboardLayout`, contesto `user:{id}`.
   - Il predefinito globale (contesto NULL) vale per chi non ha scelto (v2, super admin, g, admin), non per v1.
   - Un blocco nascosto dal predefinito resta nel pannello, deselezionato e riattivabile.
   - "Torna al predefinito" toglie la riga e riporta v1 al globale.
   - `?period=90` e `?period=30` vincono sui 7 salvati, anche nei dati del blocco.
   - I toast sono quelli dei file di lingua.
2. **Predefinito globale (GLB, FORGE, CSRF):**
   - Il pulsante globale c'è solo per chi ha `settings.update`.
   - Un salvataggio globale scrive una voce `settings.updated` con autore admin; "Salva per me" non ne scrive.
   - Una POST forgiata con `scope=global` da v1 e da w risponde 303 senza cambiare nulla (globale, riga propria, log).
   - Senza CSRF la POST è rifiutata e non cambia nulla.
   - L'anonimo viene mandato al login.
   - Una GET sull'indirizzo di salvataggio non è una pagina.
3. **Blocchi dell'applicazione (DEF, APP, ADD, BAD):**
   - Un blocco compare solo con il suo permesso; con l'override negativo w non lo vede, né nella pagina né nel pannello.
   - In assenza di scelte, i blocchi registrati vengono dopo i cinque, nell'ordine di registrazione, e si riordinano come gli altri.
   - Ogni blocco sta in una sezione del modulo con lettera e `h2` uguale a `title()`, escapato. Le lettere vanno da A a F senza buchi.
   - La view riceve `data()`, `$userId` e `$period` (days, from alle 00:00 di N−1 giorni fa, to adesso, prevFrom, N date), anche il periodo di `?period=`.
   - Un blocco registrato dopo un salvataggio compare in coda, selezionato.
   - Le nove registrazioni non valide vengono scartate, la pagina si apre (200) e ognuna è nel log per nome. Della chiave doppia vince la prima.
   - Si salva anche con blocchi non validi registrati, che non entrano nella riga.
4. **Avviso in cima (PIN):**
   - Con ordini forgiati che mettono l'avviso terzo, ultimo o lo omettono, e con un predefinito globale che lo mette in mezzo, l'avviso è sempre primo.
   - Si nasconde e torna. La sua riga nel pannello è fissa e senza su/giù.
5. **Solo i blocchi visibili (VIS, VIS2, HOS):**
   - g, che vede solo figures e signins, salva il globale: gli altri blocchi conservano stato e ordine relativo.
   - v2 e v3 salvano un ordine con in testa `u5stock2`, che non vedono, e v3 tenta di nasconderlo. Quando ottengono `u5stock.view`, il blocco compare in coda e visibile in tutti e due i casi.
   - Chiavi sconosciute, `<script>`, `../`, 400 chiavi inventate, NUL e byte non UTF-8, `scope` ostile o mancante, periodi 365/abc/−7/array, `order` come stringa: mai un 5xx (tranne D2), nulla di ostile salvato. Un periodo non ammesso fa rifiutare l'intero salvataggio.
6. **Senza e con JavaScript (NOJS, JS):**
   - Senza JS i su/giù stanno in un contenitore `x-cloak`, nascosto dal CSS servito. Il form così com'è salva mostra/nascondi e periodo.
   - Con Chrome headless: su e giù spostano di un posto, il primo spostabile non sale, l'ultimo non scende, la riga fissa non si muove.
   - Dopo uno spostamento in mezzo il fuoco resta sul pulsante, `:focus-visible`, con contorno.
   - "Salva per me" rende il nuovo ordine. Nessun errore in console.
7. **Lingue (LANG):**
   - 19 chiavi `customize` con le stesse chiavi in en e it, nessuna vuota o rimasta in inglese; i tre nomi di campo del log tradotti.
   - In italiano il pannello e i toast sono in italiano, senza testo inglese.
8. **A5:** con tre email bloccate più un IP alla soglia, l'avviso ha due righe: prima l'IP, poi una riga sola con tutte e tre le email.
9. **Schema (SCH):**
   - Nessuna tabella cambia durante la fase.
   - Le installazioni nuove di `29a5df6` (U4) e `09b9159` danno le stesse 16 tabelle con lo stesso `CREATE TABLE`.

## Difetti

**D1 — La view di un blocco dell'applicazione riceve i dati del modulo e degli altri blocchi** (APP-isol).

- **Riproduzione:** registrare due blocchi, il primo con `data()` che restituisce `['marker' => 'ORD-DATA', …]`, il secondo senza `data()`. Aprire la dashboard come super admin.
- **Cosa succede:** la view del secondo blocco vede `$marker = 'ORD-DATA'` e `$gotUser`. `array_keys(get_defined_vars())` nella view dà: `blocks, period, name, hour, security, figures, buckets, roles, changes, app, customize, global, greeting, range, flow, at, half, letter, block, data, marker, gotUser, gotDays, hostile, userId`. Lo stesso vale per il primo blocco.
- **Perché è un difetto:**
  - SPEC U5: «la view del blocco … riceve i dati di `data()` piu' `$period` e `$userId`».
  - Il docblock di `Block` e api.html dicono lo stesso.
  - Una view che usa `$total ?? 0` o `isset($x)` prende il valore di un altro blocco.
  - Le view dell'acquirente vedono anche i dati interni dei blocchi del modulo.
- **Uscite:** riga `[INFO] APP vars` in `verify-u5.console.cust.*.txt`.

**D2 — Errore 500 su una POST di layout con array annidati** (HOS «nested arrays», LOG-5xx, LOG-app).

- **Riproduzione:** come v2 autenticato, con un token CSRF valido, POST a `rolewarden/dashboard/layout` con `order[a][b]=figures&shown[x][]=roles&period=30&scope=me`.
- **Cosa succede:** risposta 500 in development e in production, e nel log `CRITICAL … ErrorException: Array to string conversion`, che il log attribuisce a `DashboardController.php` riga 100 (`strval`). Non si salva nulla e la dashboard poi si apre.
- **Perché è un difetto:** prompt punto 5, valori ostili nella POST. È un input del client che produce un'eccezione invece di un rifiuto. In production non si vede testo d'errore.

**D3 — Il fuoco si perde quando una riga arriva all'ultimo posto** (JS-edge).

- **Riproduzione:** pannello Personalizza con JavaScript, fuoco sul pulsante giù della penultima riga, attivarlo.
- **Cosa succede:** la riga diventa l'ultima, il suo pulsante giù diventa disabilitato e `document.activeElement` torna a `BODY`. Chi usa la tastiera ricomincia dall'inizio della pagina.
- **Perché è un difetto:** prompt punto 6, «Il fuoco resta visibile». Negli spostamenti in mezzo il fuoco resta (JS-focus PASS). Presumibilmente succede lo stesso salendo fino al primo posto spostabile, ma non l'ho provato.

## Ambiguità di specifica (da decidere, non decise qui)

- **A1 — `?period=` non valido con un periodo salvato.** Con 7 giorni salvati, `?period=365`, `abc` e `-7` aprono 30 giorni, il predefinito di serie; `?period=` vuoto apre i 7 salvati. La SPEC dice solo che `?period=` vince sul salvato. Non dice se un valore non ammesso debba cadere sul salvato o su 30.
- **A2 — Tutti i blocchi nascosti.** Togliendo tutte le caselle, la dashboard si apre senza nessun blocco: resta solo il pannello Personalizza, che permette di tornare indietro. La SPEC non dice se sia ammesso o se serva un messaggio di dashboard vuota. Il README dice «empty blocks say what will appear», ma parla dei singoli blocchi.
- **A3 — Ordine globale e blocchi che chi salva non vede.** Quando g salva il globale con `[signins, figures]`, dove vadano i blocchi che g non vede non è definito. Ho osservato un ordine sensato: l'ordine relativo degli altri resta (security, signins, figures, roles, changes, …). Ho verificato solo l'ordine relativo, non una regola.

**Osservazioni, non bloccanti:**

- Il rifiuto della POST globale forgiata va con 303 a `/index.php`, la home, non alla dashboard. Era annunciato nel log («segue il filtro `can:`»).
- Un utente senza `dashboard.*` che fa la POST riceve 403.
- Il warning di PHP `max_input_vars` di un primo giro veniva dalla mia POST da 1200 campi. Il limite è di PHP, non del modulo, e i giri contati usano 800 campi.

## Regressione

- **U4 DASH 258/258 e UPG 29/29 in development**, sul database ripristinato con hash identico. Entrambe girano con il pannello Personalizza presente, senza adattamenti.
- **U3 SRCH development: non valida.**
  - Durante la corsa l'host ha esaurito la memoria; il comando in background è stato fermato da Claude Code.
  - I processi spark uscivano con `rc=-1073741502`, come nella U4 (6 volte). I 6 FAIL di RE-* e ID-* sono quindi da scartare: `*.interrupted-memory.*`.
  - Anche il ripristino finale dello snapshot è fallito (exit 3221225794). L'ho rifatto a mano dallo snapshot della corsa: hash identico, riga in `verify-u5.environment.txt`. La cartella di lavoro è stata rimossa, `.env` per primo.
- **U2 e U1 development, e tutto production tranne CUST: non eseguiti.** Il driver orfano è ripartito sul database non ripristinato, ma ogni passo si è fermato prima di iniziare, perché lo snapshot non riusciva (exit 4). Il database non è stato toccato. Le uscite sono `*.aborted-no-snapshot.txt`.
- **Da rilanciare, su richiesta, quando c'è memoria.** Uno alla volta: `bash verify-u5.all.sh u3.development`, poi `u2.development`, `u1.development`, `dash.production`, `upg.production`, `u3.production`, `u2.production`, `u1.production`, con `RW_U5_RUN` diverso a ogni giro.

## Riverifica su 89971ed

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `89971ed` (correzione di D1–D3). Esito: PASS. D1, D2 e D3 sono chiusi. U5 è approvata per quanto riguarda i difetti; A1–A3 restano aperte e vanno decise.**

Ho seguito le stesse regole di lettura del primo giro. Del commit conosco solo il `--stat`.

Driver: `verify-u5.all.sh` con `RW_V8_NEW=89971ed` e `RW_U5_RUN=-v2`. Ho eseguito una suite alla volta, attendendo ogni volta che i suoi processi fossero chiusi (`scratchpad/u5step.sh`). Ordine: CUST, poi U4 DASH, U4 UPG, U3, U2, U1, ciascuna prima in development e poi in production. La memoria libera è rimasta tra 2,8 e 3,2 GB, e nessuna uscita ha `rc=-1073741502`.

| Suite | development | production |
| --- | --- | --- |
| U5 CUST | **125/125** | **125/125** |
| U4 DASH | **258/258** | **259/259** (in più SQL-prod) |
| U4 UPG | **29/29** | **29/29** |
| U3 SRCH (copia adattata U4) | **41/41** | **41/41** |
| U2 CHK, ST | **129/129** | **129/129** |
| U1 NAV…ESC (copie adattate U4) | **226/226** | **226/226** |

**Totale:** 1616/1616.

- **D1 chiuso (APP-isol).** La view di Orders vede solo `marker, gotUser, gotDays, hostile, period, userId`, cioè `data()` più `$period` e `$userId`. La view di Stock, che non ha `data()`, vede solo `period, userId`.
- **D2 chiuso (HOS «nested arrays», LOG-5xx, LOG-app).** La POST con `order[a][b]=…` riceve 303 e non salva nulla. Nessuna risposta ≥ 500, e nel log non c'è più `Array to string conversion`.
- **D3 chiuso (JS-edge).** Quando una riga arriva all'ultimo posto, il fuoco resta su un pulsante visibile del pannello, con `:focus-visible`. **Controllo nuovo, JS-edge-up:** lo stesso vale salendo al primo posto spostabile. Rispetto al primo giro CUST ha 125 controlli invece di 124, e il controllo in più è questo.
- **Nessuna regressione:**
  - tutti gli altri controlli CUST passano come nel primo giro;
  - U4 DASH e UPG, U3, U2 e U1 passano in tutti e due gli ambienti;
  - la regressione interrotta nel primo giro è ora completa.
- **Ambiguità (non decise):** A1–A3 restano aperte, come descritte sopra. Il commit non le tocca. Le ho osservate tutte e tre anche su `89971ed`:
  - un `?period=` non valido con 7 salvati apre 30;
  - con tutti i blocchi nascosti resta solo il pannello;
  - l'ordine relativo dei blocchi che chi salva non vede resta.
  Non ne sono emerse di nuove.
- **Ambiente:**
  - database identico allo snapshot `f7f37c04…e21e64` prima e dopo ogni esecuzione (`verify-u5.environment.txt`), Mailpit vuoto, nessun processo residuo;
  - le cartelle di lavoro sono state rimosse; l'ultima, `u5r-u2-development-v2`, era rimasta vuota e l'ho cancellata a mano.

## Riverifica su cf007ae (A1-A3)

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `cf007ae` (decisioni dell'utente su A1–A3, SPEC U5, voce *Risoluzione*). Esito: PASS. U5 è approvata. A1–A3 sono chiuse come decise; resta una nuova ambiguità minore, A4, non bloccante.**

Ho seguito le stesse regole di lettura dei giri precedenti. In più ho letto solo la voce *Risoluzione* della SPEC e la nuova stringa `customize.empty` dei file di lingua, solo per la traduzione. Del commit conosco solo il `--stat`.

Ho eseguito una suite alla volta, attendendo ogni volta che i suoi processi fossero chiusi (`RW_U5_RUN=-v3`).

| Suite | development | production |
| --- | --- | --- |
| U5 CUST (adattata ad A1–A3) | **142/142** | **142/142** |
| U4 DASH (regressione della dashboard) | **258/258** | **259/259** (in più SQL-prod) |

**Totale:** 801/801.

### Controlli adattati e nuovi in `verify-u5.phase-cust.php`

Rispetto ai 125 controlli del giro su 89971ed, ce ne sono 4 più restrittivi e 17 nuovi.

- **A1, periodo non offerto → periodo salvato:**
  - ME-qh-* (adattati, 4 più 4 nuovi): con 7 giorni salvati, `?period=` uguale a `365`, `abc`, `-7`, vuoto, `0`, `14`, `1e1` o NUL apre 7 giorni, nella pagina e nei dati del blocco.
  - ME-qh-arr (nuovo): `?period[]=30` apre 7.
  - ME-qh-none (nuovo): senza nessun periodo salvato vale il 30 di serie.
  - GLB-qh (nuovo): chi segue il globale di 90 giorni apre 90 con `?period=365`, e `?period=7` vince ancora.
  - Su 09b9159 lo stesso caso dava 30, quindi i controlli distinguono il comportamento nuovo.
- **A2, tutti i blocchi nascosti:**
  - A2-en: nessun blocco reso e il testo «Every block is hidden. Open Customize to show them again.».
  - A2-place: il messaggio sta nella dashboard, fuori dal pannello Personalizza.
  - A2-not: il messaggio manca quando almeno un blocco è visibile.
  - A2-it: in italiano «Tutti i blocchi sono nascosti. Apri Personalizza per mostrarli di nuovo.», senza il testo inglese.
- **A3, globale e blocchi che chi salva non vede.** L'ordine completo del globale si legge dal pannello del super admin, che non ha una scelta propria.
  - A3-1 e A3-2: dopo due salvataggi globali di g, che vede solo figures e signins, i blocchi che g non vede hanno lo stesso ordine relativo e lo stesso stato.
  - A3-1g: i due blocchi che g vede seguono l'ordine e lo stato di g.
  - A3-3, A3-3v, A3-3g: admin nasconde `roles` nel globale; quando g salva, `roles`, che g non vede, resta nascosto e al suo posto tra gli altri, e v2 non lo riceve.
- Tutti gli altri controlli di CUST passano come nel giro su 89971ed, compresi D1–D3.

### Difetti

Nessuno.

### Ambiguità nuove (da decidere, non decise qui)

- **A4 — "Posizione relativa" rispetto a chi?** Il globale prima del salvataggio di g era `security, roles, figures, signins, changes, u5-orders, u5stock2`; dopo è `security, signins, figures, roles, changes, u5-orders, u5stock2`.
  - L'ordine relativo dei blocchi che g non vede resta: security, roles, changes, … È quello che ho verificato (A3-1).
  - Ma `roles` è passato da prima di figures a dopo i blocchi di g: i blocchi di g sono stati messi subito dopo `security`, invece di occupare i posti che figures e signins avevano.
  - La SPEC dice «tengono la loro posizione relativa nell'ordine». Non è chiaro se intenda l'ordine relativo tra loro (rispettato) o anche la posizione rispetto ai blocchi di chi salva (non rispettata).
  - Non è bloccante.

### Ambiente

- Database identico allo snapshot `f7f37c04…e21e64` prima e dopo ogni esecuzione (`verify-u5.environment.txt`), Mailpit vuoto.
- Nessun processo residuo, cartelle di lavoro rimosse.
- Memoria libera tra 2,3 e 2,8 GB, nessuna interruzione.

## Riverifica su 2fb290d (A4)

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `2fb290d` (A4 decisa dall'utente, SPEC U5, voce *Risoluzione*). Esito: PASS. U5 è approvata. A4 è chiusa come decisa.**

Ho seguito le stesse regole di lettura dei giri precedenti. In più ho letto solo la voce *Risoluzione* della SPEC. Del commit conosco solo il `--stat`.

Ho eseguito una suite alla volta, attendendo ogni volta che i suoi processi fossero chiusi (`RW_U5_RUN=-v4`).

| Suite | development | production |
| --- | --- | --- |
| U5 CUST (adattata ad A4) | **151/151** | **151/151** |
| U4 DASH (regressione della dashboard) | **258/258** | **259/259** (in più SQL-prod) |

**Totale:** 819/819.

### Regola verificata

Salvando, i blocchi che chi salva non vede restano nei loro posti e nel loro stato; i blocchi di chi salva si riordinano solo tra i posti che occupavano. Vale sia per "Salva per me" sia per il globale.

L'atteso lo calcolo nella fase (`a4_expect`) a partire dalla riga di Settings letta con una query diretta, prima del salvataggio. Poi lo confronto con la riga scritta dopo: ordine esatto e insieme dei blocchi nascosti.

### Controlli nuovi in `verify-u5.phase-cust.php` (9)

- **Globale salvato da g**, che vede solo figures e signins (ripete il caso di g):
  - **A4-g1:** prima `security, roles, figures, signins, changes, u5-orders, u5stock2`, con g che salva `[signins, figures]`. Dopo: `security, roles, signins, figures, changes, u5-orders, u5stock2`, cioè `roles` resta al secondo posto e i due blocchi di g si scambiano solo tra loro. Su `cf007ae` l'ordine era `security, signins, figures, roles, …`, quindi il controllo distingue il comportamento nuovo.
  - **A4-g1r:** il super admin senza scelta propria rende quell'ordine.
  - **A4-g2:** g nasconde figures salvando lo stesso ordine: nulla si sposta, cambia solo lo stato di figures.
  - **A4-g3:** admin mette `roles`, che g non vede, tra i blocchi di g e lo nasconde (`signins, roles, figures`). Quando g salva `[figures, signins]`, `roles` resta in mezzo e nascosto: `security, figures, roles, signins, …`.
- **"Salva per me"** di v4, che non vede signins né u5stock2, partendo da un globale salvato da un secondo super admin: `security, figures, u5stock2, signins, roles, changes, u5-orders`, con signins nascosto.
  - **A4-base:** il globale è salvato così com'è; l'avviso fisso viene messo in testa.
  - **A4-me:** v4 salva `[security, u5-orders, changes, roles, figures]` e nasconde changes. Nella sua riga `user:{id}` c'è `security, u5-orders, u5stock2, signins, changes, roles, figures`, con nascosti signins (conservato) e changes.
  - **A4-me-glob:** il globale non cambia.
  - **A4-me-r:** v4 rende `security, u5-orders, roles, figures`.
  - **A4-me-stock:** quando v4 ottiene `u5stock.view`, Stock compare al posto che aveva conservato, `security, u5-orders, u5stock2, roles, figures`, e non in coda.

### Controlli adattati

- **LANG-it:** confronta l'etichetta italiana di ogni blocco del modulo per chiave e non più per posizione. Con il nuovo globale di A4 nel pannello Stock sta tra i blocchi del modulo, quindi la posizione non vale più.
- Il vecchio confronto «ordine relativo» di A3 (A3-1, A3-2, A3-3) resta e passa: è implicato dalla regola A4.
- Tutti gli altri controlli passano come su `cf007ae`.

### Difetti

Nessuno.

### Ambiguità

Nessuna nuova.

**Osservazione, non bloccante:** quando un super admin salva un ordine con l'avviso di sicurezza non in testa, la riga lo registra in testa. È coerente con l'avviso fisso («sta sempre in cima»).

### Ambiente

- Database identico allo snapshot `f7f37c04…e21e64` prima e dopo ogni esecuzione (`verify-u5.environment.txt`), Mailpit vuoto.
- Nessun processo residuo, cartelle di lavoro rimosse.
- Memoria libera tra 1,2 e 3,1 GB, nessuna interruzione.

## Blocco della demo su 0c136e6

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `0c136e6` (blocco d'esempio "Demo documents"). Esito: FAIL. Un difetto, D1: un caso limite che fa cadere tutta la dashboard. Due ambiguità da decidere, A1 e A2.**

Ho letto solo:
- `demo/README.md`, sezioni "Install", "Dashboard block" e "Remove";
- `documentation/api.html#dashboard-blocks`;
- `CHANGELOG.md`.

Non ho aperto `demo/src` né il codice del modulo. Del commit conosco solo il `--stat`.

| Suite | development | production |
| --- | --- | --- |
| U5 DEMO (`verify-u5.phase-demo.php`) | **50/52** (FAIL: D1 ×2) | **50/52**, uguale |
| U5 CUST (regressione, adattata) | **152/152** | non richiesta |

### Come

- **App di prova:** la copia usa e getta di `../rolewarden-app-test`. È già collegata come dice il README: `'RoleWardenDemo' => …/demo/src` in `$psr4` e `DemoRoutes::register($routes)` dopo la riga del modulo. Nessun'altra riga, nessun `app/Config/RoleWarden.php`, nessun Registrar dell'app (PRE-wire). Il blocco non è registrato a mano.
- **Stato "prima di demo:install":** l'harness installa la demo all'avvio. La fase la toglie con `demo:uninstall`, che cancella anche il file `writable/rolewarden/demo-installed`, e verifica che non restino né la tabella né i `documents.*`.
- **Uscite:** `verify-u5.console.demo.{development,production}.txt` e `verify-u5.s1.demo.0c136e6.*`. Il nome della fase segue l'harness (`verify-u5.phase-demo.php`).

### Cosa passa (in tutti e due gli ambienti)

- **Prima di `demo:install`:**
  - il super admin apre la dashboard: 200, nessun errore, nessuna riga WARNING o ERROR nel log;
  - il blocco compare al super admin con «Run php spark demo:install to fill this block.», senza cifre e senza query;
  - admin non lo vede.
- **Dopo `demo:install`:**
  - **Chi lo vede:** Reader, Author, Approver e super admin; admin, `user` e un utente con solo `dashboard.figures` no. Reader, che non ha nessun `dashboard.*`, apre la dashboard con il solo blocco della demo.
  - **Sezione:** è una sezione del modulo con lettera e titolo "Demo documents", e ha la sua riga nel pannello Personalizza.
- **Numeri.** I documenti seme li ho portati a 0, 20, 60 e 100 giorni di età (`created_at`).
  - Per stato: Draft, Submitted e Approved sono uguali a `GROUP BY status`, in 7, 30 e 90 giorni.
  - Nel periodo: i «documents written in the last N days» sono uguali ai `created_at` nel periodo della SPEC (1, 2, 3).
  - Dopo che l'author crea un documento e l'approver ne approva uno dalla demo: per stato `{draft 2, submitted 0, approved 3}`, nel periodo 2, 3, 4. Il documento approvato, di 20 giorni, non entra nei 7 giorni: «scritto» vuol dire creato.
- **Personalizza:** il blocco si porta in testa (subito dopo l'avviso), si nasconde restando nel pannello deselezionato e torna con "Torna al predefinito", in coda dopo i blocchi del modulo.
- **Lingue:** in inglese nessuna chiave grezza. In italiano «Documenti della demo», «Bozza / Inviato / Approvato», «N documenti scritti negli ultimi 30 giorni», «Apri i documenti», con gli stessi numeri e senza parole inglesi; la riga del pannello ha il titolo italiano.
- **Escape:** una riga con titolo `<script>…` e stato `<b>x</b>&"` scritta direttamente in tabella non esce mai senza escape, né nel blocco né nella pagina.
- **Dopo `demo:uninstall`:**
  - la dashboard si apre (200), senza righe di errore nel log;
  - admin non vede il blocco;
  - **il super admin lo vede ancora**: «Run php spark demo:install to fill this block.», senza cifre, con la sua riga nel pannello. Resta finché la cartella e le due righe del README restano collegate.
- **Nessuna risposta ≥ 500** e nessun warning PHP in tutta la fase, tranne il caso di D1.

### Difetti

**D1 — Demo segnata come installata ma senza la tabella `demo_documents`: tutta la dashboard dà 500** (ROB-1, ROB-2).

- **Riproduzione:** `php spark demo:install`, poi `DROP TABLE demo_documents` (per esempio un database ripristinato senza la tabella, mentre resta `writable/rolewarden/demo-installed`), poi aprire `/rolewarden` come super admin.
- **Cosa succede:** risposta 500 in development e in production. Nel log `ERROR … Table 'rolewarden_test.demo_documents' doesn't exist` e `CRITICAL … DatabaseException`.
- **Come l'ho trovato:** al primo giro, ricreando il database con il file della demo ancora presente, la dashboard dava 500 con lo stesso errore.
- **Perché è un difetto:**
  - prompt punto 1: «Il blocco, se compare, non deve interrogare una tabella che non esiste»;
  - un blocco d'esempio che fa cadere la pagina d'ingresso del pannello, per tutti quelli che lo vedono, non è un modello da copiare;
  - `demo:uninstall` in quello stato funziona (exit 0).

### Ambiguità (da decidere, non decise qui)

- **A1 — Eccezione in un blocco dell'applicazione.** La SPEC e api.html dicono che una classe mancante, che non estende `Block` o con una chiave non valida viene scartata e scritta nel log, «and the dashboard still opens». Non dicono cosa succede se `data()` o la view di un blocco valido lancia un'eccezione. Oggi la dashboard intera dà 500 (vedi D1). Va deciso se il modulo debba scartare quel blocco e scriverlo nel log come per le classi non valide, o se tocchi al blocco proteggersi.
- **A2 — Il blocco della demo per il super admin senza demo installata.** Prima di `demo:install` e dopo `demo:uninstall` il super admin vede "Demo documents" con l'invito a lanciare `demo:install`. Gli altri utenti non lo vedono, perché `documents.view` non esiste. Il README dice che `demo:uninstall` «removes everything the demo added». Il blocco resta finché non si tolgono la cartella e le due righe, come il README chiede subito dopo. Non è chiaro se il blocco debba sparire già a demo disinstallata.

**Osservazioni, non bloccanti:**

- Uno stato fuori dai tre della demo, scritto direttamente in tabella, compare con l'etichetta grezza `Demo.statuses.<valore>`, escapata. Non succede usando la demo.
- Nella pagina `/demo` il super admin legge «Your document permissions 0». È fuori dal blocco e non l'ho approfondito.

### Regressione U5 CUST (development)

- **Senza adattamento:** 138/151 (`verify-u5.console.cust.development.0c136e6.unadapted.txt`). I 13 FAIL vengono tutti dal blocco "Demo documents", che ora entra negli elenchi esatti dei blocchi del super admin (DEF, ME-5, GLB-own, PIN-me, A4-base, A4-me-stock, ADD). Sono un adattamento del test, non regressioni.
- **Adattamento** (`SET-nodemo`): all'inizio della fase la demo viene disinstallata e scollegata dalla copia dell'app (le due righe del README). Lo schema di partenza si rilegge dopo, senza la tabella della demo.
- **Esito:** 152/152.

### Ambiente

- Database identico allo snapshot `f7f37c04…e21e64` prima e dopo ogni esecuzione (`verify-u5.environment.txt`), Mailpit vuoto.
- L'app di prova `../rolewarden-app-test` non è stata toccata: ogni esecuzione lavora su una copia nello scratchpad, poi rimossa.
- Nessun processo residuo.

## Riverifica su db68a26 (blocco demo)

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `db68a26`: D1 corretto in `b2c23a1`, A1 e A2 decise dall'utente. Esito: PASS. D1 è chiuso, A1 e A2 sono chiuse come decise. Il blocco della demo è approvato.**

Ho seguito le stesse regole di lettura del giro su `0c136e6`. In più ho letto solo:
- la voce *API dei blocchi* della SPEC e `documentation/api.html#dashboard-blocks` per A1;
- `demo/README.md`, sezione "Dashboard block", per A2;
- la nuova stringa `failed` dei file di lingua, solo per la traduzione.

Non ho aperto `demo/src` né il codice del modulo.

Ho eseguito una suite alla volta, attendendo ogni volta che i suoi processi fossero chiusi (`RW_U5_RUN=-v6`).

| Suite | development | production |
| --- | --- | --- |
| U5 DEMO (con A1 e A2) | **65/65** | **65/65** |
| U5 CUST (regressione, adattata) | **152/152** | **152/152** |
| U4 DASH (regressione) | **258/258** | non richiesta |

**Totale:** 692/692.

- **D1 chiuso (ROB-1, ROB-2).** La demo risulta installata ma `demo_documents` non c'è: la dashboard risponde 200, senza testo SQL e senza nessuna riga d'errore sulla tabella nel log. Succede lo stesso in development e in production.
- **A2 (PRE-A2, UNI-A2, nuovi).** Prima di `demo:install` e dopo `demo:uninstall`, con la demo collegata, il super admin vede il blocco con l'invito a eseguire `demo:install`, come scrive il README. Admin non lo vede.
- **A1, sezione nuova THROW.** Nella copia dell'app ho registrato tre blocchi, tutti con `dashboard.figures`, e alla fine li ho rimossi (THR-clean):
  - `u5-throw-data`, che lancia in `data()`;
  - `u5-throw-view`, che scrive `<div class="u5-half"><table><tr><td>HALF-OUTPUT-MARK` e poi lancia;
  - `u5-after-ok`, valido, dopo di loro.

  Con la demo installata:
  - **THR-200:** 200 in development e in production, senza testo d'errore.
  - **THR-data, THR-view:** ogni blocco che lancia tiene sezione, lettera e titolo, e dice «This block could not be loaded. The error is in the application log.».
  - **THR-it:** in italiano dice «Non è stato possibile caricare questo blocco. L'errore è nel log dell'applicazione.», senza il testo inglese.
  - **THR-log, THR-log2:** nel log c'è un `ERROR … dashboard block "u5-throw-data" failed: U5-THROW-DATA` e uno per `u5-throw-view`, e si ripetono a ogni pagina.
  - **THR-half:** l'output scritto a metà dalla view non compare nella pagina.
  - **THR-markup:** `main` è chiuso e `section`, `div`, `table`, `details`, `form`, `ul` e `ol` sono bilanciati.
  - **THR-after, THR-others:** il blocco successivo si vede intero, e così i blocchi del modulo e quello della demo, con le sue cifre.
- **Tutti gli altri controlli DEMO** passano come sul giro precedente. Uno stato sconosciuto scritto in tabella ora non compare più: con `b2c23a1` gli stati non previsti vengono esclusi, e l'osservazione sulla chiave grezza `Demo.statuses.*` non vale più.
- **Regressione:** CUST adattata (`SET-nodemo`) e DASH passano. DASH gira con la demo collegata e installata, e il blocco della demo non la disturba.

### Difetti

Nessuno.

### Ambiguità

Nessuna nuova.

### Ambiente

- Database identico allo snapshot `f7f37c04…e21e64` prima e dopo ogni esecuzione (`verify-u5.environment.txt`), Mailpit vuoto.
- `../rolewarden-app-test` non è stata toccata. Nessun processo residuo.

