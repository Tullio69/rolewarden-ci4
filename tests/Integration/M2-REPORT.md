# M2 — Independent re-verification report

Target: `9e22678fcf9458ab8c89e33c8dd41cf3df8e97fd`. Runtime: PHP 8.3.11.

700 controls; 700 PASS; 0 FAIL

100 original controls; 100 PASS; 0 FAIL

5 original final-LF controls; 5 PASS; 0 FAIL

M2 approved within the tested scope. No reproducible defects found.

## Method and D1 re-verification

Run: `php tests/Integration/verify-m2.php` (no framework bootstrap, no database). Output captured in `m2-output.txt`. Counts refer to named controls; parameter combinations inside a control are not counted separately. Observations are excluded from all totals.

Tests derive from SPEC permission model/architecture, BRIEF M2/constraints/definition of done, the two public contracts and the user's explicit full-list validation requirement. The 08:10 correction log was read. Resolver implementation and unit tests were not opened or inspected. Production classes are loaded only for black-box execution. The separately requested mechanical framework-name scan reads PHP files without exposing source text; it reports only unexpected paths/line numbers, allowing the exact known contract comment. It requires neither rg nor grep; this lexical check is not a complete dependency audit. E_ALL warnings become failures.

D1 closed: all five original final-LF controls pass.

Additional probes cover every C0 byte, DEL and raw C1 byte; UTF-8 U+0085, U+2028/U+2029, Unicode spaces and invisible separators; CRLF, repeated LF, malformed UTF-8; leading, embedded and trailing positions. Each family runs through can, canAny, canAll and authorize with a directly assigned super admin and an explicit user denial. Slugs with action lengths 4096, 65536 and 1048576 bytes plus malformed suffixes are also checked.

Mixed lists contain valid granted or denied slugs and one malformed slug in first, middle or last position, for ordinary, super-admin and inactive users. Both canAny and canAll must report InvalidArgumentException even after a member that would otherwise short-circuit. This checks the requested full-list validation regression, including cached calls. Single malformed checks may deny or throw InvalidArgumentException; authorize must throw without warnings. The precise exception type for list validation is a regression expectation for this correction, not a claim that SPEC already defines it.

