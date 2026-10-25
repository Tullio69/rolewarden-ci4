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

