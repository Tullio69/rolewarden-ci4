# V4 — Email di sicurezza: collaudo indipendente

## Riverifica — 2026-09-30, Codex, commit `c0ab8fc`

**D1 corretto. Esito complessivo: 1287 PASS / 14 FAIL su 1301 controlli eseguiti, più 6 controlli SMTP bloccati dall'ambiente. V4 non approvata integralmente:** il confronto richiesto del contenuto rileva una variazione ulteriore, D2 (titolo duplicato nel testo, minore), e le prove con SMTP spento/appeso non sono state rieseguibili. La regressione V2/V3 passa **899/899**. A1-A4 restano separate e non decise; il sintomo di A4 risulta superato.

Modulo eseguito da `git archive c0ab8fc`; baseline di aggiornamento `3805640`; HEAD del repository rimasto `05ad4eb9836c139de219d3f6818099285be40fb0`. Stesso perimetro documentale del collaudo precedente: nessuna implementazione, template email, test unitario o diff aperto. I contenuti email provengono esclusivamente da Mailpit e dalle catture del precedente collaudo. Nessuna modifica al modulo, commit o push.

### Totali della riverifica

| Suite | development | production |
| --- | ---: | ---: |
| V4 preesistente, controlli eseguibili | 92/92, 3 bloccati | 92/92, 3 bloccati |
| D1: 24 varianti, POST + GET + log per variante | 72/72 | 72/72 |
| Contenuto email: replay corretto delle catture | 30/37 | 30/37 |
| Regressione V3 principale | 139/139 | 139/139 |
| Regressione V3 supplementare | 57/57 | 57/57 |
| Regressione V3 filtri array | 55/55 | 55/55 |
| Regressione V2 `test` | 141/141 | 154/154 |
| Regressione V2 `extra` | 31/31 | 39/39 |
| Regressione V2 `adapt` | 16/16 | 16/16 |
| **Totale eseguito** | **633/640** | **654/661** |

I 14 FAIL sono i sette confronti `R-CONTENT-*` in ciascun ambiente: **una sola variazione**, D2. M01-M03 non eseguiti sono contati come bloccati, mai come PASS o FAIL. Anche la misura M05 relativa ad A2 non è eseguibile; era già fuori dal conteggio precedente. M04 ora verifica la consegna con Mailpit disponibile, **non** un recupero dopo un fermo che non è avvenuto.

Evidenze: [riepilogo numerico](verify-v4.recheck-summary.json), `verify-v4.<modo>.<ambiente>.c0ab8fc.output.txt`, `verify-v4.mail-replay.<ambiente>.c0ab8fc.output.txt`, catture `verify-v4.v4.<ambiente>.c0ab8fc.verify-v4.captured-mail.json` (JSONL), log applicativi e server con lo stesso prefisso, [snapshot e pulizia](verify-v4.environment.txt). Gli output storici su `2028ea2` sono preservati.

### D1 — chiuso in development e production

Riproduzione originale Z03-0, array annidato Z03-1 ed email registrata Z03-4: PASS. Aggiunte 24 varianti per ambiente usando i nomi letterali nel corpo POST, senza trasformare `email[]` in `email[0]` nel client:

- `email[]`, `email[0]`, `email[_]`, `email[a][b]`;
- password assente oppure scalare presente;
- remember assente, `remember[]=1`, oppure `remember[_]=1`.

Per ciascuna combinazione: POST rifiutata senza HTTP 500, nessuna autenticazione, GET successiva di `/login` nella stessa sessione **200** con modulo utilizzabile, nessun errore PHP/SQL nella risposta. Log D1 a soglia 9 **senza WARNING, NOTICE, DEPRECATED, ERROR, CRITICAL, ALERT o EMERGENCY**, senza esclusioni. Server PHP con `error_reporting=-1`, nessun warning/notice/deprecation/fatal. La cache del throttling viene pulita tra i casi affinché il limite per IP non nasconda il percorso difettoso.