## Controls

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
| framework boundary scan in pure PHP (only known contract comment permitted) | PASS |  |
| D1 variant raw byte 00 via can | PASS |  |
| D1 variant raw byte 00 via canAny | PASS |  |
| D1 variant raw byte 00 via canAll | PASS |  |
| D1 variant raw byte 00 via authorize | PASS |  |
| full mixed-list validation raw byte 00 via canAny | PASS |  |
| full mixed-list validation raw byte 00 via canAll | PASS |  |
| D1 variant raw byte 01 via can | PASS |  |
| D1 variant raw byte 01 via canAny | PASS |  |
| D1 variant raw byte 01 via canAll | PASS |  |
| D1 variant raw byte 01 via authorize | PASS |  |
| full mixed-list validation raw byte 01 via canAny | PASS |  |
| full mixed-list validation raw byte 01 via canAll | PASS |  |
| D1 variant raw byte 02 via can | PASS |  |
| D1 variant raw byte 02 via canAny | PASS |  |
| D1 variant raw byte 02 via canAll | PASS |  |
| D1 variant raw byte 02 via authorize | PASS |  |
| full mixed-list validation raw byte 02 via canAny | PASS |  |
| full mixed-list validation raw byte 02 via canAll | PASS |  |
| D1 variant raw byte 03 via can | PASS |  |
| D1 variant raw byte 03 via canAny | PASS |  |
| D1 variant raw byte 03 via canAll | PASS |  |
| D1 variant raw byte 03 via authorize | PASS |  |
| full mixed-list validation raw byte 03 via canAny | PASS |  |
| full mixed-list validation raw byte 03 via canAll | PASS |  |
| D1 variant raw byte 04 via can | PASS |  |
| D1 variant raw byte 04 via canAny | PASS |  |
| D1 variant raw byte 04 via canAll | PASS |  |
| D1 variant raw byte 04 via authorize | PASS |  |
| full mixed-list validation raw byte 04 via canAny | PASS |  |
| full mixed-list validation raw byte 04 via canAll | PASS |  |
| D1 variant raw byte 05 via can | PASS |  |
| D1 variant raw byte 05 via canAny | PASS |  |
| D1 variant raw byte 05 via canAll | PASS |  |
| D1 variant raw byte 05 via authorize | PASS |  |
| full mixed-list validation raw byte 05 via canAny | PASS |  |
| full mixed-list validation raw byte 05 via canAll | PASS |  |
| D1 variant raw byte 06 via can | PASS |  |
| D1 variant raw byte 06 via canAny | PASS |  |
| D1 variant raw byte 06 via canAll | PASS |  |
| D1 variant raw byte 06 via authorize | PASS |  |
| full mixed-list validation raw byte 06 via canAny | PASS |  |
| full mixed-list validation raw byte 06 via canAll | PASS |  |
| D1 variant raw byte 07 via can | PASS |  |
| D1 variant raw byte 07 via canAny | PASS |  |
| D1 variant raw byte 07 via canAll | PASS |  |
| D1 variant raw byte 07 via authorize | PASS |  |
| full mixed-list validation raw byte 07 via canAny | PASS |  |
| full mixed-list validation raw byte 07 via canAll | PASS |  |
| D1 variant raw byte 08 via can | PASS |  |
| D1 variant raw byte 08 via canAny | PASS |  |
| D1 variant raw byte 08 via canAll | PASS |  |
| D1 variant raw byte 08 via authorize | PASS |  |
| full mixed-list validation raw byte 08 via canAny | PASS |  |
| full mixed-list validation raw byte 08 via canAll | PASS |  |
| D1 variant raw byte 09 via can | PASS |  |
| D1 variant raw byte 09 via canAny | PASS |  |
| D1 variant raw byte 09 via canAll | PASS |  |
| D1 variant raw byte 09 via authorize | PASS |  |
| full mixed-list validation raw byte 09 via canAny | PASS |  |
| full mixed-list validation raw byte 09 via canAll | PASS |  |
| D1 variant raw byte 0A via can | PASS |  |
| D1 variant raw byte 0A via canAny | PASS |  |
| D1 variant raw byte 0A via canAll | PASS |  |
| D1 variant raw byte 0A via authorize | PASS |  |
| full mixed-list validation raw byte 0A via canAny | PASS |  |
| full mixed-list validation raw byte 0A via canAll | PASS |  |
| D1 variant raw byte 0B via can | PASS |  |
| D1 variant raw byte 0B via canAny | PASS |  |
| D1 variant raw byte 0B via canAll | PASS |  |
| D1 variant raw byte 0B via authorize | PASS |  |
| full mixed-list validation raw byte 0B via canAny | PASS |  |
| full mixed-list validation raw byte 0B via canAll | PASS |  |
| D1 variant raw byte 0C via can | PASS |  |
| D1 variant raw byte 0C via canAny | PASS |  |
| D1 variant raw byte 0C via canAll | PASS |  |
| D1 variant raw byte 0C via authorize | PASS |  |
| full mixed-list validation raw byte 0C via canAny | PASS |  |
| full mixed-list validation raw byte 0C via canAll | PASS |  |
| D1 variant raw byte 0D via can | PASS |  |
| D1 variant raw byte 0D via canAny | PASS |  |
| D1 variant raw byte 0D via canAll | PASS |  |
| D1 variant raw byte 0D via authorize | PASS |  |
| full mixed-list validation raw byte 0D via canAny | PASS |  |
| full mixed-list validation raw byte 0D via canAll | PASS |  |
| D1 variant raw byte 0E via can | PASS |  |
| D1 variant raw byte 0E via canAny | PASS |  |
| D1 variant raw byte 0E via canAll | PASS |  |
| D1 variant raw byte 0E via authorize | PASS |  |
| full mixed-list validation raw byte 0E via canAny | PASS |  |
| full mixed-list validation raw byte 0E via canAll | PASS |  |
| D1 variant raw byte 0F via can | PASS |  |
| D1 variant raw byte 0F via canAny | PASS |  |
| D1 variant raw byte 0F via canAll | PASS |  |
| D1 variant raw byte 0F via authorize | PASS |  |
| full mixed-list validation raw byte 0F via canAny | PASS |  |
| full mixed-list validation raw byte 0F via canAll | PASS |  |
| D1 variant raw byte 10 via can | PASS |  |
| D1 variant raw byte 10 via canAny | PASS |  |
| D1 variant raw byte 10 via canAll | PASS |  |
| D1 variant raw byte 10 via authorize | PASS |  |
| full mixed-list validation raw byte 10 via canAny | PASS |  |
| full mixed-list validation raw byte 10 via canAll | PASS |  |
| D1 variant raw byte 11 via can | PASS |  |
| D1 variant raw byte 11 via canAny | PASS |  |
| D1 variant raw byte 11 via canAll | PASS |  |
| D1 variant raw byte 11 via authorize | PASS |  |
| full mixed-list validation raw byte 11 via canAny | PASS |  |
| full mixed-list validation raw byte 11 via canAll | PASS |  |
| D1 variant raw byte 12 via can | PASS |  |
| D1 variant raw byte 12 via canAny | PASS |  |
| D1 variant raw byte 12 via canAll | PASS |  |
| D1 variant raw byte 12 via authorize | PASS |  |
| full mixed-list validation raw byte 12 via canAny | PASS |  |
| full mixed-list validation raw byte 12 via canAll | PASS |  |
| D1 variant raw byte 13 via can | PASS |  |
| D1 variant raw byte 13 via canAny | PASS |  |
| D1 variant raw byte 13 via canAll | PASS |  |
| D1 variant raw byte 13 via authorize | PASS |  |
| full mixed-list validation raw byte 13 via canAny | PASS |  |
| full mixed-list validation raw byte 13 via canAll | PASS |  |
| D1 variant raw byte 14 via can | PASS |  |
| D1 variant raw byte 14 via canAny | PASS |  |
| D1 variant raw byte 14 via canAll | PASS |  |
| D1 variant raw byte 14 via authorize | PASS |  |
| full mixed-list validation raw byte 14 via canAny | PASS |  |
| full mixed-list validation raw byte 14 via canAll | PASS |  |
| D1 variant raw byte 15 via can | PASS |  |
| D1 variant raw byte 15 via canAny | PASS |  |
| D1 variant raw byte 15 via canAll | PASS |  |
| D1 variant raw byte 15 via authorize | PASS |  |
| full mixed-list validation raw byte 15 via canAny | PASS |  |
| full mixed-list validation raw byte 15 via canAll | PASS |  |
| D1 variant raw byte 16 via can | PASS |  |
| D1 variant raw byte 16 via canAny | PASS |  |
| D1 variant raw byte 16 via canAll | PASS |  |
| D1 variant raw byte 16 via authorize | PASS |  |
| full mixed-list validation raw byte 16 via canAny | PASS |  |
| full mixed-list validation raw byte 16 via canAll | PASS |  |
| D1 variant raw byte 17 via can | PASS |  |
| D1 variant raw byte 17 via canAny | PASS |  |
| D1 variant raw byte 17 via canAll | PASS |  |
| D1 variant raw byte 17 via authorize | PASS |  |
| full mixed-list validation raw byte 17 via canAny | PASS |  |
| full mixed-list validation raw byte 17 via canAll | PASS |  |
| D1 variant raw byte 18 via can | PASS |  |
| D1 variant raw byte 18 via canAny | PASS |  |
| D1 variant raw byte 18 via canAll | PASS |  |
| D1 variant raw byte 18 via authorize | PASS |  |
| full mixed-list validation raw byte 18 via canAny | PASS |  |
| full mixed-list validation raw byte 18 via canAll | PASS |  |
| D1 variant raw byte 19 via can | PASS |  |
| D1 variant raw byte 19 via canAny | PASS |  |
| D1 variant raw byte 19 via canAll | PASS |  |
| D1 variant raw byte 19 via authorize | PASS |  |
| full mixed-list validation raw byte 19 via canAny | PASS |  |
| full mixed-list validation raw byte 19 via canAll | PASS |  |
| D1 variant raw byte 1A via can | PASS |  |
| D1 variant raw byte 1A via canAny | PASS |  |
| D1 variant raw byte 1A via canAll | PASS |  |
| D1 variant raw byte 1A via authorize | PASS |  |
| full mixed-list validation raw byte 1A via canAny | PASS |  |
| full mixed-list validation raw byte 1A via canAll | PASS |  |
| D1 variant raw byte 1B via can | PASS |  |
| D1 variant raw byte 1B via canAny | PASS |  |
| D1 variant raw byte 1B via canAll | PASS |  |
| D1 variant raw byte 1B via authorize | PASS |  |
| full mixed-list validation raw byte 1B via canAny | PASS |  |
| full mixed-list validation raw byte 1B via canAll | PASS |  |
| D1 variant raw byte 1C via can | PASS |  |
| D1 variant raw byte 1C via canAny | PASS |  |
| D1 variant raw byte 1C via canAll | PASS |  |
| D1 variant raw byte 1C via authorize | PASS |  |
| full mixed-list validation raw byte 1C via canAny | PASS |  |
| full mixed-list validation raw byte 1C via canAll | PASS |  |
| D1 variant raw byte 1D via can | PASS |  |
| D1 variant raw byte 1D via canAny | PASS |  |
| D1 variant raw byte 1D via canAll | PASS |  |
| D1 variant raw byte 1D via authorize | PASS |  |
| full mixed-list validation raw byte 1D via canAny | PASS |  |
| full mixed-list validation raw byte 1D via canAll | PASS |  |
| D1 variant raw byte 1E via can | PASS |  |
| D1 variant raw byte 1E via canAny | PASS |  |
| D1 variant raw byte 1E via canAll | PASS |  |
| D1 variant raw byte 1E via authorize | PASS |  |
| full mixed-list validation raw byte 1E via canAny | PASS |  |
| full mixed-list validation raw byte 1E via canAll | PASS |  |
| D1 variant raw byte 1F via can | PASS |  |
| D1 variant raw byte 1F via canAny | PASS |  |
| D1 variant raw byte 1F via canAll | PASS |  |
| D1 variant raw byte 1F via authorize | PASS |  |
| full mixed-list validation raw byte 1F via canAny | PASS |  |
| full mixed-list validation raw byte 1F via canAll | PASS |  |
| D1 variant raw byte 7F via can | PASS |  |
| D1 variant raw byte 7F via canAny | PASS |  |
| D1 variant raw byte 7F via canAll | PASS |  |
| D1 variant raw byte 7F via authorize | PASS |  |
| full mixed-list validation raw byte 7F via canAny | PASS |  |
| full mixed-list validation raw byte 7F via canAll | PASS |  |
| D1 variant raw byte 80 via can | PASS |  |
| D1 variant raw byte 80 via canAny | PASS |  |
| D1 variant raw byte 80 via canAll | PASS |  |
| D1 variant raw byte 80 via authorize | PASS |  |
| full mixed-list validation raw byte 80 via canAny | PASS |  |
| full mixed-list validation raw byte 80 via canAll | PASS |  |
| D1 variant raw byte 81 via can | PASS |  |
| D1 variant raw byte 81 via canAny | PASS |  |
| D1 variant raw byte 81 via canAll | PASS |  |
| D1 variant raw byte 81 via authorize | PASS |  |
| full mixed-list validation raw byte 81 via canAny | PASS |  |
| full mixed-list validation raw byte 81 via canAll | PASS |  |
| D1 variant raw byte 82 via can | PASS |  |
| D1 variant raw byte 82 via canAny | PASS |  |
| D1 variant raw byte 82 via canAll | PASS |  |
| D1 variant raw byte 82 via authorize | PASS |  |
| full mixed-list validation raw byte 82 via canAny | PASS |  |
| full mixed-list validation raw byte 82 via canAll | PASS |  |
| D1 variant raw byte 83 via can | PASS |  |
| D1 variant raw byte 83 via canAny | PASS |  |
| D1 variant raw byte 83 via canAll | PASS |  |
| D1 variant raw byte 83 via authorize | PASS |  |
| full mixed-list validation raw byte 83 via canAny | PASS |  |
| full mixed-list validation raw byte 83 via canAll | PASS |  |
| D1 variant raw byte 84 via can | PASS |  |
| D1 variant raw byte 84 via canAny | PASS |  |
| D1 variant raw byte 84 via canAll | PASS |  |
| D1 variant raw byte 84 via authorize | PASS |  |
| full mixed-list validation raw byte 84 via canAny | PASS |  |
| full mixed-list validation raw byte 84 via canAll | PASS |  |
| D1 variant raw byte 85 via can | PASS |  |
| D1 variant raw byte 85 via canAny | PASS |  |
| D1 variant raw byte 85 via canAll | PASS |  |
| D1 variant raw byte 85 via authorize | PASS |  |
| full mixed-list validation raw byte 85 via canAny | PASS |  |
| full mixed-list validation raw byte 85 via canAll | PASS |  |
| D1 variant raw byte 86 via can | PASS |  |
| D1 variant raw byte 86 via canAny | PASS |  |
| D1 variant raw byte 86 via canAll | PASS |  |
| D1 variant raw byte 86 via authorize | PASS |  |
| full mixed-list validation raw byte 86 via canAny | PASS |  |
| full mixed-list validation raw byte 86 via canAll | PASS |  |
| D1 variant raw byte 87 via can | PASS |  |
| D1 variant raw byte 87 via canAny | PASS |  |
| D1 variant raw byte 87 via canAll | PASS |  |
| D1 variant raw byte 87 via authorize | PASS |  |
| full mixed-list validation raw byte 87 via canAny | PASS |  |
| full mixed-list validation raw byte 87 via canAll | PASS |  |
| D1 variant raw byte 88 via can | PASS |  |
| D1 variant raw byte 88 via canAny | PASS |  |
| D1 variant raw byte 88 via canAll | PASS |  |
| D1 variant raw byte 88 via authorize | PASS |  |
| full mixed-list validation raw byte 88 via canAny | PASS |  |
| full mixed-list validation raw byte 88 via canAll | PASS |  |
| D1 variant raw byte 89 via can | PASS |  |
| D1 variant raw byte 89 via canAny | PASS |  |
| D1 variant raw byte 89 via canAll | PASS |  |
| D1 variant raw byte 89 via authorize | PASS |  |
| full mixed-list validation raw byte 89 via canAny | PASS |  |
| full mixed-list validation raw byte 89 via canAll | PASS |  |
| D1 variant raw byte 8A via can | PASS |  |
| D1 variant raw byte 8A via canAny | PASS |  |
| D1 variant raw byte 8A via canAll | PASS |  |
| D1 variant raw byte 8A via authorize | PASS |  |
| full mixed-list validation raw byte 8A via canAny | PASS |  |
| full mixed-list validation raw byte 8A via canAll | PASS |  |
| D1 variant raw byte 8B via can | PASS |  |
| D1 variant raw byte 8B via canAny | PASS |  |
| D1 variant raw byte 8B via canAll | PASS |  |
| D1 variant raw byte 8B via authorize | PASS |  |
| full mixed-list validation raw byte 8B via canAny | PASS |  |
| full mixed-list validation raw byte 8B via canAll | PASS |  |
| D1 variant raw byte 8C via can | PASS |  |
| D1 variant raw byte 8C via canAny | PASS |  |
| D1 variant raw byte 8C via canAll | PASS |  |
| D1 variant raw byte 8C via authorize | PASS |  |
| full mixed-list validation raw byte 8C via canAny | PASS |  |
| full mixed-list validation raw byte 8C via canAll | PASS |  |
| D1 variant raw byte 8D via can | PASS |  |
| D1 variant raw byte 8D via canAny | PASS |  |
| D1 variant raw byte 8D via canAll | PASS |  |
| D1 variant raw byte 8D via authorize | PASS |  |
| full mixed-list validation raw byte 8D via canAny | PASS |  |
| full mixed-list validation raw byte 8D via canAll | PASS |  |
| D1 variant raw byte 8E via can | PASS |  |
| D1 variant raw byte 8E via canAny | PASS |  |
| D1 variant raw byte 8E via canAll | PASS |  |
| D1 variant raw byte 8E via authorize | PASS |  |
| full mixed-list validation raw byte 8E via canAny | PASS |  |
| full mixed-list validation raw byte 8E via canAll | PASS |  |
| D1 variant raw byte 8F via can | PASS |  |
| D1 variant raw byte 8F via canAny | PASS |  |
| D1 variant raw byte 8F via canAll | PASS |  |
| D1 variant raw byte 8F via authorize | PASS |  |
| full mixed-list validation raw byte 8F via canAny | PASS |  |
| full mixed-list validation raw byte 8F via canAll | PASS |  |
| D1 variant raw byte 90 via can | PASS |  |
| D1 variant raw byte 90 via canAny | PASS |  |
| D1 variant raw byte 90 via canAll | PASS |  |
| D1 variant raw byte 90 via authorize | PASS |  |
| full mixed-list validation raw byte 90 via canAny | PASS |  |
| full mixed-list validation raw byte 90 via canAll | PASS |  |
| D1 variant raw byte 91 via can | PASS |  |
| D1 variant raw byte 91 via canAny | PASS |  |
| D1 variant raw byte 91 via canAll | PASS |  |
| D1 variant raw byte 91 via authorize | PASS |  |
| full mixed-list validation raw byte 91 via canAny | PASS |  |
| full mixed-list validation raw byte 91 via canAll | PASS |  |
| D1 variant raw byte 92 via can | PASS |  |
| D1 variant raw byte 92 via canAny | PASS |  |
| D1 variant raw byte 92 via canAll | PASS |  |
| D1 variant raw byte 92 via authorize | PASS |  |
| full mixed-list validation raw byte 92 via canAny | PASS |  |
| full mixed-list validation raw byte 92 via canAll | PASS |  |
| D1 variant raw byte 93 via can | PASS |  |
| D1 variant raw byte 93 via canAny | PASS |  |
| D1 variant raw byte 93 via canAll | PASS |  |
| D1 variant raw byte 93 via authorize | PASS |  |
| full mixed-list validation raw byte 93 via canAny | PASS |  |
| full mixed-list validation raw byte 93 via canAll | PASS |  |
| D1 variant raw byte 94 via can | PASS |  |
| D1 variant raw byte 94 via canAny | PASS |  |
| D1 variant raw byte 94 via canAll | PASS |  |
| D1 variant raw byte 94 via authorize | PASS |  |
| full mixed-list validation raw byte 94 via canAny | PASS |  |
| full mixed-list validation raw byte 94 via canAll | PASS |  |
| D1 variant raw byte 95 via can | PASS |  |
| D1 variant raw byte 95 via canAny | PASS |  |
| D1 variant raw byte 95 via canAll | PASS |  |
| D1 variant raw byte 95 via authorize | PASS |  |
| full mixed-list validation raw byte 95 via canAny | PASS |  |
| full mixed-list validation raw byte 95 via canAll | PASS |  |
| D1 variant raw byte 96 via can | PASS |  |
| D1 variant raw byte 96 via canAny | PASS |  |
| D1 variant raw byte 96 via canAll | PASS |  |
| D1 variant raw byte 96 via authorize | PASS |  |
| full mixed-list validation raw byte 96 via canAny | PASS |  |
| full mixed-list validation raw byte 96 via canAll | PASS |  |
| D1 variant raw byte 97 via can | PASS |  |
| D1 variant raw byte 97 via canAny | PASS |  |
| D1 variant raw byte 97 via canAll | PASS |  |
| D1 variant raw byte 97 via authorize | PASS |  |
| full mixed-list validation raw byte 97 via canAny | PASS |  |
| full mixed-list validation raw byte 97 via canAll | PASS |  |
| D1 variant raw byte 98 via can | PASS |  |
| D1 variant raw byte 98 via canAny | PASS |  |
| D1 variant raw byte 98 via canAll | PASS |  |
| D1 variant raw byte 98 via authorize | PASS |  |
| full mixed-list validation raw byte 98 via canAny | PASS |  |
| full mixed-list validation raw byte 98 via canAll | PASS |  |
| D1 variant raw byte 99 via can | PASS |  |
| D1 variant raw byte 99 via canAny | PASS |  |
| D1 variant raw byte 99 via canAll | PASS |  |
| D1 variant raw byte 99 via authorize | PASS |  |
| full mixed-list validation raw byte 99 via canAny | PASS |  |
| full mixed-list validation raw byte 99 via canAll | PASS |  |
| D1 variant raw byte 9A via can | PASS |  |
| D1 variant raw byte 9A via canAny | PASS |  |
| D1 variant raw byte 9A via canAll | PASS |  |
| D1 variant raw byte 9A via authorize | PASS |  |
| full mixed-list validation raw byte 9A via canAny | PASS |  |
| full mixed-list validation raw byte 9A via canAll | PASS |  |
| D1 variant raw byte 9B via can | PASS |  |
| D1 variant raw byte 9B via canAny | PASS |  |
| D1 variant raw byte 9B via canAll | PASS |  |
| D1 variant raw byte 9B via authorize | PASS |  |
| full mixed-list validation raw byte 9B via canAny | PASS |  |
| full mixed-list validation raw byte 9B via canAll | PASS |  |
| D1 variant raw byte 9C via can | PASS |  |
| D1 variant raw byte 9C via canAny | PASS |  |
| D1 variant raw byte 9C via canAll | PASS |  |
| D1 variant raw byte 9C via authorize | PASS |  |
| full mixed-list validation raw byte 9C via canAny | PASS |  |
| full mixed-list validation raw byte 9C via canAll | PASS |  |
| D1 variant raw byte 9D via can | PASS |  |
| D1 variant raw byte 9D via canAny | PASS |  |
| D1 variant raw byte 9D via canAll | PASS |  |
| D1 variant raw byte 9D via authorize | PASS |  |
| full mixed-list validation raw byte 9D via canAny | PASS |  |
| full mixed-list validation raw byte 9D via canAll | PASS |  |
| D1 variant raw byte 9E via can | PASS |  |
| D1 variant raw byte 9E via canAny | PASS |  |
| D1 variant raw byte 9E via canAll | PASS |  |
| D1 variant raw byte 9E via authorize | PASS |  |
| full mixed-list validation raw byte 9E via canAny | PASS |  |
| full mixed-list validation raw byte 9E via canAll | PASS |  |
| D1 variant raw byte 9F via can | PASS |  |
| D1 variant raw byte 9F via canAny | PASS |  |
| D1 variant raw byte 9F via canAll | PASS |  |
| D1 variant raw byte 9F via authorize | PASS |  |
| full mixed-list validation raw byte 9F via canAny | PASS |  |
| full mixed-list validation raw byte 9F via canAll | PASS |  |
| D1 variant Unicode U+0085 via can | PASS |  |
| D1 variant Unicode U+0085 via canAny | PASS |  |
| D1 variant Unicode U+0085 via canAll | PASS |  |
| D1 variant Unicode U+0085 via authorize | PASS |  |
| full mixed-list validation Unicode U+0085 via canAny | PASS |  |
| full mixed-list validation Unicode U+0085 via canAll | PASS |  |
| D1 variant Unicode U+00A0 via can | PASS |  |
| D1 variant Unicode U+00A0 via canAny | PASS |  |
| D1 variant Unicode U+00A0 via canAll | PASS |  |
| D1 variant Unicode U+00A0 via authorize | PASS |  |
| full mixed-list validation Unicode U+00A0 via canAny | PASS |  |
| full mixed-list validation Unicode U+00A0 via canAll | PASS |  |
| D1 variant Unicode U+1680 via can | PASS |  |
| D1 variant Unicode U+1680 via canAny | PASS |  |
| D1 variant Unicode U+1680 via canAll | PASS |  |
| D1 variant Unicode U+1680 via authorize | PASS |  |
| full mixed-list validation Unicode U+1680 via canAny | PASS |  |
| full mixed-list validation Unicode U+1680 via canAll | PASS |  |
| D1 variant Unicode U+180E via can | PASS |  |
| D1 variant Unicode U+180E via canAny | PASS |  |
| D1 variant Unicode U+180E via canAll | PASS |  |
| D1 variant Unicode U+180E via authorize | PASS |  |
| full mixed-list validation Unicode U+180E via canAny | PASS |  |
| full mixed-list validation Unicode U+180E via canAll | PASS |  |
| D1 variant Unicode U+2000 via can | PASS |  |
| D1 variant Unicode U+2000 via canAny | PASS |  |
| D1 variant Unicode U+2000 via canAll | PASS |  |
| D1 variant Unicode U+2000 via authorize | PASS |  |
| full mixed-list validation Unicode U+2000 via canAny | PASS |  |
| full mixed-list validation Unicode U+2000 via canAll | PASS |  |
| D1 variant Unicode U+2001 via can | PASS |  |
| D1 variant Unicode U+2001 via canAny | PASS |  |
| D1 variant Unicode U+2001 via canAll | PASS |  |
| D1 variant Unicode U+2001 via authorize | PASS |  |
| full mixed-list validation Unicode U+2001 via canAny | PASS |  |
| full mixed-list validation Unicode U+2001 via canAll | PASS |  |
| D1 variant Unicode U+2002 via can | PASS |  |
| D1 variant Unicode U+2002 via canAny | PASS |  |
| D1 variant Unicode U+2002 via canAll | PASS |  |
| D1 variant Unicode U+2002 via authorize | PASS |  |
| full mixed-list validation Unicode U+2002 via canAny | PASS |  |
| full mixed-list validation Unicode U+2002 via canAll | PASS |  |
| D1 variant Unicode U+2003 via can | PASS |  |
| D1 variant Unicode U+2003 via canAny | PASS |  |
| D1 variant Unicode U+2003 via canAll | PASS |  |
| D1 variant Unicode U+2003 via authorize | PASS |  |
| full mixed-list validation Unicode U+2003 via canAny | PASS |  |
| full mixed-list validation Unicode U+2003 via canAll | PASS |  |
| D1 variant Unicode U+2004 via can | PASS |  |
| D1 variant Unicode U+2004 via canAny | PASS |  |
| D1 variant Unicode U+2004 via canAll | PASS |  |
| D1 variant Unicode U+2004 via authorize | PASS |  |
| full mixed-list validation Unicode U+2004 via canAny | PASS |  |
| full mixed-list validation Unicode U+2004 via canAll | PASS |  |
| D1 variant Unicode U+2005 via can | PASS |  |
| D1 variant Unicode U+2005 via canAny | PASS |  |
| D1 variant Unicode U+2005 via canAll | PASS |  |
| D1 variant Unicode U+2005 via authorize | PASS |  |
| full mixed-list validation Unicode U+2005 via canAny | PASS |  |
| full mixed-list validation Unicode U+2005 via canAll | PASS |  |
| D1 variant Unicode U+2006 via can | PASS |  |
| D1 variant Unicode U+2006 via canAny | PASS |  |
| D1 variant Unicode U+2006 via canAll | PASS |  |
| D1 variant Unicode U+2006 via authorize | PASS |  |
| full mixed-list validation Unicode U+2006 via canAny | PASS |  |
| full mixed-list validation Unicode U+2006 via canAll | PASS |  |
| D1 variant Unicode U+2007 via can | PASS |  |
| D1 variant Unicode U+2007 via canAny | PASS |  |
| D1 variant Unicode U+2007 via canAll | PASS |  |
| D1 variant Unicode U+2007 via authorize | PASS |  |
| full mixed-list validation Unicode U+2007 via canAny | PASS |  |
| full mixed-list validation Unicode U+2007 via canAll | PASS |  |
| D1 variant Unicode U+2008 via can | PASS |  |
| D1 variant Unicode U+2008 via canAny | PASS |  |
| D1 variant Unicode U+2008 via canAll | PASS |  |
| D1 variant Unicode U+2008 via authorize | PASS |  |
| full mixed-list validation Unicode U+2008 via canAny | PASS |  |
| full mixed-list validation Unicode U+2008 via canAll | PASS |  |
| D1 variant Unicode U+2009 via can | PASS |  |
| D1 variant Unicode U+2009 via canAny | PASS |  |
| D1 variant Unicode U+2009 via canAll | PASS |  |
| D1 variant Unicode U+2009 via authorize | PASS |  |
| full mixed-list validation Unicode U+2009 via canAny | PASS |  |
| full mixed-list validation Unicode U+2009 via canAll | PASS |  |
| D1 variant Unicode U+200A via can | PASS |  |
| D1 variant Unicode U+200A via canAny | PASS |  |
| D1 variant Unicode U+200A via canAll | PASS |  |
| D1 variant Unicode U+200A via authorize | PASS |  |
| full mixed-list validation Unicode U+200A via canAny | PASS |  |
| full mixed-list validation Unicode U+200A via canAll | PASS |  |
| D1 variant Unicode U+200B via can | PASS |  |
| D1 variant Unicode U+200B via canAny | PASS |  |
| D1 variant Unicode U+200B via canAll | PASS |  |
| D1 variant Unicode U+200B via authorize | PASS |  |
| full mixed-list validation Unicode U+200B via canAny | PASS |  |
| full mixed-list validation Unicode U+200B via canAll | PASS |  |
| D1 variant Unicode U+2028 via can | PASS |  |
| D1 variant Unicode U+2028 via canAny | PASS |  |
| D1 variant Unicode U+2028 via canAll | PASS |  |
| D1 variant Unicode U+2028 via authorize | PASS |  |
| full mixed-list validation Unicode U+2028 via canAny | PASS |  |
| full mixed-list validation Unicode U+2028 via canAll | PASS |  |
| D1 variant Unicode U+2029 via can | PASS |  |
| D1 variant Unicode U+2029 via canAny | PASS |  |
| D1 variant Unicode U+2029 via canAll | PASS |  |
| D1 variant Unicode U+2029 via authorize | PASS |  |
| full mixed-list validation Unicode U+2029 via canAny | PASS |  |
| full mixed-list validation Unicode U+2029 via canAll | PASS |  |
| D1 variant Unicode U+202F via can | PASS |  |
| D1 variant Unicode U+202F via canAny | PASS |  |
| D1 variant Unicode U+202F via canAll | PASS |  |
| D1 variant Unicode U+202F via authorize | PASS |  |
| full mixed-list validation Unicode U+202F via canAny | PASS |  |
| full mixed-list validation Unicode U+202F via canAll | PASS |  |
| D1 variant Unicode U+205F via can | PASS |  |
| D1 variant Unicode U+205F via canAny | PASS |  |
| D1 variant Unicode U+205F via canAll | PASS |  |
| D1 variant Unicode U+205F via authorize | PASS |  |
| full mixed-list validation Unicode U+205F via canAny | PASS |  |
| full mixed-list validation Unicode U+205F via canAll | PASS |  |
| D1 variant Unicode U+2060 via can | PASS |  |
| D1 variant Unicode U+2060 via canAny | PASS |  |
| D1 variant Unicode U+2060 via canAll | PASS |  |
| D1 variant Unicode U+2060 via authorize | PASS |  |
| full mixed-list validation Unicode U+2060 via canAny | PASS |  |
| full mixed-list validation Unicode U+2060 via canAll | PASS |  |
| D1 variant Unicode U+3000 via can | PASS |  |
| D1 variant Unicode U+3000 via canAny | PASS |  |
| D1 variant Unicode U+3000 via canAll | PASS |  |
| D1 variant Unicode U+3000 via authorize | PASS |  |
| full mixed-list validation Unicode U+3000 via canAny | PASS |  |
| full mixed-list validation Unicode U+3000 via canAll | PASS |  |
| D1 variant Unicode U+FEFF via can | PASS |  |
| D1 variant Unicode U+FEFF via canAny | PASS |  |
| D1 variant Unicode U+FEFF via canAll | PASS |  |
| D1 variant Unicode U+FEFF via authorize | PASS |  |
| full mixed-list validation Unicode U+FEFF via canAny | PASS |  |
| full mixed-list validation Unicode U+FEFF via canAll | PASS |  |
| D1 variant CRLF via can | PASS |  |
| D1 variant CRLF via canAny | PASS |  |
| D1 variant CRLF via canAll | PASS |  |
| D1 variant CRLF via authorize | PASS |  |
| full mixed-list validation CRLF via canAny | PASS |  |
| full mixed-list validation CRLF via canAll | PASS |  |
| D1 variant double LF via can | PASS |  |
| D1 variant double LF via canAny | PASS |  |
| D1 variant double LF via canAll | PASS |  |
| D1 variant double LF via authorize | PASS |  |
| full mixed-list validation double LF via canAny | PASS |  |
| full mixed-list validation double LF via canAll | PASS |  |
| D1 variant ASCII space via can | PASS |  |
| D1 variant ASCII space via canAny | PASS |  |
| D1 variant ASCII space via canAll | PASS |  |
| D1 variant ASCII space via authorize | PASS |  |
| full mixed-list validation ASCII space via canAny | PASS |  |
| full mixed-list validation ASCII space via canAll | PASS |  |
| D1 variant invalid UTF-8 FF via can | PASS |  |
| D1 variant invalid UTF-8 FF via canAny | PASS |  |
| D1 variant invalid UTF-8 FF via canAll | PASS |  |
| D1 variant invalid UTF-8 FF via authorize | PASS |  |
| full mixed-list validation invalid UTF-8 FF via canAny | PASS |  |
| full mixed-list validation invalid UTF-8 FF via canAll | PASS |  |
| D1 variant invalid UTF-8 truncated via can | PASS |  |
| D1 variant invalid UTF-8 truncated via canAny | PASS |  |
| D1 variant invalid UTF-8 truncated via canAll | PASS |  |
| D1 variant invalid UTF-8 truncated via authorize | PASS |  |
| full mixed-list validation invalid UTF-8 truncated via canAny | PASS |  |
| full mixed-list validation invalid UTF-8 truncated via canAll | PASS |  |
| D1 variant invalid UTF-8 overlong LF via can | PASS |  |
| D1 variant invalid UTF-8 overlong LF via canAny | PASS |  |
| D1 variant invalid UTF-8 overlong LF via canAll | PASS |  |
| D1 variant invalid UTF-8 overlong LF via authorize | PASS |  |
| full mixed-list validation invalid UTF-8 overlong LF via canAny | PASS |  |
| full mixed-list validation invalid UTF-8 overlong LF via canAll | PASS |  |
| D1 variant invalid UTF-8 surrogate via can | PASS |  |
| D1 variant invalid UTF-8 surrogate via canAny | PASS |  |
| D1 variant invalid UTF-8 surrogate via canAll | PASS |  |
| D1 variant invalid UTF-8 surrogate via authorize | PASS |  |
| full mixed-list validation invalid UTF-8 surrogate via canAny | PASS |  |
| full mixed-list validation invalid UTF-8 surrogate via canAll | PASS |  |
| D1 variant invalid UTF-8 above Unicode range via can | PASS |  |
| D1 variant invalid UTF-8 above Unicode range via canAny | PASS |  |
| D1 variant invalid UTF-8 above Unicode range via canAll | PASS |  |
| D1 variant invalid UTF-8 above Unicode range via authorize | PASS |  |
| full mixed-list validation invalid UTF-8 above Unicode range via canAny | PASS |  |
| full mixed-list validation invalid UTF-8 above Unicode range via canAll | PASS |  |
| D1 variant wildcard via can | PASS |  |
| D1 variant wildcard via canAny | PASS |  |
| D1 variant wildcard via canAll | PASS |  |
| D1 variant wildcard via authorize | PASS |  |
| full mixed-list validation wildcard via canAny | PASS |  |
| full mixed-list validation wildcard via canAll | PASS |  |
| D1 variant long action 4096 with forbidden suffix via can | PASS |  |
| D1 variant long action 4096 with forbidden suffix via canAny | PASS |  |
| D1 variant long action 4096 with forbidden suffix via canAll | PASS |  |
| D1 variant long action 4096 with forbidden suffix via authorize | PASS |  |
| full mixed-list validation long action 4096 with forbidden suffix via canAny | PASS |  |
| full mixed-list validation long action 4096 with forbidden suffix via canAll | PASS |  |
| D1 variant long action 65536 with forbidden suffix via can | PASS |  |
| D1 variant long action 65536 with forbidden suffix via canAny | PASS |  |
| D1 variant long action 65536 with forbidden suffix via canAll | PASS |  |
| D1 variant long action 65536 with forbidden suffix via authorize | PASS |  |
| full mixed-list validation long action 65536 with forbidden suffix via canAny | PASS |  |
| full mixed-list validation long action 65536 with forbidden suffix via canAll | PASS |  |
| D1 variant long action 1048576 with forbidden suffix via can | PASS |  |
| D1 variant long action 1048576 with forbidden suffix via canAny | PASS |  |
| D1 variant long action 1048576 with forbidden suffix via canAll | PASS |  |
| D1 variant long action 1048576 with forbidden suffix via authorize | PASS |  |
| full mixed-list validation long action 1048576 with forbidden suffix via canAny | PASS |  |
| full mixed-list validation long action 1048576 with forbidden suffix via canAll | PASS |  |

