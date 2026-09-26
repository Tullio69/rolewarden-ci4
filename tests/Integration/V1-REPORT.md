# v1.0 "design system" panel: independent verification (Collaudatore ad Hoc)

This run stands in for Codex, whose quota ran out. It follows the same protocol: black-box testing
against an isolated copy of the app, written only against `docs/SPEC.md`,
`docs/BRIEF-MVP.md`, `_AI-LOG.md`, `src/Authorization/Contracts/`, `docs/design-system/`
and the README wiring section. I never opened `src/Controllers/`, `src/Views/`,
`src/Models/`, `src/Config/RouteRegistrar.php`, `src/Assets/`,
`src/Helpers/rolewarden_helper.php` or `tests/Unit/`. I read every form field name and
endpoint shape from the HTML/JSON that the panel returns over HTTP. The view path used by the
override test (X05) came from CI4's own `DEBUG-VIEW` comments in development output.

HEAD verified: `7f5c629` (master).

## Verdict

**FAIL. v1.0 is NOT approved.** 140 PASS, 14 FAIL, 2 AMBIGUITY (development run
135/11/2 plus the production spot check 5/3). Every M1-M6 protection that I tested again
still holds (see "Not regressed"). The FAILs come down to six confirmed module defects
(D1-D6). Each one was re-checked to rule out a test-script cause.

## Environment

- Copy of `../rolewarden-app-test` made with `robocopy /E /XJ` into the session scratchpad. The
  vendor junction to this repo was recreated by hand. `writable/cache` and `writable/session`
  were emptied before any request.
- `.env` of the copy uses `database.default.database = rolewarden_test`,
  `app.baseURL = http://localhost:8070/`, and has no password line. DB credentials
  reach CI4 only as process environment overrides built from `RW_DB_*` (`verify-v1.sh`).
