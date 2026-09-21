# M4 verification report

M4 is **not approved**: **224 PASS, 5 FAIL**, representing two defects.
Verified HEAD: `9ca97d574620e737a8ec627fc21773e8033b090e` (`7e688c0` plus the one-line log fix).
Runtime: PHP 8.3.11, CodeIgniter 4.7.4, Shield 1.4.1, MariaDB through MySQLi, E_ALL.

## Method and artifacts

Tests derive from SPEC, the M4 row and definition of done in BRIEF-MVP, CLAUDE.md, the collaboration log (including the 23:35 M4 claims), public contracts and the requested model APIs. Production implementation bodies, adapters and unit tests were not inspected. A mechanical PHP token scan checks the framework boundary without displaying source; Git compares the original resolver/contracts without displaying source.

The existing Codex lock and draft verifier were resumed. The recovered output already had a completion/restore footer; it was rerun, then extended with isolated defect reproductions and HTTP automatic-invalidation checks. Intermediate HTTP fixture failures caused by trying to log in a second user in an existing session were corrected only in the harness: each independent case starts a fresh session, then retains it throughout warm/write/read. Final counts exclude these intermediate fixture failures.

- `verify-m4.ps1`: isolated host copy, inherited environment password, cleanup and sibling `.env` hash check.
- `verify-m4.php`: orchestration, database snapshot/restore, mechanical boundary checks, retained M3 HTTP controls and new M4 HTTP controls.
- `M4Cases.php`: black-box model/protection cases, installed only into the temporary host.
- `m4-output.txt`: complete final run, including row differences for failed no-write assertions.

Run from the repository with `RW_DB_PASSWORD` already in the environment:

```powershell
& .\tests\Integration\verify-m4.ps1 *> .\tests\Integration\m4-output.txt
$LASTEXITCODE # 1 while the reported defects remain
```

The host is a temporary copy of the sibling app under `tests/Integration/.m4-app`, with its Composer loader isolated and `Auth::$userProvider = \RoleWarden\Models\UserModel::class`. Only `rolewarden_test` is selected, emptied after a snapshot, and restored with an exact schema/data comparison. No access to the `rolewarden` database. Passwords are read from the environment, not written into configuration. The HTTP server is stopped, temporary app removed and sibling `.env` hash unchanged at completion. Production files and HEAD remain unchanged; no commits.

## Results

| Group | PASS | FAIL |
| --- | ---: | ---: |
| M4 model/protection cases | 133 | 5 |
| M4 command completion | 1 | 0 |
| Additional HTTP automatic invalidation | 15 | 0 |
| Retained M3 controls, metadata and cleanup | 75 | 0 |
| **Total** | **224** | **5** |

The PHP footer reports 223 PASS / 5 FAIL; the PowerShell cleanup contributes the remaining PASS. All original M3 functional controls are retained, including uppercase rejection, hierarchy/overrides, Shield groups, filters/helpers, manual invalidation in the same session, soft deletion and configured user table name. The M3 whole-Authorization unchanged check is appropriately narrowed for M4: Resolver, AuthorizationStore and Cache are unchanged since `246d031`; new protection classes are allowed. No CodeIgniter/Shield references outside comments were detected in Authorization.

Passing M4 coverage:

- Saving self-parent, direct and longer hierarchy cycles raises `ProtectionException::HIERARCHY_CYCLE`; missing-parent insert/update raises `PARENT_MISSING`. Full database snapshots remain identical on refusals. Valid re-parent and clearing the parent work.
- All three seeded system roles resist both logical and physical deletion; all twelve seeded system permissions resist deletion, with rows and existing role/user links intact (`SYSTEM_RECORD`). Non-system leaf role and permission deletion succeed and their resulting row state is checked.
- Five last-super-admin operations: user delete, user deactivation, assignment revoke, non-system super role delete and clearing its flag. Each refuses with `LAST_SUPER_ADMIN` and an unchanged full database when alone, when the other user is inactive, or when the other active user holds only a soft-deleted super role. Each succeeds with another active super admin.
- A sole user with two live super roles still cannot be deleted/deactivated; revoking/deleting/clearing one role succeeds because the second remains.
- Automatic cache invalidation after permitted role deletion, re-parent, permission deletion, role revoke and user deactivation: warm resolver answers change immediately without `forget`. Descendant effects and unaffected users are checked. All five also pass warm/write/read across HTTP requests in the same authenticated session, without logout or manual invalidation, including after the host user-table rename.
- Unbounded RoleModel update/delete and unbounded UserModel/PermissionModel delete refuse and preserve the database. Update defects are below.
- All four requested `RoleWarden.protection.*` language keys resolve to nonempty translated strings. HTTP E_ALL produces no PHP warnings/notices/deprecations/fatal diagnostics.

