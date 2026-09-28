# V3 — Log attività: riverifica su `a602071`

**Esito: PASS. D1 corretto. V3 approvata per il collaudo HTTP.** Restano dovuti il controllo visivo in browser e le decisioni dell'autore su A1 e A2, sotto, non decise.

Commit riverificato: `a602071` (= `0e3d6f6` + correzione dei filtri), baseline `162ccd4`. Data: 2026-09-28. Collaudatore: Collaudatore ad Hoc (Claude), in sostituzione di Codex (quota esaurita), stesso protocollo: letti solo specifica, brief, README, `_AI-LOG.md`, contratti pubblici e `tests/Integration/`; nessun controller, model, view, sorgente vietato, test unitario o diff aperto.

## Totali della riverifica

Ogni suite eseguita due volte, un processo per ambiente (`verify-v3.run.py <modo> <ambiente>`), ciascuna con snapshot e ripristino propri. I conteggi per ambiente non coincidono con quelli di Codex, che eseguiva entrambi gli ambienti in un solo processo.

| Suite | development | production | Evidenza |
| --- | ---: | ---: | --- |
| V3 principale (`v3`) | 139/139 | 139/139 | `verify-v3.recheck.v3.*.output.txt` |
| V3 supplementare (`edges`) | 57/57 | 57/57 | `verify-v3.recheck.edges.*.output.txt` |
| Filtri array (`arrays`, nuova) | 55/55 | 55/55 | `verify-v3.recheck.arrays.*.output.txt` |
| Regressione V2 `test` adattata | 141/141 | 154/154 | `verify-v3.recheck.test.*.output.txt` |
| Regressione V2 `extra` adattata | 31/31 | 39/39 | `verify-v3.recheck.extra.*.output.txt` |
| Regressione V2 `adapt` adattata | 16/16 | 16/16 | `verify-v3.recheck.adapt.*.output.txt` |
| **Totale** | | | **899/899 PASS, 0 FAIL** |

Controllo di sensibilità, escluso dai totali: la suite `arrays` contro `0e3d6f6` dà **16 PASS / 39 FAIL** (`verify-v3.recheck.arrays.development.0e3d6f6.output.txt`), con HTTP 500 e `CRITICAL ... Array to string conversion` nel log sia su `/rolewarden/activity` sia su `/rolewarden/users`. La suite quindi rileva D1 e conferma che il difetto esisteva anche nell'elenco utenti prima della correzione.

## D1 — corretto

- URL del report (`user%5B_%5D=1`, `action%5B0%5D=x`, `from%5B0%5D=x`): HTTP 200 in development e production (anche Z10-5/6/7 della suite principale).
- Varianti su `/rolewarden/activity` per `user`, `action`, `from`, `to`, `page`, ciascuna nelle forme `[]=x`, `[0]=x`, `[_]=1`, `[nested][0]=x`; tutte le chiavi array insieme; array più `page[]=2` più un valore scalare invalido; scalare e array sulla stessa chiave in entrambi gli ordini.
- Le stesse 24 varianti su `/rolewarden/users` con `q`, `role`, `status`, `sort`, `page`.
- Risultato: 48/48 richieste HTTP 200 per ambiente, nessun errore PHP o SQL nella risposta; log applicativo a soglia 9 senza alcuna riga WARNING o superiore (nessuna esclusione) e log del server PHP (`error_reporting=-1`) puliti.
- Con valori normali i filtri funzionano ancora: `q=priya` filtra l'elenco utenti; `action=auth.login&user=priya` restituisce solo le righe dell'utente; un utente inesistente dà la lista vuota; tutti i controlli di filtro della suite principale (F*, LF*, S*) passano.

Nei log delle altre suite compaiono solo `SecurityException: The action you requested is not allowed`, provocate di proposito dalle prove CSRF senza token.

## Correzioni dello strumento in questa riverifica

