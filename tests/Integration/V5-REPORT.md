# V5 — Temi: collaudo indipendente

## Riverifica — 2026-10-03, Collaudatore ad Hoc (Claude), commit `95c90e7` (V5 = `d3c7dbb` + `95c90e7`)

**Esito: PASS. Suite V5: 177/177 in development e 177/177 in production, 0 FAIL, più l'ambiguità A1, registrata e non contata. Regressione V2/V3/V4: 1562/1564; i 2 FAIL sono V2 G07, già noto come superato da V5. Il suo intento è ricontrollato da G07v5, che passa. D1-D5 sono corretti. V5 approvata per il collaudo.** Le ambiguità A1-A3 restano aperte, separate e non decise. Il controllo visivo in un browser vero resta dovuto all'autore.

Lo stesso mandato, perimetro e regole del collaudo. Modulo da `git archive 95c90e7`, baseline di aggiornamento `bd7488e`, aggancio come cartella sostituibile. Nessun file vietato aperto e nessuna lettura del diff. Comando: `RW_V5_NEW=95c90e7 python tests/Integration/verify-v5.run.py <modo> <ambiente>`.

### Totali

| Suite | development | production |
| --- | ---: | ---: |
| V5 (`verify-v5.php`, 170 controlli del collaudo + 7 di riverifica) | 177/177 | 177/177 |
| Regressione V4 (baseline `3805640`) | 232/232 | 232/232 |
| Regressione V2 `test` / `extra` / `adapt` | 140/141 + 31 + 16 | 153/154 + 39 + 16 |
| Regressione V3 principale / supplementare / filtri array | 139 + 69 + 55 | 139 + 69 + 55 |
| **Totale** | **859/860** | **880/881** |

Evidenze: `verify-v5.<modo>.<ambiente>.95c90e7.*` (output, log di fase, CSS servito, audit dei token, schermate) e `verify-v5.environment.txt`.

### Difetti dichiarati corretti: verificati

**D1, corretto.** Sulla pagina così come viene servita, senza nessuna registrazione manuale:
- **personalizzatore** (W00):
  - compare il messaggio «all clear»;
  - un testo sotto AA viene segnalato e l'anteprima applica il colore scelto;
- **salvataggio dal browser** (W00b): il file di tema contiene i colori scelti, compresi quelli derivati: `light.ink #c8c8c8`, `accent #0b4f8a`, `accent-hover #094171`, `accent-tint #d9e2e9`, `on-accent #ffffff` e `dark.ink #eeeeee`, insieme a tema Contrast, raggio 8 e densità Comfortable;
- **schermate con Alpine**, controllate per nome e stato di avvio (J01-J04):
  - `rwToasts` su ogni pagina;
  - `rwSettings` nelle Impostazioni;
  - `rwMatrix` nella matrice di un ruolo;
  - `rwUserMatrix` nel dettaglio utente;
- **comportamento in pagina** (J01-J04):
  - un toast spinto da JavaScript compare nella pila;
  - cambiando un valore delle Impostazioni, la barra passa a «1 unsaved change … Discard / Save settings»;
  - la matrice espone il suo stato (`changes()`) e disegna i segni;
- **console**: nessun errore JavaScript su queste schermate (J04), sul personalizzatore (W14), sulle 14 schermate più il login nei 3 temi in chiaro e in scuro (R-*-js) e con il quarto tema (X09, X10).

**D2, corretto.**
- **CSS servito** (T05): non resta nessun valore grezzo nei gruppi dei token fuori dalle eccezioni dichiarate. Restano solo i valori esenti di geometria delle icone: segno della casella, pallino di stato, punto di modifica della matrice, icona di ricerca, ramo dell'albero.
- **Markup servito** (P11): nessuno stile inline con colori o lunghezze non nulle. Restano solo token (`color:var(--ink-faint)`, `color:var(--denied)`) e zeri o parole chiave (`border-bottom:0`, `margin:0`, `display:inline`, `white-space:nowrap`, `grid-template-columns:1fr`).

