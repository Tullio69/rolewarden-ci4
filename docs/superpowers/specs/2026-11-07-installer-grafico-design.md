# Installer grafico per applicazioni esistenti: design

Data: 2026-11-07. Origine: collaudo dell'installazione su HelpDesk CI4. Il registro completo degli attriti è in `E:\progetti_lavoro\ASP\helpdesk-ci4\helpdesk\docs\rolewarden-install-log.md`.

## Perché

Fabio ha installato RoleWarden 1.1.0 (zip `module`) su un'applicazione Shield esistente, l'helpdesk demo, seguendo il README. L'ha trovato complicato anche per un developer: ha incontrato 16 attriti in sei passi. I più gravi:

- **Comandi composer assenti dalla doc**, lanciati dalla cartella sbagliata, con il `^` che Windows elimina.
- **Login dell'app senza CSS:** il filtro `session` globale dell'host blocca `/rolewarden/assets/*`.
- **App host in errore per tutti** tra il cablaggio e le migrazioni (`acl_user_roles doesn't exist`): il README le mette nell'ordine sbagliato.
- **Gruppo Shield `admin` saltato senza avviso** dall'import, per collisione con il ruolo di sistema: l'admin dell'host perde i suoi permessi.
- **Super admin solo con un INSERT SQL a mano.**
- **Nuovi iscritti senza ruolo:** `$defaultRole = null` e il `$defaultGroup` dell'host viene ignorato.
- **`addGroup`/`syncGroups` del codice host senza effetto:** scrivono in `auth_groups_users`, che nessuno legge più.
- **Secondo profilo con la grafica RoleWarden** per tutti gli utenti dell'host, anche quando non è voluto.

Funziona già bene: i controlli `$user->can()` dell'host passano a RoleWarden senza toccare il suo codice.

**Obiettivo (di Fabio):** RoleWarden deve innestarsi nel modo più neutro possibile su qualunque applicazione Shield. Un'interfaccia grafica guida passo per passo, spiega ogni conseguenza e lascia scegliere. Le istruzioni nella documentazione HTML devono essere chiare anche per chi non usa l'installer.

## Obiettivi

1. **Installazione completa da browser** su un'app CI4 + Shield esistente, dalla copia del modulo fino al primo super admin, senza terminale e senza SQL.
2. **Nessuna rottura:** l'app host non deve mai trovarsi in uno stato rotto durante l'installazione.
3. **Nessuna decisione presa in silenzio:** ogni scelta che cambia il comportamento dell'host è una domanda, con la sua conseguenza scritta accanto.
4. **Aggiornamento leggero:** il pannello rileva le migrazioni nuove e le applica con un pulsante.
5. **Registro delle modifiche:** ogni modifica ai file dell'host viene annotata, così la futura disinstallazione può annullarla.
6. **Documentazione HTML** con la procedura manuale equivalente, comandi esatti compresi.
7. **Codice Shield esistente dell'host funzionante:** `addGroup`, `removeGroup`, `syncGroups` e il `$defaultGroup` agiscono sui ruoli RoleWarden. Questo si fa nel modulo, non nel wizard.

## Non obiettivi

- **Disinstallazione da interfaccia.** In questa versione c'è solo la sezione "Disinstallare" nella documentazione (`composer remove` o rimozione della riga di Autoload, rollback delle migrazioni, poi cancellazione di `modules/`). Il registro delle modifiche la prepara.
- **App senza Shield o non CodeIgniter 4.** L'installer le riconosce, si ferma e spiega il motivo.
- **Cambiare il percorso dello zip `app`.** Il suo installer attuale (`/rolewarden/install`, per database vuoto) resta com'è. Il nuovo flusso riguarda lo zip `module`.

## Anti-obiettivi (sarebbe un fallimento anche se funzionasse)

- Lasciare l'app host in errore anche per un minuto, per esempio cablando prima delle migrazioni.
- Modificare un file dell'host senza dirlo, oppure senza annotarlo nel registro.
- Togliere permessi a un utente esistente senza un avviso esplicito.
- Rendere possibile a uno sconosciuto di completare l'installazione e nominarsi super admin.

