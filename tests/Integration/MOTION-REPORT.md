# Movimento del pannello (1.1): collaudo indipendente

**Collaudatore ad Hoc (Claude), 2026-10-25, commit `b38e350` (main).**

## Esito

**FAIL. Il movimento non è approvato.**

- Il pannello funziona: dialog, ricerca comandi, matrice, override, Personalizza, toast, navigazione, rail e pagine senza JavaScript fanno quello che facevano, e lo stato salvato nel database è giusto.
- **Cinque difetti:**
  - D1: il passaggio tra pagine non avviene mai e ogni navigazione lascia un errore JavaScript in console;
  - D2: l'evidenziazione della voce attiva copre il testo della barra mentre scorre;
  - D3: le cifre della dashboard mostrano valori negativi all'inizio del conteggio;
  - D4: con il movimento ridotto il pannello Personalizza si muove ancora;
  - D5: l'evidenziazione compare sulla voce nuova, poi torna sulla vecchia e scorre (osservato con screenshot CDP, vedi sotto).
- Un'ambiguità di specifica (A1), non decisa.
- La regressione passa, tranne un controllo di U5 CUST che cade per D1.

Ho letto solo:
- `CLAUDE.md` (Collaudo di una tappa), `_AI-LOG.md`, il prompt del collaudo;
- `docs/design-system/README.md` (Motion), `components/Toast/README.md`, `components/UserStatus/README.md`, `components/CommandSearch/README.md`;
- `CHANGELOG.md` (Motion sotto Changed);
- i miei strumenti di `tests/Integration/`.

Non ho aperto `resources/css`, `src/Assets`, le view, i controller né i test unitari. Il comportamento l'ho osservato dal DOM, da `document.getAnimations()`, dagli eventi `animationstart`/`transitionstart`, dagli stili calcolati e con eventi reali di puntatore e tastiera. Del commit conosco solo il `--stat`.

| Suite | development | production |
| --- | --- | --- |
| MOTION (`verify-motion.phase-motion.php`) | **123/131** (FAIL: D1 ×2, D3 ×2, D4 e A1 ×4) | **120/128** (uguale; i 3 controlli dei fotogrammi girano solo in development) |
| U5 CUST (regressione) | **151/152** (FAIL: JS-console, è D1) | non richiesta |
| U4 DASH (regressione) | **258/258** | non richiesta |
| U3 SRCH (regressione) | **41/41** | non richiesta |
| U1 NAV, RAIL, STYLE, ESC (regressione, STYLE adattata) | **196/196** | non richiesta |

## Come

- **App di prova:** la copia usa e getta di `../rolewarden-app-test` nella cartella di lavoro dello scratchpad, col modulo da `git archive b38e350`. Server `php -S` sulla 8071, solo `rolewarden_test` (127.0.0.1:3317) e Mailpit, credenziali solo come variabili di processo.
- **Snapshot:** ogni esecuzione fa lo snapshot all'inizio, alla fine ripristina e confronta l'hash. È sempre `f7f37c04…e21e64`, identico, in tutte le 19 esecuzioni (`verify-motion.environment.txt`).
- **Browser:** Chrome 155 headless via CDP (`verify-motion.browser.mjs`, copia del driver U3). Le operazioni nuove sono:
  - `nojs`: JavaScript spento;
  - `mouse`: puntatore reale al centro di un elemento;
  - `frames`, `shots`, `rate`: fotogrammi a tempi fissi, anche con le animazioni rallentate 10 volte;
  - `initscript`: registra `pagereveal`, ogni animazione e transizione che parte e ogni valore scritto nelle cifre;
  - `block`: guasto di rete simulato, per il toast d'errore.
- **Dati:** 1.234 utenti, 12.345 accessi riusciti e 2.345 falliti da un solo IP (avviso di sicurezza), 1.111 modifiche ai permessi. Così le cifre hanno quattro o cinque cifre e il separatore cambia tra en e it. In più un ruolo `mo-editor`, un utente con due override, un ruolo e un utente da eliminare.
- **Script:** `verify-motion.all.sh`, `verify-motion.suite.py`, `verify-motion.harness.php` (copia di quello U5, le uscite si chiamano `verify-motion.*`), `verify-motion.phase-motion.php`, `verify-motion.phase-style.php` (U1 STYLE adattata), `verify-motion.phase-x.php` (sonda). Uscite in `verify-motion.console.*`, `verify-motion.s1.*` e `verify-motion.frames.development.*.png`.

## Cosa passa (in tutti e due gli ambienti)

1. **Nulla si rompe (ERR):**
   - 12 pagine (dashboard, utenti, ruoli, matrice di un ruolo, dettaglio utente con override, permessi, Check a permission, attività, impostazioni, aspetto, profilo, ricerca), percorse con clic reali sulla barra;
   - nei 3 temi × 3 barre (Paper, Ink, Charcoal), più Personalizza, Ctrl K, un dialog e una cella sul primo giro;
   - nessun errore JavaScript **oltre a quello di D1**.
2. **Movimento ridotto (RM):**
   - su 12 pagine, sul toast e sulla ricerca non parte nessuna animazione che muova. Il registratore di animazioni, senza l'impostazione, ne vede in 15 punti su 16 (calibrazione);
   - le cifre hanno subito il valore finale e non scrivono valori intermedi;
   - il dialog e la ricerca sono aperti, opachi e senza scala 30 ms dopo il clic;
   - nessun passaggio tra pagine.
   - Fanno eccezione D4 e A1.