La prova di sensibilità sulla baseline `3805640` continua a produrre 500 sulla POST; è archiviata separatamente. A3 (password array) resta separata: non è una variante scalare di D1 e continua a produrre il TypeError di Shield. Fuori dalla fase D1 i log contengono anche i rifiuti CSRF deliberati e i warning attesi per `fromEmail` vuoto, come nel collaudo precedente.

### Modifiche email dichiarate e D2

**O1 e O2 superate.** Le parti text/plain hanno righe distinte; `When`, `Device`, `Address` iniziano ciascuno su una riga. Il nome di prova appare come testo letterale `<i>V4esc</i>`, senza `&lt;`/`&gt;`; l'HTML ricevuto mantiene l'escape. Tutti i controlli preesistenti su destinatari, mittente, contenuti richiesti, password/hash, permessi, estensione e override passano.

**Fuso verificato:** `UTC` accompagna l'ora sia nel testo sia nell'HTML dei messaggi con data (nuovo accesso e password cambiata, da profilo e da admin). Le email di blocco continuano a non contenere una data, come già osservato in O3. Il sintomo «data senza fuso» di A4 è quindi superato, senza scegliere un formato o un fuso obbligatorio per la specifica.

**D2 — Il testo aggiunge un titolo iniziale duplicato rispetto a `2028ea2`.** È uno scostamento minore dal controllo richiesto «il resto del contenuto invariato», non un problema di invio o sicurezza. Esempio catturato su `c0ab8fc`:

```text
New sign-in to your account
localhost
New sign-in to your account
Hello V4newdev,
```

La cattura precedente iniziava con `localhost`, seguito da una sola occorrenza del titolo. Lo stesso avviene per `Your password was changed` e `Too many failed sign-ins`, in entrambi gli ambienti. Sono confrontati sette messaggi per ambiente: nuovo dispositivo, nuovo IP, nome con markup, password da profilo, password da admin, blocco all'utente e blocco all'admin. Il titolo aggiunto coincide con il `<title>` dell'HTML **ricevuto**, letto da Mailpit; non è stato aperto alcun template.

Il confronto normalizza data/ora, indicazione UTC, spaziatura e decodifica delle entità, ma **non** elimina il titolo aggiunto: i sette `R-CONTENT-*` restano FAIL. Un controllo separato `R-BODY-*` dimostra che, tolto soltanto quel prefisso, il testo restante coincide con la cattura precedente; mittente, destinatari e oggetto coincidono. Il confronto storico riguarda il testo catturato e i campi del messaggio, non l'identità byte per byte del vecchio HTML, che il precedente output non conservava integralmente.

### Limite d'ambiente e correzioni dello strumento

`docker stop rolewarden-mail` fallisce con accesso negato alla pipe `dockerDesktopLinuxEngine`. Mailpit resta acceso su 1026/8026: **SMTP spento e appeso non sono stati simulati**. Non sono stati cambiati porta o trasporto per aggirare il vincolo. Restano da rieseguire M01-M03 e la misura M05 con accesso a Docker; la verifica FPM di A2 resta distinta.

Nel primo giro il vecchio runner ignorava l'exit code di Docker e dichiarava falsamente PASS le prove SMTP, compresa M05. Quel giro è conservato come `c0ab8fc.preliminary.*` ed escluso dai totali. Lo strumento ora emette `[BLOCKED]`, salta la simulazione se il fermo fallisce, verifica che il listener SMTP abbia davvero aperto la porta e restituisce un exit code non zero anche quando vi siano solo blocchi.

Due correzioni riguardano esclusivamente i controlli del contenuto: leggere il fuso dall'HTML decodificato senza concatenare le parole dei tag adiacenti; accettare l'andata a capo MIME tra data, ora e UTC. Quest'ultima causava quattro falsi FAIL per ambiente nei controlli di data e confronto del corpo delle email password. È stato rieseguito **solo il confronto sulle stesse catture immutate**, senza nuovi invii o accessi al database:

```powershell
python tests/Integration/verify-v4.run.py v4 development
python tests/Integration/verify-v4.run.py v4 production
# Analogamente: v3, edges, arrays, test, extra, adapt, ciascuno nei due ambienti.
php tests/Integration/verify-v4.recheck.php development
php tests/Integration/verify-v4.recheck.php production
```

