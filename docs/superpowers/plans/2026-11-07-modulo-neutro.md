# Modulo neutro: piano di implementazione (piano 1 di 5)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** il codice Shield esistente dell'host (`addGroup`, `syncGroups`, `addToDefaultGroup`, `$defaultGroup`) e un'installazione a metà non devono più rompere niente né essere ignorati in silenzio. Inoltre servono le due impostazioni su cui si appoggerà il wizard: il ruolo del pannello e la visibilità di Rolewarden.

**Architecture:** si fanno solo modifiche dentro `src/`, seguendo le convenzioni esistenti:
- impostazioni nella Settings library di CodeIgniter, con una classe per impostazione in `src/Settings/`;
- permessi concessi da `RoleWardenSeeder`;
- assegnazioni tramite `Models/UserRoles`.

La logica pura ha test unitari PHPUnit. Il comportamento sul database si verifica con test di integrazione su `rolewarden_test`.

**Tech Stack:** PHP 8.3, CodeIgniter 4.7, Shield 1.x, PHPUnit 11, MariaDB (docker compose del repo).

**Spec:** `docs/superpowers/specs/2026-11-07-installer-grafico-design.md`, sezione "Modifiche al modulo, fuori dal wizard".

**Piani successivi** (ognuno produce software funzionante da solo):
2. wizard nell'app (fase 2);
3. `rolewarden-setup.php` a file unico più la build (fase 1);
4. avviso di aggiornamento nel pannello e registro delle modifiche;
5. documentazione HTML/README (installazione su app esistente, disinstallazione, guida agli zip).

## Global Constraints

- PHP `^8.3`, `codeigniter4/framework ^4.7.4`, `codeigniter4/shield ^1.4`. Nessuna nuova dipendenza.
- Nessun file del modulo deve richiedere modifiche da parte di chi installa. Ogni personalizzazione sta nell'host.
- Ogni testo nuovo va sia in `src/Language/en/RoleWarden.php` sia in `src/Language/it/RoleWarden.php`. `tests/Unit/Language/TranslationsTest.php` deve restare verde.
- Le chiavi delle impostazioni hanno il prefisso `RoleWarden.` (convenzione di `DefaultRole::KEY`).
- Compatibilità: con le impostazioni nuove non valorizzate, un'installazione 1.1.0 esistente si comporta esattamente come prima.
  - Ruolo del pannello = `admin`.
  - Visibilità = `all`.
  - Ruolo di default = setting, altrimenti `$defaultRole`, altrimenti `AuthGroups::$defaultGroup` (novità).
- Ogni commit termina con `Co-Authored-By: Claude Opus 5.5 <noreply@anthropic.com>`.

## Review Focus

1. **Host con `$defaultGroup` che non esiste come ruolo** (per esempio `user` saltato per collisione, o un gruppo rinominato): la registrazione non deve dare errori e l'utente resta senza ruolo, come oggi. Il test va nel Task 3.
2. **`syncGroups()` senza argomenti, chiamato dall'host per togliere tutti i ruoli:** non deve togliere l'ultimo super admin. L'eccezione di protezione si propaga com'è. Il test va nel Task 4.
3. **`RoleWarden.panelRole` che punta a un ruolo cancellato dopo l'installazione:** il seeder e le migrazioni non devono fallire, e i permessi nuovi non vanno a nessuno. Il test va nel Task 2.
4. **Visibilità `admins` con un utente che ha solo un override personale su un permesso del pannello** (nessun ruolo): deve vedere profilo e sessioni, perché conta il permesso effettivo e non il ruolo. Il test va nel Task 6.
5. **Tabelle mancanti a modulo caricato** (stato del collaudo, passo 3b): le pagine dell'host per un utente loggato rispondono 200, mentre `/rolewarden/*` mostra la pagina "non ancora installato" e non una `DatabaseException`. Il test va nel Task 7.

---

### Task 1: base per i test di integrazione su database

**Files:**
- Create: `tests/_support/DatabaseTestCase.php`
- Create: `tests/Integration/DatabaseSmokeTest.php`
- Modify: `phpunit.xml.dist` (variabili `database.tests.*` per `rolewarden_test` sulla porta del docker compose)

