<?php

declare(strict_types=1);

namespace RoleWarden\Install;

use Closure;
use PhpToken;

/**
 * Safe, reversible edits to the host's config files (Filters, Auth, Routes).
 *
 * The with* methods are pure: they take a file's content and return the new
 * content, or null when the shape is not recognised or the change is already
 * there. Null means the wizard shows manual instructions instead: a file is
 * never rewritten as a whole, only the recognised value is replaced, so the
 * rest stays byte for byte (line endings included).
 */
final class ConfigEditor
{
    private const GLOBAL_FILTERS = ['session', 'group', 'permission'];
    private const USER_PROVIDER  = 'RoleWarden\Models\UserModel';
    private const LOGIN_VIEW     = '\RoleWarden\Views\auth\login';
    private const LOGIN_REDIRECT = 'rolewarden';
    private const REGISTRAR      = '\RoleWarden\Config\RouteRegistrar::register($routes);';
    private const MODIFIERS      = [T_PUBLIC, T_PROTECTED, T_PRIVATE, T_VAR, T_STATIC, T_READONLY, T_STRING, T_ARRAY, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED];

    private static ?Closure $linter = null;

    /**
     * The global "before" filter among session, group and permission, and its exceptions.
     *
     * @return array{filter: string, except: list<string>}|null
     */
    public static function globalFilterExcepts(string $filtersPhp): ?array
    {
        $f = self::findGlobalFilter(PhpToken::tokenize($filtersPhp));

        return $f === null ? null : ['filter' => $f['filter'], 'except' => $f['except']];
    }

    /** @param list<string> $paths */
    public static function withFilterExcept(string $filtersPhp, array $paths): ?string
    {
        $t = PhpToken::tokenize($filtersPhp);
        $f = self::findGlobalFilter($t);

        if ($f === null) {
            return null;
        }

        $new = array_values(array_diff(array_unique($paths), $f['except']));

        if ($new === []) {
            return null;
        }

        $list = implode(', ', array_map(self::quote(...), $new));

        switch ($f['kind']) {
            case 'bare': // 'session' as a plain list value
                return self::splice($filtersPhp, $t, $f['at'], $f['at'], $t[$f['at']]->text . " => ['except' => [" . $list . ']]');

            case 'noexcept': // 'session' => []
                return self::splice($filtersPhp, $t, $f['at'], $f['close'], "['except' => [" . $list . ']]');

            case 'string': // 'except' => 'login*'
                return self::splice($filtersPhp, $t, $f['at'], $f['at'], '[' . $t[$f['at']]->text . ', ' . $list . ']');
        }

        // 'except' => [...]
        $entries = $f['entries'];

        if ($entries === []) {
            return self::splice($filtersPhp, $t, $f['at'], $f['close'], '[' . $list . ']');
        }

        $last  = $entries[count($entries) - 1];
        $start = $t[$f['at']]->pos;

        if (! str_contains(substr($filtersPhp, $start, $t[$f['close']]->pos - $start), "\n")) {
            return self::insert($filtersPhp, [[self::end($t[$last['e']]), ', ' . $list]]);
        }

        // Multi-line: one path per line, same indentation as the last entry.
        $eol    = self::eol($filtersPhp);
        $indent = self::indent($filtersPhp, $t[$last['s']]->pos);
        $lines  = array_map(static fn (string $p) => $eol . $indent . self::quote($p), $new);
        $anchor = self::afterLineComment($t, $last['comma'] ?? $last['e']);

        if ($last['comma'] !== null) {
            return self::insert($filtersPhp, [[$anchor, implode(',', $lines) . ',']]);
        }

        return self::insert($filtersPhp, [[self::end($t[$last['e']]), ','], [$anchor, implode(',', $lines)]]);
    }

