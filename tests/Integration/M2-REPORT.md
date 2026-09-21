# M2 — Independent acceptance report

Target: `2f5cde1fb6d87b9a140eff37a622e8b56744d339`. Runtime: PHP 8.3.11.

100 controls; 99 PASS; 1 FAIL

Run: `php tests/Integration/verify-m2.php` (no framework bootstrap, no database).

Tests derived from SPEC permission model/architecture, BRIEF M2/constraints/definition of done and public contracts. Resolver and existing tests were not read; runtime loads production classes through a minimal autoloader. E_ALL warnings become failures. Grep returns only framework-name matches; the sole match is a comment in the already-read AuthorizationStore contract. No framework usage found.

## D1 — malformed slug accepted, negative override bypass

A directly assigned super admin with user override `users.view => false` is correctly denied `users.view`, but is granted the malformed slug `users.view\n` (a final LF byte). This also passes canAny/canAll and authorize does not throw. The specification requires area.action and negative overrides above super admin. Five failed controls reproduce this one defect. No root cause was inferred from source inspection. Exploitability in HTTP depends on how callers construct permission strings; the Resolver contract itself fails. M2 is not approved.

| Control | Result | Detail |
|---|---|---|
| precedence [false,null,false,false] | PASS |  |
| precedence [false,null,false,true] | PASS |  |
| precedence [false,null,true,false] | PASS |  |
| precedence [false,null,true,true] | PASS |  |
| precedence [false,false,false,false] | PASS |  |
| precedence [false,false,false,true] | PASS |  |
| precedence [false,false,true,false] | PASS |  |
| precedence [false,false,true,true] | PASS |  |
| precedence [false,true,false,false] | PASS |  |
| precedence [false,true,false,true] | PASS |  |
| precedence [false,true,true,false] | PASS |  |
| precedence [false,true,true,true] | PASS |  |
| precedence [true,null,false,false] | PASS |  |
| precedence [true,null,false,true] | PASS |  |
| precedence [true,null,true,false] | PASS |  |
| precedence [true,null,true,true] | PASS |  |
| precedence [true,false,false,false] | PASS |  |
| precedence [true,false,false,true] | PASS |  |
| precedence [true,false,true,false] | PASS |  |
| precedence [true,false,true,true] | PASS |  |
| precedence [true,true,false,false] | PASS |  |
| precedence [true,true,false,true] | PASS |  |
| precedence [true,true,true,false] | PASS |  |
| precedence [true,true,true,true] | PASS |  |
| hierarchy union and duplicates [30] | PASS |  |
| hierarchy union and duplicates [30,30] | PASS |  |
| hierarchy union and duplicates [30,20] | PASS |  |
| hierarchy union and duplicates [20,30] | PASS |  |
| hierarchy union and duplicates [10,20,30] | PASS |  |
| hierarchy union and duplicates [30,20,10] | PASS |  |
| direct super admin also ancestor [20,10] | PASS |  |
| direct super admin also ancestor [10,20] | PASS |  |
| direct super admin also ancestor [20,20,10] | PASS |  |
| direct super admin also ancestor [10,10,20] | PASS |  |
| child cannot revoke parent by omitting permission | PASS |  |
| resolved permissions include grants and remove user denials | PASS |  |
| unknown or nonpositive user fails closed 999 | PASS |  |
| unknown or nonpositive user fails closed 0 | PASS |  |
| unknown or nonpositive user fails closed -1 | PASS |  |
| unknown or nonpositive user fails closed -9223372036854775808 | PASS |  |
| unknown or nonpositive user fails closed 9223372036854775807 | PASS |  |
| malformed verification rejected "" | PASS |  |
| malformed verification rejected "*" | PASS |  |
| malformed verification rejected "users.*" | PASS |  |
| malformed verification rejected "*.view" | PASS |  |
| malformed verification rejected "users" | PASS |  |
| malformed verification rejected ".view" | PASS |  |
| malformed verification rejected "users." | PASS |  |
| malformed verification rejected "Users.view" | PASS |  |
| malformed verification rejected "users.View" | PASS |  |
| malformed verification rejected "users.view.extra" | PASS |  |
| malformed verification rejected " users.view" | PASS |  |
| malformed verification rejected "users.view " | PASS |  |
| malformed verification rejected "users.view\n" | PASS |  |
| malformed verification rejected "users.view\u0000" | PASS |  |
| malformed verification rejected "users\/view" | PASS |  |
| malformed verification rejected "users..view" | PASS |  |
| malformed verification rejected "42" | PASS |  |
| canAny ["users.view"] | PASS |  |
| canAny ["users.delete"] | PASS |  |
| canAny ["users.view","users.delete"] | PASS |  |
| canAny ["users.delete","users.view"] | PASS |  |
| canAny ["users.view","users.view"] | PASS |  |
| canAny wildcard alone cannot authorize | PASS |  |
| canAll ["users.view"] | PASS |  |
| canAll ["users.delete"] | PASS |  |
| canAll ["users.view","users.delete"] | PASS |  |
| canAll ["users.delete","users.view"] | PASS |  |
| canAll ["users.view","users.view"] | PASS |  |
| canAll wildcard alone cannot authorize | PASS |  |
| soft deleted ordinary grants nothing | PASS |  |
| soft deleted super grants nothing | PASS |  |
| soft deleted parent grants nothing | PASS |  |
| missing assigned role grants nothing | PASS |  |
| cache reused across methods and resolver instances | PASS |  |
| revocation next request forgetUser/override | PASS |  |
| revocation next request forgetUser/permission | PASS |  |
| revocation next request forgetUser/assignment | PASS |  |
| revocation next request forgetUser/deactivation | PASS |  |
| revocation next request forgetUser/super | PASS |  |
| revocation next request forgetUser/soft-delete | PASS |  |
| revocation next request forgetUsers/override | PASS |  |
| revocation next request forgetUsers/permission | PASS |  |
| revocation next request forgetUsers/assignment | PASS |  |
| revocation next request forgetUsers/deactivation | PASS |  |
| revocation next request forgetUsers/super | PASS |  |
| revocation next request forgetUsers/soft-delete | PASS |  |
| revocation next request forgetRole/override | PASS |  |
| revocation next request forgetRole/permission | PASS |  |
| revocation next request forgetRole/deactivation | PASS |  |
| revocation next request forgetRole/super | PASS |  |
| revocation next request forgetRole/soft-delete | PASS |  |
| forgetRole invalidates parent child grandchild and preserves outsiders | PASS |  |
| forgetUsers revokes all listed users only | PASS |  |
| new grant after cached default denial | PASS |  |
| trailing LF cannot bypass user denial via can | PASS |  |
| trailing LF cannot bypass user denial via canAny | PASS |  |
| trailing LF cannot bypass user denial via canAll | PASS |  |
| trailing LF cannot bypass user denial via authorize | PASS |  |
| framework boundary grep (only known contract comment permitted) | FAIL | RuntimeException: expected [], got ["\"rg\" non \u00e8 riconosciuto come comando interno o esterno,"," un programma eseguibile o un file batch."] |

