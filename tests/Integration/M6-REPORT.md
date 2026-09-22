# M6 "Innesto" — Independent verification (Collaudatore ad Hoc)

Stand-in for Codex, who was quota-blocked (resets ~12:03, too late to wait). Same
protocol as `tests/Integration/verify-m5-adhoc.php`: black-box against a running copy
of the app, written against `docs/SPEC.md` / `docs/BRIEF-MVP.md` / `README.md` /
`_AI-LOG.md` only. Never opened `src/Controllers`, `src/Views`, `src/Models`,
`src/Config/RouteRegistrar.php`, `src/Assets`, `src/Helpers/rolewarden_helper.php`,
`tests/Unit/`, or `src/Database/Migrations/2026-09-22-090001_ImportAuthGroups.php`.

HEAD verified: `f705290` (M6 implementation, per the log entry of 2026-09-22 09:20).

**Note on `verify-m6-adhoc.php`'s own PASS/FAIL count.** That script's "PASS: 13 FAIL: 0"
is a *transcript-consistency* check — it asserts the recorded observations match what
this report claims was observed, not a verdict on M6. The verdict is below and is NOT
approved.

## Verdict

**M6 NOT approved.** One severe, cleanly reproduced defect (D1) breaks the README's own
documented collision-skip guarantee for the exact scenario M6 exists to support:
installing on an app that already has Shield groups whose names collide with
RoleWarden's seeded system roles (`admin`, `user` — Shield's own default group names).

## Environment

- Copy of `rolewarden-app-test` in `%TEMP%\rw-m6-adhoc` (robocopy `/E /XJ`, vendor
  junction to `E:\progetti_lavoro\ASP\rolewarden-ci4` recreated by hand afterwards,
  since `/XJ` skips junctions entirely rather than materializing them empty).
- `.env`: `database.default.database` → `rolewarden_test`, `app.baseURL` →
  `http://localhost:8040/`.
- `app/Config/Auth.php`: already correctly wired to `\RoleWarden\Models\UserModel`
  (no change needed — the sibling app's own copy of this file, outside the temp copy,
  is still wrong per the 2026-09-22 M5-adhoc log entry, unrelated to this session).
- `app/Config/Routes.php`: already had the documented `RouteRegistrar::register($routes)`
  line.
- `app/Config/AuthGroups.php`: added a group `editor` (not colliding with any
  RoleWarden system role slug) with matrix `['beta.access', 'users.*']` — one own
  permission plus a wildcard over the `users` area, already listed in
  `$permissions`. Left Shield's own `admin` group in place (colliding with the
  seeded system role `admin`), with its stock matrix
  `['admin.access','users.create','users.edit','users.delete','beta.access']`.
- PHP 8.3.11, CI4 4.7.4, Shield 1.4.1, MariaDB 11 in Docker (`rolewarden-db`), database
  `rolewarden_test` only — `rolewarden` was never touched.

## Passo 2 — installation on an "existing app" scenario

1. Migrated only `CodeIgniter\Settings` and `CodeIgniter\Shield` (batches 1, 2). Not
   RoleWarden yet. **PASS**
2. Created two Shield users via `php spark shield:user create` (interactive prompts,
   piped answers — the command has no non-interactive email/password flags that work
   in this CI4/Shield version, despite `--email`/`--password` being accepted on the
   command line, they are ignored and it still prompts), activated both directly via
   SQL (`users.active = 1`). **PASS**
3. `app/Config/AuthGroups.php`: added the non-colliding `editor` group as above,
   assigned user 1 (`editoruser`) to it via `auth_groups_users`. **PASS**
4. Confirmed `admin` and `user` already exist as Shield's own stock groups (no need to
   add them). Assigned user 2 (`adminuser`) to the colliding `admin` group via
   `auth_groups_users`. **PASS**
5. Installed RoleWarden exactly per README: `php spark migrate -n RoleWarden` (all six
   migrations, one batch), then `php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'`.
   **PASS** (ran without error) — but see D1 below for what it produced.