**Interfaces:**
- Produces: `abstract class RoleWarden\Tests\Support\DatabaseTestCase extends CodeIgniter\Test\CIUnitTestCase`. Usa `DatabaseTestTrait` con `$refresh = true` e `$namespace = null`, quindi tutte le migrazioni: Settings, Shield, RoleWarden.
- Produces: `protected function makeUser(string $email): int`, che crea un utente attivo con Shield tramite `RoleWarden\Models\UserModel` e restituisce l'id.
- Produces: `protected function roleId(string $slug): ?int` e `protected function rolesOf(int $userId): list<string>` (slug ordinati).

- [ ] **Step 1:** scrivere `DatabaseSmokeTest::testMigrationsCreateTheSystemRoles`. Dopo il refresh, `roleId('super-admin')`, `roleId('admin')` e `roleId('user')` non sono null.
- [ ] **Step 2:** `docker compose up -d`, poi `vendor/bin/phpunit --testsuite Integration --filter DatabaseSmokeTest`. Atteso: FAIL perché la classe base non esiste.
- [ ] **Step 3:** implementare `DatabaseTestCase` e le variabili in `phpunit.xml.dist`.
- [ ] **Step 4:** rilanciare il test. Atteso: PASS.
- [ ] **Step 5:** commit `test: base per test di integrazione su database`.

### Task 2: ruolo del pannello (`RoleWarden.panelRole`)

**Files:**
- Create: `src/Settings/PanelRole.php`
- Modify: `src/Database/Seeds/RoleWardenSeeder.php` (rimuovere `ALL_PERMISSIONS_ROLES`, usare `PanelRole`)
- Modify: le migrazioni `Add*Permissions` e `AddDashboardPermissions` che hanno `forgetAdmin()` (la cache va svuotata per il ruolo del pannello)
- Test: `tests/Unit/Settings/PanelRoleTest.php`, `tests/Integration/PanelRoleTest.php`

**Interfaces:**
- Produces: `final class RoleWarden\Settings\PanelRole`.
  - `public const KEY = 'RoleWarden.panelRole'`.
  - `public static function resolve(mixed $stored): ?string`, pura: `null` diventa `'admin'` (compatibilità), `''` diventa `null` (nessuno), una stringa resta com'è.
  - `public static function slug(): ?string`, che legge il setting e passa da `resolve()`.
  - `public static function roleId(): ?int`, che restituisce l'id del ruolo con quello slug non cancellato, oppure null.
- Produces: `RoleWardenSeeder::slugs(): list<string>`, public static. Sono gli slug dei permessi del modulo, cioè quello che oggi è `$ownSlugs`. Lo usa anche il Task 6.

- [ ] **Step 1: test unitario.** `testUnsetMeansAdmin`: `resolve(null) === 'admin'`. `testEmptyMeansNobody`: `resolve('') === null`. `testAStoredSlugIsKept`: `resolve('helpdesk-admin') === 'helpdesk-admin'`.
- [ ] **Step 2: test di integrazione.**
  - `testSeederGrantsModulePermissionsToThePanelRole`: crea un ruolo `boss`, imposta `setting(PanelRole::KEY, 'boss')`, cancella una riga di `role_permissions` di `boss` se c'è e una permission del modulo, poi lancia il seeder. Il permesso ricreato va a `boss`, non ad `admin`.
  - `testDeletedPanelRoleGrantsToNobody`: setting su uno slug inesistente, poi seeder. Nessuna eccezione e nessuna riga nuova in `role_permissions`.
  - `testEmptyPanelRoleGrantsToNobody`.
- [ ] **Step 3:** eseguire i test. Atteso: FAIL.
- [ ] **Step 4:** implementare.
  - Nel seeder il ramo "ruolo appena creato → tutti i permessi" resta solo per `admin`, quando `admin` è il ruolo del pannello.
  - Il ramo "permessi appena creati → ruolo del pannello" usa `PanelRole::roleId()`.
  - `forgetAdmin()` nelle migrazioni diventa `forgetPanelRole()`, con `PanelRole::roleId()`.
- [ ] **Step 5:** `vendor/bin/phpunit`. Atteso: tutto PASS.
- [ ] **Step 6:** commit `feat: il ruolo del pannello è un'impostazione (default admin)`.

### Task 3: ruolo di default ereditato dall'host

**Files:**
- Modify: `src/Settings/DefaultRole.php`
- Test: `tests/Unit/Settings/DefaultRoleTest.php` (estendere), `tests/Integration/DefaultRoleTest.php`