**D3, corretto.** Un raggio non valido (`8px;}*{color:red}`) non viene salvato e l'errore compare sotto il campo (C02-8). Gli altri casi di validazione restano verdi.

**D4, corretto.** Con il quarto tema dichiarato, il messaggio è «Error: Choose one of the listed themes.» (X03b).

**D5, corretto.** Il warning per un file di tema non valido nomina il file di tema, cioè `…\app\writable\rolewarden/theme.json` oppure `…\app\app\Config/RoleWarden/theme.json`, e non più un sorgente del modulo (H-LOG-PATH). Nella fase dei file ostili:
- ci sono 18 warning per 6 file non validi (3 per posizione) su 3-4 richieste ciascuno, coerenti con al più uno per richiesta;
- non c'è nessun errore PHP (H-LOG).

### Aspettative superate e strumento

- **V2 G07** resta superato da V5 (pulsanti della barra in alto) e non è stato modificato: G07v5 ne ricontrolla l'intento e passa in entrambi gli ambienti.
- **Nuovi controlli di riverifica** in `verify-v5.php`: W00b, J01-J04, X03b, H-LOG-PATH. Nessuna asserzione del collaudo precedente è stata cambiata.

### Ambiguità ancora aperte (non decise)

- **A1:** dimensioni fisse che non sono né colonne né icone. Elenco nel collaudo qui sotto, invariato.
- **A2:** il controllo WCAG confronta i colori solo con lo sfondo `surface`.
- **A3:** valori fuori formato accettati o scartati in silenzio. I campi inviati come array e il colore derivato nascosto non valido si comportano come nel collaudo (C03 e C02-10, informativi).

### Pulizia della riverifica

- **Database:** 16 cicli di snapshot e ripristino, tutti identici, SHA-256 `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`; database vuoto alla fine. In tutto il collaudo e la riverifica i cicli sono 38, tutti identici.
- **Email:** solo Mailpit, destinatari `*.test`; Mailpit vuoto alla fine, `rolewarden-mail` healthy. La suite V4 lo ferma e lo riavvia per le prove SMTP.
- **Processi:**
  - nessun `php.exe` o `python.exe` attivo;
  - ogni istanza di Chrome headless avviata è terminata (P10);
  - nessun nuovo monitor in questa riverifica.
- **Restano dal collaudo precedente**, da sistemare a mano:
  - le coppie `tail`/`grep` orfane (PID 26328/42716 e 29472/26372);
  - la cartella bloccata `tests/Integration/verify-v5.work`, con profili Chrome non cancellabili da Windows (junction già rimossa);
  - `scratchpad/chrome`, che contiene anche i profili di questa riverifica, con lo stesso blocco di Windows.

  La cartella di lavoro `verify-v5.work2` è stata rimossa da ogni giro (prima la junction).
- Nessun commit, nessun push.

## Collaudo — 2026-10-03, Collaudatore ad Hoc (Claude), commit `d3c7dbb`

**Esito: FAIL. Suite V5: 160 PASS / 10 FAIL su 170 controlli in ciascun ambiente (development e production), più 1 ambiguità non contata. Regressione V2/V3/V4: 1385 PASS su 1387, con 2 FAIL superati da V5 (G07, uno per ambiente), il cui intento è stato ricontrollato con G07v5, che passa. V5 non è approvata.** Il difetto bloccante è D1: in un browser vero i componenti Alpine del pannello non partono. Per questo il personalizzatore non mostra né l'anteprima dal vivo né gli avvisi WCAG AA, e salvando dal browser i colori scelti vanno persi. Ci sono poi quattro difetti minori (D2-D5) e tre ambiguità di specifica (A1-A3), che non decido io.