6. No file under `vendor/codeigniter4/framework` or `vendor/rolewarden` (the junction)
   was ever edited during this procedure; only `.env` and `app/Config/AuthGroups.php`
   in the temp copy (host wiring). **PASS**
7. Both pre-existing Shield users could still log in with their original passwords
   after the full install (real HTTP login, cookie-jar session, confirmed via a route
   requiring an authenticated session). Their `users` row anagraphic data (`username`,
   `active`) was unchanged by the install. **PASS**

## Passo 3 — import verification

### The non-colliding group (`editor`) — correct

- `acl_roles`: new row `slug=editor`, `is_system=0`, no `parent_id`. **PASS**
- `acl_role_permissions` for that role: `beta.access` plus the `users.*` wildcard
  expanded over whatever `users.*` permissions existed in `acl_permissions` **at
  import time** — i.e., Shield's own `users.manage-admins`, `users.create`,
  `users.edit`, `users.delete` (RoleWarden's own `users.view` etc. did not exist yet,
  since the seeder hadn't run; this is a consequence of D1's ordering, not itself a
  defect for this group — see D1). **PASS**
- `acl_user_roles`: user 1 got `role_id` = editor's id. **PASS**
- HTTP, logged in as user 1: `GET /rolewarden/users/create` (gated by `users.create`,
  which the role does have) → **200**. `GET /rolewarden/roles` and
  `GET /rolewarden/users` (gated by `roles.view` / `users.view`, which the role does
  NOT have) → **302** (redirected, denied). **PASS**

### The colliding group (`admin`) — D1, severe defect

**Expected** (per README: "A group whose slug already names an existing role ... is
skipped rather than merged into it: the import never changes what an existing role can
do. Skipped groups do not have their assignments imported either.") and per this
task's Passo 3: the seeded system role `admin` keeps EXACTLY its
`RoleWardenSeeder`-defined 12 permissions, no more; user 2 gets no automatic role
assignment for the Shield `admin` group.

**Observed**, reproduced twice (fresh install both times):

1. `acl_roles` row `slug=admin` ends with **`is_system = 0`**, not `1`. It is not the
   row the seeder inserts — the seeder's `insertMissing()` found the slug already
   taken (by the import, which ran first) and skipped inserting its own row, so the
   "system" `admin` role is, structurally, an ordinary importable role. It is no
   longer protected by `Guard::assertSystemRecordNotDeleted` the way `super-admin` and
   the correctly-seeded rows are (M4's "record di sistema non cancellabili"
   guarantee is compromised for this specific installation path).

2. `acl_role_permissions` for `admin` ends up with **17 rows**, not the seeded **12**:
   the seeded set (`users.view`, `users.create`, `users.update`, `users.delete`,
   `users.activate`, `roles.view`, `roles.create`, `roles.update`, `roles.delete`,
   `roles.assign`, `permissions.view`, `permissions.override`) **plus** five leaked
   from the colliding Shield group's own matrix (`admin.access`, `admin.settings`,
   `beta.access`, `users.edit`, `users.manage-admins`). `RoleWardenSeeder`'s
   `ALL_PERMISSIONS_ROLES` grant logic looks up the role by slug and grants it every
   currently-seeded permission regardless of who created that role row, so it happily
   adds the full system permission set on top of whatever the import already put
   there.

   Root cause (inferred purely from the observed timeline, not from reading the
   migration source): `php spark migrate -n RoleWarden` runs the schema migrations
   and the import migration **in the same command**, before `php spark db:seed`
   creates any system role. At the moment the import's "does a role already exist at
   this slug" check runs, `acl_roles` is empty — so the check that is supposed to
   protect `admin`/`user` never sees them, and the collision-skip logic has nothing to
   skip against.

3. `acl_user_roles` gained a row `(user_id=2, role_id=<admin>)` — the pre-existing
   Shield user in the colliding group DID receive an automatic role assignment,
   contradicting the README explicitly.

4. Bonus consequence of the same ordering bug: the two permission slugs shared between
   Shield's `AuthGroups.php` (`users.create`, `users.delete`) and RoleWarden's own
   vocabulary are created by the import (as ordinary, non-system permissions) before
   the seeder runs; `insertMissing()` then finds them already present and never
   upgrades them to `is_system=1`, even though they are part of the official
   12-permission system set. Two of RoleWarden's own system permissions end up
   unprotected.

