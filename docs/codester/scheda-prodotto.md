# Scheda prodotto Codester - RoleWarden 1.2.0

Note per Fabio (italiano). I blocchi di codice sono il testo da incollare su Codester, in
inglese. Derivata da `docs/codecanyon/scheda-prodotto.md` (1.1.0) e aggiornata alla 1.2.0
(wizard `/rolewarden/setup`, CHANGELOG del 2026-11-08). Le funzioni 1.1 restano coperte da
`docs/codecanyon/inventario.md`; quelle 1.2 dal CHANGELOG e dal collaudo del wizard
(`helpdesk-ci4/docs/rolewarden-install-log.md`).

**Requisiti di Codester: non verificati sul sito.** codester.com rifiuta la lettura
automatica (403). Quello che segue viene dalle loro pagine indicizzate: anteprima 1600x800
(il blog; la guida vecchia diceva 800x400), icona 400x400 (la guida vecchia 200x200), .png o
.jpg, screenshot di supporto senza misure, descrizione di almeno 300 caratteri con funzioni e
requisiti, diritti su tutto il contenuto. **Controlla le misure nel form di caricamento**:
se sono diverse, le immagini si rigenerano (vedi `README.md`).

Marcati **[DA VERIFICARE]**: punti che dipendono dal form o da decisioni tue.

---

## 1. File da caricare

| Campo del form | File | Misura |
|---|---|---|
| Item / main file | `build/1.2.0/rolewarden-module-1.2.0.zip`, `rolewarden-app-1.2.0.zip` e `rolewarden-demo-1.2.0.zip` in un unico zip, oppure i tre separati se il form li accetta **[DA VERIFICARE]** | |
| Preview image | `docs/codester/preview-1600x800.png` | 1600x800 |
| Icon / thumbnail | `docs/codester/icon-400x400.png` | 400x400 |
| Screenshots, in ordine | `docs/codecanyon/screenshots/01-dashboard.png`, `02-role-author.png`, `03-user-author.png`, `04-check.png`, `05-users.png`, `06-activity.png`, `07-appearance.png`, `08-cmdk-dark.png`, poi `docs/codester/screenshots/09-wizard-roles.png`, `10-wizard-confirm.png`, `11-wizard-done.png` | 1440x900 |
| Video (se il form lo prevede) | `renders/rolewarden-promo-v2-voce-16x9.mp4` | 16:9 |

Gli screenshot 01-08 sono quelli della 1.1.0: le schermate del pannello non sono cambiate in
modo visibile nella 1.2. Il wizard (09-11) è stato scattato il 2026-11-08 dallo zip 1.2.0 su
un'app Shield di prova in inglese (un negozio con gruppi admin, manager, support, customer),
con la correzione CSS del commit `d2301b5`.

Se il form chiede **un solo zip**: crea `rolewarden-1.2.0-codester.zip` con dentro i tre zip
e `SHA256SUMS`. Lo zip del modulo da solo non basta a chi non ha Composer.

## 2. Titolo

Lo stesso approvato per CodeCanyon il 2026-10-31 (78 caratteri). Se il form ha un limite
più basso, la variante corta.

```
RoleWarden - CodeIgniter 4 Shield Roles, Permissions & User Management Panel
```

```
RoleWarden - Roles & Permissions Panel for CodeIgniter 4 Shield
```

## 3. Descrizione breve / tagline

```
Move roles and permissions out of AuthGroups.php and into the database, with an admin panel built on Shield and a setup wizard for the app you already have.
```

## 4. Descrizione completa

Codester accetta HTML nella descrizione? **[DA VERIFICARE]** Se il campo è testo semplice,
togli i tag: i paragrafi restano leggibili.