Il collaudo sostituisce Codex, che non si avvia per un errore di configurazione del modello. Il protocollo è lo stesso. Ho letto solo `docs/SPEC.md` (Modello dati, Pannello admin con le decisioni V5, Design system), `docs/BRIEF-v1.0.md`, `README.md`, `documentation/design-guide.html`, `docs/design-system/`, `_AI-LOG.md`, `src/Authorization/Contracts/` e `tests/Integration/`. Il CSS e il JS li ho esaminati solo come li riceve un browser, via HTTP. Non ho aperto `resources/`, controller, model, view, `src/Settings`, `src/Account`, `src/Config`, `src/Database`, `src/Language`, helper, test unitari né diff. Il modulo viene da `git archive` di `d3c7dbb` (e di `bd7488e` come baseline), agganciato come cartella sostituibile (junction) in una copia di `../rolewarden-app-test`. Il server PHP è uno alla volta, `php -S` con `error_reporting=-1`, `logger.threshold = 9`. Uso solo `rolewarden_test` su 127.0.0.1:3317, con le credenziali passate esclusivamente da `RW_DB_USERNAME` e `RW_DB_PASSWORD`. Le email vanno solo a Mailpit (SMTP 127.0.0.1:1026, `mail()` disabilitata nel server).

Comandi:
- `python tests/Integration/verify-v5.run.py v5 <ambiente>`;
- per la regressione, `python tests/Integration/verify-v5.run.py <v4|test|extra|adapt|v3|edges|arrays> <ambiente>`;
- per il browser, variabili `RW_V5_CHROME_DIR` (cartella dei profili temporanei) e `RW_V5_WORKNAME` (vedi Pulizia).

### Esito per punto del mandato

| # | Punto | Esito | Controlli |
| --- | --- | --- | --- |
| 1 | Aggiornamento da `bd7488e` per sostituzione della cartella e `migrate -n RoleWarden`; permesso, voce di menu, rollback, installazione nuova, nessuna build | **PASS** | U01-U14 |
| 2 | Tre temi in chiaro e scuro su ogni schermata, solo token | **FAIL** (D2; D1 per gli errori JS) | T01-T08, R-*, P11 |
| 3 | Chiaro/scuro per utente dalla barra in alto | **PASS** | M01-M18 |
| 4 | Personalizzatore: validazione, file, download, ripristino, precedenza di `app/`, file malevoli, avvisi WCAG | **FAIL** (D1, D3; D4 minore) | C01-C10, W00-W15, A01-A05, H-* |
| 5 | Il tema sopravvive all'aggiornamento del modulo | **PASS** | S01-S03 |
| 6 | Quarto tema e componente dell'ospite | **PASS** per tema e componente; X09-X10 FAIL solo per D1 | X01-X10 |
| 7 | Regressione V2/V3/V4 nei due ambienti | **PASS** (G07 superato da V5, vedi sotto) | 1385/1387 |
| 8 | Pubblicazione: CSRF, escape, SQL, log, rotta del CSS | **PASS** con D5 (log) e P11 (punto 2) | P01-P10 |

### Totali

| Suite | development | production |
| --- | ---: | ---: |
| V5 (`verify-v5.php`) | 160/170 | 160/170 |
| Regressione V4 (`verify-v4.php`, baseline `3805640`) | 232/232 | 232/232 |
| Regressione V2 `test` / `extra` / `adapt` | 140/141 + 31 + 16 | 153/154 + 39 + 16 |
| Regressione V3 principale / supplementare / filtri array | 139 + 69 + 55 | 139 + 69 + 55 |
| **Totale** | **842/854** | **853/865** |

I FAIL della V5 sono gli stessi in entrambi gli ambienti: T05, C02-8, W00, W14, R-console-js, R-clarity-js, R-contrast-js, X09, X10, P11. Ambiguità T06 (A1) in entrambi.

Il supplementare V3 conta 69 controlli invece dei 57 della V4. I 12 in più sono i controlli CSRF che la suite genera per ogni form trovato: il form chiaro/scuro della barra in alto è nuovo su ogni pagina, e passano tutti.

