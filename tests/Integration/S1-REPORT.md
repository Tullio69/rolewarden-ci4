# S1 — Passo 1 della roadmap di pubblicazione: collaudo indipendente

## Riverifica 7560117 — 2026-10-18, Collaudatore ad Hoc (Claude), commit `7560117` (main, locale; comprende `d665818`)

**Esito: PASS. S1 approvata per il collaudo.**
- I nomi delle aree sono tradotti come decide la SPEC («Esiti di S1»): pagina Permissions e colonna Area delle matrici, aria-label della riga compreso.
- La regressione ridotta è pulita.
- M11 (osservazione O3) passa in tutti gli 8 giri.

Non ho trovato nessun difetto. Le due ambiguità A1 e A2 sono decise dall'utente e la SPEC le riporta:
- **A1 (aree).** Il comportamento deciso è verificato dalla fase A.
- **A2 (`updated_at`).** Resta così, non è un difetto: T03 continua a ignorare `updated_at` e lo registra a parte.

Ho visto solo i nomi dei file e il diff di SPEC e `views.html`. Leggo i file di lingua solo per i testi attesi.

| Suite su `7560117` | development | production |
| --- | ---: | ---: |
| S1 (`verify-s1.php`, fasi N, Q, Y, **A**, T, D, O, W, B, G; 141 controlli) | **141/141** | **141/141** |
| V8b, fasi L, M, P, I, G (M nella copia adattata, vedi sotto) | 116/116, 1 AMB (LA1, superata) | 116/116, 1 AMB |
| M ripetuta da sola (O3) | 3 giri, 35/35 ciascuno | 3 giri, 35/35 ciascuno |

Uscite:
- `verify-s1.7560117.{development,production}.output.txt`;
- `verify-s1.v8b.7560117.{development,production}.output.txt`;
- `verify-s1.v8b.7560117.*.M-rerun*.output.txt`;
- prova di sensibilità della fase A su `f40170e`: `verify-s1.d665818.sensitivity-f40170e.output.txt`.

### Nomi delle aree (fase A, nuova)

Dati di prova:
- un'area dell'applicazione tradotta come dice `views.html` (`articles`: «Articles» / «Articoli», in `app/Language/{en,it}/RoleWardenData.php`);
- un'area con markup nella traduzione italiana (`marked`);
- un'area senza nessuna traduzione (`warehouse`);
- un visualizzatore con `roles.view`, `users.view` e `permissions.view`, per le matrici in sola lettura.

| Controllo | Esito |
| --- | --- |
| A00: `areas.{area}` presenti in inglese e in italiano per le stesse 9 aree (modulo e demo) | PASS |
| A01-it, A02: pagina Permissions in italiano, ogni titolo è il nome italiano (14 aree); nessun nome inglese dei file di dati. «Admin» e «Beta», aree di Shield dell'app di prova senza traduzione, e «Warehouse» sono lo slug con l'iniziale maiuscola, come vuole la SPEC | PASS |
| A01-en: in inglese i titoli sono come prima (Users, Roles…; Articles; Warehouse) | PASS |
| A03-it, A03-en: righe di quattro matrici in Chrome (ruolo e utente, modificabili per il proprietario e in sola lettura per il visualizzatore), 60 righe per lingua | PASS |
| A04: markup nel nome dell'area (traduzione dell'applicazione) escapato nel titolo e nella matrice | PASS |
| B01: installazione pulita identica a `b1cca14` | PASS |
| T00, T00b, T01: chiavi en/it allineate, testo salvato uguale al seed, nessun testo seminato inglese nelle pagine italiane | PASS |

Dettagli di A03:
- Il nome dell'area è nella lingua di chi legge, e quelle senza traduzione mostrano lo slug con l'iniziale maiuscola.
- Lo slug `area.*` resta invariato.
- L'aria-label della casella di riga usa lo stesso nome: «Concedi ogni azione in Attività» / «Grant every action on Activity».
- Le matrici in sola lettura non hanno la casella di riga, quindi l'aria-label c'è solo in quelle modificabili.

**Sensibilità.** La fase A su `f40170e` (aree non tradotte) fallisce come atteso: A00, A01-it, A03-it, A03-en e A04.

### Strumenti adattati alla decisione A1 (aspettative superate, non difetti)

- **Fase M di V8b.** Leggeva l'area di una riga dal nome mostrato. Con i nomi tradotti, anche in inglese («Users»), non trovava più le celle: sul primo giro 18 FAIL in ciascun ambiente, conservati come `verify-s1.v8b.7560117.unadapted-M.*`. La copia `verify-s1.phase-m.php` legge l'area dallo slug (`users.*`) e non cambia nient'altro.
- **Fase T.**
  - T00b non cerca più nel database i nomi delle aree: sono solo etichette e non vengono salvati.
  - In T01 i nomi delle aree si confrontano come stringhe intere e non come sottostringhe: «Security» compariva come sottostringa nel testo della demo.
  - Prima di questa correzione T00b e T01 fallivano sul giro d665818 interrotto.

