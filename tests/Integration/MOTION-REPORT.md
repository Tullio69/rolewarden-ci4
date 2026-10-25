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