Evidenze in `tests/Integration/`:
- `verify-v5.<modo>.<ambiente>.d3c7dbb.output.txt` e, per la V5, i log di fase `…phase-*.log`;
- il CSS servito (`…served-panel.css`, `…served-theme.css`) e l'audit dei token (`…css-audit.json`);
- le schermate (`…customiser-served.png`, `…customiser-started.png`, `…ocean-light.png`, `…ocean-dark.png`);
- i log applicativi e del server con lo stesso prefisso, e `verify-v5.environment.txt` (hash di ogni ciclo).

### Difetti

**D1 (bloccante). I componenti Alpine non partono in un browser: il personalizzatore non dà anteprima né avvisi WCAG, e salvando dal browser i colori si perdono.**

Ogni pagina carica `rolewarden/assets/js/vendor/alpine.min.js` e poi `rolewarden/assets/js/panel.js`, entrambi `defer`. Il build di Alpine servito termina con `queueMicrotask(()=>{…start()})`, quindi Alpine parte subito dopo il proprio script, prima che `panel.js` venga eseguito. Ma `panel.js` registra `rwToasts`, `rwSettings`, `rwMatrix`, `rwUserMatrix` e `rwAppearance` dentro `document.addEventListener('alpine:init', …)`, cioè su un evento già passato.

Riproduzione, Chrome headless con profilo temporaneo, identica in development e production:
1. accedere come admin e aprire `/rolewarden/appearance`;
2. la console riporta `ReferenceError: rwToasts is not defined`, `ReferenceError: rwAppearance is not defined`, e poi `items`, `changed`, `cur` non definiti;
3. la barra degli avvisi resta vuota: non compare nemmeno «Every text colour is readable…»;
4. cambiando il colore del testo (`#rw-light-ink`) la pagina non cambia;
5. scegliendo Contrast, raggio 8 e densità Comfortable e premendo «Save theme», il file `writable/rolewarden/theme.json` contiene `"base": "contrast", "radius": 8, "density": "comfortable"`, ma `"colors": {"light": [], "dark": []}`. I campi nascosti `colors[…]` sono legati con `:value` e restano vuoti, quindi i colori scelti si perdono senza alcun messaggio.

Gli stessi errori compaiono su tutte le schermate e in tutti i temi (R-*-js), e con il quarto tema (X10). Su `bd7488e` la console mostra già `rwToasts is not defined` e `rwSettings is not defined`, quindi il difetto è anteriore alla V5 e tocca anche la pila dei toast in JavaScript, la barra delle modifiche delle Impostazioni e la matrice dei permessi. I collaudi V1-V4 erano solo HTTP e non potevano vederlo.

Diagnosi non decisiva per l'esito: nella stessa sessione il collaudo registra a mano i componenti dopo il caricamento (`Alpine.data(...)` e `Alpine.initTree` su una copia del form). Così il personalizzatore funziona come descritto: W01-W13 passano, vedi «Avvisi WCAG».

**D2 (minore). Valori scritti fuori dai token, nel CSS servito e nel markup.**

Le eccezioni dichiarate dalla design guide sono solo «widths of individual table columns and the geometry of icons».

Nel CSS servito (T05), i valori di icone e colonne sono stati esclusi:

| Regola | Valore grezzo |
| --- | --- |
| `.rw-btn`, `.rw-page` | `border: 1px solid #0000` |
| `.rw-input,.rw-select` | `border: 1px solid var(--control-border)` |
| `.rw-check` | `border: 1px solid var(--control-border)` |
| `.rw-auth-alert` | `border: 1px solid var(--denied)` |
| `.rw-flash` | `border: 1px solid` |
| `.rw-list-footer` | `margin-top: -1px` |
| `.rw-auth-pw button` | `top: 4px; right: 4px` |
| `.rw-toast__close` | `margin: -2px -8px 0 0` |
| `.rw-cell:hover` e `[aria-disabled]` | `box-shadow: inset 0 0 0 1px …` (solo informativo) |

Lo spessore dei bordi esiste come token (`--rule-width`, gruppo «Forma» della SPEC), ma questi componenti non lo leggono. Un quarto tema o un personalizzatore che lo cambi non li raggiunge.

