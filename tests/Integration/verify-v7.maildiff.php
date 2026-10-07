<?php
/**
 * V7 recheck (Collaudatore ad Hoc): compares the security emails captured by the V4 regression on
 * the first V7 commit (2836697) and on the recheck commit, after normalising only the date
 * (ISO "2026-10-07 18:52" or localised "7 Oct 2026, 18:52"), whitespace and HTML entities.
 * Usage: php verify-v7.maildiff.php <captured-old.json> <captured-new.json>
 */
declare(strict_types=1);
function load(string $f): array { $o = []; foreach (file($f, FILE_IGNORE_NEW_LINES) as $l) { $j = json_decode($l, true); if ($j) $o[$j['tag']] = $j; } return $o; }
function norm(string $s): string
{
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = preg_replace('/(\d{4}-\d{2}-\d{2}|\d{1,2}\s+\p{L}{3,}\.?\s+\d{4},?)\s+\d{2}:\d{2}/u', '<DATE>', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}
[$_, $a, $b] = $argv;
$A = load($a); $B = load($b); $ok = true;
foreach ($A as $tag => $m) {
    if (! isset($B[$tag])) { echo "[FAIL] $tag missing in new capture\n"; $ok = false; continue; }
    $n = $B[$tag];
    $same = $m['Subject'] === $n['Subject'] && $m['From'] === $n['From'] && $m['To'] === $n['To'] && norm($m['Text']) === norm($n['Text']) && norm($m['HTML']) === norm($n['HTML']);
    $ok = $ok && $same;
    preg_match('/(\d{4}-\d{2}-\d{2}|\d{1,2}\s+\p{L}{3,}\.?\s+\d{4},?)\s+\d{2}:\d{2}(\s+UTC)?/u', $n['Text'], $d);
    printf("[%s] %-18s same subject, sender, recipients, text and HTML apart from the date; new date: %s\n", $same ? 'PASS' : 'FAIL', $tag, $d[0] ?? '(none)');
    if (! $same) { echo '         old: ' . substr(norm($m['Text']), 0, 300) . "\n         new: " . substr(norm($n['Text']), 0, 300) . "\n"; }
}
exit($ok ? 0 : 1);
