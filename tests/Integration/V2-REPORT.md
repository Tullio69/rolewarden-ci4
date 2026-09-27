# V2 Account: collaudo indipendente

Commit collaudato: `5c7bfc5` su `main` (V2 `dd56279` + correzioni `a0327fe` e `5c7bfc5`).
Baseline di aggiornamento: `35f058f` (V1). Data: 2026-09-27.
Collaudatore: **Collaudatore ad Hoc (Claude)**, in sostituzione di Codex (quota esaurita), stesso
protocollo. Parte dal lavoro parziale di Codex, che il suo ambiente aveva bloccato.

## Riverifica su 37bb47f (2026-09-27, dopo la correzione di D1)

V2 = `5c7bfc5` + `37bb47f`. Stesso mandato, stesse regole, nessun file d'implementazione aperto. Di
`37bb47f` ho letto solo l'elenco dei file toccati e la sezione del README ammessa.

**Esito: PASS. 290/290.**

| Suite | Controlli | PASS | FAIL |
| --- | --- | --- | --- |
| Aggiornamento e ruolo predefinito (`verify-v2.adapt.php`, development e production) | 25 | 25 | 0 |
| Principale (`verify-v2.php`) | 188 | 188 | 0 |
| Extra (`verify-v2.extra.php`, development e production) | 77 | 77 | 0 |

- **D1: corretto, verificato**, in development e in production.
  - U02, sostituzione della cartella prima di migrate: login, pannello da connesso e home rispondono 200, senza testo SQL.
  - U04, rollback delle migrazioni V2 con la cartella V2 ancora al suo posto: stesso esito.
  - I log dell'app sono vuoti in entrambi i casi.
  - Nuovi controlli U02s, U04s e U05s: le tre pagine rispondono 200.
