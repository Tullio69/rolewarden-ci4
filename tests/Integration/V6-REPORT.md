# V6 — Importazione CSV: collaudo indipendente

## Collaudo — 2026-10-05/06, Collaudatore ad Hoc (Claude), commit `98636ea`

**Esito: FAIL. Suite V6: 137 PASS / 6 FAIL su 143 controlli in ciascun ambiente (development e production), più 1 ambiguità non contata. I 6 FAIL vengono da due difetti: D1 (bloccante: oltre poche decine di righe l'importazione va in timeout, HTTP 500, con i 30 secondi standard di PHP) e D2 (le password non seguono tutte le regole di Shield). Regressione: V5 177/177 in entrambi gli ambienti; V2/V3/V4 682/683 in development e 703/704 in production, dove l'unico FAIL è V2 G07, già noto come superato da V5 (G07v5 passa). V6 non è approvata.** Tutto il resto della tappa regge: rifiuto per intero con l'errore sulla riga, nessuna scrittura parziale, anteprima che non scrive, conferma in transazione con ricontrollo dell'unicità, permessi, log, toast, tre temi in chiaro e scuro senza errori JavaScript, escape e CSRF.

Il collaudo sostituisce Codex, che non si avvia per un errore di configurazione del modello; protocollo identico. Ho letto solo `docs/SPEC.md` (Modello dati, Pannello admin con le decisioni V6 del 2026-10-05, Design system), `docs/BRIEF-v1.0.md`, `README.md` ("Importing users from CSV", "Settings and the default role", "Updating the module"), `_AI-LOG.md`, `src/Authorization/Contracts/` e `tests/Integration/`. HTML, CSS e JS solo come serviti via HTTP. Non ho aperto `resources/`, controller, model, view, `src/Users`, `src/Settings`, `src/Account`, `src/Config`, `src/Database`, `src/Language`, helper, test unitari né diff.

Ambiente: copia di `../rolewarden-app-test` in `tests/Integration/verify-v6.work`, modulo da `git archive` di `98636ea` (baseline `6c9fd04`; `bd7488e` e `3805640` per le suite di regressione, come in V5) agganciato con una junction; un server `php -S` alla volta con `error_reporting=-1`, `logger.threshold = 9`, `mail()` disabilitata, `php.ini` di sistema con `max_execution_time = 30` (valore standard). Solo `rolewarden_test` su 127.0.0.1:3317, credenziali solo in `RW_DB_USERNAME`/`RW_DB_PASSWORD`. Email solo verso Mailpit (SMTP 127.0.0.1:1026), destinatari `*.test`.

Comandi: `python tests/Integration/verify-v6.run.py v6 <ambiente>` (suite V6); `… v5|v4|test|extra|adapt|v3|edges|arrays <ambiente>` (regressione, asserzioni invariate). File: `verify-v6.php`, `verify-v6.upload.php` (upload multipart), `verify-v6.browser.mjs` (driver Chrome di V5 più il passo `upload` con `DOM.setFileInputFiles`), `verify-v6.run.py`. Evidenze: `verify-v6.<modo>.<ambiente>.98636ea.*` (output, log di fase, log applicativi e del server, schermate, CSV caricati dal browser), `verify-v6.environment.txt` (hash di ogni ciclo).

### Esito per punto del mandato

| # | Punto | Esito | Controlli |
| --- | --- | --- | --- |
| 1 | Aggiornamento da `6c9fd04` per sostituzione della cartella e `migrate -n RoleWarden`: `users.import` solo ad `admin`, nessuna tabella nuova, rollback esatto, installazione nuova | **PASS** | U01-U10 |
| 2 | Definizione di fatto: una riga non valida rifiuta il file con l'errore sulla riga e nulla scritto; un file valido crea gli utenti | **PASS** (ma vedi D1 sui file oltre ~50 righe) | D01-D06 |
| 3 | Formato: ordine libero, colonne obbligatorie/facoltative, sconosciuta/mancante/doppia, virgola e punto e virgola, BOM, UTF-8 non valido, vuoto, sola intestazione, virgolette, valori in più, 1000 righe e 1 MB | **FAIL** (D1: F13b, F13d) | F01-F18 |
| 4 | Righe: regole di Shield, unicità (anche maiuscole), ruoli, ruolo predefinito con e senza impostazione, password | **FAIL** (D2: R06d) | R01-R08b |
| 5 | Utenti creati: attivi, ruolo, email e username; accesso con la password del file; senza password nessun accesso noto e recupero con magic link; password mai in chiaro | **PASS** | I01-I09, P06 |
| 6 | Due passi: anteprima senza scritture, tutto o niente, conferma senza anteprima, token sbagliato, ripetuta, scaduta, utente creato da altri nel frattempo | **PASS** | T01-T12, D03, D05 |
| 7 | Permessi e log | **PASS** | A01-A06b, L01-L03 |
| 8 | Interfaccia: toast di errore e di esito, errori sotto il campo, tre temi in chiaro e scuro senza errori in console | **PASS** con ambiguità A1 | V01-V06, B-* |
| 9 | Regressione V5 e V2/V3/V4, development e production | **PASS** (G07 superato da V5, invariato) | vedi Totali |
| 10 | Pubblicazione: CSRF, escape, nessun SQL a schermo, log puliti, file malformati senza 500 | **FAIL** (P07-P09, solo per D1) | P01-P10 |

### Totali

| Suite | development | production |
| --- | ---: | ---: |
| V6 (`verify-v6.php`) | 137/143 | 137/143 |
| Regressione V5 (`verify-v5.php`, baseline `bd7488e`) | 177/177 | 177/177 |
| Regressione V4 (`verify-v4.php`, baseline `3805640`) | 232/232 | 232/232 |
| Regressione V2 `test` / `extra` / `adapt` | 140/141 + 31 + 16 | 153/154 + 39 + 16 |
| Regressione V3 principale / supplementare / filtri array | 139 + 69 + 55 | 139 + 69 + 55 |
| **Totale** | **996/1003** | **1017/1024** |

**Aspettative superate.** Nessuna nuova da V6. V2 G07 («la vista Impostazioni in sola lettura non ha pulsanti submit») resta superato da V5 per i pulsanti chiaro/scuro della barra in alto: non è stato modificato, e G07v5 nella suite V5 ne ricontrolla l'intento e passa. Le 4 ambiguità della suite V4 sono quelle già registrate in V4.

I 6 FAIL della V6 sono gli stessi nei due ambienti: F13b, F13d, P07, P08, P09 (tutti D1) e R06d (D2). Ambiguità V06 (A1) in entrambi.

### Difetti

**D1 (bloccante). L'importazione va in timeout ben prima delle 1000 righe promesse: HTTP 500 e nessun utente creato.**

Ogni riga costa circa mezzo secondo: la conferma di 20 righe senza password impiega 10 s (0,5 s a riga), l'anteprima di 20 righe con password 8 s (0,4 s a riga). Con i 30 secondi standard di `max_execution_time` il limite pratico è intorno alle 50-60 righe, contro le 1000 dichiarate da SPEC V6 e README ("at most 1000 users"). Il log del server riporta `PHP Fatal error: Maximum execution time of 30 seconds exceeded in …\vendor\codeigniter4\shield\src\Authentication\Passwords.php on line 40`, cioè il calcolo degli hash di Shield (bcrypt costo 12, ~0,23 s ciascuno su questa macchina; la conferma ne spende evidentemente più di uno a riga).

Riproduzione, identica in development e production:
1. accedere come admin, `/rolewarden/users/import`;
2. caricare un CSV `email,username,role,password` con 100 righe valide senza password (`hundN@v6.test,hund.N,,`): l'anteprima risponde in meno di un secondo, «100 users are ready to import»;
3. premere «Import 100 users»: dopo 30 s HTTP 500, nessun utente creato, `CRITICAL … Maximum execution time of 30 seconds exceeded` nel log applicativo;
4. con 1000 righe senza password la conferma fa lo stesso (F13b); con 1000 righe con password è già l'anteprima a dare HTTP 500 dopo 30 s (F13d).

Il "tutto o niente" regge anche qui (F13c: zero utenti creati, nessuna voce di log), ma l'utente vede una pagina di errore generica invece di un riscontro. P07 (HTTP 500 in tre pagine), P08 (tre righe CRITICAL) e P09 (fatal nel log del server) sono conseguenze dello stesso difetto.

**D2 (minore). Le password del file non passano da tutte le regole di robustezza di Shield.**

SPEC V6: la password «se c'è, segue le regole di robustezza di Shield». L'importazione applica lunghezza minima e dizionario (R06b, R06c), ma non il controllo sulle informazioni personali. Confronto con il modulo di registrazione di Shield sulle stesse password (R06d):

| Password | Username / email della riga | Importazione | Registrazione Shield |
| --- | --- | --- | --- |
| `r.sixd.r.sixd` | `r.sixd` | accettata | rifiutata |
| `v6test-r.reg.c` | `r.reg.c` / `…@v6.test` | accettata | rifiutata |
| `Abcdefgh`, `Qwerty123!`, `Tr0ub4dor&3x` | | accettate | accettate |

Riproduzione: CSV con `r6d@v6.test,r.sixd,,r.sixd.r.sixd` → anteprima «1 user is ready to import», la conferma crea l'utente con quella password.

**Osservazione minore, non contata come FAIL.** Quando il file è rifiutato per una ragione dell'intero file (colonna sconosciuta, file vuoto, codifica) il toast dice «The file was refused: nothing was created. Fix the rows marked below and upload it again.», ma non c'è nessuna riga segnata: l'errore è nel riquadro della sezione Preview. Il testo andrebbe adattato al caso.

### Ambiguità di specifica (non decise)

- **A1 (V06): dove stanno gli errori dell'intero file.** Il mandato chiede gli errori «sotto il campo del file»; SPEC V1 dice che gli errori di validazione di un campo restano nel modulo. Il file mancante e il file troppo grande (1 MB + 1 byte, 3 MB) hanno l'errore sotto il campo, con `aria-invalid` e `aria-describedby` (V05, F16, F17). Colonna sconosciuta, mancante o doppia, file vuoto, sola intestazione, UTF-8 non valido e limite di 1000 righe sono invece mostrati nella sezione C «Preview», dopo il modulo, più un toast di errore. Se questi siano errori del campo non lo decide la specifica.
- **A2: la conferma ricontrolla solo l'unicità.** SPEC V6 chiede di ricontrollare l'unicità tra anteprima e conferma, e lo fa (T10, T11). Un ruolo diventato super admin tra anteprima e conferma viene comunque assegnato (c6 creato con quel ruolo), un ruolo cancellato nel frattempo fa fallire la transazione senza scrivere nulla e senza 500 (T12), ma il log applicativo riceve due righe `ERROR` con il testo del vincolo di chiave esterna. Il ruolo predefinito tolto dalle Impostazioni nel frattempo resta quello calcolato all'anteprima. Se la conferma debba ricontrollare anche i ruoli non è scritto.
- **A3: scadenza dell'anteprima.** I 30 minuti del mandato non compaiono in SPEC né nel README. Verificati in nero invecchiando il campo dell'ora dell'anteprima nel file di sessione della copia: a 31 minuti la conferma è rifiutata («That preview is no longer valid»), a 29 minuti passa (T08, T09).
- **A4: ruolo scritto per nome o in altre maiuscole.** `Admin` invece dello slug `admin` è accettato. Anche intestazioni in altre maiuscole (`Email,USERNAME`), spazi attorno a nomi e valori, righe con meno valori delle colonne, righe vuote in mezzo e fine riga solo CR sono accettati. La SPEC parla di slug e di nomi delle colonne senza dire se il confronto distingue le maiuscole. Registrato, non deciso.

### Cosa è stato verificato e regge

- **Aggiornamento (U01-U10).** Cartella sostituita prima di `migrate`: elenco utenti 200 senza pulsante, pagina di importazione rifiutata senza 500. Dopo `migrate -n RoleWarden`, `users.import` solo ad `admin` (il super admin la vede comunque), nessuna tabella nuova. Rollback dell'ultimo batch: righe ACL, migrazioni e tabelle identiche a prima; la vecchia cartella torna a funzionare; installazione nuova e suo rollback puliti.
- **Formato (F01-F18).** Tutti i casi elencati dal mandato si comportano come da SPEC. 1000 righe accettate e 1001 rifiutate con un messaggio sul limite. Un file di esattamente 1 048 576 byte viene letto riga per riga, mentre a 1 048 577 byte arriva il rifiuto per dimensione sotto il campo, senza leggere righe. Anche colonna doppia, valori in più (errore sulla sua riga), UTF-16 e BOM con punto e virgola e CRLF si comportano come da SPEC.
- **Righe (R01-R08b).** Regole di Shield per email (anche > 254) e username (3-30, lettere/cifre/punti). Unicità contro gli utenti esistenti e dentro il file, anche con maiuscole diverse. Ruoli: inesistente, `super-admin` e ruolo personalizzato con flag super admin rifiutati. Ruolo vuoto: senza ruolo predefinito l'utente non riceve ruoli (README: `null`, nessun ruolo), con `V1 Parent` come predefinito riceve quello.
- **Utenti creati (I01-I09).** Gli utenti importati risultano attivi, con email e username come nel file (maiuscole comprese) e il ruolo giusto.
  - Con la password del file: è conservata solo come hash Shield che la verifica, e l'utente accede.
  - Senza password: hash casuale e diverso per utente; nessuna password nota funziona. Il magic link di Shield, catturato da Mailpit, fa entrare l'utente.
  - Nessuna password del CSV in chiaro (P06) in 480+ pagine viste, nei file di `writable/` (sessioni, log, debug toolbar, cache) o in qualunque tabella. La sessione dell'anteprima contiene solo hash.
  - Fuori perimetro V6: la registrazione di Shield usata per il confronto di D2 lascia la password rifiutata nell'old input della sessione. Si tratta del comportamento di Shield, non dell'importazione.
- **Due passi (T01-T12).** L'anteprima non scrive nulla. Vengono rifiutate senza scritture le conferme:
  - senza CSRF;
  - con token sbagliato (che invalida l'anteprima);
  - con il token usato da un'altra sessione;
  - ripetute;
  - senza anteprima o con token vuoto;
  - scadute.

  Un'email o uno username presi da altri tra anteprima e conferma fanno rifiutare l'intero file, e non viene creato nessuno degli altri. Il messaggio è «Some of these users were created after the preview…».
- **Permessi (A01-A06b).** Pagina, anteprima, conferma e file di esempio sono negati, senza scritture, a quattro profili: solo `users.view`; `users.create` senza `users.import`; admin con override negativo; nessun permesso. Il pulsante è assente per tutti e quattro. L'anonimo è rediretto al login. Il file di esempio ha l'intestazione documentata e un formato valido.
- **Log (L01-L03).** Ogni utente importato ha una sola voce `user.imported` con l'admin come autore. Gli utenti creati da altri non vi compaiono. Il filtro per azione della schermata Attività mostra solo «User imported».
- **Interfaccia (V01-V05, B-*).** Il toast d'errore compare sia statico sia in Alpine e resta finché non si chiude; dopo l'importazione compare il toast «Saved 2 users imported.». In Chrome headless, nei tre temi in chiaro e in scuro:
  - la pagina vuota, il file rifiutato (riga marcata, toast di errore attivo) e l'anteprima pronta rendono sui token;
  - non ci sono errori JavaScript;
  - nessun testo visibile è sotto 3:1.

  Nel browser l'attributo `required` ferma il modulo senza file.
- **Pubblicazione (P01-P06, P10).**
  - CSRF su upload e conferma; un upload senza CSRF viene rifiutato e non salva l'anteprima.
  - Il markup nei valori, nel ruolo, nel nome del file e in un nome di colonna viene reso con escape.
  - 17 file malformati (binario, NUL, virgolette aperte, campo da 900 KB, 5000 colonne, 3000 intestazioni, ZIP, HTML, tabulazioni, testo SQL, emoji…) non producono né 500 né testo SQL, e non scrivono nulla.
  - Ogni Chrome avviato è terminato.

Il controllo visivo in un browser vero resta dovuto all'autore. Le schermate `verify-v6.v6.*.shot-*.png` sono di Chrome headless.

### Pulizia

- **Database:** 25 cicli di snapshot e ripristino in `verify-v6.environment.txt`, tutti identici, SHA-256 `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`. `rolewarden_test` è vuoto alla fine, con lo stesso hash dello snapshot iniziale. `rolewarden` non è stato toccato. La prima esecuzione della regressione V5 in production è stata interrotta dalla chiusura della sessione prima dell'avvio del test e poi rifatta per intero; il database era già vuoto e identico allo snapshot.
- **Email:** solo Mailpit, tutti i destinatari `*.test`, mittente `noreply@rolewarden.test`; Mailpit vuoto alla fine.
- **Processi:** nessun `php.exe` o `python.exe` attivo; nessun Chrome con profilo del collaudo in esecuzione; P10 ha verificato la chiusura di ogni istanza avviata.
- **Cartelle:**
  - `tests/Integration/verify-v6.work` è stata rimossa. Era rimasta dalla sessione interrotta, senza junction (verificato: zero reparse point), ed è stata cancellata senza seguirne nessuna.
  - Restano i profili Chrome in `scratchpad/chrome6`, che Windows non lascia cancellare (accesso negato su `LOCK` e `*-journal`, come in V5), da rimuovere a mano.
  - Le sonde esplorative iniziali sono in `scratchpad/v6probe`, fuori dal repository. Il loro output è in `verify-v6.probe.development.98636ea.*`.
- Nessun commit, nessun push.
