# U1 — Navigazione (v1.1): collaudo indipendente

## Seconda riverifica su `b1df3cc` (2026-10-19, Collaudatore ad Hoc)

**Esito: PASS. U1 è approvata, punto 8 completo.** D2 è corretto. A3, decisa dall'utente (le coppie per modo valgono anche per `--side-ink` e `--side-muted`), è applicata nel CSS servito.

Del commit conosco solo il `--stat`: `resources/css/panel.css` e `src/Assets/css/panel.css`, 9 righe in più e 9 in meno.

| Suite | development | production |
| --- | --- | --- |
| V5 adattata | **177/177**, 0 FAIL, 1 AMB già nota (T06); T02 e T05 PASS | **177/177**, uguale |
| U1 (NAV, CNT, TODAY, RAIL, STYLE, LANG, ESC) | **226/226**, 0 AMB | **226/226**, 0 AMB |
| A2 (`verify-u1.phase-a2.php`) | **15/15** | **15/15** |
| S1, solo fase Y (layout in Chrome a 1280-1920 px, passo `s1y.<env>`) | **52/52** | **52/52** |

- **U1 e A2:** i controlli sono gli stessi del giro su `d73ce80`, riga per riga. Cambia solo il testo di T-3 («ora di punta 12:00» invece di «05:00»), perché dipende dall'ora UTC dell'esecuzione. Le misure di colore e contrasto di STYLE e i 12 token di A2 sono confrontati con valori fissi, quindi i valori resi non sono cambiati.
- **CSS servito, confronto con `d73ce80`:** cambiano solo tre punti.
  - `.rw-side-nav`: `gap: 2px` diventa `var(--space-0-5)`, che vale 2px.
  - `.rw-side-count`: `line-height: 20px` diventa `var(--space-5)`, che vale 20px.
  - In ogni tema `--side-ink` e `--side-muted` passano da `var(--l-side-*)`/`var(--d-side-*)`. Le due coppie hanno lo stesso primitivo di prima, uguale in chiaro e in scuro.
  - I valori calcolati restano identici.
- **Perché S1 fase Y:** il `gap` della lista di navigazione può spostare la barra. Il layout misurato (`verify-u1.s1.s1y.b1df3cc.*.layout.json`) coincide con quello del giro S1 completo su `d73ce80`, con quattro sole eccezioni. Tutte dipendono dai dati e non dal CSS:
  - percorsi degli screenshot;
  - id utente nelle URL (7 invece di 17);
  - numero di righe delle tabelle: qui gira solo la fase Y, quindi ci sono meno dati seminati;
  - di conseguenza, colonne spostate di 0,3 px.

  Non ci sono differenze nella barra.
- **Non ripetute su `b1df3cc`:** S1 completa, V8, V8b, V2 e il giro Docker. Il commit tocca solo il CSS del pannello, e le misure rese di barra e contenuto sono invariate.
- **Correzione al giro precedente:** in D2 avevo scritto che non esiste un `--space-*` da 2px. Era sbagliato: `--space-0-5: 2px` c'era già nel CSS servito di `d73ce80` (usato da `.rw-auth-err`, `.rw-toast__close` e altri).
- **Nota per l'autore (non è un difetto):** il `line-height` del contatore usa un token di spaziatura (`--space-5`). Nessun `--leading-*` vale 20px in tutti i temi, e T05 lo accetta.

### Difetti e ambiguità

Nessun difetto aperto. Nessuna ambiguità nuova. T06 (AMB della V5) era già nota e non riguarda U1.

### Pulizia

- **Primo tentativo:** si è fermato per memoria scarsa.
  - Lo snapshot del giro era già stato cancellato. È lo snapshot canonico del database vuoto, cioè la sola riga `CREATE DATABASE`.
  - L'ho rigenerato e il suo SHA256 coincide con `f7f37c04…e21e64`. Poi ho ripristinato `rolewarden_test`, riverificato l'hash e svuotato Mailpit.
- **Questo giro:** dopo ognuna delle 8 esecuzioni il database è uguale allo snapshot `f7f37c04…e21e64` (`verify-u1.environment.txt`, righe `b1df3cc`).
- **Alla fine:** hash finale `f7f37c04…e21e64`, Mailpit vuoto, nessun processo php/python/node/chrome mio residuo.
- **Scratchpad:** restano `u1rc/`, `u1rc2/`, `u1rc3/` e `u1rc4/`. Contengono solo profili Chrome che il sandbox non lascia cancellare, nessun `.env` e nessuna credenziale. Vanno cancellati a mano.
- Nessun commit.

