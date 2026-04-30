<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony'                    => true,
        'declare_strict_types'        => true,
        'ordered_imports'             => ['sort_algorithm' => 'alpha'],
        'no_unused_imports'           => true,
        'array_syntax'                => ['syntax' => 'short'],
        'trailing_comma_in_multiline' => ['elements' => ['arrays', 'parameters']],
    ])
    ->setFinder(
        Finder::create()
            ->in(__DIR__ . '/src')
            ->name('*.php')
    );
