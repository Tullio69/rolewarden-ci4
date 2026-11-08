# Changelog

Every change that matters to whoever installs or updates RoleWarden. Versions follow
[semantic versioning](https://semver.org): a minor version adds without breaking, a major
version may require changes on your side, listed under "Upgrading". After any update, run
`php spark migrate -n RoleWarden`.

## [Unreleased]

## [1.2.0] - 2026-11-08

### Added
- **Install wizard for an existing application**, `/rolewarden/setup`. For a CodeIgniter 4
  app that already has Shield, users and groups: load the module (Composer, or
  `modules/rolewarden` with an Autoload line) and open the page; its route comes from the
  module's own `Config/Routes.php`, so nothing has to be wired first. Behind the setup code
  it asks about the server, the global filters, the roles (adapt your groups, or import
  RoleWarden's next to them, with merge, rename or skip for same-named groups), the role for
  new sign-ups, the super admin (existing user or a new one, whose password is kept only as
  Shield's hash until the account exists) and who sees RoleWarden. Nothing is written until
  "Confirm and apply", which runs migrations, roles, super admin, the filter exception and
  the wiring (Routes.php first, Auth.php only after it), in that order. Each host file is
  parsed before it is written (content that does not parse is never written), gets a
  `.rolewarden-bak` backup of the original, is written through a temporary file renamed over
  it, and gets an entry in `writable/rolewarden/install-manifest.json`; a file it cannot
  change stops the step with the text to paste by hand. Only Shield's own `UserModel` is
  replaced as user provider: a model of the application's own is left to the owner, who
  makes it extend RoleWarden's. Permissions given to single users in Shield are not
  imported: the wizard counts them and warns. Once applying has started the answers are
  locked, except the super admin until its own step is done; a run that stops resumes where
  it stopped. When done, the setup code is deleted and the wizard answers 403.
- **`RoleWarden.seedDefaultRoles`**: set to `false`, the seeder creates only `super-admin`,
  not `admin` and `user` (used by the wizard's "adapt" mode).
- **Panel role**: the role that receives the module's new permissions is now the setting
  `RoleWarden.panelRole` (default `admin`). New permissions added by migrations and by the
  seeder go to it.
- **Shield group methods act on RoleWarden roles**: `addGroup()`, `removeGroup()` and
  `syncGroups()` on the user entity, and `UserModel::addToDefaultGroup()`, read and write
  RoleWarden roles instead of `auth_groups_users`. Each call is atomic and goes through the
  usual protections (last super admin, system roles). In `addGroup()` and `syncGroups()` an
  unknown slug throws Shield's `AuthorizationException` before anything is written;
  `removeGroup()` ignores it, as Shield does. With `Config\Auth::$userProvider` set to
  RoleWarden's `UserModel` (or a subclass), `model(CodeIgniter\Shield\Models\UserModel::class)`
  returns that model too, so they also work when the host loads users through Shield's model.
  A direct `new CodeIgniter\Shield\Models\UserModel()` is not covered.
- **Importing existing Shield groups** now reports every skipped group on the command line,
  with the users and permissions that are not carried over, and ends with a summary. The
  seeder no longer prints `Seeded:` during migrations. Setting `RoleWarden.importDeferred`
  lets an installer take over the import: the migration skips it and leaves the groups alone.
- **Who sees RoleWarden**, a setting (`RoleWarden.visibility`, `all` or `admins`) on the
  Settings screen. With `admins`, the account screens (profile and sessions) of
  people without panel access answer 404; `/rolewarden` sends them to Shield's
  `permission_denied` redirect, and their new sign-in email has no sessions link.
- **Module loaded, tables missing**: the host app keeps working (no roles, no permissions,
  nothing cached), group writes throw a clear error, and `/rolewarden` shows a "not installed
  yet" page (HTTP 503) with a link to the installer.

### Changed
- The default role for new users now falls back to the host's `AuthGroups::$defaultGroup`
  when the setting has never been saved and `RoleWarden\Config\RoleWarden::$defaultRole` is
  `null`. Saving the Settings screen stores the choice, `''` for "no role", and from then on
  the fallback no longer applies.

### Upgrading
With the new settings unset, an existing 1.1.0 install keeps panel role `admin` and
visibility `all`. What does change:

- **Default role.** An app whose `$defaultGroup` names an existing RoleWarden role and which
  has never saved the Settings screen now gives that role to new users. An install that has
  saved the Settings screen at least once already stores `''` and is not affected. To keep
  the old result, save the Settings screen with "no role".
- **Shield's user model.** With `$userProvider` set to RoleWarden's `UserModel`,
  `model(CodeIgniter\Shield\Models\UserModel::class)` returns RoleWarden's model, with its
  guards: users must be written by primary key, and the last super admin cannot be
  deactivated or deleted. `model('UserModel')` with the short name and
  `new CodeIgniter\Shield\Models\UserModel()` are not covered by the alias.
- **Group methods now write.** The host's `addGroup()` and `syncGroups()` calls, which had no
  effect before, now change RoleWarden roles. `syncGroups('admin')` assigns the system role
  `admin`, with every panel permission.
- **Any role, `super-admin` included.** The group methods accept every RoleWarden role,
  `super-admin` too. They do not check who is calling: the host decides who may reach the
  code that calls them.
- **`php spark shield:user addgroup`** writes RoleWarden roles, but Shield still validates the
  group name against `Config\AuthGroups`.

## [1.1.0] - 2026-11-02

### Added
- **Sidebar** in groups (Access, Security, System), each item shown only to those who hold
  its permission, with counters: users, roles, permissions, and the activity log's new
  entries since your last visit. A **Today** panel with the day's sign-ins and failures for
  those who can see the activity log, switched on and off from the Settings screen. Each
  user can collapse the sidebar to a rail of icons.
- **Three sidebar styles** in the Appearance screen: Paper (the light sidebar of 1.0), Ink
  and Charcoal (dark in light and dark mode alike), saved in the theme file.
- **Check a permission**, a read-only screen for those with `permissions.view`: who holds a
  permission and why (role and its parents, override, super admin), and for one user the
  outcome with the rule that decides it. Each permission in the Permissions screen links to it.
- `service('rolewarden')->explain($userId, $slug)`: the rule that decides a check and the
  role chains that hold the permission.
- **Command search**, from the sidebar or with Ctrl K (Cmd K on macOS): screens, actions and
  permission identifiers, only those the signed-in user may reach, filtered on the server.
  Actions open their screen and never act on their own; a permission identifier answers who
  holds it and opens Check a permission. Without JavaScript the sidebar link opens the same
  results as a page, `/rolewarden/search`.
- **Dashboard** at `/rolewarden`: key figures (active users, sign-ins, failed sign-ins,
  permission changes, each with its change on the previous period), sign-ins over time and
  by weekday and hour, a security notice when an address reaches the lock threshold or an
  email is locked, users per role and the latest permission changes, over 7, 30 or 90 days.
  Each block has its own permission: `dashboard.figures`, `dashboard.signins`,
  `dashboard.security`, `dashboard.roles`, `dashboard.changes`. Charts are SVG drawn on the
  server from the theme's colours, each with its numbers as a table. Whoever holds none of
  them is sent on to the first screen of the sidebar they can see.
- **Customise the dashboard**: each user shows, hides and reorders the blocks they can see
  and picks the period the page opens with; those with `settings.update` save a default for
  everyone who has not chosen. Kept with the Settings library, no new table. The security
  notice can be hidden but always comes first.
- **Dashboard blocks of your own**: a class extending `RoleWarden\Dashboard\Block`, listed in
  the new `$dashboardBlocks` of `Config\RoleWarden`, with its key, title, permission, view
  and data. Stable for every 1.x release; see the API reference. The demo app adds one,
  **Demo documents**, to copy from.

### Changed
- **Motion** across the panel, all of it off under the system's reduced-motion setting: pages hand
  over to each other with the sidebar's highlight sliding to the new item, dialogs grow out of
  their button, lists arrive row by row, the dashboard draws its figures and charts, and matrix
  cells, toasts and the customise panel show what changed. Browsers without View Transitions
  change page as before.
- The sidebar moved from `layouts/panel.php` to its own view, `partials/sidebar.php`.

### Fixed
- The counts at the top of the Users screen no longer include deleted users.
- Updating no longer gives `admin` back the stock permissions you revoked from it: the
  seeder grants an existing `admin` only the permissions it creates.

### Upgrading
- If you extend `RoleWarden\Authorization\Resolver` with a method of your own named
  `explain()`, rename it: the class now has one.
- Run `php spark migrate -n RoleWarden`. An updated installation keeps the Paper sidebar and
  starts with Today switched off; a new one starts with it on. The migration adds the five
  `dashboard.*` permissions, granted to `admin` and to every role not deleted that holds `activity.view`;
  rolling it back deletes them, with every grant and override of them.
- To land on the dashboard after signing in, set the `login` entry of `$redirects` in
  `app/Config/Auth.php` to `'rolewarden'` (it was `'rolewarden/users'`).
- If you copied `layouts/panel.php` into `app/Views/overrides/`, your copy keeps the 1.0
  sidebar and still works. To get the new one, replace its `<aside>` with
  `<?= view('RoleWarden\Views\partials\sidebar', ['active' => $active, 'collapsed' => $collapsed]) ?>`
  as in the module's layout, which also sets `$collapsed` and the `rw-shell--rail` class.

## [1.0.0] - 2026-10-18

The first version for sale.

### Added
- **Settings screen** (`/rolewarden/settings`): default role for new users, session lifetime,
  "Remember me", the lock after failed sign-ins and its duration, sign-in attempts per IP
  address, how long the activity log is kept. Permissions `settings.view` and
  `settings.update`.
- **Toasts** for the outcome of every action in the panel.
- **Profile** with password change, and **sessions**: every browser signed in to an account,
  signed out one by one or all at once, remembered browsers forgotten. Permissions
  `sessions.view` and `sessions.revoke` for other users' sessions.
- **Sign-in limits**: a lock per email after repeated failures and a rate per IP address,
  on top of Shield's own sign-in.
- **Activity log** (`/rolewarden/activity`): every change to users, roles, permissions and
  settings, and every sign-in attempt read from Shield, filtered by user, action and period.
  Permission `activity.view`; event `rolewarden.activity`.
- **Security emails**: sign-in from a new device or address, password changed, too many
  failed sign-ins (also to holders of `security.alerts`). Sent after the response; event
  `rolewarden.mail` to take them over.
- **Themes**: Console, Clarity and Contrast, light and dark, light or dark chosen by each
  user; the Appearance screen (`appearance.update`) with live preview, contrast check and a
  theme file kept in your application; a design guide of every token.
- **CSV import of users** (`/rolewarden/users/import`, `users.import`) with a row-by-row
  preview and nothing written unless every row is valid.
- **Italian** translation, the language chosen by each user from the top bar or the profile, security
  emails in the recipient's language.
- **Browser installer** at `/rolewarden/install`, and the complete application zip with
  `vendor/` included, for installing without Composer.
- **Demo app** (`demo:install`, `demo:uninstall`) with three roles and a showcase mode.
- **HTML documentation** in `documentation/`.
- The **Permissions** list shows the roles that grant each permission; the role matrix marks
  every permission not yet granted and says how to grant it.
- **One language only:** `$languages` in `app/Config/RoleWarden.php` lists the languages the
  panel offers; with one, the language switch disappears.
- The seeded roles and permissions are shown in the reader's language until an admin
  changes their text; your own can be translated the same way.
- Below 1440px the role matrix tightens, and nothing makes the page scroll sideways.

### Changed
- The panel was redrawn on the "editorial ledger" design system, with the CSS compiled from
  tokens.

### Upgrading from 0.1
- Run `php spark migrate -n RoleWarden`: the new tables (`acl_sessions`,
  `acl_activity_log`) are created and the `admin` role receives the new permissions.
- The panel routes now also apply the `rw-language` filter, registered by the module.
- Views you overrode under `app/Views/overrides/RoleWarden/` predate the new design: compare
  them with the module's and copy them again.

## [0.1.0] - 2026-09-26

The MVP, not sold.

### Added
- Roles and permissions in the database, with role inheritance and per-user overrides that
  grant or deny a single permission.
- Resolution order: a deactivated user is denied everything; then a user's denial, which
  beats even a super admin; then a user's grant; then super admin; then the roles.
- Integration with Shield: `$user->can()` answered from the database, the `can` route
  filter, the `can()`, `can_any()`, `can_all()` and `permissions()` helpers.
- Admin panel: users, roles, the permission matrix per role, overrides per user.
- Protections: never without an active super admin, no inheritance cycles, system roles and
  permissions cannot be deleted.
- Import of Shield's groups from `app/Config/AuthGroups.php`, reversible.