### O3 — M11

M11 passa in 8 giri su 8 con la fase M adattata: 4 in development e 4 in production, cioè quello della suite V8b più 3 ripetizioni per ambiente. Su `f40170e` era fallito una volta su 4 giri. Non si è più ripetuto: resta un'osservazione da provare a mano in un browser vero, come già detto.

### Note sull'esecuzione

- **Giro d665818 interrotto.** Si è interrotto con la fine della sessione precedente: Docker spento e l'esecuzione di production a metà.
  - Ho ripristinato `rolewarden_test` dallo snapshot salvato in quel giro (hash `f7f37c04…e21e64`, registrato in `verify-s1.environment.txt`) e svuotato Mailpit.
  - Ho cancellato i `.env` della cartella di lavoro, togliendo prima le junction.
  - Le uscite parziali sono rinominate `verify-s1.d665818.*.interrupted.*`.
- **Docker.** Ho riavviato Docker Desktop. `rolewarden-db` e `rolewarden-mail` sono ripartiti da soli; anche `scolibro_*` e `qrart-mysql` ripartono per la loro politica di riavvio, e non li ho toccati.
- **Primo giro S1 in development.** Ha dato due FAIL dell'ambiente, a macchina appena riavviata:
  - Y00-it: il caricamento del login di Chrome è andato in timeout;
  - Y01-en-1280: sulla pagina demo la misura del gutter ha letto 0 px.

  Il secondo giro è 141/141. Il primo è conservato come `verify-s1.7560117.development.round1.*`.

### Pulizia

- **Database e Mailpit.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` in tutte le 15 esecuzioni su `7560117` e a mano alla fine. Mailpit vuoto.
- **Processi.** Nessun processo mio attivo.
- **File.** Nessun `.env` rimasto.
- **Da cancellare a mano.** In `scratchpad/` restano altri profili Chrome non cancellabili: `s1d-development`, `s1g-*` e `s1h-*`.

Nessun commit, nessun push.

---

## Riverifica f40170e — 2026-10-16, Collaudatore ad Hoc (Claude), commit `f40170e` (main, locale; comprende `a396354`)

**Esito: PASS. S1 approvata per il collaudo.**
- D2b è chiuso: Y07 passa a tutte le larghezze, in tutte e due le lingue.
- Il nuovo link per uscire funziona su tutte le pagine.
- Non resta nessun difetto aperto. A1 e A2 restano ambiguità per l'utente e non le conto.

Del commit ho visto solo i nomi dei file (`git diff --stat`) e il diff del design system, oltre al CSS e all'HTML serviti.
- Il design system ora dice soltanto: «Below 1440px the page never scrolls sideways: the content column stops at the window and anything wider scrolls inside it. The permission matrix widths are what its columns get when they fit; with more actions than fit, the columns shrink to their content».
- Su questo testo ho riscritto Y07. Le larghezze delle colonne a margine sono ora solo informazione.

| Suite su `f40170e` | development | production |
| --- | ---: | ---: |
| S1 (`verify-s1.php`, fasi N, Q, Y, T, D, O, W, B, G; 134 controlli con la nuova fase O) | **134/134** | **134/134** |
| V8b, fasi L, M, P, I, G | 116/116, 1 AMB (LA1, superata) | 115/116, 1 AMB: M11 una volta, poi 2 giri su 2 PASS (O3) |

Uscite:
- `verify-s1.f40170e.{development,production}.output.txt`;
- `verify-s1.v8b.f40170e.{development,production}.output.txt`;
- `verify-s1.v8b.f40170e.production.M-rerun{1,2}.output.txt`;
- screenshot del focus `verify-s1.signout-focus-1280.png`.

### Y07

**Y07, a 1280, 1366, 1440 e 1920 px, in inglese e in italiano: PASS.** Verifica due cose:
- nessuna intestazione d'azione della matrice è più stretta del proprio contenuto, sia nella matrice del modulo sia in quella a 27 colonne della fase Y;
- la matrice o sta nel suo riquadro o scorre dentro di esso.

Restano verdi anche:
- Y03: nessuno scorrimento orizzontale della pagina su 20 schermate;
- Y06: ciò che è più largo scorre dentro la colonna e si raggiunge fino in fondo;
- Y01, Y02 e Y05.

### Link per uscire (fase O, nuova)

| Controllo | Esito |
| --- | --- |
| O01: «Sign out» nella barra in alto di tutte le 15 pagine del pannello, una volta per pagina, per il super admin | PASS |
| O02: punta al logout di Shield dell'applicazione (`…/index.php/logout`), uguale ovunque | PASS |
| O03: c'è anche per un utente senza permessi, su profilo e sessioni | PASS |
| O04: in italiano «Esci», dal file di lingua, su tutte le 15 pagine, mai «Sign out» | PASS |
| O05: dopo il link una pagina del pannello rimanda al login | PASS |
| O05b: anche la demo è chiusa | PASS |
| O06, Chrome a 1280 px: il link prende il focus, risponde a `:focus-visible` e cambia aspetto | PASS: contorno da `none` a `solid 2px` nel colore d'accento |
| O07: a 1280 px nessuno scorrimento orizzontale con il link (utenti, ruolo, Permissions, Settings; Y03 copre le 20 schermate) | PASS |
| O08, Chrome: il clic esce e il pannello apre il login | PASS |
| G01-G03: log a soglia 9 senza WARNING/ERROR/CRITICAL, nessun warning PHP, nessun 500/SQL/errore a schermo | PASS |

### Osservazioni (non contate)

- **O3 — M11 di V8b, una volta in production.**
  - Dopo il salvataggio della matrice, Kid M non aveva `users.delete`, anche se M10 aveva visto la cella concessa nella pagina prima del Save.
  - Non si è ripetuto in due nuovi giri di M in production, né in development, né nei 6 giri precedenti su `30de388` e `79a0cda`.
  - Lo strumento clicca Save 300 ms dopo la casella di colonna: può essere tempismo dello strumento o una corsa reale nel salvataggio parziale. Il dato serve all'autore, che può provarla in un browser vero cliccando subito Save dopo una casella di colonna.
  - Per un limite dello strumento il JSON della matrice di quel giro non è stato conservato: i file con percorso fisso delle fasi V8b finiscono nella cartella di lavoro.
- **O4.** L'uscita è un link GET verso il logout di Shield, come lo prevede Shield. Un link o un'immagine di un altro sito può quindi far uscire l'utente: è il comportamento standard di Shield, non del modulo.

### Pulizia della riverifica

- **Database e Mailpit.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` in tutte le 7 esecuzioni su `f40170e` registrate in `verify-s1.environment.txt` e a mano alla fine. Mailpit vuoto.
- **Processi.** Nessun processo mio attivo.
- **File.** `.env` cancellati, giro di prova `ftry1` tolto.
- **Da cancellare a mano.** In `scratchpad/` restano altri profili Chrome non cancellabili: `s1f-development`, `s1f-production`, `s1f-v8b-*` e `s1f-m1`/`s1f-m2`.