## Defects and minimal reproductions

### D1 — PermissionModel accepts updates without an explicit primary-key argument

Three FAIL assertions. The broad `where('is_system', 0)->update(null, ['is_system'=>1])` does not raise the claimed `InvalidArgumentException`. An isolated, existing non-system permission demonstrates an actual write, not merely a no-op:

```php
// Fixture: an existing non-system permission with slug m4.action191.
(new \RoleWarden\Models\PermissionModel())
    ->where('slug', 'm4.action191')
    ->update(null, ['slug' => 'm4.action191changed']);
```

Expected: `InvalidArgumentException`, unchanged database. Observed: no exception; the stored slug changes and `updated_at` is set. The final output includes the before/after row. This violates M4 log claim (3) and the explicit acceptance requirement. The initial broad case had no matching non-system permission, which is why its no-write assertion passes; the isolated case removes that uncertainty.

### D2 — UserModel rejects an unbounded update only after changing stored data

Two FAIL assertions. Broad reproduction:

```php
// Fixture: at least one inactive user.
try {
    (new \RoleWarden\Models\UserModel())
        ->where('active', 0)->update(null, ['active' => 1]);
} catch (\InvalidArgumentException $e) {
    // The exception occurs, but matching users have already become active.
}
```

Expected: exception before any database change. Observed: the exception type is correct, but six matching users become active and `updated_at` changes. A fresh inactive user isolates the same issue:

```php
// $id is the freshly inserted inactive user's primary key.
(new \RoleWarden\Models\UserModel())
    ->where('id', $id)->update(null, ['active' => 1]);
```

This also throws `InvalidArgumentException` while leaving that user's `active = 1`. A `where()` clause does not supply the explicit model primary-key argument required by the claimed contract. The snapshots show only the expected affected user rows, not harness-induced changes. No implementation cause is inferred or production fix attempted. Point updates using `update($id, ...)` pass the protection matrix.

## Specification ambiguities — undecided, not scored as defects

1. **Direct versus inherited super status.** SPEC says a user possesses a flagged role without defining inheritance of that flag. The M4 log and ProtectionStore count only direct assignments to live super roles. Tests check the requested direct-assignment contract; product semantics remain to be ratified.
2. **Protection boundary.** SPEC describes interface protection, while the requested checks target models/UserRoles. The log excludes query builder/direct SQL; foreign-key cascades and other non-model writes need an explicit product contract. Fixture SQL is not evidence that such writes are required to be guarded.
3. **Deleting a parent with active children.** The earlier 00:25 log says M4 should refuse this, whereas 23:35 describes soft-deleted parents remaining linked and the resolver stopping at the missing parent. The cache tests observe permitted parent deletion and correct denial for descendants. Which deletion behavior is required remains unresolved; no arbitrary extra reason constant or refusal was imposed.
4. **Soft-deleted rows and parent links.** The later log retains `parent_id`; SPEC preserves assignment history but does not define restoration, re-parenting or inheritance through removed ancestors. This is not treated as an independent failure.
5. **Other permission writes.** SPEC broadly requires invalidation on every relevant write; the log defers dedicated `acl_role_permissions` and `acl_user_permissions` write APIs to M5. Automatic invalidation is verified only for the five requested M4 paths. M3's direct-SQL/manual-forget controls remain regression checks, not proof of automatic invalidation for those deferred APIs.
6. **Existing resolver/API questions.** Empty lists (especially `canAll([])` for inactive users), super-admin permission enumeration, wildcard expansion in assignment, ID-domain rules and conflicting override representation remain outside this milestone's decisions. No new interpretation was chosen to obtain passing results.

## Limits

This is a sequential black-box acceptance run on the recorded runtime, not a concurrency/race test or PHP/CI4 version matrix. It does not certify arbitrary batch/upsert/callback-disabled APIs or external SQL writes. Panel security and user-visible SQL error handling remain later milestone work. SPEC was not edited because the authorized write scope is integration tests and the collaboration log.
