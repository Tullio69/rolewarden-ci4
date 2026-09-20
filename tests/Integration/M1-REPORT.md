# Independent M1 verification

Target: commit `80a60ba`, verified on 2026-09-20 using PHP 8.3.11 and the supplied host app's installed CI4/Shield dependencies. Module implementation files were neither opened nor modified. Expectations came from the two requested SPEC sections, BRIEF-MVP, CLAUDE.md and the user's explicit M1 acceptance criteria. The coordination log was read as required.

**Outcome: M1 is not ready for acceptance. 55 checks: 47 passed, 8 failed.** The failures are repeated cases of two issues, not eight distinct defects. Full command output, exit codes and assertions are in [m1-output.txt](m1-output.txt).

## Method and reproduction

Reproducible Spark scripts were chosen instead of PHPUnit: this acceptance test needs fresh CLI processes, real MariaDB constraints, changed dotenv settings, complete migration/rollback cycles and captured production/development console failures. It does not use a PHPUnit database refresh trait or a substitute SQLite schema. No dependency was added.

Run from the module repository in PowerShell:

```powershell
docker compose up -d
# Set RW_DB_PASSWORD to the supplied local MariaDB password in this shell.
& .\tests\Integration\verify-m1.ps1 *> .\tests\Integration\m1-output.txt
$LASTEXITCODE
```

Optional environment variables: `RW_DB_HOST`, `RW_DB_PORT`, `RW_DB_USER`; defaults are loopback, 3306 and root. The launcher also accepts `-App` with the host app path.

The database is fixed to `rolewarden_test`. **It must be empty and exclusively available:** the runner refuses a nonempty database and restores the initially empty state in `finally`. It never connects to the `rolewarden` database. Exit 1 means at least one failed assertion; exit 0 means all passed.

The sibling app is outside this session's writable root. The launcher therefore copies its app files, Spark, web root and `.env` into `tests/Integration/.m1-app`, and junctions the same installed vendor directory. This runs the supplied app configuration/dependencies and the path-linked module in an isolated app root. The custom-prefix test edits the copied `.env` to `rolewarden.tablePrefix = "xx_"`; the default test removes that setting. The copied `.env` is restored byte-for-byte, then the temporary app and junction are removed. The original app `.env` is never modified. This is a disclosed difference from running directly in the sibling directory.

The SQL checks inspect persisted records and database metadata, not PHP implementation. Metadata supplies required fixture display fields and identifies the self-referencing parent column because the spec does not name it. Expected outcomes are not inferred from those metadata values. Fixture DML runs inside rolled-back transactions. No resolver, Shield authorization integration or panel behavior was tested.

## Results by requested point

All schema/data checks below were run with both `acl_` and `xx_`.

| Point | Result | Observed output / evidence |
| --- | --- | --- |
| 1. Default migrations | PASS | `PASS 1/2 acl_ exact five MVP tables`; `acl_roles`, `acl_permissions`, `acl_role_permissions`, `acl_user_roles`, `acl_user_permissions` exist. |
| 2. Configurable prefix | PASS | `PASS 1/2 xx_ exact five MVP tables`; exactly the five `xx_` module tables, no `acl_` tables. Dotenv restoration passed. |
| 3. Unique slugs | PASS | Duplicate role and permission inserts rejected with MariaDB error code `1062`. |
| 4. Foreign keys | PASS | Physical user deletion removed both user bridges; permission deletion removed role/user permission bridges; role deletion removed role permission/user role bridges. Orphan bridge inserts rejected with `1452`. Parent deletion preserved the child with `parent_id=null`; no dangling parent references. |
| 5. Soft-delete column | PASS | Setting `deleted_at` retained the role and its user-role and role-permission records. This tests schema support, not a future model's delete method. |
| 6. User override | PASS for stated valid values and uniqueness | `granted=1` and `granted=0` persisted; a second row for the same user/permission with the opposite outcome was rejected with `1062`. Domain-enforcement ambiguity below. |
| 7. Rollback and remigration | FAIL on fresh installation; PASS with preexisting Shield batches | Fresh `migrate --all` followed by the exact requested rollback left `["migrations"]`, deleting Shield too. Remigration passed for both prefixes. When Shield and Settings were migrated in earlier batches, rollback removed only RoleWarden and preserved their tables. |
| 8. Seeder | PASS | Two runs produced identical rows and counts: `roles=3`, `permissions=12`, `role_permissions=12`, `user_roles=0`, `user_permissions=0`. Exact role flags, permission slugs, lowercase format and admin's exact permission set all passed. |
| 9. Failure-path output | FAIL | Missing-table seed and unreachable-DB migration exposed database exceptions in both development and production. All four failed commands nevertheless returned process exit code `0`. |

