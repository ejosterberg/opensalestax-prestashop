<?php

// SPDX-License-Identifier: Apache-2.0 OR GPL-2.0-or-later

declare(strict_types=1);

$finder = (new PhpCsFixer\Finder())
    ->in([__DIR__ . '/src', __DIR__ . '/tests'])
    ->name('*.php');

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12'                => true,
        'declare_strict_types'  => true,
        'array_syntax'          => ['syntax' => 'short'],
        'no_unused_imports'     => true,
        'ordered_imports'       => ['sort_algorithm' => 'alpha'],
        'single_quote'          => true,
    ])
    ->setRiskyAllowed(true)
    ->setFinder($finder);
