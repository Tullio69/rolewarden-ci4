# M4 re-verification report

M4 is **not approved**: **340 PASS, 4 FAIL**, representing one newly exposed defect (D3). Previous defects **D1 and D2 are closed**: all five previously failing assertions now pass. The active-children deletion rule passes; former ambiguity 3 is resolved by author decision 4.

Verified HEAD: `91039c7f4e873ac6e0978f0b634f42c4866706f5`.
Runtime: PHP 8.3.11, CodeIgniter 4.7.4, Shield 1.4.1, MariaDB through MySQLi, E_ALL.

## Method and isolation

Tests derive from SPEC, BRIEF-MVP, CLAUDE.md, the 23:35 M4 entry, the 00:10 correction, author decision 4 and this re-verification request. No production implementation bodies, adapters or unit tests were inspected. Reflection was used only to list public ProtectionException methods and properties. The existing mechanical token scan checks the Authorization framework boundary without displaying source; Git checks the original resolver/contracts against `246d031` without displaying source.

The lock was acquired before editing and released after verification. Writes were confined to `tests/Integration/` and `_AI-LOG.md`. Only `rolewarden_test` was selected: its original schema/data were snapshotted, the test database was rebuilt, then restored with an exact comparison. The HTTP server was stopped, the temporary host removed and the sibling app's `.env` hash preserved. No access to `rolewarden`, no credentials written into configuration, no production changes and no commits. HEAD and `src/` remain unchanged.

Artifacts: `verify-m4.ps1` (isolated host and cleanup), `verify-m4.php` (orchestration and HTTP regression), `M4Cases.php` (black-box model cases), and `m4-output.txt` (complete final output).

```powershell
# RW_DB_PASSWORD must already be in the environment.
& .\tests\Integration\verify-m4.ps1 *> .\tests\Integration\m4-output.txt
$LASTEXITCODE # 1: D3 remains reproducible
```

Two previous cache fixtures deleted a parent with live children. These fixtures now delete a permitted leaf, retaining warm/write/read checks; the immediate case uses two holders. Descendant invalidation remains covered by re-parenting, permission deletion and permission rename. Separate new tests assert parent-deletion refusal and database preservation. No previous M3 control was removed.

An intermediate harness assertion assumed `ProtectionException::getMessage()` returned the full language key. No public contract requires that representation: the corrected check verifies the exact reason and its `RoleWarden.protection.roleHasChildren` translation lookup. Intermediate results are not included in final counts.

## Final results

| Group | PASS | FAIL |
| --- | ---: | ---: |
| M4 model/protection cases | 240 | 2 |
| M4 command completion | 1 | 0 |
| HTTP automatic invalidation, including role-held permission rename | 20 | 0 |
| HTTP override-only permission rename reproduction | 4 | 2 |
| Retained M3 controls, metadata and cleanup | 75 | 0 |
| **Total** | **340** | **4** |

The PHP footer reports 339 PASS / 4 FAIL; PowerShell cleanup contributes one additional PASS. Syntax checks and `git diff --check` pass. HTTP E_ALL produced no PHP warnings, notices, deprecations or fatal diagnostics.

Passing coverage:

- All previously passing controls remain passing under the decided deletion contract; all five previous failures now pass. Unbounded PermissionModel updates raise InvalidArgumentException without changing rows, including the isolated slug reproduction. UserModel rejects unbounded activation before changing any row, including the isolated inactive-user reproduction.
- RoleModel, PermissionModel and UserModel reject updates/deletes without an explicit primary-key argument, with broad predicates, an exact `where('id', ...)`, and no predicate. Logical and purge deletion paths are checked. Full database snapshots are identical after every refusal.
- Self, direct and longer hierarchy cycles, missing parents, valid re-parenting and clearing parents retain their controls. All seeded system roles and permissions remain protected, including logical/physical role deletion and existing links.
- The full last-active-super-admin matrix remains passing: user deletion, deactivation, role revoke, role deletion and flag clearing, with no alternative, another active/inactive user, a deleted alternative role, or a second live role on the same user.
- A non-system parent with live children refuses both logical and physical deletion with ProtectionException reason `roleHasChildren`; the matching language key resolves. Mixed live and deleted children also refuse. Every refusal leaves the entire database unchanged.
- Moving the child, soft-deleting it, or physically deleting it makes the non-system parent deletable. Parent logical and physical deletion are both checked, including a parent whose only child is soft-deleted. Deleted child rows remain present; physical parent deletion sets their parent reference to NULL through the foreign key. Leaf deletion succeeds in both modes.
- System parents refuse both modes with `systemRecord`, while their child is active and after it is soft-deleted. The children rule does not make system records deletable.
- Automatic cache invalidation remains passing for permitted role deletion, re-parenting, permission deletion, assignment revocation and user deactivation, immediately and across HTTP requests without logout or manual invalidation.
- Permission rename by primary key invalidates direct role holders, descendant-role holders, holders through a second independent role, and a role holder with a negative override. Both old and new slugs are warmed before the write. A role-held permission rename also passes across HTTP requests in the same authenticated session.
- All M3 functional regression controls pass: precedence, Shield subclass/provider and groups, filters/helpers, uppercase and malformed-slug rejection, manual invalidation, soft deletion and the configured user-table name. The original Resolver, AuthorizationStore and Cache remain unchanged from `246d031`; no framework references outside comments were detected in Authorization.

## D3 — Permission rename leaves an override-only holder's cached answers stale

Four FAIL assertions: old/new slug checks immediately and across HTTP requests. This is a PermissionModel rename defect, not a request to implement the deferred M5 override-write API. The override is an existing fixture; the tested write is `PermissionModel::update($permissionId, ['slug' => $newSlug])`.

Minimal reproduction on an active ordinary user with no role grants:

```php
// Existing fixture: permission $id has slug 'users.view';
// acl_user_permissions grants that permission ID directly to $userId.
$resolver = service('rolewarden');
$resolver->can($userId, 'users.view');    // true, warms cache
$resolver->can($userId, 'users.renamed'); // false, warms cache
(new \RoleWarden\Models\PermissionModel())->update($id, ['slug' => 'users.renamed']);
$resolver->can($userId, 'users.view');    // observed true; expected false
$resolver->can($userId, 'users.renamed'); // observed false; expected true
```

The permission row is renamed successfully and the positive override remains linked to the same ID. Explicit `forgetUser($userId)` makes both answers correct immediately. The HTTP reproduction confirms the same stale answers in a subsequent request while retaining the authenticated user (final fixture user 12, permission 5). Explicit invalidation then corrects both answers in that same session. No production cause is inferred from source and no production fix was attempted.

## Remaining specification ambiguities — undecided, not scored

Original numbering is retained; item 3 is resolved and is no longer an ambiguity.

1. **Direct versus inherited super status.** SPEC does not define inheritance of the super flag. Tests retain the requested direct-assignment contract; product semantics remain to be ratified.
2. **Protection boundary.** The requested checks target models/UserRoles; the log excludes query builder/direct SQL. Foreign-key cascades and other non-model writes still need an explicit product contract. Fixture SQL does not establish an obligation to guard direct writes.
4. **Soft-deleted rows and parent links.** Restoration, re-parenting and inheritance through removed ancestors remain unspecified beyond the decided deletion rule. Tests do not impose new restoration semantics.
5. **Other permission writes.** Dedicated acl_role_permissions and acl_user_permissions write APIs and their automatic invalidation remain deferred to M5. This does not exclude invalidating existing override holders when PermissionModel renames a permission, as demonstrated by D3.
6. **Existing resolver/API questions.** Empty lists (especially canAll([]) for inactive users), super-admin permission enumeration, wildcard expansion during assignment, ID-domain rules and conflicting override representation remain undecided.

## Limits

Sequential black-box acceptance on the recorded runtime, not concurrency/race testing or a PHP/CI4 version matrix. The write checks cover the agreed update/save/delete entry points; arbitrary batch/upsert/callback-disabled APIs and external SQL are not certified. Panel security and user-visible SQL error handling remain later work. SPEC was not edited because the authorized write scope is integration tests and the collaboration log.