## Vincoli

- PHP 8.3+, CodeIgniter 4.7.4+, Shield `^1.4`, MySQL/MariaDB InnoDB: sono i requisiti attuali del README.
- Senza composer, i vincoli di versione li deve controllare l'installer.
- Nessun file del modulo va modificato da chi installa. Le personalizzazioni vivono nell'host (convenzione esistente).
- Due lingue, inglese e italiano, come il resto del modulo.
- Lo stile segue il design system del pannello (`docs/design-system/`, temi, token).
- Sicurezza: codice monouso letto dal server (`writable/rolewarden/install-code.txt`), come nella decisione V7 di SPEC. CSRF attivo.

## Decisioni prese con Fabio

1. **Anche la copia e il collegamento sono guidati,** non solo le scelte dentro l'app.
2. **Collegamento predefinito con `app/Config/Autoload.php`** (`'RoleWarden' => ROOTPATH . 'modules/rolewarden/src'`), come nello zip `app`. Composer resta un'alternativa documentata. Motivo: il pacchetto non è su Packagist, quindi `composer update` non porta versioni nuove, e la riga di Autoload funziona anche senza terminale. Proposta mia, approvata da Fabio.
3. **Passo "Ruoli", prima domanda (idea di Fabio):**
   - **"Adatta i ruoli già presenti"** (consigliata per app in uso): restano i gruppi dell'host, convertiti in ruoli RoleWarden. RoleWarden non aggiunge i suoi `admin` e `user`. Una tendina chiede quale ruolo dell'host riceve i permessi del pannello, con l'opzione "nessuno (solo super admin)".
   - **"Importa i ruoli di RoleWarden":** aggiunge `super-admin`, `admin` e `user` accanto ai gruppi dell'host. Se ci sono slug uguali, si passa alle collisioni.
4. **Collisioni: una scheda per ognuna** (bozza A), con spiegazione e conseguenze. Ogni scheda indica utenti e permessi del gruppo dell'host e cosa fa il ruolo RoleWarden, poi offre tre opzioni, ciascuna con la sua conseguenza in chiaro:
   - **Unisci:** il ruolo esistente riceve i permessi e gli utenti del gruppo.
   - **Importa con un altro nome:** slug modificabile, proposta `app-<slug>`.
   - **Salta:** l'avviso dice quanti utenti perdono quanti permessi.

   Chi usa solo la CLI ottiene il comportamento attuale ("salta") più un avviso stampato dalla migrazione di import (scelta di Fabio).
5. **"Chi vede RoleWarden?"** Scelta unica (idea di Fabio):
   - **Tutti gli utenti:** login con la grafica RoleWarden, profilo e sessioni per tutti.
   - **Solo gli amministratori** (consigliata per app in uso): l'installer non tocca `$views['login']` né `$redirects['login']`. Profilo e sessioni sono raggiungibili solo da chi ha almeno un permesso del pannello; per gli altri rispondono 404. Il tracciamento delle sessioni, la revoca da parte dell'admin e le email di sicurezza continuano per tutti.
6. **Ruolo per i nuovi iscritti:** un passo del wizard, precompilato con l'`AuthGroups::$defaultGroup` dell'host.
7. **Super admin:** si sceglie un utente esistente da un elenco, oppure se ne crea uno nuovo. Nessun SQL.
8. **Gradualità:** installazione completa e aggiornamento leggero ora, disinstallazione da interfaccia più avanti.

## Il flusso

### Fase 1: il modulo non è ancora caricato

È un file solo, `rolewarden-setup.php`, che **contiene già l'intero modulo** (decisione di Fabio). Si copia solo quel file, nella `public/` dell'host, e si apre nel browser. Non c'è nessuno zip da estrarre e nessun file da caricare.
- Il file lo genera `bin/build-package` insieme agli altri zip. Il formato interno (archivio phar oppure contenuto incorporato) è lasciato a chi costruisce. Peso indicativo: circa 600 KB.
- Lo zip `module` resta disponibile per chi segue la procedura manuale.

