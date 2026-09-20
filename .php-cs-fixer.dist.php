<?php

/**
 * Coding style for the RoleWarden package.
 *
 * PSR-12 plus a few rules that keep diffs small and imports tidy.
 * Views and language files are left alone: they are mostly markup and arrays.
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->exclude(['Views', 'Language'])
    ->append([__FILE__]);

return (new Config())
    ->setRiskyAllowed(false)
    ->setRules([
        '@PSR12'                     => true,
        'array_syntax'               => ['syntax' => 'short'],
        'binary_operator_spaces'     => ['default' => 'single_space'],
        'concat_space'               => ['spacing' => 'one'],
        'no_unused_imports'          => true,
        'ordered_imports'            => ['sort_algorithm' => 'alpha'],
        'single_quote'               => true,
        'trailing_comma_in_multiline' => true,
    ])
    ->setFinder($finder);