5. `slug=user` (Shield's other stock default group, also colliding, matrix `[]` in
   this app) is hijacked the same way structurally (`is_system=0` instead of `1`),
   though with no permission-set consequence here because Shield's `user` group has an
   empty matrix and `user` is not in `RoleWardenSeeder::ALL_PERMISSIONS_ROLES`. Still a
   real defect (the seeded `user` role loses its system protection) — just one whose
   blast radius happens to be limited to record protection, not to permission grants,
   **in this particular AuthGroups.php**. An app whose `AuthGroups.php` gives `user`
   real permissions in its matrix would see the same permission-leak as `admin`.

**Minimal reproduction** (any fresh `rolewarden_test`, any `AuthGroups.php` with the
stock Shield `admin` and/or `user` groups left in place — which is the *default*
`AuthGroups.php` shipped by `codeigniter4/shield`, no customization needed for `user`,
and adding real permissions to `admin`'s matrix entry for the full effect):

```
php spark migrate -n 'CodeIgniter\Settings'
php spark migrate -n 'CodeIgniter\Shield'
php spark migrate -n RoleWarden
php spark db:seed 'RoleWarden\Database\Seeds\RoleWardenSeeder'
```

then:

```sql
SELECT is_system FROM acl_roles WHERE slug IN ('admin','user');          -- 0, 0 (expected 1, 1)
SELECT COUNT(*) FROM acl_role_permissions rp
  JOIN acl_roles r ON r.id = rp.role_id WHERE r.slug = 'admin';          -- 17 (expected 12)
```

This is exactly the two-command sequence the README documents as the installation
procedure, run in that order, with no deviation. It is not an edge case: any host
application still carrying Shield's own default `AuthGroups.php` — which ships with
`admin` and `user` groups out of the box — will hit this on first install.

### Rollback

- `php spark migrate:rollback -b <RoleWarden's previous batch>` removes all six
  RoleWarden migrations at once (they share one batch, since they were all applied by
  a single `migrate -n RoleWarden` with no seed step in between to split them). No
  residual `acl_*` table, no residual `migrations` row for RoleWarden. Shield and
  Settings tables and rows untouched. **PASS** for "no residue", though note this
  means there is no way, with the documented install procedure, to roll back only the
  import migration while keeping the schema and seeded roles — the whole module comes
  out together. Not itself scored as a defect (matches what the single-command install
  makes structurally possible), but see the ambiguity note below.
- Full rollback to batch 0 (Shield + Settings + RoleWarden) also clean: only
  framework/Shield-independent tables remain (`users`, `auth_*`), no RoleWarden table
  or row anywhere. Pre-existing Shield users (created before RoleWarden was even
  installed) still present, unchanged. **PASS**

## Passo 4 — targeted confirmation of M1-M5 criteria (not a full re-run; already
collaudata e approvata, vedi voci precedenti in `_AI-LOG.md` per M2 07:50, M3 22:59,
M4 07:12, M5 07:57/2026-09-22-adhoc)

One example each, all on HEAD `f705290`, all via real HTTP against the reinstalled
temp app (fresh `super-admin`-role user, no collision involved):

- **Override negativo batte super admin.** Inserted a `granted=0` override for
  `users.view` on the super-admin user. `GET /rolewarden/users` → 302 (denied);
  `GET /rolewarden/roles` (unrelated permission) → 200 (still allowed). **PASS**
- **Ultimo super admin non eliminabile/disattivabile.** `POST
  /rolewarden/users/<id>/deactivate` on the only active super-admin-role user →
  redirected with no change (`users.active` stayed `1`). **PASS**
- **Ciclo di gerarchia rifiutato al salvataggio.** Set role `developer`'s parent to
  `user`, then tried to set `user`'s parent to `developer` (closing the loop) via the
  real edit form/POST → rejected, redirected back to the edit form, `parent_id`
  unchanged (`NULL`). **PASS**
