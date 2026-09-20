# AI Collaboration Log - rolewarden-ci4

> File di coordinamento tra AI diverse che lavorano sullo stesso progetto.
> Ogni agente legge questo file prima di iniziare una sessione e scrive una voce prima di terminare.

---

## Stato corrente (lock)

**In lavorazione:** nessuno
**Agente:** -
**Dalle:** -
**Scope:** -

Regola: se trovi "In lavorazione" diverso da "nessuno" e il tuo scope si sovrappone a quello indicato, fermati e segnala all'utente invece di procedere. Se non si sovrappone, puoi lavorare in parallelo ma aggiorna comunque questa sezione aggiungendo il tuo intervento.

Quando inizi una sessione, aggiorna questi campi. Quando finisci, riporta "In lavorazione" a "nessuno" e aggiungi la voce corrispondente nel log sotto.

---

## Log interventi

Formato di ogni voce:

```
### [YYYY-MM-DD HH:MM] - Nome agente
- Tipo: feature | fix | refactor | review | setup | docs
- Scope: file o moduli toccati
- Cosa ho fatto: descrizione breve
- Stato: completato | parziale | bloccato
- Note per il prossimo: eventuali avvisi, TODO lasciati, decisioni prese
```

---

<!-- Aggiungi le nuove voci qui sotto, in ordine cronologico crescente -->

### [2026-09-20 22:52] - Claude
- Tipo: setup
- Scope: `composer.json`, `.gitignore`, `.gitattributes`, `phpunit.xml.dist`, `README.md`, `CLAUDE.md`, `AGENTS.md`, `_AI-LOG.md`, `docs/`, albero `src/` e `tests/`
- Cosa ho fatto: scheletro iniziale del pacchetto (M0 parziale) su progetto PhpStorm vuoto, nessun codice PHP scritto; specifica e brief esportati in `docs/SPEC.md` e `docs/BRIEF-MVP.md` perche' gli agenti non possono aprire i documenti su claude.ai
- Stato: parziale
- Note per il prossimo: restano da fare, sempre dentro M0, il `composer install`, la creazione dell'app CI4 di prova come cartella sorella `../rolewarden-app-test` con `composer create-project codeigniter4/appstarter` e l'aggancio del modulo come repository di tipo path (tenuta fuori dal pacchetto per evitare che il symlink in `vendor/` punti alla propria cartella genitrice), piu' l'inizializzazione di git. Due vincoli in `composer.json` sono da verificare al primo `composer update`: `codeigniter4/shield: ^1.2`, da fissare sull'ultima stabile e da registrare come intervallo supportato, e `phpunit/phpunit: ^11.5`, che potrebbe non combaciare con quanto richiede CI4 4.5 (in tal caso allargare a `^10.5 || ^11.5`). Nessun file PHP e' stato creato: `src/` contiene solo cartelle con `.gitkeep`.

### [2026-09-20 23:10] - Claude
- Tipo: docs
- Scope: `docs/SPEC.md`
- Cosa ho fatto: aggiunta alla specifica la sezione sull'ambiente della demo pubblica su rolewarden.com (reset periodico dei dati, isolamento verso l'esterno, noindex, ambiente di test separato dalla demo) e riallineata la copia locale all'originale su Claude
- Stato: completato
- Note per il prossimo: il lavoro su staging, deploy e test e' rinviato di proposito. Si imposta quando l'MVP e' verificato, prima di iniziare la v1.0, e prima della pubblicazione dell'item: provare gli aggiornamenti direttamente sulla demo che i compratori guardano non e' accettabile. Nessuna infrastruttura da predisporre adesso.

