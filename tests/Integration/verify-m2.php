<?php

declare(strict_types=1);

// Independent acceptance checks: SPEC/BRIEF and public contracts only.
error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'RoleWarden\\Authorization\\')) {
        require dirname(__DIR__, 2) . '/src/' . str_replace('\\', '/', substr($class, 11)) . '.php';
    }
});

use RoleWarden\Authorization\Contracts\AuthorizationStore;
use RoleWarden\Authorization\Contracts\Cache;
use RoleWarden\Authorization\Resolver;

final class M2Store implements AuthorizationStore
{
    public array $active = [1 => true, 2 => true, 3 => true];
    public array $overrides = [];
    public array $assignments = [];
    public array $roles = [];
    public array $deleted = [];
    public array $reads = [];

    private function read(int $id): void { $this->reads[$id] = ($this->reads[$id] ?? 0) + 1; }
    public function isActive(int $userId): bool { $this->read($userId); return $this->active[$userId] ?? false; }
    public function userOverrides(int $userId): array { $this->read($userId); return $this->overrides[$userId] ?? []; }
    public function userRoleIds(int $userId): array
    {
        $this->read($userId);
        return array_values(array_filter($this->assignments[$userId] ?? [], fn ($id) => !isset($this->deleted[$id])));
    }
    public function role(int $roleId): ?array { return isset($this->deleted[$roleId]) ? null : ($this->roles[$roleId] ?? null); }
    public function childRoleIds(int $roleId): array
    {
        return array_values(array_filter(array_keys($this->roles), fn ($id) => !isset($this->deleted[$id]) && $this->roles[$id]['parentId'] === $roleId));
    }
    public function userIdsWithRole(int $roleId): array
    {
        return array_values(array_filter(array_keys($this->assignments), fn ($id) => in_array($roleId, $this->assignments[$id], true)));
    }
}

final class M2Cache implements Cache
{
    public array $entries = [];
    public function get(string $key): ?array { return $this->entries[$key] ?? null; }
    public function set(string $key, array $value): void { $this->entries[$key] = $value; }
    public function forget(string $key): void { unset($this->entries[$key]); }
}

function role(array $permissions = [], ?int $parent = null, bool $super = false): array
{
    return ['parentId' => $parent, 'isSuperAdmin' => $super, 'permissions' => $permissions];
}
function fixture(): array
{
    $s = new M2Store(); $c = new M2Cache();
    return [$s, $c, new Resolver($s, $c)];
}
function same(mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException('expected ' . json_encode($expected) . ', got ' . json_encode($actual));
    }
}
function permissionSet(Resolver $r, int $user, array $expected): void
{
    $actual = $r->permissions($user);
    sort($actual); sort($expected); same($actual, $expected);
}
function denied(callable $call): void
{
    try { $result = $call(); } catch (InvalidArgumentException) { return; }
    same($result, false);
}
function authorizationDenied(Resolver $r, int $id, string $slug): void
{
    try { $r->authorize($id, $slug); } catch (Exception $e) {
        if ($e instanceof ErrorException) { throw $e; }
        return;
    }
    throw new RuntimeException('authorize did not throw');
}
$results = [];
function check(string $name, callable $test): void
{
    global $results;
    try { $test(); $status = 'PASS'; $detail = ''; }
    catch (Throwable $e) { $status = 'FAIL'; $detail = get_class($e) . ': ' . $e->getMessage(); }
    $results[] = [$name, $status, $detail];
    echo "$status $name" . ($detail ? " — $detail" : '') . PHP_EOL;
}