Seeder role output, identical under both prefixes:

```text
admin       is_system=1 is_super_admin=0 permissions=12
super-admin is_system=1 is_super_admin=1 permissions=0
user        is_system=1 is_super_admin=0 permissions=0
```

The exact permission set checked was:

```text
users.view users.create users.update users.delete users.activate
roles.view roles.create roles.update roles.delete roles.assign
permissions.view permissions.override
```

## Defects and acceptance blockers

### D1: SQL/database internals are visible on CLI failure

On an empty test database:

```text
$ php spark db:seed RoleWarden\Database\Seeds\RoleWardenSeeder
exit=0
[CodeIgniter\Database\Exceptions\DatabaseException]
Table 'rolewarden_test.acl_roles' doesn't exist
at SYSTEMPATH\Database\BaseConnection.php:863
...
[mysqli_sql_exception]
```

Development additionally prints the SQL query and a stack trace containing module paths. Production still prints the table name, driver exception and framework file locations. With the database port set to the unreachable local port 1:

```text
$ php spark migrate --all
exit=0
[CodeIgniter\Database\Exceptions\DatabaseException]
Unable to connect to the database.
Main connection [MySQLi]: ...
at SYSTEMPATH\Database\BaseConnection.php:606
```

This violates the explicit rule against displaying SQL errors in any environment/path. The unreachable-DB failure occurs during framework migration setup; the black-box test does not assign its root cause to a module migration. The visible behavior still fails the requested acceptance criterion. HTTP error rendering was not tested.

Related operational observation: exit code 0 on these failures makes a success-only shell check unreliable. The suite therefore inspects output and persisted state. Nonzero failure status was not separately specified, so this is reported as an observation, not an additional counted defect.

### D2: The requested rollback command is not namespace-isolated

Installed Spark help lists `-b` and `-f` for `migrate:rollback`; it does not list `-n`. The actual command acts on the last migration batch. Following a fresh `migrate --all`, Shield, Settings and RoleWarden share a batch, and output includes:

```text
$ php spark migrate:rollback -n RoleWarden
Rolling back migrations to batch:  0
... (RoleWarden) ...
... (CodeIgniter\Settings) ...
... (CodeIgniter\Shield) ...CreateAuthTables
FAIL 7 acl_ rollback leaves only original Shield tables and migrations
{"tables":["migrations"],"module_migration_rows":0}
```

The same failure occurs with `xx_`, including a second cycle after successful remigration. This is an acceptance-procedure defect with destructive consequences, not evidence that RoleWarden's down migrations directly delete Shield tables. No module residues remained.

The supplemental existing-app setup created Shield batch 1, Settings batch 2, RoleWarden batch 3. The same rollback returned to batch 2 and preserved Shield and Settings for both prefixes. Thus M1 rollback works under that batch layout, but the proposed command does not guarantee isolation. The installation/rollback contract needs an explicit batch precondition or a separately agreed safe procedure; the failing fresh-install assertion has not been weakened to make it pass.

## Specification ambiguities requiring an author decision

1. **Rollback scope and baseline.** Define whether fresh installs and existing-app installs must both preserve Shield during module rollback, and document a supported procedure. `settings` is created by the framework Settings migrations during `--all` and remains alongside Shield in the existing-app case. The phrase "only Shield tables + migrations" should explicitly account for this dependency; it is not a RoleWarden leftover.
2. **Binary override domain.** The tested database accepts `granted=2` under both prefixes. Valid 0/1 values and uniqueness pass, but the documents do not say whether values outside that domain must be rejected by the database in M1 or by a later write API. No interpretation was selected to declare this extra input valid. Decide the enforcement layer before adding a rejection assertion.
3. **Parent deletion semantics.** The minimum requested invariant, no dangling references, passes. Physical deletion currently keeps the child with a null parent. The spec does not choose between detachment, rejection or cascading deletion, nor describe authorization semantics for a soft-deleted parent. No assertion imposes one of those choices.
4. **Slug reuse after soft deletion.** Uniqueness of live duplicate fixtures is verified; the specification does not define whether a soft-deleted role reserves its slug. That extra behavior is not certified.
5. **Schema/seed contract completeness.** The agreed role names, 12 permissions, super-admin column name and numeric override encoding are explicit in this verification request but are not fully enumerated in the allowed SPEC sections. They should be incorporated into the authoritative document before future independent verification. No documentation choice was silently made here.

No source fix, spec rewrite or commit was made. The coordination lock was released and the findings were recorded in `_AI-LOG.md`.