- `verify-v3.run.py`: commit di destinazione `a602071`, sovrascrivibile con `RW_V3_NEW` solo per il controllo di sensibilità (etichetta di output con il commit); secondo argomento `development|production`. La modalità `arrays` era già predisposta nello script.
- `verify-v3.cases.php`: `rows3()` restituisce zero righe su corpo vuoto. Eseguendo `run3()` interamente in production, il 403 di P02 ha corpo vuoto e `DOMDocument::loadHTML('')` interrompeva lo script (primo tentativo `v3 production`, exit 255, ripristino comunque identico); dopo la correzione 139/139. Aggiunti in `arrays3()` i controlli `SCALAR-*` sui filtri con valori normali.
- Nessuna correzione riguarda il codice del modulo.

## Ambiente e pulizia

Stesso impianto della prima tornata: copia di `../rolewarden-app-test` con modulo da `git archive` agganciato via junction, server PHP riavviato a ogni scambio e chiuso a fine fase, porta 8070, `mail()` disabilitato e SMTP sul loopback porta 9, solo `rolewarden_test` su 127.0.0.1:3317, credenziali solo da variabili di processo. 14 cicli (12 validi, il tentativo interrotto e il controllo di sensibilità), tutti con dump identico prima e dopo, SHA256 `f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64`, database finale vuoto (`verify-v3.environment.txt`). Junction rimossa prima di `verify-v3.work`, cartella assente; nessun php.exe o python.exe residuo. Nessun commit, nessun push.

---

# Prima tornata (Codex, `0e3d6f6`)

# V3 — Log attività: collaudo indipendente

**Esito: FAIL. V3 non approvata per il collaudo HTTP.** Un difetto riproducibile: filtri con valori array provocano HTTP 500 ed errori PHP. Il controllo visivo in browser resta **dovuto**, fuori da questo incarico.

Commit collaudato: `0e3d6f6218cd3b20a75389c3dc01a662dd9bbafc`, coincidente con `HEAD` e `main`. Baseline: `162ccd4`, V2 chiusa. Data: 2026-09-28. Collaudatore: Codex.

## Totali finali

| Suite | Controlli | PASS | FAIL | Evidenza |
| --- | ---: | ---: | ---: | --- |
| V3 principale | 150 | 142 | 8 | `verify-v3.v3.output.txt` |
| V3 supplementare | 57 | 57 | 0 | `verify-v3.edges.output.txt` |
| Regressione `verify-v2.php` adattata | 188 | 188 | 0 | `verify-v3.test.output.txt` |
| Regressione `verify-v2.extra.php` adattata | 77 | 77 | 0 | `verify-v3.extra.output.txt` |
| Regressione `verify-v2.adapt.php` adattata | 25 | 25 | 0 | `verify-v3.adapt.output.txt` |
| **Totale** | **497** | **489** | **8** | |

Gli otto FAIL rappresentano **un solo difetto**: sei richieste negative, il controllo complessivo dei log e quello delle risposte HTTP. Probe, esecuzione preliminare e ripetizioni non si sommano ai totali finali. Le ambiguità non sono conteggiate come difetti né come PASS.

## Esito per punto richiesto