Nessun commit, nessun push.

---

## Riverifica 79a0cda — 2026-10-16, Collaudatore ad Hoc (Claude), commit `79a0cda` (main, locale)

**Esito: FAIL. S1 non approvata.**
- **D1 corretto.** Tutti i casi chiesti passano, in development e in production.
- **D2 corretto per metà.** La parte della matrice passa: le colonne si stringono al contenuto e restano leggibili. La regola nuova delle colonne a margine invece non ha effetto: «marginal columns tighten to 160px» sotto i 1440 px.

Mandato e regole sono quelli del primo giro. Del commit ho visto solo i nomi dei file (`git diff --stat`) e il diff di SPEC e design system; nessun codice. Le ambiguità A1 e A2 restano all'utente e non le conto.

| Suite su `79a0cda` | development | production |
| --- | ---: | ---: |
| S1 (`verify-s1.php`, fasi N, Q, Y, T, D, W, B, G; ora 125 controlli) | 121/125 | 121/125 |
| V8b, fasi L, M, P, I, G | 116/116, 1 AMB (LA1, superata) | 116/116, 1 AMB |
| V8, fasi A-D, F, G (una sola esecuzione: l'ambiente lo sceglie la suite, che prova anche production nelle fasi C ed E) | 195/195, 2 AMB (A15c e G03, decise; vedi primo giro) | |
| V6 | 151/151 | 151/151 |

**I 4 FAIL**, uguali nei due ambienti, sono Y07-en-1280, Y07-en-1366, Y07-it-1280 e Y07-it-1366: è D2b, sotto.

Uscite:
- `verify-s1.79a0cda.{development,production}.output.txt`;
- `verify-s1.v8b.79a0cda.*.output.txt`;
- `verify-s1.v8.79a0cda.output.txt`;
- `verify-s1.regression.v6.*.79a0cda.console.txt`.

### D1 e i controlli collegati

Lo strumento della fase N è stato toccato in un solo punto: il giro di email del passo `['it']` ora usa un utente suo (`enm@s1.test`). Prima usava l'utente che sceglie `en`, che restava bloccato per i fallimenti e falsava N10b, non il prodotto.

| Controllo | Esito |
| --- | --- |
| N10, N10b, N12: con `[]` dopo la creazione della classe, scelte salvate prima valide (pannello ed email) | **PASS** |
| Q01, Q02: lingua e chiaro/scuro scelti senza classe, poi classe aggiunta (`$extraThemes`) | **PASS** |
| Q04 (nuovo): caso inverso, scelte fatte con la classe, poi classe tolta | **PASS** |
| Q04b (nuovo): l'utente di Q01, a classe tolta di nuovo | **PASS** |
| Q05 (nuovo): stessa chiave sotto le due classi (`en`/chiaro sotto `RoleWarden\Config\RoleWarden`, `it`/scuro sotto `Config\RoleWarden`), classe presente | **PASS**: vince la classe corrente, nessun errore, resta una riga per chiave |
| Q05b (nuovo): poi la classe viene tolta | **PASS**: resta il valore che era corrente (`it`, scuro), quello vecchio non torna |
| Q06a/Q06b (nuovi): `lockAttempts` a 3 dalla schermata Settings, applicato al login | **PASS**: con il default 2 fallimenti non bloccano; dopo il salvataggio 3 fallimenti bloccano |
| Q07/Q07b (nuovi): classe aggiunta con `public int $lockAttempts = 9;` | **PASS**: la schermata mostra ancora 3 (riga passata a `Config\RoleWarden`) e 3 fallimenti bloccano ancora |

Nota di strumento per Q06. La schermata rifiuta il `signInRate = 1000` che le copie del collaudo hanno nel `.env`: il limite è 100. Il salvataggio di prova invia quindi 100, e alla fine della fase tutte le impostazioni globali scritte vengono tolte. Non è un difetto: è la validazione della schermata.

### Y07 riscritto e D2b

Il nuovo Y07 si basa sul design system di `79a0cda`: «Below 1440px … marginal columns tighten to 160px. The permission matrix widths are what its columns get when they fit; with more actions than fit, the columns shrink to their content». Verifica, a ogni larghezza e in tutte e due le lingue:
- **Matrice.** Nessuna intestazione d'azione è più stretta del proprio contenuto, e la matrice o sta nel riquadro o scorre dentro di esso: **PASS** ovunque, anche con le 27 colonne della fase Y.
- **Colonne a margine (`.rw-anno`).** Sotto i 1440 px devono essere larghe 160 px: **FAIL**.

**D2b — minore. Sotto i 1440 px le colonne a margine non si stringono a 160 px** (Y07 a 1280 e 1366).

| Pagina | 1280 | 1366 | 1440 |
| --- | ---: | ---: | ---: |
| Elenco utenti, Activity, elenco ruoli | 200 | 200 | 200-204 |
| Permissions (12 tabelle) | 240 | 240 | 240 |
| Pagina utente | 140 / 172 | 165 / 187 | 187 / 199 |
| Settings | 179-196 | 200 | 200 |
| Profilo, Sessioni, Aspetto, Import | 162-189 | 182-200 | 193-200 |

- Nessuna colonna vale 160 px.
- Dove vale meno di 200 è la tabella che si stringe da sola, come a 1440, non la regola.
- Il CSS servito ha la regola `@media (max-width:1439px){.rw-anno{width:160px}}`. Le regole per pagina (`.rw-t-users .rw-c-login+.rw-anno`, `.rw-t-roles .rw-anno`, `.rw-t-permissions .rw-anno`, `.rw-settings .rw-anno`) però sembrano prevalere.
- Effetto visibile nullo: nessuno scorrimento della pagina (Y03), contenuto raggiungibile (Y06).
- Se 160 px valeva solo per le colonne a margine senza larghezza propria, è la frase del design system da precisare: lo decide l'autore.

### Pulizia della riverifica

- **Database e Mailpit.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` in tutte le 12 esecuzioni su `79a0cda` registrate in `verify-s1.environment.txt` e a mano alla fine. Mailpit vuoto.
- **Processi.** Nessun processo mio attivo.
- **File.** I `.env` sono cancellati e le esecuzioni di messa a punto (`rtry*`) tolte.
- **Da cancellare a mano.** In `scratchpad/` restano altri profili Chrome che Windows non lascia cancellare: `s1r-development`, `s1r-production`, `s1r-v8b-*` e `s1r-reg/reg-v6-*`.

Nessun commit, nessun push.

---

## Collaudo — 2026-10-16, Collaudatore ad Hoc (Claude), commit `30de388` (main, locale; baseline `b1cca14`, V8b PASS)

**Esito: FAIL. S1 non approvata.** Due difetti:

- **D1, medio, già presente in `b1cca14` ma ora sul percorso documentato.** Appena l'applicazione crea `app/Config/RoleWarden.php`, come la documentazione chiede per `$languages`, le scelte già salvate dagli utenti non contano più: lingua e chiaro/scuro.
- **D2, minore.** Le colonne della matrice non hanno le misure dichiarate (azioni 80 px e note 200 px sotto i 1440 px; 104 px a 1440 e oltre).

Tutto il resto delle quattro modifiche passa.
- Una lingua sola con `['en']` e con `['it']`: niente selettore, pagine ed email nella lingua imposta, POST ignorata.
- Nessuno scorrimento orizzontale a 1280, 1366, 1440 e 1920 px, anche con dati larghi.
- Ruoli e permessi seminati tradotti ovunque. Testo dell'admin mostrato com'è scritto. Salvataggio senza modifiche che non rinomina. Seed identico a `b1cca14`.
- Voci di log degli utenti veri conservate dopo `demo:uninstall`.

La regressione dà solo i fallimenti già noti.

Due ambiguità di specifica, separate e non decise: **A1** (titoli delle aree) e **A2** (`updated_at` toccato da un salvataggio senza modifiche).

| Suite | development | production |
| --- | ---: | ---: |
| S1 (`verify-s1.php`, fasi N, Q, Y, T, D, W, B, G) | 104/113 | 104/113 |
| V8b, fasi L, M, P, I, G (Y sostituita dalla Y di S1, a quattro larghezze) | 116/116, 1 AMB (LA1, superata) | 116/116, 1 AMB |
| V8 (`verify-v8.php`, fasi A-D, F, G; l'ambiente lo sceglie la suite) | 195/195, 2 AMB (A15c e G03, ora decise: vedi sotto) | |
| V7 adattata (`verify-v8b.v7adapted.php`) su zip sostitutivi a `30de388` | 181/181, 1 AMB (A1 di V7, ridotta ad A1 di S1) | |
| V6 | 151/151 | 151/151 |
| V5 | 176/177: solo G07v5, noto | 176/177: G07v5 |
| V4 | 207/232: i 25 FAIL noti della data ISO, gli stessi controlli di V8b | 207/232: gli stessi 25 |
| V2 `test` / `extra` / `adapt` | 150/151 (G07, noto) + 41 + 16 | 168/169 (G07, noto) + 54 + 16 |
| V3 principale / supplementare / filtri array | 139 + 81 + 55 | 139 + 81 + 55 |

**I 9 FAIL di S1**, uguali nei due ambienti:
- N10, N10b, N12, Q01 e Q02 sono D1;
- Y07-1280, Y07-1366, Y07-1440 e Y07-1920 sono D2.

Uscite:
- suite S1: `verify-s1.30de388.{development,production}.output.txt`, misure in `verify-s1.30de388.*.layout.json`, screenshot `verify-s1.layout-*.png`;
- fasi V8b: `verify-s1.v8b.30de388.{development,production}.output.txt`;
- suite V8: `verify-s1.v8.30de388.output.txt`;
- V7: `verify-s1.v7.30de388.standin.adapted.output.txt`;
- regressione: `verify-s1.regression.*.30de388.console.txt` e `verify-s1.{v2..v6}.*.30de388.output.txt`;
- prove: `verify-s1.environment.txt`.

### Perimetro e regole

- **Letto.** Ho letto:
  - SPEC (decisioni del 2026-10-16) e BRIEF v1.0;
  - `_AI-LOG.md`, README e CHANGELOG;
  - `documentation/` (in particolare `configuration.html` e `views.html#languages`);
  - `docs/design-system/README.md` e il README di PermissionMatrix;
  - i contratti e gli script di `tests/Integration/`.

  I file di lingua (`RoleWardenData.php` del modulo e della demo, `Demo.php`, `RoleWarden.php`) li ho letti solo per i testi attesi e per la qualità della traduzione. Il resto è HTML, CSS e JS come li riceve Chrome.
- **Non aperto.** Nessun controller, model, vista PHP, seeder, `src/Settings`, `src/Models`, test unitario o diff. Dei file cambiati da `30de388` ho visto solo i nomi (`git diff --stat`). Non ho aperto il repo `../rolewarden-demo`.
- **Ambiente.**
  - Copie di `../rolewarden-app-test` nello scratchpad. Il modulo è `git archive 30de388` e la demo `git archive 30de388:demo`, agganciati come junction. La baseline è `git archive b1cca14`, con la sua demo.
  - Database solo `rolewarden_test` su 127.0.0.1:3317. Credenziali solo come variabili di processo.
  - Mailpit su 1026, indirizzi `@s1.test`, `@v8b.test` ed `@example.com`.
  - Un server PHP alla volta (`error_reporting=-1`). Chrome headless con profilo nella cartella di lavoro, chiuso per PID.
  - Una suite alla volta, senza job paralleli.
- **Zip.** `build/` è fermo a `rc.v8c` (`b1cca14`). Per la suite V7 ho costruito, nella cartella di lavoro, degli zip sostitutivi a `30de388`:
  - l'applicazione `rc.v8c` con `modules/rolewarden` e `modules/rolewarden-demo` sostituiti dai `git archive` di `30de388`, e il CHANGELOG di `30de388`;
  - il modulo = `git archive 30de388`.

  I pacchetti veri non li ho collaudati: vedi «Cosa non ho potuto provare».

### Esito per punto

| # | Punto | Esito | Controlli |
| --- | --- | --- | --- |
| 1 | Una sola lingua | **FAIL** (D1) per `[]` dopo l'aggiunta della classe; `['en']` e `['it']` PASS | N00-N12, Q01-Q02 |
| 2 | Layout sotto i 1440 px | PASS per scorrimento, raggiungibilità, numeri e intestazioni; **FAIL** minore (D2) per le misure delle colonne | Y00-Y07 |
| 3 | Traduzione dei dati seminati | PASS, più A1 e A2 | T00-T11, B01 |
| 4 | Demo | PASS | D00-D07, suite V8 A |
| 5 | Regressione | PASS (solo i fallimenti noti) | tabella sopra |
| 6 | Log | PASS | G01-G03 di S1 e di V8b |

**1. Lingue (fase N).** La classe è scritta esattamente come la mostra `views.html`: `class RoleWarden extends \RoleWarden\Config\RoleWarden { public array $languages = ['en']; }`. Il locale dell'app è cambiato in `app/Config/App.php` della copia.
- **Sensibilità, senza classe e con locale `it`.** Chi non ha scelto vede tutte le 17 pagine (pannello e demo) in italiano, con il selettore. Due utenti scelgono `it` ed `en`, e anche il proprietario sceglie `it` (N01, N01b).
- **`['en']`, locale `it`** (N02-N06b):
  - il proprietario, che aveva scelto l'italiano, vede tutte le 17 pagine in inglese (`lang="en"`, h1 inglesi);
  - nessun controllo della lingua né nella barra in alto né nel profilo, e il profilo mantiene gli altri form;
  - anche chi non ha mai scelto e chi aveva scelto `it` vede l'inglese;
  - la POST `language=it`, costruita a mano anche con il campo `back`, viene ignorata: redirect, nessun errore, nulla salvato;
  - le email di sicurezza all'utente che aveva scelto `it` sono in inglese: password cambiata e troppi accessi falliti. Lo sono anche quelle ai destinatari degli allarmi.
- **`['it']`, locale `en`** (N07-N09b): tutto in italiano anche per chi aveva scelto `en`. Anche le email sono in italiano. La POST `en` viene ignorata.
- **`[]`, comportamento di prima** (N10-N12):
  - il selettore EN/IT è tornato su ogni pagina e la lista del profilo offre «come l'applicazione», `en` e `it` (N10, parte che passa);
  - le scelte **salvate prima** di creare la classe sono ignorate, e da qui i FAIL N10, N10b e N12. Il proprietario e l'utente `it` vedono l'inglese e ricevono email in inglese; l'utente `en`, che sotto `['it']` era in italiano, resta in italiano;
  - una scelta fatta dopo la creazione della classe funziona (N11).

  È D1.
- **Q (riferimento).** Lo stesso succede creando la classe per un'altra opzione documentata (`$extraThemes`), e vale anche per il chiaro/scuro. Su `b1cca14` avviene allo stesso modo: il difetto è già presente.

**2. Layout (fase Y, Chrome headless).**
- **Dati di prova.** Un'area `warehouse-operations` con 12 azioni dai nomi lunghi (la matrice passa a 27 colonne), un ruolo dal nome lungo, 26 utenti con indirizzi lunghi (due pagine), un utente con 4 ruoli e 14 override, 30 voci di attività lunghe.
- **Schermate.** 20 schermate a 1280, 1366, 1440 e 1920 px, in inglese e in italiano.
- **Y03.** Nessuno scorrimento orizzontale né di `html` né di `body`, e `main` non supera mai la finestra. Su `b1cca14` lo stesso ruolo admin scorreva di 186, 100 e 26 px a 1280, 1366 e 1440 (fase W).
- **Y06.** Tutto ciò che è più largo della colonna sta in un riquadro che scorre dentro di essa, fino a 6 per larghezza. Ogni riquadro arriva in fondo e l'ultima colonna è visibile.
- **Y01, Y02.** Numeri di riga larghi un gutter in 33 tabelle e mai tagliati.
- **Y05.** Intestazioni della matrice dentro la propria colonna, sia nella matrice standard sia in quella a 27 colonne.
- **Y07.** È D2.

**3. Traduzione (fasi T e B).**
- **T00, T00b.** I file di dati hanno le stesse chiavi in inglese e in italiano (37, modulo più demo). Il database contiene esattamente il testo inglese dei file.
- **T01.** Lettore italiano: nomi, descrizioni e permessi seminati sono in italiano, e non resta nessun testo seminato in inglese, nemmeno nei dati JSON della pagina. Le pagine viste:
  - elenco ruoli, pagine dei ruoli admin e Lettore;
  - Permissions, con «Concesso da: Amministratore»;
  - elenco utenti e pagine utente;
  - Settings (ruolo predefinito);
  - moduli di modifica e di creazione del ruolo;
  - Activity;
  - barra vetrina della demo con Lettore, Autore e Approvatore (solo in development, perché la vetrina in production è spenta).
- **T01b-T02.** La colonna della demo si chiama «Proprietario». In inglese tutto è come prima.
- **T03.** Moduli di modifica di admin, user e demo-reader salvati in italiano senza toccare nulla: nome, descrizione, permessi e assegnazioni restano invariati e non c'è nessuna voce `role.*`. Si aggiorna solo `updated_at`: è A2.
- **T04, T05.** Cambiata solo la descrizione in italiano: la descrizione è salvata così com'è e il nome resta «Admin». La nuova descrizione appare uguale nelle due lingue, il nome no («Amministratore» / «Admin»).
- **T06-T08.** I nomi scritti dall'admin («Member S1», «Lettori scelti») appaiono come sono in tutte e due le lingue. La descrizione non toccata resta tradotta. Riportando il nome al testo del seed («User») torna «Utente».
- **T09.** Il registro conserva le etichette del momento.
- **T10, T10b.** Ruolo e permesso dell'applicazione tradotti come dice `views.html` («Redattore», «Pubblicare articoli»); in inglese restano «Editor».
- **T11.** Il markup è escapato in tutte e due le lingue su 9 pagine. Viene da tre fonti: un nome e una descrizione scritti dall'admin, e una traduzione dell'applicazione.
- **B01.** Un'installazione pulita, seed del modulo più `demo:install`, è identica a `b1cca14`: ruoli, permessi, concessioni, impostazioni, documenti e account.
- **Qualità della traduzione.** Buona e coerente con il resto del pannello: «log attività», «Super admin» lasciato com'è, come nel resto del file.

**4. Demo (fase D e suite V8).**
- **Lavoro sugli oggetti della demo.** Dopo un `demo:uninstall` e un `demo:install` di nuovo puliti, il proprietario vero:
  - modifica il ruolo Lettore;
  - disattiva e riattiva l'approvatore;
  - dà il ruolo Autore a un utente vero.

  L'autore della demo scrive un documento.
- **Dopo la rimozione.**
  - Restano invariate le voci scritte dal proprietario vero sul ruolo demo e sull'assegnazione (D04).
  - Spariscono quelle che hanno un account della demo come soggetto (D05).
  - Il resto del database è come prima dell'installazione, a parte ciò che hanno fatto gli account veri: accessi, rehash della password, sessioni, ultima attività, data di potatura (D06-D06c).
  - La demo si reinstalla (D07).
- **Suite V8.**
  - A15/A15b: nessun residuo.
  - A15c («righe del proprietario sugli oggetti della demo restano», 4 righe) ora è il comportamento previsto dalla decisione del 2026-10-16.
  - G03 (dati della demo in inglese) è superata: T01 lo mostra tradotto.
  - G01, nessun testo inglese nella demo italiana, passa con «Proprietario».

**5. Regressione.**
- **Fallimenti noti, non ricontati:**
  - G07 di V2;
  - G07v5 (i bottoni EN/IT del selettore);
  - i 25 controlli V4 sulla data ISO, esattamente gli stessi di V8b `0943a60` (confronto per id).
- **Rivalutati per la decisione 3.**
  - Il selettore di V8b ancora presente con `[]`: LA1 è risolta da `$languages` e coperta dalla fase N.
  - La A1 di V7 ora segnala solo «Super admin», identico di proposito in italiano, e i titoli delle aree: è A1 di S1.
- **Giri ripetuti per lo strumento.** Il primo giro di V5 e V4 è fallito per lo strumento, non per il prodotto: con la cartella di lavoro nello scratchpad `verify-v5.css.py` e le catture `c0ab8fc` di V4 vengono cercate nella cartella superiore. Le ho copiate lì e ho rieseguito le due suite. Le uscite del primo giro sono `verify-s1.*.round1-instrument-path.*`.

**6. Log.**
- Nelle due esecuzioni S1, nelle due V8b e nella suite V8, a soglia 9: nessuna riga WARNING, ERROR o CRITICAL oltre ai rifiuti CSRF provocati.
- Nessun warning PHP nel server.
- Nessun 500, testo SQL o errore PHP a schermo: 308 risposte in S1, più quelle di V8b e V8.

### Difetti

**D1 — medio, già presente in `b1cca14`. Creando `app/Config/RoleWarden.php` si perdono le scelte di lingua e di chiaro/scuro salvate prima** (N10, N10b, N12, Q01, Q02).

Riproduzione:
1. Applicazione senza `app/Config/RoleWarden.php`. Un utente sceglie Italiano dal profilo e Scuro dalla barra in alto.
2. Creare la classe come in `views.html` (`public array $languages = [];`) oppure come in `configuration.html` (`public array $extraThemes = ['ocean' => 'Ocean'];`).
3. L'utente si ricollega.

Osservato:
- Il pannello è in inglese e chiaro. Le sue email di sicurezza arrivano in inglese.
- Nella tabella `settings` le righe ci sono ancora, con classe `RoleWarden\Config\RoleWarden`. Una nuova scelta fatta dopo viene salvata con classe `Config\RoleWarden`.
- Togliendo poi la classe si perderebbero, allo stesso modo, le scelte fatte con la classe presente.
- Su `b1cca14` succede lo stesso (fase Q). Ora però creare la classe è il modo documentato di avere una sola lingua, e il caso `[]` («comportamento di prima») non tiene le scelte degli utenti di un'installazione esistente.

**D2 — minore. Le colonne della matrice non hanno le misure dichiarate** (Y07).

Il design system dice «the permission matrix tightens to 80px action columns and a 200px marginal column» sotto i 1440 px. PermissionMatrix dice 104 px per le azioni sulla griglia di 1440. La SPEC ripete «azioni 80 px, note 200 px». Misure con i soli dati del modulo (15 colonne azione), ruoli demo-approver e admin:

| Larghezza | Colonne azione | Colonna note | Tabella / riquadro |
| --- | --- | --- | --- |
| 1280 | 44-71 px | 145-159 px | 1194-1208 / 1016 px (scorre dentro) |
| 1366 | 44-71 px | 145-159 px | 1194-1208 / 1102 px |
| 1440 | 44-71 px | 153-167 px | 1202-1216 / 1176 px |
| 1920 | 71-86 px | 204-211 px | 1656 / 1656 px |

- Le larghezze sono quelle del contenuto e non cambiano attraversando i 1440 px.
- Su `b1cca14` erano identiche (fase W): è cambiato solo il contenitore che scorre.
- L'effetto visibile è nullo: intestazioni dentro le colonne (Y05), nessuno scorrimento della pagina.
- Se le misure scritte sono solo indicative, il controllo è da riscrivere e il difetto cade: lo decide l'autore.

### Ambiguità di specifica (non decise)

- **A1 — Titoli delle aree in inglese nella pagina Permissions in italiano.**
  - Le sezioni si chiamano «Activity», «Admin», «Appearance», «Beta», «Documents», «Permissions», «Roles», «Security», «Sessions», «Settings», «Users»: lo slug dell'area con l'iniziale maiuscola.
  - La decisione 3 parla di «nome e descrizione» di ruoli e permessi. I file di dati non hanno una chiave per l'area.
  - È ciò che resta della A1 di V7.
- **A2 — Un salvataggio senza modifiche aggiorna `updated_at`.**
  - Vale in italiano e anche in inglese: la pagina del ruolo mostra un nuovo «Ultimo salvataggio» anche se nulla è cambiato.
  - La SPEC chiede che il ruolo non venga rinominato, e così è. Il mandato del collaudo diceva «database invariato».

### Osservazioni (non contate)

- **O1.** Nelle email delle fasi N non è mai arrivata quella di «nuovo accesso»: con un solo browser e un solo indirizzo è coerente. La lingua è verificata su «password cambiata» e «troppi accessi falliti», compresi i destinatari degli allarmi.
- **O2.** Nella barra in alto il ruolo dell'utente appare come «Super-admin», cioè lo slug, in tutte e due le lingue: è così anche in `b1cca14`.

### Cosa non ho potuto provare

- **Controllo visivo in un browser vero**, dovuto all'autore. Qui c'è solo Chrome headless con misure e screenshot (`verify-s1.layout-*.png`, quelli dell'ultima esecuzione, production).
- **I pacchetti veri.** Fasi P ed E di V8 e pacchetti V7: `build/` è fermo a `rc.v8c`. La suite V7 è girata sugli zip sostitutivi descritti sopra.
- **Altro.** Altre versioni di PHP (solo 8.3.11) e di CI 4.x; Apache, sottocartella e reverse proxy; staging e demo online.

### Pulizia

- **Database e Mailpit.** `rolewarden_test` uguale allo snapshot `f7f37c04…e21e64` dopo tutte le 42 esecuzioni registrate in `verify-s1.environment.txt`, senza nessuna differenza, e di nuovo a mano alla fine. Mailpit vuoto.
- **Processi.** Nessun processo php, python, node o Chrome mio attivo (verificato per riga di comando).
- **File.**
  - I `.env` temporanei sono cancellati: nessuno resta nelle cartelle di lavoro.
  - Cancellati anche le copie temporanee degli input di V4 e V5, gli script di supporto, il probe esplorativo e le esecuzioni di messa a punto.
- **Da cancellare a mano.** In `scratchpad/` restano solo profili Chrome che Windows rifiuta di cancellare: `s1w-dev`, `s1w-prod`, `s1w-try5`, `s1w-v8b-development`, `s1w-v8b-production`, `reg-v5-*` e `reg-v6-*`.

Nessun commit, nessun push.
