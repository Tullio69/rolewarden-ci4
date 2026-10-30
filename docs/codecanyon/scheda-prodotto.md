# Scheda prodotto CodeCanyon - RoleWarden 1.1.0

Note per Fabio (italiano). Tutto ciò che segue nei blocchi HTML è il testo da incollare su
CodeCanyon, in inglese, nel sottoinsieme di tag che la piattaforma accetta (p, h2, h3, ul,
li, strong, a). Ogni affermazione è coperta da `inventario.md`. I link demo e documentazione
sono placeholder tra parentesi quadre: sostituiscili con quelli definitivi. Le parti
marcate [DA VERIFICARE] vanno chiuse prima dell'invio (tutte chiuse il 2026-10-30).

Ripassata sulla 1.1 il 2026-10-27 (commit `7e3730a`): dashboard, barra laterale, verifica
permesso, ricerca comandi, movimento, blocchi della dashboard. Le nuove voci sono verificate
nel codice (rotte in `src/Config/RouteRegistrar.php`, `src/Dashboard/Block.php`,
`Resolver::explain`, migrazione `AddDashboardPermissions`) e coincidono con il CHANGELOG.

---

## 1. Nome item

[PROPOSTA DA APPROVARE, 2026-10-28] Dall'analisi dei concorrenti (`concorrenti.md`): gli
item più venduti vincono la ricerca "codeigniter user management" perché hanno "User
Management" e "Admin Panel" nel titolo, e nessuno nomina Shield. Proposta (78 caratteri):

```
RoleWarden - CodeIgniter 4 Shield Roles, Permissions & User Management Panel
```

Alternative dalla ricerca:

```
RoleWarden - CodeIgniter 4 Roles & Permissions Admin Panel for Shield
RoleWarden - CI4 Shield Admin Panel: Roles, Permissions, User Management & Activity Log
```

Scelta precedente (58 caratteri):

```
RoleWarden - Roles & Permissions Panel for CodeIgniter 4
```

Varianti:

```
RoleWarden - Database Roles & Permissions for CI4 Shield
RoleWarden - RBAC Admin Panel for CodeIgniter 4 (Shield)
RoleWarden - Roles, Permissions & User Admin for CodeIgniter 4
```

## 2. Tagline

```
Move roles and permissions out of AuthGroups.php and into the database, with an admin panel built on Shield.
```

## 3. Descrizione completa

