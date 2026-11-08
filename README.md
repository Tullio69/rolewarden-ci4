# RoleWarden

Roles & Permissions Panel for CodeIgniter 4, built on top of [Shield](https://github.com/codeigniter4/shield).

The full documentation is in `documentation/index.html`: open it in a browser. This file is
the short version. Changes between versions are in `CHANGELOG.md`, licences of third-party
code in `THIRD-PARTY-LICENSES.md`.

Shield owns identity and authentication. RoleWarden moves roles and permissions out of the
config file and into the database, and adds an admin panel to manage them at runtime.

## Requirements

- PHP 8.3 or later (tested up to 8.5)
- CodeIgniter 4.7.4 or later (tested up to the latest 4.x). 4.7 is the first release with the
  `app/Views/overrides/` folder the panel relies on for view overrides, and 4.7.4 fixes known
  security advisories in the framework itself that earlier 4.7 releases carry
- CodeIgniter Shield 1.4 or later within 1.x (`^1.4`)
- MySQL or MariaDB with InnoDB

## Project layout

```
src/
  Authorization/   Framework-agnostic authorization layer (resolver, hierarchy, contracts)
  Config/          Module configuration and route/service registration
  Controllers/     Admin panel controllers
  Database/        Migrations and seeds
  Entities/        User entity extending Shield's
  Filters/         Route filters (permission checks, sign-in throttling, panel language)
  Helpers/         View helpers
  Install/         The browser installer
  Language/        Translatable strings (English, Italian)
  Models/          Data access
  Settings/        Runtime settings (sign-in rules, theme, per-user choices)
  Views/           Admin panel views (overridable by the host application)
  Assets/          Compiled CSS and JS for the panel
documentation/     The HTML documentation (open index.html) and the design guide
```

## Two ways to install

RoleWarden ships as two zips, plus a demo app.

- **rolewarden-app-x.y.z.zip, a complete application.** For whoever starts from nothing or
  has no Composer: CodeIgniter 4, Shield, CodeIgniter's translations and RoleWarden, with
  `vendor/` included and everything below already wired. Upload it, point the web server's
  document root at its `public/` folder, create an empty MySQL or MariaDB database, and open
  the site: the home page leads to the installer. RoleWarden lives in `modules/rolewarden`
  and is loaded from there (`app/Config/Autoload.php`), so an update replaces that folder and
  nothing else.
- **rolewarden-module-x.y.z.zip, the module alone.** For an existing CodeIgniter 4
  application: load it (Composer, or `modules/rolewarden` with an Autoload line) and open
  `/rolewarden/setup`, the [wizard](#installing-on-an-existing-application-wizard) that
  migrates, maps your groups and wires the files for you. Or wire it as described under
  [Wiring the admin panel](#wiring-the-admin-panel), then either open the installer or run
  the commands under [Installing and rolling back the migrations](#installing-and-rolling-back-the-migrations).
- **rolewarden-demo-x.y.z.zip, the demo app.** A small document register with three roles
  (Reader, Author, Approver) that shows permissions at work, and the model to copy for your
  own screens. The complete application already contains it in `modules/rolewarden-demo`,
  not installed: `php spark demo:install` adds it, `php spark demo:uninstall` removes it
  without leaving anything behind. Its own `README.md` explains the rest, including the
  showcase mode for a public demo.

### The browser installer

`/rolewarden/install` takes the application from an empty database to a first super admin:

1. It checks the server: PHP 8.3 or later, the `intl`, `mbstring`, `json` and `mysqli`
   extensions, a writable `writable/` folder, and whether `.env` can be written.
2. It asks for the **setup code** saved in `writable/rolewarden/install-code.txt`, created on
   the first visit. Only someone who can read the server's files can install: a stranger who
   finds a fresh upload first cannot make themselves super admin.
3. It asks for the application's address and the database settings, tests the connection,
   and writes them to `.env` with a new encryption key (an existing key is kept, and so is an
   existing `CI_ENVIRONMENT`; otherwise it is set to `production`). When `.env` cannot be
   written, it shows what to save there by hand. A failed connection says so without any
   detail from the database, which goes to `writable/logs/`.
4. It creates the tables of Settings, Shield and RoleWarden, each in a batch of its own as
   below, and the system roles.
5. It creates the first super admin through Shield, active, then writes
   `writable/rolewarden/installed.lock` and deletes the setup code.

From then on it refuses to run. It also refuses on an application that already has an
active super admin, lock file or not. It never sends anything outside the server.

Security emails need a sender: set `email.fromEmail` (and the SMTP settings) in `.env`, or
they are skipped with a line in the log.

### Installing on an existing application (wizard)

`/rolewarden/setup` adds RoleWarden to a CodeIgniter 4 application that already has Shield,
users and its own groups. Load the module first, either as a Composer package or by copying
it to `modules/rolewarden` and adding one line to `app/Config/Autoload.php`:

```php
public $psr4 = [
    // ...
    'RoleWarden' => ROOTPATH . 'modules/rolewarden/src',
];
```

Nothing else is needed before opening the wizard: its route is in the module's own
`src/Config/Routes.php`, which CodeIgniter's route discovery includes by itself (keep
`routes` discovery on in `app/Config/Modules.php`, the default).

Every step before the last only asks and shows previews: it writes nothing to the database,
to the host's files or to the settings. Only the last step, **Confirm and apply**, writes. The
answers are kept in `writable/rolewarden/install-manifest.json`, so a closed browser or an
expired session resumes at the first unanswered question (after the setup code again).

1. **Setup code.** The code saved in `writable/rolewarden/install-code.txt`, as for the
   browser installer: only someone who can read the server's files can go on. It is asked
   even when someone is already signed in to the application.
2. **Server requirements.** The same checks as the browser installer, except the `.env`
   one; a missing one blocks the wizard until it is fixed.
3. **Filters.** If `app/Config/Filters.php` applies a `session`, `group` or `permission`
   filter to every page, RoleWarden's stylesheets and this wizard need to be exceptions to
   it (`rolewarden/assets/*`, `rolewarden/theme.css`, `rolewarden/setup*`). The step shows
   the filter, its current exceptions and the change. The panel itself still requires
   signing in.
4. **Roles.** *Adapt* keeps the application's groups as RoleWarden roles, with their users
   and permissions, and adds only `super-admin`; you choose which of your roles manages the
   panel (it receives the panel's permissions). *Import* adds RoleWarden's `super-admin`,
   `admin` and `user` next to your groups. The wizard suggests Import when the application's
   `AuthGroups` has no groups or only Shield's stock ones (`superadmin`, `admin`,
   `developer`, `user`, `beta`), Adapt otherwise. With Import, a group named like one of
   RoleWarden's roles gets a step of its own: merge it into that role, import it under
   another name, or skip it (the step says which users and permissions are lost). Adapt is
   refused for a group named `super-admin`, or named like a system role already in the
   database (an earlier `spark migrate` creates `admin` and `user`): choose Import for it.
   Permissions given to single users in Shield (`auth_permissions_users`) are not imported:
   the step and the summary count them, and those users lose them unless you give them a
   role or a user override.
5. **Role for new sign-ups.** Prefilled from `AuthGroups::$defaultGroup`.
6. **Super admin.** An existing active user, or a new account checked with Shield's own
   username, email and password rules. The new password is never saved in clear: only
   Shield's hash is kept until the account is created, then removed.
7. **Who sees RoleWarden.** *All users* (RoleWarden's sign-in screen, profile and sessions
   for everyone) or *Only administrators* (the application keeps its own sign-in screen),
   recommended for an application already in use. See
   [Settings and the default role](#settings-and-the-default-role).

**Confirm and apply** shows every answer and the exact change to each file, then applies it
in this order: database tables (Settings, Shield and RoleWarden migrations), roles, super
admin, the filter exception, and last the wiring: `app/Config/Routes.php` gets the
`RouteRegistrar` line, and only once that line is there `app/Config/Auth.php` gets the user
provider (plus the login view and redirect with *All users*). Only Shield's own
`UserModel` is replaced: if `$userProvider` names a model of your application, the wizard
leaves it and asks you to make that model extend `\RoleWarden\Models\UserModel`. Nothing is
written into a config file that could break the application before the tables exist. Once
this step has started the answers are locked: the wizard can only continue or retry. The
super admin alone can still be chosen again until its own step is done.

Every host file it changes gets a syntax check before writing (content that does not parse
is never written), a backup of the original, `<file>.rolewarden-bak` (made at the first
write and never overwritten), a write through a temporary file renamed over the original,
`php -l` as a further check where the server can run it (a file it rejects is put back) and
an entry in `install-manifest.json` with the path, the content before and after, and the
date. The final page lists the changed files.

**If a file cannot be changed** (not writable, missing, or written in a shape the wizard does
not recognise), the step stops at that file and shows the exact text to paste and where.
Make the change by hand, then choose **I edited the files, continue**: the wizard checks the
file again and goes on. Steps already done are not repeated. When a step fails for any other
reason, the screen names the step and the details go to `writable/logs/`, never to the page;
fix the cause and apply again.

**If the wizard is stuck.** Before any apply step is done, deleting
`writable/rolewarden/install-manifest.json` starts the questions over. Once a step is done,
do not delete it: it records what was applied and the host files changed, and without it the
wizard asks every question again and could apply different answers on top of what is done. Fix the cause the screen names (the
details are in `writable/logs/`) and apply again, or finish by hand with
[Wiring the admin panel](#wiring-the-admin-panel), comparing each changed file with its
`.rolewarden-bak`.

At the end the setup code is deleted and the wizard answers 403. Add a link to `/rolewarden`
in your application's menu. If the user provider changed, sign out and in again.

**A host with a global `session` filter.** Until the wizard has added its exception, such an
application sends a visitor of `/rolewarden/setup` to its own login page. Sign in with any
existing account and open `/rolewarden/setup` again: the setup code is still required, and
the code page says so.

To wire the module by hand instead, follow [Wiring the admin panel](#wiring-the-admin-panel)
and [Installing and rolling back the migrations](#installing-and-rolling-back-the-migrations).

## Installing and rolling back the migrations

Migrations are grouped in batches, and `migrate:rollback` undoes whole batches: it does
not filter by namespace (`-n` is ignored for that purpose). `php spark migrate --all` on a
fresh database puts Shield, the framework's Settings and RoleWarden in the same batch, so
a rollback would remove Shield too. To keep the module removable on its own, install in
separate steps:

```
php spark migrate -n 'CodeIgniter\Settings'
php spark migrate -n 'CodeIgniter\Shield'
php spark migrate -n RoleWarden
php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'
```

On an application that already has Shield migrated, `php spark migrate -n RoleWarden`
creates a batch of its own. Check the batch numbers in the `migrations` table, then roll
back to the batch just before RoleWarden's:

```
php spark migrate:rollback -b <previous batch>
```

`-b 0` rolls back everything, Shield included.

### Updating the module

Replace the module's folder (or run `composer update`), then run
`php spark migrate -n RoleWarden`. New versions ship their changes to existing data as
migrations, never as edits you make by hand. For example, the migration for the Settings
screen gives the `admin` role the new permissions `settings.view` and `settings.update`, the one for sessions gives it `sessions.view` and `sessions.revoke`, the one for
the activity log gives it `activity.view`, and the one for security emails gives it
`security.alerts`, the one for the theme customiser gives it `appearance.update`, and the one for the CSV import
gives it `users.import`.

### Importing existing Shield groups

`php spark migrate -n RoleWarden` also runs a migration that reads the host's
`app/Config/AuthGroups.php` (its `$groups`, `$permissions` and `$matrix`) and the
assignments already in `auth_groups_users`, turning them into roles, permissions and
assignments here. It is the entry path for an app that already used Shield's own
groups before adding this module.

A group whose slug already names an existing role — most commonly `admin` or `user`,
Shield's own stock group names, which collide with two of the seeded system roles — is
skipped rather than merged into it: the import never changes what an existing role can
do. Skipped groups do not have their assignments imported either. Rolling back removes
exactly what the import created, by slug, and never touches a role or permission it
did not create.

Every skipped group is reported on the command line, with the users and permissions that
are not carried over, and the run ends with a summary. The seeder is silent during
migrations. An installer that wants to run the import itself sets the setting
`RoleWarden.importDeferred` before migrating: the migration then leaves the groups alone.

This migration runs the system seed itself before importing, because it and
`RoleWardenSeeder` are two separate commands in the install steps above and the import's
own collision check needs the system roles to already exist. Running
`php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'` afterward, as documented,
stays a safe no-op either way.

### Creating the first super admin

The seeder creates the roles but assigns them to no one, and the panel needs someone
who can already manage roles. Create the account with Shield and activate it (Shield
creates it inactive):

```
php spark shield:user create -n admin -e you@example.com
php spark shield:user activate -e you@example.com
```

Then give it the `super-admin` role, once, from any SQL client:

```sql
INSERT INTO acl_user_roles (user_id, role_id)
SELECT i.user_id, r.id
FROM auth_identities i
JOIN acl_roles r ON r.slug = 'super-admin'
WHERE i.type = 'email_password' AND i.secret = 'you@example.com';
```

Replace `acl_` if you changed `rolewarden.tablePrefix`. From then on, every other role
and assignment is managed from the panel.

## Wiring the admin panel

Point Shield at RoleWarden's user model in the host's `app/Config/Auth.php`, so that
`auth()->user()->can()` goes through RoleWarden's resolver instead of Shield's config groups:

```php
public string $userProvider = \RoleWarden\Models\UserModel::class;
```

Then add one line to the host's `app/Config/Routes.php`, next to the existing
`service('auth')->routes($routes);` line Shield already needs:

```php
\RoleWarden\Config\RouteRegistrar::register($routes);
```

This registers `/rolewarden/users`, `/rolewarden/roles`, `/rolewarden/permissions` and
their actions, gated by the `session` and `can:<permission>` filters, plus a route that
serves the panel's CSS/JS straight from the package (no publish step, no copy under
`public/`). The file is deliberately not named `Config/Routes.php`: CI4 auto-includes any
file at that path in every registered namespace, which would run it a second time and
crash on the re-declared class.

Every panel view is resolved through CI4's own override mechanism: to replace
`src/Views/roles/show.php`, copy it to
`app/Views/overrides/RoleWarden/Views/roles/show.php` in the host application. No file in
this package needs editing.

The sign-in screen is Shield's own login route, restyled: point Shield's own view setting
at ours, one line in the host's `app/Config/Auth.php`, next to the `$views` array Shield
already scaffolds there:

```php
'login' => '\RoleWarden\Views\auth\login',
```

To land on the panel after signing in, change the `login` entry of the `$redirects` array
Shield scaffolds in the same file (Shield's default is `/`):

```php
'login' => 'rolewarden',
```

Shield still sends a user back to the page they were trying to open, when there was one.
`/rolewarden` is the dashboard; a user who holds none of its `dashboard.*` permissions is
sent on to the first panel screen they can see, or to their profile.

Registration and password recovery stay exactly Shield's own screens (this module does
not touch identity or credentials); only the login screen is restyled.

### Settings and the default role

`/rolewarden/settings` shows module options that change at runtime. It needs
`settings.view` to open and `settings.update` to save. The values live in CodeIgniter's
Settings library, in the `settings` table Shield already uses. Its **Roles** section
holds the **default role for new users**. That role goes to everyone who
registers through Shield and to everyone created from the panel. A super admin role cannot
be the default. The starting value is `RoleWarden\Config\RoleWarden::$defaultRole` (`null`, no
role), which the host app can change by extending that config class. While the setting has
never been saved and that property is `null`, the default is the host's
`AuthGroups::$defaultGroup` if it names a RoleWarden role. Saving the Settings screen stores
the choice (`''` for "no role"), and from then on that fallback no longer applies.

The **panel role** is a Settings-library key, `RoleWarden.panelRole`, set outside the
Settings screen (for example `setting('RoleWarden.panelRole', 'helpdesk-admin')`). It is the
role that receives the module's new permissions when migrations and the seeder add them.
Unset means `admin`; an empty string means no role receives them, so only the super admin
sees them.
**Who sees RoleWarden** (`RoleWarden.visibility`, `all` or `admins`) limits the account
screens (profile and sessions) to people with panel access; unset means `all`. With `admins`,
the others are sent from `/rolewarden` to Shield's `permission_denied` redirect, and their
new sign-in email carries no sessions link.

Shield's `addGroup()`, `removeGroup()` and `syncGroups()` on the user entity, and
`UserModel::addToDefaultGroup()`, act on RoleWarden roles, not on `auth_groups_users`. Each
call is atomic and keeps the protections (last super admin, system roles). In `addGroup()` and
`syncGroups()` an unknown slug throws Shield's `CodeIgniter\Shield\Authorization\AuthorizationException`
before anything is written; `removeGroup()` ignores it, as Shield does. These methods accept
any RoleWarden role, `super-admin` included, and do not check who is calling: the host must
decide who can reach the code that calls them.
With `$userProvider` set to RoleWarden's `UserModel` (or a subclass of it),
`model(CodeIgniter\Shield\Models\UserModel::class)` returns that model too, so a host that
loads users through Shield's model gets RoleWarden's entity with no change. This covers
`model()` and `Factories` only: a direct `new CodeIgniter\Shield\Models\UserModel()` still
returns Shield's entity.

If the module is loaded but its tables are missing, the host keeps working with no roles and
no permissions, group writes throw an error saying the module is not installed, and
`/rolewarden` shows a "not installed yet" page (503) linking to the installer.

The registration hook lives in the module's `Config/Events.php`. CodeIgniter includes it by
itself, as long as the host's `app/Config/Modules.php` keeps module discovery on for
`events`, which is the default.

### Sign-in rules, profile and sessions

The Settings screen also holds the **Sign-in** rules: session lifetime (how long a
signed-in user may stay idle), "Remember me" (Shield's own `Auth.sessionConfig`, stored
through the same Settings library, so Shield reads it with no change), the lock after
failed sign-ins with its duration, and the sign-in attempts accepted per minute from one IP
address. Their starting values are in `RoleWarden\Config\RoleWarden`.

- The lock counts the failures Shield records in `auth_logins`. Keep
  `Config\Auth::$recordLoginAttempt` at its default (record everything) or at least at
  failures; with `RECORD_LOGIN_ATTEMPT_NONE` there is nothing to count.
- The limits run in the `rw-signin` filter, which the module registers by itself on the
  `login` route (a host that moves Shield's login route puts `rw-signin` on its own path in
  `app/Config/Filters.php`). Shield's `auth-rates` filter, if the host uses it, still caps
  every IP at 10 requests a minute; remove it to let the setting go higher.

Every signed-in user has a **profile** at `/rolewarden/profile`, where they change their
password by confirming the current one, and a **sessions** screen at `/rolewarden/sessions`
listing the browsers signed in to their account. Signing a session out, or forgetting a
remembered browser, takes effect at that browser's next request. Changing the password signs
out every other session. On the user detail screen, `sessions.view` shows another user's
sessions and `sessions.revoke` signs them out.

Sessions are tracked in the `acl_sessions` table by a `pre_system` hook in the module's
`Config/Events.php`, so the host has nothing to wire.

### Security emails

The module emails a user when their account signs in from a device or an address it has
never used before (not on the very first sign-in), when their password changes (from the
profile or set by an administrator), and when their email reaches the lock after too many
failed sign-ins. That last alert also goes to everyone holding `security.alerts` (granted to
`admin`), and is sent once per lock.

- Emails use CodeIgniter's Email service with the host's `app/Config/Email.php`, which must
  have a `fromEmail`; without one they are skipped and a warning is logged.
- They are sent after the response has gone out, from a shutdown function, so no page waits
  for the mail server.
- Before each email the event `rolewarden.mail` fires with the message (`to`, `subject`,
  `view`, `data`). A listener that returns `false` takes the message over, for example to put
  it on the host's own queue, and the module does not send it.
- The templates are views in `RoleWarden\Views\emails`; override one by copying it to
  `app/Views/overrides/RoleWarden/Views/emails/`.

### Importing users from CSV

`/rolewarden/users/import` (permission `users.import`, also linked from the users list)
creates many users at once. The first row names the columns, in any order: `email` and
`username` are required, `role` (a role slug; empty gives the default role from Settings) and
`password` are optional. UTF-8, comma or semicolon, at most 1000 users and 1 MB per file, of
which at most 50 with a password (each costs Shield a hash and a dictionary check); a sample
file can be downloaded from the page. A preview stays valid for 30 minutes.

Uploading shows a preview, row by row. If any row is wrong (an invalid or taken email or
username, a name repeated in the file, an unknown or super admin role, a weak password), the
whole file is refused with the error on its row and nothing is created. A valid file is
created on confirmation, in one transaction: every user or none. Imported users are active.
A user imported without a password gets a random one nobody knows and signs in through
Shield's password recovery (magic link), or an administrator sets one. Passwords given in the
file are hashed by Shield as soon as the preview is made. Every imported user appears in the
activity log as "User imported".

### Themes and appearance

The panel ships three themes, **Console** (dense, the default), **Clarity** (airy) and
**Contrast** (high contrast), each in light and dark. Everyone who is signed in picks light,
dark or "System" (follow the operating system) from the top bar; the choice is kept per user.

`/rolewarden/appearance` (permission `appearance.update`) sets the theme for everyone: the
starting theme, background, text and accent colours for light and dark, corner radius and
density. Every change shows on the page as it is made, and any text colour under WCAG AA is
flagged (you can still save).

- The result is a theme file of your application, never a module file, so updating the
  module keeps it: `writable/rolewarden/theme.json`, written by the panel. The folder must be
  writable by the web server, like the rest of `writable/`.
- To keep the theme under version control, download it from the Appearance screen and save
  it as `app/Config/RoleWarden/theme.json`. When that file exists it wins, and the panel
  shows the theme without letting anyone change it.
- A fourth theme or a component of your own goes in a stylesheet of your application,
  declared with `$themeStylesheet` and `$extraThemes` in a class extending
  `RoleWarden\Config\RoleWarden`. `documentation/design-guide.html` lists every token with
  its value in each theme and shows how; open it in a browser from the module folder.

### Activity log

`/rolewarden/activity` (permission `activity.view`) lists every change made from the panel to
users, roles, permissions and settings, sessions signed out, sign-outs, and every sign-in
attempt, newest first, with who did it, on what, when and from which address. It filters by
user (name or email, also finding users since deleted), action and period.

- Changes are written to `acl_activity_log`, with the author's and the object's names copied
  in, so the history stays readable after they are renamed or deleted.
- Sign-in attempts are not copied: they are read from Shield's `auth_logins` and merged into
  the same list.
- The Settings screen sets how long entries are kept: 90 days, 1 year (the default,
  `RoleWarden\Config\RoleWarden::$activityRetentionDays`) or forever. Older entries are
  deleted once a day, on the first write of the day; no cron job is needed. `auth_logins` is
  Shield's and is left alone.
- Every entry fires the CodeIgniter event `rolewarden.activity` with the row as its argument,
  so the host app can react to it:

```php
Events::on('rolewarden.activity', static function (array $entry): void {
    // $entry['action'] is e.g. 'role.updated'; $entry['details'] is JSON.
});
```

### Languages

The panel and the security emails come in English and Italian. Each user picks the language
from the top bar of every page or from their profile (`/rolewarden/profile`); until they do, the panel follows the application's
own locale (`Config\App::$defaultLocale`, or the negotiated one). A security email goes out in
the language of the person receiving it, not of whoever triggered it.

- Another language is one file in your application: copy the module's
  `src/Language/en/RoleWarden.php` to `app/Language/{locale}/RoleWarden.php` (for example
  `app/Language/fr/RoleWarden.php`) and translate the values. It shows up in the top bar and the profile's
  list at once, named in its own language. Lines you leave out fall back to English.
- To change a few lines of a shipped language, put just those lines in
  `app/Language/{locale}/RoleWarden.php`: CodeIgniter merges them over the module's.
- CodeIgniter's own messages (validation errors, "5 minutes ago") come from the official
  `codeigniter4/translations` package: `composer require codeigniter4/translations`. The full
  application package already includes it. Shield ships its own translations.

## Conventions

- Table prefix `acl_`, configurable, deliberately distinct from Shield's `auth_`
- Code checks permissions, never roles: `can('users.delete')`, never `hasRole('admin')`
- Permission slugs follow `area.action`
- No file inside this package is meant to be edited by the buyer; every customisation
  belongs in the host application

## License

Proprietary. Sold on CodeCanyon.
