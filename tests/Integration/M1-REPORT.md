# Independent M1 re-verification

**M1 APPROVED: 79 checks passed, 0 failed**, under the decisions supplied by the author. The launcher additionally confirms sibling dotenv integrity and temporary-app removal. Full commands, exit codes and assertions: [m1-output.txt](m1-output.txt).

Verified 2026-09-21 (Europe/Rome), HEAD `4fc333f`. Source comparison with `80a60ba` returned no differences. Stack: PHP 8.3.11, CI4 4.7.4, supplied installed Shield dependencies, MariaDB 11.8.9. Expectations came from SPEC, BRIEF-MVP, the 00:25 log decisions, README and the current user request. No module implementation, module config or seeder source was opened or modified.

## Results per point

Each database check ran with both `acl_` and `xx_`, each starting from an empty database.

| Point | Result | Captured evidence |
| --- | --- | --- |
| 1. Five MVP tables | PASS | `PASS 1/2 acl_ exact five MVP tables`: roles, permissions, role_permissions, user_roles, user_permissions. |
| 2. Configurable prefix | PASS | `PASS 1/2 xx_ exact five MVP tables`; no acl_ module tables. Default has no dotenv override; custom prefix uses the isolated dotenv. |
| 3. Unique slugs | PASS | Role and permission duplicates rejected with `database_error_code:1062`. |
| 4. Foreign keys | PASS | User, permission and role deletion each cascade to both relevant bridges; orphan inserts rejected with `1452`; parent FK exists and deletion leaves no dangling references. No stronger parent-delete semantics asserted. |
| 5. Soft delete | PASS | `soft-delete timestamp preserves role and assignments`; `soft-deleted slug remains reserved` with error `1062`. Schema support tested, not a future model method. |
| 6. Overrides | PASS | `granted=1 stored`, `denied=0 stored`; duplicate user/permission with opposite outcome rejected with `1062`. No binary-domain DB constraint assertion. |
| 7. Rollback/remigration | PASS | Settings=1, Shield=2, RoleWarden=3. Forced and README-literal rollback both leave `module_migration_rows:0`, `framework_migration_rows_unchanged:true` and exactly the framework baseline tables. Both remigrations pass. |
| 8. Seed | PASS | Exact counts `3/12/12/0/0`; full rows identical after second seed; exact roles, flags, permission slugs and admin assignments pass. |
| 9. Module output | PASS in revised scope | All executed migration, rollback and seed commands pass `no SQL/query/secret output`; only ordinary framework progress and class names observed. Framework terminal failure diagnostics and exit code behavior are excluded. HTTP verification starts at M5. |

Exact seed checked:

```text
admin       is_system=1 is_super_admin=0 permissions=12
super-admin is_system=1 is_super_admin=1 permissions=0
user        is_system=1 is_super_admin=0 permissions=0
users.view users.create users.update users.delete users.activate
roles.view roles.create roles.update roles.delete roles.assign
permissions.view permissions.override
```

## Literal README execution

The runner extracts the four installation/seed command lines directly from README and executes their quoted text through PowerShell in the isolated app. It tests both `php spark migrate:rollback -b 2 -f` and the README command without `-f`, substituting the documented `<previous batch>` placeholder with observed batch `2` and supplying `y` on stdin. Original namespace quoting is retained. No namespace option is used for rollback.

After each rollback, `php spark migrate -n RoleWarden` recreates the five tables and five migration records. The preserved baseline is:

```text
auth_groups_users auth_identities auth_logins auth_permissions_users
auth_remember_tokens auth_token_logins migrations settings users
```

Assertions compare all framework migration rows with their pre-module snapshot, plus the table list. Settings is framework-owned. This fresh-install test does not certify preservation of arbitrary populated host-app data; that wider scenario belongs to M6.

## Residual defects and ambiguities

No residual M1 defect observed. D2 is closed by the executable batch procedure. D1 is closed for M1 by the user's explicit scope decision, not by a change to framework diagnostics. Output checks cover the successful paths executed here; they are not source inspection or an HTTP security audit.

None of the five previous M1 ambiguities remains open under the supplied decisions. Documentation synchronization remains: carry the decisions into the authoritative specification and re-export docs (HTTP error scope, rollback baseline, reserved deleted slugs, exact schema/seed contract). These files were outside the permitted write scope. Future checks: binary-domain validation in M2/M3, active-child deletion protection in M4, HTTP disclosure from M5.

## Reproduction and safety

With the existing database service available and rolewarden_test empty, set `RW_DB_PASSWORD` to the supplied local test password, then run from the repository:

```powershell
& .\tests\Integration\verify-m1.ps1 *> .\tests\Integration\m1-output.txt
$LASTEXITCODE # 0
```

Optional connection settings: RW_DB_HOST, RW_DB_PORT, RW_DB_USER; the database is fixed to rolewarden_test. The launcher copies the sibling app into tests/Integration/.m1-app and junctions its installed vendor directory. Only the copied dotenv is edited. Fixture DML is rolled back and test-created tables removed. Nonempty databases are refused.

Initial preflight found only rolewarden_test.migrations with zero rows; the runner refused it before migrations. After rechecking that exact state, only this empty table was removed to meet the requested empty starting condition. Docker engine access was denied by the environment; verification used the available host MySQL connection at 127.0.0.1:3306. No query or connection targeted rolewarden.

Final output:

```text
PASS temporary app .env restored byte-for-byte
PASS rolewarden_test restored to initial empty state
RESULT 79 checks, 0 failures
PASS safety sibling .env unchanged; temporary app removed
```

Only tests/Integration/ and _AI-LOG.md changed. No source fixes or commits. Approval covers M1 on the tested stack, not later MVP milestones or the full supported-version matrix.