```html
<h2>What RoleWarden does</h2>
<p>CodeIgniter Shield handles identity: passwords, sessions, remember-me, login attempts. Its roles and permissions, though, live in <strong>app/Config/AuthGroups.php</strong>, so every change to who can do what is a code change and a deploy. RoleWarden moves roles, permissions, role inheritance and per-user overrides into the database and adds an admin panel to manage them at runtime, without replacing Shield or touching its tables.</p>
<p>Your code keeps checking permissions the Shield way: <strong>auth()->user()->can('invoices.approve')</strong>, the <strong>can:</strong> route filter, a <strong>can()</strong> helper in views. The answer now comes from the database, cached per user, and an administrator changes it from a browser.</p>
<p>Built for developers and agencies who ship CodeIgniter 4 applications to clients and need a permission layer that is in place on day one and that the client can manage without calling you.</p>

<h2>How it fits an existing application</h2>
<ul>
<li>Install the module with Composer (or use the complete application zip, with <strong>vendor/</strong> included, if there is no Composer on the server).</li>
<li>Point Shield at RoleWarden's user model: one line in <strong>app/Config/Auth.php</strong>.</li>
<li>Register the panel routes: one line in <strong>app/Config/Routes.php</strong>, next to Shield's own.</li>
<li>Run the migrations and the seed, or open the browser installer at <strong>/rolewarden/install</strong>.</li>
<li>If your app already uses Shield groups, a migration imports <strong>AuthGroups.php</strong> and the existing assignments into RoleWarden, and rolls back cleanly.</li>
</ul>
<p>No file inside the module is meant to be edited. Views are replaced through CodeIgniter's own <strong>app/Views/overrides/</strong> mechanism, settings live in your application, and an update is a folder replacement followed by <strong>php spark migrate -n RoleWarden</strong>.</p>

<h2>Features</h2>

<h3>Authorization engine</h3>
<ul>
<li>Permissions identified by slug (<strong>area.action</strong>), grouped by area; code checks permissions, never roles.</li>
<li>Role inheritance: a role receives the permissions of its parent chain.</li>
<li>Per-user overrides: grant or deny a single permission to one user. A denial beats every role, including super admin.</li>
<li>Fixed resolution order: deactivated user, user denial, user grant, super admin, role permissions, otherwise denied.</li>
<li>Several roles per user; super admin as a flag on a role.</li>
<li>Per-user permission cache, invalidated on every change to the user, the role or its descendants.</li>
<li>Framework-independent resolver with unit tests; CodeIgniter reaches it through a service, a filter and helpers.</li>
</ul>

<h3>Shield integration</h3>
<ul>
<li>Drop-in user model and entity: <strong>$user->can()</strong>, <strong>hasPermission()</strong>, <strong>inGroup()</strong> and <strong>getGroups()</strong> answer from the database.</li>
<li>Route filter <strong>can:perm.one,perm.two</strong> (any one of them passes), built on Shield's own filter base and its denied-redirect setting.</li>
<li>Helpers <strong>can()</strong>, <strong>can_any()</strong>, <strong>can_all()</strong>, <strong>permissions()</strong>.</li>
<li>Reversible import of existing Shield groups, permissions and assignments.</li>
<li>Restyled login screen; registration and password recovery stay Shield's own.</li>
<li>Hooks on Shield's <strong>register</strong>, <strong>login</strong>, <strong>failedLogin</strong> and <strong>logout</strong> events, wired automatically.</li>
</ul>

<h3>Dashboard</h3>
<ul>
<li>Key figures for 7, 30 or 90 days: active users, sign-ins, failed sign-ins and permission changes, each with its change on the previous period.</li>
<li>Sign-ins over time and by weekday and hour, users per role, the latest permission changes, and a security notice when an address reaches the lock threshold or an email is locked.</li>
<li>Every block has its own permission; whoever holds none lands on the first screen they can see.</li>
<li>Each user shows, hides and reorders their blocks; an administrator saves a default for everyone else.</li>
<li>Charts are SVG drawn on the server in the theme's colours, each with its numbers as a table.</li>
<li>Blocks of your own: one class extending <strong>RoleWarden\Dashboard\Block</strong>, listed in the config, stable for every 1.x release.</li>
</ul>

<h3>Admin panel</h3>
<ul>
<li>Sidebar grouped into Access, Security and System, each item shown only to those who hold its permission, with counters and a Today panel of the day's sign-ins; collapsible to a rail of icons.</li>
<li>Command search with Ctrl K (Cmd K on macOS): screens, actions and permission identifiers, only those the signed-in user may reach, filtered on the server.</li>
<li>Check a permission: who holds it and why (role and its parents, override, super admin), and for one user the outcome with the rule that decides it.</li>
<li>Users: search, filter by role and status, sort, paginate; create, edit, activate, deactivate, delete; assign and revoke roles; grant or deny single permissions from the user's own matrix.</li>
<li>Roles: create, edit, delete (soft), parent role, super admin flag, and a permission matrix per role that marks what is not granted yet and says how to grant it.</li>
<li>Permissions: every permission grouped by area, with the roles that grant it.</li>
<li>Guard rails: no inheritance cycles, system roles and permissions cannot be deleted, a role with children cannot be deleted, the application can never be left without an active super admin, an admin cannot lock themselves out of role management.</li>
<li>Settings screen: default role for new users, session lifetime, remember-me, lock after failed sign-ins, sign-in attempts per IP, activity log retention. Stored with CodeIgniter's Settings library.</li>
<li>Toasts for the outcome of every action.</li>
<li>Motion that shows what changed: pages hand over with the sidebar's highlight sliding, dialogs grow out of their button, matrix cells and lists show their changes. All of it off under the system's reduced-motion setting.</li>
</ul>

<h3>Accounts, sessions and sign-in limits</h3>
<ul>
<li>Profile: password change with confirmation of the current one; changing it signs out every other session.</li>
<li>Sessions: every browser signed in to an account, with address, device and last activity; sign out one, all, or forget a remembered browser. Administrators with the right permission do the same for other users.</li>
<li>Sign-in throttling on top of Shield: configurable attempts per minute per IP address, and a lock per email after repeated failures, counted from Shield's own login records.</li>
<li>Session lifetime enforced before routing, so a revoked or idle session is signed out before any page runs.</li>
</ul>

<h3>Activity log and security emails</h3>
<ul>
<li>Activity log of every change made from the panel to users, roles, permissions and settings, plus sign-outs and every sign-in attempt read from Shield. Filter by user (including deleted users), action and period. Actor and subject names are stored in the row, so history stays readable after renames and deletions.</li>
<li>Retention of 90 days, one year or forever, pruned once a day on write; no cron job.</li>
<li>Event <strong>rolewarden.activity</strong> on every entry, for your own listeners.</li>
<li>Security emails: sign-in from a new device or address, password changed, account locked after too many failed sign-ins (also sent to holders of <strong>security.alerts</strong>). Sent after the response through CodeIgniter's Email service, in the recipient's language.</li>
<li>Event <strong>rolewarden.mail</strong> to take over delivery, for example to push messages onto your own queue.</li>
</ul>

<h3>Users import</h3>
<ul>
<li>CSV import with a row-by-row preview: email and username required, role and password optional; comma or semicolon; up to 1000 users per file.</li>
<li>The file is refused as a whole if any row is invalid, and created in one transaction otherwise. Passwords are hashed by Shield at preview time and never stored in clear.</li>
<li>Users without a password receive one nobody knows and sign in through Shield's password recovery.</li>
</ul>

<h3>Themes and appearance</h3>
<ul>
<li>Three themes, Console (dense), Clarity (airy) and Contrast (high contrast), each in light and dark; every user picks light, dark or system from the top bar.</li>
<li>Three sidebar styles: Paper (light), Ink and Charcoal (dark in both modes).</li>
<li>Appearance screen with live preview: starting theme, background, text and accent colours for light and dark, corner radius, density, with a WCAG AA contrast check on every text colour.</li>
<li>The result is a theme file in your application's <strong>writable/</strong> folder, downloadable and versionable; a theme file under <strong>app/Config/</strong> wins and locks the screen.</li>
<li>Add a fourth theme or your own components through a stylesheet of your application; a design guide documents every token with its value in each theme.</li>
<li>Hand-written HTML, Tailwind CSS compiled and shipped, Alpine.js for interaction. No Node, no npm, no build step on your side.</li>
</ul>

<h3>Languages</h3>
<ul>
<li>English and Italian, chosen by each user from the top bar or the profile.</li>
<li>Another language is one PHP file in your application; missing lines fall back to English.</li>
<li>Seeded role and permission names shown in the reader's language until an administrator edits them.</li>
</ul>

<h3>Installation</h3>
<ul>
<li>Browser installer: checks PHP version, extensions and writable folders, asks for a setup code that only someone with file access to the server can read, tests the database connection, writes <strong>.env</strong> with a fresh encryption key, runs the migrations of Settings, Shield and RoleWarden in separate batches, creates the first super admin and locks itself.</li>
<li>Or the command line: three <strong>php spark migrate</strong> calls and one seed, documented so that the module can be rolled back on its own without touching Shield.</li>
</ul>

<h2>Requirements</h2>
<ul>
<li>PHP 8.3 or later, with the <strong>intl</strong>, <strong>mbstring</strong>, <strong>json</strong> and <strong>mysqli</strong> extensions.</li>
<li>CodeIgniter 4.7.4 or later (4.7 introduced the <strong>app/Views/overrides/</strong> folder the panel relies on).</li>
<li>CodeIgniter Shield 1.4 or later within 1.x.</li>
<li>MySQL or MariaDB with InnoDB (tested on MariaDB 11.8).</li>
<li>For the panel, a current browser: Chrome or Edge 111+, Firefox 128+, Safari 16.4+.</li>
<li>Composer, unless you use the complete application zip.</li>
</ul>

<h2>What is included</h2>
<ul>
<li><strong>The module</strong> (rolewarden-module): source, 16 migrations, seeder, config, views, English and Italian language files, compiled CSS and JS, IBM Plex fonts.</li>
<li><strong>The complete application</strong> (rolewarden-app): CodeIgniter 4, Shield, CodeIgniter's translations and the module, already wired, <strong>vendor/</strong> included, opening on the browser installer. For servers without Composer.</li>
<li><strong>The demo application</strong> (rolewarden-demo): a small document register with three roles (Reader, Author, Approver) that shows permissions at work and is the pattern to copy for your own screens. Installed and removed with <strong>php spark demo:install</strong> / <strong>demo:uninstall</strong>.</li>
<li><strong>HTML documentation</strong>: installation, configuration, Shield integration, API reference, view overrides, design guide, troubleshooting.</li>
<li>CHANGELOG and a third-party licence file.</li>
</ul>
<p>Third-party code is open source: CodeIgniter, Shield, Settings and Translations (MIT), Alpine.js (MIT), IBM Plex (SIL OFL 1.1), Tailwind CSS (MIT, shipped only as compiled CSS).</p>

<h2>Demo and documentation</h2>
<ul>
<li>Live demo: <a href="[DEMO_URL]">[DEMO_URL]</a>. Data is reset every hour and no email leaves the demo. Sign in as <strong>[DEMO_ADMIN_EMAIL]</strong> / <strong>[DEMO_ADMIN_PASSWORD]</strong> for the full panel, or as the Reader, Author and Approver accounts of the demo app (credentials in the listing below the screenshots).</li>
<li>Documentation: <a href="[DOCS_URL]">[DOCS_URL]</a>, the same HTML you find in the package.</li>
</ul>

<h2>Support and updates</h2>
<ul>
<li>Updates are migrations: replace the module folder, run <strong>php spark migrate -n RoleWarden</strong>, and new permissions reach the admin role by themselves. The changelog lists every change that matters to an installed site.</li>
<li>Support covers installation, configuration and bugs in the module. It does not cover custom development or debugging of your application's own code.</li>
<li>Registration, email verification and password recovery are Shield's own screens and follow Shield's documentation.</li>
</ul>

<h2>Roadmap</h2>
<p>Planned for later versions, not in 1.1: two-factor authentication, social sign-in, API tokens managed from the panel, a notification centre, user invitations by email, user impersonation for support. No dates are promised; buyers of 1.x receive the 1.x updates.</p>
```

