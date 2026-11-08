# Wizard di installazione su app esistente: piano di implementazione (piano 2 di 5)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** una pagina `/rolewarden/setup` guida l'installazione di RoleWarden su un'app CodeIgniter 4 + Shield esistente. Si parte dal momento in cui il modulo è caricato, a mano o, nel piano 3, dal file di setup unico, e si arriva al primo super admin. La pagina chiede ogni scelta con la sua conseguenza e, solo alla fine, applica migrazioni, ruoli, super admin, modifiche ai filtri e cablaggio, annotando tutto in un registro.

**Architecture:**
- **Rotta:** la registra `src/Config/Routes.php`, che CodeIgniter include da solo per ogni modulo (discovery `routes`), quindi il wizard si apre prima che l'host tocchi `Routes.php`.
- **Controller:** semplice, come `InstallController`, protetto dal codice monouso già esistente (`Installer::code()`).
- **Stato e registro:** stanno in un file JSON in `writable/rolewarden/`.
- **Logica:** in classi piccole sotto `src/Install/`. Le parti pure (analisi dell'host, trasformazioni dei file di configurazione, piano dei ruoli) hanno test unitari; l'applicazione sul database ha test di integrazione.

**Tech Stack:** PHP 8.3, CodeIgniter 4.7, Shield 1.x, PHPUnit 11, MariaDB (docker compose del repo).

**Spec:** `docs/superpowers/specs/2026-11-07-installer-grafico-design.md`, sezioni "Decisioni prese con Fabio" (3–7), "Fase 2", "Registro delle modifiche" e "Vincoli/lingue".

## Global Constraints

- PHP `^8.3`, `codeigniter4/framework ^4.7.4`, `codeigniter4/shield ^1.4`. Nessuna nuova dipendenza.
- I passi del wizard da 1 a 6 non scrivono niente: né database, né file dell'host, né impostazioni. Scrive solo il passo "Conferma e applica". Unica eccezione: il file di stato e registro in `writable/rolewarden/`.
- Ordine di applicazione, vincolante (spec):
  1. migrazioni;
  2. ruoli e import;
  3. super admin;
  4. eccezione nei filtri;
  5. cablaggio.

  Prima che le tabelle esistano non si scrive niente nei file di configurazione dell'host che possa farlo andare in errore.
- Ogni modifica a un file dell'host viene mostrata in anteprima prima della conferma. Al momento della scrittura si salva un backup `<file>.rolewarden-bak`, si controlla la sintassi e si annota nel registro (percorso, prima, dopo, data). Se la sintassi risulta non valida si ripristina il backup e lo si segnala.
- Sicurezza:
  - il wizard funziona solo dopo aver inserito il codice monouso (`Installer::code()`, file `writable/rolewarden/install-code.txt`);
  - CSRF attivo;
  - quando il flusso è completato il wizard risponde 403;
  - nessun dettaglio SQL arriva a schermo.
- Testi solo nei file di lingua, `src/Language/en|it/RoleWarden.php`, nella sezione `setup`. `TranslationsTest` deve restare verde. Lingua iniziale: quella del browser; un selettore permette di cambiarla.
- Stile coerente con l'installer esistente (`src/Views/install/index.php`) e con i token del pannello.
- Ogni commit termina con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Host senza gruppi propri** (`AuthGroups` di serie, solo `user`/`superadmin` o nessun gruppo): il passo Ruoli propone direttamente "Importa i ruoli di RoleWarden" e non si blocca. Test nel Task 3.
2. **File di configurazione dell'host scritti in modo insolito**: proprietà su più righe, `use` alias come `UserModel::class`, commenti, `except` come stringa invece che array, nessun filtro `session` globale. Il trasformatore non deve rompere il file. Se non riconosce lo schema, non scrive e mostra le istruzioni a mano. Test nel Task 4, con le copie reali dei file dell'helpdesk tra le fixture.
3. **Wizard interrotto a metà del passo 7** (errore di permessi su un file, connessione persa): rilanciato, riprende dal primo passo non completato senza duplicare ruoli o assegnazioni, e il registro riflette solo ciò che è avvenuto davvero. Test nel Task 7.
4. **Utente che sceglie come super admin un utente disattivato o bannato**: l'elenco mostra solo utenti attivi; un id manomesso nel POST viene rifiutato. Test nel Task 6.
5. **Host con un filtro `session` globale**: un ospite viene rimandato al login dell'host prima di vedere il wizard. La pagina "codice" spiega di accedere con un account esistente. Il wizard non si apre senza codice nemmeno da loggati. Test nel Task 6.

---

### Task 1: il seeder può non creare i ruoli `admin` e `user`

Segue un ruling del piano 1: con "Adatta i ruoli già presenti" RoleWarden non aggiunge i suoi `admin` e `user`.

**Files:**
- Modify: `src/Database/Seeds/RoleWardenSeeder.php`
- Test: `tests/Integration/SeederDefaultRolesTest.php`

**Interfaces:**
- Produces: setting `RoleWarden.seedDefaultRoles` (bool, default true se non impostato). Con false il seeder crea solo `super-admin` e i permessi, non `admin` né `user`. Costante: `RoleWardenSeeder::DEFAULT_ROLES_KEY = 'RoleWarden.seedDefaultRoles'`.
- I permessi nuovi vanno sempre al ruolo del pannello (`PanelRole`, piano 1).

- [ ] **Step 1: test.**
  - `testDefaultsCreateAdminAndUser`: senza setting, esistono `admin` e `user`.
  - `testDisabledCreatesOnlySuperAdmin`: setting false, refresh delle tabelle, seed. `roleId('admin') === null`, `roleId('user') === null`, `roleId('super-admin') !== null`. Il ruolo del pannello impostato su un ruolo creato a mano prima del seed riceve tutti gli slug del modulo.
  - Il `tearDown` cancella il setting.
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare.
- [ ] **Step 4:** eseguire `vendor/bin/phpunit`. Atteso: PASS.
- [ ] **Step 5:** commit `feat: il seeder può creare solo super-admin (RoleWarden.seedDefaultRoles)`.

### Task 2: stato del wizard e registro delle modifiche

**Files:**
- Create: `src/Install/SetupState.php`
- Test: `tests/Unit/Install/SetupStateTest.php`

**Interfaces:**
- Produces: `final class RoleWarden\Install\SetupState`, che rappresenta il file `writable/rolewarden/install-manifest.json`.
  - `public static function file(): string`
  - `public static function load(?string $file = null): self`: file assente o non valido danno uno stato vuoto.
  - `public function save(?string $file = null): void`: scrittura atomica (file temporaneo + `rename`).
  - `public function choices(): array` / `public function setChoice(string $key, mixed $value): void`
  - `public function done(string $step): bool` / `public function markDone(string $step): void`. Passi: `migrations`, `roles`, `superAdmin`, `filters`, `wiring`.
  - `public function recordEdit(string $path, string $before, string $after): void`: salva `path`, `before`, `after` e `at` in formato ISO 8601.
  - `public function edits(): list<array{path: string, before: string, after: string, at: string}>`
  - `public function complete(): void` / `public function completed(): bool`: imposta e legge `completed_at`.
- Formato JSON (chiavi di primo livello): `version` (1), `choices`, `done` (lista), `edits`, `completed_at` (null o ISO 8601).

- [ ] **Step 1: test unitari su una directory temporanea.**
  - `testEmptyWhenMissing`
  - `testRoundTrip`: scelte, passi completati e modifiche sopravvivono a `save` e `load`.
  - `testCorruptFileIsEmptyState`
  - `testCompleted`
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare.
- [ ] **Step 4:** eseguire i test. Atteso: PASS.
- [ ] **Step 5:** commit `feat: stato del wizard e registro delle modifiche in install-manifest.json`.

### Task 3: analisi dell'host (sola lettura)

**Files:**
- Create: `src/Install/HostScan.php`
- Test: `tests/Unit/Install/HostScanTest.php`, `tests/Integration/HostScanTest.php`

**Interfaces:**
- Produces: `final class RoleWarden\Install\HostScan`.
  - `public static function groups(object $authGroups, array $memberCounts): list<array{slug: string, title: string, users: int, permissions: int}>`, pura. I permessi sono contati con i wildcard espansi, come `ImportAuthGroups`. Se possibile, riusa la logica di espansione spostandola in `GroupImport::expand()` (public static) invece di duplicarla.
  - `public static function collisions(array $groups, array $systemSlugs): list<string>`, pura. Gli slug di sistema sono `['super-admin','admin','user']`.
  - `public static function suggestedMode(array $groups): string`, pura. Restituisce `'adapt'` se l'host ha almeno un gruppo diverso da `user`/`superadmin`, altrimenti `'import'` (Review Focus 1).
  - `public static function memberCounts(): array<string,int>`: legge `auth_groups_users`. Restituisce `[]` se la tabella non esiste.
  - `public static function activeUsers(int $limit = 200): list<array{id: int, username: string, email: string}>`: legge le tabelle di Shield. Esclude utenti disattivati e bannati e quelli con `deleted_at`.
  - `public static function defaultGroup(object $authGroups): ?string`

- [ ] **Step 1: test unitari** con un oggetto `AuthGroups` fittizio che riproduce quello dell'helpdesk: `admin` con `tickets.*` e `admin.*`, `operatore`, `cliente`. Risultati attesi:
  - `admin` ha `permissions === 14`;
  - `collisions === ['admin']`;
  - `suggestedMode === 'adapt'`;
  - `suggestedMode` su soli `['user','superadmin']` restituisce `'import'`.
- [ ] **Step 2: test di integrazione.**
  - `activeUsers` esclude un utente con `active=0` e uno bannato.
  - `memberCounts` conta per gruppo.
- [ ] **Step 3:** eseguire i test. Atteso: FAIL.
- [ ] **Step 4:** implementare.
- [ ] **Step 5:** eseguire i test. Atteso: PASS.
- [ ] **Step 6:** commit `feat: analisi dell'applicazione host per il wizard`.

### Task 4: modifiche sicure ai file di configurazione dell'host

**Files:**
- Create: `src/Install/ConfigEditor.php`
- Create: `tests/_support/fixtures/host/` con le copie reali, al commit `544b9eb` dell'helpdesk, di `Filters.php`, `Auth.php` e `Routes.php`, più due varianti scritte apposta: una senza `session` globale e una con `except` come stringa.
- Test: `tests/Unit/Install/ConfigEditorTest.php`

**Interfaces:**
- Produces: `final class RoleWarden\Install\ConfigEditor`. Tutti i metodi `with*` sono puri: ricevono il contenuto del file e restituiscono `?string`, cioè il nuovo contenuto, oppure null se lo schema non è riconosciuto o la modifica è già presente. In quel caso il wizard mostra le istruzioni a mano.
  - `globalFilterExcepts(string $filtersPhp): ?array{filter: string, except: list<string>}`: trova un filtro globale `before` tra `session`, `group` e `permission`, e le sue eccezioni.
  - `withFilterExcept(string $filtersPhp, list<string> $paths): ?string`: aggiunge i percorsi agli `except` del filtro trovato. Se `except` è una stringa diventa un array; i percorsi già presenti non vengono duplicati.
  - `withUserProvider(string $authPhp): ?string`: imposta `public string $userProvider = \RoleWarden\Models\UserModel::class;`.
  - `withLoginView(string $authPhp): ?string`: imposta la voce `'login'` di `$views` a `'\RoleWarden\Views\auth\login'`.
  - `withLoginRedirect(string $authPhp): ?string`: imposta la voce `'login'` di `$redirects` a `'rolewarden'`.
  - `withRouteRegistrar(string $routesPhp): ?string`: aggiunge `\RoleWarden\Config\RouteRegistrar::register($routes);` subito dopo la riga `service('auth')->routes($routes);`. Se quella riga manca, alla fine del file.
  - `public static function preview(string $before, string $after): string`: diff unificato, a righe.
  - `public static function apply(string $path, string $after, SetupState $state): bool`. In ordine:
    1. backup in `<path>.rolewarden-bak`;
    2. scrittura;
    3. controllo di sintassi con `php -l`, se `PHP_BINARY` è eseguibile e le funzioni di processo sono attive. Altrimenti il controllo si salta e nel registro la modifica viene segnata con `checked: false`;
    4. se il controllo fallisce, ripristino del backup e `false`;
    5. se riesce, `recordEdit` e `true`.
- Percorsi da aggiungere alle eccezioni: `rolewarden/assets/*`, `rolewarden/theme.css`, `rolewarden/setup*`.

- [ ] **Step 1: test unitari sulle fixture reali dell'helpdesk.**
  - `withFilterExcept` produce `'session' => ['except' => ['login*', 'register', 'auth/a/*', 'logout', 'rolewarden/assets/*', 'rolewarden/theme.css', 'rolewarden/setup*']]`, e il resto del file resta byte per byte uguale.
  - Una seconda chiamata restituisce null (idempotenza).
  - Variante con `except` come stringa: diventa un array.
  - Variante senza filtro globale: `globalFilterExcepts === null` e `withFilterExcept === null`.
  - `withUserProvider` sull'`Auth.php` reale cambia solo la riga 437. Tutti gli output passano `php -l` (in test si può usare `PHP_BINARY`).
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare. Ricerca con espressioni regolari ancorate alla proprietà, oppure `PhpToken`; niente riscrittura completa del file.
- [ ] **Step 4:** eseguire i test. Atteso: PASS.
- [ ] **Step 5:** commit `feat: modifiche sicure e reversibili ai file di configurazione dell'host`.

### Task 5: applicazione delle scelte sui ruoli

**Files:**
- Create: `src/Install/RoleSetup.php`
- Test: `tests/Integration/RoleSetupTest.php`

**Interfaces:**
- Consumes:
  - `GroupImport::DEFERRED` (piano 1): impostato a true prima delle migrazioni, così l'import lo fa questa classe;
  - `PanelRole::KEY`, `DefaultRole::KEY`, `RoleWardenSeeder::DEFAULT_ROLES_KEY` (Task 1);
  - `Models\UserRoles`, `Models\RoleModel`;
  - `HostScan` (Task 3).
- Produces: `final class RoleWarden\Install\RoleSetup`.
  - `public static function prepare(array $choices): void`: da chiamare **prima** delle migrazioni. Imposta `GroupImport::DEFERRED = true` e `RoleWardenSeeder::DEFAULT_ROLES_KEY = ($choices['mode'] === 'import')`.
  - `public static function apply(array $choices, object $authGroups): void`: da chiamare **dopo** le migrazioni, idempotente.
- Formato di `$choices` (lo stesso salvato in `SetupState`):
  - `mode`: `'adapt'|'import'`
  - `panelRole`: lo slug di un gruppo dell'host oppure `''`, solo in modalità adapt
  - `collisions`: `array<slug, array{action: 'merge'|'rename'|'skip', newSlug?: string}>`, solo in modalità import
  - `defaultRole`: uno slug oppure `''`
  - `superAdmin`: `array{kind: 'existing', id: int}|array{kind: 'new', username, email, password}`
  - `visibility`: `'all'|'admins'`
- Comportamento di `apply`:
  - **adapt:** ogni gruppo dell'host diventa un ruolo con i suoi permessi (wildcard espansi) e i suoi membri. `PanelRole::KEY` diventa `panelRole`, e quel ruolo riceve tutti gli slug del modulo (`RoleWardenSeeder::slugs()`).
  - **import:** ogni gruppo senza collisione viene importato come sopra. Per le collisioni:
    - `merge`: il ruolo esistente riceve i permessi e i membri del gruppo;
    - `rename`: il gruppo diventa un ruolo `newSlug`, che deve essere valido e libero;
    - `skip`: nessuna azione.
    `PanelRole::KEY` resta non impostato, quindi vale `admin`.
  - In entrambi i casi si imposta `DefaultRole::KEY = defaultRole` e `Visibility::KEY = visibility`, poi `GroupImport::DEFERRED` torna false.
- Riuso: `GroupImport::expand()` (Task 3) e le stesse regole di slug e permessi di `ImportAuthGroups`, estratte in un metodo condiviso, non copiate.

- [ ] **Step 1: test di integrazione** con un `AuthGroups` come quello dell'helpdesk e righe in `auth_groups_users`.
  - `testAdaptImportsHostGroupsAndMakesPanelRole`: il ruolo `admin` è quello dell'host con 14 permessi più gli slug del modulo, e il suo membro è assegnato. Non esiste un `user` di sistema.
  - `testImportMergeGivesHostPermissionsToSystemAdmin`
  - `testImportRename`: `app-admin` esiste con 14 permessi e il membro.
  - `testImportSkip`
  - `testApplyTwiceDoesNotDuplicate`
  - `testRenameToTakenSlugFails`: lancia `InvalidArgumentException` e non scrive niente, perché si esegue in transazione.
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare.
- [ ] **Step 4:** eseguire i test. Atteso: PASS.
- [ ] **Step 5:** commit `feat: applicazione delle scelte sui ruoli (adatta / importa, collisioni)`.

### Task 6: il wizard, dalla domanda 1 alla 6 (nessuna scrittura)

**Files:**
- Create: `src/Config/Routes.php`. CodeIgniter lo include da solo; contiene **solo** `$routes->match(['GET','POST'], 'rolewarden/setup', '\RoleWarden\Controllers\SetupController::index', ['filter' => 'csrf']);`. Va scritto nel docblock perché questo file esiste accanto a `RouteRegistrar`.
- Create: `src/Controllers/SetupController.php`, `src/Views/setup/index.php` (più i parziali per passo in `src/Views/setup/steps/`)
- Modify: `src/Language/en/RoleWarden.php`, `src/Language/it/RoleWarden.php` (sezione `setup`)
- Test: `tests/Integration/SetupWizardTest.php`

**Interfaces:**
- `SetupController extends CodeIgniter\Controller`, come `InstallController`: niente `auth()`, niente settings del pannello.
- Il passo corrente si ricava dallo stato (`SetupState`) e dal POST, con i passi:
  `code → requirements → filters → roles → collisions (solo import con collisioni) → newUsers → superAdmin → visibility → confirm → done`.
- Ogni POST salva la scelta in `SetupState::setChoice` e passa al passo successivo. "Indietro" torna al passo precedente senza perdere le scelte.
- Sessione: chiave `rw_setup_verified` dopo il codice corretto, come `InstallController`.
- Se `SetupState::completed()`, la risposta è 403 con la pagina "già completato".
- Contenuti dei passi (le copie del testo vanno scritte nei file di lingua, in it e en):
  - **filters:** se `ConfigEditor::globalFilterExcepts` trova un filtro, mostra la riga attuale e l'anteprima. Altrimenti dice "nessuna modifica necessaria".
  - **roles:** due opzioni con conseguenza.
    - "Adatta i ruoli già presenti", consigliata se `suggestedMode` vale `adapt`. Ha una tendina "Chi gestirà il pannello" con i gruppi dell'host più "nessuno (solo super admin)".
    - "Importa i ruoli di RoleWarden".
  - **collisions:** una scheda per gruppo in collisione (bozza A, approvata da Fabio). Mostra utenti e permessi del gruppo dell'host, poi i radio Unisci / Importa con un altro nome (campo precompilato `app-<slug>`) / Salta, ciascuno con la sua conseguenza. La conseguenza di "Salta" dice "N utenti perdono M permessi".
  - **newUsers:** tendina dei ruoli che risulteranno dalla scelta, precompilata con `HostScan::defaultGroup`.
  - **superAdmin:** radio "un utente esistente" (tendina di `HostScan::activeUsers`) / "un nuovo utente" (username, email, password con le regole di Shield, come `InstallController::createAdmin`).
  - **visibility:** "Tutti gli utenti" / "Solo gli amministratori", con la seconda consigliata per app in uso, e le conseguenze della spec (decisione 5).
- Nessuna scrittura prima di `confirm`, salvo `SetupState`.
- Lingua: la prima pagina usa la lingua del browser (`Accept-Language`, tra `en` e `it`). Un selettore in alto la cambia, e la scelta si salva in `SetupState` come `choices.locale`.

- [ ] **Step 1: test di integrazione (FeatureTestTrait, rotte del modulo registrate nei test).**
  - `testWithoutCodeShowsCodeStep`
  - `testWrongCodeRejected`
  - `testCodeEvenWhenSignedIn` (Review Focus 5)
  - `testFullQuestionFlowWritesNothing`: percorre tutte le domande con i POST e alla fine controlla che non ci siano tabelle nuove, che `Filters.php`/`Auth.php` di una copia temporanea siano invariati e che non esistano impostazioni RoleWarden. Le scelte invece sono in `SetupState`.
  - `testCollisionsStepOnlyForImportWithCollisions`
  - `testTamperedSuperAdminIdRejected` (Review Focus 4)
  - `testCompletedReturns403`
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare. Per i percorsi dei file dell'host il controller usa `APPPATH . 'Config/…'`.
- [ ] **Step 4:** eseguire i test. Atteso: PASS, con `TranslationsTest` verde.
- [ ] **Step 5:** commit `feat: wizard /rolewarden/setup, domande e anteprime senza scritture`.

### Task 7: "Conferma e applica", ripresa e riepilogo

**Files:**
- Create: `src/Install/Setup.php` (orchestrazione)
- Modify: `src/Controllers/SetupController.php` (passi `confirm` e `done`), le viste e i file di lingua
- Test: `tests/Integration/SetupApplyTest.php`

**Interfaces:**
- `final class RoleWarden\Install\Setup`
  - `public static function apply(SetupState $state, object $authGroups, string $appConfigPath): array{ok: bool, failedStep: ?string, messages: list<string>}`
  - Esegue nell'ordine vincolante solo i passi non ancora `done`, segnandoli uno per uno:
    1. `RoleSetup::prepare`, poi migrazioni (`Installer::NAMESPACES`, ognuna `latest()`), poi `Readiness::forget()` → `migrations`;
    2. `RoleSetup::apply` → `roles`;
    3. super admin. Per un utente esistente: assegnazione del ruolo `super-admin`. Per uno nuovo: creazione come in `Installer::createSuperAdmin`, ma senza `finish()` → `superAdmin`;
    4. `ConfigEditor::apply` su `Filters.php`, se c'è una modifica → `filters`;
    5. cablaggio. Sempre `withUserProvider` e `withRouteRegistrar`; con `visibility === 'all'` anche `withLoginView` e `withLoginRedirect` → `wiring`.
  - Alla fine: `complete()` e cancellazione del codice monouso, come in `Installer::finish()`.
  - Se un passo fallisce si ferma, salva lo stato e restituisce il passo e un messaggio senza dettagli SQL. Il dettaglio va nel log.
  - Se un file non è scrivibile o lo schema non è riconosciuto, il messaggio mostra il testo da incollare a mano (l'anteprima). Il wizard offre "Ho modificato il file a mano, continua", che ricontrolla il file e segna il passo come fatto.
- Il passo **done** mostra cosa è stato fatto, i file toccati (da `SetupState::edits()`) e il suggerimento "aggiungi un link a `/rolewarden` nel menu della tua app". Se il cablaggio ha cambiato `userProvider`, avvisa che la sessione corrente va rinnovata con un nuovo accesso.

- [ ] **Step 1: test di integrazione.** I file dell'host sono copiati dalle fixture in una directory temporanea passata come `$appConfigPath`.
  - `testApplyAdaptEndToEnd`: tabelle create, ruoli dell'host, ruolo del pannello, super admin esistente assegnato, `Filters.php` con le eccezioni, `Auth.php` con il provider (ma non la vista di login, perché visibility vale admins), `Routes.php` con la riga, e il registro con 3 modifiche e `completed_at`.
  - `testResumeAfterFailure` (Review Focus 3): `Filters.php` reso non scrivibile. Il primo `apply` fallisce su `filters`, con `done` che contiene `migrations`, `roles` e `superAdmin`. Ripristinati i permessi, il secondo `apply` completa senza duplicare ruoli né assegnazioni.
  - `testUnrecognisedFileShowsManualText`
  - `testHostKeepsWorkingBeforeWiring`: dopo le migrazioni e prima del cablaggio, `Readiness::tablesExist()` è true e nessun file dell'host è cambiato.
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare.
- [ ] **Step 4:** eseguire i test. Atteso: PASS.
- [ ] **Step 5:** commit `feat: il wizard applica le scelte nell'ordine sicuro, con ripresa e riepilogo`.

### Task 8: documentazione e collaudo sull'helpdesk da zero

**Files:**
- Modify: `README.md`: sezione "Installing on an existing application (wizard)" con cosa chiede ogni passo e perché, cosa scrive, il registro, cosa fare se un file non è scrivibile, e la nota sul login dell'host con un filtro `session` globale. La guida completa con la procedura manuale resta al piano 5.
- Modify: `CHANGELOG.md` ("Unreleased").

- [ ] **Step 1:** aggiornare README e CHANGELOG.
- [ ] **Step 2:** `vendor/bin/phpunit` completo, anche con `--order-by=reverse`, e `composer analyse`. Atteso: tutto verde.
- [ ] **Step 3: collaudo da zero, senza toccare l'installazione attuale dell'helpdesk.**
  1. Creare un worktree dell'helpdesk al commit `544b9eb` in una cartella temporanea, con `composer install`.
  2. Creare un database nuovo `helpdesk_wizard` nel container `helpdesk-db-1` e lanciare migrazioni e `DemoSeeder`.
  3. Copiare il modulo in `modules/rolewarden` e aggiungere la riga di Autoload.
  4. Avviare `php spark serve` su una porta libera.
  5. Percorrere il wizard via HTTP con scelte adapt, `panelRole=admin`, super admin = utente `admin` esistente, visibility admins.
  6. Verificare:
     - login con l'helpdesk ancora funzionante e con lo stile;
     - `admin` che apre `/rolewarden`;
     - `mario.operatore` che vede la coda;
     - un nuovo iscritto con il ruolo `cliente`;
     - il cambio di ruolo da `/admin/users` effettivo;
     - il registro con le modifiche.
  7. Annotare l'esito in `E:\progetti_lavoro\ASP\helpdesk-ci4\helpdesk\docs\rolewarden-install-log.md` sotto "## Collaudo del wizard (piano 2)".
  8. Togliere il worktree e il database di prova.
- [ ] **Step 4:** commit `docs: wizard di installazione su app esistente`.