// Exhaustive precedence truth table, independent of traversal/cache internals.
foreach ([false, true] as $active) {
    foreach ([null, false, true] as $override) {
        foreach ([false, true] as $super) {
            foreach ([false, true] as $grant) {
                check('precedence ' . json_encode([$active, $override, $super, $grant]), function () use ($active, $override, $super, $grant): void {
                    [$s, , $r] = fixture();
                    $s->active[1] = $active;
                    $s->assignments[1] = [10];
                    $s->roles[10] = role($grant ? ['users.view'] : [], null, $super);
                    if ($override !== null) { $s->overrides[1] = ['users.view' => $override]; }
                    $expected = $active && ($override ?? ($super || $grant));
                    same($r->can(1, 'users.view'), $expected);
                    if ($expected) { $r->authorize(1, 'users.view'); } else { authorizationDenied($r, 1, 'users.view'); }
                });
            }
        }
    }
}
foreach ([[30], [30, 30], [30, 20], [20, 30], [10, 20, 30], [30, 20, 10]] as $ids) {
    check('hierarchy union and duplicates ' . json_encode($ids), function () use ($ids): void {
        [$s, , $r] = fixture();
        $s->roles = [10 => role(['users.view']), 20 => role(['users.create'], 10), 30 => role(['users.update', 'users.view'], 20)];
        $s->assignments[1] = $ids;
        foreach (['users.view', 'users.create', 'users.update'] as $p) { same($r->can(1, $p), true); }
        permissionSet($r, 1, ['users.view', 'users.create', 'users.update']);
        same($r->can(1, 'users.delete'), false);
    });
}
foreach ([[20, 10], [10, 20], [20, 20, 10], [10, 10, 20]] as $ids) {
    check('direct super admin also ancestor ' . json_encode($ids), function () use ($ids): void {
        [$s, , $r] = fixture(); $s->roles = [10 => role([], null, true), 20 => role([], 10)]; $s->assignments[1] = $ids;
        same($r->can(1, 'unknown.action'), true);
    });
}
check('child cannot revoke parent by omitting permission', function (): void {
    [$s, , $r] = fixture(); $s->roles = [10 => role(['users.view']), 20 => role([], 10)]; $s->assignments[1] = [20];
    same($r->can(1, 'users.view'), true);
});
check('resolved permissions include grants and remove user denials', function (): void {
    [$s, , $r] = fixture(); $s->roles[10] = role(['users.view', 'users.delete']); $s->assignments[1] = [10];
    $s->overrides[1] = ['users.delete' => false, 'users.create' => true];
    permissionSet($r, 1, ['users.view', 'users.create']);
    $s->active[1] = false; $r->forgetUser(1); permissionSet($r, 1, []);
});
foreach ([999, 0, -1, PHP_INT_MIN, PHP_INT_MAX] as $id) {
    check('unknown or nonpositive user fails closed ' . $id, function () use ($id): void {
        [$s, , $r] = fixture(); $s->assignments[$id] = [10]; $s->roles[10] = role([], null, true); $s->overrides[$id] = ['users.view' => true];
        same($r->can($id, 'users.view'), false); permissionSet($r, $id, []); authorizationDenied($r, $id, 'users.view');
    });
}
foreach (['', '*', 'users.*', '*.view', 'users', '.view', 'users.', 'Users.view', 'users.View', 'users.view.extra', ' users.view', 'users.view ', "users.view\n", "users.view\0", 'users/view', 'users..view', '42'] as $slug) {
    check('malformed verification rejected ' . json_encode($slug), function () use ($slug): void {
        [$s, , $r] = fixture(); $s->roles[10] = role([], null, true); $s->assignments[1] = [10];
        denied(fn () => $r->can(1, $slug));
        authorizationDenied($r, 1, $slug);
    });
}
foreach ([false, true] as $all) {
    foreach ([['users.view'], ['users.delete'], ['users.view', 'users.delete'], ['users.delete', 'users.view'], ['users.view', 'users.view']] as $slugs) {
        check(($all ? 'canAll ' : 'canAny ') . json_encode($slugs), function () use ($all, $slugs): void {
            [$s, , $r] = fixture(); $s->overrides[1] = ['users.view' => true];
            $expected = $all ? !in_array('users.delete', $slugs, true) : in_array('users.view', $slugs, true);
            same($all ? $r->canAll(1, $slugs) : $r->canAny(1, $slugs), $expected);
        });
    }
    check(($all ? 'canAll' : 'canAny') . ' wildcard alone cannot authorize', function () use ($all): void {
        [$s, , $r] = fixture(); $s->roles[10] = role([], null, true); $s->assignments[1] = [10];
        denied(fn () => $all ? $r->canAll(1, ['users.*']) : $r->canAny(1, ['users.*']));
    });
}
foreach (['ordinary', 'super', 'parent'] as $kind) {
    check('soft deleted ' . $kind . ' grants nothing', function () use ($kind): void {
        [$s, , $r] = fixture(); $s->roles[10] = role(['users.view'], null, $kind === 'super');
        $s->roles[20] = role(['users.create'], 10); $s->deleted[10] = true; $s->assignments[1] = [$kind === 'parent' ? 20 : 10];
        same($r->can(1, 'users.view'), false); same($r->can(1, 'users.delete'), false);
    });
}
check('missing assigned role grants nothing', function (): void {
    [$s, , $r] = fixture(); $s->assignments[1] = [999]; same($r->can(1, 'users.view'), false);
});
check('cache reused across methods and resolver instances', function (): void {
    [$s, $c, $r] = fixture(); $s->overrides[1] = ['users.view' => true]; same($r->can(1, 'users.view'), true); $reads = $s->reads;
    $r = new Resolver($s, $c); same($r->canAny(1, ['users.view']), true); same($r->canAll(1, ['users.view']), true);
    $r->authorize(1, 'users.view'); permissionSet($r, 1, ['users.view']); same($s->reads, $reads);
});
foreach (['forgetUser', 'forgetUsers', 'forgetRole'] as $method) {
    foreach (['override', 'permission', 'assignment', 'deactivation', 'super', 'soft-delete'] as $change) {
        if ($method === 'forgetRole' && $change === 'assignment') { continue; }
        check('revocation next request ' . $method . '/' . $change, function () use ($method, $change): void {
            [$s, $c, $r] = fixture(); $s->roles[10] = role(['users.view'], null, $change === 'super');
            $s->roles[99] = role(['users.view']); $s->assignments = [1 => [10], 2 => [99]];
            same($r->can(1, 'users.view'), true); same($r->can(2, 'users.view'), true); $otherReads = $s->reads[2];
            switch ($change) {
                case 'override': $s->overrides[1]['users.view'] = false; break;
                case 'permission': $s->roles[10]['permissions'] = []; break;
                case 'assignment': $s->assignments[1] = []; break;
                case 'deactivation': $s->active[1] = false; break;
                case 'super': $s->roles[10] = role(); break;
                case 'soft-delete': $s->deleted[10] = true; break;
            }
            if ($method === 'forgetUser') { $r->forgetUser(1); }
            elseif ($method === 'forgetUsers') { $r->forgetUsers([1, 1, 999]); }
            else { $r->forgetRole(10); }
            same($r->can(1, 'users.view'), false);
            $r = new Resolver($s, $c); same($r->can(1, 'users.view'), false); permissionSet($r, 1, []);
            same($r->can(2, 'users.view'), true); same($s->reads[2], $otherReads);
        });
    }
}
check('forgetRole invalidates parent child grandchild and preserves outsiders', function (): void {
    [$s, $c, $r] = fixture(); $s->active[4] = true;
    $s->roles = [10 => role(['users.view']), 20 => role([], 10), 30 => role([], 20), 99 => role(['users.view'])];
    $s->assignments = [1 => [10], 2 => [20], 3 => [30, 10], 4 => [99]];
    foreach ([1, 2, 3, 4] as $id) { same($r->can($id, 'users.view'), true); }
    $otherReads = $s->reads[4]; $s->roles[10]['permissions'] = []; $r->forgetRole(10); $r = new Resolver($s, $c);
    foreach ([1, 2, 3] as $id) { same($r->can($id, 'users.view'), false); }
    same($r->can(4, 'users.view'), true); same($s->reads[4], $otherReads);
});
check('forgetUsers revokes all listed users only', function (): void {
    [$s, $c, $r] = fixture();
    foreach ([1, 2, 3] as $id) { $s->overrides[$id] = ['users.view' => true]; same($r->can($id, 'users.view'), true); }
    $otherReads = $s->reads[3]; $s->overrides[1] = $s->overrides[2] = []; $r->forgetUsers([1, 2, 1]); $r = new Resolver($s, $c);
    same($r->can(1, 'users.view'), false); same($r->can(2, 'users.view'), false); same($r->can(3, 'users.view'), true); same($s->reads[3], $otherReads);
});
check('new grant after cached default denial', function (): void {
    [$s, $c, $r] = fixture(); same($r->can(1, 'users.view'), false); $s->overrides[1] = ['users.view' => true]; $r->forgetUser(1);
    same((new Resolver($s, $c))->can(1, 'users.view'), true);
});
foreach (['can', 'canAny', 'canAll', 'authorize'] as $method) {
    check('trailing LF cannot bypass user denial via ' . $method, function () use ($method): void {
        [$s, , $r] = fixture(); $s->roles[10] = role([], null, true); $s->assignments[1] = [10];
        $s->overrides[1] = ['users.view' => false]; same($r->can(1, 'users.view'), false);
        if ($method === 'authorize') { authorizationDenied($r, 1, "users.view\n"); }
        else { denied(fn () => $r->$method(1, $method === 'can' ? "users.view\n" : ["users.view\n"])); }
    });
}
check('framework boundary grep (only known contract comment permitted)', function (): void {
    $output = []; $exit = 0;
    exec('rg -n -i --glob "*.php" "CodeIgniter|Shield" ' . escapeshellarg(dirname(__DIR__, 2) . '/src/Authorization') . ' 2>&1', $output, $exit);
    if (!in_array($exit, [0, 1], true)) { throw new RuntimeException('rg failed: ' . implode(' ', $output)); }
    $unexpected = array_values(array_filter($output, static fn ($line) => !str_ends_with(str_replace('\\', '/', $line), '/Contracts/AuthorizationStore.php:9: * arrives with the Shield integration; the resolver only knows this contract.')));
    same($unexpected, []);
});