Nota: la sezione Roadmap è coerente con la decisione del 24/10 (impersonazione e starter
in v1.5, senza date). Se preferisci non citare funzioni future nella scheda, togli il blocco:
nulla di ciò che precede dipende da esso.

## 4. Lista feature per la colonna laterale (max 12)

```html
<ul>
<li>Roles and permissions in the database, managed from an admin panel</li>
<li>Built on CodeIgniter Shield, which keeps identity and sessions</li>
<li>Role inheritance and per-user grant/deny overrides</li>
<li>Drop-in: $user->can(), can: route filter, view helpers</li>
<li>Reversible import of existing Shield groups</li>
<li>Dashboard with per-permission blocks and blocks of your own</li>
<li>Command search and a Check a permission screen</li>
<li>Users: search, filters, CSV import with preview</li>
<li>Sessions list with revoke, sign-in throttling and email lock</li>
<li>Activity log with filters and a one-year default retention</li>
<li>Three themes, light and dark, live theme customiser</li>
<li>Browser installer and a complete app zip without Composer</li>
</ul>
```

## 5. FAQ

```html
<h3>Does it work with CodeIgniter Shield, or does it replace it?</h3>
<p>It extends Shield. Shield keeps users, credentials, sessions, remember-me, login records, registration and password recovery. RoleWarden replaces only the authorization part (AuthGroups.php) with database tables and a panel, through a user model that extends Shield's. Shield's own tables are left as they are.</p>

<h3>I already use Shield groups in production. How do I migrate?</h3>
<p>Run the module's migrations. One of them reads your app/Config/AuthGroups.php (groups, permissions, matrix) and the assignments in auth_groups_users and turns them into RoleWarden roles, permissions and assignments. A group whose slug matches an existing role (Shield's stock "admin" and "user" collide with two seeded roles) is skipped, never merged. Rolling the migration back removes exactly what it created.</p>

<h3>Can I use it in an application that has its own users table or another auth library?</h3>
<p>No. RoleWarden stores assignments against Shield's users table and relies on Shield's authentication. An application on Ion Auth, Myth:Auth or a home-made login has to move to Shield first.</p>

<h3>How do I add permissions for my own features?</h3>
<p>With a migration in your application that inserts rows into the permissions table (an example is in the API reference). The panel then lists them and lets you assign them to roles. The panel deliberately does not create permissions, because a permission only means something to the code that checks it.</p>

<h3>Can I customise the panel's look and its views?</h3>
<p>Yes, without editing the module. Colours, corner radius and density are set from the Appearance screen and saved in a theme file of your application; a fourth theme or extra components go in a stylesheet of yours. Any view is replaced by copying it to app/Views/overrides/RoleWarden/Views/, CodeIgniter's standard mechanism. Updates never overwrite your changes.</p>

<h3>Is it multi-tenant?</h3>
<p>No. Roles and permissions are global to the application in 1.x. Scoping by organisation is not supported.</p>

<h3>Which databases are supported?</h3>
<p>MySQL and MariaDB with InnoDB. Releases are tested on MariaDB 11.8. The installer requires the mysqli extension. Other engines are not tested and not supported.</p>

<h3>Does it need Node, npm or a build step?</h3>
<p>No. The CSS is compiled with Tailwind and shipped in the package, Alpine.js is bundled. You install PHP files and run migrations, nothing else.</p>

<h3>What about two-factor authentication, social login or API tokens?</h3>
<p>Not in 1.1. They are planned for later versions. Shield's own features in those areas keep working as documented by Shield, but RoleWarden does not add screens for them yet.</p>

<h3>Which licence do I need?</h3>
<p>A Regular Licence covers one end product whose end users are not charged (for example a client's back office). An Extended Licence covers one end product whose end users are charged, such as a paid SaaS. Each further end product needs its own licence. CodeCanyon's licence terms apply.</p>
```