1. **Riconoscimento:** il progetto ha `spark`, `app/Config`, `vendor/codeigniter4/framework` e `vendor/codeigniter4/shield`. Le versioni vengono confrontate con i requisiti, insieme a PHP, alle estensioni e alla scrittura su `writable/`, `app/Config/` e `modules/`.
2. **Codice monouso** generato in `writable/rolewarden/install-code.txt`, da incollare nella pagina.
3. **Estrazione del modulo** dal file stesso in `modules/rolewarden`. Se la cartella c'è già, non si sovrascrive senza chiedere. Prima di inserire il codice monouso, la pagina non scrive niente.
4. **Collegamento:** la riga in `app/Config/Autoload.php` viene aggiunta e annotata nel registro. Se il modulo è già caricato via composer, il passo si salta.
5. **Passaggio alla fase 2:** si apre la rotta del wizard, poi `rolewarden-setup.php` si cancella. Se non riesce a cancellarsi, lo dice.

### Fase 2: il wizard dentro l'app

Il wizard deve essere raggiungibile appena il modulo è caricato, senza che l'host abbia ancora toccato `Routes.php` o `Auth.php`, e senza che il filtro `session` dell'host lo blocchi.

1. **Requisiti:** riepilogo dei controlli già fatti nella fase 1.
2. **Filtri dell'host:** l'installer rileva un filtro globale `session`, `group` o `permission` che coprirebbe `rolewarden/assets/*` o le rotte del modulo, mostra la riga di `app/Config/Filters.php` e propone l'eccezione con un'anteprima della modifica.
3. **Tabelle:** migrazioni `-n RoleWarden` in un batch separato, poi il seed dei ruoli secondo la scelta del passo Ruoli. L'output non contiene righe ripetute e si chiude con un riepilogo.
4. **Ruoli:** decisioni 3 e 4.
5. **Ruolo per i nuovi iscritti:** decisione 6.
6. **Super admin:** decisione 7.
7. **Chi vede RoleWarden:** decisione 5.
8. **Cablaggio:** `userProvider` in `Auth.php`, la riga di `RouteRegistrar` in `Routes.php`, e vista e redirect di login se previsti dalla scelta 7. Ogni modifica mostra l'anteprima prima di essere scritta.
9. **Riepilogo:** l'elenco di cosa è stato fatto e quali file sono stati toccati. Il suggerimento di aggiungere un link al pannello nel menu dell'host. Il lock `installed.lock`.

L'ordine è voluto: le tabelle vengono prima del cablaggio, così l'app host funziona in ogni momento (anti-obiettivo 1).

**Le domande vengono prima delle scritture** (scelta mia, dopo la revisione). I passi Ruoli, Nuovi iscritti, Super admin e Chi vede raccolgono solo le scelte: gruppi e assegnazioni si leggono da `AuthGroups` e da `auth_groups_users`, che esistono già prima delle migrazioni. Una volta confermate, il wizard applica tutto in quest'ordine: migrazioni, ruoli e import secondo le scelte, super admin, cablaggio.
- Sotto il wizard la migrazione `ImportAuthGroups` non importa niente, perché l'import lo fa il wizard con le scelte dell'utente. Il wizard lo segnala con un setting letto dalla migrazione.
- Da CLI la migrazione resta com'è oggi, più l'avviso. `down()` continua a rimuovere per slug ciò che l'import ha creato.

**Stato del wizard** (scelta mia): il flusso per app esistenti ha uno stato suo, cioè il registro delle modifiche, e un lock suo, separati dall'installer dello zip `app`. Il controllo "esiste già un super admin attivo" non lo blocca: il passo Super admin è una scelta, non una guardia. Finché manca il lock del flusso, il wizard riprende dal punto in cui si era fermato.

### Aggiornamento