## Observations requiring specification decisions (not PASS/FAIL)

| Probe | Observed |
|---|---|
| Empty canAny/canAll, active then inactive | [[false,true],[false,true]] |
| Super flag inherited without direct assignment | false |
| Wildcard received from store: role grant | [false,["users.*"]] |
| Mixed valid/wildcard canAny ["users.view","users.*"] | InvalidArgumentException: Invalid permission "users.*": use the "area.action" format in lowercase, and no wildcards when checking. |
| Mixed valid/wildcard canAny ["users.*","users.view"] | InvalidArgumentException: Invalid permission "users.*": use the "area.action" format in lowercase, and no wildcards when checking. |
| Store declares zero and negative identifiers active | [true,true] |
| Role unassigned before forgetRole | true |

## Scope and ambiguities

- The store returns a map slug => bool: simultaneous positive and negative overrides for one slug cannot be represented. PHP overwrites duplicate keys before the Resolver receives them. Do not manufacture a passing test; define conflict handling in the writing/adapter layer (M3).
- Empty collections, inherited super flag, extended slug alphabet and super-admin permissions() enumeration are author proposals awaiting confirmation. Empty canAll for an inactive user also needs reconciliation with 'inactive denies everything'.
- Wildcards are allowed at assignment, but the contract does not say whether the writer must expand them or the Resolver must interpret them. The observation is not scored.
- For mixed valid/invalid lists, specify full input validation versus per-item short-circuit validation. Single malformed checks may return false or throw InvalidArgumentException; both reject access. authorize must throw.
- No positive-ID precondition is declared. Unknown/zero/negative users reported inactive must deny; a store reporting them active is an observation, not an invented constraint.
- Soft deletion filtering belongs to AuthorizationStore by contract. These tests verify resolver behavior with compliant filtering; database adapter filtering remains M3.
- Removing assignments before forgetRole loses the former-user relationship. Writers must invalidate the former users with forgetUser/forgetUsers; define this obligation explicitly. This is recorded as an observation, not a passing forgetRole test.
- Cycles are rejected at save time in M4, not asserted as a Resolver responsibility. Last-super-admin protection, actual write hooks, HTTP errors and framework integration are outside M2. Only installed PHP was run, not the full supported runtime matrix.
- No source changes or commit. SPEC was not edited because the authorized write scope is tests/Integration and the coordination log.