// Observations without invented acceptance criteria.
$observations = [];
function observe(string $name, callable $call): void
{
    global $observations;
    try { $value = json_encode($call(), JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { $value = get_class($e) . ': ' . $e->getMessage(); }
    $observations[] = [$name, $value];
}
observe('Empty canAny/canAll, active then inactive', function (): array {
    [$s, , $r] = fixture(); $a = [$r->canAny(1, []), $r->canAll(1, [])]; $s->active[1] = false; $r->forgetUser(1);
    return [$a, [$r->canAny(1, []), $r->canAll(1, [])]];
});
observe('Super flag inherited without direct assignment', function (): bool {
    [$s, , $r] = fixture(); $s->roles = [10 => role([], null, true), 20 => role([], 10)]; $s->assignments[1] = [20]; return $r->can(1, 'users.view');
});
observe('Wildcard received from store: role grant', function (): array {
    [$s, , $r] = fixture(); $s->roles[10] = role(['users.*']); $s->assignments[1] = [10]; return [$r->can(1, 'users.view'), $r->permissions(1)];
});
foreach ([['users.view', 'users.*'], ['users.*', 'users.view']] as $slugs) {
    observe('Mixed valid/wildcard canAny ' . json_encode($slugs), function () use ($slugs): bool {
        [$s, , $r] = fixture(); $s->overrides[1] = ['users.view' => true]; return $r->canAny(1, $slugs);
    });
}
observe('Store declares zero and negative identifiers active', function (): array {
    [$s, , $r] = fixture(); $s->active[0] = $s->active[-1] = true; $s->overrides[0] = $s->overrides[-1] = ['users.view' => true]; return [$r->can(0, 'users.view'), $r->can(-1, 'users.view')];
});
observe('Role unassigned before forgetRole', function (): bool {
    [$s, $c, $r] = fixture(); $s->roles[10] = role(['users.view']); $s->assignments[1] = [10]; $r->can(1, 'users.view'); $s->assignments[1] = []; $r->forgetRole(10); return (new Resolver($s, $c))->can(1, 'users.view');
});
$failures = count(array_filter($results, fn ($row) => $row[1] === 'FAIL'));
$summary = count($results) . ' controls; ' . (count($results) - $failures) . ' PASS; ' . $failures . ' FAIL';
echo $summary . PHP_EOL;
$report = "# M2 — Independent acceptance report\n\nTarget: `2f5cde1fb6d87b9a140eff37a622e8b56744d339`. Runtime: PHP " . PHP_VERSION . ".\n\n$summary\n\nRun: `php tests/Integration/verify-m2.php` (no framework bootstrap, no database).\n\nTests derived from SPEC permission model/architecture, BRIEF M2/constraints/definition of done and public contracts. Resolver and existing tests were not read; runtime loads production classes through a minimal autoloader. E_ALL warnings become failures. Grep returns only framework-name matches; the sole match is a comment in the already-read AuthorizationStore contract. No framework usage found.\n\n## D1 — malformed slug accepted, negative override bypass\n\nA directly assigned super admin with user override `users.view => false` is correctly denied `users.view`, but is granted the malformed slug `users.view\\n` (a final LF byte). This also passes canAny/canAll and authorize does not throw. The specification requires area.action and negative overrides above super admin. Five failed controls reproduce this one defect. No root cause was inferred from source inspection. Exploitability in HTTP depends on how callers construct permission strings; the Resolver contract itself fails. M2 is not approved.\n\n| Control | Result | Detail |\n|---|---|---|\n";
foreach ($results as [$name, $status, $detail]) { $report .= '| ' . str_replace('|', '\\|', $name) . " | $status | " . str_replace(["\n", '|'], [' ', '\\|'], $detail) . " |\n"; }
$report .= "\n## Observations requiring specification decisions (not PASS/FAIL)\n\n| Probe | Observed |\n|---|---|\n";
foreach ($observations as [$name, $value]) { $report .= "| $name | " . str_replace('|', '\\|', $value) . " |\n"; }
$report .= "\n## Scope and ambiguities\n\n- The store returns a map slug => bool: simultaneous positive and negative overrides for one slug cannot be represented. PHP overwrites duplicate keys before the Resolver receives them. Do not manufacture a passing test; define conflict handling in the writing/adapter layer (M3).\n- Empty collections, inherited super flag, extended slug alphabet and super-admin permissions() enumeration are author proposals awaiting confirmation. Empty canAll for an inactive user also needs reconciliation with 'inactive denies everything'.\n- Wildcards are allowed at assignment, but the contract does not say whether the writer must expand them or the Resolver must interpret them. The observation is not scored.\n- For mixed valid/invalid lists, specify full input validation versus per-item short-circuit validation. Single malformed checks may return false or throw InvalidArgumentException; both reject access. authorize must throw.\n- No positive-ID precondition is declared. Unknown/zero/negative users reported inactive must deny; a store reporting them active is an observation, not an invented constraint.\n- Soft deletion filtering belongs to AuthorizationStore by contract. These tests verify resolver behavior with compliant filtering; database adapter filtering remains M3.\n- Removing assignments before forgetRole loses the former-user relationship. Writers must invalidate the former users with forgetUser/forgetUsers; define this obligation explicitly. This is recorded as an observation, not a passing forgetRole test.\n- Cycles are rejected at save time in M4, not asserted as a Resolver responsibility. Last-super-admin protection, actual write hooks, HTTP errors and framework integration are outside M2. Only installed PHP was run, not the full supported runtime matrix.\n- No source changes or commit. SPEC was not edited because the authorized write scope is tests/Integration and the coordination log.\n";
file_put_contents(__DIR__ . '/M2-REPORT.md', $report);
exit($failures === 0 ? 0 : 1);
