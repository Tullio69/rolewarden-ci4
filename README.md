# RoleWarden

Roles & Permissions Panel for CodeIgniter 4, built on top of [Shield](https://github.com/codeigniter4/shield).

Shield owns identity and authentication. RoleWarden moves roles and permissions out of the
config file and into the database, and adds an admin panel to manage them at runtime.

## Requirements

- PHP 8.3 or later (tested up to 8.5)
- CodeIgniter 4.7.4 or later (tested up to the latest 4.x). 4.7 is the first release with the
  `app/Views/overrides/` folder the panel relies on for view overrides, and 4.7.4 fixes known
  security advisories in the framework itself that earlier 4.7 releases carry
- CodeIgniter Shield 1.4 or later within 1.x (`^1.4`)
- MySQL or MariaDB with InnoDB

## Status

Pre-release. Currently building the MVP; see `_AI-LOG.md` for the current state of the work.

## Project layout

```
src/
  Authorization/   Framework-agnostic authorization layer (resolver, hierarchy, contracts)
  Config/          Module configuration and route/service registration
  Controllers/     Admin panel controllers
  Database/        Migrations and seeds
  Entities/        User entity extending Shield's
  Filters/         Route filter for permission checks
  Helpers/         View helpers
  Language/        Translatable strings
  Models/          Data access
  Views/           Admin panel views (overridable by the host application)
  Assets/          Compiled CSS and JS for the panel
tests/
  Unit/            Authorization logic
  Integration/     Framework and Shield integration
```

The CodeIgniter test application lives in a sibling directory,
`../rolewarden-app-test`, generated locally with `composer create-project
codeigniter4/appstarter` and wired to this package through a Composer path
repository. It is kept outside this package so the path symlink in its
`vendor/` does not point back at its own parent.

## Local development

The database runs in Docker; PHP and Composer run natively on the host.

```
docker compose up -d
```

This starts MariaDB 11 on port 3306 with two databases, `rolewarden` for the test
application and `rolewarden_test` for the suite, plus Adminer on
<http://localhost:8080> (server `db`, user `root`, password `rolewarden`).

If port 3306 or 8080 is already taken, override them without editing the file:

```
DB_PORT=3307 ADMINER_PORT=8081 docker compose up -d
```

Then open Adminer on the port you chose. If another project's Adminer already sits on
8080, that page still loads, but its `db` is a different server: "Access denied for user
'root'" there means you are on the wrong Adminer, not using the wrong password.

`docker compose down -v` deletes the data volume and recreates both databases from
scratch on the next start. Use it when migrations leave the schema in a state that is
faster to rebuild than to repair.

Credentials here are deliberately trivial: this stack is local only and never exposed.

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
screen gives the `admin` role the new permissions `settings.view` and `settings.update`, and the one for sessions gives it `sessions.view` and `sessions.revoke`.

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
'login' => 'rolewarden/users',
```

Shield still sends a user back to the page they were trying to open, when there was one.
A user without `users.view` is turned away by the panel's permission check, as on any
panel URL, so set this only if most people who sign in are administrators.

Registration and password recovery stay exactly Shield's own screens (this module does
not touch identity or credentials); only the login screen has a matching design in
`docs/design-system/` so far.

### Settings and the default role

`/rolewarden/settings` shows module options that change at runtime. It needs
`settings.view` to open and `settings.update` to save. The values live in CodeIgniter's
Settings library, in the `settings` table Shield already uses. In this version the screen
holds one option: the **default role for new users**. That role goes to everyone who
registers through Shield and to everyone created from the panel. A super admin role cannot
be the default. The starting value is `RoleWarden\Config\RoleWarden::$defaultRole` (`null`, no
role), which the host app can change by extending that config class.

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

## Conventions

- Table prefix `acl_`, configurable, deliberately distinct from Shield's `auth_`
- Code checks permissions, never roles: `can('users.delete')`, never `hasRole('admin')`
- Permission slugs follow `area.action`
- No file inside this package is meant to be edited by the buyer; every customization
  belongs in the host application

## License

Proprietary. Sold on CodeCanyon.