Nota (verificata il 2026-10-30 sui testi ufficiali codecanyon.net/licenses/regular e
/licenses/extended): Regular = un solo prodotto finale i cui utenti non pagano; Extended = un
solo prodotto finale i cui utenti possono pagare. Ogni altro prodotto finale richiede una
licenza nuova. La risposta è riscritta su questa formulazione.

## 6. Changelog iniziale

La prima versione su CodeCanyon è la 1.1.0: chi compra non ha mai avuto la 1.0.0, quindi il
changelog della scheda parte da qui. `[RELEASE_DATE]` si sostituisce il giorno del rilascio, insieme alla sezione 1.1.0 del
CHANGELOG (passo "Prep rilascio 1.1.0"): stesso giorno in entrambi.

```html
<h3>1.1.0 - [RELEASE_DATE]</h3>
<p>First release on CodeCanyon.</p>
<ul>
<li>Roles and permissions in the database, with inheritance and per-user overrides; fixed resolution order with a deactivated user denied everything and a user denial beating super admin.</li>
<li>Shield integration: user model and entity, can: route filter, can(), can_any(), can_all() and permissions() helpers; import of existing Shield groups.</li>
<li>Admin panel: dashboard, users, roles, permission matrix, user overrides, check a permission, command search, settings, profile, sessions, activity log, appearance, CSV import.</li>
<li>Dashboard blocks with their own permissions, per-user layout and a public API for blocks of your own.</li>
<li>Sign-in throttling per IP and lock per email on top of Shield.</li>
<li>Security emails on new device, password change and lockout; rolewarden.mail and rolewarden.activity events.</li>
<li>Three themes in light and dark, three sidebar styles, theme customiser with contrast check, design guide.</li>
<li>English and Italian.</li>
<li>Browser installer, complete application zip, demo application, HTML documentation.</li>
</ul>
```