```html
<h2>What RoleWarden does</h2>
<p>CodeIgniter Shield handles identity: passwords, sessions, remember-me, login attempts. Its roles and permissions, though, live in <strong>app/Config/AuthGroups.php</strong>, so every change to who can do what is a code change and a deploy. RoleWarden moves roles, permissions, role inheritance and per-user overrides into the database and adds an admin panel to manage them at runtime, without replacing Shield or touching its tables.</p>
<p>Your code keeps checking permissions the Shield way: <strong>auth()->user()->can('invoices.approve')</strong>, the <strong>can:</strong> route filter, a <strong>can()</strong> helper in views. The answer now comes from the database, cached per user, and an administrator changes it from a browser.</p>
<p>Built for developers and agencies who ship CodeIgniter 4 applications to clients and need a permission layer that is in place on day one and that the client can manage without calling you.</p>

<h2>New in 1.2: a setup wizard for the application you already have</h2>
<p>Open <strong>/rolewarden/setup</strong> in an existing CodeIgniter 4 + Shield application and RoleWarden is added step by step, from the browser:</p>
<ul>
<li>Load the module with one line in <strong>app/Config/Autoload.php</strong> (or with Composer). Nothing else to wire by hand.</li>
<li>The wizard checks the server, finds a global session filter and lets RoleWarden's assets through it, and shows your Shield groups with their users and permissions.</li>
<li><strong>Adapt</strong> keeps your groups as RoleWarden roles, so your code keeps working; <strong>Import</strong> adds RoleWarden's roles next to yours, and lets you merge, rename or skip a group with the same name.</li>
<li>Choose the role for new sign-ups, the super admin (an existing user or a new one) and whether your users see RoleWarden's screens or only your administrators do.</li>
<li>Nothing is written until <strong>Confirm and apply</strong>, which shows the exact change to each file first. Every file it edits is syntax-checked, backed up as <strong>.rolewarden-bak</strong> and written atomically; a file it cannot change stops the step with the text to paste by hand.</li>
</ul>

<h2>Features</h2>

<h3>Authorization engine</h3>
<ul>
<li>Permissions identified by slug (<strong>area.action</strong>), grouped by area; code checks permissions, never roles.</li>
<li>Role inheritance: a role receives the permissions of its parent chain.</li>
<li>Per-user overrides: grant or deny a single permission to one user. A denial beats every role, including super admin.</li>
<li>Fixed resolution order: deactivated user, user denial, user grant, super admin, role permissions, otherwise denied.</li>
<li>Several roles per user; super admin as a flag on a role.</li>
<li>Per-user permission cache, invalidated on every change to the user, the role or its descendants.</li>
</ul>

<h3>Shield integration</h3>
<ul>
<li>Drop-in user model and entity: <strong>$user->can()</strong>, <strong>hasPermission()</strong>, <strong>inGroup()</strong> and <strong>getGroups()</strong> answer from the database.</li>
<li><strong>addGroup()</strong>, <strong>removeGroup()</strong> and <strong>syncGroups()</strong> now act on RoleWarden roles, so your existing user-management code keeps working.</li>
<li>Route filter <strong>can:perm.one,perm.two</strong>, and helpers <strong>can()</strong>, <strong>can_any()</strong>, <strong>can_all()</strong>, <strong>permissions()</strong>.</li>
<li>Import of existing Shield groups, permissions and assignments.</li>
<li>Hooks on Shield's register, login, failedLogin and logout events, wired automatically.</li>
</ul>

<h3>Admin panel</h3>
<ul>
<li>Dashboard: active users, sign-ins, failed sign-ins and permission changes for 7, 30 or 90 days, sign-ins by weekday and hour, users per role, latest changes. Every block has its own permission; add blocks of your own.</li>
<li>Users: search, filter, sort; create, edit, activate, deactivate, delete; assign roles; grant or deny single permissions. CSV import with a row-by-row preview.</li>
<li>Roles with a permission matrix each, parent role and super admin flag; permissions grouped by area.</li>
<li>Check a permission: who holds it and why, and for one user the rule that decides it.</li>
<li>Command search with Ctrl K, filtered to what the signed-in user may reach.</li>
<li>Guard rails: no inheritance cycles, system roles cannot be deleted, the application can never be left without an active super admin.</li>
<li>Settings: default role for new users, who sees RoleWarden, session lifetime, sign-in limits, activity log retention.</li>
</ul>

<h3>Security</h3>
<ul>
<li>Sessions list per account with revoke; sign-in throttling per IP and lock per email on top of Shield.</li>
<li>Activity log of every change made from the panel and every sign-in attempt, with filters and retention.</li>
<li>Security emails: new device, password changed, account locked.</li>
</ul>

<h3>Themes and languages</h3>
<ul>
<li>Three themes in light and dark, three sidebar styles, an appearance screen with live preview and a WCAG AA contrast check.</li>
<li>English and Italian; another language is one PHP file.</li>
<li>Tailwind CSS compiled and shipped, Alpine.js bundled: no Node, no npm, no build step on your side.</li>
</ul>

<h3>Three ways to install</h3>
<ul>
<li><strong>Existing application:</strong> the setup wizard at /rolewarden/setup.</li>
<li><strong>From nothing, or without Composer:</strong> the complete application zip (CodeIgniter 4, Shield and RoleWarden, vendor/ included) with a browser installer.</li>
<li><strong>By hand:</strong> documented migrations and wiring, with the module removable on its own.</li>
</ul>

<h2>Requirements</h2>
<ul>
<li>PHP 8.3 or later, with the intl, mbstring, json and mysqli extensions.</li>
<li>CodeIgniter 4.7.4 or later and CodeIgniter Shield 1.4 or later within 1.x.</li>
<li>MySQL or MariaDB with InnoDB (tested on MariaDB 11.8, MySQL 8.0 and MySQL 8.4).</li>
<li>A current browser for the panel: Chrome or Edge 111+, Firefox 128+, Safari 16.4+.</li>
</ul>

<h2>What is included</h2>
<ul>
<li><strong>The module</strong>: source, 16 migrations, seeder, setup wizard, views, English and Italian, compiled CSS and JS, IBM Plex fonts.</li>
<li><strong>The complete application</strong>: CodeIgniter 4, Shield, translations and the module, wired, vendor/ included, opening on the browser installer.</li>
<li><strong>The demo application</strong>: a document register with three roles, the pattern to copy for your own screens.</li>
<li><strong>HTML documentation</strong>: installation with the wizard step by step, configuration, Shield integration, API reference, view overrides, design guide, troubleshooting.</li>
<li>CHANGELOG, SHA-256 checksums and a third-party licence file.</li>
</ul>
<p>Third-party code is open source: CodeIgniter, Shield, Settings and Translations (MIT), Alpine.js (MIT), IBM Plex (SIL OFL 1.1), Tailwind CSS (MIT, shipped only as compiled CSS).</p>

<h2>Demo and documentation</h2>
<ul>
<li>Live demo: <a href="https://demo.rolewarden.com/">https://demo.rolewarden.com/</a>. Sign in as <strong>admin@example.com</strong> / <strong>demo-admin-2026</strong> for the full panel. Data is reset every hour.</li>
<li>Documentation: <a href="https://www.rolewarden.com/docs/">https://www.rolewarden.com/docs/</a>, the same HTML you find in the package.</li>
</ul>

<h2>Support and updates</h2>
<ul>
<li>Updates are migrations: replace the module folder and run <strong>php spark migrate -n RoleWarden</strong>. The changelog lists every change that matters to an installed site.</li>
<li>Support covers installation, configuration and bugs in the module. It does not cover custom development or your application's own code.</li>
</ul>
```