Nel markup servito (P11), attributi `style` con valori non nulli:
- `rolewarden/roles`: `padding-left:12px`;
- `rolewarden/roles/create` e `rolewarden/roles/<id>/edit`: `height:auto;padding:8px 12px`;
- `rolewarden/roles/<id>`: `padding-left:12px`, `font-size:12px`;
- `rolewarden/permissions`: `width:280px`.

Gli altri `style` inline usano token (`color:var(--ink-faint)`, `color:var(--denied)`) o zeri (`border-bottom:0`, `margin:0` sul login).

**D3 (minore). Il raggio non valido viene convertito invece che rifiutato.**

Con un POST del personalizzatore con `radius=8px;}*{color:red}` oppure `8abc`, il file salva `"radius": 8` e compare il toast «Theme saved.». Con `2.9` salva `2`, con ` 4` salva `4`. Nessun errore sotto il controllo. `5` e `-4` vengono invece rifiutati con errore (C02-6/7 PASS). Nessuna iniezione: il CSS servito contiene solo `--radius-md: 8px`.

Riproduzione: `save_theme(['base'=>'clarity','radius'=>'8abc'])` in `verify-v5.php`, controllo C02-8.

**D4 (minore). Messaggio sbagliato con un tema in più.**

Con `$extraThemes = ['ocean' => 'Ocean']`, inviare `base=oceanx` produce l'errore «Error: Choose one of the three themes.» sotto quattro temi offerti. Il rifiuto è corretto (X03 PASS), è sbagliato il testo.

**D5 (minore). Il warning per un file di tema non valido indica un percorso sbagliato.**

Con un `writable/rolewarden/theme.json` o un `app/Config/RoleWarden/theme.json` scritto a mano non valido, il log riporta:

```text
WARNING … RoleWarden: theme file <modulo>\src\Settings\Theme.php is not valid JSON, using the default theme.
```

Il file indicato è un sorgente del modulo, non il file di tema da correggere: probabilmente il segnaposto `{file}` viene sostituito dal logger di CI4 con il file chiamante. Il warning si ripete 7 volte per richiesta. Il comportamento resta corretto: tema predefinito, nessun errore a schermo, nessuna iniezione.

Evidenza: `verify-v5.v5.<ambiente>.d3c7dbb.phase-hostile.log`.

### Ambiguità di specifica (non decise, non contate)

**A1 (T06). Dimensioni fisse che non sono né colonne di tabella né icone.**

| Regola | Valore |
| --- | --- |
| `dialog.rw-confirm` | `width:440px` |
| `.rw-form` | `max-width:480px` |
| `.rw-auth-sheet` | `width:448px` |
| `.rw-toasts` | `width:360px` |
| `.rw-search` | `width:320px` |
| `.rw-page-head` | `grid 1fr 296px`; `p` con `max-width:560px` |
| `.rw-role` | `height:20px` |
| `.rw-who i` | `32px` |
| `.rw-check` | `16px` |
| `.rw-appearance-row .rw-input` | `280px` |
| `.rw-appearance-toast` | `420px` |

Altri valori informativi: `outline-offset:2px`, `letter-spacing:.04em`, `translateY(8px)` nell'animazione dei toast. La design guide le chiama «layout rather than theme» solo per colonne e icone. Resta da decidere se rientrano fra le eccezioni o se servono token di dimensione.

**A2. Il controllo WCAG confronta tutto solo con lo sfondo (`surface`).**

Le coppie controllate, dal JS servito, sono: testo, testo secondario, note, accento, granted e denied contro `surface`, più testo sull'accento contro l'accento. `surface-raised` (campi, menu) e `surface-sunk` (barra laterale) non sono personalizzabili e non vengono confrontati.

Esempio, con il componente avviato a mano: in modo chiaro, sfondo `#111111` e testo `#f0f0f0`. Il testo dei campi resta `#f0f0f0` su bianco, cioè 1.14:1. In questo caso un avviso compare comunque, ma per le altre coppie (testo secondario 2.8:1, note, accento, granted, denied). La DoD chiede di segnalare «le combinazioni sotto WCAG AA» e il README «any text colour under WCAG AA». Va deciso se le combinazioni con `surface-raised`/`surface-sunk` ne fanno parte.