**Interfaces:**
- Produces: `DefaultRole::slugFrom(mixed $stored, ?string $configDefault, ?string $hostDefaultGroup): string`, pura. Restituisce il primo valore valorizzato tra il setting salvato e `$configDefault`, oppure `$hostDefaultGroup` quando sono entrambi null. Il setting `''`, cioè "nessun ruolo" scelto dal pannello, resta `''` e non eredita niente.
- `DefaultRole::role()` cambia solo per usare `slugFrom(setting(KEY), config('RoleWarden')->defaultRole, config('AuthGroups')->defaultGroup ?? null)`. Il resto, cioè la ricerca del ruolo e `reject()`, resta com'è.

- [ ] **Step 1: test unitari.** Casi di `slugFrom`:
  - `(null, null, 'cliente') === 'cliente'`;
  - `('', null, 'cliente') === ''`;
  - `('viewer', null, 'cliente') === 'viewer'`;
  - `(null, 'author', 'cliente') === 'author'`.
- [ ] **Step 2: test di integrazione.**
  - `testRegistrationGetsTheHostDefaultGroup`: crea il ruolo `cliente`, imposta `config('AuthGroups')->defaultGroup = 'cliente'` con `Factories::injectMock` o `config()` in memoria, poi `DefaultRole::assignTo($id)`. `rolesOf($id) === ['cliente']`.
  - `testMissingHostDefaultRoleAssignsNothing` (Review Focus 1): `defaultGroup = 'nonexistent'`, assignTo senza eccezioni, `rolesOf === []`.
- [ ] **Step 3:** eseguire i test. Atteso: FAIL.
- [ ] **Step 4:** implementare.
- [ ] **Step 5:** eseguire i test. Atteso: PASS.
- [ ] **Step 6:** commit `feat: senza impostazione il ruolo di default è il defaultGroup di Shield`.

### Task 4: i metodi "gruppo" di Shield lavorano sui ruoli Rolewarden

**Files:**
- Modify: `src/Entities/User.php`
- Modify: `src/Models/UserModel.php` (ridefinire `addToDefaultGroup`)
- Test: `tests/Integration/ShieldGroupMethodsTest.php`

**Interfaces:**
- Consumes: `Models\UserRoles::assign(int, int)` e `revoke(int, int)`, `Models\RoleModel`, `Settings\DefaultRole::assignTo(int)`.
- Produces: in `RoleWarden\Entities\User`:
  - `addGroup(string ...$groups): static`;
  - `removeGroup(string ...$groups): static`;
  - `syncGroups(string ...$groups): static`.

  Gli slug non sono sensibili alle maiuscole, come `inGroup`. Uno slug sconosciuto o di un ruolo cancellato lancia `CodeIgniter\Shield\Authorization\AuthorizationException::forUnknownGroup($slug)` prima di qualsiasi scrittura. Questi metodi non scrivono mai su `auth_groups_users`.
- Produces: `RoleWarden\Models\UserModel::addToDefaultGroup(User $user): void`, che delega a `DefaultRole::assignTo((int) $user->id)`.

- [ ] **Step 1: test di integrazione.**
  - `testAddGroupAssignsTheRole`: ruoli `operatore` e `cliente`, `$user->addGroup('operatore')`. `rolesOf === ['operatore']`, e la tabella `auth_groups_users` resta vuota per quell'utente.
  - `testSyncGroupsReplacesRoles`: con il ruolo `cliente`, `syncGroups('operatore')` porta a `['operatore']`.
  - `testRemoveGroup`.
  - `testUnknownGroupThrowsAndWritesNothing`: `addGroup('operatore', 'ghost')` lancia l'eccezione e `rolesOf === []`.
  - `testSyncGroupsCannotRemoveTheLastSuperAdmin` (Review Focus 2): l'unico super admin chiama `syncGroups()`. Si propaga l'eccezione di protezione e il ruolo resta.
  - `testAddToDefaultGroupUsesTheDefaultRole`.
  - `testAddToDefaultGroupTwiceAssignsOnce`.
  - `testCanReflectsTheChangeImmediately`: dopo `addGroup` il resolver risponde subito, perché la cache viene svuotata da `UserRoles`.
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare.
  - In `syncGroups` si risolvono prima tutti gli slug, poi si fanno le revoche dei ruoli in più e infine le assegnazioni.
  - Va mantenuto il commento della classe ("Group compatibility…") aggiornato.