| Punto | Esito | Verifica ed eventuale limite |
| --- | --- | --- |
| 1. Aggiornamento, rollback, installazione nuova | **PASS** | Sostituzione cartella da `162ccd4`, `migrate -n RoleWarden`, concessione di `activity.view` ad admin e comparsa del menu. Prima di migrate il pannello funziona e le sessioni restano valide. Rollback per batch restituisce ACL e righe migrazione alla baseline, rimuove il log; ripristino cartella V2, remigrazione e installazione nuova riusciti. Anche development/production nella regressione adapt. U01–U08, A01–A07, U* adapt. |
| 2. Registrazione delle modifiche | **PASS nel perimetro delle azioni esposte** | Creazione/modifica/cancellazione di ruoli e utenti; attivazione/disattivazione; password amministrativa; assegnazione/revoca ruoli; concessione/revoca permessi di ruolo; override concesso, negato e rimosso. Anche impostazioni, revoche singole/totali proprie e altrui, browser ricordati propri/altrui, password dal profilo, registrazione Shield e logout. Autore, oggetto, timestamp, indirizzo e reperibilità con filtri combinati. E01–E27. **Nessuna certificazione del CRUD del catalogo dei permessi: vedere A2.** |
| 3. Accessi | **PASS** | Login riuscito, fallito su email registrata e fallito su email assente: tre nuove righe Shield, nessuna copia nel log del modulo. Ricerca per azione/utente/date, lista unificata ordinata dal più recente. L01–L06, LF-merge. |
| 4. Filtri | **PASS per i valori previsti** | Nome ed email; utenti eliminati dal pannello; `%` e `_` letterali sia negli eventi sia nei login Shield; filtri singoli e combinati; inclusione di 00:00:00 e 23:59:59 con esclusione dei giorni adiacenti, su entrambe le sorgenti; paginazione senza perdita di filtri né duplicati fra le due pagine provate; stato vuoto. F*, LF*, S*. Valori di tipo array: **FAIL D1**, riportato anche al punto 9. Vecchie email e cancellazione fisica: A1. |
| 5. Storico | **PASS per la leggibilità prescritta** | Nome dell'autore e dell'oggetto conservati dopo rinomina e cancellazione dal pannello. Verificata anche cancellazione fisica dell'autore: evento conservato, `actor_id` nullo, nome storico ancora ricercabile. S01–S08. Estensione della ricerca alle email storiche: A1. |
| 6. Conservazione | **PASS** | Opzioni esatte 90/365/0, default 365, salvataggio e rilettura; valori invalidi rifiutati sotto il controllo senza scritture né toast di validazione. Voci invecchiate a 89/91/364/366/10000 giorni: sopravvivenza/pulizia corretta. Lettura senza pulizia; prima scrittura con pulizia; seconda scrittura nello stesso giorno senza nuova pulizia. `auth_logins` identico prima/dopo. C01–C08. |
| 7. Permessi | **PASS** | Senza `activity.view`: menu e “See activity” assenti, GET diretto negato. Anonimo negato. `activity.view` da solo basta per leggere il registro. Il link nel dettaglio utente apre la lista con filtro corretto. P01–P05, LF-link. |
| 8. Regressione V2 | **PASS** | 290/290, con adattamenti dichiarati sotto. Nessuna modifica ai file originali V2. |
| 9. Pubblicazione | **FAIL — D1** | CSRF dei form e delle matrici verificato; nessuna mutazione riuscita senza token. Markup e caratteri speciali in nome ruolo, autore e ricerca sono escapati. Password e relativo hash non compaiono nei dettagli del log. Nessun errore SQL rilevato nelle risposte esaminate. Tuttavia i filtri array producono 500 in entrambi gli ambienti, pagina di errore PHP in development e sei errori CRITICAL nei log. |

Il PASS limitato del punto 2 **non risolve A2**: se il mandato comprende il CRUD delle definizioni dei permessi, quella parte resta non verificata e non può ricevere approvazione. Il punto 4 non certifica la robustezza degli input malformati, che fallisce in D1.

## Difetti

### D1 — I filtri activity accettano array fino a provocare un errore PHP

**Gravità: media; impedisce l'approvazione per i criteri di pubblicazione.** Nessuna valutazione della causa interna: controller e altri sorgenti vietati non sono stati aperti.

Precondizioni: installazione al commit richiesto, migrazioni applicate, utente autenticato con `activity.view`. Riproduzione indipendente, anche con il solo ruolo auditor:

1. Aprire normalmente `/index.php/rolewarden/activity`: HTTP 200.
2. Richiedere separatamente questi URL:

   ```text
   /index.php/rolewarden/activity?user%5B_%5D=1
   /index.php/rolewarden/activity?action%5B0%5D=x
   /index.php/rolewarden/activity?from%5B0%5D=x
   ```

3. Ripetere con `CI_ENVIRONMENT = production`, riavviando il server.

**Atteso:** gestione controllata dell'input non scalare, senza HTTP 500 né warning/errori PHP; nessun obbligo qui di un particolare codice 4xx o messaggio, non prescritto dalla specifica.

**Osservato:** HTTP 500 per tutte e sei le richieste. In development la risposta espone l'errore PHP; in production la risposta non espone lo stesso dettaglio, ma resta 500. Nei log a soglia 9:

```text
CRITICAL - ... --> ErrorException: Array to string conversion
```

