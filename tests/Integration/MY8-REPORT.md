# MY8 - RoleWarden su MySQL 8 (Collaudatore ad Hoc)

Commit collaudato: `22bfc01` su `main`, in un worktree separato (rimosso a fine collaudo).
Domanda: la scheda CodeCanyon può dire "MySQL or MariaDB with InnoDB"? Finora ogni collaudo era su MariaDB 11.8.9.

## Esito

| Server | Controlli | PASS | FAIL | Esito |
|---|---|---|---|---|
| MySQL 8.4.13 (`mysql:8.4`, LTS), run r2 | 91 | 89 | 2 | **FAIL** (un difetto, sotto) |
| MySQL 8.0.39 (`mysql:8.0`) | 91 | 89 | 2 | **FAIL** (lo stesso difetto) |
| MariaDB 11.8.9 (confronto, fasi A, X, Z) | 18 | 18 | 0 | PASS |

I due FAIL di ogni versione sono **lo stesso difetto**: X02b è la risposta 500 e Z01 è la sua riga SQL nel log.
Tutto il resto è PASS su entrambe le versioni di MySQL:
- installazione a mano e da browser;
- rollback del lotto di RoleWarden;
- tutti i percorsi del pannello, verificati con query;
- integrità (charset, chiavi esterne, cascade, testo non ASCII);
- production senza SQL a schermo, anche a database spento;
- nessun errore `ONLY_FULL_GROUP_BY`, di collation o di valori stretti nei log, a parte il difetto.

Il primo run su 8.4 (r1, `verify-my8.84-r1.*`) aveva 4 FAIL: due erano un difetto dello strumento (sotto). Il run r2
è identico a quello di 8.0.

**Stato di approvazione: non approvabile così com'è.** Dopo la correzione del difetto serve una riverifica di X02b e Z01 su 8.4 e 8.0.
Il resto della frase "MySQL or MariaDB with InnoDB" regge: MySQL 8 con `sql_mode` di serie
(`ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`),
collation di serie `utf8mb4_0900_ai_ci`, utente applicativo con il plugin di autenticazione di serie (`caching_sha2_password`).

## Difetto del modulo

**D1 - Activity: una data impossibile scritta nell'indirizzo dà 500 su MySQL 8 (su MariaDB no).**

Passi per riprodurlo:
1. Installare su MySQL 8.4 o 8.0 con la configurazione di serie dell'immagine.
2. Entrare con un utente che ha `activity.view`.
3. Aprire `/rolewarden/activity?from=2026-13-45` oppure `/rolewarden/activity?to=2026-02-30`.

Risultato:
- **HTTP 500**: in production la pagina d'errore generica, senza SQL a schermo; in development la pagina di debug.
- La stessa richiesta su MariaDB 11.8.9 risponde 200.
- `from=abc&to=xyz` e un periodo valido rispondono 200 su tutti e tre i server.

Messaggio dal log (`writable/logs/`, MySQL 8.4 e 8.0 uguali):
```
ERROR - ... --> Incorrect DATETIME value: '2026-13-45 00:00:00'
CRITICAL - ... --> CodeIgniter\Database\Exceptions\DatabaseException: Incorrect DATETIME value: '2026-13-45 00:00:00'
CRITICAL - ... --> [Caused by] mysqli_sql_exception: Incorrect DATETIME value: '2026-13-45 00:00:00'
ERROR - ... --> Incorrect DATETIME value: '2026-02-30 23:59:59'
```
Nella pagina il campo è `<input type="date">`, quindi il caso nasce solo da un indirizzo scritto a mano o da un link
costruito male. Resta comunque un 500 e un errore SQL, contro "Mai mostrare errori SQL" e il divieto di 500 sugli input.
Il difetto è specifico di MySQL: MySQL 8 rifiuta il confronto tra una colonna DATETIME e un letterale impossibile anche
con le modalità strict tolte dalla connessione di CodeIgniter (`strictOn = false`); MariaDB lo accetta con un warning.

## Copertura (uguale su 8.4 e 8.0)