### [2026-09-20 23:13] - Claude
- Tipo: setup
- Scope: `docker-compose.yml`, `docker/mariadb/init/01-databases.sql`, `.gitattributes`, `README.md`
- Cosa ho fatto: ambiente database locale in Docker (MariaDB 11 con `rolewarden` e `rolewarden_test`, piu' Adminer), escluso dal pacchetto distribuito via export-ignore
- Stato: completato
- Note per il prossimo: solo il database sta in container, PHP e Composer girano sull'host. Porte sovrascrivibili con `DB_PORT` e `ADMINER_PORT`. Le credenziali sono volutamente banali perche' lo stack non e' mai esposto: se un giorno serve raggiungerlo da fuori, vanno cambiate prima.

### [2026-09-20 23:22] - Claude
- Tipo: fix
- Scope: `composer.json`
- Cosa ho fatto: rimosso `codeigniter4/devkit` dalle dipendenze di sviluppo e sostituito con php-cs-fixer, phpstan e phpunit diretti
- Stato: completato
- Note per il prossimo: devkit fino alla v1.2.3 vincola PHPUnit a ^9.3 || ^10.5.16, incompatibile con il nostro ^11.5; la v1.3.0 richiede `roave/security-advisories dev-latest` e imporrebbe `minimum-stability: dev` su tutto il pacchetto. Nessuna delle due e' accettabile per un pacchetto venduto, quindi gli strumenti sono richiesti direttamente. Restano da aggiungere i file di configurazione `.php-cs-fixer.dist.php` e `phpstan.neon.dist`, che devkit avrebbe portato con se': vanno scritti in M0.

### [2026-09-20 23:25] - Claude
- Tipo: setup
- Scope: `composer.json`, `docs/SPEC.md`, `docs/BRIEF-MVP.md`
- Cosa ho fatto: primo `composer update` riuscito; fissato l'intervallo di versioni Shield supportate a `^1.4` e riallineati i due documenti
- Stato: completato
- Note per il prossimo: versioni risolte al 20 settembre 2026, cioe' Shield v1.4.1, CodeIgniter v4.7.4, PHPUnit 11.5.56, PHPStan 2.2.14, php-cs-fixer 3.95.26. Il vincolo su Shield era `^1.2` ed e' stato alzato a `^1.4` perche' si dichiara supportato solo cio' che viene collaudato davvero; serve un `composer update` per riallineare il lock. M0 non e' ancora chiusa: mancano l'app CI4 di prova, i file di configurazione di phpstan e php-cs-fixer, e il primo commit.

### [2026-09-20 23:30] - Claude
- Tipo: setup
- Scope: `phpstan.neon.dist`, `.php-cs-fixer.dist.php`, `.gitignore`, app di prova `../rolewarden-app-test`
- Cosa ho fatto: aggiunti i file di configurazione di phpstan (livello 5) e php-cs-fixer (PSR-12); configurato il `.env` dell'app di prova con `CI_ENVIRONMENT = development` e `app.baseURL` su porta 8000; verificato che il modulo e' agganciato via repository path, che Shield e' installato e che il login funziona
- Stato: completato
- Note per il prossimo: M0 e' chiusa tranne il primo commit. Lo stack locale e' Docker per il solo database (MariaDB 11, database `rolewarden` e `rolewarden_test`), PHP e Composer sull'host, `php spark serve --port 8000` perche' la 8080 e' occupata sulla macchina dell'autore. Il vincolo Shield e' stato alzato a `^1.4` dopo che il lock era gia' stato scritto: serve un `composer update` nel modulo per riallinearlo. Lo stile e' PSR-12 semplice e non `codeigniter4/coding-standard`, che sarebbe piu' idiomatico per CI4 ma aggiunge dipendenze di sviluppo: decisione da riportare all'autore prima di cambiarla. Prossima tappa M1, migrazioni e seed, partendo da `docs/BRIEF-MVP.md`.

### [2026-09-20 23:50] - Claude
- Tipo: feature
- Scope: `src/Config/RoleWarden.php`, `src/Database/Migrations/` (5 file), `src/Database/Seeds/RoleWardenSeeder.php`
- Cosa ho fatto: tappa M1. Cinque migrazioni reversibili (roles, permissions, role_permissions, user_roles, user_permissions), prefisso da `RoleWarden::$tablePrefix` (default `acl_`, override da `.env` `rolewarden.tablePrefix` o da `app/Config/RoleWarden.php`), nome della tabella utenti letto da `config('Auth')->tables['users']`. Seeder idempotente (per slug): ruoli `super-admin` (is_super_admin), `admin`, `user`, tutti `is_system`; 12 permessi `users.{view,create,update,delete,activate}`, `roles.{view,create,update,delete,assign}`, `permissions.{view,override}`; `admin` li riceve tutti, gli altri nessuno. Verificato sull'app di prova: migrate --all scopre il namespace, seed due volte = stessi conteggi (3/12/12), rollback lascia solo tabelle Shield + `migrations`, prefisso `xx_` funziona e si annulla, phpstan ok, php-cs-fixer ok su `src/`.
- Stato: completato (in attesa di Codex)
- Note per il prossimo: scelte non nella spec, da confermare e riportare nell'originale su Claude: (1) colonna `acl_roles.is_super_admin`, non nell'elenco colonne ma la spec dice "flag sul ruolo"; (2) contenuto del seed, la spec non lo elenca; (3) `acl_roles.slug` unique anche sui ruoli con `deleted_at`, quindi lo slug di un ruolo cancellato non si riusa; (4) `user_permissions.granted` 1/0, PK composte sulle tabelle ponte, nessuna colonna `id` li'. Il seeder si lancia con `spark db:seed "RoleWarden\Database\Seeds\RoleWardenSeeder"`, il comando di setup che lo concatena non c'e' ancora. Il prefisso va deciso prima di migrare: cambiarlo dopo non rinomina le tabelle. Non ancora fatto: `composer update` nel modulo per il lock Shield `^1.4`; `php-cs-fixer` su `.php-cs-fixer.dist.php` stesso segnala differenze (file M0, non toccato). Nessun commit fatto.

### [2026-09-20 23:48] - Codex
- Tipo: review
- Scope: `tests/Integration/verify-m1.php`, `tests/Integration/verify-m1.ps1`, `tests/Integration/m1-output.txt`, `tests/Integration/M1-REPORT.md`
- Cosa ho fatto: verifica indipendente M1 sul commit `80a60ba`, senza aprire implementazione, config del modulo o seeders. Script Spark/MariaDB riproducibili contro specifica e criteri espliciti dell'utente; entrambi i prefissi, dotenv, seed, vincoli, cancellazioni, rollback e failure paths development/production. 55 controlli: 47 PASS, 8 FAIL (due problemi ripetuti). App temporanea nel repository con dipendenze della app sorella; rolewarden_test inizialmente vuoto ripristinato, app temporanea rimossa, .env originale della app sorella mai modificato.
- Stato: completato (M1 non approvata)
- Note per il prossimo: report e output completi in tests/Integration. Errori SQL visibili anche in produzione e exit code 0 sui fallimenti. `migrate:rollback -n RoleWarden` non isola il namespace: help espone solo -b/-f, rollback di ultimo batch; installazione fresca --all elimina anche Shield/Settings, mentre Shield in batch precedente viene preservato. Remigrazione riuscita con entrambi i prefissi. Ambiguita' da risolvere nella specifica: baseline Settings/batch del rollback, enforcement dominio granted (2 accettato dal DB), semantica eliminazione padre, riuso slug dopo soft delete, contratto schema/seed da allineare ai requisiti concordati. Nessuna correzione al modulo e nessun commit.

### [2026-09-21 00:10] - Claude
- Tipo: fix
- Scope: `README.md`, `_AI-LOG.md`
- Cosa ho fatto: chiuso D2 della verifica di Codex. Documentato in README il rollback per batch e l'installazione in passi separati. Correzione alla mia voce M1 delle 23:50: il comando `migrate:rollback -n RoleWarden` che avevo indicato come verificato NON isola il namespace; nel mio test funzionava solo perche' Shield e Settings stavano nel batch 1 e RoleWarden nel 2. Procedura corretta verificata su `rolewarden_test`: `migrate -n` separato per Settings, Shield e RoleWarden (batch 1, 2, 3), poi `migrate:rollback -b 2` toglie solo RoleWarden e lascia Shield e Settings; `-b 0` toglie tutto.
- Stato: completato per D2. D1 (dettagli del database a schermo sui fallimenti da riga di comando, exit code 0) e le ambiguita' della spec restano aperti in attesa di decisione dell'autore.
- Note per il prossimo: incidente durante la verifica: un override del database via variabile d'ambiente non ha avuto effetto e `migrate:rollback -b 2` e `-b 0` sono girati per errore sul database `rolewarden` invece che su `rolewarden_test`, cancellando tutte le tabelle. Ripristinati con `migrate --all` e il seed (3 ruoli, 12 permessi). Dati persi: nessuno noto oltre a eventuali utenti di prova di M0 (la tabella `users` ora e' vuota; se serve il login di prova, va ricreato). Per usare un altro database negli script di prova modificare `.env` con backup, non le variabili d'ambiente. Il `.env` e' stato riportato com'era. I file di Codex in `tests/Integration/` non sono ancora committati.

