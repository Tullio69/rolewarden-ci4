# Inventario verificato per la scheda CodeCanyon

Base di verifica: commit `6313215` su `origin/main` (tag v1.0.0, 2026-10-18). Ogni voce qui
sotto è stata letta nel codice, non nel README. Le note per Fabio sono in italiano; i testi
della scheda stanno in `scheda-prodotto.md`.

Nota di contesto: il lavoro della v1.1 (barra, verifica permesso, ricerca comandi, dashboard)
vive solo nel repo locale e non è su GitHub, quindi NON compare in questo inventario né nella
scheda. Se la scheda deve uscire con la 1.1.0, va rifatta una passata su quelle tappe.

## 1. Funzionalità implementate (con file che le realizza)

### Autorizzazione (strato indipendente dal framework)
- Risoluzione `can(user, permission)` con precedenza fissa: utente disattivato nega tutto;
  override negativo dell'utente (batte anche il super admin); override positivo; super
  admin; permesso da ruolo proprio o ereditato; altrimenti nega.
  `src/Authorization/Resolver.php`
- `canAny`, `canAll`, `authorize` (lancia `AuthorizationException`), `permissions()`
  (elenco esplicito dei permessi effettivi). `src/Authorization/Resolver.php`
- Gerarchia ruoli: un ruolo eredita i permessi del padre lungo la catena `parent_id`; il
  flag super admin NON si eredita. `Resolver::build()`
- Più ruoli per utente (assign aggiunge, non sostituisce). `src/Models/UserRoles.php`,
  `UsersController::assignRole`
- Slug permesso obbligatorio nel formato `area.azione`, validato con regex; niente
  wildcard. `Resolver::assertSlug`
- Cache per utente degli snapshot di permesso, invalidata su cambio utente/ruolo (incluso
  sottoalbero dei ruoli figli). `Resolver::forgetUser/forgetRole`, `src/Adapters/CiCache.php`
- Protezioni di scrittura (`src/Authorization/Guard.php`): nessun ciclo di ereditarietà,
  padre inesistente rifiutato, ruoli e permessi di sistema non cancellabili, ruolo con figli
  non cancellabile, mai senza un super admin attivo (su delete/deactivate utente, revoca
  ruolo super, rimozione flag super), chi si toglie da solo l'unico ruolo con
  `roles.assign` viene fermato.
- Contratti `AuthorizationStore`, `ProtectionStore`, `Cache` in
  `src/Authorization/Contracts/`; adapter CI4 in `src/Adapters/DatabaseStore.php`.

### Integrazione con Shield
- Entity `RoleWarden\Entities\User` estende quella di Shield: `can()`, `hasPermission()`,
  `inGroup()`, `getGroups()` rispondono dal database (un "gruppo" Shield = slug di un ruolo).
  `src/Entities/User.php`
- `RoleWarden\Models\UserModel` estende `ShieldUserModel`: da indicare come
  `$userProvider` in `app/Config/Auth.php`. Blocca disattivazione/cancellazione dell'ultimo
  super admin e invalida la cache. `src/Models/UserModel.php`
- Filtro di rotta `can:<perm>[,<perm>]` (passa se almeno uno), basato su
  `AbstractAuthFilter` di Shield, redirect a `permissionDeniedRedirect()` di Shield.
  `src/Filters/PermissionFilter.php`
- Helper `can()`, `can_any()`, `can_all()`, `permissions()` (ospite = sempre negato).
  `src/Helpers/rolewarden_helper.php`
- Servizi `rolewarden` (Resolver) e `rolewardenGuard`. `src/Config/Services.php`
- Registrazione automatica dei filtri via `Config/Registrar.php` (alias `can`,
  `rw-signin`, `rw-language`; `rw-signin` messo da solo sulla rotta `login`).
- Hook sugli eventi Shield `register`, `login`, `failedLogin`, `logout` e CI4
  `pre_system`/`post_system`. `src/Config/Events.php`
