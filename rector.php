<?php

declare(strict_types=1);

/**
 * Maximum relevant Rector scan for the vanilla PHP 8.3 interview application.
 *
 * Run in dry-run mode to review changes before applying them. Vendor-specific
 * Laravel/Symfony/Doctrine sets are deliberately excluded.
 */

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\Config\RectorConfig;
use Rector\Naming\Rector\Assign\RenameVariableToMatchMethodCallReturnTypeRector;
use Rector\Naming\Rector\Class_\RenamePropertyToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameParamToMatchTypeRector;
use Rector\Naming\Rector\ClassMethod\RenameVariableToMatchNewTypeRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchExprVariableRector;
use Rector\Naming\Rector\Foreach_\RenameForeachValueVariableToMatchMethodCallReturnTypeRector;
use Rector\ValueObject\PhpVersion;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/runtime',
        __DIR__ . '/infrastructure',
        __DIR__ . '/bootstrap',
        __DIR__ . '/bin',
        __DIR__ . '/public',
        __DIR__ . '/tests',
        __DIR__ . '/tools',
    ])
    ->withPhpVersion(PhpVersion::PHP_83)
    ->withPhpSets(php83: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        typeDeclarationDocblocks: true,
        privatization: true,
        // Identifier names are a deliberate author choice, not a type-derived rewrite.
        naming: false,
        namedArgs: true,
        instanceOf: true,
        if: true,
        earlyReturn: true,
        rectorPreset: true,
        phpunitCodeQuality: true,
        phpunitNarrowAsserts: true,
        phpunitMockToStub: true,
    )
    // Keep existing public named-argument contracts, domain vocabulary and local names intact.
    // This explicit blocklist also guards against rename rules introduced by other presets.
    ->withSkip([
        RenamePropertyToMatchTypeRector::class,
        RenameParamToMatchTypeRector::class,
        RenameVariableToMatchNewTypeRector::class,
        RenameVariableToMatchMethodCallReturnTypeRector::class,
        RenameForeachValueVariableToMatchExprVariableRector::class,
        RenameForeachValueVariableToMatchMethodCallReturnTypeRector::class,
        CatchExceptionNameMatchingTypeRector::class,
    ])
    ->withComposerBased(phpunit: true);