## 7. Metadati

**Categoria CodeCanyon proposta:** PHP Scripts > Miscellaneous (non esiste una
sottocategoria CodeIgniter; "Miscellaneous" è dove stanno gli item RBAC/user management per
framework. Alternativa: PHP Scripts > Loaders and Uploaders no; evitare "Social Networking").
Verificato il 2026-10-30: PHP Scripts > Miscellaneous esiste
(codecanyon.net/category/php-scripts/miscellaneous) e ospita già item di gestione utenti per
framework. Non usare Membership (abbonamenti a pagamento) né Social Networking. Se al momento
dell'upload il form offre una sottocategoria di autorizzazione o gestione utenti, quella ha la
precedenza.

**Tag (18):**
```
codeigniter, codeigniter 4, ci4, shield, rbac, roles, permissions, access control, authorization, admin panel, user management, acl, activity log, session management, csv import, tailwind, alpinejs, php 8
```

[PROPOSTA DA APPROVARE, 2026-10-28] Tag ricorrenti nei concorrenti più venduti che mancano
qui: `user roles and permissions`, `authentication`, `login`, `secure`. Se il form limita il
numero di tag, togliere per primi `tailwind`, `alpinejs`, `csv import` (nessun acquirente
cerca così) e tenere `shield`, che nessun concorrente usa. Il limite del form va visto in
fase di upload.