    public static function withUserProvider(string $authPhp): ?string
    {
        $t = PhpToken::tokenize($authPhp);
        $v = self::property($t, 'userProvider');

        if ($v === null || self::providerIsOurs($authPhp, $t, $v)) {
            return null;
        }

        return self::splice($authPhp, $t, $v[0], $v[1], '\\' . self::USER_PROVIDER . '::class');
    }

    public static function withLoginView(string $authPhp): ?string
    {
        return self::withEntry($authPhp, 'views', 'login', self::LOGIN_VIEW);
    }

    public static function withLoginRedirect(string $authPhp): ?string
    {
        return self::withEntry($authPhp, 'redirects', 'login', self::LOGIN_REDIRECT);
    }

    /**
     * The has* checks tell "already applied" (true) from "missing or not
     * recognised" (false), which the with* methods both report as null.
     *
     * @param list<string> $paths
     */
    public static function hasFilterExcept(string $filtersPhp, array $paths): bool
    {
        $f = self::globalFilterExcepts($filtersPhp);

        return $f !== null && array_diff($paths, $f['except']) === [];
    }

    public static function hasUserProvider(string $authPhp): bool
    {
        $t = PhpToken::tokenize($authPhp);
        $v = self::property($t, 'userProvider');

        return $v !== null && self::providerIsOurs($authPhp, $t, $v);
    }

    public static function hasLoginView(string $authPhp): bool
    {
        return self::entryIs($authPhp, 'views', 'login', self::LOGIN_VIEW) === true;
    }

    public static function hasLoginRedirect(string $authPhp): bool
    {
        return self::entryIs($authPhp, 'redirects', 'login', self::LOGIN_REDIRECT) === true;
    }

    public static function hasRouteRegistrar(string $routesPhp): bool
    {
        return str_contains($routesPhp, 'RouteRegistrar::register(');
    }

    public static function withRouteRegistrar(string $routesPhp): ?string
    {
        if (self::hasRouteRegistrar($routesPhp)) {
            return null;
        }

        $eol = self::eol($routesPhp);

        if (preg_match('/^([ \t]*)service\(\s*[\'"]auth[\'"]\s*\)\s*->\s*routes\(\s*\$routes\b[^;\r\n]*\);[^\r\n]*/m', $routesPhp, $m, PREG_OFFSET_CAPTURE) === 1) {
            return self::insert($routesPhp, [[$m[0][1] + strlen($m[0][0]), $eol . $m[1][0] . self::REGISTRAR]]);
        }

        if (preg_match('/\?>\s*$/', $routesPhp) === 1) {
            return null;
        }

        $tail = str_ends_with($routesPhp, "\n") ? '' : $eol;

        return $routesPhp . $tail . $eol . self::REGISTRAR . $eol;
    }