- (Not independently re-driven through the panel's JS/JSON matrix-save endpoint this
  session — its request contract could not be inferred from the rendered HTML/JSON
  alone within a reasonable black-box effort, and "permesso revocato nega l'accesso
  senza logout" was already solidly re-verified via that exact endpoint in the
  approved M5 adhoc run of 2026-09-22. Instead confirmed the adjacent, previously
  documented limitation still holds: a raw SQL `DELETE` on `acl_role_permissions`,
  bypassing the model layer, does NOT invalidate the cache — expected per the M4 log's
  declared limitation, not a new defect.)

Since D1 already fails Passo 3 on its own, this section is a confirmation that nothing
in M1-M5 regressed on this HEAD, not a substitute full collaudo.

## Ambiguities (not decided, reported only)

1. **CSS a mano vs Tailwind** (carried over from M5, unchanged).
2. **Attivazione immediata utente da pannello senza verifica email** (carried over
   from M5, unchanged).
3. **Nessuna via per il rollback "solo import"**: since the schema and import
   migrations share a batch when installed the way the README documents, there is no
   supported way to undo just the import while keeping the seeded system roles and
   schema in place — the whole module comes out together on any `migrate:rollback`.
   Not itself a violation of the "limite dichiarato" (D1 is a separate, real defect,
   not this), but worth a product decision: is a step-granular rollback expected here,
   or is "reinstall from scratch" an acceptable recovery path for a botched import?

## Cleanup

`rolewarden_test` rolled back to empty (both RoleWarden and Shield/Settings), residual
`migrations` table dropped (mirrors the state prior M1-M5 sessions left it in). `php
spark serve` on port 8040 stopped. Temp app copy in `%TEMP%\rw-m6-adhoc` deleted.
`rolewarden` database never selected or touched. No commit made.

---

## Reverification — "Collaudatore ad Hoc" — HEAD `48ab158` (fix for D1/D2)

