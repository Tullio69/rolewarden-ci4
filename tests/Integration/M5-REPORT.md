# M5 — collaudo indipendente

Target: master, HEAD `670dd97`. Sessione 2026-09-22, orario host.

**Esito: BLOCCATO. M5 NON approvata. Nessun controllo funzionale PASS/FAIL disponibile.**

Letti SPEC, BRIEF, log (inclusa voce Claude 08:10) e i tre contratti pubblici AuthorizationStore, Cache e ProtectionStore. Non letti controller, model, view, asset del pannello o test unitari. M4 risulta approvata nell'ultima voce del log (422 PASS storici, non risultati M5).

## Blocco

RW_DB_PASSWORD, RW_DB_USERNAME, RW_DB_HOSTNAME e RW_DB_PORT assenti dall'ambiente del processo; assenti anche dagli ambienti persistenti utente e macchina. Non sono state cercate credenziali nei file. Chiesto di rendere disponibili le variabili d'ambiente. Il preflight si ferma prima della connessione se manca la password; exit code 2 significa blocco, non difetto del prodotto.

Il terminale pwsh ha restituito CreateProcessAsUserW errore 5; Windows PowerShell funziona e supera questo problema operativo.

## Copertura reale

- HEAD richiesto verificato; lock inizialmente libero.
- Piano di casi derivato dai requisiti in `verify-m5.cases.md`; NON e' una suite automatizzata funzionale e non e' stato eseguito.
- `verify-m5.preflight.php` controlla disponibilita' credenziali e, solo se presenti, selezione esatta di rolewarden_test senza scritture.
- CRUD, autorizzazione HTTP/DOM, CSRF, matrice JavaScript, override view, protezioni e invalidazione: **NON ESEGUITI**.
- Query parametrizzate: non certificabili universalmente con sole prove black-box. I futuri tentativi di SQL injection sono evidenza comportamentale limitata.
- Nessun difetto di prodotto accertato; zero PASS funzionali non significano approvazione.

## Ambiguita' separate dai difetti

1. CSS scritto a mano invece di Tailwind: deviazione dichiarata da Claude, non approvata da questo collaudo. SPEC parla esplicitamente di CSS compilato incluso nel pacchetto e nessuna build obbligatoria per l'acquirente.
2. Creazione amministrativa attiva subito l'utente senza verifica email: scelta dichiarata da confermare, comportamento non ancora osservato indipendentemente.

## Integrita' e ripresa

Nessuna connessione DB, snapshot o scrittura dati; rolewarden e rolewarden_test non toccati. Nessuna copia temporanea o server creati: non occorre ripristino e non viene rivendicato un confronto snapshot eseguito. Nessuna modifica a src, SPEC o BRIEF, nessun commit.

Preesistevano non tracciati `verify-m5.php`, `verify-m5.md` e `verify-m5.output.txt`: non letti, non eseguiti, non modificati. I nuovi file hanno nomi distinti.

Per riprendere: rendere disponibili RW_DB_PASSWORD e RW_DB_USERNAME nel processo, opzionalmente RW_DB_HOSTNAME/RW_DB_PORT (default 127.0.0.1:3306), quindi eseguire `php tests/Integration/verify-m5.preflight.php`. Occorre ancora costruire ed eseguire il runner funzionale nella copia isolata secondo i casi documentati, con snapshot e ripristino esatto. Non basta il successo del preflight per approvare M5.

Data ripresa: 2026-09-22 08:01
## Ripresa del collaudo: verifica ambiente

HEAD verificato: `670dd97730bb121d06f2c355034ecec0fef96089`.
Esito: BLOCKED, M5 NON approvata. Nessun test funzionale eseguito in questa ripresa: 0 PASS / 0 FAIL funzionali. Non e' un difetto accertato del pannello.

Le variabili RW_DB_USERNAME, RW_DB_PASSWORD, RW_DB_HOSTNAME e RW_DB_PORT risultano tutte assenti dal processo degli strumenti. Controllo ripetuto con Windows PowerShell: assenti anche negli scope User e Machine. Sono state stampate esclusivamente informazioni booleane sulla presenza, nessun valore o segreto. Non e' stata tentata alcuna connessione a database, ne' eseguita alcuna scrittura dati. Snapshot e ripristino non eseguiti per blocco preliminare; nessuna app temporanea o server creati.

Restano NON VERIFICATI tutti i criteri funzionali richiesti: autenticazione/autorizzazione server e visibilita' UI; CRUD utenti/ruoli e protezioni M4; assegnazioni e override; matrice nel browser, salvataggio parziale ed ereditarieta'; elenco permessi in sola lettura; override view host; CSRF, parametrizzazione query e assenza di diagnostica SQL; revoca da pannello efficace nella stessa sessione. La parametrizzazione delle query non puo' essere certificata dalla sola assenza di errori nei tentativi di injection: occorre dichiarare il limite della verifica esterna nel collaudo successivo.

Ambiguita' separate dai difetti e non decise: CSS manuale rispetto a Tailwind; attivazione immediata dell'utente creato senza verifica email. Nessun difetto funzionale nuovo accertato.

Per riprendere occorre rendere disponibili le credenziali nell'ambiente effettivamente ereditato dagli strumenti Codex. Non inserirle nei file o nel report. Nessuna lettura dell'implementazione del pannello o dei test unitari; nessuna modifica a src/, SPEC o BRIEF; nessun commit.