- **Chi naviga durante la finestra non viene più disconnesso** (U06, nuovo controllo, era un'osservazione). Dopo migrate, l'admin che ha usato il pannello nella finestra è ancora connesso e compare come "this browser". Resta valido anche U03: un admin connesso che non ha fatto richieste nella finestra resta connesso.
- **Nessuna regressione.**
  - Principale 188/188 ed extra 77/77, identici al giro su 5c7bfc5.
  - Nessun warning PHP nei log dei server.
  - La regressione V1 (`verify-v1.sh`, `verify-v1-m1.php`) non è stata rieseguita, perché il mandato non la chiedeva. Resta valido l'esito su 5c7bfc5 riportato sotto; `37bb47f` tocca solo `README.md` e `src/Account/Sessions.php`.
- **A7 (README "one option"): corretta.** La sezione "Settings and the default role" ora dice che la sezione **Roles** della schermata contiene il ruolo predefinito. Restano aperte, separate e non decise, le ambiguità A1-A6.
- Il runner (`verify-v2.run.py`) punta ora a `37bb47f`. Gli output `verify-v2.output.txt`, `verify-v2.extra.output.txt` e `verify-v2.adapt.output.txt` sono quelli della riverifica. Gli output di regressione sono ancora quelli del giro su 5c7bfc5.
- **Pulizia.**
  - Dump di `rolewarden_test` identico prima e dopo ognuna delle 3 esecuzioni (SHA256 `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`), 0 tabelle alla fine; 13 ripristini registrati in tutto, tutti identici.
  - Nessun `php.exe` o `python.exe` rimasto attivo; `verify-v2.work/` rimossa.
  - Nessun commit.

**Stato di approvazione: V2 APPROVATA per il collaudo HTTP.** Restano due cose:

- il controllo visivo in un browser vero di Profilo, Sessioni, Impostazioni/Sign-in e della sezione Sessioni del dettaglio utente, che non spetta a questo collaudo;
- le decisioni dell'autore sulle ambiguità A1-A6. Nessuna blocca l'approvazione. A2 non vincola più D1, perché ora il pannello funziona anche nella finestra.

---

## Collaudo su 5c7bfc5 (primo giro)

**Esito del primo giro: FAIL per un solo difetto minore (D1), confinato ai passaggi di aggiornamento
e di rollback. Tutti gli altri controlli erano PASS.** D1 è stato poi corretto in `37bb47f`: vedi la
riverifica sopra.

## Totali

| Suite | Script | Controlli | PASS | FAIL |
| --- | --- | --- | --- | --- |
| Principale (development, poi development e production per il punto 11) | `verify-v2.php` → `verify-v2.output.txt` | 188 | 188 | 0 |
| Extra (development e production, riproduzioni mirate) | `verify-v2.extra.php` → `verify-v2.extra.output.txt` | 77 | 77 | 0 |
| Aggiornamento e ruolo predefinito | `verify-v2.adapt.php` → `verify-v2.adapt.output.txt` | 17 | 15 | 2 (D1) |
| **Totale V2** | | **282** | **280** | **2** |
| Regressione `verify-v1.sh` (development) | `verify-v2.regression-v1.output.txt` | 177 | 176 | 1 (D04, già noto) |
| Regressione `verify-v1.sh` (production) | idem | 12 | 12 | 0 |
| Regressione `verify-v1-m1.php` (development) | `verify-v2.regression-m1.output.txt` | 58 | 48 | 10 (8 superati da V2, 2 = D1) |

Esecuzione: `RW_DB_USERNAME=... RW_DB_PASSWORD=... python tests/Integration/verify-v2.run.py test|extra|adapt|regression`.
Ogni modo crea una copia isolata di `../rolewarden-app-test` in `tests/Integration/verify-v2.work/`,
estrae `35f058f`, `5c7bfc5` e `3631fd2` con `git archive`, aggancia il modulo con una junction
sostituibile, fotografa `rolewarden_test` e la ripristina alla fine.

## Esito per punto

| # | Punto | Esito | Controlli |
| --- | --- | --- | --- |
| 1 | Aggiornamento 35f058f → 5c7bfc5, permessi, rollback, installazione nuova | PASS (con D1 nella finestra tra sostituzione e migrate) | A01-A08, U01, U03, U05 (dev e prod) |
| 2 | Sessioni proprie: elenco, uscita singola, "everywhere else", revoca con Remember me | PASS | B00-B07, B01b, B02b |
| 3 | Browser ricordati senza sessione: si dimenticano e non rientrano | PASS | C01, C02 |
| 4 | Sessioni altrui: view, revoke sulla sessione giusta, rotte negate anche via POST | PASS (vedi A1) | D01-D08, D05a, D05b, X04 (12) |
| 5 | Inattività: scadenza, rientro con token, sessione nuova subito elencata e revocabile | PASS | E00-E05, X01-X03 (dev e prod) |
| 6 | Profilo: cambio password, errori sotto il campo, altre sessioni chiuse | PASS | F01-F07 (con 4 "b"), F06b |
| 7 | Impostazioni Sign-in: valori, intervalli, errori, persistenza, Remember me, permessi | PASS | G01-G08 (38), R01-R06 |
| 8 | Throttling: limite IP, blocco per email, stesso esito registrate/non, sblocco | PASS | H01-H07, H02b |
| 9 | Toast su ogni nuova azione, CSRF in production compreso; errori di campo nel modulo | PASS | T01-T08, X06t (11), X07t (4), F01-F04, G06 |
| 10 | Regressione V1 | PASS (nessuna regressione del modulo; vedi sotto) | v1 176/177 + 12/12, m1 48/58 spiegati |
| 11 | CSRF, escape, nessun errore SQL a schermo, log puliti | **FAIL (D1)**; tutto il resto PASS | J01-J08, X05, X06, X08, X09, U02, U04 |

### Dettaglio

1. **Aggiornamento.** Installazione per README a 35f058f (tre batch), sostituzione della junction con
   5c7bfc5, `php spark migrate -n RoleWarden`: nuovo batch con `CreateSessions` e
   `AddSessionPermissions`; `admin` ottiene `sessions.view` e `sessions.revoke` (A02), la tabella
   `acl_sessions` esiste (A03). `migrate:rollback -b 3` riporta `acl_*` e `migrations` identici alla
   baseline e toglie la tabella (A04); la migrazione si riapplica (A05). Installazione nuova a 5c7bfc5:
   permessi presenti (A06), rollback `-b 2` toglie tutte le tabelle del modulo (A07), poi tutta la suite
   gira su quell'installazione. Un admin connesso prima dell'aggiornamento, che non fa richieste
   durante la finestra, resta connesso e compare subito nelle sue sessioni come "this browser" (U03,
   dev e prod). Rimesso il modulo V1 dopo il rollback, login, pannello e home rispondono 200 (U05).
2. **Sessioni proprie.** Ogni accesso crea esattamente una riga (B00). L'elenco offre "Sign out" per
   ogni altro browser e nessuno per quello corrente (B01b). La riga da revocare è scelta per l'id che il
   suo login ha creato, non per posizione. Dopo la revoca sparisce solo quella riga (B02b), il browser
   ricordato revocato non rientra e il suo token è cancellato (B03, B04), mentre chi agisce e il terzo
   browser restano (B05). "Sign out everywhere else" lascia attivo chi lo chiede e chiude tutti gli
   altri, con zero token (B06, B07).
3. **Browser ricordati senza sessione.** "Forget" su un token orfano: il token sparisce e il browser non
   rientra (C01). Revocare il token di un browser con una sessione aperta chiude anche quella sessione
   alla richiesta successiva (C02).
4. **Sessioni altrui.** Con `sessions.view` la sezione si vede, senza moduli di revoca (D02); senza,
   manca (D03). La revoca dell'admin sceglie la sessione per id (D05a). Solo quella sessione e il suo
   token spariscono, l'altro browser del bersaglio resta connesso (D05, D05b), e la revoca totale lascia
   il bersaglio senza sessioni e senza token (D08). Tutti i POST di revoca dell'admin, sessione singola,
   token ricordato e revoca totale, sono negati senza `sessions.revoke`, sia con solo view sia senza
   permessi (D04, X04 ×12): stato invariato, risposta diversa da 200 e rifiuto non dovuto al CSRF. Vedi A1.
5. **Inattività.** Una sessione ferma da 100 minuti, sotto le 2 ore di default, resta valida (E00).
   Oltre la durata, la sessione non ricordata termina (E01). Quella ricordata rientra con un nuovo
   cookie di sessione (E02, E03), e la nuova riga è già in `acl_sessions` ed elencata nel dettaglio
   admin nella stessa richiesta del rientro (E05). Una revoca totale fatta subito dopo nega la
   richiesta successiva (E04). Stesso esito con durata 30 minuti e riga invecchiata di 31, in development
   e in production (X01-X03).
   **Affermazione dello sviluppatore su E04/X02/X03 (a0327fe): verificata.** Su dd56279 Codex aveva
   osservato la nuova sessione tracciata solo alla richiesta successiva; ora compare subito.
6. **Profilo.** Password attuale sbagliata, nuova password debole, conferma assente o diversa: tutti
   rifiutati con "Error:" sotto il campo, nessun toast, password invariata (F01-F04 e F01b-F04b). Cambio
   riuscito: toast di esito (T06), chi lo chiede resta connesso (F05), gli altri browser, ricordati e
   non, sono chiusi e resta una sola riga senza token (F06, F06b), la nuova password funziona e la
   vecchia no (F07).
7. **Impostazioni, Sign-in.** Valori iniziali 2 ore e 30 giorni (G01). Durata sessione 30 min, 2 h,
   8 h e 24 h, e Remember me spento, 7, 30 e 90 giorni: tutti salvati e riletti (G02). Con Remember me
   spento l'opzione sparisce dal login, altrimenti l'etichetta dice "Remember me for N days" (G03). La
   scadenza del token corrisponde alla durata scelta, entro 90 secondi (G04). Con Remember me spento,
   un `remember=on` forzato non crea token (G04-0). Estremi accettati: tentativi 3 e 20, minuti 1 e 1440,
   richieste per IP 5 e 100 (G05). Fuori intervallo o non numerici (13 casi): nessuna scrittura,
   "Error:" sotto il controllo, nessun toast (G06). Con solo `settings.view` non c'è pulsante di
   salvataggio e il POST diretto non scrive (G07, G08). Il ruolo predefinito si salva ancora col
   modulo completo V2 e vale per chi si registra via Shield e per chi è creato dal pannello
   (R01-R04). Un super admin come predefinito è rifiutato (R05). Un POST parziale non scrive nulla e
   mostra errori sui campi mancanti (R06).
8. **Throttling** (3 tentativi, 1 minuto). Tre fallimenti, poi la password giusta: bloccato, "Too many
   failed sign-ins for this email. Try again in 1 minute." (H01, H02). Ancora bloccato 30 secondi dopo
   (H02b), sbloccato a durata trascorsa (H03). Per un'email non registrata compare lo stesso identico
   messaggio (H05), e a durata trascorsa torna il messaggio normale di credenziali errate (H04). Un
   accesso riuscito azzera il conteggio (H07). Con limite per IP a 5, cinque POST arrivano a Shield
   (una riga in `auth_logins` ciascuno) e il sesto è respinto prima, con "Too many sign-in attempts
   from this address. Wait a minute and try again." (H06).
9. **Toast.** Toast di esito "Saved" per l'uscita singola, "everywhere else", "Forget", la revoca
   singola e totale dell'admin, il cambio password e il salvataggio delle impostazioni (T01-T07). Sono
   verificati sia nella forma statica senza JavaScript sia in quella passata ad Alpine. Toast di errore
   con `role="alert"`, in production, per ogni POST rifiutato dal CSRF: 14 moduli nella suite
   principale (T08) e 11 nella extra (X06t). Toast di errore anche per la revoca di una sessione altrui
   o inesistente: "That session has already ended. Reload the page to see the current list." (X07t).
   Gli errori di validazione di un campo restano sotto il campo (F01-F04, G06).
   **Affermazione dello sviluppatore su X06-production (5c7bfc5): verificata.**
10. **Regressione.** `verify-v1.sh` gira invariato in Git Bash: 176/177 in development, con il solo
    D04 già noto e superato da V1, e 12/12 in production. `verify-v1-m1.php` gira invariato da una copia
    (per non appendere al suo log committato), in development: 48/58. I 10 FAIL si spiegano così.
    D04-D08, F01 e F02 postano `default_role` da solo, e il modulo V2 rifiuta il POST perché mancano i
    campi Sign-in: è l'aspettativa V1 a essere superata, e il comportamento equivalente è ricontrollato
    con il modulo completo in R01-R06, tutto PASS. G05 si aspetta l'assenza delle righe "Session",
    che arrivano proprio con V2. G01 e G02 sono D1 (rollback con cartella V2).
    Nota: il primo giro di m1 era partito in production perché `verify-v1.sh` lascia il `.env` della
    copia su production. È un errore del runner, corretto: m1 ora riparte in development.
11. **Pubblicazione.** Ogni modulo nuovo (sessioni proprie, token ricordati, revoca totale, profilo,
    impostazioni, azioni admin) porta `csrf_test_name` (J01, X05). Senza token, in development risponde
    403 e in production torna indietro con il toast di errore, sempre senza modifiche a sessioni, token
    o impostazioni (J02, X06). Il nome utente ostile nel profilo è escapato (J03). Uno user agent ostile
    non è mai reso crudo nelle due liste di sessioni (J07, vedi limiti). Id malformati nelle rotte di
    revoca: rifiutati senza testo SQL (J08). Nessun testo SQL o errore PHP nelle risposte catturate
    (J04, X08). Log dell'app puliti a soglia 9 e con `error_reporting=-1`, a parte i rifiuti CSRF
    provocati (J05, X09, R07). Nessun warning o notice nei log del server PHP (J06).
    **FAIL solo per D1.**

## Difetti

### D1 (minore): nella finestra di aggiornamento e dopo il rollback, chi è connesso riceve un 500 che in development mostra l'errore SQL

Se la cartella del modulo è V2 ma la tabella `acl_sessions` non c'è, ogni richiesta al pannello di un
utente connesso va in errore. Succede tra la sostituzione della cartella e `php spark migrate`
(l'ordine del README), e dopo il rollback delle migrazioni V2 con la cartella V2 ancora al suo posto.

- In development la pagina mostra l'eccezione con
  `Table 'rolewarden_test.acl_sessions' doesn't exist`. Questo viola "Mai mostrare errori SQL a
  schermo, in nessun ambiente" (CLAUDE.md) e "Nessun errore SQL a schermo in nessun percorso, compresi
  quelli di fallimento" (definizione di fatto).
- In production la pagina è generica, senza SQL, ma lo stato è sempre HTTP 500. Il log registra
  `ERROR`/`CRITICAL DatabaseException ... acl_sessions doesn't exist`.
- Login anonimo e home dell'ospite, anche da connessi, rispondono 200: il guasto riguarda il pannello
  per gli utenti connessi.
- Chi ha usato il pannello durante la finestra viene disconnesso dopo la migrazione (302 al login,
  nessuna riga). Chi non ha fatto richieste nella finestra resta connesso (U03).

Riproduzione (automatizzata in `verify-v2.adapt.php`, U02 e U04):

1. Installa 35f058f per README e accedi al pannello come admin.
2. Sostituisci la cartella del modulo con 5c7bfc5 e riavvia il server. Non lanciare migrate.
3. Con lo stesso browser apri `/rolewarden/users`: HTTP 500; in development la pagina contiene il
   messaggio SQL.
4. Oppure: dopo `migrate`, `php spark migrate:rollback -b <batch V1>` con la cartella V2 ancora in
   posto, poi apri `/rolewarden/users` da connesso. L'esito è lo stesso.

Il comportamento "giusto" in quella finestra non è deciso: vedi A2. Il difetto, in senso stretto, è
il testo SQL a schermo.

## Ambiguità di specifica (non decise)

- **A1. Revoca con solo `sessions.revoke`.** La specifica dice "Vedere e revocare le sessioni altrui
  richiede `sessions.view` e `sessions.revoke`". Un utente con `users.view` e `sessions.revoke`, senza
  `sessions.view`, non vede la sezione (D06). Però un POST diretto sull'azione di revoca, con un id
  valido, riesce: HTTP 303 e sessione rimossa (INFO D07). Non è deciso se revocare richieda entrambi i
  permessi o solo `sessions.revoke`.
- **A2. Il pannello durante la finestra di aggiornamento.** README: "Replace the module's folder …,
  then run migrate". Non dice se tra i due passi il pannello debba restare utilizzabile, né se un
  rollback delle migrazioni lasciando la cartella nuova sia uno stato supportato. Dall'esito dipende se
  basta togliere il testo SQL (D1) o se serve anche evitare il 500 e la disconnessione.
- **A3. Errore del ruolo predefinito "super admin".** È mostrato come toast di errore, non sotto il
  controllo (R05). La decisione V1 dice che gli errori di validazione di un campo restano nel modulo,
  e il componente Settings vuole "Error: …" sotto il controllo. È la stessa questione aperta (4) del
  collaudo V1, qui estesa a un rifiuto sul valore di un campo.
- **A4. Tentativi rimanenti al login.** Il componente Login dice che il blocco di errore "names the
  remaining attempts". Il messaggio osservato ("Unable to log you in. Please check your credentials.")
  non li nomina, anche se ora il blocco per email li renderebbe calcolabili. Nominarli è compatibile con
  la decisione A1 del 2026-09-23, perché il blocco vale anche per le email non registrate. Da decidere
  se è dovuto in V2.
- **A5. POST parziale delle Impostazioni.** Un POST con solo `default_role` è rifiutato, con errori
  sui cinque campi Sign-in mancanti e nessuna scrittura (R06). Prima, un POST senza un campo salvava
  "None" (questione (3) del collaudo V1). È sensato, ma nessun documento lo fissa.
- **A6. Durata predefinita di Remember me.** Codex si aspettava 30 giorni (G01, PASS), il default di
  Shield. La SPEC elenca le scelte ma non il valore di partenza, e il README rimanda a
  `RoleWarden\Config\RoleWarden`, che il collaudo non può leggere.
- **A7. Documentazione.** Il README "Settings and the default role" dice ancora "In this version the
  screen holds one option", mentre la sezione successiva e la decisione V2 aggiungono le regole di
  Sign-in (osservazione di Codex, confermata). *Corretta in 37bb47f: vedi la riverifica.*

## Aspettative di Codex corrette o rafforzate (non cambiate in silenzio)

- **D05.** Codex premeva il primo modulo "Sign out" del dettaglio admin, senza sapere di quale sessione
  fosse. Ora la sessione è scelta per l'id creato dal suo login (D05a), e si verifica che sparisca solo
  quella col suo token mentre l'altro browser resta (D05b). La spiegazione dello sviluppatore (un FAIL
  precedente di D05 dovuto allo script) è **plausibile, ma non dimostrabile**: l'output di quel
  passaggio non esiste più, e nell'ultimo output di Codex D05 era PASS.
- **Punto 9.** La suite di Codex non controllava i toast delle azioni nuove: aggiunti T01-T08, X06t e X07t.
- **Controlli aggiunti.** B00, B01b e B02b, cioè la scelta della riga e l'elenco. E00, H02b e H07
  come controlli negativi, E05 per l'elenco del rientro, F06b per il browser non ricordato, G04-0,
  J07 e J08.
- **Runner.** Revisioni aggiornate a 5c7bfc5; un log del server per modo; nuovi modi `adapt` e
  `regression`, che eseguono gli script V1 senza modificarli. Il `probe` di Codex non è più usato, e
  `verify-v2.probe.output.txt` resta come suo artefatto su dd56279.
- **Errori miei, corretti prima dell'esito.** Il primo giro di m1 era in production (vedi punto 10).
  In U05 il server restava acceso durante lo scambio della junction: `php -S` teneva la cache dei
  percorsi, e ne veniva un falso "Class SignInThrottle not found". Ora il server si riavvia a ogni
  scambio. In R03 ho usato uno username con trattino, che Shield rifiuta.

## Limiti

- J07: lo user agent non compare testualmente nelle liste (né crudo né come testo escapato),
  presumibilmente viene riassunto. L'escape è quindi verificato come "mai reso crudo", non
  osservandone la forma escapata.
- La finestra di aggiornamento è provata con `php -S` riavviato; su Apache/FPM con opcache potrebbe
  comportarsi diversamente.
- Nessuna email: le email di sicurezza sono V4, fuori tappa.
- Controllo visivo in browser: **dovuto**, non eseguito da questo collaudo.

## Ambiente e pulizia

- PHP 8.3.11, CodeIgniter 4.7.4 (app di prova copiata), MariaDB `rolewarden-db` su 127.0.0.1:3307, solo
  `rolewarden_test`. Il database `rolewarden` e la porta 3306 non sono stati toccati.
- Credenziali solo in `RW_DB_USERNAME`/`RW_DB_PASSWORD` di processo, mai in file o output.
- `rolewarden_test` era vuoto all'inizio (0 tabelle). Il dump canonico, SHA256
  `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`, è identico prima e dopo ognuna
  delle 10 esecuzioni (`verify-v2.environment.txt`), e alla fine ha di nuovo 0 tabelle.
- Un solo server `php -S` su 8070 alla volta, chiuso a fine fase. Alla fine non restano `php.exe` o
  `python.exe`. `verify-v2.work/` è stata rimossa dopo aver tolto la junction.
- Restano in `%TEMP%` 26 file cookie `rwv1-*.jar`, sessioni di prova ormai morte: 24 lasciati dagli
  script V1 durante la regressione, 2 da Codex. La loro cancellazione è stata negata dai permessi
  dell'ambiente; vanno tolti a mano.
- Nessun file modificato fuori da `tests/Integration/verify-v2.*`, questo report e la voce in
  `_AI-LOG.md`. Nessun commit, nessun push.