### [2026-09-21 00:25] - Claude
- Tipo: docs
- Scope: `_AI-LOG.md` (nessun file di codice, `docs/SPEC.md` non toccato)
- Cosa ho fatto: decisioni su D1 e sulle ambiguita' della verifica M1, delegate dall'autore ("decidi tu"). Da riportare nell'originale della specifica su Claude e poi riesportare in `docs/`. Nessuna modifica allo schema.
  1. D1, errori a schermo. La regola "mai errori SQL a schermo" vale per le risposte HTTP, cioe' quello che vede l'utente finale, ed e' verificata da M5 in poi (voce della definizione di fatto). I comandi `spark` girano sotto il controllo dello sviluppatore e mostrano la diagnostica del framework, che il modulo non intercetta ne' riscrive; l'exit code 0 e' anch'esso del framework. Vincolo per il codice del modulo: non cattura mai un'eccezione del database per stamparla. ATTENZIONE: e' una lettura piu' stretta del testo attuale ("in nessun ambiente e in nessun percorso"), quindi e' uno scostamento da confermare esplicitamente dall'autore.
  2. Rollback. "Senza residui" significa nessuna tabella del modulo e nessuna riga del modulo in `migrations`, con Shield e Settings intatti. Procedura per batch come nel README; `settings` e' del framework, non un residuo.
  3. `granted` fuori da 0/1. Non si aggiunge un CHECK nel database: non e' portabile su MySQL vecchi, dove viene ignorato in silenzio, e sarebbe una modifica di schema non prevista. Il vincolo va nello strato di scrittura (modello in M2/M3), con test.
  4. Eliminazione di un ruolo padre. Il `SET NULL` sulla chiave esterna resta come rete di sicurezza per le cancellazioni fisiche. In M4 lo strato di scrittura rifiuta di eliminare un ruolo che ha figli attivi, con messaggio che dice di spostarli o eliminarli prima.
  5. Slug dopo cancellazione logica. Un ruolo cancellato logicamente continua a riservare il suo slug. Il messaggio di errore deve dire di ripristinare quel ruolo o scegliere un altro slug.
  6. Contratto schema e seed da scrivere nella specifica: colonna `acl_roles.is_super_admin`; `acl_user_permissions.granted` 1 concesso, 0 negato; seed con ruoli `super-admin` (flag super admin), `admin`, `user`, tutti di sistema, e i 12 permessi `users.{view,create,update,delete,activate}`, `roles.{view,create,update,delete,assign}`, `permissions.{view,override}`; `admin` li ha tutti.