## Observations requiring specification decisions (not defects; not PASS/FAIL)

| Probe | Observed |
|---|---|
| Empty canAny/canAll, active then inactive | [[false,true],[false,true]] |
| Super flag inherited without direct assignment | false |
| Wildcard received from store: role grant | [false,["users.*"]] |
| Store declares zero and negative identifiers active | [true,true] |
| Role unassigned before forgetRole | true |
| Super-admin permissions enumeration | [["users.view"],true] |
| Long alphabetic slug, action bytes 4096 via can | true |
| Long alphabetic slug, action bytes 4096 via canAny | true |
| Long alphabetic slug, action bytes 4096 via canAll | true |
| Long alphabetic slug, action bytes 4096 via authorize | null |
| Long alphabetic slug, action bytes 65536 via can | true |
| Long alphabetic slug, action bytes 65536 via canAny | true |
| Long alphabetic slug, action bytes 65536 via canAll | true |
| Long alphabetic slug, action bytes 65536 via authorize | null |
| Long alphabetic slug, action bytes 1048576 via can | true |
| Long alphabetic slug, action bytes 1048576 via canAny | true |
| Long alphabetic slug, action bytes 1048576 via canAll | true |
| Long alphabetic slug, action bytes 1048576 via authorize | null |
| Unspecified slug alphabet "users.read_all" | true |
| Unspecified slug alphabet "users.read-all" | true |
| Unspecified slug alphabet "users.read2" | true |
| Unspecified slug alphabet "utenti.\u00e8dit" | InvalidArgumentException: Invalid permission "utenti.èdit": use the "area.action" format in lowercase, and no wildcards when checking. |
| Unspecified slug alphabet "\u7528\u6237.view" | InvalidArgumentException: Invalid permission "用户.view": use the "area.action" format in lowercase, and no wildcards when checking. |