Evidenza finale: `Z10-development-5/6/7`, `Z10-production-5/6/7`, `Z01`, `Z02` in `verify-v3.v3.output.txt`. Riproduzione completa: `python tests/Integration/verify-v3.run.py v3`.

Non è stato rilevato un errore SQL a schermo in questi casi: il difetto è di gestione dell'input e diagnostica PHP. I normali filtri testuali, le date malformate scalari e la stringa SQL di prova non producono questo errore.

## Ambiguità di specifica — separate dai difetti

### A1 — Quali email deve conservare la ricerca storica?

README promette ricerca per nome/email anche di utenti cancellati; la decisione V3 prescrive la copia del **nome** in `actor_label`, non dell'email dell'autore. La leggibilità dopo rinomina è soddisfatta.

Osservazioni:

- Un autore con email invariata, eliminato **dal pannello**, resta ricercabile per nome ed email; anche il suo login resta ricercabile per nome. Controlli supplementari `D1a–D1d` (gli ID di questi controlli diagnostici non indicano il difetto D1 sopra).
- Se prima della cancellazione si cambia `oldauthor@v3.test` in `newauthor@v3.test`, l'evento precedente si trova con il nome storico e l'email nuova, ma non con la vecchia email.
- Dopo una successiva cancellazione **fisica** via fixture SQL, il nome storico continua a funzionare e il riferimento all'autore diventa nullo; l'ultima email non ritrova l'evento.

Da decidere nella specifica: la ricerca deve conservare tutte le email usate nel tempo? La promessa comprende la cancellazione fisica, oltre a quella del pannello? Nessuna di queste estensioni è stata assunta per far passare o fallire il prodotto. Le due risposte vuote sono registrate come `[AMBIGUITY A1]`, non come asserzioni.

### A2 — CRUD del catalogo dei permessi o modifiche delle concessioni?

La sezione Pannello admin descrive l'elenco permessi come sola lettura “salvo i personalizzati”; il mandato cita creazione/modifica/cancellazione tra le modifiche da tracciare. La pagina `/rolewarden/permissions`, visitata come admin e super admin, non espone form né collegamenti per creare, modificare o cancellare definizioni di permesso, anche se mostra permessi personalizzati. Il selettore delle azioni del registro espone le concessioni/revoche di ruolo e gli override, non un CRUD delle definizioni.

Concessioni/revoche e override sono stati collaudati e passano. Non sono state inventate rotte né eseguite scritture SQL spacciandole per operazioni del pannello. Occorre precisare se il CRUD del catalogo rientra nel requisito V3 o se “modifiche ai permessi” significa modifiche di assegnazione. Nel primo caso manca la copertura e serve un percorso pubblico da collaudare; non viene certificata quella funzionalità.

### Questioni V2 non risolte da questo collaudo

La regressione continua a osservare il POST di revoca riuscito con `sessions.revoke` senza `sessions.view`, il rifiuto del super admin predefinito tramite toast e il rifiuto del POST parziale delle impostazioni. Non sono state introdotte nuove interpretazioni delle questioni V2 già registrate.

## Adattamenti delle suite e correzioni dello strumento

`verify-v3.run.py` genera copie temporanee degli script V2 e riusa client, fixture, funzioni HTTP, snapshot e runner esistenti. Gli originali non sono modificati.

- Trasporto obbligato a `127.0.0.1:3317`, database letterale `rolewarden_test`; percorsi di output rinominati `verify-v3.*`; cookie e file temporanei confinati alla copia. Credenziali ereditate dall'ambiente.
- Baseline aggiornata a `162ccd4`, destinazione `0e3d6f6`. L'aspettativa A01 “baseline senza permessi sessions” è **superata**: la baseline V2 li ha già; il controllo ora richiede l'assenza di `activity.view`.
- In A04 la tabella assente dopo rollback deve essere `acl_activity_log`, **non** `acl_sessions`, che appartiene alla baseline V2. L'uguaglianza dello stato ACL/migrazioni resta invariata.
- Gli altri controlli V2 restano invariati. I vecchi testi descrittivi V1/V2 stampati dalla suite adapt non identificano i commit realmente montati: le righe `module:` e i comandi migrazione documentano `162ccd4`/`0e3d6f6`.
- Il form Impostazioni è sempre riletto dall'HTML, quindi trasmette anche `activity_retention`; non si è eliminata la validazione nuova per favorire la regressione.

