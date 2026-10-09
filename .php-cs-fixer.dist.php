<?php

declare(strict_types=1);

$finder = PhpCsFixer\Finder::create()
    ->in([
             __DIR__ . '/src',
             __DIR__ . '/runtime',
             __DIR__ . '/infrastructure',
             __DIR__ . '/bootstrap',
             __DIR__ . '/bin',
             __DIR__ . '/public',
             __DIR__ . '/tests',
             __DIR__ . '/tools',
         ])
    ->name('*.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRiskyAllowed(true)
    ->setRules([
                   '@PER-CS3x0'                   => true,
                   'declare_strict_types'         => true,
                   'no_unused_imports'            => true,
                   'ordered_imports'              => true,
                   'fully_qualified_strict_types' => [
                       'import_symbols' => true,
                   ],
                   'global_namespace_import'      => [
                       'import_classes'   => true,
                       'import_functions' => null,
                       'import_constants' => null,
                   ],
                   'strict_param'                 => true,
               ])
    ->setFinder($finder);