## Scope and decisions still open

- Empty canAny/canAll semantics remain undecided, particularly canAll([]) for inactive users. No FAIL is assigned.
- Inheritance of the super-admin flag and permissions() enumeration for a super admin remain specification decisions; the observed behavior is not a defect.
- Extended slug alphabet (digits, underscores, hyphens, Unicode letters) and maximum slug length are unspecified. Long alphabetic slugs are observations across all four methods, without inventing a size limit. Malformed suffixes still must not authorize.
- Wildcards are allowed at assignment, but the documents do not say whether the writer expands them or the Resolver interprets them. The store wildcard probe is unscored.
- Simultaneous opposite overrides cannot be represented by the contract's slug-to-bool map: PHP replaces duplicate keys. Define conflict handling in the M3 writer/adapter, not through a fabricated Resolver test.
- No positive-ID precondition is declared. A store reporting zero or negative IDs active remains an unscored observation.
- Full mixed-list validation is explicitly requested for this re-verification and is tested above; synchronize this requirement and its error response into the specification. It is no longer presented as an unresolved short-circuit choice in this report.
- After assignment removal, forgetRole cannot discover former holders through the store. Define the M3 writer obligation to invalidate former IDs with forgetUser/forgetUsers. This is an integration obligation to specify, not a Resolver FAIL.
- Soft-delete filtering is an AuthorizationStore responsibility. Tests supply compliant filtering; actual database adapter filtering and write hooks remain M3. Cycles and last-super-admin protection remain M4; HTTP paths remain later milestones. Only PHP 8.3.11 was run, not the supported runtime matrix.
- No source edits, database access or commit. SPEC is unchanged because writes are restricted to tests/Integration and _AI-LOG.md. The pre-existing modification to CLAUDE.md was left intact.