**A3. Valori fuori formato accettati in silenzio.**

- `colors[light][surface][]=…`, `radius[]=8` o `colors=1` vengono ignorati, e il salvataggio riesce con i valori predefiniti e il toast «Theme saved.»;
- un colore derivato non valido nel campo nascosto `colors[light][accent-hover]` fa scartare l'intero salvataggio senza toast né errore.

Nessun 500, nessun errore a schermo, nessun valore malevolo salvato. Va deciso se servono messaggi espliciti per richieste che dal form normale non possono arrivare.

Osservazioni:
- i tentativi di path traversal sulla rotta degli asset vengono respinti da CI4 («disallowed characters») ma registrati come `CRITICAL` nel log del framework, non del modulo;
- `theme.json` serializza i colori vuoti come `[]` invece di `{}`;
- la rotta del CSS dei temi è pubblica, perché serve anche al login, ed espone solo proprietà personalizzate;
- il CSS compilato contiene una utility Tailwind `.filter`, con le variabili interne `--tw-*` a `initial`: innocua.

### Cosa è stato verificato e passa

- **Aggiornamento (U01-U14).**
  - Dopo la sostituzione della cartella e prima di `migrate`, il pannello e il login rispondono 200, senza voce Appearance, e `/rolewarden/appearance` viene respinta senza 500.
  - Dopo `migrate`, `appearance.update` va al solo `admin` e nessuna tabella è nuova. La voce di menu compare per admin e super admin, ma non per chi ha solo `users.view` né per un utente senza permessi. La rotta è respinta a chi non ha il permesso.
  - Il rollback è esatto (righe ACL, `migrations`, tabelle). La cartella `bd7488e` ripristinata funziona, la migrazione si riapplica, l'installazione nuova concede il permesso e il rollback non lascia tabelle `acl_`.
  - Nessuna build: il pacchetto contiene il CSS compilato, e il CSS servito è byte per byte identico a quel file.
- **Token (T01-T04, T07, T08).**
  - Nessun componente legge primitivi (`--p-*`) né palette Tailwind, e i semantici passano ai primitivi solo attraverso le coppie `--l-*`/`--d-*` di ciascun tema.
  - Ogni proprietà letta è definita, i tre temi definiscono lo stesso insieme di token e ogni colore chiaro ha il suo corrispondente scuro.
- **Resa (R-*), con Chrome headless.**
  - Login più 14 schermate (utenti, nuovo utente, dettaglio, modifica, ruoli, nuovo ruolo, matrice, modifica ruolo, permessi, impostazioni, attività, profilo, sessioni, aspetto), per 3 temi in chiaro e in scuro: tutte 200, con `data-rw-theme` e `data-rw-mode` corretti.
  - Ogni token usato si risolve in ogni combinazione, e sfondo e testo del pannello vengono dai token.
  - Nessun testo visibile sotto 3:1, nessuno sotto 4.5:1. Le coppie di testo dei temi spediti rispettano AA in tutte e sei le combinazioni (R-AA, come dichiara la design guide).
- **Chiaro/scuro (M01-M18).**
  - La barra in alto offre Sistema, Chiaro e Scuro, con CSRF.
  - La scelta viene salvata nella libreria Settings con contesto `user:<id>` (`RoleWarden\Config\RoleWarden.colorScheme`), vale su ogni pagina, sopravvive a un nuovo accesso ed è indipendente fra utenti; la può fare anche un utente senza permessi.
  - Valori non validi, array e POST senza CSRF vengono respinti.
  - In Chrome: senza scelta il pannello segue `prefers-color-scheme` (anche il login), una scelta esplicita lo batte, e il pulsante Sistema torna a seguire il sistema.