Second independent pass, standing in for Codex (again quota-blocked until ~12:04). Same
protocol: black-box against a real running copy of the app; `src/Controllers`,
`src/Views`, `src/Models`, `src/Config/RouteRegistrar.php`, `src/Assets`,
`src/Helpers/rolewarden_helper.php`, `tests/Unit/`, and — this time also explicitly —
`src/Database/Migrations/2026-09-22-090001_ImportAuthGroups.php` and
`src/Database/Seeds/RoleWardenSeeder.php` (the two files the previous D1 fix touched)
were never opened. Output transcript: `tests/Integration/verify-m6-reverify.output.txt`
(15 PASS, 0 FAIL — a transcript-consistency check on the observations below, same caveat
as `verify-m6-adhoc.php`: the verdict is this prose, not that script's own exit code).

### Verdict

**M6 now approved.** The fix in commit `48ab158` (import migration seeds system
roles/permissions itself, first line of its `up()`, before running its own collision
check; seeder's permission grant filtered to `RoleWardenSeeder::PERMISSIONS` only) closes
both D1 and the related D2 found in the previous report, reproduced against the exact
same scenario (Shield's stock `admin` group with real permissions in its matrix, plus a
non-colliding `editor` group), following the README's documented two-command install
verbatim, in that order.

### Environment

- Fresh copy of `rolewarden-app-test` in `%TEMP%\rw-m6-reverify` (robocopy `/E /XJ`,
  vendor junction to `E:\progetti_lavoro\ASP\rolewarden-ci4` recreated by hand
  afterwards).
- `.env`: `database.default.database` → `rolewarden_test`, `app.baseURL` →
  `http://localhost:8060/`.
- `app/Config/Auth.php` and `app/Config/Routes.php` in the temp copy already correctly
  wired (`\RoleWarden\Models\UserModel`, `RouteRegistrar::register($routes)`) — no change
  needed.
- `app/Config/AuthGroups.php`: added a group `editor` (matrix `['editor.publish',
  'users.*']`, `editor.publish` added to `$permissions`), non-colliding with any
  RoleWarden system role slug. Left Shield's own `admin` group in place (colliding with
  the seeded system role `admin`), with its stock matrix `['admin.access',
  'users.create', 'users.edit', 'users.delete', 'beta.access']`.
- Two Shield users created via `php spark shield:user create` (non-interactive piping of
  username/email/password/confirmation), activated via direct SQL
  (`users.active = 1`): `adminuser` (id 1, assigned to the colliding `admin` group via
  `auth_groups_users`) and `editoruser` (id 2, assigned to the non-colliding `editor`
  group).
- PHP 8.3.11 / CI4 4.7.4 / Shield 1.4.1, MariaDB 11 in Docker (`rolewarden-db`), database
  `rolewarden_test` only — `rolewarden` was never selected or touched.

### Passo 2 — installation, exactly per README

`php spark migrate -n 'CodeIgniter\Settings'` then `php spark migrate -n
'CodeIgniter\Shield'`, then, unmodified from the two Shield users' assignments above,
`php spark migrate -n RoleWarden` followed by `php spark db:seed
'RoleWarden\Database\Seeds\RoleWardenSeeder'`. The `migrate -n RoleWarden` output itself
now shows `Seeded: RoleWarden\Database\Seeds\RoleWardenSeeder` printed **before** the six
migration lines — directly observable confirmation that the import migration seeds first,
as the fix intends, regardless of migration/seed command order.

### Passo 3 — the two defects, re-verified fixed

**D1 (role hijack).** `SELECT id, slug, is_system, is_super_admin FROM acl_roles;`
immediately after `migrate -n RoleWarden` (before any `db:seed` call) already shows:

```
id  slug          is_system  is_super_admin
1   super-admin   1          1
2   admin         1          0
3   user          1          0
4   superadmin    0          0   <- imported, non-colliding Shield group
5   developer     0          0   <- imported, non-colliding Shield group
6   beta          0          0   <- imported, non-colliding Shield group
7   editor        0          0   <- imported, non-colliding, the one this session added
```

`admin` and `user` are `is_system=1` from the very first `migrate -n RoleWarden` command,
before `db:seed` ever runs — the collision check now has something to skip against.
`acl_role_permissions` for `admin`:

```
permissions.override, permissions.view, roles.assign, roles.create, roles.delete,
roles.update, roles.view, users.activate, users.create, users.delete, users.update,
users.view
```

Exactly the 12 seeded slugs, byte for byte. No leak from Shield's colliding `admin`
group's own matrix (`admin.access`, `beta.access`, `users.edit` are absent, as they
should be — only `users.create`/`users.delete`, which are genuinely part of
RoleWarden's own vocabulary too, appear, and they appear because they are seeded system
permissions, not because they leaked from the import).

**D2 (reseed inflation).** Ran `php spark db:seed
'RoleWarden\Database\Seeds\RoleWardenSeeder'` a second time (the documented step).
`admin`'s permission set and `is_system` flag are unchanged: still exactly the same 12
slugs, still `is_system=1`. On the unfixed HEAD `f705290` this second run was exactly
what inflated `admin` to 17 permissions; it no longer does.

**Bonus finding from the previous report, also closed.** `SELECT slug, is_system FROM
acl_permissions WHERE slug IN ('users.create','users.delete');` → both `is_system=1`.
Previously these two slugs (shared between `AuthGroups.php`'s own permission vocabulary
and RoleWarden's) were created by the import before the seed ran and stayed
`is_system=0`; now the seed runs first, so they are born as system permissions and the
import's `insertMissing()` finds them already correctly flagged.

**`acl_user_roles`**: `SELECT * FROM acl_user_roles;` after both the install and the
second `db:seed` → only `(user_id=2, role_id=7)`, i.e. `editoruser` → `editor`. No row
for `user_id=1` (`adminuser`, the pre-existing Shield user in the colliding `admin`
group) — matching the README's "skipped groups do not have their assignments imported
either", the exact guarantee D1 broke previously.

### The non-colliding "editor" group — still correct

`acl_role_permissions` for `editor`: `editor.publish`, `users.create`, `users.delete`,
`users.edit`, `users.manage-admins` — its own declared permission plus the `users.*`
wildcard expanded over `AuthGroups.php`'s own `$permissions` list (not over whatever
happened to already exist in `acl_permissions`, which is the more sensible and
seed-order-independent reading, and produces the same set either way in this scenario).