**Versioni da dichiarare nel form:**
- Software version / PHP: 8.3, 8.4, 8.5. Collaudate sulla 1.0 (S2) e sulla 1.1 (R1 PASS il
  2026-10-28, `tests/Integration/R1-REPORT.md`)
- Compatible with: CodeIgniter 4.7.4+ (scrivilo nel testo, il form non ha un campo
  dedicato per CI4)
- Database: MySQL o MariaDB con InnoDB, come dice la documentazione. Tutti i collaudi
  (M1, M6, matrice MVP, V1, R1) girano su MariaDB 11.8.9 in Docker. MySQL 8 e MariaDB 10.x
  non sono mai stati provati: nel testo si dichiara solo "tested on MariaDB 11.8".
- Compatible browsers: Chrome, Firefox, Safari, Edge. Non spuntare IE né Opera legacy. Le
  versioni minime sono quelle di Tailwind v4 (tailwindcss.com/docs/compatibility, verificato
  il 2026-10-30): Chrome e Edge 111+, Firefox 128+, Safari 16.4+. Si dichiarano così nel testo.
- Files included: PHP, HTML, CSS, JS

**Prezzo suggerito:** Regular $29 (poi $35), Extended $99, come deciso il 18/10, confermato
dall'analisi dei concorrenti del 28/10 (`concorrenti.md`): gli item CI4 regular stanno tra
$16 e $39, WebGuard (applicazione completa con SSO e 2FA) è a $39/$149; rialzo a $35 dopo
circa 20 recensioni sopra 4.5.
Motivazione: l'unico item CI4 analogo in vetrina (Ion Auth + Argon, $16/$65) vende prezzo
d'impulso con recensioni sui bug e nessuna doc; RoleWarden si distingue per permessi
granulari, base Shield ufficiale, modulo drop-in e documentazione, quindi non deve inseguirlo
sul prezzo. $29 resta sotto la soglia psicologica dei pannelli admin generici ($39-59) e
lascia spazio al rialzo a $35 dopo le prime recensioni.