Quando ci sono migrazioni `RoleWarden` non ancora applicate, il pannello mostra un avviso all'admin: "RoleWarden x.y rilevato: N aggiornamenti al database da applicare", con un pulsante. Serve un permesso dedicato, per esempio `settings.update`.

### Registro delle modifiche

`writable/rolewarden/install-manifest.json` registra, per ogni file dell'host toccato, il percorso, il prima e il dopo della riga, e la data. Registra anche le scelte fatte nel wizard. È la base della futura disinstallazione.

## Modifiche al modulo, fuori dal wizard

- **Ruolo del pannello** (scelta mia): `super-admin` si crea sempre, in entrambe le modalità. Il ruolo che riceve i permessi del pannello si salva nel setting `RoleWarden.panelRole`: `admin` in modalità "Importa", il ruolo dell'host scelto in modalità "Adatta", vuoto con "nessuno". Le migrazioni di dati, quelle attuali che oggi cercano lo slug `admin` e tutte le future, concedono i permessi nuovi al ruolo indicato da quel setting. Se il setting è vuoto, i permessi non vanno a nessun ruolo e li vede solo il super admin.
- **"Chi vede RoleWarden"** (scelta mia): è un setting modificabile anche dopo dalla schermata Impostazioni, come le altre opzioni. Per "permesso del pannello" si intende uno qualunque dei permessi creati da `RoleWardenSeeder` (`users.*`, `roles.*`, `settings.*`, `dashboard.*`, …), mai quelli dell'host.

- **`Entities/User`:** ridefinire `addGroup`, `removeGroup` e `syncGroups` sui ruoli RoleWarden, tramite `Models/UserRoles`, che svuota già la cache e applica le protezioni. Uno slug sconosciuto genera un'eccezione, come in Shield.
- **`Models/UserModel::addToDefaultGroup()`** (scelta mia, dopo la revisione): delega a `DefaultRole::assignTo()`, così anche gli utenti creati dal codice dell'host (admin, seeder, import) ricevono il ruolo di default. Che lo chiamino sia Shield sia l'evento `register` non è un problema: `UserRoles::assign()` non duplica un'assegnazione che esiste già.
- **`Settings/DefaultRole::role()`:** se il setting manca, usare `AuthGroups::$defaultGroup` quando esiste come ruolo.
- **Import CLI:** avviso per ogni gruppo saltato, più un riepilogo finale.
- **Tabelle mancanti:** se il modulo è caricato ma le tabelle non esistono, le richieste mostrano "RoleWarden non è ancora installato → apri l'installer" invece di una `DatabaseException`.

## Documentazione

- **`documentation/index.html` e README:** un capitolo "Installare su un'app esistente" con entrambe le strade.
  - **Wizard:** cosa chiede e perché.
  - **Procedura manuale:** dove estrarre (attenzione al livello in più creato da "Estrai tutto" di Windows), la riga di Autoload oppure i due comandi composer esatti da lanciare dalla cartella con `spark` (`composer config repositories.rolewarden path modules/rolewarden`, `composer require rolewarden/codeigniter4-rolewarden`), poi migrazioni, cablaggio, filtri, ruolo di default e super admin con `php spark`.
- **Sezione "Disinstallare".**
- **Accanto agli zip di build,** una riga che dica quale zip serve a chi.

## Lasciato a chi costruisce

- I nomi delle rotte e dei controller del wizard, e la loro integrazione con `InstallController` e `Installer` esistenti (riuso dei controlli dei requisiti, del codice monouso e del lock).
- Come rendere il wizard raggiungibile prima del cablaggio di `Routes.php`. Per esempio, la fase 1 può aggiungere subito la riga di `RouteRegistrar`, oppure il modulo può registrare la rotta dell'installer da solo tramite la discovery.
- Come modificare in modo sicuro i file PHP di configurazione dell'host: rilevare la riga, inserire, verificare con `php -l`, annullare se fallisce.
- Il formato esatto del registro delle modifiche.
- Come rendere "Adatta i ruoli già presenti" per un host che non ha gruppi Shield propri, per esempio proponendo direttamente l'importazione.
