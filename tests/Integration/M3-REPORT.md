# M3 Shield integration verification

Target: `41db1d15eba787d8a5a89d46487fff961ff793fa`.

**M3 NOT APPROVED: 70 PASS, 3 FAIL, one production defect.**

The PHP verifier reports 69 PASS and 3 FAIL; the PowerShell cleanup adds one PASS. All functional controls ran. Runtime: PHP 8.3.11, CodeIgniter 4.7.4, Shield 1.4.1. Full evidence: [m3-output.txt](m3-output.txt).

## Reproduction

With `RW_DB_PASSWORD` already set in the process environment:

```powershell
& tests/Integration/verify-m3.ps1
```

Optional connection overrides: `RW_DB_HOSTNAME`, `RW_DB_PORT`, `RW_DB_USERNAME`. Connection coordinates otherwise come from the sibling application's configuration, in memory; the password comes exclusively from `RW_DB_PASSWORD`. No credentials are written into files. Exit code 1 currently reflects the three acceptance failures below.

## D1 — Entity can() accepts uppercase permission slugs

Requirement: SPEC, “Formato dei permessi”, requires lowercase `area.azione`; the M3 handoff requires `InvalidArgumentException` for invalid slugs. Case-insensitive group input is a separate, explicitly permitted contract.

Minimal reproduction, after the one-line host provider configuration, with an active user who has `users.view` through a role:

```php
$user = auth()->getProvider()->findById($userId);
$user->can('USERS.view'); // actual: true; expected: InvalidArgumentException
service('rolewarden')->can($userId, 'USERS.view'); // InvalidArgumentException
```

Observed by the HTTP probe: `{"entity":true,"resolver":"InvalidArgumentException"}`. No implementation was inspected to infer the cause.

Three failing controls reproduce non-rejection through the entity for an ordinary active user, a super admin and an inactive user. In each eight-case malformed-input control only uppercase input fails; wildcard, final LF, missing separator, empty input, NUL and mixed valid/wildcard lists are rejected correctly. The inactive user still denies permissions; this finding is an API validation inconsistency, not evidence of inactive-user access or a negative-override bypass.

No production fix was made. If uppercase normalization is desired for permission input, that requires an explicit specification decision; this report does not silently adopt it.

## Verified behavior

- The host selects `RoleWarden\Models\UserModel` with one line. The provider returns a subclass of Shield User. The shared `rolewarden` service is a Resolver.
- Database-backed precedence: inactive user denies despite super-admin and positive override; negative override beats super admin and a granted role permission; positive override grants; super admin grants otherwise unknown valid permissions; direct, parent and grandparent role permissions work; default deny works.
- Variadic entity `can()` uses any-of semantics. Invalid-input checks run for ordinary, super-admin and inactive users, with D1 as the only discrepancy.
- `getGroups()` contains the directly assigned non-deleted role, excludes ancestors and deleted roles; `inGroup()` accepts mixed-case input and does not report an inherited parent group.
- Actual HTTP routes use `can:users.view,users.update`: guest redirects to login, either granted argument allows the controller, denied access does not execute it and carries the translated `RoleWarden.accessDenied` message. Shield's `permission` alias remains `CodeIgniter\Shield\Filters\PermissionFilter`.
- `helper('rolewarden')` exposes `can`, `can_any`, `can_all`, `permissions`: guest answers are false/empty; authenticated answers and the effective ordinary-user permission list match the fixtures.
- Separate HTTP requests share the Shield session. SQL permission changes followed by public `forgetUser()` or `forgetRole()` take effect without logout, including parent-role revocation for children and grandchildren, route denial after revocation, and regrant.
- Soft-deleted super-admin/direct roles grant nothing and are absent from group lists; deleting an ordinary parent removes its inherited permission.
- Renaming Shield's users table to `m3_people` and changing only the host `Auth::$tables['users']` mapping preserves entity lookup and permission resolution. Deactivating a user in that table denies after invalidation.
- `src/Authorization` is unchanged since `246d031`. Mechanical token scanning finds no framework references outside comments. Production source remains unchanged from the target commit.
- HTTP checks ran with E_ALL; the server log contains no PHP warning, notice, deprecation or fatal diagnostic. PHP lint and `git diff --check` passed.

## Isolation and harness corrections

The runner follows `verify-m1.ps1`: copy the sibling app into `tests/Integration/.m3-app`, use its existing vendor directory through a junction, and clean up after execution. The sibling `.env` is not copied and its hash is checked afterward. Only temporary host configuration is edited.

Runtime validation exposed draft-harness issues: Composer's app class map resolved host configuration from the sibling rather than the copy; PHP's HTTP router needed an explicit isolation bootstrap; the permissions fixture incorrectly assumed a `name` column. These were corrected solely in the verifier. Composer's App/Config mappings now point to the temporary copy, and configuration preflight verifies `rolewarden_test` before migrations. They are harness errors, not production defects; the final output contains the completed corrected run.

Only `rolewarden_test` was selected. Table definitions and rows were snapshotted, the database emptied before migrations, and the original schema/data restored and compared successfully in `finally`. Views and triggers are refused before destructive operations. The original `rolewarden` database was never selected or modified. The temporary server stopped, vendor junction and app were removed, and sibling `.env` remained unchanged. No commit was made.

## Specification ambiguities — not scored as defects

The earlier log's unresolved questions remain separate:

1. `canAll([])` for inactive users versus “inactive denies everything”; zero-argument entity/helper semantics.
2. Inheritance of a parent's super-admin flag; ordinary ancestors are used here.
3. Whether super-admin `permissions()` lists explicit permissions or represents its open-ended grant set.
4. Where assignment wildcards are expanded, including a wildcard reaching the database store.
5. Resolution of opposing overrides before persistence: both cannot coexist in the unique user/permission mapping.
6. Invalidation of former assignees after removal: `forgetRole()` cannot discover an already removed association.

No specification document was changed because the authorized write scope excludes it. Shield `addGroup`/`addPermission` writes, automatic invalidation in future write paths, M4 protections, the M5 panel and the complete runtime compatibility matrix are outside this M3 approval scope.

## Reading boundary and provenance

Assertions derive from SPEC, BRIEF's M3/definition of done/protocol, CLAUDE, the collaboration log and public authorization contracts. Existing integration drafts and the requested M1 runner were inspected. No M3 implementation under Adapters, Entities, Models, Filters, Helpers, Services or Registrar, and no unit tests, were read.

The previous session already disclosed an accidental Resolver signature extraction with body context; that provenance limitation remains. This resumed session did not inspect Resolver bodies. Boundary scanning processes source mechanically without displaying it. Runtime host configuration is inspected only to prepare and verify isolation.

Only `tests/Integration/` and `_AI-LOG.md` were modified.