- **A, installazione a mano** (installation.html#migrations). Migrazioni di Settings, Shield e RoleWarden in tre lotti
  distinti (1, 2, 3), `RoleWardenSeeder`, `demo:install` (lotto 4), primo super admin con `shield:user create/activate`
  e la INSERT documentata. Il super admin e il lettore della demo entrano; le schermate principali rispondono 200.
- **RB, rollback.** `migrate:rollback -b 2` toglie tutte le tabelle `acl_`; tabelle, utenti e identità di Shield e
  Settings restano identici; poi `migrate -n RoleWarden` e il seeder rimettono tutto e il pannello torna in uso. Nota: con
  la demo installata dopo RoleWarden il rollback toglie anche il lotto della demo, come dice la documentazione ("undoes
  whole batches").
- **B, installer da browser** su database vuoto. L'app di prova è "il modulo in un'applicazione esistente non ancora
  collegata". In development l'installer risponde 500 prima delle tabelle, ed è il sintomo documentato in
  troubleshooting.html. Quindi l'installer è stato aperto in production, come suggerisce la documentazione:
  - codice di installazione accettato;
  - password del database sbagliata rifiutata, senza dettagli a schermo;
  - connessione con l'utente MySQL 8 (`caching_sha2_password`) accettata e `.env` scritto;
  - tabelle e ruoli di sistema creati;
  - primo super admin creato, lock scritto, codice cancellato, nessuna seconda esecuzione.
- **P, pannello in uso**, ogni passo verificato con query.
  - Dashboard a 7 e 30 giorni: accessi, accessi falliti e utenti attivi uguali alle query su `auth_logins` e `users`, con
    18 accessi retrodatati fino a 60 giorni. Funziona anche a 90 giorni.
  - Utenti:
    - creazione, modifica, attivazione e disattivazione;
    - eliminazione: logica, con `deleted_at` e assegnazioni tolte;
    - ricerca, anche con `%` come testo, e filtri per ruolo e per stato;
    - ordinamento per "Last active" asc/desc, coerente con `users.last_active`;
    - import CSV: anteprima senza scritture, conferma di 3 utenti con i loro ruoli e `user.imported`, file con un'email
      già registrata rifiutato per intero.
  - Ruoli: creazione con padre, modifica, ciclo rifiutato, matrice (cella singola, riga intera "users", colonna intera
    "create" dal browser), eliminazione logica.
  - Override e precedenza: una concessione apre una schermata, una negazione la chiude; la negazione batte anche un super
    admin; togliere la negazione ridà la schermata. Check a permission conferma la regola che decide, cercando l'utente
    per id, per username e per email.
  - Utente limitato e app di prova: filtro `can:` su una rotta dell'app (ospite verso il login, chi ha il permesso 200,
    chi non lo ha rimandato); gli helper `can`, `can_any`, `can_all` e `permissions()` coerenti.
  - Ricerca comandi: il super admin trova `users.view`; all'utente limitato non sono offerte le Impostazioni.
  - Activity: filtri per azione, per utente + azione e per periodo, con conteggi uguali al database.
  - Impostazioni:
    - blocco dopo 3 tentativi: anche la password giusta è rifiutata e l'avviso di sicurezza compare in dashboard;
    - limite di 5 per indirizzo: oltre, anche la password giusta è rifiutata.
  - Sessioni: revoca singola (riga tolta, l'altro browser va al login) e "sign out everywhere else".
  - Appearance: salvataggio di Clarity dal browser in `theme.json` e reset; chiaro/scuro salvato per utente con Settings.
- **I, integrità.**
  - Tabelle: tutte InnoDB e `utf8mb4`. Le tabelle sono in `utf8mb4_general_ci`, dalla configurazione dell'app; il
    database è in `utf8mb4_0900_ai_ci` e la differenza non ha dato errori di collation.
  - Chiavi esterne come dice la SPEC:
    - verso `users`: CASCADE, tranne `acl_activity_log.actor_id`, che è SET NULL;
    - verso `acl_roles`: CASCADE, tranne `parent_id`, che è SET NULL.
  - `DELETE FROM users`: ruoli, override e sessioni se ne vanno in cascata; il log resta con `actor_id` NULL; il browser
    dell'utente esce alla richiesta successiva.
  - `DELETE FROM acl_roles`: concessioni e assegnazioni se ne vanno in cascata; il figlio resta senza padre e la sua
    pagina si apre. Nessuna riga orfana.
  - Ruolo con accenti, CJK ed emoji a 4 byte: salvato byte per byte e mostrato correttamente. Ricerche e filtri con emoji:
    nessun errore. Import UTF-8 con BOM: funziona.
- **X, production.** Le 24 schermate rispondono 200. Indirizzi strani e id inesistenti: niente 500, salvo D1. Valori
  troppo lunghi e chiavi duplicate: nessun 500 e nessun SQL. A database fermo (`docker stop`): 500 generico, senza
  dettagli; il pannello si riprende quando il database torna.
- **Z, log.** Esclusi gli errori di connessione provocati apposta (password sbagliata in B04, server fermo in X04),
  l'unico errore SQL di tutto il run è D1.

## Ambiguità di specifica (non decise)

1. **Lunghezze massime.** Un ruolo con slug di 304 caratteri, nome di 300 e descrizione di 80 KB, oppure un utente con
   username ed email di 300 caratteri, risponde 303 senza errore; il ruolo risulta salvato. Il comportamento è identico
   su MariaDB, quindi non dipende da MySQL. La SPEC non fissa limiti. Con `strictOn = false`, il default di CodeIgniter,
   il database tronca invece di rifiutare. Va deciso se i form debbano validare la lunghezza.
2. **Username non ASCII.** `my8àccento` e `my8🚀` vengono accettati (regole di Shield) e salvati correttamente. La SPEC e
   la documentazione non dicono se siano ammessi: annotato senza giudizio.
3. **Dashboard senza `dashboard.*` ma con un blocco dell'applicazione.** Un utente senza nessuno dei cinque `dashboard.*`,
   ma con `documents.view` (il blocco della demo), apre `/rolewarden` con 200 e vede solo quel blocco. La SPEC (U4, "Senza
   nessun `dashboard.*`") dice che va alla prima voce della barra; U5 fa entrare i blocchi dell'app nella dashboard.
   Comportamento non legato a MySQL.

## Difetti dello strumento (corretti, non del modulo)

- **r1 su 8.4, P16/P17.** Lo strumento chiudeva il browser 2,5 s dopo "Save changes" della matrice. Con più celle il
  salvataggio non era finito: mancava `users.manage-admins` e la colonna non risultava salvata. Ora attende che la pagina
  non abbia più niente da salvare: PASS su 8.0 e su 8.4 r2.
- Limite per indirizzo: il form accetta 5-100, e lo strumento provava 3. Corretto a 5.
- Durante la messa a punto: l'azione vuota dei form, la cache dei permessi tra un database e l'altro, il `mail()`
  disabilitato con il protocollo `mail` dell'app. Le email ora vanno a una porta SMTP chiusa e Mailpit non è stato toccato.

## Ambiente

- Container `rolewarden-my8-84` e `rolewarden-my8-80` sulla 3318, configurazione di serie dell'immagine: rimossi con i
  loro volumi. Nessun volume nuovo rimasto. Restano le immagini `mysql:8.4` (scaricata) e `mysql:8.0` (già presente).
- App di prova:
  - usata una copia nella scratch, con la giunzione `vendor/rolewarden` puntata al worktree;
  - `../rolewarden-app-test` non è stata toccata: hash di `.env`, `composer.json`, `composer.lock`, symlink e
    `writable/rolewarden` identici prima e dopo;
  - copia cancellata.
- Worktree rimosso, `git worktree prune` eseguito.
- MariaDB `rolewarden_test` (3317), usato solo per il confronto: snapshot `f7f37c04…e21e64` prima, ripristino esatto,
  stesso hash dopo (`verify-my8.environment.txt`). `rolewarden` non è stato toccato. Nell'output MariaDB la parola
  "rolewarden" appare come `[omesso]`, perché coincide con la password di sviluppo che lo strumento oscura.
- Nessuna credenziale nei file: le password dei container sono generate a runtime e lette dall'ambiente del container.
- Ambiente stabile: nessun `ERR_NO_BUFFER_SPACE`, un solo server PHP e un solo Chrome alla volta.

## File

- `verify-my8.php`, `verify-my8.phases.php`, `verify-my8.phase-p.php`, `verify-my8.phase-ix.php`, `verify-my8.lib.php`
  (copia di `verify-v7.lib.php` con porta configurabile), `verify-my8.run.sh`.
- Riusa `verify-v7.router.php`, `verify-v6.browser.mjs` e `verify-v4.snapshot.php` senza modificarli.
- Output: `verify-my8.{84,80,84-r1,mariadb}.output.txt`, `.applog.txt`, `.container.log`, `.server.log`,
  `.b-dev-probe.log` ed `environment.txt`.

## Riverifica su ee4f9d9 (r2, incompleta per ambiente)

Commit collaudato: `ee4f9d9` (correzione di D1), in un worktree separato, con una copia dell'app di prova collegata.

**Esito: NON CONCLUSO.** Nessun verdetto su D1 e nessun verdetto di regressione. Il motivo è l'ambiente, non il modulo.

- **MySQL 8.4.13**, suite completa `A,RB,B,P,I,X,Z` (`verify-my8.84-r2.output.txt`). Il processo che lanciava la suite
  è stato fermato da Claude Code per memoria di sistema quasi esaurita, intorno alla fase P (P16). Il collaudo è andato
  avanti senza il suo lanciatore fino alla fase I, poi si è interrotto con "server did not start", prima delle fasi X e Z.
  - Eseguiti: A01-A11, RB1-RB5, B02-B09, P00-P41, I01-I04.
  - PASS tutti, tranne P16, P17 e P36. Sono i tre passi da browser (Chrome headless) eseguiti mentre il sistema era senza
    memoria.
    - P16/P17: alla seconda pressione di "Save" la matrice risponde "The action you requested is not allowed". Manca
      `users.manage-admins`.
    - P36: il browser non ha restituito output.
  - Li classifico come **ambiente, non confermati come difetti**. Su 22bfc01 erano PASS su 8.0 e su 8.4 r2. Vanno
    rieseguiti a memoria libera.
  - Fasi **X e Z non eseguite**: X02b (D1), il nuovo X02c/X02d e Z01 restano da verificare.
- **MySQL 8.0**: non avviato. Il lanciatore chiedeva di non ripartire da soli dopo un arresto per memoria.
- **Nuove date per D1**: non provate. Le prove sono già nello strumento, non eseguite: X02c, con `2026-02-29`,
  `2026-04-31`, `0000-00-00`, `2026-13-45` e `2026-02-30` sia in `from` sia in `to`, e X02d, con le date valide
  `2028-02-29` e `2024-02-29`. Per ogni data annotano:
  - stato HTTP;
  - SQL a schermo;
  - valore mostrato nel campo;
  - "Entries" contro la pagina senza filtro, per dire se il filtro è applicato o ignorato;
  - righe SQL aggiunte al log.

Modifiche allo strumento:
- `verify-my8.phase-ix.php`: aggiunti X02c e X02d.
- `verify-my8.run.sh`: variabile facoltativa `MY8_LABEL` per gli output `-r2`.

Per riprendere: `MY8_LABEL=84-r2 bash verify-my8.run.sh 84`, poi `MY8_LABEL=80-r2 bash verify-my8.run.sh 80`.
Prima chiudere altre applicazioni pesanti.

Ambiente a fine sessione:
- Container `rolewarden-my8-84`: risulta ucciso e distrutto alle 22:56. Non è stato rimosso da questa sessione e
  `verify-my8.84-r2.container.log` non c'è. Nessun container `rolewarden-my8-*` rimasto e nessun volume nuovo: gli unici
  volumi orfani sono precedenti, del 15/10 o più vecchi.
- Worktree rimosso e `git worktree prune` eseguito. Copia dell'app e cookie jar cancellati.
- `../rolewarden-app-test`: hash dell'albero (escluso `vendor/rolewarden`) `2b060284…3bab83` prima e dopo, giunzione
  invariata.
- MariaDB `rolewarden_test` (3317) e `rolewarden` non toccati.
- Nessuna credenziale nei file.
