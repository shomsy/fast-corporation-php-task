<?php

declare(strict_types=1);

/**
 * Maximum relevant Rector scan for the vanilla PHP 8.3 interview application.
 *
 * Run in dry-run mode to review changes before applying them. Vendor-specific
 * Laravel/Symfony/Doctrine sets are deliberately excluded.
 */

use Rector\CodingStyle\Rector\Catch_\CatchExceptionNameMatchingTypeRector;
use Rector\CodingStyle\Rector\ClassLike\NewlineBetweenClassLikeStmtsRector;
use Rector\CodingStyle\Rector\ClassMethod\NewlineBeforeNewAssignSetRector;
use Rector\CodingStyle\Rector\Stmt\NewlineAfterStatementRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\CodingStyle\Rector\PostInc\PostIncDecToPreIncDecRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
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
        deadCode                : true,
        codeQuality             : true,
        // MOI owns visual formatting; Rector must not invent a competing style.
        codingStyle             : false,
        typeDeclarations        : true,
        typeDeclarationDocblocks: true,
        privatization           : true,
        // Identifier names are a deliberate author choice, not a type-derived rewrite.
        naming                  : false,
        namedArgs               : true,
        instanceOf              : true,
        if                      : true,
        earlyReturn             : true,
        rectorPreset            : true,
        phpunitCodeQuality      : true,
        phpunitNarrowAsserts    : true,
        phpunitMockToStub       : true,
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
                   // Rector quality presets must not override MOI whitespace and test style.
                   NewlineAfterStatementRector::class,
                   NewlineBeforeNewAssignSetRector::class,
                   NewlineBetweenClassLikeStmtsRector::class,
                   PreferPHPUnitThisCallRector::class,
                   // Project requires explicit @var contracts in comprehensive DocBlocks.
                   RemoveUselessVarTagRector::class,
                   // Preserve PHPDoc array-shapes attached to file-level return expressions,
                   // lexical template parameters, and author-chosen operator spelling.
                   RemoveNonExistingVarAnnotationRector::class,
                   RemoveUnusedPublicMethodParameterRector::class,
                   PostIncDecToPreIncDecRector::class,
               ])
    ->withComposerBased(phpunit: true);