Il default del runner è ora `c0ab8fc`, sovrascrivibile con `RW_V4_NEW`. Gli output HTTP definitivi riportano ancora il risultato grezzo **190/201** per ambiente; non sono stati riscritti. Nei totali sopra i loro 37 controlli email sono **sostituiti**, non sommati, dai 37 del replay corretto (**30 PASS / 7 FAIL**), ottenendo **194/201** per la V4 estesa. Gli hash delle catture usate sono riportati negli output del replay. La regressione conserva gli adattamenti V2/V3 già documentati sotto, senza nuove aspettative funzionali.

### Ambiguità, sicurezza e pulizia della riverifica

- **A1:** ancora riproducibile, non decisa.
- **A2:** non decisa; nuove prove SMTP bloccate dall'ambiente e verifica FPM ancora dovuta.
- **A3:** ancora riproducibile, non decisa.
- **A4:** sintomo superato da `UTC` esplicito; nessuna decisione sul formato della specifica. La voce storica sotto resta preservata.
- Solo `rolewarden_test` su `127.0.0.1:3317`, credenziali esclusivamente da `RW_DB_USERNAME` e `RW_DB_PASSWORD`. **15 cicli con ripristino identico** (14 definitivi + 1 preliminare); database finale vuoto. SHA-256 iniziale/finale: `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`.
- `.env` della copia su SMTP `127.0.0.1:1026`, crypto vuoto, prima di ogni richiesta; guardia prima di ogni avvio, configurazione effettiva verificata, `mail()` disabilitato nel server. Indirizzi solo `@*.test`; Mailpit finale **0 messaggi**. Nessun invio reale.
- Un server HTTP PHP alla volta, arrestato tramite il proprio processo/PID. Nessun `taskkill /IM`. Junction rimossa prima della cartella temporanea. Verifica finale: **0 processi PHP, 0 processi Python, `verify-v4.work` assente**.
- Scritture solo in `tests/Integration/` e nella nuova voce di `_AI-LOG.md`; «Stato corrente» non modificato. Le modifiche concorrenti di Claude restano fuori dall'intervento. Controllo visivo in browser/client email ancora dovuto, fuori da questo incarico.

---

## Collaudo precedente su `2028ea2` — storico preservato

**Esito: FAIL, per un solo difetto (D1). V4 non approvata** finché D1 non è corretto. Tutte le funzioni V4 (nuovo dispositivo o IP, password cambiata, troppi tentativi, invio, estensione, override, mancanza di `fromEmail`) passano in development e production; la regressione V2/V3 passa per intero (899/899). Restano da decidere dall'autore le ambiguità A1-A4, non decise qui. Il controllo visivo in un browser vero resta **dovuto** (fuori da questo incarico).

Commit collaudato: `2028ea2` (main). Baseline di aggiornamento: `3805640`. Data: 2026-09-28/29. Collaudatore: Collaudatore ad Hoc (Claude), in sostituzione di Codex (quota esaurita), stesso protocollo: letti solo `docs/SPEC.md` (Modello dati, Pannello admin con le decisioni V1-V4, Sistema di notifiche), `docs/BRIEF-v1.0.md`, README, `_AI-LOG.md`, `docs/design-system/`, `src/Authorization/Contracts/` e `tests/Integration/`. Nessun controller, model, view (template email compresi), sorgente vietato, test unitario o diff aperto. Il contenuto delle email è letto solo dai messaggi catturati da Mailpit; i nomi dei template sono ricavati dal campo `view` dell'evento documentato `rolewarden.mail`. Le posizioni dei file citate in D1 vengono dai log applicativi (stack trace), non dalla lettura del codice.

## Totali

Ogni suite eseguita due volte, un processo per ambiente (`verify-v4.run.py <modo> <ambiente>`), ciascuna con snapshot e ripristino propri.

