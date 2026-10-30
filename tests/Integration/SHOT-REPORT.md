# SHOT - collaudo del commit 782fb64 (panel polish before the CodeCanyon screenshots)

Collaudatore ad Hoc (Claude, subagente). Commit `782fb64`, padre `603a94c`. Collaudo in nero, iniziato il 2026-10-28
e ripreso il 2026-10-29 (u1a si era interrotto alle 23:12 senza riga `exit=`). Fonti delle asserzioni: la definizione
di fatto del prompt di lancio (7 comportamenti), `docs/design-system/`, `CHANGELOG.md`, la regressione 1.1 (U1-U5,
MOTION). Nessun file di `src/`, `resources/` o dei test unitari letto. Database: solo `rolewarden_test`.

## Esito

**PASS con riserva: nessun difetto del modulo trovato. Non approvabile senza due decisioni di chi lancia.**

1. **V-7 resta FAIL in modo deterministico** (5 controlli, tutti i run). Il percorso di "Saved in" esce di 2-11 px dal
   margine destro della page head. La definizione di fatto non copre il caso: la questione è riportata tra le
   ambiguità, non tra i difetti.
2. **Ambiente degradato nella sessione del 29.** L'host Windows ha esaurito i buffer dei socket: Chrome riporta
   `net::ERR_NO_BUFFER_SPACE`, il log dell'app WSAENOBUFS ("Spazio nel buffer insufficiente o coda piena") alla
   connessione MySQL. Ogni FAIL di motion (r2, r3, r4) e di shot.b (r3, r4), V-7 escluso, deriva da una risorsa non
   caricata: `panel.js`, `alpine.min.js`, `panel.css` o un font. Ne seguono `rwSearch is not defined` e simili, e
   l'evidenziazione della barra laterale che non si anima.
   - Ogni controllo di motion è PASS in almeno uno dei tre run (137/137 sull'unione).
   - MX-F11 e A11Y-5 di shot.b sono PASS in r2 (28/10, ambiente pulito). In r4 falliscono solo per voci
     `ERR_NO_BUFFER_SPACE` su favicon e font.
   - Chi lancia decide se accettare questa unione dei run, oppure far ripetere motion e shot.b dopo un riavvio di
     Windows.

## Step e run validi

| Step | Fasi | Run valido | Risultato | Note |
|---|---|---|---|---|
| shot.parent | SHOT (solo misure, 603a94c) | r2 | 14/14 PASS | r1 12/14: difetti dello strumento, corretti |
| shot.a | SHOT parte a | r2 | 16/16 PASS | |
| shot.b | SHOT parte b (controlli) | r4 | 103/110 | 5 FAIL V-7 (ambiguità), 2 FAIL ambiente (MX-F11, A11Y-5: PASS in r2) |
| x | SHOTX development | development | 2/2 PASS | |
| dash | DASH (U4) | r1 | 256/256 PASS | |
| cust | CUST (U5) | r1 | 152/152 PASS | |
| u2 | CHK, ST | r3 | 129/129 PASS | r1/r2 difetti dello strumento (selettore del verdetto, stringhe LANG) |
| u1a | NAV, RAIL, STYLE, ESC | r2 (29/10) | 196/196 PASS | r1 interrotto il 28/10 alle 23:12 |
| u1b | NAV, CNT, TODAY, LANG | r2 | 103/103 PASS | r1 errore dello strumento (NAV non eseguito per primo) |
| u3 | SRCH | r2 (29/10) | 41/41 PASS | durata 13 min 42 s |
| motion | MOTION | r2, r3, r4 (29/10) | 129, 133, 133 su 139; unione 137/137 PASS | tutti i FAIL da `ERR_NO_BUFFER_SPACE`/WSAENOBUFS (vedi sopra); durata ~23 min per run |

Gli step u3 e motion superano i 10 minuti: lo strumento non li divide in parti, come già succedeva nei collaudi U3 e
MOTION (13 e 25 min). Non sono stati interrotti, perché un'interruzione non avrebbe dato alcun esito.

Gli output stanno in `verify-shot.s1.<step>.<commit>[-rN].output.txt`, le console in `verify-shot.console.*` e la
cronologia in `verify-shot.progress.txt`. Le prove di ripristino stanno in `verify-shot.environment.txt`.

## Correzioni dello strumento

| Quando | File | Correzione | Motivo |
|---|---|---|---|
| 28/10 | `verify-shot.phase-shot.php` | Verdetto letto con fallback, righe nascoste escluse, K-7/A11Y-3/DB-3 corretti | r1 di shot.parent/shot.b: lo strumento misurava elementi sbagliati |
| 28/10 | `verify-shot.phase-shot.php` | K-3 reso preciso: bordo e linea inset sullo stesso bordo inferiore contano come un solo indicatore di focus | La definizione di fatto chiede "focus mostrato una volta sulla riga del campo", e i due elementi formano un'unica linea |
| 28/10 (22:45) | `verify-shot.phase-shot.php` | V-7 ridefinito: il percorso confrontato con il margine destro della page head (`dlRight`) invece che con il bordo delle sezioni | Il percorso sta nella page head e non in una sezione. Questa versione è stata eseguita solo il 29 (r3, r4) e resta FAIL |
| 28/10 | `verify-shot.phase-chk.php` | Selettore del verdetto allargato; le stringhe LANG leggono anche lo span del verdetto | u2 r1/r2: il verdetto veniva letto vuoto. Da qui nasceva anche la falsa AMB-num di r1 (soggetto `null`) |
| 28/10 | `verify-shot.all.sh` | u1b esegue NAV per primo | NAV crea gli utenti con cui CNT, TODAY e LANG fanno login |
| 29/10 | `verify-shot.phase-motion.php` (nuovo) | Copia identica di `verify-motion.phase-motion.php` più una riga INFO in `mb()` che stampa gli errori di log del browser (rete). Nessun verdetto cambiato | Serviva a provare che i FAIL di motion vengono dall'ambiente (`panel.js` non caricato per `ERR_NO_BUFFER_SPACE`) e non dal modulo |

## Difetti del modulo

Nessuno.

## Ambiguità di specifica (non decise)

1. **Username numerico nella verifica di un permesso.** Il segnaposto dice "Username, email or id". Uno username
   fatto di sole cifre, uguale all'id di un altro utente, quale utente deve indicare? Il modulo osservato fa vincere
   l'id: `user="17"` indica l'utente con id 17, non l'utente 78 con username "17" (u2 r3, INFO a riga 108-109). La
   specifica non lo dice.
2. **V-7: il percorso "Saved in" in Appearance esce dal margine destro.** Misure del run r4:

   | Lingua | Viewport | Bordo destro del percorso | Margine della page head |
   |---|---|---|---|
   | en | 1440 | 1395 px | 1392 px |
   | en | 1600 | 1555 px | 1552 px |
   | en | 1920 | 1875 px | 1872 px |
   | it | 1440 | 1403 px | 1392 px |
   | it | 1600 | 1563 px | 1552 px |

   - Lo sforamento si vede a occhio in `verify-shot.commit.appearance.1440.light.it.png`: il percorso
     `writable/rolewarden/theme.json` sporge oltre "Scarica" e oltre la riga sotto la testata.
   - La definizione di fatto chiede solo che le sezioni condividano il margine destro (V-4 PASS) e che "Saved in"
     non vada a capo (V-5/V-6 PASS). Non dice se il percorso debba restare dentro il margine.
   - Chi lancia decide se è un difetto da correggere prima degli screenshot.

## Ambiente

- Alla ripresa Docker Desktop era spento: l'ho avviato io e sono ripartiti `rolewarden-db` (3317), `rolewarden-mail`
  (8026) e `rolewarden-adminer`. Li ho lasciati accesi.
- `rolewarden_test` non era vuoto, perché il run u1a del 28 non aveva fatto il ripristino. L'ho ripristinato da
  `sh-work-u1a/snapshot.sql`, che ha l'hash di riferimento.
- Hash di `rolewarden_test`:
  - prima della ripresa, dopo il ripristino: `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`;
  - prima e dopo ogni run (`identical=True`);
  - a fine collaudo: `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`.
- Mailpit vuoto prima e dopo ogni run e a fine collaudo.
- Worktree `shot/wt` (782fb64) e `shot/wt-parent` (603a94c) rimossi; `git worktree prune` eseguito; resta solo `main`.
- Nessun processo php/python/node/chrome del collaudo attivo.
  - In `netstat` restano tre socket in LISTENING su 127.0.0.1:8071 con PID 0: socket orfani del kernel, senza
    processo, che non accettano connessioni. È la stessa anomalia dello stack TCP che produce WSAENOBUFS. Li
    elimina un riavvio di Windows.
  - Durante motion r3, TIME_WAIT è arrivato al massimo a 581 (`verify-shot.netstat.motion-r3.txt`), su circa 14.300
    porte effimere disponibili. Non è quindi un esaurimento di porte.
- Nella scratch della sessione del 28 (`.../1475d35b-.../scratchpad/shot/`) restano cartelle `sh-work-*` che
  contengono solo profili Chrome. Non si possono cancellare (EPERM: ACL di un altro SID del sandbox), come in R1.
  Nessun `.env` e nessuna credenziale.
- Nessun commit. Sezione del lock di `_AI-LOG.md` non toccata.
