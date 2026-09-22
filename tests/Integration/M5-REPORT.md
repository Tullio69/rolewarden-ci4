# M5 "Pannello" — independent verification (Collaudatore ad Hoc / Claude)

Stand-in for Codex, blocked by its own usage quota. Same protocol as the project uses
with Codex (CLAUDE.md, "Collaudatore ad Hoc" / "Lancio di Codex per il collaudo di una
tappa"): independent black-box verification against `docs/SPEC.md` and
`docs/BRIEF-MVP.md`, no implementation files read (`src/Controllers/`, `src/Views/`,
`src/Models/`, `src/Config/RouteRegistrar.php`, `src/Assets/`,
`src/Helpers/rolewarden_helper.php`, `tests/Unit/` were not opened). Field names for
forms and the matrix save endpoint were determined by fetching the *rendered* HTML/JSON
over HTTP with curl (what a browser would receive), never by reading source.

HEAD at verification time: `41f30b7` (panel implementation is `670dd97`; later commits
only touch `_AI-LOG.md`/`CLAUDE.md`).

## Environment

- Isolated copy of `rolewarden-app-test` in a temp folder
  (`C:\Users\fabio\AppData\Local\Temp\rolewarden-m5-adhoc`), never the original.
- `.env` pointed at `database.default.database = rolewarden_test` (same host/user/pass/port
  as `docker-compose.yml`), `app.baseURL = http://localhost:8030/`.
- `vendor/rolewarden/codeigniter4-rolewarden` recreated as a directory junction to the real
  module folder (`robocopy` cannot copy the original junction; it silently materialized an
  empty directory in its place, so the junction was recreated by hand pointing at
  `E:\progetti_lavoro\ASP\rolewarden-ci4`, exactly what the original pointed at).
- `app/Config/Auth.php`: `$userProvider` was still `CodeIgniter\Shield\Models\UserModel`
  in the copied sibling app (not the module's own `\RoleWarden\Models\UserModel`, which
  the M3 log entry documents as required host wiring). Set to
  `\RoleWarden\Models\UserModel::class` — this is host wiring, not module implementation,
  and without it the resolver-backed `can()` would never run.
- `app/Config/Routes.php` in the sibling app already carried
  `\RoleWarden\Config\RouteRegistrar::register($routes);` from prior manual testing — left
  as-is, matches README's "Wiring the admin panel" section.
- Migrations (incl. RoleWarden namespace) + `RoleWardenSeeder` run against
  `rolewarden_test` (started empty, confirmed via `SHOW TABLES`).
- Two Shield users created via `spark shield:user create`, activated and role-assigned via
  direct SQL: one holds `super-admin` (the sole active super admin for the duration of the
  run), one holds a purpose-built role with only `users.view`.
- `php spark serve --host localhost --port 8030` run in the background for the duration.

## What was run

`tests/Integration/verify-m5-adhoc.php` — a black-box PHP/curl/mysqli script (no
PHPUnit dependency), covering the eight points of Passo 2. It logs in two real sessions
(cookie-jar based), drives every write through the actual HTTP forms with real,
per-request CSRF tokens (`csrf_test_name`, session-scoped, regenerated per request — the
script re-fetches a token from a page known to carry one immediately before every POST),
and cross-checks every mutation against the database directly via `mysqli`. Full run
output: `tests/Integration/verify-m5-adhoc.output.txt`.

**Result: 77 PASS, 0 FAIL, exit code 0.**

## Coverage per point (Passo 2)

1. **Access control.** Anonymous GET on the panel redirects away (not served). The
   limited user (only `users.view`) can open the list but is refused the create form
   server-side; a second markup diff on the *user detail page* (not the list, which never
   renders per-row action links) confirms `rolewarden/users/.../delete` is present in the
   super admin's HTML and **absent** from the limited user's HTML for the identical page —
   not rendered, not merely disabled, per spec ("Un pulsante che l'utente non puo' usare
   non viene reso"). A direct forced POST to a delete route the limited user lacks
   permission for is refused and the row is confirmed untouched in the DB. PASS.
2. **CRUD users.** Create (verified new row + correct email in list and detail), edit,
   deactivate/activate, delete (soft, `deleted_at` set), role assign/revoke (DB-checked),
   permission override grant/deny/clear (DB-checked `granted` 1/0 and row removal). Last
   active super admin: deactivate and delete both rejected via the panel with the DB
   state unchanged and no SQL error text in the response body. PASS.
3. **CRUD roles.** Create/edit/delete (soft) all DB-verified. System role (`admin`,
   `is_system=1`) cannot be deleted via the panel. A role with an active child cannot be
   deleted. A hierarchy cycle (setting a parent's `parent_id` to its own child) is
   rejected — the parent's `parent_id` was confirmed unchanged in the DB. PASS.
4. **Permission matrix.** Role detail renders rows/areas and columns/actions from the
   seeded permission set. The single-cell save endpoint
   (`POST /rolewarden/roles/{id}/permissions`) returns a small JSON body
   (`{"ok":true,"csrfHash":"..."}`), not a full HTML page — confirmed by absence of
   `<html` in the response and by the DB row appearing immediately, and again on a fresh
   GET of the role page in a later request (no logout in between). **Inheritance,
   verified two ways:** (a) a child role of a role holding `users.view` shows
   inherited-permission wording (inherited/parent) on its detail page; (b) a direct POST
   to the child's own `.../permissions` endpoint attempting `granted=0` for the
   inherited permission returns `ok:true` (no server error) but creates **no** row in
   `acl_role_permissions` for the child and leaves the parent's row untouched — and,
   crucially, a real Shield user assigned only to that child role was confirmed to still
   get HTTP 200 on `GET /rolewarden/users` (`can:users.view`-gated) both before and
   *after* the attempted revoke via the child. This is the strongest form of the check:
   the effective permission was never reduced, not just the raw table row. PASS —
   matches the Definizione di fatto item "Un ruolo figlio non puo' revocare un permesso
   concesso dal padre" end to end.
5. **Permissions list read-only.** No `<form method="post">` and no
   `.../permissions/{id}/(delete|update)` action links anywhere on the page. PASS.
6. **Overridable views.** A file dropped at
   `app/Views/overrides/RoleWarden/Views/permissions/index.php` in the host copy, with a
   distinctive marker string, wins over the module's own view on the next request — no
   restart needed. PASS.
7. **Cross-cutting security.** CSRF: a POST with a syntactically-present but wrong token
   is rejected (HTTP 403, no DB effect). SQL errors: a nonexistent numeric user id
   (`999999999`) returns a clean response with no `SQLSTATE`/`Database Error`/
   `mysqli_sql_exception` text; a non-numeric id (`/rolewarden/users/abc`) 404s at the
   router (the route regex is `([0-9]+)`, so it never reaches a controller) — its debug
   toolbar (development-mode `Debugbar`) does print the string `MySQLi` as the
   configured driver name in its own diagnostic panel, which is normal CI4 dev-mode
   behavior and not an application-level SQL error, so it is **not** scored as a defect;
   see the observation below. A role name containing quotes/SQL metacharacters
   (`O'Brien's "role" -- test`) was accepted without any SQL error text in the response.
   PASS on all of the above.
8. **Live revocation without logout.** The limited user opens the users list (200) while
   logged in; the super admin then revokes `users.view` from the limited user's role
   through the matrix endpoint; the very next request from the limited user's *unchanged
   session* (no logout, same cookie jar) is refused. PASS — matches the Definizione di
   fatto item verbatim.

## Ambiguities observed, not decided (per Passo 3, and one new one)

- **CSS/Tailwind vs hand-written CSS**, already declared in the 2026-09-22 08:10 Claude
  log entry: CLAUDE.md's "no generation scripts" rule is in tension with the brief's
  "Stack e struttura" table listing Tailwind. Not re-decided here.
- **Immediate activation of a panel-created user, no email verification**, already
  declared in the same log entry. Not re-decided here.
- **New observation (not scored as a defect):** with `CI_ENVIRONMENT=development` in the
  test copy, CI4's Debugbar and its JSON exception renderer show framework file paths and
  stack traces (e.g. on the CSRF-rejection response, and the driver name `MySQLi` on any
  page). This is standard CodeIgniter 4 development-mode behavior, not something the
  module renders itself, and the spec's "no SQL error to screen" clause is specifically
  about SQL errors, not general framework diagnostics — the M1 log entry (2026-09-21
  00:25, decision 1) already scoped "no SQL error to screen" to the HTTP surface the
  buyer's own users see, implicitly in whatever environment the buyer runs (normally
  `production`). Flagged for awareness, not scored as a panel defect: the panel itself
  never printed SQL error text in any of the 77 checks, in development mode, including on
  every deliberately-forced failure path exercised above.

## No defects found

All 77 checks passed on the first fully-clean run (an initial run surfaced two script
bugs of my own — wrong CSRF-refresh page for two POST-only creation forms, and comparing
delete-action markup on the users *list* page where it never appears for anyone rather
than the detail page where it does — both fixed in the script itself, not in the module;
the final numbers above are the corrected, clean run).

## Database / cleanup state

All fixtures created by this run (test users, temporary roles, permission grants) were
created directly for this session on `rolewarden_test` only; `rolewarden` was never
selected or touched. `rolewarden_test` was rolled back to zero tables
(`php spark migrate:rollback -b 0 -f`) after the run, matching its state at the start of
this session. The `php spark serve` background process was stopped. The temporary app
copy in `%TEMP%\rolewarden-m5-adhoc` was deleted. The sibling app
`E:\progetti_lavoro\ASP\rolewarden-app-test` was never modified.

## Verdict

**M5 approved** within the scope collaudato above (Passo 2, points 1–8). No defects
found; two pre-existing ambiguities carried forward unresolved, plus one new observation
about development-mode diagnostic verbosity that is not module-specific and not a
violation of the "no SQL error to screen" rule as previously scoped by the author.