    /** Unified diff, line by line, three lines of context. Empty when nothing changes. */
    public static function preview(string $before, string $after): string
    {
        if ($before === $after) {
            return '';
        }

        $a = self::lines($before);
        $b = self::lines($after);
        $n = count($a);
        $m = count($b);

        $p = 0;
        while ($p < $n && $p < $m && $a[$p] === $b[$p]) {
            $p++;
        }
        $s = 0;
        while ($s < $n - $p && $s < $m - $p && $a[$n - 1 - $s] === $b[$m - 1 - $s]) {
            $s++;
        }

        // LCS on the part that differs.
        $x  = array_slice($a, $p, $n - $p - $s);
        $y  = array_slice($b, $p, $m - $p - $s);
        $dp = array_fill(0, count($x) + 1, array_fill(0, count($y) + 1, 0));
        for ($i = count($x) - 1; $i >= 0; $i--) {
            for ($j = count($y) - 1; $j >= 0; $j--) {
                $dp[$i][$j] = $x[$i] === $y[$j] ? $dp[$i + 1][$j + 1] + 1 : max($dp[$i + 1][$j], $dp[$i][$j + 1]);
            }
        }

        $ops = [];
        foreach (array_slice($a, 0, $p) as $l) {
            $ops[] = [' ', $l];
        }
        for ($i = 0, $j = 0; $i < count($x) || $j < count($y);) {
            if ($i < count($x) && $j < count($y) && $x[$i] === $y[$j]) {
                $ops[] = [' ', $x[$i++]];
                $j++;
            } elseif ($j >= count($y) || ($i < count($x) && $dp[$i + 1][$j] >= $dp[$i][$j + 1])) {
                $ops[] = ['-', $x[$i++]];
            } else {
                $ops[] = ['+', $y[$j++]];
            }
        }
        foreach (array_slice($a, $n - $s) as $l) {
            $ops[] = [' ', $l];
        }

        // Group changes into hunks.
        $ranges = [];
        foreach ($ops as $k => $op) {
            if ($op[0] === ' ') {
                continue;
            }
            $from = max(0, $k - 3);
            $to   = min(count($ops) - 1, $k + 3);
            if ($ranges !== [] && $from <= $ranges[count($ranges) - 1][1] + 1) {
                $ranges[count($ranges) - 1][1] = $to;
            } else {
                $ranges[] = [$from, $to];
            }
        }

        $out = "--- before\n+++ after\n";
        foreach ($ranges as [$from, $to]) {
            $old = $new = 0;
            foreach (array_slice($ops, 0, $from) as $op) {
                $old += $op[0] !== '+' ? 1 : 0;
                $new += $op[0] !== '-' ? 1 : 0;
            }
            $body = '';
            $oc   = $nc = 0;
            foreach (array_slice($ops, $from, $to - $from + 1) as $op) {
                $oc += $op[0] !== '+' ? 1 : 0;
                $nc += $op[0] !== '-' ? 1 : 0;
                $body .= $op[0] . $op[1] . "\n";
            }
            $out .= sprintf("@@ -%d,%d +%d,%d @@\n", $old + ($oc > 0 ? 1 : 0), $oc, $new + ($nc > 0 ? 1 : 0), $nc) . $body;
        }

        return $out;
    }

    /**
     * Backup to <path>.rolewarden-bak (only once: it keeps the pristine original,
     * the log keeps every later `before`), write, syntax check. On a failed check
     * the original content is written back and false returned; if even that fails,
     * RestoreFailedException. The edit is logged only on success, with checked
     * false when the check could not run.
     */
    public static function apply(string $path, string $after, SetupState $state): bool
    {
        $before = @file_get_contents($path);
        $backup = $path . '.rolewarden-bak';

        if ($before === false || (! is_file($backup) && ! @copy($path, $backup))) {
            return false;
        }

        $ok = @file_put_contents($path, $after) === false ? false : (self::$linter ?? self::lint(...))($path);

        if ($ok === false) {
            if (@file_put_contents($path, $before) !== strlen($before)) {
                throw new RestoreFailedException($path, $backup);
            }

            return false;
        }

        $state->recordEdit($path, $before, $after, $ok === true);

        return true;
    }

    /**
     * Tests only: replace the `php -l` check (the closure gets the path and
     * returns true, false, or null for "could not run"). Null restores it.
     *
     * @internal
     */
    public static function useLinter(?Closure $linter): void
    {
        self::$linter = $linter;
    }