- Stato: completato, in attesa di conferma dell'autore sul punto 1
- Note per il prossimo: Codex deve riverificare M1 con la procedura di rollback per batch e senza il punto 9 sui comandi da terminale (resta valido per l'HTTP dopo M5). I punti 3 e 4 sono lavoro futuro di M2/M3 e M4: ricordarli quando si scrivono i modelli.


### [2026-09-21 00:20] - Codex
- Tipo: review
- Scope: tests/Integration/verify-m1.php, verify-m1.ps1, m1-output.txt, M1-REPORT.md; _AI-LOG.md
- Cosa ho fatto: riverifica indipendente M1 su HEAD 4fc333f (src invariato da 80a60ba), contro specifica e decisioni autore, senza leggere implementazione. 79 controlli PASS, zero FAIL; entrambi i prefissi, vincoli, soft delete con slug riservato, override 1/0 e unicita', seed esatto e idempotente. Installazione Settings/Shield/RoleWarden in batch 1/2/3; rollback -b 2 -f e procedura README letterale con conferma, senza residui modulo, tabelle e righe migrazione framework preservate; remigrazioni riuscite. Output comandi senza query/segreti; diagnostica framework CLI esclusa secondo D1.
- Stato: completato, M1 approvata nel perimetro concordato.
- Note per il prossimo: nessun difetto residuo M1 e nessuna delle cinque ambiguita' ancora aperta; resta da sincronizzare la specifica con le decisioni. Dominio granted in M2/M3, protezione figli attivi in M4, HTTP da M5. Preflight: trovata solo migrations vuota in rolewarden_test; rimossa dopo verifica prima del collaudo. Database finale vuoto, app temporanea rimossa, hash .env sorella invariato. Nessun accesso al DB rolewarden, nessuna modifica sorgente, nessun commit. Docker CLI non accessibile; usata connessione MySQL locale disponibile. Orari di questa sessione dal clock host (anteriore alla precedente voce 00:25).