- Wiring in the copy was already as README "Wiring the admin panel" says:
  `RouteRegistrar::register($routes)` in Routes.php, `$userProvider =
  RoleWarden\Models\UserModel`, `$views['login'] = '\RoleWarden\Views\auth\login'`.
  Shield resolves that login view without changes to Shield (L01: the page is served by
  `src/Views/auth/login.php` through Shield's own `/login` route).
- Install per README on a freshly dropped/created `rolewarden_test`: migrate
  `CodeIgniter\Settings`, `CodeIgniter\Shield`, `RoleWarden`, then `db:seed`.
- There was one `php -S` process at a time (never `spark serve`, which forks a child). The wrapper's EXIT trap
  stopped it after each phase. At the end, 0 `php.exe` processes were running.
- Development phase: `CI_ENVIRONMENT=development`. Production spot check: same data,
  `CI_ENVIRONMENT=production`, set in the copy's `.env`. A process env var does not win
  over `.env` in CI4 when `$_ENV` is not populated, and an earlier attempt that relied
  on it was discarded.
- Requests send a browser `Accept: text/html` header. Matrix saves send
  `X-Requested-With` and `Accept: application/json`, as the panel's own fetch does.

Files: `verify-v1.sh` (install + server lifecycle), `verify-v1.php` (development run),
`verify-v1.prod.php` (production spot check), `verify-v1.lib.php`,
`verify-v1.fixtures.php`, `verify-v1.output.txt` (last full run).

## Confirmed defects

### D1. User search crashes with a SQL error (high)

- Request: `GET /index.php/rolewarden/users?q=mara`, or any non-empty `q`, e.g. `q=Priya`.
  `q` is the name of the Search field in the panel's own filter form.
- Expected: 200 with the matching users (UsersList README: "Search", "Filters apply…";
  SPEC Elenco utenti "Tabella con ricerca"). No SQL error on screen in any environment
  (CLAUDE.md, BRIEF "Regole di codice" 2, BRIEF definition of done).
- Got: HTTP 500. In development the page shows
  `DatabaseException: Unknown column 'email' in 'WHERE'` with the query. In production
  it shows the generic "Whoops!" page. Shield's `users` table has no `email` column; the email
  lives in `auth_identities.secret`.
- Checks: U11, U12, U13, Z01, Q02 (×2).

### D2. Adding a nonexistent role to a user gives a 500 with a SQL error (medium)

- Request: `POST /index.php/rolewarden/users/{id}/roles` with `role_id=99999` and a fresh
  CSRF token.
- Expected: a validation refusal, no SQL error on screen. The mandate's "tentativi di rottura
  (ID inesistenti…)" and the BRIEF definition of done ("Nessun errore SQL visibile a
  schermo in nessun percorso, compresi quelli di fallimento") both apply.
- Got: HTTP 500. In development the page shows `Cannot add or update a child row: a foreign key
  constraint fails (rolewarden_test.acl_user_roles, CONSTRAINT
  acl_user_roles_role_id_foreign …)`. In production it shows "Whoops!". Nothing is written. For comparison,
  `role_id=abc` is handled cleanly (P11).
- Checks: P12, Z01, Q05.

### D3. A user can deactivate themselves (high)

- Request: `POST /index.php/rolewarden/users/{own id}/deactivate` with a fresh CSRF token,
  sent by the signed-in user about themselves. I reproduced it as Priya (admin, not super
  admin) and as the owner (super admin, with a second active super admin present, so the
  last-super-admin rule does not apply).
- Expected: refused server side. UserDetail README: "Don't let a user … disable
  themselves". SPEC "Pannello admin": "nascondere non e' proteggere" means the
  server check stays. The mandate, point 4, asks for this check to be server side, not just a disabled button.
- Got: `303` to the user page and `users.active = 0`. The UI does disable the button on the
  user's own page (P07/P07b PASS), but the endpoint does not enforce the rule. I repaired the
  fixture by SQL after each attempt and flushed the copy's file cache.
- Checks: P06, P06b.

### D4. A user can delete themselves (high)

- Request: `POST /index.php/rolewarden/users/{own id}/delete` with a fresh CSRF token.
  Same two actors as D3.
- Expected: refused server side, as the mandate's point 4 requires ("un utente non deve poter … eliminare
  se stesso (controllo lato server)"). Same SPEC principle as D3.
- Got: `303` to the users list and `users.deleted_at` set.
- Checks: P05, P05b.

### D5. The login screen has no password-recovery link (medium)

- Request: `GET /index.php/login`. Shield has `allowMagicLinkLogins = true`, and
  `GET /index.php/login/magic-link` answers 200.
- Expected: SPEC "Schermate": `Login | Credenziali, recupero password`. SPEC MVP
  "Recupero password (Shield, viste nostre)". Login README: "…password, remember me and a
  forgot-password link", "Forgot password sits on the label line".
- Got: no link to any recovery flow. The page footer reads "Trouble signing in? Ask an
  administrator to check your account." Shield's stock login view offers the link, so
  swapping in the RoleWarden view removes the only way into recovery from the UI. The
  v1.0 log entry does not declare this as deferred. It only defers "Send reset link" on UserDetail.
- Check: L05.

### D6. The password field is not `type="password"` in the served HTML (low)

- Request: `GET /index.php/login`.
- Expected: the password is masked by default (Login README: a Show/Hide ghost button toggles it).
- Got: `<input … :type="show ? 'text' : 'password'" id="password" name="password">`
  with no static `type`. Until Alpine (loaded with `defer`) starts, or if JS fails to load,
  the browser renders a plain text field and the typed password is visible. Adding a static
  `type="password"` next to the binding removes the gap. I could not render the page, so I have
  not observed the flash in a browser. The markup alone is enough to show the gap.
- Check: L03.

## Ambiguities (reported, not decided)

- **A1. Failed-login wording (L08).** A known email with a wrong password shows "Unable to log
  you in. Please check your password." An unknown email shows "Unable to log you in.
  Please check your credentials." Both strings are Shield's own (`Auth.invalidPassword` and
  `Auth.badAttempt` in `vendor/codeigniter4/shield/src/Language/en/Auth.php`), and the view
  shows them verbatim, as the mandate asks ("il messaggio d'errore di Shield deve
  comparire"). The Login README also says "Never say which of email or password was
  wrong". As things stand the difference leaks whether an email is registered. Deciding
  between the two requirements is a spec decision, not a code fix.
- **A2. "Own last Administrator role" (P15).** Priya (admin) removed her own `admin` role
  through `POST users/{own id}/roles/{admin}/revoke`, and the server accepted it. UserDetail README:
  "Don't let a user remove their own last Administrator role". The spec does not say
  which RoleWarden role "Administrator" is. It could be the seeded system `admin`, any role
  granting user/role management, or only `super-admin`, which the last-super-admin rule already
  protects (P01/P02 PASS). I restored the role by SQL.
- **A3. Role matrix "refused or ignored" (M12).** A direct POST revoking an inherited cell on the
  child role (`roles.view` on V1 Child) answers `{"ok": true}` and changes nothing: parent grants intact, the
  child holder keeps access. The mandate accepts either behaviour. Note that the client is told
  "ok" for a no-op.
- Pre-existing, unchanged, not re-decided: hand-written CSS instead of Tailwind (decided
  by the author); dark theme tokens present (`[data-theme="dark"]` found in `panel.css`,
  X03) but with no control to turn them on; no "import only" rollback for M6.

## Observations (not defects)

- `permission_id='1 OR 1=1'` on both matrix endpoints is accepted as `1`, which looks like a lenient
  integer cast. The row written was a legitimate override on permission 1, with no injection:
  queries are parameterized, and no other value was written. `abc`, `''`, `-1`, `99999` and
  `1.5` are refused or ignored cleanly (M18, D21).
- The Parent role fact on the role page links to the roles list, not to the parent role.
- Cell buttons in both matrices are rendered client-side by Alpine (`x-for`, `x-html`).
  The role matrix binds `:aria-pressed` and `:aria-disabled`. The user matrix binds neither.
  Neither button template has a static `aria-label`: the PermissionMark README asks for one
  ("articles.update, inherited from Author. Locked."). It may be injected by the `mark()`
  output, which HTTP cannot see without a JS runtime. **Not verifiable black-box. Flag for the
  browser pass.**
- Only two pages of users exist with 36 users, so I could not exercise the "more than 7 pages →
  ellipsis" rule.
- Before this run `rolewarden_test` was **not** empty. It held 14 tables with
  `V1*` users, timestamped 2026-09-22 21:54, which is after the 21:48 log entry that recorded 0
  tables. It is probably residue of the killed Codex run. I snapshotted and restored it byte for byte, as
  found, and did not clean it. A `codex.exe app-server` process (started 22:11) was
  running during this session. It did not touch this run: no php servers, and it did not
  write the DB during the run.

## Not regressed (PASS, grouped)

- **Login contract (L01-L02, L04, L06-L07, L09-L12):** form posts to `url_to('login')` with
  `csrf_field()`, `email`, `password`, `remember`. The Show/Hide button binds `aria-pressed` and
  `aria-controls`. A failed login redirects back with a `role="alert"` block carrying Shield's
  message. Remember-me creates a Shield `auth_remember_tokens` row. The session opens
  `can:users.view` routes, and logout closes them. A disabled user gets no panel access.
- **Gating (G01-G08):** anonymous users are redirected to login everywhere. A users.view-only user
  sees the list but no "New user" button (not rendered). Other routes are refused. Both matrix
  endpoints and deactivate are refused with nothing written (including self-escalation on
  own role).
- **UsersList (U01-U10, U14-U19):** `aria-current` in the sidebar. `aria-sort` on the Last login
  header follows `sort=asc|desc`. Default order matches `auth_logins` (most recent
  first). "Never" is shown for users who never signed in. The pager has `aria-current="page"`,
  Previous is `aria-disabled` (not hidden), and row numbers continue on page 2. The count sits in
  `role="status"`. Role and status filters and their combination are correct against the DB.
  "Clear filters" appears when a filter is set. The empty state reads "No users match these
  filters.". Tampered `role`/`status`/`sort`/`page` produce no 500 and no SQL text.
- **RolesList (R01-R12):** role create over HTTP with a parent works. Markup in name/description
  is escaped. Depth-first order: child directly under parent, grandchild under child. "Inherits
  from" and the elbow match `acl_roles.parent_id` for every row. Depth 2 is marked
  differently from depth 1. Counts read "1 own + 2 inherited" and "0 own + 3 inherited".
  System roles have no Delete and carry the note. The delete dialog names the affected user.
  Footer totals match the DB. A cycle is refused at save. System role delete is refused
  server side.
- **Role matrix (M00-M19):** inherited cells are `inherit`, locked, with the tooltip "from V1 Parent".
  The own cell is `role`, editable. The five-mark legend uses exactly the README SVG paths.
  Row/column select-all has `indeterminate`. The unsaved bar has Discard / Save changes /
  "All changes saved.". The "Effective" footer is correct (3 in view). A single-cell save
  persists at once and returns a fresh `csrfHash` that works for the next POST. Grant and
  revoke take effect on the holder's **next request without logout**. Revoking on the
  parent propagates down the chain on the next request, and re-granting restores it. Missing or
  wrong CSRF is refused with nothing written. Malformed `permission_id` and a nonexistent role
  produce no 500 and no SQL text.
- **UserDetail (D00-D23):** sections A/B/C lettered, legend and unsaved bar present.
  Deferred features are absent (no Send reset link / Sign out everywhere / Export CSV /
  Invite / Settings, and no dead links). An override grant (`granted=1`) persists and opens the
  route on the user's next request without logout. The matrix shows `grant` and the Overrides
  fact shows 1. A request without `granted` removes it and access closes again. A deny
  (`granted=0`) on a role grant closes the route. The role matrix summarises it as `deny`,
  read-only, with the tooltip naming the user ("denied for 1 user"). **Deny beats super
  admin:** Priya made super admin is still refused the denied permission and gets the
  others. Clearing the deny reopens it. Removing the role that fed a deny keeps the override.
  Missing or wrong CSRF is refused. Malformed `permission_id`/`granted` and a nonexistent user
  produce no 500 and no SQL text.
- **M4 protections through v1 (P01-P04, P07-P11, P13-P14):** the last active super admin can lose
  neither the role (self or via another admin), nor be deactivated or deleted by another admin.
  On the user's own page the Deactivate/Delete controls are disabled (but see D3/D4). 404 for
  nonexistent users and roles (show/edit). No 500 on POSTs to nonexistent ids. Form POSTs without
  or with a wrong CSRF are refused.
- **Links, assets, overrides (X01-X05):** all 44 same-origin links and assets on the panel pages
  resolve (no 4xx/5xx, no link to an unbuilt feature). `panel.css` is served as `text/css`
  and defines every custom property of `docs/design-system/tokens.css`, plus `:focus-visible`
  on `var(--focus)`. The asset route does not serve files outside its folder (6 traversal
  variants). A host override at `app/Views/overrides/RoleWarden/Views/roles/show.php`
  wins, and removing it restores the module view.
- **Global (Z02):** no PHP warning, notice or deprecation in any of 122 responses. Z01 fails only
  on the D1/D2 requests.

## Limits of this verification

- There was no real browser. Visual fidelity, Alpine behaviour (tri-state checkboxes, tooltips
  on hover/focus, `<dialog>`, client-side dirty tracking, focus moving to the first
  invalid field) and the rendered accessible names of matrix cells are not certified here.
- The role and user matrices were exercised through their save endpoint one cell per request, the same
  requests the Save button sends, rather than by driving the UI.

## Database and process hygiene

- Snapshot: `mariadb-dump --databases rolewarden_test` taken before any change.
  Restored after the runs, and a fresh dump is byte-identical (md5
  `0736e6d52daf7f587c4d1f2fae2828bf` before and after). `rolewarden` was never opened:
  the script refuses to run unless `SELECT DATABASE()` is `rolewarden_test`.
- The server was stopped after every phase. 0 `php.exe` processes remained at the end. I removed
  the temporary app copy, removing its vendor junction first, and deleted the cookie jars.
- No credentials in any file: `verify-v1.*` read `RW_DB_*` from the environment, and test
  user passwords are random per run and held only in memory.

---

# Re-verification after the D1-D6 fixes (Collaudatore ad Hoc, 2026-09-22 23:03)

This run also stands in for Codex (quota exhausted), under the same protocol. It tests the
**uncommitted working tree** (HEAD `7f5c629` plus the fix recorded in `_AI-LOG.md` at 23:00),
not HEAD. As before I read only SPEC, BRIEF, `_AI-LOG.md`, `src/Authorization/Contracts/`,
`docs/design-system/`, the README and the `tests/Integration/` files. I did not open
controllers, models, views, assets, helpers or unit tests, and I did not look at the `src/` diff.

## Verdict

**PASS. v1.0 is approved.** Development run: 166 PASS, 0 FAIL, 4 AMBIGUITY entries (A1, A2, and
the new A4, which is logged once for `%` and once for `_`). Production spot check: 12 PASS, 0 FAIL.
Every check from the first run was run again. The six defects are fixed, and nothing that passed
before regressed.

## D1-D6 status

| Defect | Status | Evidence |
|---|---|---|
| D1 user search SQL error | **Fixed** | U11-U13 PASS. `q=mara` returns only Mara. `q=Priya` and `q=PRIYA` return Priya. New checks: an email-only fragment (`theo@northwind`) returns only Theo (U13b), a query with no match shows the empty state (U13c), injection strings return 0 rows with no SQL text (U13d), and search combined with the role filter works (U13e). Production Q02/Q03 PASS (200, not "Whoops"). |
| D2 nonexistent role: 500 with FK error | **Fixed** | P12 PASS in development and Q05 PASS in production, both HTTP 404 with nothing written. New checks: an existing role posted to nonexistent user 99999 gets 404, no SQL text, nothing written (RV01, Q05b), and adding a valid role to a valid user still works (RV02). |
| D3 self-deactivation | **Fixed** | P06 (Priya, admin) and P06b (owner, with a second super admin present) PASS: 303 back to the user page, `active` stays 1. The redirect shows "Error: You cannot disable or delete your own account." (RV07). The actor's session stays valid (RV04). An admin can still deactivate another user (RV05). Production Q06 PASS. |
| D4 self-deletion | **Fixed** | P05 and P05b PASS, `deleted_at` stays NULL. An admin can still delete another user (RV06). Production Q07 PASS. |
| D5 missing recovery link | **Fixed** | L05 PASS. New check L05b: the link is an `<a href=".../login/magic-link">` with the text "Forgot your password?" and resolves to 200. L05c: the text is a real string, not a raw language key. Production Q08 PASS. |
| D6 no static `type="password"` | **Fixed** | L03 PASS: a static `type="password"` is served. L03b: the Alpine `:type` binding for Show/Hide is still there. Production Q08 PASS. |

## Script errors found and corrected in this run (not module defects)

- **Empty CSRF token on some POSTs in the first run.** `Client::token()` read the token from
  `rolewarden/users`, which renders no form and so carries no token. The limited user's pages
  carry no token either. As a result, P10 (four POSTs to nonexistent ids) and G06-G08 (limited
  user's forbidden POSTs) were rejected by CSRF (403 `SecurityException`) before they reached
  the check under test. They passed, but for the wrong reason. The first version of RV01 hit the
  same problem. Fix: when a page has no form, `token()` falls back to the actor's own CSRF value,
  read from the copy's file session (the app uses session CSRF). The affected checks now also
  assert `! csrf_refused()`. After the fix, G06-G08, P10 and RV01 all PASS on the real path:
  permission refusals, 404s and no 500s.
- The first draft of U13d counted the `%` wildcard as a FAIL. It is not a spec violation, so it is
  now reported as ambiguity A4 below.

## Ambiguities (reported, not decided)

- **A1 (unchanged). Failed-login wording (L08).** A known email with a wrong password shows "Unable
  to log you in. Please check your password." An unknown email shows "... Please check your
  credentials." Both strings are Shield's own and are shown verbatim, as the mandate asks. The
  Login README says "Never say which of email or password was wrong", and the current wording
  reveals whether an email is registered.
- **A2 (unchanged). "Own last Administrator role" (P15).** Priya (admin) can still remove her own
  `admin` role, and the server accepts it. The UserDetail README forbids removing one's "last
  Administrator role" but never says which RoleWarden role that is.
- **A3 (unchanged, minor). Role matrix no-op (M12).** A direct revoke of an inherited cell answers
  `{"ok": true}` and changes nothing. The mandate accepts either behaviour.
- **A4 (new). LIKE wildcards in user search (U13w).** `q=%` and `q=_` match every user, `q=d_na`
  finds Dana, and `q=dana%test` finds her too. So `%` and `_` typed by the user work as SQL LIKE
  wildcards instead of being matched literally. This is not injection: the value is bound,
  quote-based and `OR 1=1` payloads match nothing, and no SQL text appears. SPEC and the UsersList
  README only say "search", so whether these characters must be matched literally is a spec
  decision.
- Pre-existing, not re-decided: hand-written CSS instead of Tailwind; dark theme tokens with no
  control to turn them on; no "import only" rollback for M6.

## Not regressed

All first-run PASS checks still pass on the working tree: login contract and remember-me (L01-L12),
gating (G01-G08, now exercised past CSRF), UsersList (U01-U19), RolesList (R01-R12), role matrix
(M00-M19), UserDetail matrix and overrides, including deny beating super admin (D00-D23), the M4
protections (P01-P14), links/assets/traversal/view override (X01-X05; 44 links, no 4xx/5xx). Z01 now
passes: no SQL or DB error text in any of 137 responses. Z02: no PHP warning, notice or deprecation.

## Environment and hygiene

- Fresh copy of `rolewarden-app-test` in the session scratchpad (`robocopy /E /XJ`, vendor junction
  recreated by hand). In the copy's `.env`, the password lines were removed and the database was
  set to `rolewarden_test` and the base URL to `:8070`. The original app was not modified. DB
  credentials came only from `RW_DB_*`.
- Install per README on a freshly dropped and recreated `rolewarden_test`, then the development
  phase and the production phase (`CI_ENVIRONMENT` in the copy's `.env`), with one `php -S` at a
  time, stopped by the wrapper's trap. Three short diagnostic probes (one server each, also
  trap-stopped) were used to separate the script errors above from module behaviour.
- Snapshot `mariadb-dump --skip-dump-date --databases rolewarden_test` before any change,
  restored at the end. The dump after the restore has the same md5 as the snapshot
  (`cda54cd2d53ed7912e6d0f8cfeb7b08a`). `rolewarden` was never opened.
- At the end: 0 `php.exe` processes; the app copy was removed (junction first, the module repo is
  intact); dumps, probe scripts, server logs and cookie jars were deleted.
- No commit, and no changes to `src/` or `docs/`.
- Limits unchanged: without a real browser, visual fidelity, Alpine behaviour and the rendered
  accessible names of matrix cells are not certified.

---

# Collaudo delle decisioni A1-A4 del 2026-09-23 e regressione v1.0 (Collaudatore ad Hoc, 2026-09-23)

Sostituisce Codex (ucciso dal sistema per memoria insufficiente), stesso protocollo. Oggetto: il working
tree non committato (non HEAD) dopo le correzioni A1-A4 delle 07:35. Letti solo SPEC (in particolare
"Pannello admin" e "Decisioni del 2026-09-23"), BRIEF, `_AI-LOG.md`, `docs/design-system/` (README
Login e UserDetail), `src/Authorization/Contracts/`, README e i file di `tests/Integration/`. Mai
aperti controller, model, view, asset, helper, `RouteRegistrar`, `Guard.php`, `tests/Unit/` ne' il diff di `src/`.

## Verdetto

**Approvata.** A1-A4: 49 PASS / 0 FAIL in development e 49 PASS / 0 FAIL in production, 1 ambiguita'.
Regressione: `verify-v1.php` (development) 171 PASS / 0 FAIL, `verify-v1.prod.php` (production)
12 PASS / 0 FAIL. Nessun testo SQL a schermo in nessuna risposta. Nessun difetto confermato.

## Esito per decisione (identico in development e production)

- **A1 PASS.** Login fallito con email registrata e password errata, e con email sconosciuta: stesso
  blocco `role="alert"`, "Error: Unable to log you in. Please check your credentials.". POST arrivato
  all'autenticazione (non un rifiuto CSRF), redirect a `login`. Nella suite v1.0 L08 ora e' un controllo (PASS).
- **A2 PASS.** Admin con il solo ruolo `admin` sulla propria pagina: il controllo del ruolo (etichettato
  "Remove", come nel README UserDetail) e' disabilitato con `title` "You cannot remove your own role that
  lets you manage roles."; la POST diretta di revoca e' rifiutata (303 con "Error: You cannot remove your own
  role that lets you manage roles."), `acl_user_roles` invariata. Consentita, con ruolo tolto nel DB e
  controllo non disabilitato in pagina, quando un altro ruolo concede `roles.assign` direttamente o tramite
  il ruolo padre. Consentita con override utente concesso su `roles.assign`. Consentita la revoca del proprio
  ruolo `user` (non concede `roles.assign`). Consentita la revoca di `admin` a un ALTRO utente. La protezione
  dell'ultimo super admin regge ("This would leave the system without an active super admin", ruolo intatto).
  Nella suite v1.0 P15 ora e' un controllo (Priya non puo' togliersi `admin`: PASS).
- **A3 PASS.** Revoca diretta di una cella ereditata dal padre: HTTP 422, `{"ok":false,"message":"Inherited
  from A1A4 parent: change it on that role."}`, nessuna riga di `acl_role_permissions` cambiata, non e' un
  rifiuto CSRF. Revoca di una cella posseduta direttamente: 2xx, `ok: true`, cancellata solo quella riga.
  Nella suite v1.0 M12a (nuovo) PASS.
- **A4 PASS.** `q=%` e `q=_` trovano solo gli utenti il cui username contiene davvero `%`/`_`, `q=d_na`
  non trova nessuno, `A4%` e `A4_` trovano l'utente esatto; "Dana" e il frammento email `dana@northwind`
  trovano Dana; `O'Brien` trova l'utente con l'apostrofo senza errori. Nella suite v1.0 U13w ora e' un
  controllo (PASS), le stringhe di iniezione restano senza risultati.

## Ambiguita' (riportate, non decise)

- **A2.DENY.** "Un override su `roles.assign` decide da solo" non e' esercitabile via HTTP nel caso
  negato: con `roles.assign` negato l'attore non puo' usare la rotta di revoca (POST -> 303 verso la home,
  nessun messaggio, ruolo intatto, non CSRF). Osservabile e verificato solo che il rifiuto non viene dalla
  regola di auto-protezione. Se l'autore intendeva altro (es. revoca da parte di un altro utente), va detto.
- **Spiegazione del controllo disabilitato (minore).** Il README UserDetail chiede di spiegare "in the
  marginal note"; la spiegazione e' solo nel `title` del bottone, la cella `rw-anno` della riga e' vuota.
  Stesso schema gia' accettato per l'auto-disattivazione nella riverifica precedente. Da decidere se il
  `title` basta (un `title` su un bottone disabilitato non e' esposto in modo affidabile ai lettori di schermo).

## Errori degli script trovati e corretti (non difetti del modulo)

- I file parziali del run Codex interrotto sono stati rivisti prima dell'uso: ripristinato il fallback
  del token CSRF da file di sessione in `verify-v1.lib.php` (la variante di Codex via `login/magic-link`
  non era stata validata), ripristinata la ricerca della view da sovrascrivere in X05; tenute le
  conversioni L08/U13w/P15 in controlli e il nuovo M12a. `verify-v1-a1a4.run.py` sostituito da
  `verify-v1-a1a4.sh` (riscriveva `app/Config/Database.php` della copia; non serve).
- Primo giro: A2.UI FAIL perche' lo script cercava un bottone "Revoke", mentre il pannello (e il README)
  lo chiamano "Remove". Corretto lo script; ricontrollato sull'HTML servito.

## Ambiente e igiene

- `verify-v1-a1a4.sh`: snapshot `mariadb-dump --skip-dump-date --skip-comments rolewarden_test`, copia di
  `rolewarden-app-test` nello scratchpad (`robocopy /E /XJ`), junction verso il modulo ricreata a mano, cache
  e sessioni svuotate subito, nella `.env` della copia righe password tolte, database `rolewarden_test`,
  base URL `:8070`. Credenziali solo da `RW_DB_*`. Installazione da README su database ricreato prima di
  ogni fase (suite v1.0 dev; A1-A4 dev; A1-A4 + spot check prod), un solo `php -S` alla volta.
- Fine: `rolewarden_test` ripristinato, md5 del dump identico allo snapshot (`b98a95c14161f0186fb51fab93a6e114`,
  0 tabelle come trovato); `rolewarden` mai toccato; 0 `php.exe`; copia rimossa (prima la junction);
  cookie jar e log del server cancellati. Output in `verify-v1.output.txt`, `verify-v1.prod.output.txt`,
  `verify-v1-a1a4.{development,production}.output.txt`. Nessun commit.
- Limite invariato: resa visiva e comportamento Alpine non certificabili senza browser.

---

# Collaudo nota a margine UserDetail (decisione 2026-09-23, voce delle 18:20) - Collaudatore ad Hoc (Claude)

Working tree non committato (non HEAD), in sostituzione di Codex (memoria insufficiente), stesso protocollo:
letti solo SPEC, BRIEF, `_AI-LOG.md`, `docs/design-system/` (README UserDetail), `src/Authorization/Contracts/`,
README e i file di `tests/Integration/`; mai aperti controller, model, view, asset, helper, `RouteRegistrar`,
`Guard.php`, test unitari ne' il diff di `src/`. Markup ricavato solo dall'HTML servito (sonda preliminare).

**Esito: PASS. Decisione approvata.**

| Suite | development | production |
|---|---|---|
| `verify-v1-anno.php` (nuovo) | 24 PASS / 0 FAIL | 24 PASS / 0 FAIL |
| `verify-v1-a1a4.php` (A2.UIa aggiunto) | 50 PASS / 0 FAIL, 1 ambiguita' | 50 PASS / 0 FAIL, 1 ambiguita' |
| `verify-v1.php` | 171 PASS / 0 FAIL | - |
| `verify-v1.prod.php` | - | 12 PASS / 0 FAIL |

## Controlli nuovi (`verify-v1-anno.php`, uguali nelle due fasi, prefisso DEVE/PROD)

- R1-R3: Priya (solo `admin`) sulla propria pagina: Remove del ruolo Admin disabilitato; la `td.rw-anno`
  della stessa riga contiene "You cannot remove your own role that lets you manage roles."; il testo non
  compare su nessun'altra riga. PASS.
- R4-R5: con un secondo ruolo che concede `roles.assign`, Remove attivo su entrambe le righe e nessuna nota
  di blocco. PASS.
- S1-S5: propria pagina: Deactivate e Delete disabilitati, note di entrambe le righe "You cannot disable or
  delete your own account." (al posto di "reversible"), testo assente dalle altre righe. PASS.
- L1-L3: pagina di Dana (unico super admin attivo) vista da Priya: Deactivate e Delete disabilitati, note
  "This would leave the system without an active super admin."; con un secondo super admin attivo i controlli
  tornano attivi e la nota sparisce. PASS.
- N1-N5: Mara vista da Priya: Deactivate e Delete attivi, nota Status "reversible", nota Delete vuota, Remove
  attivo senza nota; Lena (inattiva): Activate con nota "reversible". PASS.
- D1: Dana sulla propria pagina (vale sia l'auto-protezione sia l'ultimo super admin): controlli disabilitati,
  note presenti; mostrata la nota di auto-protezione. PASS.
- X1-X2, SQL: nome ruolo con markup (`Anno <i>x</i> & "q"`) servito escapato; ogni nota a margine e' testo
  escapato dentro `rw-note` (7 pagine); nessun testo SQL. PASS.

## Regressione

- `verify-v1-a1a4.php`: A2.UI invariato (bottone disabilitato + `title`), aggiunto **A2.UIa** che richiede la
  stessa spiegazione nella nota a margine della riga del bottone: PASS in entrambe le fasi. L'ambiguita' minore
  "spiegazione solo nel `title`" del collaudo precedente e' quindi risolta.
- Nessuna regressione su v1.0 e A1-A4.

## Ambiguita' (non decise)

- A2.DENY, invariata: con override negato su `roles.assign` la revoca del proprio ruolo non e' esercitabile via
  HTTP (la rotta richiede quel permesso); verificato solo che il rifiuto non viene dall'auto-protezione.
- Osservazione, non difetto: quando valgono insieme auto-protezione e ultimo super admin (D1) la pagina mostra
  solo il motivo di auto-protezione; il README non dice quale prevalga.

## Ambiente e igiene

- `verify-v1-a1a4.sh` esteso con una fase "marginal notes" su installazione fresca in development e in
  production (una sonda preliminare solo development, stesso harness via `RW_PROBE`). Un solo `php -S` alla volta.
- Fine (entrambi i giri): `rolewarden_test` ripristinato, md5 del dump identico allo snapshot
  (`b98a95c14161f0186fb51fab93a6e114`, 0 tabelle come trovato); `rolewarden` mai toccato; 0 `php.exe`; copia
  rimossa (prima la junction); cookie jar e log del server cancellati. Output in
  `verify-v1-anno.{development,production}.output.txt` e negli output gia' esistenti. Nessun commit.

---

# Collaudo 2026-09-26: ordinamento per ultima attivita' ed etichetta "Last active" (Collaudatore ad Hoc D)

Collaudatore ad Hoc D (Claude), in sostituzione di Codex, stesso protocollo. Letti solo SPEC, BRIEF,
`_AI-LOG.md`, README, `docs/design-system/` (README UsersList e UserDetail, `preview.html`) e i file di
`tests/Integration/`. Mai aperti controller, model, view, lang, asset, helper, `RouteRegistrar`, `Guard.php`,
`tests/Unit/` ne' il diff di `src/`. Working tree non committato.

## Esito

**PASS.** `verify-v1.php` (development) 173 PASS / 0 FAIL; `verify-v1.prod.php` (production) 12 PASS / 0 FAIL.
**Ordinamento per ultima attivita' ed etichetta "Last active" approvati.**

## Controlli cambiati o nuovi in `verify-v1.php`

- Dati: subito dopo il login del proprietario, `users.last_active` e' impostato via SQL su `rolewarden_test`:
  tutti a NULL, poi Dana 2025-06-11, Priya 06-10, Mara 06-09, Theo 06-08, Lena 06-07, bulk01-30 in gennaio
  2025 (un giorno di distanza l'uno dall'altro); Jonas resta senza attivita'. L'ordine atteso si rilegge dal
  database dopo ogni GET, quindi non dipende dai tempi delle richieste (pareggio al secondo visto da B).
- U02: l'intestazione ordinabile con `aria-sort` si chiama "Last active". PASS.
- U02b (nuovo): nell'elenco non compare piu' "Last login" (debug toolbar esclusa). PASS.
- U03 (riscritto): ordine di default per `last_active` decrescente; gli id con attivita' in pagina 1
  coincidono con il prefisso dell'ordine dal database, Dana prima, `aria-sort="descending"`. PASS.
- U04 (riscritto): `sort=asc` con `aria-sort="ascending"`, id con attivita' in ordine crescente come nel
  database, Dana non in pagina 1. PASS. La vecchia richiesta "chi non ha mai fatto login per primo" e' tolta:
  la posizione degli utenti senza attivita' non e' specificata, quindi e' solo stampata (`[INFO]`): in fondo
  nell'ordine decrescente, in testa nel crescente.
- U05 (riscritto): la riga di Jonas (`last_active` NULL, verificato nel database) mostra "Never". PASS.
- U05b (nuovo): nel dettaglio utente il fatto d'intestazione e' `<dt>Last active</dt>` e "Last login" non
  compare. PASS.

## Difetti

Nessuno.

## Ambiguita' (non decise)

- Minore: il README UsersList non dice dove vanno gli utenti senza attivita' ("Never") nei due versi
  dell'ordinamento. Il pannello li mette in fondo in decrescente e in testa in crescente; non giudicato.

## Ambiente e igiene

- Copia di `rolewarden-app-test` nello scratchpad (`robocopy /E /XJ`), junction verso il modulo ricreata a mano,
  `writable/cache` e `writable/session` svuotate. Un solo `php -S` alla volta (:8070), development e production
  via `verify-v1.sh`, credenziali solo da `RW_DB_*`.
- Incidente di procedura, dichiarato: al primo lancio avevo dimenticato di portare il `.env` della copia su
  `rolewarden_test` (lo fanno `verify-v1-a1a4.sh` e `verify-matrix-*.sh`, non `verify-v1.sh`). Quel giro ha
  eseguito `spark migrate` (3 namespace, "Migrations complete") e il seeder di RoleWarden sul database
  `rolewarden`, poi si e' fermato sulle fixture (tabelle assenti in `rolewarden_test`) prima di qualsiasi
  richiesta HTTP. Nessun dato di `rolewarden` e' stato letto; dai soli metadati di `information_schema`:
  14 tabelle, nessuna creata di recente (ultima 2026-09-22), `update_time` NULL su tutte in due letture dopo l'incidente, quindi
  nessuna scrittura registrata da InnoDB dall'avvio del container. Da confermare dall'autore se lo ritiene.
  Corretto il `.env` della copia (database `rolewarden_test`, righe password tolte, base URL :8070) e rilanciato
  da zero: e' il giro riportato qui.
- Fine: `rolewarden_test` ripristinato dallo snapshot, md5 del dump identico (`b98a95c14161f0186fb51fab93a6e114`,
  0 tabelle come trovato); 0 `php.exe`; copia rimossa (prima la junction); log del server e cookie jar
  cancellati. Nel log dell'app solo i rifiuti CSRF voluti, nessun warning/notice/deprecation. Output in
  `verify-v1.output.txt` e `verify-v1.prod.output.txt` (quest'ultimo identico al precedente). Nessun commit.

---

# Collaudo 2026-09-26: posizione degli utenti senza attivita' nell'ordinamento (Collaudatore ad Hoc E)

Collaudatore ad Hoc E (Claude), in sostituzione di Codex, stesso protocollo. Letti solo `_AI-LOG.md`, il
README UsersList di `docs/design-system/` (voce "Sorting", fissata dall'autore il 2026-09-26) e i file di
`tests/Integration/`. Mai aperti controller, model, view, lang, asset, helper, `RouteRegistrar`, `Guard.php`,
`tests/Unit/`. Codice del modulo: working tree committato (HEAD `dafd861`, `src/` pulito).

## Esito

**PASS.** `verify-v1.php` (development) 177 PASS / 0 FAIL (173 precedenti + 4 nuovi); `verify-v1.prod.php`
(production) 12 PASS / 0 FAIL. **Posizione degli utenti senza attivita' conforme alla specifica, approvata.**

## Controlli cambiati in `verify-v1.php`

La riga `[INFO] U04` (stampata, non giudicata) e' sostituita da quattro controlli. L'elenco e' percorso tutto,
pagina per pagina, seguendo il link "Next" del pager, in entrambi i versi: nessuna ipotesi sul numero di utenti
o sulla dimensione della pagina. Gli altri controlli, U03 e U04 compresi, sono invariati.

- U04a: almeno un utente con `users.last_active` NULL, letto dal database dopo il percorso (Jonas). PASS.
- U04b: in entrambi i versi ogni utente non cancellato compare una e una sola volta (conteggio dal database) e
  ogni pagina mantiene `aria-sort` nel verso richiesto (il pager conserva `sort`). PASS.
- U04c: dal piu' recente (default) gli utenti senza attivita' sono le ultime righe dell'ultima pagina, e le
  righe prima di loro seguono esattamente l'ordine `last_active DESC` del database. PASS.
- U04d: dal meno recente (`sort=asc`) gli utenti senza attivita' sono le prime righe di pagina 1, e le righe
  dopo di loro seguono esattamente l'ordine `last_active ASC` del database. PASS.

Controprova dei predicati fuori dal pannello: con l'utente senza attivita' in testa o in mezzo nel verso
decrescente (e in fondo o in mezzo nel crescente) U04c e U04d risultano FAIL.

## Difetti

Nessuno.

## Ambiguita' (non decise)

Nessuna nuova. Quella segnalata da D (posizione degli utenti "Never") e' chiusa dalla specifica.

## Ambiente e igiene

- Copia di `rolewarden-app-test` nello scratchpad (`robocopy /E /XJ`), junction verso il modulo ricreata a mano,
  `writable/cache` e `writable/session` svuotate, log dell'app azzerati. `.env` della copia preparato prima del
  lancio: una sola riga `database.default.database = rolewarden_test`, righe della password tolte, base URL
  `http://localhost:8070/`. `verify-v1.sh` completo (install da zero, development, production), un solo
  `php -S` alla volta su :8070, credenziali solo da `RW_DB_*`.
- Log dell'app: solo i rifiuti CSRF voluti (6 `SecurityException`), nessun warning/notice/deprecation.
- Fine: `rolewarden_test` ripristinato dallo snapshot, md5 del dump identico prima e dopo
  (`e682501ee20ca4e61bb81f7b48315d4a`, 0 tabelle come trovato). `rolewarden` non toccato: dai soli metadati di
  `information_schema`, 14 tabelle, ultima creazione 2026-09-22 07:41, `update_time` NULL su tutte, identici
  prima e dopo. 0 `php.exe`; copia rimossa (prima la junction); log del server e cookie jar cancellati.
  Output in `verify-v1.output.txt` e `verify-v1.prod.output.txt` (quest'ultimo identico al precedente).
  Nessun commit, `_AI-LOG.md` non modificato.