3. **Cifre (CNT):**
   - a fine conteggio il testo di ogni cifra è identico a quello della pagina senza JavaScript e a quello servito;
   - in en `1,242 · 12,363 · 2,345 · 1,113`, in it `1.242 · 12.364 · 2.345 · 1.113`.
   - Fa eccezione D3, durante il conteggio.
4. **Dialog (DLG)**, per l'eliminazione di un ruolo, l'eliminazione di un utente e il ripristino del tema:
   - **Col mouse:** il clic lo apre; dopo il movimento è aperto del tutto e la pagina sotto non si è mossa. Cancel lo chiude e il fuoco torna al pulsante.
   - **Con la tastiera:** Invio sul pulsante lo apre, Esc lo chiude e il fuoco torna al pulsante.
   - **Le azioni confermate funzionano:**
     - il ruolo viene eliminato col mouse, e un toast di successo lo dice;
     - l'utente viene eliminato con la tastiera (Tab fino a Delete, poi Invio);
     - il tema Clarity/Ink salvato sparisce.
5. **Ricerca (SRCH):**
   - Ctrl K la apre, cresciuta del tutto, con il fuoco nel campo;
   - Giù e Su muovono `aria-activedescendant` e `aria-selected`, Invio apre il risultato evidenziato;
   - il link della barra la apre col mouse e con Invio; Esc la chiude e il fuoco torna al link.
6. **Matrice di un ruolo (MTX):**
   - un clic concede, e la barra conta 1 modifica non salvata; un secondo clic annulla;
   - Discard dopo due modifiche riporta tutto com'era;
   - tre clic rapidi sulla stessa cella finiscono su "concesso";
   - Save dà il toast di successo;
   - nel database c'è esattamente lo stato salvato, e dopo il ricaricamento ogni cella mostra il database;
   - dopo ogni passo nessuna cella resta a metà: nessuna animazione in corso, opacità 1, nessuna scala, `stroke-dashoffset` 0.
7. **Override di un utente (OVR):**
   - clic, Discard e Save funzionano e dopo il movimento le celle sono ferme;
   - nel database ci sono esattamente gli override che la matrice mostrava al momento di Save, e dopo il ricaricamento le celle li rispecchiano.
8. **Personalizza (CUST):**
   - Invio su "giù" sposta la riga di un posto, due volte; a fine scorrimento le righe sono in ordine anche a schermo, e il fuoco resta sul pulsante, visibile (`:focus-visible`, contorno 2px);
   - il clic su "su" sposta la riga;
   - "Salva per me" salva e rende l'ordine mostrato.
9. **Toast (TST):**
   - il successo se ne va da solo in 5,05–5,11 s;
   - col puntatore sopra resta per oltre 7,5 s, e se ne va quando il puntatore esce;
   - l'errore (salvataggio con rete bloccata) è ancora lì dopo 8 s, e la chiusura lo toglie;
   - con tre toast (successo, successo, errore) chiudere quello in mezzo lascia gli altri due nel loro ordine, impilati senza sovrapporsi;
   - a puntatore lontano il successo rimasto se ne va e l'errore resta.
10. **Senza JavaScript (NOJS):**
    - in 12 pagine nulla è trasparente, ritagliato o scalato a zero: righe, cifre, grafici e avviso «2,345 failed sign-ins from 203.0.113.7…» si vedono;
    - con JavaScript, 2,5 s dopo il caricamento, nulla di visibile è rimasto nascosto (JSEND).
    - I controlli disabilitati al 50% sono esclusi, come chiede il design system.
11. **Navigazione (NAV):**
    - per ognuna delle 8 voci, dopo il clic è corrente solo quella, l'evidenziazione sta dentro la voce, ferma, e nessun testo è coperto **a movimento finito**;
    - il rail si comprime, ci si naviga (voce corrente ed evidenziazione giuste) e si riespande.
12. **Log:** nessuna risposta ≥ 500, nessun testo SQL, nessuna riga WARNING/ERROR nel log dell'app, nessun warning PHP.

## Difetti

**D1 — Il passaggio tra pagine non avviene e ogni navigazione lascia un errore in console** (VT-err, NAV-vt; anche U5 CUST JS-console).