L'esecuzione preliminare `verify-v3.initial.output.txt` riportava 131 PASS/13 FAIL su 144. **Non è il verdetto finale**:

1. S01 confrontava il nome con virgolette contro JSON serializzato: confronto corretto sul testo della cella; ora PASS.
2. Due prove pretendevano ricerca per email storica senza un contratto inequivoco: spostate in A1, sostituite da controlli del nome storico e dell'email corrente. La cancellazione dal pannello senza cambio email è stata verificata separatamente: PASS.
3. Due prove di pulizia svuotavano solo la cache, senza simulare un nuovo giorno. La lettura SQL delle impostazioni della copia ha mostrato `activityPrunedOn`; la prova finale invecchia quel valore al giorno precedente e svuota la cache prima della richiesta. Non sono stati letti sorgenti per trovarlo. Pulizia e frequenza giornaliera: PASS.

Nessuna di queste correzioni riguarda il codice del modulo. Lo script finale restituisce esito non zero in presenza di FAIL; i vecchi output possono riportare exit del processo PHP 0 perché prodotti prima di questa correzione del runner. Fa fede anche il riepilogo delle asserzioni.

## Ambiente, sicurezza del collaudo e ripristino

- PHP **8.3.11**, CodeIgniter **4.7.4** come riportato da Spark. Questo incarico non certifica tutta la matrice delle versioni PHP/CI della pubblicazione finale.
- App sorella copiata senza `.env`, `.git`, `writable` o attraversamento delle junction. Moduli estratti con `git archive`; aggancio tramite junction sostituibile. Server PHP riavviato a ogni cambio cartella e chiuso a fine fase.
- Solo `rolewarden_test` su **127.0.0.1:3317**. Nessun uso delle porte 3306/3307 o del database `rolewarden`. Le menzioni delle vecchie porte negli script letti non sono state eseguite: i processi di collaudo usano le copie adattate.
- Credenziali soltanto da `RW_DB_USERNAME` e `RW_DB_PASSWORD`, passate ai processi, mai scritte in file. Protezioni sul nome del database sia nel client SQL sia nel `.env` della copia.
- Snapshot iniziale: database vuoto, senza tabelle, viste, routine o eventi. L'adattatore riusato interrompe l'esecuzione se questa precondizione non vale; non finge di preservare un database popolato.
- **Nove cicli isolati**, ciascuno con ripristino e confronto esatto del dump. SHA256 prima/dopo, identico in tutti:

  ```text
  f7f37c04ce251be86330196bed3e209bfeaff8fde5b8be79fd293aa09de21e64
  ```

  Evidenza: `verify-v3.environment.txt`. Database finale nuovamente vuoto.
- Una sola istanza di server, porta 8070. Invio locale `mail()` disabilitato nel server; SMTP della copia indirizzato al loopback, porta 9. Nessuna email reale.
- Junction rimossa prima della cartella temporanea. Verifica finale: `verify-v3.work` assente, nessun processo `php` o `python` residuo. App sorella non modificata.
- Solo file `tests/Integration/verify-v3.*`, questo report e una voce aggiunta in `_AI-LOG.md`. “Stato corrente” non modificato; il log aveva già modifiche all'avvio. Nessun sorgente vietato, test unitario o diff dei commit aperto. Nessun commit, nessun push.

## Ripetizione

Con credenziali già presenti nell'ambiente e database di test vuoto:

```powershell
python tests/Integration/verify-v3.run.py v3
python tests/Integration/verify-v3.run.py edges
python tests/Integration/verify-v3.run.py test
python tests/Integration/verify-v3.run.py extra
python tests/Integration/verify-v3.run.py adapt
```

Eseguire in sequenza. Ogni comando gestisce snapshot, copia, server, ripristino e rimozione della propria cartella. L'esito FAIL di V3 è atteso finché D1 rimane nel commit collaudato. Prima della chiusura della tappa restano la correzione/riverifica di D1, le precisazioni A1/A2 e il controllo visivo in browser.
