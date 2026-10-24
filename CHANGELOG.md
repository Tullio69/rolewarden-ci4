# Changelog

Every change that matters to whoever installs or updates RoleWarden. Versions follow
[semantic versioning](https://semver.org): a minor version adds without breaking, a major
version may require changes on your side, listed under "Upgrading". After any update, run
`php spark migrate -n RoleWarden`.

## [Unreleased]

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

### Changed
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
  `dashboard.*` permissions, granted to `admin` and to every role that holds `activity.view`;
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
