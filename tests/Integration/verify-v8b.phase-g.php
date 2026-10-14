<?php
// Phase G: logs at threshold 9 and the PHP server output (error_reporting=-1) of every copy used by this run.
$lines = [];
foreach ($APPS as $a) $lines = array_merge($lines, logs_of($a));
$bad = bad_log_lines($lines);
// rejections the suite causes on purpose: CSRF token missing or wrong (L10, L10b)
$induced = array_values(array_filter($bad, fn ($l) => preg_match('/SecurityException|action you requested is not allowed/i', $l)));
$other = array_values(array_diff($bad, $induced));
info('log lines at WARNING or above caused on purpose (CSRF refusals): ' . count($induced));
check('G01', 'Application logs (threshold 9): no WARNING, ERROR or CRITICAL line besides the CSRF refusals the suite caused', $other === [], implode("\n", array_slice($other, 0, 10)));
$srv = '';
foreach ($SERVERLOGS as $f) $srv .= (string) @file_get_contents($f);
$php = preg_grep('/(PHP (Warning|Notice|Deprecated|Fatal)|Stack trace|Uncaught)/', explode("\n", $srv));
check('G02', 'PHP built-in server output: no PHP warning, notice, deprecation or fatal error', $php === [], implode("\n", array_slice($php, 0, 10)));
check('G03', 'No response of the run had a 500, SQL text or PHP error on screen (' . count($SEEN) . ' responses)', ! array_filter($SEEN, fn ($s) => $s[1] >= 500 || leaks_sql($s[3]) || phperr($s[3])), json_encode(array_map(fn ($s) => [$s[0], $s[1]], array_slice(array_filter($SEEN, fn ($s) => $s[1] >= 500 || leaks_sql($s[3]) || phperr($s[3])), 0, 5))));
foreach ($APPS as $a) foreach (glob("$a/writable/logs/*.log") ?: [] as $f) @copy($f, "$WORK/verify-v8b.applog-" . basename($a) . '-' . basename($f));