- Vista di login di Shield ridisegnata (`src/Views/auth/login.php`), da puntare in
  `Auth::$views['login']`. Registrazione e recupero password restano di Shield.
- Import reversibile dei gruppi Shield da `app/Config/AuthGroups.php` e da
  `auth_groups_users` (gruppi con slug già esistente saltati, mai fusi).
  `src/Database/Migrations/2026-09-22-090001_ImportAuthGroups.php`

### Pannello admin (Tailwind v4 compilato + Alpine 3.17, niente build per chi installa)
- Utenti: lista con ricerca, filtro per ruolo e stato, ordinamento, paginazione; crea
  (username, email, password forte, attivo subito, ruolo predefinito); modifica; cancella;
  attiva/disattiva; assegna/revoca ruoli; override per permesso (concedi/nega) dalla matrice
  dell'utente. `src/Controllers/UsersController.php`, `src/Views/users/*`
- Ruoli: CRUD con padre e flag super admin, matrice permessi per ruolo (le voci non
  concesse dicono come concederle), soft delete. `src/Controllers/RolesController.php`,
  `src/Models/RoleModel.php`, `src/Views/roles/*`
- Permessi: elenco raggruppato per area con i ruoli che concedono ciascuno. Solo lettura:
  i permessi si creano con una migrazione dell'app ospite (documentato in
  `documentation/api.html`). `src/Controllers/PermissionsController.php`
- Impostazioni runtime (libreria Settings di CI4, tabella `settings`): ruolo predefinito
  per i nuovi utenti, durata sessione, remember me, blocco dopo N tentativi e durata,
  tentativi al minuto per IP, retention del log. `src/Controllers/SettingsController.php`,
  `src/Settings/*`
- Profilo: cambio password con conferma della corrente (fa uscire le altre sessioni),
  scelta lingua. `src/Controllers/AccountController.php`
- Sessioni: elenco browser connessi (IP, device da user agent, ultimo accesso), revoca
  singola, revoca tutte, dimenticare browser "ricordati"; un admin con `sessions.view` /
  `sessions.revoke` lo fa per altri. Tracciamento in `acl_sessions` via `pre_system`, logout
  forzato al giro successivo del browser revocato o oltre la durata di sessione.
  `src/Account/Sessions.php`
- Limiti di accesso: rate per IP (throttler CI4) e lock per email contato da `auth_logins`
  di Shield. `src/Filters/SignInThrottle.php`, `src/Settings/SignIn.php`
- Log attività: tabella `acl_activity_log` con nomi copiati (resta leggibile dopo rename o
  delete), tentativi di login letti da `auth_logins` e fusi nella lista, filtri per utente
  (anche cancellati), azione e periodo, pruning giornaliero alla prima scrittura, evento
  `rolewarden.activity`. `src/Account/ActivityLog.php`, `src/Controllers/ActivityController.php`
- Email di sicurezza: accesso da device/IP nuovo (non al primo login), password cambiata
  (da profilo o da admin), lock per troppi tentativi (anche ai titolari di
  `security.alerts`, una volta per lock). Inviate a fine risposta via shutdown function con
  il servizio Email di CI4; evento `rolewarden.mail` per prenderle in carico (listener che
  ritorna `false`); template in `src/Views/emails/*`, nella lingua del destinatario.
  `src/Account/SecurityMail.php`
- Temi: Console, Clarity, Contrast, ciascuno chiaro e scuro; scelta chiaro/scuro/sistema
  per utente dalla barra; personalizzatore (`/rolewarden/appearance`) con anteprima live,
  colori per chiaro e scuro, raggio e densità, controllo contrasto WCAG AA, salvataggio in
  `writable/rolewarden/theme.json`, download del file, precedenza a
  `app/Config/RoleWarden/theme.json` se esiste; temi extra e stylesheet dell'ospite via
  `$extraThemes` e `$themeStylesheet`. `src/Controllers/AppearanceController.php`,
  `src/Settings/Theme.php`, `src/Settings/ColorScheme.php`