Lunghezza: ben oltre i 300 caratteri richiesti.

## 5. Elenco funzioni breve (se il form ha un campo "features")

```
Roles and permissions in the database, managed from an admin panel
Setup wizard for existing CodeIgniter 4 + Shield applications
Built on CodeIgniter Shield, which keeps identity and sessions
Role inheritance and per-user grant/deny overrides
Drop-in: $user->can(), can: route filter, view helpers
Dashboard with per-permission blocks
Users with CSV import, sessions with revoke, sign-in limits
Activity log and security emails
Three themes, light and dark, live theme customiser
Complete app zip and browser installer for servers without Composer
```

## 6. FAQ

```html
<h3>Does it work with CodeIgniter Shield, or does it replace it?</h3>
<p>It extends Shield. Shield keeps users, credentials, sessions, remember-me, registration and password recovery. RoleWarden replaces only the authorization part (AuthGroups.php) with database tables and a panel, through a user model that extends Shield's.</p>

<h3>I already use Shield groups in production. How do I move to RoleWarden?</h3>
<p>Open the setup wizard at /rolewarden/setup. Adapt keeps your groups as RoleWarden roles, with their users and permissions, so your code keeps working as it is. Import adds RoleWarden's own roles next to yours, and for a group with the same name you choose to merge it, import it under another name or skip it. Nothing is written until you confirm, and every file it changes keeps a backup.</p>

<h3>Can I use it with my own users table or another auth library?</h3>
<p>No. RoleWarden relies on Shield's users table and authentication. An application on Ion Auth, Myth:Auth or a home-made login has to move to Shield first.</p>

<h3>How do I add permissions for my own features?</h3>
<p>With a migration in your application that inserts rows into the permissions table (an example is in the API reference). The panel then lists them and lets you assign them to roles.</p>

<h3>Can I customise the look and the views?</h3>
<p>Yes, without editing the module: colours, radius and density from the Appearance screen, and any view replaced through CodeIgniter's app/Views/overrides/ folder. Updates never overwrite your changes.</p>

<h3>Is it multi-tenant?</h3>
<p>No. Roles and permissions are global to the application in 1.x.</p>

<h3>Does it need Node, npm or a build step?</h3>
<p>No. CSS and JavaScript ship compiled. You install PHP files and run migrations, or let the wizard do it.</p>
```