- [ ] **Step 4:** eseguire i test. Atteso: PASS.
- [ ] **Step 5:** commit `feat: addGroup/removeGroup/syncGroups e addToDefaultGroup scrivono i ruoli RoleWarden`.

### Task 5: import dei gruppi, avviso in CLI e rinvio al wizard

**Files:**
- Create: `src/Install/GroupImport.php`
- Modify: `src/Database/Migrations/2026-09-22-090001_ImportAuthGroups.php`
- Modify: `src/Database/Seeds/RoleWardenSeeder.php` (nessun output quando viene richiamato da una migrazione)
- Test: `tests/Unit/Install/GroupImportTest.php`, `tests/Integration/ImportAuthGroupsTest.php`

**Interfaces:**
- Produces: `final class RoleWarden\Install\GroupImport`.
  - `public const DEFERRED = 'RoleWarden.importDeferred'`: un bool. Quando è true, la migrazione non importa niente, perché l'import lo farà il wizard (piano 2).
  - `public static function plan(array $groups, array $matrixCounts, array $memberCounts, array $existingRoleSlugs): array`, pura. Restituisce `{import: list<string>, skipped: list<array{slug: string, users: int, permissions: int}>}`.
  - `public static function report(array $plan, string $locale): list<string>`, pura. Produce le righe da stampare.
    - Una riga per ogni gruppo saltato. Testo it: `Gruppo "admin" non importato: esiste già il ruolo "admin". 1 utente perde 14 permessi: concedili dal pannello (Ruoli).`
    - Una riga finale: `Importati: operatore, cliente. Saltati: admin.`
    - Le stringhe vengono dai file di lingua, sezione `install.import`.
- La migrazione usa `plan()` per decidere. Se `is_cli()` e `report()` non è vuoto, stampa le righe con `CLI::write`.
- Il seeder richiamato dalle migrazioni non stampa più `Seeded: …`. Va passato con `$this->silent` / `setSilent(true)` del Seeder di CI.

- [ ] **Step 1: test unitari.**
  - `testCollidingGroupIsSkippedWithCounts`: gruppi `admin`, `operatore` e `cliente`, slug esistenti `admin`, `user` e `super-admin`. Risultato: `import === ['operatore','cliente']` e `skipped[0] === ['slug'=>'admin','users'=>1,'permissions'=>14]`.
  - `testReportItalian`: le righe sono esattamente quelle sopra.
  - `testNoCollisionNoSkippedLine`.
- [ ] **Step 2: test di integrazione.**
  - `testDeferredImportsNothing`: setting a true, rollback e migrazione di `ImportAuthGroups`. Non c'è nessun ruolo nuovo dai gruppi dell'host.
  - `testNotDeferredBehavesAsBefore`.
- [ ] **Step 3:** eseguire i test. Atteso: FAIL.
- [ ] **Step 4:** implementare.
- [ ] **Step 5:** eseguire i test. Atteso: PASS. Controllo a mano: `php spark migrate -n RoleWarden` sull'app di test non stampa più righe `Seeded:` ripetute.
- [ ] **Step 6:** commit `feat: import dei gruppi Shield con avviso sui gruppi saltati e modalità rinviata`.

### Task 6: "Chi vede Rolewarden" (`RoleWarden.visibility`)