- Import CSV utenti: colonne `email`, `username` obbligatorie, `role` e `password`
  opzionali; virgola o punto e virgola; max 1000 righe, 1 MB, 50 password; anteprima riga
  per riga, file rifiutato se una riga è errata, creazione in una transazione, utenti
  attivi, password hashate da Shield già in anteprima, anteprima valida 30 minuti, file di
  esempio scaricabile. `src/Users/CsvImport.php`, `src/Users/UserImport.php`,
  `src/Controllers/ImportController.php`
- Lingue: inglese e italiano (555 chiavi in `src/Language/en/RoleWarden.php`), scelta per
  utente dalla barra o dal profilo, `$languages` per limitare l'offerta, lingua aggiuntiva
  = un file in `app/Language/{locale}/`. `src/Filters/PanelLanguage.php`,
  `src/Settings/UserLanguage.php`
- Testi dei ruoli/permessi di serie tradotti finché un admin non li modifica.
  `src/Models/SeededText.php`, `src/Language/*/RoleWardenData.php`
- Toast per l'esito di ogni azione. `src/Views/partials/flash.php`, `src/Assets/js/panel.js`
- Asset serviti dal pacchetto (`/rolewarden/assets/...`), nessun publish in `public/`.
  `src/Controllers/AssetController.php`
- Viste sostituibili con il meccanismo nativo `app/Views/overrides/RoleWarden/Views/...`.
- Installer da browser `/rolewarden/install`: controllo PHP >= 8.3.0, estensioni `intl`,
  `mbstring`, `json`, `mysqli`, `writable/` scrivibile, `.env`; codice di setup in
  `writable/rolewarden/install-code.txt`; test connessione DB; scrive `.env` con chiave di
  cifratura; migra Settings, Shield, RoleWarden in batch separati; crea il primo super
  admin; lock in `writable/rolewarden/installed.lock`; rifiuta di ripartire se esiste un
  super admin attivo. `src/Install/Installer.php`, `src/Controllers/InstallController.php`

### Dati di serie
- Ruoli di sistema: `super-admin` (flag super), `admin` (riceve tutti i permessi del
  modulo), `user`. Nessuna assegnazione a utenti. `src/Database/Seeds/RoleWardenSeeder.php`
- Permessi di sistema (da `src/Language/en/RoleWardenData.php` e migrazioni):
  `users.view/create/update/delete/activate/import`, `roles.view/create/update/delete/assign`,
  `permissions.view/override`, `settings.view/update`, `sessions.view/revoke`,
  `activity.view`, `security.alerts`, `appearance.update`.

## 2. Requisiti reali
- PHP `^8.3` (composer.json; installer `MIN_PHP = 8.3.0`). README dichiara test fino a
  8.5: [DA VERIFICARE] che la 8.5 sia davvero stata provata prima di scriverlo in scheda
  (nella scheda ho messo "8.3 or later").
