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
