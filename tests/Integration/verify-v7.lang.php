<?php
/**
 * V7 language completeness (Collaudatore ad Hoc): compares the English and Italian language files
 * of the module zip (the interface text only, not logic): same keys, same placeholders, no line
 * left in English, no empty value. Usage: php verify-v7.lang.php <module-dir>
 */
declare(strict_types=1);
$dir = rtrim($argv[1] ?? '', '/\\');
$en = require "$dir/src/Language/en/RoleWarden.php";
$it = require "$dir/src/Language/it/RoleWarden.php";
function flat(array $a, string $p = ''): array { $o = []; foreach ($a as $k => $v) { if (is_array($v)) $o += flat($v, "$p$k."); else $o["$p$k"] = (string) $v; } return $o; }
function ph(string $s): array {
    preg_match_all('/\{[^{}]*\}|%[sd]|:[a-z_]+\b/', $s, $m);
    $o = $m[0]; sort($o); return $o;
}
$E = flat($en); $I = flat($it);
$res = ['en_keys' => count($E), 'it_keys' => count($I), 'missing_in_it' => array_values(array_diff(array_keys($E), array_keys($I))), 'extra_in_it' => array_values(array_diff(array_keys($I), array_keys($E))), 'placeholders' => [], 'identical' => [], 'empty' => []];
foreach ($E as $k => $v) {
    if (! isset($I[$k])) continue;
    if (ph($v) !== ph($I[$k])) $res['placeholders'][$k] = [$v, $I[$k]];
    if ($v === $I[$k] && preg_match('/[a-z]{3,}/i', $v)) $res['identical'][$k] = $v;
    if (trim($I[$k]) === '' && trim($v) !== '') $res['empty'][] = $k;
}
echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