Confirmed via real HTTP with a fresh cookie-jar session and a CSRF token pulled from a
freshly-fetched login page (the rendered Shield login form's fields are `email` and
`password`, not `login` — noted for future black-box scripts against this app copy):
logged in as `editoruser`, `GET /rolewarden/users/create` (gated by `users.create`, which
`editor` has) → **200**; `GET /rolewarden/roles` and `GET /rolewarden/users` (gated by
`roles.view`/`users.view`, which `editor` does NOT have) → **302** both.

### The colliding "admin" group user — no access, real HTTP

Logged in as `adminuser` (the Shield user in the colliding `admin` group, no
`acl_user_roles` row at all): `GET /rolewarden/users` and `GET /rolewarden/roles` → both
**302**, consistent with having zero roles.

**Methodological note, not a module defect.** The first attempt at this check, right
after copying the temp app with `robocopy`, returned **200** on every route for this
user — alarming until traced to its cause: `robocopy /E` copies the sibling dev app's
`writable/cache` directory verbatim, and that directory already contained stale
per-user permission cache files (`rolewarden_user_1`, `rolewarden_user_2`) left over from
unrelated prior work on that same app skeleton against the `rolewarden` database, keyed
only by user id (`rolewarden.user.{id}`) with no regard for which database populated
them. Since the freshly created `adminuser` in `rolewarden_test` also got id `1`, it
inherited a stale cached permission set granting broad access. Deleting
`writable/cache/rolewarden_user_*` (keeping `index.html`) before running any HTTP checks
made the 302s appear as expected. Recorded here because it is exactly the kind of
observation a future black-box session copying this same fixture app should watch for —
**always clear `writable/cache` (and `writable/session`, to be safe) right after copying
the sibling app, before doing anything else.**

The pre-existing Shield user in the colliding group can still log in normally after the
full install (real HTTP login, correct password, redirected to the home page) — the
install does not corrupt or lock out an existing user, it only fails to import a role for
them, as documented.

### Rollback

`php spark migrate:rollback -b <Shield's batch>` removed all six RoleWarden migrations
(schema + import) as one batch: `SHOW TABLES LIKE 'acl_%'` empty afterward, zero
`RoleWarden%` rows left in `migrations`, both Shield users (`adminuser`, `editoruser`)
present and unchanged (`active=1`). Full rollback to batch 0 then removed Shield and
Settings too, cleanly. Same known limitation as the first M6 pass: no supported way to
roll back only the import while keeping schema/seed, since they share a batch under the
documented install procedure — unchanged, not re-scored as a new defect.

### Passo 3 (per this session's own numbering) — targeted confirmation, HEAD `48ab158`

Reinstalled fresh (`migrate -n RoleWarden` + `db:seed`) after the rollback, assigned
`adminuser` the `super-admin` role directly via SQL for this section only:

- **Login + a CRUD-protected route.** Logged in as the super-admin-role user via real
  HTTP; `GET /rolewarden/users` and `GET /rolewarden/roles` both → **200**.
- **Last active super admin cannot be deactivated.** Fetched the real detail page for
  that user, extracted its actual `POST /rolewarden/users/1/deactivate` form action and a
  fresh CSRF token from that same page, submitted it → redirected back, `users.active`
  in the database stayed `1`.

### Ambiguities

Unchanged from the previous M6 pass, not re-decided: CSS/Tailwind (M5), immediate user
activation without email verification (M5), absence of a "import-only" rollback path
(M6, noted above again). No new ambiguity surfaced.

### Cleanup

`rolewarden_test` rolled back to batch 0 (RoleWarden + Shield + Settings), residual empty
`migrations` table dropped afterward, `SHOW TABLES` confirmed empty before closing. `php
spark serve` on port 8060 (and its child listener on the auto-selected port 8061)
stopped. Temp app copy in `%TEMP%\rw-m6-reverify` deleted. `rolewarden` database never
selected or touched. No commit made.