| Suite | development | production | Evidenza |
| --- | ---: | ---: | --- |
| V4 (`v4`, nuova) | 90/95 | 90/95 | `verify-v4.v4.*.output.txt` |
| Regressione V3 principale (`v3`) | 139/139 | 139/139 | `verify-v4.v3.*.output.txt` |
| Regressione V3 supplementare (`edges`) | 57/57 | 57/57 | `verify-v4.edges.*.output.txt` |
| Regressione V3 filtri array (`arrays`) | 55/55 | 55/55 | `verify-v4.arrays.*.output.txt` |
| Regressione V2 `test` | 141/141 | 154/154 | `verify-v4.test.*.output.txt` |
| Regressione V2 `extra` | 31/31 | 39/39 | `verify-v4.extra.*.output.txt` |
| Regressione V2 `adapt` | 16/16 | 16/16 | `verify-v4.adapt.*.output.txt` |
| **Totale** | | | **1079 PASS / 10 FAIL su 1089** |

I 10 FAIL sono **un solo difetto, D1**, visto da cinque controlli per ambiente (Z03-0, Z03-1, Z03-4, Z04, Z05). Le ambiguità (`[AMB]`) non sono contate né come PASS né come FAIL.

## Esito per punto

| Punto | Esito | Controlli e note |
| --- | --- | --- |
| 1. Aggiornamento | **PASS** | Da `3805640` per sostituzione della cartella: prima di `migrate` il pannello di chi è connesso funziona e un login da dispositivo nuovo riesce (U02, U02b). `migrate -n RoleWarden` dà `security.alerts` solo ad `admin` (U03, U03b), nessuna tabella nuova (U03c). Rollback di batch: ACL, migrazioni e tabelle identiche alla baseline (U04), cartella `3805640` di nuovo funzionante (U05), riapplicazione (U06). Installazione nuova: permesso concesso (U07), rollback completo senza tabelle `acl_` residue (U08). |
| 2. Nuovo dispositivo o IP | **PASS** | Primissimo accesso: nessuna email (N01); stesso dispositivo e IP: nessuna (N02); stesso browser e sistema con versione diversa: nessuna (N03); dispositivo nuovo (Firefox/Linux) con IP noto: una sola email, solo all'utente (N04); di nuovo quel dispositivo: nessuna (N05); browser noto su sistema mai abbinato (Chrome/Linux): una (N06); login fallito da dispositivo nuovo: nessuna (N08); poi riuscito da quel dispositivo: una (N09). **IP nuovo via HTTP**, senza query dirette: server riavviato su `[::1]`, `auth_logins` registra `::1` invece di `127.0.0.1`: una email (N10), poi nessuna (N11); IP visto e dispositivo visto, mai insieme: nessuna (N12, coerente con "l'IP oppure il dispositivo"). Storico inserito **con query dirette in `auth_logins`, dichiarate**: storico di un altro utente non conta (N13); solo tentativi falliti prima, primo accesso riuscito: nessuna (N14); dispositivo e IP noti dallo storico: nessuna (N15); dispositivo nuovo (N16) e IP nuovo (N17): una. Informativo: Edge/Windows dopo Chrome/Windows produce una email (riconosciuto come browser diverso). |
| 3. Password cambiata | **PASS** | Profilo: password attuale errata (P01), nuova troppo corta (P02), da dizionario (P02b), conferma diversa (P03), senza token CSRF (P04): password invariata, nessuna email. Cambio corretto: una email, solo all'utente (P05). Admin: senza token CSRF rifiutato, nessuna email (P07); modifica senza password (rinomina): nessuna email (P08); nuova password: una email all'utente e nessuna all'admin (P09). Informativo: l'admin può impostare `abc` (nessuna regola di robustezza sul percorso admin, preesistente e fuori da V4); in quel caso la password cambia davvero e l'email parte, coerentemente (P06). |
| 4. Troppi tentativi | **PASS** | Soglia 3 dalle Impostazioni: 2 fallimenti nessuna email (L01); il quarto tentativo è bloccato anche con la password giusta (L02); l'avviso parte al raggiungimento della soglia (dopo il terzo fallimento) e arriva **una sola volta** all'utente (L03) e a ciascun titolare di `security.alerts`: super admin, admin, utente con override positivo (L04-*); nessuno a chi non ha il permesso, compresi l'admin disattivato e l'admin con override negativo (L05); 6 tentativi, un messaggio per destinatario (L06). Dopo la scadenza, un nuovo blocco manda un nuovo avviso, uno solo (L07). Email non registrata: nulla all'indirizzo digitato, un avviso a ciascun titolare, nessuno ad altri (L08-L10). Soglia portata a 5 dalle Impostazioni: nulla a 3 e 4 fallimenti, avviso al quinto, sesto tentativo bloccato (L11-L13). Fallimenti contati dall'ultimo accesso riuscito: 2 + successo + 2 a soglia 3 non avvisano (L14). |
| 5. Contenuto | **PASS** | Destinatario solo l'utente (C01, C09), mittente = `Config\Email` dell'ospite, `noreply@rolewarden.test` / `RoleWarden Test` (C02, C09, C12). Oggetti: "New sign-in to your localhost account", "Your localhost password was changed", "Too many failed sign-ins on localhost". Email di nuovo accesso con dispositivo (C04, C04b), indirizzo (C05, C05b) e data (C06, C06b). Nessuna password (vecchia, nuova, digitata) né hash nel testo, nell'HTML e nel sorgente grezzo (C07, C10, C11, C12, C13), né nei payload di `rolewarden.mail` (X02; 0 hash trovati). HTML: nome utente `<i>V4esc</i>` e user agent con `<b>`/`<img>` senza markup crudo, nome presente come `&lt;i&gt;` (C08); email digitata `x&y..@v4.test` escapata nell'avviso admin (C14). Vedi osservazioni O1-O3. |
| 6. Invio dopo la risposta | **PASS con limite, ambiguità A2** | Mailpit fermato (`docker stop`): login da dispositivo nuovo risponde 303, pagina successiva 200, nessun errore a schermo (M01). Server SMTP che accetta e non risponde mai (listener di prova su 127.0.0.1:1026 a Mailpit fermo, non inoltra nulla): stessa cosa (M02). Log: solo errori gestiti (`Email: sendWithSmtp threw ErrorException ...`, `RoleWarden: security email to ... not sent.`), nessun CRITICAL né eccezione non gestita (M03). Mailpit riacceso, l'email successiva arriva (M04). **Limite:** con `php -S` la risposta aspetta l'SMTP (1,2 s di base, 3,1-3,3 s con connessione rifiutata, 16,2-16,3 s con server appeso): il server integrato non ha `fastcgi_finish_request()`. La non attesa sotto PHP-FPM non è verificabile su questo host (A2). |
| 7. Estensione e override | **PASS** | `rolewarden.mail` riceve `to`, `subject`, `view`, `data` (X01). Listener nell'app di prova che restituisce `false`: evento emesso, nessun invio (X03); senza quel verdetto l'invio riprende (X04). I nomi dei template vengono dal campo `view`: `new_sign_in`, `password_changed`, `too_many_attempts`; copie in `app/Views/overrides/RoleWarden/Views/emails/<nome>.php` usate per nuovo accesso, password e blocco (X05-X07). Senza `fromEmail` (`email.fromEmail = ''`, verificato con `spark config:check`): login e cambio password admin riusciti con toast di successo (X08), pagine del pannello 200 (X09), nessun invio (X10), un WARNING "security email not sent, Config\Email::$fromEmail is empty." e niente di peggio (X11). |
| 8. Regressione | **PASS** | 899/899, sotto gli adattamenti dichiarati. |
| 9. Pubblicazione | **FAIL — D1** | CSRF: impostazioni (Z02), profilo (P04) e password admin (P07) rifiutano senza token; login senza token: **A1**. Campo email del login come array: **HTTP 500** (D1). Password come array: 500 dentro Shield (A3). Nessun testo SQL in 165 pagine; unici 500 quelli di D1 (Z04). Log a soglia 9 senza warning o errori tranne D1 e le fasi volute (Z05). Log del server PHP (`error_reporting=-1`) senza warning, notice, deprecation o fatal (Z06). |