Tolta la FAQ sulla licenza della scheda CodeCanyon: parlava di Regular/Extended di Envato.
Le licenze di Codester sono diverse e non le ho potute leggere **[DA VERIFICARE nel form]**.

## 7. Changelog per la scheda

```html
<h3>1.2.0 - 2026-11-08</h3>
<ul>
<li>Setup wizard at /rolewarden/setup to add RoleWarden to an existing CodeIgniter 4 + Shield application: adapt or import your groups, choose the super admin and the role for new sign-ups, preview every file change, apply with backups.</li>
<li>Shield's addGroup(), removeGroup() and syncGroups() act on RoleWarden roles.</li>
<li>New setting: who sees RoleWarden's sign-in, profile and sessions screens (everyone or administrators only).</li>
<li>The role that receives the panel's new permissions is a setting (RoleWarden.panelRole); the default role for new sign-ups falls back to Shield's $defaultGroup.</li>
<li>With the module loaded and its tables missing, the host application keeps working.</li>
</ul>
<h3>1.1.0 - 2026-11-02</h3>
<ul>
<li>Dashboard, command search, check a permission, collapsible sidebar, motion.</li>
</ul>
<h3>1.0.0 - 2026-10-18</h3>
<ul>
<li>First release.</li>
</ul>
```

## 8. Metadati

- **Categoria:** PHP Scripts, sottocategoria per gestione utenti o "Miscellaneous" se non
  c'è di meglio **[DA VERIFICARE nel form]**.
- **Tag** (gli stessi approvati il 2026-10-31, più `setup wizard`; se il limite è più basso,
  togli per primi `secure`, `acl`, `ci4`):
  ```
  codeigniter, codeigniter 4, ci4, shield, rbac, roles, permissions, user roles and permissions, access control, authorization, authentication, login, secure, admin panel, user management, acl, activity log, session management, php 8, setup wizard
  ```
- **Versioni:** PHP 8.3, 8.4, 8.5; CodeIgniter 4.7.4+; MySQL/MariaDB; browser Chrome,
  Firefox, Safari, Edge. File inclusi: PHP, HTML, CSS, JS.
- **Prezzo:** su CodeCanyon era $29 Regular / $99 Extended. Su Codester il prezzo lo
  scegli tu e la quota del venditore (70% secondo le recensioni, non verificato) non dipende
  dall'esclusiva **[DECISIONE tua]**.

## 9. Prima di pubblicare

- [ ] **Documentazione online alla 1.2.0.** `https://www.rolewarden.com/docs/installation.html`
  il 2026-11-08 non nomina ancora `/rolewarden/setup`: la scheda dice che è la stessa del
  pacchetto.
- [ ] **Demo online:** controllare che giri la 1.2.0 e che le credenziali della scheda valgano
  ancora.
- [ ] **Correzione CSS del wizard (`d2301b5`)** non è nello zip 1.2.0: il diff della conferma
  esce dalla colonna. Conviene una 1.2.1 prima di caricare (vedi `README.md`).
- [ ] Misure delle immagini, campi e licenze nel form di Codester.