---

## Riverifica su `d73ce80` (2026-10-19, Collaudatore ad Hoc)

**Esito: FAIL sul punto 8 (regressione V5). U1 non è approvata.** Punti 1-7 PASS; A1 e A2 chiuse secondo le decisioni dell'utente; un difetto nuovo (D2) e un'ambiguità nuova (A3) dalla V5.

Del commit conosco solo il `--stat`: `docs/design-system/README.md`, `resources/css/panel.css`, `src/Assets/css/panel.css`.

| Suite | development | production |
| --- | --- | --- |
| U1 (NAV, CNT, TODAY, RAIL, STYLE, LANG, ESC) | **226/226**, 0 AMB | **226/226**, 0 AMB |
| A2, fase nuova `verify-u1.phase-a2.php` | **15/15** | **15/15** |
| S1 (N, Q, Y, A, C, R, T, D, O, W, B, G), completa | **154/154** | **154/154** |
| V8 (A-D, F, G; l'ambiente lo sceglie la suite) | **195/195**, 2 AMB già decise (A15c, G03) | |
| V5 adattata | **175 PASS, 2 FAIL (T02, T05)**, 1 AMB già nota (T06) | uguale |
| V2 adattata / extra / adapt | **151/151** + 41/41 + 16/16 | **169/169** + 54/54 + 16/16 |

Non ripetute su `d73ce80`: V8b (116/116 su `f4115c5`) e il giro Docker S3/aggiornamento (su `6c636d4`); il commit tocca solo CSS e README.

### A1 e A2 chiuse

- **A1 (decisa):** in `verify-u1.phase-style.php` il controllo `-5` di Paper ora chiede testo `accent` ≥ 3:1 sulla barra, peso ≥ 600 e testo ≥ 4.5:1 sulla velatura; la velatura non è più misurata contro 3:1. Nessuna AMB S-31. PASS in 6 combinazioni × 2 ambienti.
- **A2 (decisa):** il controllo `-2` delle 18 combinazioni chiede anche che i 12 token `--side-*`, `--on-side-accent`, `--charcoal-*` siano definiti per nome. Nessuna AMB S-tok. Fase A2:
  - senza foglio dell'applicazione, in 3 temi × chiaro/scuro, i 12 token hanno esattamente i valori di `tokens.css` (Console) e della tabella del README (Clarity, Contrast);
  - un foglio dell'applicazione (`themeStylesheet`, come in `documentation/configuration.html`) con `:root { --side-accent; --on-side-accent; --charcoal-surface }` cambia in Chrome fondo e testo della voce attiva di Ink e Charcoal e il fondo della barra Charcoal, in chiaro e in scuro;
  - tolto il foglio, i valori tornano quelli della tabella.

### Adattamenti delle suite di regressione (nessuna aspettativa cambiata nella sostanza)

- **G07 (V2) e G07v5 (V5):** dalla 1.1 ogni pagina porta il form di compressione della barra (POST `rolewarden/sidebar`), un submit che non è un salvataggio delle Impostazioni. Escluso come i selettori della barra in alto, come L17 della V8b. Senza adattamento: 1 FAIL ciascuna (uscite `*.unadapted-G07.*`).
- **V5, R-* e X08:** le variabili `--sb-*` sono variabili di componente dichiarate solo su `.rw-side` (CSS servito), derivate dai token semantici; la suite le cercava su `:root`, dove non esistono, e falliva sul login (senza barra). Escluse come già `--tw-*`; i loro valori resi sono coperti da STYLE `-2`/`-3`. Senza adattamento: 9 FAIL (uscite `*.unadapted-sb.*`).
- **V5, percorso di `verify-v5.css.py`:** lo script cercava il file nella cartella sopra la copia di lavoro; ora lo riceve da `RW_U1_V5CSS` (impostata in `verify-u1.all.sh`). Prima la fase 2 si fermava con un errore fatale.

### Difetti

- **D2 — valori grezzi nel CSS della barra (V5 T05).** Il CSS servito ha `.rw-side-nav { gap: 2px }` e `.rw-side-count { line-height: 20px }`, fuori dalle eccezioni dichiarate. Il README del design system vuole che i componenti leggano solo token semantici; la V5 si era chiusa senza valori grezzi. Nota: non esiste un `--space-*` da 2px (il minimo è 4px) e l'anteprima `components/Sidebar/preview.html` usa `gap: 2px`; se la correzione sia un token o un'eccezione dichiarata lo decide l'autore.
  - Riproduzione: `verify-u1.all.sh v5.development`, controllo T05.

### Ambiguità di specifica (all'utente, non decise)

- **A3 — `--side-ink` e `--side-muted` mappati direttamente sui primitivi (V5 T02).** Nel CSS servito `--side-ink` e `--side-muted` valgono `var(--p-…)` per tema (es. `--p-inkblue-25`, `--p-slate-50`, `--p-white`), non tramite le coppie `--l-*`/`--d-*` come gli altri token semantici. La regola V5 chiede le coppie; il README dice solo che «un tema mappa il livello semantico sui primitivi», e per la tabella questi due token hanno lo stesso valore in chiaro e in scuro. Non è detto se le famiglie 1.1 indipendenti dal modo debbano passare dalle coppie (e quindi seguire il personalizzatore) o no.

### Pulizia

- Database `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` dopo ognuna delle 21 esecuzioni (verificato per hash, `verify-u1.environment.txt`). Mailpit vuoto. Nessun processo php/python/node/chrome residuo; nessun container, immagine o rete creati.
- Restano in scratchpad `u1rc/` e `u1rc2/`: solo profili Chrome con file che il sandbox non lascia cancellare (nessun `.env`, nessuna credenziale), da cancellare a mano.
- Nessun commit.

---

## Giri precedenti

**Collaudatore ad Hoc (Claude), 2026-10-19.** Primo giro sul commit `6c636d4`, riverifica sul commit `f4115c5` (main), che corregge D1.

## Esito

**PASS sui punti 1-7. Il punto 8 è collaudato solo in parte: U1 non è ancora approvata.**

- La regressione S1 è stata interrotta due volte dal sistema per memoria scarsa.
- Le suite V8, V5 e V2 non sono state eseguite.

| # | Punto | Esito | Totali |
| --- | --- | --- | --- |
| 1 | Voci e permessi | **PASS** | fase NAV 71/71 |
| 2 | Contatori | **PASS** su `f4115c5` (D1 corretto) | fase CNT 14/14 |
| 3 | Oggi | **PASS** | fase TODAY 11/11 |
| 4 | Compressione | **PASS** | fase RAIL 13/13 |
| 5 | Stili | **PASS**, 2 ambiguità all'utente | fase STYLE 104/104 (3 temi × 3 stili × chiaro/scuro, misura in Chrome) |
| 6 | Aggiornamento dalla 1.0.0 | **PASS** | Docker: aggiornamento 80/80, installazione nuova 66/66 |
| 7 | Lingue | **PASS** | fase LANG 5/5 |
| 8 | Regressione e sicurezza | **parziale** | ESC 6/6; V8b 116/116 ×2; S1 145/145 fino alla fase O; V8, V5 e V2 non eseguite |

**Suite U1 su `f4115c5`:** 226 PASS, 0 FAIL, 2 AMB in development e 226 PASS, 0 FAIL, 2 AMB in production (`verify-u1.console.u1.{development,production}.txt`).

## Ambiente e regole

- **Database:** solo `rolewarden_test` su 127.0.0.1:3317. Credenziali solo come variabili di processo.
  - Snapshot `f7f37c04…e21e64` ripristinato in ogni esecuzione (`verify-u1.environment.txt`).
  - In due casi il ripristino automatico è fallito, perché il sistema ha fermato il processo per memoria. L'ho rifatto a mano dallo snapshot del giro e verificato per hash. Stato finale identico allo snapshot.
- **Email:** solo Mailpit (SMTP 1026), indirizzi `@example.com` e `@*.test`. Mailpit è vuoto alla fine.
- **Host:** app di prova copiata da `../rolewarden-app-test`, con il modulo collegato da `git archive` del commit, servita da `php -S` sulla porta 8071. La porta 8000 non è stata toccata.
- **Docker:** immagine `u1-col-php:8.3` (dal Dockerfile di S3) e container `u1-web` sulla rete `u1-net`, porta 8092. Container, immagine e rete sono stati rimossi.
- **Browser:** Chrome headless con profilo in una cartella mia, chiuso per PID.
- **Letto:** SPEC (Pannello admin, decisioni v1.1 e U1), BRIEF-v1.1, il design system (README e Sidebar), CHANGELOG Unreleased, `documentation/`, `_AI-LOG.md`, gli script di `tests/Integration`, e HTML e CSS serviti.
- **Non aperto:** controller, model, viste PHP, migrazioni, seeder, `src/Settings`, `src/Models`, `resources/css`, test unitari, diff.
  - Del commit di correzione conosco solo il `--stat` (`src/Settings/Sidebar.php`, una riga).
  - Il layout 1.0 è stato copiato nel container senza leggerlo.

## 1. Voci e permessi (fase NAV)

**Account provati (15):**
- super admin; admin; utente senza permessi;
- un ruolo per ciascun permesso delle voci (`users.view`, `roles.view`, `permissions.view`, `activity.view`, `settings.view`, `appearance.update`);
- un ruolo senza permessi di vista (`users.create`/`import`/`update`, `sessions.view`, `security.alerts`);
- un ruolo misto; un ruolo figlio che eredita `activity.view`;
- admin con override negativi; super admin con override negativi; `user` con override positivi; figlio con override negativo e positivo.

**Per ognuno è verificato che:**
- la barra renda esattamente le voci dei permessi posseduti, calcolati con la precedenza della SPEC;
- nessuna voce sia disabilitata o senza link, e nessun gruppo sia vuoto;
- i gruppi siano tra Access, Security e System, nell'ordine;
- il server sia d'accordo: ogni voce mostrata apre la sua pagina con `aria-current="page"` solo su di sé, ogni voce nascosta è rifiutata.

**Altri casi:**
- Il gruppo Overview manca: la Dashboard entra con U4.
- Sotto le liste (dettaglio, modifica, creazione, import) è marcata come corrente la sezione madre; su Profilo e Sessioni nessuna voce.
- Un account disattivato non vede voci, nemmeno se disattivato dal pannello mentre è collegato.
- Dopo l'eliminazione dal pannello del suo unico ruolo, l'utente vede esattamente ciò che il server gli apre.

## 2. Contatori (fase CNT)

- **Utenti:** il contatore e l'intestazione della schermata Utenti (Utenti / Attivi / Inattivi) coincidono con le query. Gli utenti cancellati sono esclusi, i disattivati contati.
- **Permessi:** il contatore coincide con la query, anche dopo un permesso aggiunto.
- **Ruoli:** su `6c636d4` il contatore era 20 contro 18 (D1); su `f4115c5` coincide (C-2r PASS).
- **Contatore del log attività:**
  - assente prima della prima apertura, anche con voci e accessi falliti già presenti;
  - assente subito dopo l'apertura;
  - poi vale le voci del log scritte da altri più gli accessi falliti successivi all'ultima apertura. Esclusi le voci proprie e gli accessi riusciti. Dettagli: 2 voci altrui + 4 falliti; la voce propria non conta;
  - ogni utente ha il suo: l'apertura di un altro non lo tocca;
  - riaprire il log, anche filtrato, lo azzera, e dopo l'azzeramento un solo accesso fallito vale 1;
  - è reso come «N new», con la classe di attenzione.

## 3. Oggi (fase TODAY)

- **Dati:** righe di `auth_logins` piantate a più ore di oggi (fuso dell'app, UTC) e di ieri.
- **Cifre:** accessi riusciti e falliti di oggi uguali alle query; quelli di ieri esclusi.
- **Linea:** 24 punti con altezze proporzionali agli accessi riusciti di ogni ora; `role="img"` con un'etichetta che nomina l'ora di punta e il suo numero.
- **Chi lo vede:** solo chi ha `activity.view` (per ruolo, ereditato o da super admin); mai senza il permesso, né con il permesso negato.
- **Interruttore:**
  - salvato da chi ha `settings.update`, con una voce nel log;
  - vale per tutti;
  - con il solo `settings.view` è rifiutato;
  - un valore non valido e un POST senza CSRF sono rifiutati;
  - riacceso, Oggi torna per tutti.
- **Barra compressa:** senza Oggi (punto 4).

## 4. Compressione (fase RAIL)

- POST `rolewarden/sidebar` con CSRF.
- **Rifiutati senza effetto:** POST senza token; GET; utente anonimo (mandato al login).
- Il redirect resta nell'applicazione anche con un Referer esterno.
- **Sulla barra compressa:**
  - niente Oggi;
  - ogni voce ha etichetta nel markup e tooltip;
  - in Chrome è larga 64px, con etichette fuori vista (clip di 1px);
  - nell'albero di accessibilità ogni link è nominato dalla sua etichetta e il pulsante ha un nome.
- La scelta resta dopo uscita e nuovo accesso.
- **Per utente:** l'utente semplice comprime la sua barra e quella del proprietario non cambia; un altro utente resta espanso.
- Un valore non valido non dà errore.

## 5. Stili (fase STYLE)

**Personalizzatore e file di tema:**
- Ink e Charcoal salvati nel file di tema e applicati a tutti gli utenti. Paper salva senza altro stile.
- **Rifiutati senza cambiare il file e senza eco:** `neon`, `Ink`, `"><script>…`, `paper `.
- Senza `appearance.update` il personalizzatore non si apre e non salva; un salvataggio senza CSRF è rifiutato.
- Un file di tema senza stile dà Paper.
- `app/Config/RoleWarden/theme.json` (Charcoal) vince sul file in `writable/` (Ink).

**In Chrome, 18 combinazioni (3 temi × 3 stili × chiaro/scuro), screenshot `verify-u1.style-*.png`:**
- fondo, voci, etichette, chip dei contatori e voce attiva (peso ≥ 600) hanno i valori del design system: `tokens.css` per Console, la tabella del README per Clarity e Contrast;
- ogni colore della barra è un valore di token;
- tutti i testi sono ≥ 4.5:1 (minimo misurato 6.1:1);
- sulle barre scure la voce attiva ha un riempimento ≥ 7.8:1 sulla barra.

## 6. Aggiornamento dalla 1.0.0 (Docker, `verify-u1.s3run.py`)

**up83, 80/80:**
- **Installazione 1.0.0:** dall'installer dello zip `build/rolewarden-app-1.0.0.zip`, con utenti e un file di tema 1.0 (Clarity, senza stile della barra).
- **Aggiornamento:** cartella `modules/rolewarden` sostituita con `git archive`, poi `php spark migrate -n RoleWarden`:
  - la migrazione aggiunge solo la sua riga in `migrations` e il setting `sidebarToday=0`;
  - barra Paper con tutte le voci, Oggi spento, Impostazioni su «Hidden»;
  - tema Clarity mantenuto; tutte le schermate a 200.
- **Rollback del batch:** riporta il database esatto della 1.0.0. Rimigrare funziona e rispegne Oggi.
- **Override:** `layouts/panel.php` della 1.0 copiato in `app/Views/overrides/RoleWarden/Views/layouts/`:
  - le 15 schermate rispondono 200 con la barra vecchia;
  - le fasi S3 U, E, D, S, T, K e L passano con l'override in posto, log puliti.

**new83, 66/66:** installazione nuova dall'installer con il modulo del commit: Oggi acceso, Impostazioni su «Shown»; fasi S3 U..L PASS.

Eseguito su `6c636d4`. La correzione `f4115c5` tocca solo `src/Settings/Sidebar.php`, cioè il conteggio dei ruoli, quindi l'esito vale anche per `f4115c5`; il giro Docker non è stato ripetuto.

## 7. Lingue (fase LANG)

- Confronto posizione per posizione tra inglese e italiano di: barra, Oggi, pulsante nei due stati, interruttore delle Impostazioni, riga dello stile nell'Aspetto (29 stringhe). Tutte hanno il testo italiano.
- Nessuna di queste stringhe inglesi appare come parola nelle pagine italiane (Utenti, Impostazioni, Aspetto, Profilo).
- Paper, Ink e Charcoal restano invariati.
- **L-4:** il log attività nomina i due campi nuovi in ciascuna lingua («Today in the sidebar» / «Oggi nella barra laterale», «Sidebar» / «Barra laterale»).

## 8. Regressione e sicurezza

- **Fase ESC, 6/6:**
  - un'etichetta della barra cambiata dal file di lingua dell'applicazione è escapata, nel testo e nel `title` della barra compressa;
  - valori creati ad arte per interruttore, stile e compressione non sono mai stampati grezzi;
  - token CSRF nel form della barra su ogni pagina;
  - nessun 5xx né testo SQL o PHP nelle 1.500 e più risposte del giro;
  - log applicativo pulito, a parte i rifiuti CSRF provocati.
- **V8b (fasi L, M, P, I, G), 116/116 in development e production, con 1 AMB già nota (LA1).**
  - Adattato L17 in `verify-u1.phase-l.adapted.php`: dalla v1.1 ogni pagina porta il form di compressione della barra, un pulsante submit che non è un'azione delle Impostazioni.
  - Senza adattamento il risultato era 115/116 (uscita conservata come `*.unadapted-L.*`).
- **S1 (fasi N, Q, Y, A, C, R, T, D, O), 145/145 in development.** Il giro è stato fermato dal sistema durante la fase W; mancano W, B e G e tutta la production.
- **Non eseguite:** V8 (A-D, F, G); V5 e V2 (test, extra, adapt).
  - Per V5 e V2 ho preparato le copie adattate `verify-u1.v5adapted.php` e `verify-u1.v2adapted.php`: leggono le voci anche in `ul.rw-side-nav`, la struttura a gruppi della v1.1. Nessuna aspettativa cambia.
  - Sono già collegate in `verify-u1.all.sh`.
- **S3:** coperta in Docker (punto 6).

## Difetti

- **D1 (su `6c636d4`, corretto in `f4115c5`):** il contatore Ruoli contava i ruoli eliminati dal pannello.
  - Riproduzione: eliminare un ruolo e confrontare il contatore con `SELECT COUNT(*) FROM acl_roles WHERE deleted_at IS NULL`.
  - Riverificato: C-2r PASS in development e production.

## Ambiguità di specifica (all'utente, non decise)

- **A1 — 3:1 per Paper.** La voce attiva di Paper è una velatura `accent-tint` con testo `accent`.
  - Il riempimento sta a 1.05-1.64:1 sulla barra; il testo a 6.95-13.7:1.
  - Non è detto se il 3:1 valga per il riempimento, come sulle barre scure, o per la voce nel suo insieme.
- **A2 — nomi dei token.** I nomi `--side-accent`, `--on-side-accent` e `--charcoal-*` del design system non esistono nel CSS servito.
  - Ink e Charcoal leggono i token del modo scuro (`--d-accent`, `--d-surface` …) in entrambi i modi.
  - I valori resi sono quelli del design system.
  - Però un acquirente non può impostarli per nome, e un accento o una superficie scura scelti nel personalizzatore cambiano Ink e Charcoal anche in chiaro.

## Osservazioni (non contate)

- **O1:** il `.env` di prova ha `rolewarden.signInRate = 1000`, fuori dall'intervallo 5-100 della schermata. Finché è così ogni salvataggio delle Impostazioni è rifiutato. È comportamento 1.0.
- **O2:** la colonna Oggetto del log stampa l'etichetta salvata con la voce, nella lingua di chi l'ha scritta («Settings» nel pannello italiano). È dato 1.0.
- **O3:** una disattivazione scritta direttamente nel database non si vede finché i permessi in sessione non si aggiornano. Dal pannello funziona.
- **O4:** in production CodeIgniter risponde a un CSRF mancante con un redirect, non con 403; nulla viene salvato.
- **O5:** il dettaglio del log mostra l'interruttore come `1`/`0`, non come Shown/Hidden.

**Non provato:** il controllo visivo in un browser vero, dovuto all'autore e all'utente sullo staging.

## Pulizia

- Database uguale allo snapshot. Mailpit vuoto.
- Container, immagine `u1-col-php:8.3` e rete `u1-net` rimossi.
- Restano in scratchpad `u1r-run2` e `u1r-v8b-development`: profili Chrome con file bloccati, da cancellare a mano.
- Nessun commit.

## Per chiudere U1

Quando la memoria lo permette, una suite alla volta e in primo piano:

```
bash verify-u1.all.sh s1.development
bash verify-u1.all.sh s1.production
bash verify-u1.all.sh v8
bash verify-u1.all.sh v5.development
bash verify-u1.all.sh v5.production
bash verify-u1.all.sh test.development
bash verify-u1.all.sh test.production
bash verify-u1.all.sh extra.development
bash verify-u1.all.sh extra.production
bash verify-u1.all.sh adapt.development
bash verify-u1.all.sh adapt.production
```

Servono `RW_DB_USERNAME`, `RW_DB_PASSWORD` e `RW_U1_SCRATCH` nell'ambiente, e una cartella `RW_U1_SCRATCH` nuova.