## Difetti

### D1 — Email del login inviata come array: HTTP 500 (development e production)

Passi:
1. `GET /login`, prendere `csrf_test_name` dal modulo.
2. `POST /login` con `csrf_test_name=<token>&email[]=x@v4.test&password=y` (anche `email[a][b]=...`, oppure un'email vera come `email[]=<email registrata>`).
3. La POST risponde 303 verso `/login`; il `GET /login` successivo, nella stessa sessione, risponde **HTTP 500**.

Log (CRITICAL, entrambi gli ambienti): `ErrorException: Array to string conversion [Method: GET, Route: login] in .../src/Views/auth/login.php on line 35` (la vista di login del modulo che ristampa il valore precedente del campo). In development la pagina d'errore di CI4 compare a schermo. Controlli: Z03-0, Z03-1, Z03-4, Z04, Z05.

Sensibilità sulla baseline: su `3805640` lo stesso invio dava già 500, ma sulla POST, con `Array to string conversion` in `src/Filters/SignInThrottle.php` riga 38. Su `2028ea2` la POST passa il filtro e il 500 si sposta sulla pagina di login. Difetto preesistente, non corretto da V4.

## Ambiguità (non decise)

- **A1 — Login senza token CSRF accettato.** Nell'app di prova `POST /login` senza `csrf_test_name` autentica (303 verso il pannello, riga in `auth_logins`), sia su `2028ea2` sia su `3805640`. La rotta è di Shield e il filtro `csrf` globale dell'ospite è commentato (`app/Config/Filters.php` dell'app di prova), mentre le rotte del pannello lo applicano da sole. La definizione di fatto chiede "CSRF su ogni form" e il modulo di login è una sua vista; il README non dice all'ospite di attivare il filtro globale, e il modulo aggancia già `rw-signin` alla rotta `login`. Da decidere: CSRF sulla rotta di login a carico del modulo, oppure istruzione nel README. Finché non si decide, quella voce della definizione di fatto non è spuntabile.
- **A2 — Invio "dopo la risposta" fuori da PHP-FPM.** La SPEC prevede `fastcgi_finish_request()` "quando esiste". Col server integrato (e presumibilmente con mod_php) la risposta del login aspetta l'SMTP: +2 s con connessione rifiutata, +15 s con server che non risponde (`SMTPTimeout` 5 s dell'ospite, più letture). Da decidere se è accettabile così o va documentato nel README. La verifica sotto FPM va fatta su un ambiente FPM (la replica o il VPS).
- **A3 — Password del login inviata come array.** HTTP 500, con `TypeError: CodeIgniter\Shield\...\ValidationRules::max_byte(): Argument #1 ($str) must be of type ?string, array given`, dentro la validazione di Shield. Non viene da un file del modulo; da decidere se il filtro `rw-signin` del modulo deve fermare gli input non scalari prima di Shield, o se si segnala a Shield.
- **A4 — Data senza fuso orario.** "When: 2026-09-28 21:35" è in UTC (ora del server dell'app), mentre l'ora locale era 23:35, e nel testo non c'è alcuna indicazione del fuso. La SPEC non fissa il formato.

## Osservazioni non bloccanti

- **O1 — Parte text/plain senza separatori.** Nella parte testuale le righe dei dettagli sono attaccate: "When: 2026-09-28 21:32Device: Safari / Mac OS XAddress: ...".
- **O2 — Entità HTML nella parte text/plain.** Il nome `<i>V4esc</i>` compare come `&lt;i&gt;V4esc&lt;/i&gt;` nella parte testuale (nell'HTML è corretto).
- **O3 — Contenuto informativo.** L'email di password cambiata ha la data ma non l'indirizzo; l'avviso di blocco ha l'indirizzo e l'email bloccata ma non la data. La SPEC non chiede di più.
- L'admin può impostare una password di 3 caratteri dal dettaglio utente (preesistente, le policy password sono v1.5).

## Adattamenti della regressione (aspettative superate da V4)

La baseline ora è `3805640` (V3), che ha già sessioni e log attività. Quindi:
- `verify-v2.php`: A01 ("V1 installed without sessions permissions", poi "V2 baseline lacks activity.view" nel collaudo V3) ora controlla che la baseline non abbia `security.alerts`; il controllo di A04 sulla tabella `acl_sessions` (poi `acl_activity_log`) assente dopo il rollback ora controlla che `security.alerts` sia assente. Le altre aspettative sono invariate.
- `verify-v3.cases.php`, solo il blocco "V3 upgrade and fresh install": U01, U03, U05, U07 e U08 seguono `security.alerts` invece di `activity.view` e della tabella `acl_activity_log`, perché la baseline li ha già. Il resto della suite è invariato.
- Trasporto: SMTP su Mailpit (127.0.0.1:1026) invece del loopback porta 9 del collaudo V3, `mail()` disabilitato nel server, guardia sul `.env` prima di ogni avvio del server. Gli originali V2/V3 non sono modificati: le copie adattate vivono solo nella cartella temporanea.

## Correzioni dello strumento durante il collaudo

Nessuna riguarda il modulo. Primo avvio fermato da S00: `spark config:check` non gira prima che il modulo sia agganciato (rotte dell'app), poi non stampa in production. Spostato dopo l'installazione, con `CI_ENVIRONMENT=development` solo per quella chiamata (`Config\Email` non dipende dall'ambiente). M03 all'inizio contava come "non gestite" le righe di stack trace che CI4 `Email` allega al proprio errore gestito; ora contano solo CRITICAL/ALERT/EMERGENCY e `Uncaught`. X05-X07: il campo `view` contiene il nome nudo (`new_sign_in`), mappato sulla cartella del README. P06-P09 resi indipendenti (Mailpit svuotato e hash riletto prima di ciascuno). Nel primo giro di development Z01 era un controllo; ora è l'ambiguità A1. I numeri qui sopra sono dell'esecuzione finale.

## Ambiente, sicurezza email e pulizia

- Copia di `../rolewarden-app-test` in `tests/Integration/verify-v4.work`, modulo da `git archive` (`3805640`, `2028ea2`) agganciato via junction, server `php -S` riavviato a ogni scambio (porta 8070, legato a 127.0.0.1 o `[::1]`), un solo server alla volta.
- Solo `rolewarden_test` su 127.0.0.1:3317; credenziali solo come variabili di processo `RW_DB_USERNAME`/`RW_DB_PASSWORD`, mai in file o output.
- `.env` della copia: `email.protocol = smtp`, `email.SMTPHost = 127.0.0.1`, `email.SMTPPort = 1026`, `email.SMTPCrypto = ''`, `email.fromEmail = noreply@rolewarden.test`, `email.fromName = 'RoleWarden Test'`. Il runner e la libreria si fermano se il `.env` non punta a Mailpit; S00 verifica i valori effettivi con `spark config:check` prima della prima richiesta HTTP. Mailpit deve essere vuoto all'avvio. A fine di ogni esecuzione tutti i messaggi catturati sono stati verificati: destinatari solo `*.test`, mittente `noreply@rolewarden.test` (`[MAIL] non-test=[]` in tutte le esecuzioni); poi Mailpit svuotato (0 messaggi alla fine). Nessun invio reale.
- `docker stop`/`docker start rolewarden-mail` solo nella fase 6, con la riaccensione in un blocco `finally`; container acceso alla fine.
- 19 cicli (13 esecuzioni finali più 6 preliminari o fermate da S00), tutti con dump identico prima e dopo, SHA256 `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`, database finale vuoto (`verify-v4.environment.txt`). Junction rimossa prima di `verify-v4.work`, cartella assente. Nessun php.exe o python.exe residuo.
- Un incidente mio, fuori dal perimetro dell'app: preparando la prova dell'IP ho usato una volta `taskkill /F /IM php.exe` per chiudere un server di prova su 8071, comando che chiude ogni php.exe della macchina. Non ho controllato prima se ne girassero altri; subito dopo non ne risultava nessuno attivo.
- Nessun commit, nessun push.

## Controllo visivo

Dovuto e non eseguito: la resa delle tre email in un client di posta (oltre alla lettura dei campi Text e HTML di Mailpit) e le schermate toccate in un browser vero.