- CodeIgniter `codeigniter4/framework ^4.7.4` (necessaria `app/Views/overrides/` di 4.7).
- `codeigniter4/shield ^1.4`.
- Estensioni PHP: `intl`, `mbstring`, `json`, `mysqli` (controllate dall'installer).
- Database: MySQL o MariaDB con InnoDB (README; installer richiede `mysqli`; migrazioni con
  foreign key). Nessun codice specifico per Postgres/SQLite: [DA VERIFICARE] se si vuole
  dichiararli, oggi non sono testati e nella scheda non vanno promessi.
- Dipendenze Composer e licenze (`THIRD-PARTY-LICENSES.md`): codeigniter4/framework MIT,
  codeigniter4/shield MIT, codeigniter4/settings MIT (arriva con Shield),
  codeigniter4/translations MIT (suggerita, inclusa nell'app completa), laminas-escaper
  BSD-3, psr/log MIT. Incluse nel modulo: Alpine.js 3.17.4 MIT, IBM Plex (Latin subset)
  SIL OFL 1.1, Tailwind CSS 4.3.3 MIT (solo come CSS compilato).
- Dev only (non vanno in scheda): php-cs-fixer, phpstan 2, phpunit 11.5.
- Nessun Node/npm per chi installa; CSS già compilato e committato.

## 3. Cosa è incluso nel pacchetto
Tre zip prodotti da `bin/build-package` (esclusioni in `.gitattributes`):
- `rolewarden-module-x.y.z.zip`: `src/` (14 migrazioni, 1 seeder, config, 11 controller,
  viste, helper, filtri, 2 lingue, asset compilati con font IBM Plex), `documentation/`
  (8 pagine HTML: index, installation, configuration, shield, api, views, design-guide,
  troubleshooting), README, CHANGELOG, THIRD-PARTY-LICENSES, composer.json.
- `rolewarden-app-x.y.z.zip`: CI4 appstarter + Shield + translations + modulo in
  `modules/rolewarden` + demo in `modules/rolewarden-demo` (non installata), `vendor/`
  incluso, già cablato, si apre sull'installer.
- `rolewarden-demo-x.y.z.zip`: registro documenti con ruoli Reader/Author/Approver
  (Author eredita Reader, Approver eredita Author), 5 permessi `documents.*`, 3 account,
  4 documenti di esempio, comandi `demo:install`/`demo:uninstall`, modalità showcase
  (`demo.showcase = true`, ignorata in production).
- NON inclusi nei pacchetti: tests, docs/, docker, bin, resources (sorgente Tailwind),
  _AI-LOG, CLAUDE.md.
- Dati demo nel modulo: nessuno oltre ai ruoli/permessi di sistema; i dati di esempio
  stanno solo nell'app demo.

## 4. Presente solo in documenti/commenti o incompleto (NON in scheda)
Dalla matrice di `docs/SPEC.md` e dalla roadmap: tutto quanto segue non ha codice nel repo.
- 2FA TOTP e 2FA via email (v1.5)
- Login social Google/Facebook, provider OAuth configurabili, collegamento account (v1.5)
- JWT / token API gestiti dal pannello (v1.5)
- Centro notifiche persistente e preferenze di notifica (v1.5); tabelle
  `acl_notifications`, `acl_notification_preferences`, `acl_oauth_states` solo in SPEC
- Inviti utente via email (v1.5)
- Policy password configurabili (v1.5)
- Impersonazione utente (spostata a v1.5 il 24/10)
- Esportazione/importazione tema oltre al download, tipografia personalizzata (v1.5)
- Passkey WebAuthn, push web, Slack/Telegram, permessi a livello di campo, multi-tenant,
  webhook, export GDPR (v2.0)
- Creazione permessi dal pannello: non prevista per scelta di design (si fa via migrazione)
- Dashboard, barra nuova, ricerca comandi, verifica permesso (U1-U4 della v1.1): solo in
  locale, non su GitHub
- Nota per la memoria del progetto: la voce "JWT+session, TOTP/email 2FA, OAuth social
  login, webhooks, admin SPA" descrive il vecchio AuthConnect, non il codice di Rolewarden.

## [DA VERIFICARE] riepilogo
1. PHP 8.5 davvero testata (README lo dice, nessun test nel repo lo prova).
2. Database diversi da MySQL/MariaDB: non testati, non dichiarare.
3. Browser minimi: Tailwind v4 e Alpine 3 richiedono browser moderni (indicativamente
   Chrome/Edge 111+, Firefox 128+, Safari 16.4+); nessun test di compatibilità nel repo.
4. La prima immagine della scheda era prevista sulla dashboard della 1.1 (decisione
   24/10): con il codice su GitHub la prima schermata disponibile è la matrice permessi.
5. Prezzo: $29/$99 deciso il 18/10 e confermato qui; i 3-4 item analoghi "da analizzare
   ancora" di `docs/COMMERCIALE.md` non risultano analizzati nel repo.