- **Riproduzione:**
  1. Chrome 155 headless (non l'ho provato in un Chrome con finestra; la calibrazione sotto esclude un limite dello strumento);
  2. accedere al pannello;
  3. cliccare una voce della barra.
- **Cosa succede:**
  - in console compare `Uncaught (in promise) InvalidStateError: Transition was aborted because of invalid state. ViewTransition opt-in disabled`, attribuito alla pagina nuova;
  - nella pagina vecchia `pageswap` ha una view transition, nella nuova `pagereveal` non ne ha;
  - succede su ogni navigazione dalla barra e dal form di login: 81 su 81 nei 9 giri ERR. Succede uguale in development e in production. Con `Page.navigate` (indirizzo scritto) non succede.
- **Calibrazione:** due pagine statiche mie con `@view-transition { navigation: auto; }`, servite su un'altra porta, fanno la transizione nello stesso Chrome headless (`pagereveal` con view transition, nessun errore). Lo strumento quindi vede le transizioni.
- **Perché è un difetto:**
  - il prompt (punto 1) dice «nessun errore JavaScript in console»;
  - il design system dice «Page to page (View Transitions): … the content leaves in 120ms and the new one rises …». In un browser che le supporta, il passaggio non c'è.
- **Regressione:** U5 CUST JS-console, che su `db68a26` passava, ora fallisce per questo errore (`verify-motion.console.cust.development.txt`).

**D2 — L'evidenziazione della voce attiva copre il testo della barra mentre scorre** (controllo visivo a fotogrammi; i controlli NAV a movimento finito passano).

- **Riproduzione:** da Users cliccare Settings, e catturare fotogrammi della barra durante lo scorrimento dell'evidenziazione.
- **Cosa succede:**
  - L'evidenziazione (`.rw-side-pill`) passa **sopra** il testo e le icone delle voci che attraversa: Permissions, l'etichetta Security, Activity, l'etichetta System. Poi copre il testo della voce d'arrivo, Settings, che sparisce finché lo scorrimento non finisce.
  - Lo si vede **a velocità reale**: `verify-motion.frames.development.ink-realtime.07.png` e `.08.png` (Ink).
  - E rallentato 10 volte nelle tre barre: `verify-motion.frames.development.{paper,ink,charcoal}.05–10.png`. In Ink e Charcoal il testo d'arrivo è invisibile; in Paper la pillola chiara copre Permissions, Security e Activity.
- **Perché è un difetto:** il prompt (punto 9) dice «il testo delle voci della barra non deve mai essere coperto».
- **Nota:** il controllo automatico `elementFromPoint` non lo rileva, perché la pillola non intercetta il puntatore. Il difetto si vede solo nei fotogrammi.

**D3 — Le cifre della dashboard mostrano valori negativi all'inizio del conteggio** (CNT-en-range, CNT-it-range).

- **Riproduzione:** aprire la dashboard tre volte, una dalla barra, e registrare con un `MutationObserver` ogni testo scritto negli elementi `[data-rw-count]`.
- **Cosa succede:**
  - in tutti e due gli ambienti e in tutte e due le lingue, il primo valore scritto è spesso negativo, per esempio `-1,309` per 12.349, `-2024` per 12.360 (it), `-254` per 2.345;
  - è successo in 6–12 casi su circa 465 valori per giro;
  - il valore finale è comunque giusto (CNT-en, CNT-it).
- **Perché è un difetto:**
  - il design system dice «key figures count up»;
  - una cifra di sicurezza che per un fotogramma mostra un numero negativo è un'informazione falsa.
- **Nota:** l'it a volte non ha il separatore delle migliaia nei valori intermedi (`-2024`). È solo durante il conteggio.

**D4 — Con il movimento ridotto il pannello Personalizza si muove ancora** (RM-dash).

- **Riproduzione:** impostare `prefers-reduced-motion: reduce` e aprire la dashboard.
- **Cosa succede:** sul `form.rw-dash-panel` parte una transizione di `transform` di 320 ms. È l'arrivo «from 6px above at 98%».
- **Perché è un difetto:** il design system dice «`prefers-reduced-motion: reduce`: no movement…». Restano ammessi solo i cambi di colore e la dissolvenza del fondale.

**D5 — L'evidenziazione compare prima sulla voce nuova, poi torna sulla vecchia e scorre** (fotogrammi a velocità reale, `ink-realtime`).

- **Riproduzione:** come D2, con fotogrammi ogni 40 ms.
- **Cosa succede:**
  - dal caricamento (circa 420 ms dopo il clic) fino a circa 1.220 ms l'evidenziazione è ferma su Settings, senza trasformazione;
  - poi salta indietro di 246 px, su Users, e scorre fino a Settings in circa 400 ms;
  - succede uguale in due esecuzioni;
  - chi guarda vede la voce nuova evidenziata, poi un salto indietro, poi lo scorrimento.
- **Perché è un difetto:** il design system dice «the sidebar's active highlight slides from the old item to the new one».
- **Riserva:** la misura è fatta con screenshot CDP, che durante il caricamento possono ritardare i fotogrammi. Va confermata a occhio in un Chrome normale. Il ritardo di circa 0,8 s potrebbe dipendere anche da D1.

## Ambiguità di specifica (da decidere, non decise qui)

- **A1 — La pressione al 97% conta come movimento?** (RM-cust, RM-dlg, RM-cell)
  - Con il movimento ridotto, premendo un controllo (il pulsante Customize, il pulsante Delete, una cella) parte una transizione di `transform` di 120 ms: «controls give way to 97% when pressed».
  - Il design system dice «reduce: no movement», ma elenca come esclusi espliciti solo cascade, disegno, conteggio, passaggio tra pagine e morph.
  - Non è chiaro se un rimpicciolimento del 3% al tocco sia «movement» da spegnere o un riscontro che può restare.
  - I quattro controlli falliscono solo per questo; se la pressione è ammessa, vanno chiusi come decisi.

**Osservazioni, non bloccanti:**

- **Toast di avviso:** nessun percorso del pannello lo produce via HTTP (era già un limite di V1M1). Ho verificato il comportamento a 5 s solo sul successo, e il resto su successo ed errore.
- **Cella del ruolo in sola lettura:** nella matrice di un ruolo, un permesso concesso dal ruolo ma negato a un membro con override mostra «Denied for user» e non si può modificare (`aria-pressed` assente). Non viene da questo commit, e per questo i controlli MTX usano altri permessi.

## Regressione (development)

- **U5 CUST 151/152:** l'unico FAIL è JS-console, cioè l'errore di D1 (due navigazioni).
- **U4 DASH 258/258.**
- **U3 SRCH 41/41.**
- **U1 NAV, RAIL, STYLE, ESC: 196/196 con STYLE adattata** (154/196 senza adattamento: `verify-motion.console.u1.development.r3-unadapted-style.txt`).
  - **Perché l'adattamento:** l'evidenziazione della voce attiva ora è un elemento proprio, `.rw-side-pill`, sotto il contenuto della voce. Lo dice il CHANGELOG: «the sidebar's highlight sliding». Lo stile calcolato del link non ha più lo sfondo, e i 42 FAIL venivano tutti da S-*-2/-4/-5: sfondo della voce attiva e contrasto.
  - **Cosa cambia:** `verify-motion.phase-style.php` legge i colori della voce attiva sopra lo sfondo della pillola. Il chip del contatore resta sul proprio sfondo.
  - **Contro il contratto:** passano i valori del design system (`abg`/`afg`, 4,5:1 e 3:1) in tutti i 18 casi tema/barra/modo. Uno screenshot (`verify-motion.u1.style-console-ink-light.png`) mostra la voce attiva come prima.
  - Il primo giro adattato (r4) leggeva il chip sopra la pillola e dava 12 FAIL; corretto in r5.
- **Correzioni dello strumento durante il collaudo:** prima del giro finale ho corretto i miei errori di misura. Nessuno di questi era un difetto del modulo:
  - clip `inset(0 0% 0 0)` contato come ritaglio;
  - scroll del puntatore contato come spostamento della pagina;
  - permesso in sola lettura usato in MTX;
  - toast misurato prima di diventare visibile;
  - riga del contatore accesso letta prima del login del browser.

## Ambiente

- Database identico allo snapshot `f7f37c04…e21e64` prima e dopo ogni esecuzione, Mailpit vuoto.
- Nessun processo residuo: i php, node e chrome presenti sono anteriori alla sessione.
- **Rimasti nello scratchpad** alcuni profili Chrome delle cartelle `mw-*`, che il sandbox non lascia cancellare (EPERM su file `LOCK`/`LOG` del profilo). Non contengono `.env` né credenziali: i `.env` sono stati cancellati per primi a ogni esecuzione. Vanno tolti a mano.
- `../rolewarden-app-test` non è stata toccata. Nessun commit.

## Riverifica su 933154b (con i giri su a790b3a)

**Collaudatore ad Hoc (Claude), 2026-10-25. Esito: INCOMPLETO, nessuna approvazione.**

Su 933154b la fase MOTION in development dà 138 PASS e 1 FAIL. Quel FAIL è un errore del mio strumento, corretto dopo: il giro di conferma (rv4) è stato interrotto. Production e regressione non sono state eseguite, perché la macchina ha poca memoria: Claude Code ha interrotto due giri (rv2 e rv4) e, come prevede, non li ho rilanciati da solo.

### Giri

| Giro | Commit | Ambiente | Esito | Database |
| --- | --- | --- | --- | --- |
| rv1 | a790b3a | development | 127/135. Dei 7 FAIL dello strumento poi corretto: 3 RM (la regex di A1 non accettava classi con spazi), 1 ERR-console-ink (EBUSY sul profilo Chrome), 3 NAV-d5 (posizione letta dopo uno screenshot in ritardo di 800 ms). Più NAV-d5-realtime, chiuso dal registro di rv2. | ripristinato dal runner, hash identico |
| rv2 | a790b3a | development | prove complete, 132/139, poi interrotto per memoria prima del ripristino. FAIL: NAV-d2 ×3 (difetto vero), NAV-d5 ×4 (tolleranza mia di 2 px) | ripristinato a mano dal suo `snapshot.sql`, hash identico |
| rv3 | 933154b | development | **138/139**. L'unico FAIL, NAV-d2-charcoal, è dello strumento (vedi D2) | ripristinato dal runner, hash identico |
| rv4 | 933154b | development | interrotto per memoria all'avvio. Ho fermato i processi orfani | ripristinato a mano, hash identico, Mailpit vuoto |

Hash di riferimento sempre `f7f37c04…e21e64` (`verify-motion.environment.txt`).

### D1-D5 e A1

- **D1 corretto** (rv1, rv3):
  - nessun `InvalidStateError` nei 9 giri dei temi;
  - `pagereveal` porta una view transition su tutte le 8 navigazioni dalla barra e dopo il form di login (VT-err, NAV-vt).
- **D2 corretto su 933154b** (rv3, a meno della conferma di rv4).
  - **Come lo misuro:** la sonda a pillola rossa di rv2 non serve più. Rende opaca anche una velatura, quindi non distingue un'etichetta coperta da una visibile sopra la velatura. L'ho sostituita con una misura di leggibilità sui fotogrammi veri, senza toccare la pagina:
    - per ogni riga di pixel di ogni etichetta della barra, il contrasto WCAG fra l'inchiostro e il colore di fondo della riga;
    - separatamente per la parte sotto l'evidenziazione e per quella fuori;
    - contano solo le righe che hanno inchiostro quando l'etichetta è libera;
    - soglia 3:1.
  - **Calibrazione:** sul fotogramma 05 di rv1 (a790b3a, Ink) l'intestazione SECURITY sotto l'evidenziazione misura 1,04:1, cioè coperta, ed è rilevata.
  - **Su 933154b**, Paper, Ink e Charcoal rallentati 10 volte (25 fotogrammi ciascuno) e Ink a velocità reale (41 fotogrammi):
    - nessuna etichetta sotto 3:1 in Paper, Ink e Ink a velocità reale;
    - minimo sotto l'evidenziazione: Paper 5,07:1, Ink 4,19:1, Ink a velocità reale 7,9:1, Charcoal 4,04:1.
  - **L'unico rilievo** (Charcoal, 1527 ms, «Security below 1,05:1») è un falso positivo. Erano righe sotto la linea di base di una scritta maiuscola, senza inchiostro; a occhio SECURITY resta leggibile sopra la velatura in tutti i fotogrammi (`verify-motion.frames.development-rv3.charcoal.*.png`). La regola delle righe con inchiostro corregge questo errore e regge la calibrazione, ma il giro di conferma (rv4) è stato interrotto.
  - **Su a790b3a (rv2) D2 c'era ancora:** Settings coperta all'arrivo nei tre temi, SECURITY in Ink, Permissions in Charcoal.
- **D3 corretto:** in en e it nessun valore negativo; ogni valore intermedio è raggruppato come il testo finale (rv1, rv3: 436 valori in en e 424 in it).
- **D4 corretto:** nessuna transizione di `transform` su Personalizza con il movimento ridotto (RM-dash).
- **D5 corretto:**
  - secondo il registro `requestAnimationFrame` della pagina, dal primo fotogramma della pagina nuova l'evidenziazione parte da Users e scende sempre verso Settings;
  - nei tre temi e a velocità reale, su a790b3a e su 933154b;
  - la tolleranza ora è metà dell'altezza della voce;
  - su a790b3a la partenza era 8 px sopra la posizione a riposo. Su 933154b il controllo passa, ma lo scarto esatto non è stampato (lo stampa la versione dello strumento di rv4).
- **A1 chiusa come decisa:** con il movimento ridotto restano solo transizioni di `transform` di 120 ms su SUMMARY, BUTTON e cella premuti (RM-cust, RM-dlg, RM-cell). Nient'altro si muove.

### Cosa manca per chiudere

1. MOTION in development su 933154b con lo strumento definitivo (conferma di D2).
2. MOTION in production.
3. Regressione in development: U5 CUST, U4 DASH, U3 SRCH, U1 (NAV, RAIL, STYLE adattata, ESC).

Uno alla volta, con memoria sufficiente: `RW_V8_NEW=933154b RW_MOTION_RUN=-rv5 bash verify-motion.all.sh motion.development` (poi `motion.production`, poi `cust.development dash.development u3.development u1.development`).

### Ambiguità

Nessuna nuova.

**Interpretazione mia, da confermare:** per «illeggibile» uso la soglia 3:1 del design system per gli elementi grandi o non testuali. Nel caso peggiore, 4,04:1, i valori misurati sono comunque sopra.

### Ambiente

- Database identico allo snapshot dopo ogni giro, anche dopo le due interruzioni (ripristino a mano registrato in `verify-motion.environment.txt`).
- Mailpit vuoto, nessun `.env` e nessun processo residuo.
- Restano nello scratchpad i profili Chrome di `mw-motion-development-rv1…rv4`, bloccati da EPERM.
- Nessun commit.

## Riverifica finale su 933154b (rv5)

**Collaudatore ad Hoc (Claude), 2026-10-25. Esito: INCOMPLETO, nessuna approvazione.**

MOTION in development è stata eseguita per intero: 136/139. I 3 FAIL sono tutti NAV-d2 e, guardati fotogramma per fotogramma, sono un errore del mio strumento, non della pagina. MOTION in production è stata interrotta da Claude Code per memoria bassa dopo 6 controlli, tutti PASS. Come previsto, non l'ho rilanciata e non ho eseguito la regressione.

### Conteggi per fase e ambiente

| Fase | Ambiente | Esito | Database |
| --- | --- | --- | --- |
| MOTION (`motion.development`) | development | **136/139**. FAIL: NAV-d2-paper, NAV-d2-ink, NAV-d2-charcoal, falsi positivi dello strumento (sotto) | ripristinato dal runner, hash identico, Mailpit vuoto |
| MOTION (`motion.production`) | production | **interrotto per memoria** dopo 6 PASS e 0 FAIL (ERR-console dei temi) | processi orfani fermati, ripristinato a mano dal suo `snapshot.sql`, hash identico, Mailpit vuoto |
| U5 CUST, U4 DASH, U3 SRCH, U1 (NAV, RAIL, STYLE, ESC) | development | **non eseguiti** (interruzione precedente) | - |

Hash `f7f37c04…e21e64` verificato prima di cominciare e dopo ogni passo (`verify-motion.environment.txt`).

### D2: perché i 3 FAIL sono dello strumento

- La regola delle righe con inchiostro (il falso positivo di rv3) era già nello strumento. In rv5 regge: il rilievo «Security below 1,05:1» di Charcoal non compare più.
- I nuovi rilievi hanno un'altra causa. Il driver fa lo screenshot e **solo dopo** legge la posizione delle etichette e dell'evidenziazione. Con la macchina a corto di memoria, fra le due operazioni passa abbastanza tempo da:
  - spostare l'evidenziazione;
  - nei fotogrammi 02-03, cambiare documento.

  Così le misure cadono su punti dell'immagine dove l'etichetta non c'è (contrasto 1:1).
- **Rilievi** (rallentati 10 volte):
  - Paper a 409 ms (Permissions, 35), 1523 ms (Activity) e 2407 ms (Settings);
  - Ink a 428 ms (Roles, 11), 1595 ms (Security), 1788 ms (Activity), 2437 ms (Settings) e 2537 ms (System);
  - Charcoal a 431, 1591, 2442 e 2534 ms.

  Ink a velocità reale non ha rilievi: il minimo sotto l'evidenziazione è 7,9:1.
- **Controllo a occhio:**
  - nei fotogrammi `verify-motion.frames.development-rv5.{paper,ink}.02/03/16.png` tutte le etichette sono leggibili;
  - nel 16 di Ink l'evidenziazione sta su SYSTEM, che resta leggibile, e non su Settings come dice la geometria letta dopo;
  - nel 03 di Ink «Check a permission» si legge sopra la velatura;
  - nel 24 Settings è evidenziata e leggibile.
- **Correzione dello strumento (dopo rv5, non ancora eseguita):**
  - `verify-motion.browser.mjs` legge la geometria subito prima e subito dopo lo screenshot (`v0` e `v`);
  - `label_legibility` giudica solo i fotogrammi in cui le due letture coincidono: stesso documento, stesse etichette, evidenziazione entro 2 px;
  - NAV-d2 chiede almeno 10 fotogrammi giudicati.

  Va confermata con un giro di `motion.development`.

### D5: scarto della partenza, misurato

Il registro `requestAnimationFrame` della pagina nuova (/settings) confronta la prima posizione dell'evidenziazione con la sua posizione a riposo su Users (y = 204):

| Tema | Primo fotogramma | Scarto | Monotona verso Settings | Fotogrammi |
| --- | --- | --- | --- | --- |
| Paper (rallentato 10 volte) | y 204 | **0 px** | sì, fino a 442 | 292 |
| Ink (rallentato 10 volte) | y 204 | **0 px** | sì, fino a 442 | 286 |
| Charcoal (rallentato 10 volte) | y 204 | **0 px** | sì, fino a 442 | 288 |
| Ink, velocità reale | y 204 | **0 px** | sì, fino a 442 | 275 |

NAV-d5 passa in tutti e quattro i casi: D5 è corretto.

### Altri risultati di development

D1 (VT-err, NAV-vt), D3, D4 e A1 come decisa: tutti PASS, come in rv3.

### Osservazione, non decisa

Nei fotogrammi 02-03, con le animazioni rallentate 10 volte e durante il cambio di pagina, in Paper e in Ink tutta la pagina compare circa 5 px più in alto che nei fotogrammi 00 e 24 (Dashboard a y 147 invece di 152). Il registro della pagina nuova non mostra spostamenti, quindi è probabilmente l'istantanea della view transition, o la cattura dello screenshot durante la transizione. Non lo classifico come difetto: lo riporto perché chi conosce la specifica decida se va verificato.

### Ambiguità

Nessuna nuova. Resta da confermare l'interpretazione della soglia 3:1 per «illeggibile».

### Cosa manca per chiudere

1. `motion.development` con lo strumento corretto (conferma di D2).
2. `motion.production`.
3. `cust.development`, `dash.development`, `u3.development`, `u1.development`.

Uno alla volta, con più memoria libera. In rv5 ne erano liberi circa 2,6 GB su 16.

### Ambiente

- Nessun `.env` residuo (quello della cartella di production l'ho cancellato a mano) e nessun processo di test residuo.
- Le cartelle di lavoro di rv4 e rv5 sono svuotate, tranne i profili Chrome `chrome-mo-*` (EPERM).
- Nessun commit.

## Riverifica su 933154b (rv6)

**Collaudatore ad Hoc (Claude), 2026-10-25. Esito: INCOMPLETO, nessuna approvazione del movimento.**

MOTION in development è stata eseguita per intero: 135/139. I 4 FAIL sono tutti NAV-d2 e nessuno è un'etichetta illeggibile: in nessun fotogramma giudicato un'etichetta scende sotto 3:1. Falliscono perché lo strumento corretto dopo rv5 giudica troppo pochi fotogrammi per decidere. MOTION in production è stata interrotta da Claude Code per memoria bassa dopo 94 PASS e 0 FAIL, prima del blocco D2/D5. Come previsto, non l'ho rilanciata e non ho eseguito la regressione.

### Conteggi per fase e ambiente

| Fase | Ambiente | Esito | Database |
| --- | --- | --- | --- |
| MOTION (`motion.development`) | development | **135/139**. FAIL: NAV-d2-paper, NAV-d2-ink, NAV-d2-ink-realtime, NAV-d2-charcoal, tutti per copertura insufficiente (sotto) | ripristinato dal runner, hash identico, Mailpit vuoto |
| MOTION (`motion.production`) | production | **interrotto per memoria** dopo 94 PASS e 0 FAIL (ERR, VT-err, NAV per pagina, NAV-vt, NAV-rail, CNT en/it, RM ×25, DLG ×13, CUST e altri); non raggiunti D2, D5 e i fotogrammi | processi orfani fermati (python, 2 php, node), ripristinato a mano dal suo `snapshot.sql`, hash identico, Mailpit vuoto |
| U5 CUST, U4 DASH, U3 SRCH, U1 (NAV, RAIL, STYLE, ESC) | development | **non eseguiti** (interruzione precedente) | - |

Hash `f7f37c04…e21e64` verificato prima di cominciare e dopo ogni passo (`verify-motion.environment.txt`). Memoria libera: 4,8 GB all'inizio, 3,6 GB dopo l'interruzione.

### D2: lo strumento corretto non basta a decidere

Lo strumento ora giudica un fotogramma solo se la geometria letta subito prima e subito dopo lo screenshot coincide, e NAV-d2 chiede almeno 10 fotogrammi giudicati e almeno 5 misure sotto l'evidenziazione in movimento.

| Tema | Fotogrammi | Giudicati | Misure sotto l'evidenziazione | Minimo lì | Sotto 3:1 |
| --- | --- | --- | --- | --- | --- |
| Paper (rallentato 10 volte) | 25 | 7 | 1 | 5,39:1 | nessuna |
| Ink (rallentato 10 volte) | 25 | 8 | 2 | 4,22:1 | nessuna |
| Ink, velocità reale | 41 | 33 | 0 | - | nessuna |
| Charcoal (rallentato 10 volte) | 25 | 9 | 3 | 4,03:1 | nessuna |

- La correzione elimina i falsi positivi di rv5 (nessuna misura a 1:1), ma scarta quasi tutti i fotogrammi in cui l'evidenziazione si muove: proprio quelli che servono. A velocità reale i fotogrammi stabili sono quelli prima e dopo il movimento, quindi nessuno ha un'etichetta sotto l'evidenziazione.
- **Controllo a occhio** (`verify-motion.frames.development-rv6.*`): in `ink.08` (velatura su Activity), `charcoal.14` (velatura su SYSTEM e Settings), `paper.12` e `ink.24` (Settings evidenziata all'arrivo) tutte le etichette sono leggibili.
- Conclusione: nessuna prova di difetto, ma D2 su 933154b **non è confermato dallo strumento**. Per confermarlo serve uno strumento che misuri anche i fotogrammi in movimento senza falsi positivi (per esempio leggendo la geometria nello stesso istante dello screenshot), oppure che si accetti il controllo a occhio. Non lo decido qui.

### D5, D1, D3, D4 e A1 (development)

- D5: scarto della partenza **0 px** in Paper, Ink, Charcoal e Ink a velocità reale; sempre monotono da y 204 (Users) a 442 (Settings). NAV-d5 PASS in tutti e quattro.
- D1 (VT-err, NAV-vt: 8 su 8 view transition più quella dopo il login), D3 (CNT en 440 valori, it 444, nessuno fuori intervallo), D4 e A1 come decisa (RM): tutti PASS, anche nella parte di production eseguita.

### Ambiguità

Nessuna nuova. Resta da confermare la mia interpretazione: per «illeggibile» uso la soglia 3:1 del design system per gli elementi grandi o non testuali.

### Cosa manca per chiudere

1. Conferma di D2 con uno strumento che giudichi i fotogrammi in movimento (o decisione di accettare il controllo a occhio).
2. `motion.production` completa.
3. `cust.development`, `dash.development`, `u3.development`, `u1.development`.

Con questa macchina la memoria non basta per più di un passo di fila: conviene eseguire ogni passo in una sessione separata, con il browser e le altre applicazioni chiusi.

### Ambiente

- Nessun `.env` residuo (quello di production l'ho cancellato a mano) e nessun processo di test residuo.
- Le cartelle di lavoro di rv6 sono svuotate, tranne i profili Chrome `chrome-mo-*` (EPERM).
- Nessun commit.

## Riverifica a passi su 933154b (rv7)

**Collaudatore ad Hoc (Claude), 2026-10-26.** Un passo per sessione. Prima del passo NAV-d2 è stato corretto come deciso dall'utente il 2026-10-26: la copertura (fotogrammi giudicati, misure sotto l'evidenziazione) è ora un INFO e il controllo fallisce solo se un fotogramma giudicato ha un'etichetta sotto 3:1 (soglia decisa, `docs/design-system/README.md`, Motion, «Page to page»).

| Passo | Ambiente | Esito | Database |
| --- | --- | --- | --- |
| `motion.production` | production | **PASS, 128/128**, 0 FAIL, 0 AMB, passo completo (exit 0, 00:06-00:24). LOG-5xx, LOG-app, LOG-srv PASS. Nota: i blocchi dei fotogrammi (NAV-d2, NAV-d5) lo strumento li esegue solo in development, quindi in production non ci sono | hash `f7f37c04…e21e64` prima e dopo, ripristinato dal runner, identico, Mailpit vuoto; nessun processo residuo; `.env` cancellato, restano i profili `chrome-mo-*` (EPERM) |

Restano da eseguire, uno per sessione: `motion.development` (NAV-d2 con il criterio deciso), `cust.development`, `dash.development`, `u3.development`, `u1.development`.

## Riverifica a passi su 933154b (rv8)

**Collaudatore ad Hoc (Claude), 2026-10-26, 19:27-19:52.** Rifà il passo `motion.development` di rv7, interrotto alle 00:31 dopo SET-1 ed ERR-console (uscite parziali `-rv7` lasciate intatte). Strumento invariato rispetto a rv7: NAV-d2 giudicato solo con la regola dei 3:1, la copertura è INFO.

| Passo | Ambiente | Esito | Database |
| --- | --- | --- | --- |
| `motion.development` | development | **PASS, 139/139**, 0 FAIL, 0 AMB, passo completo (exit 0). LOG-5xx, LOG-app, LOG-srv PASS | hash `f7f37c04…e21e64` prima e dopo, ripristinato dal runner, identico; Mailpit vuoto prima e dopo; nessun processo residuo (php, python, node, chrome); `.env` cancellato; restano i profili `chrome-mo-*` (EPERM) in `work/mw-motion-development-rv8` |

### NAV-d2 e NAV-d5 (fotogrammi, solo development)

| Sidebar | Fotogrammi giudicati | Misure sotto l'evidenziazione (INFO) | Contrasto minimo lì | Sotto 3:1 | NAV-d2 | NAV-d5 |
| --- | --- | --- | --- | --- | --- | --- |
| paper | 10 / 25 | 2 | 5,39:1 | nessuna | PASS | PASS |
| ink | 17 / 25 | 2 | 4,24:1 | nessuna | PASS | PASS |
| ink a velocità reale | 34 / 41 | 0 | - | nessuna | PASS | PASS |
| charcoal | 7 / 25 | 1 | 4,03:1 | nessuna | PASS | PASS |

Copertura sotto le soglie indicative dello strumento in tutti e quattro i casi: è INFO per decisione del 2026-10-26, non un esito. Fotogrammi salvati in `verify-motion.frames.development-rv8.*`.

### Stato del movimento

`motion.production` (rv7) e `motion.development` (rv8) sono PASS. Movimento non ancora approvato: restano, uno per sessione, `cust.development`, `dash.development`, `u3.development`, `u1.development`.

Ambiguità di specifica: nessuna nuova. Memoria libera alla partenza circa 2,4 GB su 16 GB; la run non è stata interrotta.

## Regressione a passi su 933154b (rv8): `cust.development`

**Collaudatore ad Hoc (Claude), 2026-10-26, 21:14-21:22.** Passo di regressione U5 CUST in development, strumento invariato (`verify-u5.phase-cust.php` tramite `verify-motion.harness.php`). Più una sonda esplorativa (CVT) e una calibrazione in Chrome, che servono solo ad attribuire l'unico FAIL e non sono un esito.

| Passo | Ambiente | Esito | Database |
| --- | --- | --- | --- |
| `cust.development` | development | **151/152**, 1 FAIL (JS-console), 0 AMB, passo completo (exit 1 per il FAIL, 21:14-21:17). LOG-5xx, LOG-app, LOG-srv, SCH-1, SCH-2 PASS | hash `f7f37c04…e21e64` prima e dopo, ripristinato dal runner, identico; Mailpit vuoto prima e dopo; nessun processo residuo (php, python, node, chrome); `.env` cancellati, cartella di lavoro rimossa (nessun profilo EPERM questa volta) |
| sonda `cvt.development` | development | solo esplorazione (`verify-motion.phase-cvt.php`: copia della fase CUST più quattro job del browser; i controlli CUST della copia danno lo stesso 151/152 e non contano) | hash identico prima e dopo, Mailpit vuoto, cartella rimossa |

### L'unico FAIL: JS-console

- In console: `InvalidStateError: Transition was aborted because of invalid state. ViewTransition opt-in disabled`, **una volta** (su b38e350 erano due, una per navigazione: è il D1 di allora).
- **Da dove viene (sonda CVT):** il job JS di CUST fa login di `v1@u5.test`, apertura della dashboard, spostamenti e «Save for me». Contando l'errore job per job:
  - solo login: 1; login + dashboard: 1; login + dashboard + Save: 1; con due Save: 1.
  - Quindi l'errore nasce **dal form di login**, non da Personalizza né dal salvataggio. Il Save (POST e redirect) non aggiunge errori.
- **Perché nel movimento non si vede:** dopo il login `v1@u5.test` atterra su `/index.php`, la home dell'app di prova, fuori dal pannello. L'owner del passo MOTION atterra invece in `/rolewarden/users`, dentro il pannello, con la view transition (`pagereveal` = vt, VT-err PASS in rv8).
- **Calibrazione (Chrome 155 headless, stesso driver `verify-u1.browser.mjs`, due pagine statiche mie sulla 8072, nessuno script):**
  - pagina con `@view-transition { navigation: auto; }` → pagina **senza** opt-in: lo stesso identico errore in console;
  - pagina con opt-in → pagina con opt-in: nessun errore.
  - Quindi il messaggio è di Chrome: compare ogni volta che si lascia una pagina con opt-in per una pagina senza. Qui la pagina di partenza è il login del pannello, quella di arrivo è dell'app.
- **Il resto di CUST** (blocchi, scelte per utente e predefinito, su/giù con fuoco visibile, pinned, Save, senza JavaScript, lingue, A2, log, schema) passa come su b38e350.

### Difetti

Nessun difetto attribuito al modulo in questo passo.

### Ambiguità di specifica (da decidere, non decise qui)

- **A-CUST-1:** il passaggio dal pannello a una pagina **fuori dal pannello** (qui il login che porta alla home dell'app, ma vale anche per qualunque link che esce dal pannello) lascia in console l'errore di Chrome sulla view transition abortita. Il design system dice solo «Page to page (View Transitions) … Browsers without view transitions change page as before» e non tratta l'uscita dal pannello. Il controllo U5 JS-console chiede «nessun errore JavaScript in console» e su db68a26 passava. Va deciso se:
  - è accettabile, perché è un messaggio del browser e la pagina d'arrivo è dell'acquirente. Allora JS-console va trattato come VT nel passo MOTION, cioè senza contare questo messaggio, e il passo è PASS;
  - oppure il modulo non deve lasciarlo, per esempio non chiedendo la transizione quando la destinazione è fuori dal pannello. Allora è un difetto da correggere.

### Stato del movimento

`motion.production` (rv7) e `motion.development` (rv8) sono PASS. `cust.development` (rv8) è **151/152, con l'unico FAIL legato ad A-CUST-1**. Restano, uno per sessione: `dash.development`, `u3.development`, `u1.development`. Memoria libera alla partenza circa 2,7 GB; nessuna interruzione.
