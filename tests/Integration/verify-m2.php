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
check('framework boundary scan in pure PHP (only known contract comment permitted)', function (): void {
    // Mechanical name scan only: never display implementation text or inspect its logic.
    $root = dirname(__DIR__, 2) . '/src/Authorization';
    $unexpected = []; $files = 0;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'php') { continue; }
        $files++;
        foreach (file($file->getPathname()) as $number => $line) {
            if (!preg_match('/CodeIgniter|Shield/i', $line)) { continue; }
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if ($relative === 'Contracts/AuthorizationStore.php' && trim($line) === '* arrives with the Shield integration; the resolver only knows this contract.') { continue; }
            $unexpected[] = $relative . ':' . ($number + 1);
        }
    }
    same($files > 0, true);
    same($unexpected, []);
});

$baselineResults = count($results);

// D1 variants: forbidden bytes at the beginning, middle and end of a slug.
$malformed = [];
foreach (array_merge(range(0, 31), range(127, 159)) as $byte) {
    $malformed[sprintf('raw byte %02X', $byte)] = chr($byte);
}
foreach ([0x85, 0xA0, 0x1680, 0x180E, 0x2000, 0x2001, 0x2002, 0x2003, 0x2004, 0x2005, 0x2006, 0x2007, 0x2008, 0x2009, 0x200A, 0x200B, 0x2028, 0x2029, 0x202F, 0x205F, 0x2060, 0x3000, 0xFEFF] as $codepoint) {
    $malformed[sprintf('Unicode U+%04X', $codepoint)] = json_decode(sprintf('"\\u%04x"', $codepoint), true, 512, JSON_THROW_ON_ERROR);
}
$malformed += [
    'CRLF' => "\r\n", 'double LF' => "\n\n", 'ASCII space' => ' ',
    'invalid UTF-8 FF' => "\xFF", 'invalid UTF-8 truncated' => "\xE2\x80",
    'invalid UTF-8 overlong LF' => "\xC0\x8A", 'invalid UTF-8 surrogate' => "\xED\xA0\x80",
    'invalid UTF-8 above Unicode range' => "\xF4\x90\x80\x80",
];
$slugVariants = [];
foreach ($malformed as $label => $bytes) {
    $slugVariants[$label] = [$bytes . 'users.view', 'users.' . $bytes . 'view', 'users.view' . $bytes];
}
$slugVariants['wildcard'] = ['users.*'];
foreach ([4096, 65536, 1048576] as $length) {
    $base = 'users.' . str_repeat('a', $length);
    $slugVariants['long action ' . $length . ' with forbidden suffix'] = [$base . "\n", $base . "\r", $base . "\xFF", $base . "\u{2028}"];
}
foreach ($slugVariants as $label => $variants) {
    foreach (['can', 'canAny', 'canAll', 'authorize'] as $method) {
        check('D1 variant ' . $label . ' via ' . $method, function () use ($variants, $method): void {
            [$s, , $r] = fixture(); $s->roles[10] = role([], null, true); $s->assignments[1] = [10];
            $s->overrides[1] = ['users.view' => false];
            same($r->can(1, 'users.view'), false);
            foreach ($variants as $slug) {
                if ($method === 'authorize') { authorizationDenied($r, 1, $slug); }
                else { denied(fn () => $r->$method(1, $method === 'can' ? $slug : [$slug])); }
            }
        });
    }
    foreach (['canAny', 'canAll'] as $method) {
        check('full mixed-list validation ' . $label . ' via ' . $method, function () use ($variants, $method): void {
            foreach (['ordinary', 'super', 'inactive'] as $state) {
                [$s, , $r] = fixture(); $s->active[1] = $state !== 'inactive';
                $s->roles[10] = role([], null, $state === 'super'); $s->assignments[1] = [10];
                $s->overrides[1] = ['users.view' => false, 'users.create' => true];
                foreach ($variants as $slug) {
                    foreach (['users.create', 'users.view'] as $valid) {
                        foreach ([[$slug, $valid, $valid], [$valid, $slug, $valid], [$valid, $valid, $slug]] as $list) {
                            // A false short-circuit alone cannot prove that the malformed member was validated.
                            try { $r->$method(1, $list); }
                            catch (InvalidArgumentException) { continue; }
                            throw new RuntimeException('malformed list member not reported: ' . $state);
                        }
                    }
                }
                same($r->can(1, 'users.view'), false);
                same($r->can(1, 'users.create'), $state !== 'inactive');
            }
        });
    }
}

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
observe('Store declares zero and negative identifiers active', function (): array {
    [$s, , $r] = fixture(); $s->active[0] = $s->active[-1] = true; $s->overrides[0] = $s->overrides[-1] = ['users.view' => true]; return [$r->can(0, 'users.view'), $r->can(-1, 'users.view')];
});
observe('Role unassigned before forgetRole', function (): bool {
    [$s, $c, $r] = fixture(); $s->roles[10] = role(['users.view']); $s->assignments[1] = [10]; $r->can(1, 'users.view'); $s->assignments[1] = []; $r->forgetRole(10); return (new Resolver($s, $c))->can(1, 'users.view');
});
observe('Super-admin permissions enumeration', function (): array {
    [$s, , $r] = fixture(); $s->roles[10] = role(['users.view'], null, true); $s->assignments[1] = [10];
    return [$r->permissions(1), $r->can(1, 'unknown.action')];
});
foreach ([4096, 65536, 1048576] as $length) {
    foreach (['can', 'canAny', 'canAll', 'authorize'] as $method) {
        observe('Long alphabetic slug, action bytes ' . $length . ' via ' . $method, function () use ($length, $method): mixed {
            [$s, , $r] = fixture(); $s->roles[10] = role([], null, true); $s->assignments[1] = [10];
            $slug = 'users.' . str_repeat('a', $length);
            return $r->$method(1, in_array($method, ['canAny', 'canAll'], true) ? [$slug] : $slug);
        });
    }
}
foreach (['users.read_all', 'users.read-all', 'users.read2', "utenti.\u{00E8}dit", "\u{7528}\u{6237}.view"] as $slug) {
    observe('Unspecified slug alphabet ' . json_encode($slug), function () use ($slug): bool {
        [$s, , $r] = fixture(); $s->roles[10] = role([], null, true); $s->assignments[1] = [10];
        return $r->can(1, $slug);
    });
}
$failures = count(array_filter($results, fn ($row) => $row[1] === 'FAIL'));
$baselineFailures = count(array_filter(array_slice($results, 0, $baselineResults), fn ($row) => $row[1] === 'FAIL'));
$lfResults = array_filter($results, fn ($row) => str_starts_with($row[0], 'trailing LF cannot') || $row[0] === 'malformed verification rejected "users.view\n"');
$lfFailures = count(array_filter($lfResults, fn ($row) => $row[1] === 'FAIL'));
$summary = count($results) . ' controls; ' . (count($results) - $failures) . ' PASS; ' . $failures . ' FAIL';
$target = trim((string) shell_exec('git rev-parse HEAD'));
$baseline = $baselineResults . ' original controls; ' . ($baselineResults - $baselineFailures) . ' PASS; ' . $baselineFailures . ' FAIL';
$lfSummary = count($lfResults) . ' original final-LF controls; ' . (count($lfResults) - $lfFailures) . ' PASS; ' . $lfFailures . ' FAIL';
echo $summary . PHP_EOL . $baseline . PHP_EOL . $lfSummary . PHP_EOL;
foreach ($observations as [$name, $value]) { echo "OBSERVATION (unscored) $name: $value" . PHP_EOL; }
$verdict = $failures === 0 ? 'M2 approved within the tested scope. No reproducible defects found.' : 'M2 not approved: see failed controls below.';
$d1 = count($lfResults) === 5 && $lfFailures === 0 ? 'D1 closed: all five original final-LF controls pass.' : 'D1 remains unresolved: see original final-LF controls.';
$report = "# M2 — Independent re-verification report\n\nTarget: `$target`. Runtime: PHP " . PHP_VERSION . ".\n\n$summary\n\n$baseline\n\n$lfSummary\n\n$verdict\n\n## Method and D1 re-verification\n\nRun: `php tests/Integration/verify-m2.php` (no framework bootstrap, no database). Output captured in `m2-output.txt`. Counts refer to named controls; parameter combinations inside a control are not counted separately. Observations are excluded from all totals.\n\nTests derive from SPEC permission model/architecture, BRIEF M2/constraints/definition of done, the two public contracts and the user's explicit full-list validation requirement. The 08:10 correction log was read. Resolver implementation and unit tests were not opened or inspected. Production classes are loaded only for black-box execution. The separately requested mechanical framework-name scan reads PHP files without exposing source text; it reports only unexpected paths/line numbers, allowing the exact known contract comment. It requires neither rg nor grep; this lexical check is not a complete dependency audit. E_ALL warnings become failures.\n\n$d1\n\nAdditional probes cover every C0 byte, DEL and raw C1 byte; UTF-8 U+0085, U+2028/U+2029, Unicode spaces and invisible separators; CRLF, repeated LF, malformed UTF-8; leading, embedded and trailing positions. Each family runs through can, canAny, canAll and authorize with a directly assigned super admin and an explicit user denial. Slugs with action lengths 4096, 65536 and 1048576 bytes plus malformed suffixes are also checked.\n\nMixed lists contain valid granted or denied slugs and one malformed slug in first, middle or last position, for ordinary, super-admin and inactive users. Both canAny and canAll must report InvalidArgumentException even after a member that would otherwise short-circuit. This checks the requested full-list validation regression, including cached calls. Single malformed checks may deny or throw InvalidArgumentException; authorize must throw without warnings. The precise exception type for list validation is a regression expectation for this correction, not a claim that SPEC already defines it.\n\n## Controls\n\n| Control | Result | Detail |\n|---|---|---|\n";
foreach ($results as [$name, $status, $detail]) { $report .= '| ' . str_replace('|', '\\|', $name) . " | $status | " . str_replace(["\n", '|'], [' ', '\\|'], $detail) . " |\n"; }
$report .= "\n## Observations requiring specification decisions (not defects; not PASS/FAIL)\n\n| Probe | Observed |\n|---|---|\n";
foreach ($observations as [$name, $value]) { $report .= "| $name | " . str_replace('|', '\\|', $value) . " |\n"; }
$report .= "\n## Scope and decisions still open\n\n- Empty canAny/canAll semantics remain undecided, particularly canAll([]) for inactive users. No FAIL is assigned.\n- Inheritance of the super-admin flag and permissions() enumeration for a super admin remain specification decisions; the observed behavior is not a defect.\n- Extended slug alphabet (digits, underscores, hyphens, Unicode letters) and maximum slug length are unspecified. Long alphabetic slugs are observations across all four methods, without inventing a size limit. Malformed suffixes still must not authorize.\n- Wildcards are allowed at assignment, but the documents do not say whether the writer expands them or the Resolver interprets them. The store wildcard probe is unscored.\n- Simultaneous opposite overrides cannot be represented by the contract's slug-to-bool map: PHP replaces duplicate keys. Define conflict handling in the M3 writer/adapter, not through a fabricated Resolver test.\n- No positive-ID precondition is declared. A store reporting zero or negative IDs active remains an unscored observation.\n- Full mixed-list validation is explicitly requested for this re-verification and is tested above; synchronize this requirement and its error response into the specification. It is no longer presented as an unresolved short-circuit choice in this report.\n- After assignment removal, forgetRole cannot discover former holders through the store. Define the M3 writer obligation to invalidate former IDs with forgetUser/forgetUsers. This is an integration obligation to specify, not a Resolver FAIL.\n- Soft-delete filtering is an AuthorizationStore responsibility. Tests supply compliant filtering; actual database adapter filtering and write hooks remain M3. Cycles and last-super-admin protection remain M4; HTTP paths remain later milestones. Only PHP " . PHP_VERSION . " was run, not the supported runtime matrix.\n- No source edits, database access or commit. SPEC is unchanged because writes are restricted to tests/Integration and _AI-LOG.md. The pre-existing modification to CLAUDE.md was left intact.\n";
file_put_contents(__DIR__ . '/M2-REPORT.md', $report);
exit($failures === 0 ? 0 : 1);