    /** True/false from `php -l`, null when it cannot run. */
    private static function lint(string $path): ?bool
    {
        // Only a CLI or CGI binary: under FPM PHP_BINARY is php-fpm, which must not be started.
        if (! function_exists('proc_open') || ! is_executable(PHP_BINARY)
            || preg_match('/^php(-cgi)?[\d.]*(\.exe)?$/i', basename(PHP_BINARY)) !== 1) {
            return null;
        }

        $proc = @proc_open([PHP_BINARY, '-n', '-l', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($proc)) {
            return null;
        }

        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        return $code === -1 ? null : $code === 0;
    }

    private static function withEntry(string $php, string $prop, string $key, string $value): ?string
    {
        $t = PhpToken::tokenize($php);
        $e = self::arrayEntry($t, $prop, $key);

        if ($e === null || self::entryIs($php, $prop, $key, $value) !== false) {
            return null;
        }

        return self::splice($php, $t, $e['s'], $e['e'], self::quote($value));
    }

    /** Whether $prop[$key] is the string $value; null when the entry is not found. */
    private static function entryIs(string $php, string $prop, string $key, string $value): ?bool
    {
        $t = PhpToken::tokenize($php);
        $e = self::arrayEntry($t, $prop, $key);

        return $e === null ? null : $e['s'] === $e['e'] && self::literal($t[$e['s']]) === $value;
    }

    /**
     * @param list<PhpToken> $t
     *
     * @return array{key: ?string, keyed: bool, s: int, e: int, comma: ?int}|null
     */
    private static function arrayEntry(array $t, string $prop, string $key): ?array
    {
        $v = self::property($t, $prop);

        if ($v === null || $t[$v[0]]->text !== '[') {
            return null;
        }

        $arr = self::parseArray($t, $v[0]);

        return $arr === null ? null : self::entry($arr['entries'], $key);
    }

    /**
     * @param list<PhpToken> $t
     * @param array{int, int} $v
     */
    private static function providerIsOurs(string $php, array $t, array $v): bool
    {
        $start = $t[$v[0]]->pos;

        return self::resolveClass($php, substr($php, $start, self::end($t[$v[1]]) - $start)) === self::USER_PROVIDER;
    }

    /**
     * @param list<PhpToken> $t
     *
     * @return array{filter: string, except: list<string>, kind: string, at: int, close: int, entries: list<array{key: ?string, keyed: bool, s: int, e: int, comma: ?int}>}|null
     */
    private static function findGlobalFilter(array $t): ?array
    {
        $g = self::property($t, 'globals');

        if ($g === null || $t[$g[0]]->text !== '[') {
            return null;
        }

        $globals = self::parseArray($t, $g[0]);
        $before  = $globals === null ? null : self::entry($globals['entries'], 'before');
        $list    = $before !== null && $t[$before['s']]->text === '[' ? self::parseArray($t, $before['s']) : null;

        if ($list === null) {
            return null;
        }

        foreach (self::GLOBAL_FILTERS as $name) {
            foreach ($list['entries'] as $en) {
                $label = $en['keyed'] ? $en['key'] : ($en['s'] === $en['e'] ? self::literal($t[$en['s']]) : null);

                if ($label === null || ($label !== $name && ! str_starts_with($label, $name . ':'))) {
                    continue;
                }

                $found = ['filter' => $label, 'except' => [], 'kind' => 'bare', 'at' => $en['s'], 'close' => $en['s'], 'entries' => []];

                if (! $en['keyed']) {
                    return $found;
                }

                $opts = $t[$en['s']]->text === '[' ? self::parseArray($t, $en['s']) : null;

                if ($opts === null) {
                    return null;
                }

                $ex = self::entry($opts['entries'], 'except');

                if ($ex === null) {
                    return $opts['entries'] === [] ? ['kind' => 'noexcept', 'close' => $opts['close']] + $found : null;
                }

                if ($ex['s'] === $ex['e'] && ($one = self::literal($t[$ex['s']])) !== null) {
                    return ['except' => [$one], 'kind' => 'string', 'at' => $ex['s']] + $found;
                }

                $arr = $t[$ex['s']]->text === '[' ? self::parseArray($t, $ex['s']) : null;

                if ($arr === null) {
                    return null;
                }

                $except = [];
                foreach ($arr['entries'] as $x) {
                    $lit = $x['keyed'] || $x['s'] !== $x['e'] ? null : self::literal($t[$x['s']]);

                    if ($lit === null) {
                        return null;
                    }
                    $except[] = $lit;
                }

                return ['except' => $except, 'kind' => 'array', 'at' => $ex['s'], 'close' => $arr['close'], 'entries' => $arr['entries']] + $found;
            }
        }

        return null;
    }

    /**
     * Token range [first, last] of a property's default value, or null when the
     * property is missing or declared more than once.
     *
     * @param list<PhpToken> $t
     *
     * @return array{int, int}|null
     */
    private static function property(array $t, string $name): ?array
    {
        $found = null;

        foreach ($t as $i => $tok) {
            if (! $tok->is(T_VARIABLE) || $tok->text !== '$' . $name) {
                continue;
            }

            $prev = self::next($t, $i, -1);
            $eq   = self::next($t, $i, 1);

            if ($prev === null || $eq === null || ! $t[$prev]->is(self::MODIFIERS) || $t[$eq]->text !== '=') {
                continue;
            }

            $s   = self::next($t, $eq, 1);
            $end = $s === null ? null : self::exprEnd($t, $s);

            if ($end === null || ! in_array($t[$end[1]]->text, [';', ','], true)) {
                continue; // e.g. a parameter default
            }

            if ($found !== null) {
                return null;
            }

            $found = [$s, $end[0]];
        }

        return $found;
    }

    /**
     * Top-level entries of the array literal opened at $open.
     *
     * @param list<PhpToken> $t
     *
     * @return array{close: int, entries: list<array{key: ?string, keyed: bool, s: int, e: int, comma: ?int}>}|null
     */
    private static function parseArray(array $t, int $open): ?array
    {
        $entries = [];
        $j       = $open;

        while (true) {
            $s = self::next($t, $j, 1);

            if ($s === null) {
                return null;
            }

            if ($t[$s]->text === ']') {
                return ['close' => $s, 'entries' => $entries];
            }

            $end = self::exprEnd($t, $s);

            if ($end === null) {
                return null;
            }

            $key   = null;
            $keyed = $t[$end[1]]->text === '=>';

            if ($keyed) {
                $key = $s === $end[0] ? self::literal($t[$s]) : null;
                $s   = self::next($t, $end[1], 1);
                $end = $s === null ? null : self::exprEnd($t, $s);

                if ($end === null || $t[$end[1]]->text === '=>') {
                    return null;
                }
            }

            $stop      = $t[$end[1]]->text;
            $entries[] = ['key' => $key, 'keyed' => $keyed, 's' => $s, 'e' => $end[0], 'comma' => $stop === ',' ? $end[1] : null];

            if ($stop === ']') {
                return ['close' => $end[1], 'entries' => $entries];
            }

            if ($stop !== ',') {
                return null;
            }

            $j = $end[1];
        }
    }

    /**
     * From $s, the last significant token of the expression and the token that ends it
     * (`,`, `;`, `=>` or an unmatched closing bracket).
     *
     * @param list<PhpToken> $t
     *
     * @return array{int, int}|null
     */
    private static function exprEnd(array $t, int $s): ?array
    {
        $depth = 0;
        $last  = null;

        for ($j = $s, $c = count($t); $j < $c; $j++) {
            $tok = $t[$j];

            if ($tok->isIgnorable()) {
                continue;
            }

            if (in_array($tok->text, [']', ')', '}'], true)) {
                if ($depth === 0) {
                    return $last === null ? null : [$last, $j];
                }
                $depth--;
            } elseif ($depth === 0 && in_array($tok->text, [',', ';', '=>'], true)) {
                return $last === null ? null : [$last, $j];
            } elseif (in_array($tok->text, ['[', '(', '{'], true) || $tok->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE])) {
                $depth++;
            }

            $last = $j;
        }