- **Personalizzatore lato server.**
  - Il form ha tema di partenza, sfondo, testo e accento per chiaro e scuro, raggio, densità e CSRF. Gli errori compaiono sotto il controllo (`rw-field-error`) per tema, colori non validi, raggio fuori lista e densità.
  - Il file si salva in `writable/rolewarden/theme.json` e nessun file del modulo cambia (hash dell'albero).
  - Il CSS del tema viene servito anche a chi non ha effettuato l'accesso e contiene solo proprietà personalizzate. Il tema è globale, login compreso.
  - Download: `attachment; filename="theme.json"`, contenuto uguale al file; negato a chi non ha il permesso. Salvataggio e ripristino diretti senza permesso vengono respinti.
  - Il ripristino ha la conferma: il form sta solo nel `<dialog>`, il pulsante lo apre, Annulla lo chiude. Senza CSRF viene respinto; confermato, cancella il file e riporta Console, con un toast.
  - Con un `app/Config/RoleWarden/theme.json` presente, quello vince, il pannello lo mostra senza pulsante di salvataggio né ripristino, e i POST diretti non cambiano nessuno dei due file.
  - 26 file di tema scritti a mano (13 per posizione), ostili o malformati (iniezione CSS e markup, `url()`, `expression`, `javascript:`, a capo, token iniettati, chiavi annidate, `../` nel tema, JSON non valido, BOM, annidamento profondo), sia in `writable/` sia in `app/`: nessuna iniezione nel CSS o nell'HTML, `data-rw-theme` sempre uno dei temi, nessun 500, nessun errore PHP nel log.
- **Aggiornamento con tema salvato (S01-S03).** Con la cartella del modulo sostituita da una seconda estrazione di `d3c7dbb` più `migrate`, il file del tema resta identico byte per byte e il pannello mostra lo stesso tema con lo stesso CSS.
- **Quarto tema e componente dell'ospite (X01-X08).** Configurazione `app/Config/RoleWarden.php` presa letteralmente dalla design guide, con il foglio nella `public/` della copia.
  - Ocean viene offerto e si salva; il foglio dell'ospite viene collegato su pannello e login; un tema non dichiarato viene respinto.
  - I colori chiaro e scuro e i token di forma si applicano, e un token omesso torna al valore di Console (`--rule`, `--text-md`).
  - Un componente costruito con i token semantici segue tema e modo.
- **Pubblicazione (P01-P10).**
  - Ogni form nuovo (modo, salvataggio, ripristino) ha il CSRF, e il salvataggio senza token viene respinto con un toast di errore.
  - Un valore di modo manomesso nel database non finisce grezzo nella pagina.
  - La rotta del CSS dei temi non espone percorsi, JSON o dati, ignora i parametri di query e senza file non va in errore. Path traversal respinto.
  - Nessun testo SQL né 500 nelle pagine viste. Log applicativo a livello 9 pulito, esclusi i rifiuti CSRF, la fase dei file ostili e le sonde di traversal. Log del server PHP senza warning.
  - Tutte le istanze di Chrome avviate sono terminate.

### Avvisi WCAG del personalizzatore: come li ho verificati

Ho usato Chrome headless pilotato via CDP da `verify-v5.browser.mjs`, con un profilo temporaneo fuori dal profilo dell'utente, chiuso per PID. La pagina e il JS sono quelli serviti dal pannello. Le soglie le ho calcolate per conto mio in PHP, con la luminanza relativa WCAG 2.1. Sulla superficie Console chiara `#fafaf9`, `#747474` dà 4.48:1 e `#737373` dà 4.54:1.

1. **Pagina come servita (W00, W14): FAIL.** Il componente non parte (D1). Nessun messaggio, nessuna anteprima.
2. **Componente registrato e avviato dal collaudo dopo il caricamento (diagnosi, W01-W13).**
   - Con i valori di Console compare «all clear».
   - `#747474` viene segnalato come «Text (Light) 4.5:1, under 4.5:1», `#737373` no.
   - Un testo scuro sotto soglia viene segnalato come «Text (Dark)», un accento pallido come «Accent (Light)».
   - L'anteprima applica sulla pagina reale testo, accento (con hover, tinta e testo sull'accento derivati), raggio 8px, densità Comfortable (righe da 52px) e il tema di partenza.
   - Con un avviso attivo si può salvare comunque: il file contiene il colore sotto soglia (W11, W15), e dopo il salvataggio la pagina ricaricata mostra il tema salvato.
   - Con Ocean, appena il componente parte, un testo scuro sotto soglia viene segnalato; come servito, invece, X09 fallisce per D1.