**Files:**
- Create: `src/Settings/Visibility.php`
- Create: `src/Filters/AccountVisibility.php` (alias `rw-account`, registrato come gli altri filtri del modulo)
- Modify: `src/Config/RouteRegistrar.php` (`rw-account` sulle rotte `profile*` e `sessions*` proprie dell'utente)
- Modify: `src/Controllers/SettingsController.php`, `src/Views/settings/index.php` (campo nella sezione Ruoli)
- Modify: `src/Language/en/RoleWarden.php`, `src/Language/it/RoleWarden.php`
- Test: `tests/Unit/Settings/VisibilityTest.php`, `tests/Integration/AccountVisibilityTest.php`

**Interfaces:**
- Consumes: `RoleWardenSeeder::slugs()` (Task 2), `service('rolewarden')->canAny(int, list<string>)`.
- Produces: `final class RoleWarden\Settings\Visibility`.
  - `public const KEY = 'RoleWarden.visibility'`, `public const ALL = 'all'`, `public const ADMINS = 'admins'`.
  - `public static function mode(): string`: `all` se non impostato o se il valore non è valido.
  - `public static function allows(string $mode, bool $holdsPanelPermission): bool`, pura.
- Il filtro risponde 404 (`PageNotFoundException`) quando `! Visibility::allows(Visibility::mode(), canAny(id, RoleWardenSeeder::slugs()))`.
- Testi dell'impostazione:
  - it: titolo "Chi vede RoleWarden"; opzioni "Tutti gli utenti" e "Solo gli amministratori"; aiuto "Con «Solo gli amministratori» profilo e sessioni di RoleWarden si aprono solo a chi ha almeno un permesso del pannello. Le sessioni restano tracciate e le email di sicurezza partono per tutti."
  - en: "Who sees RoleWarden" / "All users" / "Administrators only" / "With «Administrators only», RoleWarden's profile and sessions open only to users holding at least one panel permission. Sessions are still tracked and security emails still go out for everyone."

- [ ] **Step 1: test unitari.** `allows('all', false) === true`, `allows('admins', false) === false`, `allows('admins', true) === true`, e un valore non valido letto come `all`.
- [ ] **Step 2: test di integrazione.**
  - `testAdminsModeHidesProfileFromPlainUsers`: risposta 404.
  - `testAdminsModeShowsProfileToPanelRole`: risposta 200.
  - `testOverrideOnPanelPermissionCounts` (Review Focus 4): un utente senza ruoli ma con override concesso su `users.view` riceve 200.
  - `testAllModeUnchanged`.
  - `testSettingIsSavedFromTheSettingsScreen`.
- [ ] **Step 3:** eseguire i test. Atteso: FAIL.
- [ ] **Step 4:** implementare. Il cambio di impostazione va registrato nel log attività come le altre, tramite `ActivityLog::diff`.
- [ ] **Step 5:** eseguire i test. Atteso: PASS, compreso `TranslationsTest`.
- [ ] **Step 6:** commit `feat: impostazione "Chi vede RoleWarden" (tutti / solo amministratori)`.

### Task 7: modulo caricato ma tabelle assenti

Scelta mia, da confermare in revisione. La spec chiede "non ancora installato" invece dell'eccezione. Per rispettare anche l'anti-obiettivo "app host mai in errore", applico il messaggio solo alle rotte `/rolewarden/*`. Sul resto dell'app, i controlli tramite Rolewarden rispondono come se l'utente non avesse ruoli né permessi, e l'app continua a funzionare.

**Files:**
- Create: `src/Install/Readiness.php`
- Modify: `src/Adapters/DatabaseStore.php` (risposte vuote quando `! Readiness::tablesExist()`)
- Modify: `src/Config/RouteRegistrar.php` e un filtro `rw-ready` sul gruppo `rolewarden`, con una vista `src/Views/install/not_installed.php`
- Modify: i file di lingua (sezione `install.notInstalled`)
- Test: `tests/Integration/ReadinessTest.php`

**Interfaces:**
- Produces: `final class RoleWarden\Install\Readiness`.
  - `public static function tablesExist(): bool`: controlla `acl_user_roles` con `tableExists()`, con memoria valida per la richiesta.
  - `public static function forget(): void`: usato dai test e dal wizard dopo le migrazioni.
- Testi: titolo "RoleWarden non è ancora installato", corpo "Le tabelle del modulo non esistono ancora. Completa l'installazione per usare il pannello.", link "Apri l'installer" verso la rotta dell'installer esistente. In inglese: "RoleWarden is not installed yet" / "The module's tables do not exist yet. Finish the installation to use the panel." / "Open the installer".

- [ ] **Step 1: test di integrazione** (Review Focus 5). Il test cancella le tabelle `acl_*` con `forge->dropTable` e chiama `Readiness::forget()`.
  - `testHostKeepsWorkingWithoutTables`: `$user->can('anything') === false` senza eccezioni, e `getGroups() === []`.
  - `testPanelShowsNotInstalledPage`: `GET rolewarden` risponde con il testo "not installed yet" e stato 503.
- [ ] **Step 2:** eseguire i test. Atteso: FAIL con `DatabaseException`.
- [ ] **Step 3:** implementare.
- [ ] **Step 4:** eseguire i test. Atteso: PASS.
- [ ] **Step 5:** commit `feat: con le tabelle assenti l'host continua a funzionare e il pannello rimanda all'installer`.

### Task 8: CHANGELOG, README e verifica sull'helpdesk

**Files:**
- Modify: `CHANGELOG.md` (voce "Unreleased": i cinque comportamenti nuovi, con la compatibilità)
- Modify: `README.md`, nelle sezioni "Settings and the default role" e "Importing existing Shield groups": fallback del ruolo di default, metodi gruppo, avviso dell'import, ruolo del pannello, visibilità. La guida completa all'installazione appartiene al piano 5.

- [ ] **Step 1:** aggiornare CHANGELOG e README.
- [ ] **Step 2:** `vendor/bin/phpunit` completo. Atteso: tutto PASS.
- [ ] **Step 3: verifica end-to-end sull'helpdesk** (`E:\progetti_lavoro\ASP\helpdesk-ci4\helpdesk`, installazione del collaudo).
  1. Copiare `src/` in `modules/rolewarden/src`.
  2. Registrare un utente nuovo: deve ricevere il ruolo `cliente` e poter aprire un ticket.
  3. Promuovere un utente da `/admin/users`: il ruolo Rolewarden deve cambiare.
  4. `php spark migrate -n RoleWarden` non deve mostrare righe `Seeded:` ripetute.
  5. Annotare l'esito nel registro `docs/rolewarden-install-log.md` dell'helpdesk.
- [ ] **Step 4:** commit `docs: changelog e README per il modulo neutro`.

### Task 9: il modello utenti di Shield chiesto dall'host restituisce le entità RoleWarden

Aggiunto durante l'esecuzione (ruling del controller). La verifica sull'helpdesk ha mostrato che `App\Controllers\Admin\Users` carica gli utenti con `model(CodeIgniter\Shield\Models\UserModel::class)`. Riceve quindi entità Shield, e il suo `syncGroups()` scrive su `auth_groups_users`: il cambio di ruolo dall'host resta senza effetto (attrito 6b del collaudo, obiettivo 7 della spec).

**Files:**
- Modify: `src/Config/Events.php` oppure `src/Config/Registrar.php` (dove il modulo si aggancia all'avvio, prima del primo `model()`, sia nel ciclo web sia con spark)
- Modify: `README.md` e `CHANGELOG.md` (togliere il vincolo "l'host deve caricare gli utenti con il UserModel di RoleWarden" e spiegare il nuovo comportamento e il suo limite)
- Test: `tests/Integration/ShieldUserModelAliasTest.php`

**Interfaces:**
- Il modulo registra `CodeIgniter\Config\Factories::define('models', \CodeIgniter\Shield\Models\UserModel::class, \RoleWarden\Models\UserModel::class)`. Va fatto solo quando `config('Auth')->userProvider` è `RoleWarden\Models\UserModel` (o una sua sottoclasse), così un host che non ha cablato il provider non cambia comportamento.
- Limite da documentare: copre `model()`/`Factories`, non un `new UserModel()` diretto.

- [ ] **Step 1: test di integrazione.**
  - `testShieldUserModelResolvesToRoleWardenModel`: con il provider cablato, `model(\CodeIgniter\Shield\Models\UserModel::class)` è un'istanza di `RoleWarden\Models\UserModel`.
  - `testSyncGroupsThroughShieldModelWritesRoleWardenRoles`: un utente caricato con `model(ShieldUserModel)->findById($id)`, poi `syncGroups('operatore')`. `rolesOf === ['operatore']` e `auth_groups_users` resta vuota.
  - `testNoAliasWithoutProvider`: con `userProvider` impostato al modello di Shield, `model(ShieldUserModel)` resta quello di Shield.
- [ ] **Step 2:** eseguire i test. Atteso: FAIL.
- [ ] **Step 3:** implementare e aggiornare README e CHANGELOG.
- [ ] **Step 4:** suite completa. Atteso: PASS. Poi ripetere il controllo (c) del Task 8 sull'helpdesk: copiare `src/` in `modules/rolewarden/src` e chiamare `syncGroups` tramite il modello Shield. Annotare l'esito nel registro dell'helpdesk.
- [ ] **Step 5:** commit `feat: il UserModel di Shield richiesto dall'host diventa quello di RoleWarden`.