        return null;
    }

    /**
     * @param list<array{key: ?string, keyed: bool, s: int, e: int, comma: ?int}> $entries
     *
     * @return array{key: ?string, keyed: bool, s: int, e: int, comma: ?int}|null
     */
    private static function entry(array $entries, string $key): ?array
    {
        $hits = array_values(array_filter($entries, static fn (array $e) => $e['key'] === $key));

        return count($hits) === 1 ? $hits[0] : null;
    }

    /** @param list<PhpToken> $t */
    private static function next(array $t, int $i, int $dir): ?int
    {
        for ($i += $dir; isset($t[$i]); $i += $dir) {
            if (! $t[$i]->isIgnorable()) {
                return $i;
            }
        }

        return null;
    }

    /** Value of a plain string literal, null for anything else. */
    private static function literal(PhpToken $tok): ?string
    {
        if (! $tok->is(T_CONSTANT_ENCAPSED_STRING)) {
            return null;
        }

        $inner = substr($tok->text, 1, -1);

        if ($tok->text[0] === "'") {
            return strtr($inner, ['\\\\' => '\\', "\\'" => "'"]);
        }

        return str_contains($inner, '$') ? null : stripcslashes($inner);
    }

    private static function quote(string $s): string
    {
        // Backslashes doubled only where single quotes need it, so the view path stays verbatim.
        return "'" . str_replace("'", "\\'", (string) preg_replace('/\\\\(?=[\\\\\']|$)/', '\\\\\\\\', $s)) . "'";
    }

    /** Fully qualified class of `X::class` or a class string, through the file's `use` imports. */
    private static function resolveClass(string $php, string $expr): ?string
    {
        if (preg_match('/^([\'"])\\\\?([\w\\\\]+)\1$/', $expr, $m) === 1) {
            return $m[2];
        }

        if (preg_match('/^(\\\\?)([\w\\\\]+)::class$/', $expr, $m) !== 1) {
            return null;
        }

        if ($m[1] === '\\' || str_contains($m[2], '\\')) {
            return $m[2];
        }

        preg_match_all('/^use\s+\\\\?([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/mi', $php, $uses, PREG_SET_ORDER);

        foreach ($uses as $u) {
            $alias = ($u[2] ?? '') !== '' ? $u[2] : substr((string) strrchr('\\' . $u[1], '\\'), 1);

            if (strcasecmp($alias, $m[2]) === 0) {
                return $u[1];
            }
        }

        return null;
    }

    /**
     * Past a `//` or `#` comment on the same line after token $i.
     *
     * @param list<PhpToken> $t
     */
    private static function afterLineComment(array $t, int $i): int
    {
        $j = $i + 1;

        if (isset($t[$j]) && $t[$j]->is(T_WHITESPACE) && ! str_contains($t[$j]->text, "\n")) {
            $j++;
        }

        if (isset($t[$j]) && $t[$j]->is(T_COMMENT) && ! str_starts_with($t[$j]->text, '/*')) {
            return $t[$j]->pos + strlen(rtrim($t[$j]->text, "\r\n"));
        }

        return self::end($t[$i]);
    }

    /** @param list<PhpToken> $t */
    private static function splice(string $php, array $t, int $from, int $to, string $text): string
    {
        return substr($php, 0, $t[$from]->pos) . $text . substr($php, self::end($t[$to]));
    }

    /** @param list<array{int, string}> $inserts byte offset and text, in ascending order */
    private static function insert(string $php, array $inserts): string
    {
        foreach (array_reverse($inserts) as [$at, $text]) {
            $php = substr($php, 0, $at) . $text . substr($php, $at);
        }

        return $php;
    }

    private static function end(PhpToken $tok): int
    {
        return $tok->pos + strlen($tok->text);
    }

    private static function indent(string $php, int $pos): string
    {
        $start = strrpos(substr($php, 0, $pos), "\n");
        $start = $start === false ? 0 : $start + 1;

        return substr($php, $start, strspn($php, " \t", $start));
    }

    private static function eol(string $php): string
    {
        return str_contains($php, "\r\n") ? "\r\n" : "\n";
    }

    /** @return list<string> */
    private static function lines(string $s): array
    {
        $lines = preg_split('/\r\n|\n/', $s) ?: [];

        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }
}