### Aspettative superate da V5 (non modificate in silenzio)

- **V2 `test` G07**, «View-only settings has no save button»: cerca `type="submit"` in tutta la pagina. Dalla V5 la barra in alto ha i pulsanti Sistema/Chiaro/Scuro su ogni pagina. Ho lasciato l'asserzione com'era (1 FAIL per ambiente) e ne ho ricontrollato l'intento in `verify-v5.php` con G07v5: nessun pulsante di invio fuori dal form del modo, per chi ha solo `settings.view`. PASS in entrambi gli ambienti.
- Il supplementare V3 ha più controlli CSRF per i nuovi form del modo (vedi Totali).

### Correzioni dello strumento (dichiarate)

- **Nel runner.** I glob dei log del server PHP nelle suite V3 (`verify-v3.*server.log`) e il nome del log del server nella suite V4 non corrispondevano ai file realmente scritti dal runner. Lo stesso accadeva già nel runner V4: lì Z03, `ARRAY-server-log` e Z06 risultavano PASS su zero file. Qui puntano ai log veri, e passano.
- **Nella suite V5, durante l'esecuzione**, ogni correzione commentata nel codice:
  - T04 e la risoluzione dei token escludono `--tw-*`, le variabili interne di Tailwind;
  - M17 aveva un `??` su un valore `null`;
  - C04b trovava l'«Error:» del toast di esempio nella sezione Sample;
  - C10 leggeva lo stat cache di PHP;
  - `error_under` ora accetta anche l'errore dopo il gruppo di controlli;
  - X02 doveva decodificare gli entity nell'`href`;
  - per C03, il «nulla salvato» era un'aspettativa mia: ora registra cosa viene salvato (A3);
  - W02 e W10 sbagliavano tema di partenza e token di confronto;
  - S02 e A01 non dipendono più dal salvataggio dal browser (W15);
  - P08 e P11 escludono le sonde di traversal e le pagine di errore del framework.
- Il primo giro in development (127/167) e il secondo (156/169) contenevano questi errori di strumento. I totali qui sopra sono del giro definitivo nei due ambienti.

### Pulizia

- **Database.** `rolewarden_test` era vuoto all'inizio. 22 cicli di snapshot e ripristino, tutti identici, SHA-256 `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`; vuoto anche alla fine. `rolewarden` mai toccato.
- **Email.** Solo Mailpit, con destinatari tutti `*.test`. Mailpit vuoto alla fine, `rolewarden-mail` healthy.
- **Processi.** Nessun `php.exe`, `python.exe` o Chrome headless di questa sessione attivo: restano solo i processi del browser dell'utente. Due coppie `tail.exe`/`grep.exe` dei miei monitor (PID 26328/42716 e 29472/26372) sono rimaste orfane: la chiusura mi è stata negata dal classificatore dei permessi, vanno chiuse dall'utente.
- **Cartelle.** La cartella di lavoro `verify-v5.work2` è stata rimossa dal runner, prima la junction. `verify-v5.work`, del primo giro, e `scratchpad/chrome` contengono solo profili Chrome temporanei, con file che Windows rifiuta di cancellare («Accesso negato», file in cancellazione con un handle aperto) anche se nessun processo Chrome di questa sessione è attivo. Vanno rimosse più tardi o dopo un riavvio. La junction di `verify-v5.work` era già stata rimossa.
- **Altro.** `writable/` della copia è stata eliminata con la copia, e l'app sorella non è stata toccata. Nessun commit, nessun push.

Il controllo visivo in un browser vero resta dovuto all'autore: le schermate headless sono solo un supporto.
