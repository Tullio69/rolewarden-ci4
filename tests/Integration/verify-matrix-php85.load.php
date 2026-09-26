<?php
// Loads (links) every class-like declaration under the module's src/ with E_ALL,
// to surface link-time deprecations that `php -l` cannot see. No DB, no app boot.
error_reporting(E_ALL);
$issues = [];
set_error_handler(function ($no, $str, $file, $line) use (&$issues) { $issues[] = "$no $str @ $file:$line"; return true; });
[$app, $src] = [$argv[1], $argv[2]];
require $app . '/vendor/autoload.php';
defined('APPPATH') || define('APPPATH', $app . '/app/');
defined('ROOTPATH') || define('ROOTPATH', $app . '/');
$n = 0; $skipped = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src)) as $f) {
    if ($f->getExtension() !== 'php') continue;
    $t = PhpToken::tokenize(file_get_contents($f->getPathname()));
    $ns = ''; $cls = null;
    foreach ($t as $i => $tok) {
        if ($tok->is(T_NAMESPACE)) { $ns = trim($t[$i + 2]->text ?? ''); }
        if ($tok->is([T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM]) && ($t[$i - 1]->text ?? '') !== '::') {
            for ($j = $i + 1; isset($t[$j]); $j++) { if ($t[$j]->is(T_STRING)) { $cls = $t[$j]->text; break; } if ($t[$j]->text === '(' || $t[$j]->text === '{') break; }
            if ($cls) break;
        }
    }
    if (! $cls) { $skipped[] = substr($f->getPathname(), strlen($src)); continue; }
    $fq = ltrim($ns . '\\' . $cls, '\\');
    try {
        if (! (class_exists($fq) || interface_exists($fq) || trait_exists($fq) || enum_exists($fq))) { require_once $f->getPathname(); }
        $ok = class_exists($fq, false) || interface_exists($fq, false) || trait_exists($fq, false) || enum_exists($fq, false);
        if ($ok) { $n++; } else { $issues[] = "not declared: $fq"; }
    } catch (Throwable $e) {
        $issues[] = sprintf('throw loading %s: %s: %s', $fq, get_class($e), $e->getMessage());
    }
}
echo "loaded=$n skipped(no class)=" . count($skipped) . "\n";
echo implode("\n", $issues) . "\n";
echo 'issues=' . count($issues) . "\n";
